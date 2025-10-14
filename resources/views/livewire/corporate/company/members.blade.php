<?php
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    // pagination (match your pattern)
    protected string $pageName = 'companyMembersPage';

    public int $perPage = 10;

    // state
    public Company $company;

    // search & form
    public string $search = '';

    public string $inviteEmail = '';

    public function mount(Company $company): void
    {
        Gate::authorize('update', $company); // company owner or admin
        $this->company = $company;
    }

    public function updatingSearch(): void
    {
        $this->resetPage($this->pageName);
    }

    // pager helpers
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

    // computed: paginated members
    public function getMembersProperty()
    {
        $base = User::query()
            ->where('company_id', $this->company->id)
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
        $p = $this->members;
        $window = 2;
        $current = max(1, (int) $p->currentPage());
        $last = max(1, (int) $p->lastPage());
        $start = max(1, $current - $window);
        $end = min($last, $current + $window);

        return compact('current', 'last', 'start', 'end');
    }

    // actions
    public function addMember(): void
    {
        Gate::authorize('update', $this->company);

        $this->validate([
            'inviteEmail' => ['required', 'email', 'max:255', 'exists:users,email'],
        ], [
            'inviteEmail.exists' => 'No account with that email exists. Ask them to sign up first.',
        ]);

        $email = strtolower(trim($this->inviteEmail));
        /** @var \App\Models\User $user */
        $user = \App\Models\User::where('email', $email)->first();

        if (! $user) {
            $this->dispatch('toast', type: 'warning', message: 'No account with that email exists.');

            return;
        }

        // Block moving someone who already belongs to another company
        if ($user->company_id && $user->company_id !== $this->company->id) {
            $this->dispatch('toast', type: 'warning', message: 'That user already belongs to a different company.');

            return;
        }

        // Set company and promote role to Corporate (but never downgrade/override Admins)
        \Illuminate\Support\Facades\DB::transaction(function () use ($user) {
            $user->company_id = $this->company->id;

            // Promote to Corporate if not already Administrator
            if ($user->role !== \App\Enums\UserRole::Administrator) {
                $user->role = \App\Enums\UserRole::Corporate;
            }

            $user->save();
        });

        $this->inviteEmail = '';
        $this->resetPage($this->pageName);
        $this->dispatch('toast', type: 'success', message: 'User added to company and set to Corporate.');
    }

    public function removeMember(int $userId): void
    {
        Gate::authorize('update', $this->company);

        if ($userId === (int) $this->company->owner_user_id) {
            $this->dispatch('toast', type: 'warning', message: 'Cannot remove the company owner.');

            return;
        }

        /** @var \App\Models\User|null $user */
        $user = \App\Models\User::where('id', $userId)
            ->where('company_id', $this->company->id)
            ->first();

        if (! $user) {
            $this->dispatch('toast', type: 'warning', message: 'User not found.');

            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($user) {
            // Detach from company
            $user->company_id = null;

            // Revert to standard role unless Administrator
            if ($user->role !== \App\Enums\UserRole::Administrator) {
                // NOTE: If your standard role is different, change the enum below.
                $user->role = \App\Enums\UserRole::Member;
            }

            $user->save();
        });

        $this->dispatch('toast', type: 'success', message: 'User removed and role reverted to standard.');
    }
};
?>

<section class="w-full">
  <div class="mx-auto max-w-7xl">
    {{-- Header --}}
    <div class="sm:flex sm:items-center">
      <div class="sm:flex-auto">
        <h1 class="text-base font-semibold text-gray-900 dark:text-white">
          Company Members — {{ $company->company_name ?? 'Company' }}
        </h1>
        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
          Add or remove users who can access your company’s leagues.
        </p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none"></div>
    </div>

    {{-- Invite/Add --}}
    <div class="mt-4 max-w-xl">
      <form wire:submit.prevent="addMember" class="flex gap-2">
        <flux:input type="email" wire:model.defer="inviteEmail" placeholder="user@example.com" class="w-full" />
        <flux:button type="submit" variant="primary" color="indigo" icon="user-plus">
          Add to company
        </flux:button>
      </form>
      @error('inviteEmail') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror

      <div class="mt-4 max-w-sm">
        <flux:input icon="magnifying-glass" placeholder="Search members…" wire:model.live.debounce.300ms="search" />
      </div>
    </div>

    {{-- Table --}}
    <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
      <table class="w-full text-left">
        <thead class="bg-white dark:bg-gray-900">
          <tr>
            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Name</th>
            <th class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 sm:table-cell dark:text-white">Email</th>
            <th class="py-3.5 pl-3 pr-4"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
          @forelse($this->members as $m)
            <tr>
              <td class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white">
                {{ $m->name }}
                @if ($m->id === $company->owner_user_id)
                  <span class="ml-2 text-xs rounded bg-amber-500/10 px-2 py-0.5 text-amber-600 ring-1 ring-amber-600/20 dark:text-amber-300">Owner</span>
                @endif
              </td>
              <td class="hidden px-3 py-4 text-sm text-gray-500 sm:table-cell dark:text-gray-400">
                {{ $m->email }}
              </td>
              <td class="py-4 pl-3 pr-4 text-right text-sm font-medium">
                @if ($m->id !== $company->owner_user_id)
                  <flux:button variant="ghost" size="xs" icon="trash"
                               class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300"
                               wire:click="removeMember({{ $m->id }})">
                    <span class="sr-only">Remove {{ $m->email }}</span>
                  </flux:button>
                @else
                  <span class="text-xs opacity-60">—</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="3" class="py-8 px-4 text-sm text-gray-500 dark:text-gray-400">
                No members yet. Add an email above to invite a user to your company.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>

      {{-- Pager --}}
      @php($p = $this->members)
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
              <button wire:click="prevPage" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:inset-ring-gray-700 dark:hover:bg-white/5" @disabled($p->onFirstPage())>
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
