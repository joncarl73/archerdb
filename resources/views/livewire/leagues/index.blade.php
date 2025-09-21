<?php
use App\Models\League;
use App\Services\LeagueScheduler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    // pagination (match your pattern)
    protected string $pageName = 'leaguesPage';

    public int $perPage = 5;

    // sorting
    public string $sort = 'updated_at';

    public string $direction = 'desc';

    // sheet state
    public bool $showSheet = false;

    public ?int $editingId = null;

    // filters/search
    public string $search = '';

    // form fields
    public string $title = '';

    public ?string $location = null;

    public int $length_weeks = 10;

    public int $day_of_week = 3; // Wednesday default (0=Sun..6=Sat)

    public ?string $start_date = null; // Y-m-d

    public string $type = 'open';      // open | closed

    public bool $is_published = false;

    // NEW: lanes
    public int $lanes_count = 10;          // 1..100

    public string $lane_breakdown = 'single'; // single|ab|abcd

    // NEW: Scoring Defaults
    public int $ends_per_day = 10;

    public int $arrows_per_end = 3;

    // --- lifecycle ---
    public function updatingSort()
    {
        $this->resetPage($this->pageName);
    }

    public function updatingDirection()
    {
        $this->resetPage($this->pageName);
    }

    public function updatingSearch()
    {
        $this->resetPage($this->pageName);
    }

    // pagination helpers (match your loadouts)
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

    // computed: paginated leagues for current corporate user
    public function getLeaguesProperty()
    {
        $base = League::query()
            ->where('owner_id', Auth::id())
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$this->search}%")
                ->orWhere('location', 'like', "%{$this->search}%")
            ))
            ->orderBy($this->sort, $this->direction);

        $total = (clone $base)->count();
        $lastPage = max(1, (int) ceil($total / $this->perPage));

        $requested = (int) ($this->paginators[$this->pageName] ?? 1);
        $page = min(max(1, $requested), $lastPage);
        if ($requested !== $page) {
            $this->setPage($page, $this->pageName);
        }

        return $base->paginate($this->perPage, ['*'], $this->pageName, $page);
    }

    // paging window
    public function getPageWindowProperty(): array
    {
        $p = $this->leagues;
        $window = 2;
        $current = max(1, (int) $p->currentPage());
        $last = max(1, (int) $p->lastPage());
        $start = max(1, $current - $window);
        $end = min($last, $current + $window);

        return compact('current', 'last', 'start', 'end');
    }

    // helpers
    private function positionsPerLane(string $mode): int
    {
        return $mode === 'ab' ? 2 : ($mode === 'abcd' ? 4 : 1);
    }

    private function totalPositions(int $lanes, string $mode): int
    {
        return max(0, $lanes) * $this->positionsPerLane($mode);
    }

    // sheet openers
    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showSheet = true;
    }

    public function openEdit(int $id): void
    {
        $league = League::where('owner_id', Auth::id())->findOrFail($id);
        Gate::authorize('update', $league);

        $this->editingId = $league->id;
        $this->title = $league->title;
        $this->location = $league->location;
        $this->length_weeks = (int) $league->length_weeks;
        $this->day_of_week = (int) $league->day_of_week;
        $this->start_date = optional($league->start_date)->toDateString();
        $this->type = $league->type->value ?? (string) $league->type;
        $this->is_published = (bool) $league->is_published;

        // NEW: lanes
        $this->lanes_count = (int) ($league->lanes_count ?? 10);
        $this->lane_breakdown = $league->lane_breakdown_value ?? 'single'; // ← use accessor
        $this->ends_per_day = (int) ($league->ends_per_day ?? 10);
        $this->arrows_per_end = (int) ($league->arrows_per_end ?? 3);

        $this->showSheet = true;
    }

    // create/update
    public function save(LeagueScheduler $scheduler): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:160'],
            'length_weeks' => ['required', 'integer', 'between:1,52'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_date' => ['required', 'date'],
            'type' => ['required', 'in:open,closed'],
            'is_published' => ['boolean'],
            // NEW:
            'lanes_count' => ['required', 'integer', 'between:1,100'],
            'lane_breakdown' => ['required', 'in:single,ab,abcd'],
            // NEW: Scoring fields
            'ends_per_day' => ['required', 'integer', 'between:1,60'],
            'arrows_per_end' => ['required', 'integer', 'between:1,12'],
        ]);

        if ($this->editingId) {
            $league = League::where('owner_id', Auth::id())->findOrFail($this->editingId);
            Gate::authorize('update', $league);

            $league->update([
                'title' => $this->title,
                'location' => $this->location,
                'length_weeks' => $this->length_weeks,
                'day_of_week' => $this->day_of_week,
                'start_date' => $this->start_date,
                'type' => $this->type,
                'is_published' => $this->is_published,
                // NEW:
                'lanes_count' => $this->lanes_count,
                'lane_breakdown' => $this->lane_breakdown,
                'ends_per_day' => $this->ends_per_day,
                'arrows_per_end' => $this->arrows_per_end,
            ]);

            // refresh weeks on edit
            $scheduler->buildWeeks($league);
        } else {
            $league = League::create([
                'owner_id' => Auth::id(),
                'title' => $this->title,
                'location' => $this->location,
                'length_weeks' => $this->length_weeks,
                'day_of_week' => $this->day_of_week,
                'start_date' => $this->start_date,
                'type' => $this->type,
                'is_published' => $this->is_published,
                // NEW:
                'lanes_count' => $this->lanes_count,
                'lane_breakdown' => $this->lane_breakdown,
            ]);

            // generate weeks on create
            $scheduler->buildWeeks($league);
        }

        $this->showSheet = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'League saved');
    }

    // inline actions
    public function togglePublish(int $id): void
    {
        $league = League::where('owner_id', Auth::id())->findOrFail($id);
        Gate::authorize('update', $league);
        $league->update(['is_published' => ! $league->is_published]);
        $this->dispatch('toast', type: 'success', message: $league->is_published ? 'Published' : 'Unpublished');
    }

    public function delete(int $id): void
    {
        $league = League::where('owner_id', Auth::id())->findOrFail($id);
        Gate::authorize('delete', $league);
        $league->delete();
        $this->dispatch('toast', type: 'success', message: 'League deleted');
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->location = null;
        $this->length_weeks = 10;
        $this->day_of_week = 3;
        $this->start_date = null;
        $this->type = 'open';
        $this->is_published = false;

        // NEW:
        $this->lanes_count = 10;
        $this->lane_breakdown = 'single';
        $this->ends_per_day = 10;
        $this->arrows_per_end = 3;
    }
};
?>

