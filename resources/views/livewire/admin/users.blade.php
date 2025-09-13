<?php
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Enums\UserRole;

new class extends Component {
    use WithPagination;

    // filters/sort
    public string $search = '';
    public string $roleFilter = 'any'; // any|standard|corporate|administrator
    public string $sort = 'name';
    public string $direction = 'asc';

    // pagination
    protected string $pageName = 'adminUsers';

    // impersonation state
    public bool $alreadyImpersonating = false;

    public function mount(): void
    {
        $this->alreadyImpersonating = session()->has('impersonator_id');
    }

    // keep pager in sync when filters/sort change
    public function updatingSearch(){ $this->resetPage($this->pageName); }
    public function updatingRoleFilter(){ $this->resetPage($this->pageName); }
    public function updatingSort(){ $this->resetPage($this->pageName); }
    public function updatingDirection(){ $this->resetPage($this->pageName); }

    // Pager helpers (match Loadouts)
    public function goto(int $page): void { $this->gotoPage($page, $this->pageName); }
    public function prevPage(): void      { $this->previousPage($this->pageName); }
    public function nextPage(): void      { $this->nextPage($this->pageName); }

    /** Paging window for page buttons (same pattern as Loadouts) */
    public function getPageWindowProperty(): array
    {
        $p = $this->users;
        $window  = 2;
        $current = max(1, (int) $p->currentPage());
        $last    = max(1, (int) $p->lastPage());
        $start   = max(1, $current - $window);
        $end     = min($last, $current + $window);
        return compact('current','last','start','end');
    }

    /** Start impersonating selected user */
    public function impersonate(int $userId): void
    {
        // prevent odd cases
        if (Auth::id() === $userId) {
            $this->dispatch('toast', type:'warning', message:'You are already this user.');
            return;
        }
        if (!session()->has('impersonator_id')) {
            session(['impersonator_id' => Auth::id()]);
        }

        Auth::loginUsingId($userId);
        $this->alreadyImpersonating = true;

        $this->dispatch('toast', type:'success', message:'Now impersonating user.');
        // send admin to a normal area
        $this->redirectRoute('dashboard', navigate: true);
    }

    /** Stop impersonating and return to admin */
    public function stopImpersonating(): void
    {
        if (session()->has('impersonator_id')) {
            $adminId = (int) session('impersonator_id');
            session()->forget('impersonator_id');
            Auth::loginUsingId($adminId);
        }
        $this->alreadyImpersonating = false;

        $this->dispatch('toast', type:'success', message:'Impersonation ended.');
        $this->redirectRoute('admin.users', navigate: true);
    }

    public function setRole(int $userId, string $role): void
    {
        abort_unless(in_array($role, array_column(UserRole::cases(),'value'), true), 422);

        $u = User::findOrFail($userId);
        $u->update(['role' => $role]);

        $this->dispatch('toast', type:'success', message:"Role updated to {$role}");
    }

    public function toggleActive(int $userId): void
    {
        $u = User::findOrFail($userId);
        $u->update(['is_active' => !$u->is_active]);
        $this->dispatch('toast', type:'success', message: $u->is_active ? 'User activated' : 'User deactivated');
    }

    public function getUsersProperty()
    {
        return User::query()
            ->when($this->search, fn($q) =>
                $q->where(fn($qq) =>
                    $qq->where('name','like',"%{$this->search}%")
                       ->orWhere('email','like',"%{$this->search}%")
                )
            )
            ->when($this->roleFilter !== 'any', fn($q) =>
                $q->where('role', $this->roleFilter)
            )
            ->orderBy($this->sort, $this->direction)
            ->paginate(10, pageName: $this->pageName);
    }

    /** Convenience getter for banner text while impersonating */
    public function getImpersonatedUserProperty(): ?User
    {
        return $this->alreadyImpersonating ? User::find(Auth::id()) : null;
    }
}; ?>

