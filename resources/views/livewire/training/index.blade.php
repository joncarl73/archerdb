<?php
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use App\Models\{TrainingSession, TrainingEnd, Loadout};

new class extends Component {
    use WithPagination;

    // pagination
    protected string $pageName = 'sessionsPage';
    public int $perPage = 5;

    // sorting
    public string $sort = 'session_at'; // or 'updated_at'
    public string $direction = 'desc';

    // filters
    public string $search = ''; // title/location

    // sheet form state
    public bool $showSheet = false;
    public ?int $editingId = null;

    // form fields
    public ?int $loadout_id = null;
    public ?string $title = null;
    public ?string $session_at = null;     // wire to datetime-local as string
    public ?string $location = null;
    public ?int $distance_m = null;
    public ?string $round_type = null;     // practice|wa18|wa25|vegas300|custom
    public ?string $scoring_system = '10'; // 10|5|none
    public ?int $arrows_per_end = 3;
    public ?int $ends_planned = null;
    public ?string $notes = null;
    public ?int $rpe = null;

    // X ring value (10 or 11)
    public ?int $x_value = 10;

    // dropdown
    public array $loadouts = [];

    // Undo
    public ?array $lastDeleted = null;

    public function mount(): void
    {
        $this->loadouts = Auth::user()->loadouts()
            ->orderBy('is_primary','desc')->orderBy('name')
            ->get(['id','name'])->toArray();
    }

    // keep URL in sync and reset when changing inputs
    public function updatingSearch(){ $this->resetPage($this->pageName); }
    public function updatingSort(){ $this->resetPage($this->pageName); }
    public function updatingDirection(){ $this->resetPage($this->pageName); }

    // pager helpers like Loadouts
    public function goto(int $page): void { $this->gotoPage($page, $this->pageName); }
    public function prevPage(): void      { $this->previousPage($this->pageName); }
    public function nextPage(): void      { $this->nextPage($this->pageName); }

    // CRUD
    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showSheet = true;
    }

    public function openEdit(int $id): void
    {
        $s = Auth::user()->trainingSessions()->findOrFail($id);
        $this->editingId      = $s->id;
        $this->loadout_id     = $s->loadout_id;
        $this->title          = $s->title;
        $this->session_at     = optional($s->session_at)->format('Y-m-d\TH:i');
        $this->location       = $s->location;
        $this->distance_m     = $s->distance_m;
        $this->round_type     = $s->round_type;
        $this->scoring_system = $s->scoring_system ?: '10';
        $this->arrows_per_end = $s->arrows_per_end ?: 3;
        $this->ends_planned   = $s->ends_planned;
        $this->notes          = $s->notes;
        $this->rpe            = $s->rpe;
        $this->x_value        = $s->x_value ?? 10;
        $this->showSheet      = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'loadout_id'     => ['nullable','exists:loadouts,id'],
            'title'          => ['nullable','string','max:120'],
            'session_at'     => ['nullable','date'],
            'location'       => ['nullable','string','max:120'],
            'distance_m'     => ['nullable','integer','min:5','max:90'],
            'round_type'     => ['nullable','in:practice,wa18,wa25,vegas300,custom'],
            'scoring_system' => ['nullable','in:10,5,none'],
            'arrows_per_end' => ['nullable','integer','in:3,6'],
            'ends_planned'   => ['nullable','integer','min:1','max:60'],
            'notes'          => ['nullable','string','max:2000'],
            'rpe'            => ['nullable','integer','min:1','max:10'],
            'x_value'        => ['required','integer','in:10,11'],
        ]);

        if (!empty($data['session_at'])) {
            $data['session_at'] = \Illuminate\Support\Carbon::parse($data['session_at']);
        }

        if ($this->editingId) {
            $s = Auth::user()->trainingSessions()->findOrFail($this->editingId);
            $s->update($data);
        } else {
            $s = Auth::user()->trainingSessions()->create($data);
        }

        $this->showSheet = false;
        $this->resetForm(keepLists:true);
        $this->dispatch('toast', type:'success', message:'Session saved');
    }

    public function delete(int $id): void
    {
        $s = Auth::user()->trainingSessions()->findOrFail($id);
        $s->delete();

        $this->lastDeleted = ['id' => $s->id];
        $this->dispatch('toast',
            type: 'success',
            message: 'Session deleted',
            duration: 4000,
        );
    }

    #[On('undo-session')]
    public function undoDelete(int $id): void
    {
        if (!$this->lastDeleted || ($this->lastDeleted['id'] ?? null) !== $id) return;

        $s = Auth::user()->trainingSessions()->withTrashed()->findOrFail($id);
        if ($s->trashed()) $s->restore();

        $this->lastDeleted = null;
        $this->dispatch('toast', type:'success', message:'Undo complete', duration:2500);
    }

    public function getSessionsProperty()
    {
        $base = Auth::user()->trainingSessions()
            ->when($this->search, function($q){
                $q->where(fn($qq) =>
                    $qq->where('title','like',"%{$this->search}%")
                       ->orWhere('location','like',"%{$this->search}%")
                );
            })
            ->with(['ends:id,training_session_id,scores,end_score,x_count'])
            ->withCount('ends')
            ->withSum('ends as total_score', 'end_score')
            ->orderBy($this->sort, $this->direction);

        $total    = (clone $base)->count();
        $lastPage = max(1, (int) ceil($total / $this->perPage));

        $requested = (int) ($this->paginators[$this->pageName] ?? 1);
        $page      = min(max(1, $requested), $lastPage);

        if ($requested !== $page) {
            $this->setPage($page, $this->pageName);
        }

        return $base->paginate($this->perPage, ['*'], $this->pageName, $page);
    }

    public function getPageWindowProperty(): array
    {
        $p = $this->sessions;
        $window  = 2;
        $current = max(1, (int) $p->currentPage());
        $last    = max(1, (int) $p->lastPage());
        $start   = max(1, $current - $window);
        $end     = min($last, $current + $window);
        return compact('current','last','start','end');
    }

    protected function resetForm(bool $keepLists = false): void
    {
        $this->editingId = null;
        $this->loadout_id = null;
        $this->title = null;
        $this->session_at = null;
        $this->location = null;
        $this->distance_m = null;
        $this->round_type = 'practice';
        $this->scoring_system = '10';
        $this->arrows_per_end = 3;
        $this->ends_planned = null;
        $this->notes = null;
        $this->rpe = null;
        $this->x_value = 10;

        if (!$keepLists) $this->mount();
    }
}; ?>