<section class="w-full">
    {{-- Header --}}
    <div class="mx-auto max-w-7xl">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">Leagues</h1>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    Create and manage league competitions. Weeks are auto-generated from start date, weekday, and length.
                </p>
            </div>
            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <button wire:click="openCreate"
                        class="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-xs
                               hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600
                               dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                    New league
                </button>
            </div>
        </div>
        <div class="mt-4 max-w-md">
            <flux:input icon="magnifying-glass" placeholder="Search by title or location…" wire:model.live.debounce.300ms="search" />
        </div>
    </div>

    {{-- Table with outer border (same shell as Loadouts) --}}
    <div class="mt-6">
        <div class="mx-auto max-w-7xl">
            <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
                <table class="w-full text-left">
                    <thead class="bg-white dark:bg-gray-900">
                        <tr>
                            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Title</th>
                            <th class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 sm:table-cell dark:text-white">Type</th>
                            <th class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 md:table-cell dark:text-white">Starts</th>
                            <th class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 md:table-cell dark:text-white">Weeks</th>
                            {{-- NEW: Lanes/Capacity --}}
                            <th class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 lg:table-cell dark:text-white">Lanes</th>
                            <th class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 md:table-cell dark:text-white">Ends × Arrows</th>
                            <th class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 md:table-cell dark:text-white">Status</th>
                            <th class="py-3.5 pl-3 pr-4"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse($this->leagues as $lg)
                            @php
                                $mode = $lg->lane_breakdown_value; // always a string
                                $per  = $mode === 'ab' ? 2 : ($mode === 'abcd' ? 4 : 1);
                                $cap  = max(0, (int)($lg->lanes_count ?? 0)) * $per;
                            @endphp
                            <tr>
                                <td class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white">
                                    <a href="{{ route('corporate.leagues.show', $lg->id) }}"
                                    class="hover:underline hover:text-indigo-600 dark:hover:text-indigo-400">
                                        {{ $lg->title }}
                                    </a>
                                    <div class="text-xs opacity-60">{{ $lg->location ?: '—' }}</div>
                                </td>
                                <td class="hidden px-3 py-4 text-sm text-gray-500 sm:table-cell dark:text-gray-400">
                                    {{ ucfirst($lg->type->value ?? $lg->type) }}
                                </td>
                                <td class="hidden px-3 py-4 text-sm text-gray-500 md:table-cell dark:text-gray-400">
                                    {{ optional($lg->start_date)->format('Y-m-d') ?: '—' }}
                                </td>
                                <td class="hidden px-3 py-4 text-sm text-gray-500 md:table-cell dark:text-gray-400">
                                    {{ $lg->length_weeks }} wk
                                </td>

                                {{-- NEW: lanes and capacity --}}
                                <td class="hidden px-3 py-4 text-sm text-gray-500 lg:table-cell dark:text-gray-400">
                                    {{ (int)($lg->lanes_count ?? 0) }} lanes •
                                    @if($mode === 'ab') A/B
                                    @elseif($mode === 'abcd') A/B/C/D
                                    @else single @endif
                                    <span class="ml-1 text-xs opacity-70">({{ $cap }} positions)</span>
                                </td>

                                <td class="hidden px-3 py-4 text-sm text-gray-500 md:table-cell dark:text-gray-400">
                                    {{ $lg->ends_per_day }}×{{ $lg->arrows_per_end }}
                                    <span class="text-xs opacity-70">({{ $lg->ends_per_day * $lg->arrows_per_end }} total)</span>
                                </td>


                                <td class="hidden px-3 py-4 text-sm text-gray-500 md:table-cell dark:text-gray-400">
                                    @if($lg->is_published)
                                        <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-700/10 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-400/30">
                                            Published
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-700/10 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30">
                                            Draft
                                        </span>
                                    @endif
                                </td>
                                <td class="py-4 pl-3 pr-4 text-right text-sm font-medium">
                                    <div class="inline-flex items-center gap-1.5">
                                        {{-- Edit (sheet) --}}
                                        <flux:button
                                            variant="ghost"
                                            size="xs"
                                            icon="pencil-square"
                                            title="Edit"
                                            wire:click="openEdit({{ $lg->id }})"
                                        >
                                            <span class="sr-only">Edit {{ $lg->title }}</span>
                                        </flux:button>

                                        {{-- Publish toggle --}}
                                        <flux:button
                                            variant="ghost"
                                            size="xs"
                                            icon="{{ $lg->is_published ? 'eye-slash' : 'eye' }}"
                                            title="{{ $lg->is_published ? 'Unpublish' : 'Publish' }}"
                                            wire:click="togglePublish({{ $lg->id }})"
                                        >
                                            <span class="sr-only">{{ $lg->is_published ? 'Unpublish' : 'Publish' }} {{ $lg->title }}</span>
                                        </flux:button>

                                        {{-- Delete --}}
                                        <flux:button
                                            variant="ghost"
                                            size="xs"
                                            icon="trash"
                                            title="Delete"
                                            wire:click="delete({{ $lg->id }})"
                                            class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300"
                                        >
                                            <span class="sr-only">Delete {{ $lg->title }}</span>
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 px-4 text-sm text-gray-500 dark:text-gray-400">
                                    No leagues yet. Click “New league” to create your first one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- Pagination footer (same UX as Loadouts) --}}
                @php($p = $this->leagues)
                @php($w = $this->pageWindow)
                <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 dark:border-white/10 dark:bg-transparent">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <button wire:click="prevPage" @disabled($p->onFirstPage()) class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10">Previous</button>
                        <button wire:click="nextPage" @disabled(!$p->hasMorePages()) class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10">Next</button>
                    </div>

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
                                        <span aria-current="page" class="relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:focus-visible:outline-indigo-500">{{ $i }}</span>
                                    @else
                                        <button wire:click="goto({{ $i }})" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:text-gray-200 dark:inset-ring-gray-700 dark:hover:bg:white/5">{{ $i }}</button>
                                    @endif
                                @endfor

                                <button wire:click="nextPage" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:inset-ring-gray-700 dark:hover:bg-white/5" @disabled(!$p->hasMorePages())>
                                    <span class="sr-only">Next</span>
                                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5"><path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" /></svg>
                                </button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div> {{-- /bordered wrapper --}}
        </div>
    </div>

    {{-- Right "sheet" for create/edit (same UX as Loadouts) --}}
    @if($showSheet)
        <div class="fixed inset-0 z-40">
            <div class="absolute inset-0 bg-black/40" wire:click="$set('showSheet', false)"></div>

            <div class="absolute inset-y-0 right-0 w-full max-w-2xl h-full overflow-y-auto bg-white p-6 shadow-xl dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $editingId ? 'Edit league' : 'Create league' }}
                    </h2>
                    <button class="rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10"
                            wire:click="$set('showSheet', false)">✕</button>
                </div>

                <form wire:submit.prevent="save" class="mt-6 space-y-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <flux:label for="title">League title</flux:label>
                            <flux:input id="title" type="text" wire:model="title" placeholder="Winter Indoor League" />
                            @error('title') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                        </div>
                        <div>
                            <flux:label for="location">Location</flux:label>
                            <flux:input id="location" type="text" wire:model="location" placeholder="Club/Range name" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <flux:label for="length_weeks"># Weeks</flux:label>
                            <flux:input id="length_weeks" type="number" min="1" max="52" wire:model="length_weeks" />
                            @error('length_weeks') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                        </div>
                        <div>
                            <flux:label for="day_of_week">Day of week</flux:label>
                            <flux:select id="day_of_week" wire:model="day_of_week" class="w-full">
                                <option value="0">Sunday</option>
                                <option value="1">Monday</option>
                                <option value="2">Tuesday</option>
                                <option value="3">Wednesday</option>
                                <option value="4">Thursday</option>
                                <option value="5">Friday</option>
                                <option value="6">Saturday</option>
                            </flux:select>
                            @error('day_of_week') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                        </div>
                        <div>
                            <flux:label for="start_date">Start date</flux:label>
                            <flux:input id="start_date" type="date" wire:model="start_date" />
                            @error('start_date') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                        </div>
                    </div>

                    {{-- NEW: Lanes config --}}
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <flux:label for="lanes_count"># Lanes</flux:label>
                            <flux:input id="lanes_count" type="number" min="1" max="100" wire:model="lanes_count" />
                            @error('lanes_count') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <flux:label for="lane_breakdown">Lane breakdown</flux:label>
                            <flux:select id="lane_breakdown" wire:model="lane_breakdown" class="w-full">
                                <option value="single">Single lane (1 per lane)</option>
                                <option value="ab">A/B split (2 per lane)</option>
                                <option value="abcd">A/B/C/D split (4 per lane)</option>
                            </flux:select>
                            @error('lane_breakdown') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror

                            <p class="text-xs opacity-70 mt-1">
                                Total shooting positions:
                                {{ max(1, (int)$lanes_count) * ( $lane_breakdown === 'single' ? 1 : ($lane_breakdown === 'ab' ? 2 : 4)) }}
                            </p>
                        </div>
                    </div>

                    {{-- Scoring config (per league date) --}}
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <flux:label for="ends_per_day"># Ends (per date)</flux:label>
                            <flux:input id="ends_per_day" type="number" min="1" max="60" wire:model="ends_per_day" />
                            @error('ends_per_day') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                        </div>
                        <div>
                            <flux:label for="arrows_per_end">Arrows per end</flux:label>
                            <flux:input id="arrows_per_end" type="number" min="1" max="12" wire:model="arrows_per_end" />
                            @error('arrows_per_end') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror

                            <p class="text-xs opacity-70 mt-1">
                                Total arrows per date:
                                {{ max(1,(int)$ends_per_day) * max(1,(int)$arrows_per_end) }}
                            </p>
                        </div>
                    </div>


                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <flux:label for="type">League type</flux:label>
                            <flux:select id="type" wire:model="type" class="w-full">
                                <option value="open">Open (no membership required)</option>
                                <option value="closed">Closed (members only)</option>
                            </flux:select>
                            @error('type') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                        </div>
                        <div class="flex items-center gap-3">
                            <flux:switch id="is_published" wire:model="is_published" />
                            <flux:label for="is_published">Published</flux:label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <flux:button type="button" variant="ghost" wire:click="$set('showSheet', false)">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create' }}</flux:button>
                    </div>

                    <p class="text-xs opacity-60 pt-2">
                        After saving, weeks are generated based on start date, weekday, and length. You can edit and resave to regenerate.
                    </p>
                </form>
            </div>
        </div>
    @endif
</section>