<section class="mx-auto max-w-7xl">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-base font-semibold text-gray-900 dark:text-white">Users</h1>
      <p class="text-sm text-gray-600 dark:text-gray-300">Manage roles, activation, and impersonation.</p>

      @if($this->alreadyImpersonating && $this->impersonatedUser)
        <div class="mt-2 inline-flex items-center gap-3 rounded-lg bg-amber-500/10 px-3 py-2 text-amber-800 ring-1 ring-amber-500/20 dark:text-amber-200">
            <span class="text-sm">Impersonating: <strong>{{ $this->impersonatedUser->name }}</strong> ({{ $this->impersonatedUser->email }})</span>
            <flux:button size="xs" variant="ghost" wire:click="stopImpersonating">Stop</flux:button>
        </div>
      @endif
    </div>

    <div class="flex gap-2">
      <flux:input placeholder="Search name or email…" wire:model.live.debounce.300ms="search" />
      <flux:select wire:model.live="roleFilter">
        <option value="any">All roles</option>
        <option value="standard">Standard</option>
        <option value="corporate">Corporate</option>
        <option value="administrator">Administrator</option>
      </flux:select>
    </div>
  </div>

  <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
    <table class="w-full text-left">
      <thead class="bg-white dark:bg-gray-900">
        <tr>
          <th class="py-3.5 pl-4 pr-3 text-sm font-semibold">Name</th>
          <th class="px-3 py-3.5 text-sm font-semibold">Email</th>
          <th class="px-3 py-3.5 text-sm font-semibold">Role</th>
          <th class="px-3 py-3.5 text-sm font-semibold">Active</th>
          <th class="py-3.5 pl-3 pr-4"><span class="sr-only">Actions</span></th>
        </tr>
      </thead>

      <tbody class="divide-y divide-gray-100 dark:divide-white/10">
        @forelse($this->users as $u)
        <tr>
          <td class="py-4 pl-4 pr-3 text-sm font-medium">{{ $u->name }}</td>
          <td class="px-3 py-4 text-sm text-gray-500">{{ $u->email }}</td>

          <td class="px-3 py-4 text-sm">
            <flux:select class="min-w-40" wire:change="setRole({{ $u->id }}, $event.target.value)">
              @foreach(\App\Enums\UserRole::cases() as $r)
                <option value="{{ $r->value }}" @selected($u->role->value === $r->value)>{{ ucfirst($r->value) }}</option>
              @endforeach
            </flux:select>
          </td>

          <td class="px-3 py-4 text-sm">
            <flux:switch :checked="$u->is_active" wire:click="toggleActive({{ $u->id }})" />
          </td>

          <td class="py-4 pl-3 pr-4 text-right text-sm space-x-2">
            {{-- Impersonate / Stop (disabled ternaries to avoid @disabled compile issue) --}}
            <flux:button
                type="button"
                variant="ghost"
                wire:click="impersonate({{ $u->id }})"
                :disabled="$alreadyImpersonating || $u->id === auth()->id()"
            >
              Impersonate
            </flux:button>

            <flux:button
                type="button"
                variant="ghost"
                wire:click="stopImpersonating"
                :disabled="!$alreadyImpersonating"
            >
              Stop
            </flux:button>

          </td>
        </tr>
        @empty
        <tr>
            <td colspan="5" class="py-8 px-4 text-sm text-gray-500 dark:text-gray-400">
              No users match the current filters.
            </td>
        </tr>
        @endforelse
      </tbody>
    </table>

    {{-- Pagination footer (same UX as Loadouts) --}}
    @php($p = $this->users)
    @php($w = $this->pageWindow)
    <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 dark:border-white/10 dark:bg-transparent">
        <!-- Mobile Prev/Next -->
        <div class="flex flex-1 justify-between sm:hidden">
            <button wire:click="prevPage"
                    {{ $p->onFirstPage() ? 'disabled' : '' }}
                    class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10">
                Previous
            </button>
            <button wire:click="nextPage"
                    {{ $p->hasMorePages() ? '' : 'disabled' }}
                    class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10">
                Next
            </button>
        </div>

        <!-- Desktop pager -->
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
                    <button wire:click="prevPage"
                            class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:inset-ring-gray-700 dark:hover:bg-white/5"
                            {{ $p->onFirstPage() ? 'disabled' : '' }}>
                        <span class="sr-only">Previous</span>
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5"><path d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" fill-rule="evenodd" /></svg>
                    </button>

                    @for ($i = $w['start']; $i <= $w['end']; $i++)
                        @if ($i === $w['current'])
                            <span aria-current="page" class="relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:focus-visible:outline-indigo-500">{{ $i }}</span>
                        @else
                            <button wire:click="goto({{ $i }})"
                                    class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:text-gray-200 dark:inset-ring-gray-700 dark:hover:bg-white/5">
                                {{ $i }}
                            </button>
                        @endif
                    @endfor

                    <button wire:click="nextPage"
                            class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:inset-ring-gray-700 dark:hover:bg-white/5"
                            {{ $p->hasMorePages() ? '' : 'disabled' }}>
                        <span class="sr-only">Next</span>
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5"><path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" /></svg>
                    </button>
                </nav>
            </div>
        </div>
    </div>
  </div>
</section>
