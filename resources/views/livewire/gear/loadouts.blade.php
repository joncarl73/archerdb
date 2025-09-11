<?php
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    // keep pagination state separate (so it doesn’t collide with other components)
    protected string $pageName = 'loadoutsPage';

    public string $sort = 'updated_at';
    public string $direction = 'desc';

    public function updatingSort()    { $this->resetPage($this->pageName); }
    public function updatingDirection(){ $this->resetPage($this->pageName); }

    public function goto(int $page): void { $this->gotoPage($page, $this->pageName); }
    public function prevPage(): void      { $this->previousPage($this->pageName); }
    public function nextPage(): void      { $this->nextPage($this->pageName); }

    public function getLoadoutsProperty()
    {
        return Auth::user()
            ->loadouts()
            ->withCount('items')
            ->orderBy($this->sort, $this->direction)
            ->paginate(5, pageName: $this->pageName);
    }

    public function getPageWindowProperty(): array
    {
        $p = $this->loadouts;              // uses the accessor above
        $window  = 2;
        $current = max(1, (int) $p->currentPage());
        $last    = max(1, (int) $p->lastPage());
        $start   = max(1, $current - $window);
        $end     = min($last, $current + $window);

        return compact('current','last','start','end');
    }

}; ?>

<section class="w-full">
    <div class="mx-auto max-w-7xl">
        <!-- Header row -->
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">Loadouts</h1>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    Create and manage your archery equipment setups. Choose a primary loadout for quick selection later.
                </p>
            </div>
            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                {{-- Wire this to your create UX (page or slide-over). For now just link to this route or noop. --}}
                <a href="{{ route('gear.loadouts') }}" wire:navigate
                class="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-xs
                        hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600
                        dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                    Add loadout
                </a>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="mt-8">
        <div class="mx-auto max-w-7xl">
            <!-- Outer border + rounded; works in light/dark -->
            <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
                <table class="w-full text-left">
                    <thead class="bg-white dark:bg-gray-900">
                        <tr>
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                Name
                            </th>
                            <th scope="col" class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 sm:table-cell dark:text-white">Items</th>
                            <th scope="col" class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 md:table-cell dark:text-white">Updated</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Primary</th>
                            <th scope="col" class="py-3.5 pl-3 pr-4"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>

                    {{-- Use divide-y for row separators (remove the absolute border divs) --}}
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse($this->loadouts as $ld)
                            <tr>
                                <td class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $ld->name }}
                                </td>
                                <td class="hidden px-3 py-4 text-sm text-gray-500 sm:table-cell dark:text-gray-400">
                                    {{ $ld->items_count }} item{{ $ld->items_count === 1 ? '' : 's' }}
                                </td>
                                <td class="hidden px-3 py-4 text-sm text-gray-500 md:table-cell dark:text-gray-400">
                                    {{ $ld->updated_at?->format('Y-m-d') }}
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    @if($ld->is_primary)
                                        <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-700/10 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/30">
                                            Primary
                                        </span>
                                    @else
                                        <span class="text-xs opacity-60">—</span>
                                    @endif
                                </td>
                                <td class="py-4 pl-3 pr-4 text-right text-sm font-medium">
                                    <a href="#" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                        Edit<span class="sr-only">, {{ $ld->name }}</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 px-4 text-sm text-gray-500 dark:text-gray-400">
                                    No loadouts yet. Click “Add loadout” to create your first setup.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <!-- Pagination -->
    @php($p = $this->loadouts)
    @php($w = $this->pageWindow)

    <div class="flex items-center justify-between border-t border-gray-200 bg-white py-3 dark:border-white/10 dark:bg-transparent">
        <!-- Mobile Prev/Next -->
        <div class="flex flex-1 justify-between sm:hidden">
            <button wire:click="prevPage" @disabled($p->onFirstPage())
                class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50
                    dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10">
                Previous
            </button>
            <button wire:click="nextPage" @disabled(!$p->hasMorePages())
                class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50
                    dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10">
                Next
            </button>
        </div>

        <!-- Desktop pager -->
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Showing
                    <span class="font-medium">{{ $p->firstItem() ?? 0 }}</span>
                    to
                    <span class="font-medium">{{ $p->lastItem() ?? 0 }}</span>
                    of
                    <span class="font-medium">{{ $p->total() }}</span>
                    results
                </p>
            </div>

            <div>
                <nav aria-label="Pagination" class="isolate inline-flex -space-x-px rounded-md shadow-xs dark:shadow-none">
                    <button wire:click="prevPage"
                        class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:inset-ring-gray-700 dark:hover:bg-white/5"
                        @disabled($p->onFirstPage())>
                        <span class="sr-only">Previous</span>
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
                            <path d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" fill-rule="evenodd" />
                        </svg>
                    </button>

                    
                    @for ($i = $w['start']; $i <= $w['end']; $i++)
                        @if ($i === $w['current'])
                            <span aria-current="page"
                                class="relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20
                                        focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600
                                        dark:bg-indigo-500 dark:focus-visible:outline-indigo-500">
                                {{ $i }}
                            </span>
                        @else
                            <button wire:click="goto({{ $i }})"
                                    class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0
                                        dark:text-gray-200 dark:inset-ring-gray-700 dark:hover:bg-white/5">
                                {{ $i }}
                            </button>
                        @endif
                    @endfor

                    <button wire:click="nextPage"
                        class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:inset-ring-gray-700 dark:hover:bg-white/5"
                        @disabled(!$p->hasMorePages())>
                        <span class="sr-only">Next</span>
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
                            <path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
                        </svg>
                    </button>
                </nav>
            </div>
        </div>
    </div>
</section>
