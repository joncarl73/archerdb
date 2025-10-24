<?php
use App\Models\League;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    // pagination
    protected string $pageName = 'leagueAccessPage';

    public int $perPage = 10;

    // state
    public League $league;

    // search & form
    public string $search = '';

    // member dropdown instead of email
    public $selectedMemberId = null;

    public string $collabRole = 'manager'; // manager | owner

    // hydrated list of available (non-collaborator) company members
    public array $companyMembers = [];

    public function mount(League $league): void
    {
        Gate::authorize('view', $league);
        $this->league = $league;
        $this->refreshCompanyMembers();
    }

    protected function refreshCompanyMembers(): void
    {
        $companyId = $this->league->company_id;

        $collaboratorIds = $this->league
            ->collaborators()
            ->pluck('users.id')
            ->all();

        $ownerId = optional($this->league->company)->owner_user_id;

        $query = User::query()
            ->where('company_id', $companyId)
            ->when($ownerId, fn ($q) => $q->where('id', '<>', $ownerId))
            ->when(! empty($collaboratorIds), fn ($q) => $q->whereNotIn('id', $collaboratorIds))
            ->orderBy('name');

        $this->companyMembers = $query
            ->get(['id', 'name', 'email'])
            ->map(fn ($u) => [
                'id' => (int) $u->id,
                'label' => trim(($u->name ?: $u->email).' — '.$u->email),
            ])
            ->all();

        if ($this->selectedMemberId && ! collect($this->companyMembers)->pluck('id')->contains($this->selectedMemberId)) {
            $this->selectedMemberId = null;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage($this->pageName);
    }

    public function goto(int $page): void
    {
        $this->gotoPage($page, $this->pageName);
    }

    public function prevPage(): void
    {
        $this->previousPage($this->pageName);
    }

    public function nextPage(): void
    {
        $this->nextPage($this->pageName);
    }

    // computed: paginated collaborators
    public function getCollaboratorsProperty()
    {
        $base = $this->league->collaborators()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
            ))
            ->orderBy('name');

        $total = (clone $base)->count();
        $lastPage = max(1, (int) ceil($total / $this->perPage));
        $requested = (int) ($this->paginators[$this->pageName] ?? 1);
        $page = min(max(1, $requested), $lastPage);
        if ($requested !== $page) {
            $this->setPage($page, $this->pageName);
        }

        return $base->paginate($this->perPage, ['*'], $this->pageName, $page);
    }

    public function getPageWindowProperty(): array
    {
        $p = $this->collaborators;
        $window = 2;
        $current = max(1, (int) $p->currentPage());
        $last = max(1, (int) $p->lastPage());
        $start = max(1, $current - $window);
        $end = min($last, $current + $window);

        return compact('current', 'last', 'start', 'end');
    }

    // actions
    public function addCollaborator(): void
    {
        Gate::authorize('update', $this->league);

        $this->validate([
            'selectedMemberId' => ['required', 'integer', 'exists:users,id'],
            'collabRole' => ['required', 'in:owner,manager'],
        ]);

        /** @var User $user */
        $user = User::find((int) $this->selectedMemberId);

        if ($user->company_id !== $this->league->company_id) {
            $this->dispatch('toast', type: 'warning', message: 'User must belong to this company to be added as a collaborator.');

            return;
        }

        $ownerId = optional($this->league->company)->owner_user_id;
        if ($ownerId && (int) $user->id === (int) $ownerId) {
            $this->dispatch('toast', type: 'info', message: 'Company owner already has full access.');

            return;
        }

        if ($this->league->collaborators()->where('users.id', $user->id)->exists()) {
            $this->dispatch('toast', type: 'info', message: 'That user is already a collaborator.');

            return;
        }

        $this->league->collaborators()->syncWithoutDetaching([$user->id => ['role' => $this->collabRole]]);

        $this->selectedMemberId = null;
        $this->collabRole = 'manager';
        $this->resetPage($this->pageName);
        $this->refreshCompanyMembers();

        $this->dispatch('toast', type: 'success', message: 'Collaborator added.');
    }

    public function changeRole(int $userId, string $role): void
    {
        Gate::authorize('update', $this->league);

        if (! in_array($role, ['owner', 'manager'], true)) {
            $this->dispatch('toast', type: 'warning', message: 'Invalid role.');

            return;
        }

        if ($role !== 'owner') {
            $isOwner = $this->league->collaborators()->where('users.id', $userId)->wherePivot('role', 'owner')->exists();
            $ownerCount = $this->league->collaborators()->wherePivot('role', 'owner')->count();
            if ($isOwner && $ownerCount <= 1) {
                $this->dispatch('toast', type: 'warning', message: 'Cannot demote the last owner.');

                return;
            }
        }

        $this->league->collaborators()->updateExistingPivot($userId, ['role' => $role]);
        $this->dispatch('toast', type: 'success', message: 'Role updated.');
    }

    public function removeCollaborator(int $userId): void
    {
        Gate::authorize('update', $this->league);

        $isOwner = $this->league->collaborators()->where('users.id', $userId)->wherePivot('role', 'owner')->exists();
        $ownerCount = $this->league->collaborators()->wherePivot('role', 'owner')->count();
        if ($isOwner && $ownerCount <= 1) {
            $this->dispatch('toast', type: 'warning', message: 'Cannot remove the last owner.');

            return;
        }

        $this->league->collaborators()->detach($userId);
        $this->dispatch('toast', type: 'success', message: 'Collaborator removed.');
        $this->refreshCompanyMembers();
    }
};
?>  <!-- IMPORTANT: do not remove this closing tag -->
<section class="w-full">
  <div class="mx-auto max-w-7xl">
    {{-- Header --}}
    <div class="sm:flex sm:items-center">
      <div class="sm:flex-auto">
        <h1 class="text-base font-semibold text-gray-900 dark:text-white">
          {{ $league->title }} — Access
        </h1>
        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
          Share this league with owners or managers. Company owners have full access automatically.
        </p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <flux:button as="a" href="{{ route('corporate.leagues.show', $league) }}" variant="ghost">
          ← Back to league
        </flux:button>
      </div>
    </div>

    {{-- Add collaborator (owner/admin only) --}}
    @can('update', $league)
      @if (!empty($this->companyMembers))
        <div class="mt-6 max-w-2xl">
          <form wire:submit.prevent="addCollaborator" class="grid gap-3 md:grid-cols-3">
            {{-- Company members dropdown --}}
            <flux:select wire:model.live="selectedMemberId" class="md:col-span-2">
              <option value="">Select a company member…</option>
              @foreach ($this->companyMembers as $m)
                <option value="{{ $m['id'] }}">{{ $m['label'] }}</option>
              @endforeach
            </flux:select>

            <div class="flex gap-2">
              <flux:select wire:model="collabRole" class="w-full">
                <option value="manager">Manager</option>
                <option value="owner">Owner</option>
              </flux:select>

              {{-- Avoid attribute directives like @disabled(...) --}}
              @if (!empty($this->selectedMemberId))
                <flux:button type="submit" variant="primary" color="indigo" icon="user-plus">
                  Share
                </flux:button>
              @else
                <flux:button type="button" variant="primary" color="indigo" icon="user-plus" disabled>
                  Share
                </flux:button>
              @endif
            </div>
          </form>

          @error('selectedMemberId')
            <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text>
          @enderror
          @error('collabRole')
            <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text>
          @enderror

          <div class="mt-4 max-w-sm">
            <flux:input icon="magnifying-glass" placeholder="Search collaborators…" wire:model.live.debounce.300ms="search" />
          </div>
        </div>
      @endif
    @endcan

    {{-- Table --}}
    <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
      <table class="w-full text-left">
        <thead class="bg-white dark:bg-gray-900">
          <tr>
            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white">User</th>
            <th class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 sm:table-cell dark:text-white">Email</th>
            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Role</th>
            <th class="py-3.5 pl-3 pr-4"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
          @forelse($this->collaborators as $u)
            <tr>
              <td class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white">
                {{ $u->name }}
              </td>
              <td class="hidden px-3 py-4 text-sm text-gray-500 sm:table-cell dark:text-gray-400">
                {{ $u->email }}
              </td>
              <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                @can('update', $league)
                  <flux:select wire:change="changeRole({{ $u->id }}, $event.target.value)">
                    <option value="manager" @selected($u->pivot->role==='manager')>Manager</option>
                    <option value="owner"   @selected($u->pivot->role==='owner')>Owner</option>
                  </flux:select>
                @else
                  <span class="badge">{{ ucfirst($u->pivot->role) }}</span>
                @endcan
              </td>
              <td class="py-4 pl-3 pr-4 text-right text-sm font-medium">
                @can('update', $league)
                  <flux:button
                    variant="ghost"
                    size="xs"
                    icon="trash"
                    class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300"
                    wire:click="removeCollaborator({{ $u->id }})"
                  >
                    <span class="sr-only">Remove {{ $u->email }}</span>
                  </flux:button>
                @else
                  <span class="text-xs opacity-60">—</span>
                @endcan
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="py-8 px-4 text-sm text-gray-500 dark:text-gray-400">
                No collaborators yet.
                @can('update', $league)
                  @if (empty($this->companyMembers))
                    Company has no available members to add. Go to <em>Company Members</em> to invite them first.
                  @else
                    Add a user above.
                  @endif
                @endcan
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>

      {{-- Pager --}}
      @php($p = $this->collaborators)
      @php($w = $this->pageWindow)
      <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 dark:border-white/10 dark:bg-transparent">
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-gray-700 dark:text-gray-300">
              Showing <span class="font-medium">{{ $p->firstItem() ?? 0 }}</span>
              to <span class="font-medium">{{ $p->lastItem() ?? 0 }}</span>
              of <span class="font-medium">{{ $p->total() }}</span> results
            </p>
          </div>
          <div>
            <nav aria-label="Pagination" class="isolate inline-flex -space-x-px rounded-md shadow-xs dark:shadow-none">
              <button wire:click="prevPage" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 dark:inset-ring-gray-700 dark:hover:bg-white/5" @disabled($p->onFirstPage())>
                <span class="sr-only">Previous</span>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5"><path d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" fill-rule="evenodd" /></svg>
              </button>
              @for ($i = $w['start']; $i <= $w['end']; $i++)
                @if ($i === $w['current'])
                  <span aria-current="page" class="relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 dark:bg-indigo-500">{{ $i }}</span>
                @else
                  <button wire:click="goto({{ $i }})" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 dark:text-gray-200 dark:inset-ring-gray-700 dark:hover:bg-white/5">{{ $i }}</button>
                @endif
              @endfor
              <button wire:click="nextPage" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 dark:inset-ring-gray-700 dark:hover:bg-white/5" @disabled(!$p->hasMorePages())>
                <span class="sr-only">Next</span>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5"><path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" /></svg>
              </button>
            </nav>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