<section class="w-full">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">Training sessions</h1>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    Log practices and rounds, track ends and scores.
                </p>
            </div>
            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <button wire:click="openCreate"
                        class="block rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500
                               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600
                               dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                    Add session
                </button>
            </div>
        </div>

        <div class="mt-4 flex items-center gap-3">
            <flux:input placeholder="Search title or location…" wire:model.live.debounce.300ms="search" />
            <div class="ml-auto flex items-center gap-2">
                <flux:select wire:model="sort">
                    <option value="session_at">Date</option>
                    <option value="updated_at">Updated</option>
                    <option value="total_score">Score (needs computed column on list)</option>
                </flux:select>
                <flux:select wire:model="direction">
                    <option value="desc">Desc</option>
                    <option value="asc">Asc</option>
                </flux:select>
            </div>
        </div>

        {{-- Responsive table (Tailwind UI pattern) --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700 -mx-4 mt-6 sm:-mx-0">
            <table class="min-w-full divide-y divide-gray-300 dark:divide-white/15">
                <thead>
                    <tr>
                        <th scope="col" class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 sm:pl-0 dark:text-white">
                            Title
                        </th>
                        <th scope="col" class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 lg:table-cell dark:text-white">
                            Date
                        </th>
                        <th scope="col" class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 sm:table-cell dark:text-white">
                            Distance
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">
                            Ends
                        </th>
                        <th scope="col" class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 sm:table-cell dark:text-white">
                            Score
                        </th>
                        <th scope="col" class="py-3.5 pr-4 pl-3 sm:pr-0">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-gray-900">
                    @forelse($this->sessions as $s)
                        @php
                            // completed ends = any non-null value present in scores
                            $completed = 0;
                            foreach ($s->ends as $e) {
                                $scores = is_array($e->scores) ? $e->scores : [];
                                $hasAny = false;
                                foreach ($scores as $v) { if (!is_null($v)) { $hasAny = true; break; } }
                                if ($hasAny) $completed++;
                            }

                            // session is complete when all ends have no nulls
                            $complete = collect($s->ends ?? [])->every(function ($e) {
                                $scores = is_array($e->scores) ? $e->scores : [];
                                if (! count($scores)) return false;
                                foreach ($scores as $v) { if (is_null($v)) return false; }
                                return true;
                            });
                        @endphp

                        <tr>
                            {{-- Title + mobile stacked details --}}
                            <td class="w-full max-w-0 py-4 pr-3 pl-4 text-sm font-medium text-gray-900 sm:w-auto sm:max-w-none sm:pl-0 dark:text-white">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span>{{ $s->title ?? 'Session' }}</span>
                                    {{-- X-value pill --}}
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium
                                                 bg-gray-100 text-gray-800 dark:bg-white/10 dark:text-gray-200">
                                        X={{ (int)($s->x_value ?? 10) }}
                                    </span>
                                </div>

                                <dl class="font-normal lg:hidden">
                                    <dt class="sr-only">Date</dt>
                                    <dd class="mt-1 truncate text-gray-700 dark:text-gray-300">
                                        {{ optional($s->session_at)->format('Y-m-d H:i') ?? '—' }}
                                    </dd>

                                    <dt class="sr-only sm:hidden">Distance</dt>
                                    <dd class="mt-1 truncate text-gray-500 sm:hidden dark:text-gray-400">
                                        {{ $s->distance_m ? $s->distance_m.' m' : '—' }}
                                    </dd>

                                    <dt class="sr-only sm:hidden">Score</dt>
                                    <dd class="mt-1 truncate text-gray-500 sm:hidden dark:text-gray-400">
                                        {{ (int)($s->total_score ?? 0) }}
                                        @if((int)($s->x_count ?? 0) > 0)
                                            <span class="opacity-60">({{ (int)$s->x_count }}X)</span>
                                        @endif
                                    </dd>

                                    <dt class="sr-only sm:hidden">Meta</dt>
                                    <dd class="mt-1 truncate text-gray-500 sm:hidden dark:text-gray-400">
                                        {{ ucfirst($s->round_type ?? 'practice') }} @if($s->location) • {{ $s->location }} @endif
                                    </dd>
                                </dl>
                            </td>

                            {{-- Desktop columns --}}
                            <td class="hidden px-3 py-4 text-sm text-gray-500 lg:table-cell dark:text-gray-400">
                                {{ optional($s->session_at)->format('Y-m-d H:i') ?? '—' }}
                            </td>
                            <td class="hidden px-3 py-4 text-sm text-gray-500 sm:table-cell dark:text-gray-400">
                                {{ $s->distance_m ? $s->distance_m.' m' : '—' }}
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $completed }}@if($s->ends_planned)/{{ $s->ends_planned }}@endif
                            </td>
                            <td class="hidden px-3 py-4 text-sm text-gray-500 sm:table-cell dark:text-gray-400">
                                {{ (int)($s->total_score ?? 0) }}
                                @if((int)($s->x_count ?? 0) > 0)
                                    <span class="opacity-60">({{ (int)$s->x_count }}X)</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="py-4 pr-4 pl-3 text-right text-sm font-medium sm:pr-0">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('training.record', $s) }}" wire:navigate>
                                        <flux:button variant="ghost" size="xs" icon="eye" title="Open">
                                            <span class="sr-only">Open</span>
                                        </flux:button>
                                    </a>

                                    <flux:button
                                        variant="ghost" size="xs" icon="pencil-square" title="Edit"
                                        wire:click="openEdit({{ $s->id }})"
                                    >
                                        <span class="sr-only">Edit</span>
                                    </flux:button>

                                    @if ($complete)
                                        <a href="{{ route('training.stats', $s->id) }}" wire:navigate>
                                            <flux:button variant="ghost" size="xs" icon="chart-bar" title="View stats">
                                                <span class="sr-only">View stats</span>
                                            </flux:button>
                                        </a>
                                    @else
                                        <flux:button variant="ghost" size="xs" icon="chart-bar" title="Stats available when session is complete" disabled>
                                            <span class="sr-only">Stats (disabled)</span>
                                        </flux:button>
                                    @endif

                                    <flux:button
                                        variant="ghost" size="xs" icon="trash" title="Delete"
                                        wire:click="delete({{ $s->id }})"
                                    >
                                        <span class="sr-only">Delete</span>
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 px-4 text-sm text-gray-500 dark:text-gray-400">
                                No sessions yet. Click “Add session” to log your first practice.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination footer (Blade-safe disabled attributes) --}}
        @php($p = $this->sessions)
        @php($w = $this->pageWindow)
        <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 dark:border-white/10 dark:bg-transparent">
            {{-- Mobile Prev/Next --}}
            <div class="flex flex-1 justify-between sm:hidden">
                <button wire:click="prevPage"
                        class="relative inline-flex items-center rounded-md border px-4 py-2 text-sm"
                        @if($p->onFirstPage()) disabled @endif>
                    Previous
                </button>
                <button wire:click="nextPage"
                        class="relative ml-3 inline-flex items-center rounded-md border px-4 py-2 text-sm"
                        @if(!$p->hasMorePages()) disabled @endif>
                    Next
                </button>
            </div>

            {{-- Desktop pager --}}
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
                                class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:inset-ring-gray-700 dark:hover:bg-white/5"
                                @if($p->onFirstPage()) disabled @endif>
                            <span class="sr-only">Previous</span>
                            <svg viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" /></svg>
                        </button>

                        @for ($i = $w['start']; $i <= $w['end']; $i++)
                            @if ($i === $w['current'])
                                <span aria-current="page" class="relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white dark:bg-indigo-500">{{ $i }}</span>
                            @else
                                <button wire:click="goto({{ $i }})" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:text-gray-200 dark:inset-ring-gray-700 dark:hover:bg-white/5">{{ $i }}</button>
                            @endif
                        @endfor

                        <button wire:click="nextPage"
                                class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:inset-ring-gray-700 dark:hover:bg-white/5"
                                @if(!$p->hasMorePages()) disabled @endif>
                            <span class="sr-only">Next</span>
                            <svg viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" /></svg>
                        </button>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    {{-- Sheet --}}
    @if($showSheet)
        <div class="fixed inset-0 z-40">
            <div class="absolute inset-0 bg-black/40" wire:click="$set('showSheet', false)"></div>
            <div class="absolute inset-y-0 right-0 w-full max-w-2xl h-full overflow-y-auto bg-white p-6 shadow-xl dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $editingId ? 'Edit session' : 'Create session' }}</h2>
                    <button class="rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10" wire:click="$set('showSheet', false)">✕</button>
                </div>

                <form wire:submit.prevent="save" class="mt-6 space-y-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <flux:label for="title">Title</flux:label>
                            <flux:input id="title" type="text" wire:model="title" placeholder="e.g., WA18 practice" />
                        </div>
                        <div>
                            <flux:label for="session_at">Date / time</flux:label>
                            <flux:input id="session_at" type="datetime-local" wire:model="session_at" />
                        </div>
                        <div>
                            <flux:label for="loadout_id">Loadout</flux:label>
                            <flux:select id="loadout_id" wire:model="loadout_id" class="w-full">
                                <option value="">{{ __('Select…') }}</option>
                                @foreach($loadouts as $l)
                                    <option value="{{ $l['id'] }}">{{ $l['name'] }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div>
                            <flux:label for="location">Location</flux:label>
                            <flux:input id="location" type="text" wire:model="location" placeholder="range, club, etc." />
                        </div>
                        <div>
                            <flux:label for="distance_m">Distance (m)</flux:label>
                            <flux:input id="distance_m" type="number" min="5" max="90" wire:model="distance_m" class="max-w-[10rem]" />
                        </div>
                        <div>
                            <flux:label for="round_type">Round</flux:label>
                            <flux:select id="round_type" wire:model="round_type" class="w-full">
                                <option value="practice">Practice</option>
                                <option value="wa18">WA 18m</option>
                                <option value="wa25">WA 25m</option>
                                <option value="vegas300">Vegas 300</option>
                                <option value="custom">Custom</option>
                            </flux:select>
                        </div>
                        <div>
                            <flux:label for="scoring_system">Scoring</flux:label>
                            <flux:select id="scoring_system" wire:model="scoring_system" class="w-full">
                                <option value="10">10-ring</option>
                                <option value="5">5-ring</option>
                                <option value="none">No score</option>
                            </flux:select>
                        </div>

                        {{-- X ring value selector --}}
                        <div>
                            <flux:label>X ring value</flux:label>
                            <div class="mt-2 flex items-center gap-4">
                                <label class="inline-flex items-center gap-2">
                                    <input type="radio" class="rounded" wire:model="x_value" value="10">
                                    <span>X = 10 (standard)</span>
                                </label>
                                <label class="inline-flex items-center gap-2">
                                    <input type="radio" class="rounded" wire:model="x_value" value="11">
                                    <span>X = 11 (Lancaster)</span>
                                </label>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                This only changes how “X” is scored during entry.
                            </p>
                        </div>

                        <div>
                            <flux:label for="arrows_per_end">Arrows/end</flux:label>
                            <flux:select id="arrows_per_end" wire:model="arrows_per_end" class="w-full">
                                <option value="3">3</option>
                                <option value="6">6</option>
                            </flux:select>
                        </div>
                        <div>
                            <flux:label for="ends_planned">Ends planned</flux:label>
                            <flux:input id="ends_planned" type="number" min="1" max="60" wire:model="ends_planned" class="max-w-[10rem]" />
                        </div>
                        <div>
                            <flux:label for="rpe">RPE</flux:label>
                            <flux:input id="rpe" type="number" min="1" max="10" wire:model="rpe" class="max-w-[8rem]" />
                        </div>
                        <div class="md:col-span-2">
                            <flux:label for="notes">Notes</flux:label>
                            <flux:input id="notes" type="text" wire:model="notes" placeholder="wind, form focus, coach notes…" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <flux:button type="button" variant="ghost" wire:click="$set('showSheet', false)">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create' }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</section>
