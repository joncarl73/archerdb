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
    public int $perPage = 10;

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

    public function rendered(): void
    {
        $p = $this->sessions;
        if ($p->isEmpty() && $p->currentPage() > 1) {
            $this->gotoPage($p->lastPage(), $this->pageName);
            $this->dispatch('$refresh');
        }
    }

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
        $this->editingId     = $s->id;
        $this->loadout_id    = $s->loadout_id;
        $this->title         = $s->title;
        $this->session_at    = optional($s->session_at)->format('Y-m-d\TH:i');
        $this->location      = $s->location;
        $this->distance_m    = $s->distance_m;
        $this->round_type    = $s->round_type;
        $this->scoring_system= $s->scoring_system ?: '10';
        $this->arrows_per_end= $s->arrows_per_end ?: 3;
        $this->ends_planned  = $s->ends_planned;
        $this->notes         = $s->notes;
        $this->rpe           = $s->rpe;
        $this->showSheet     = true;
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
        ]);

        // normalize datetime-local to Carbon
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
            duration: 6000,
            action: [
                'label'   => 'Undo',
                'event'   => 'undo-session',
                'payload' => ['id' => $s->id],
            ],
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
        return Auth::user()->trainingSessions()
            ->when($this->search, function($q){
                $q->where(fn($qq) =>
                    $qq->where('title','like',"%{$this->search}%")
                       ->orWhere('location','like',"%{$this->search}%")
                );
            })
            ->withCount('ends')
            ->withSum('ends as total_score', 'end_score')
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->perPage, ['*'], $this->pageName);
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

        if (!$keepLists) $this->mount();
    }
}; ?>

<section class="w-full">
    <div class="mx-auto max-w-7xl">
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
    </div>

    <div class="mt-6">
        <div class="mx-auto max-w-7xl">
            <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
                <table class="w-full text-left">
                    <thead class="bg-white dark:bg-gray-900">
                        <tr>
                            <th class="py-3.5 pl-4 pr-3 text-sm font-semibold">Title</th>
                            <th class="hidden px-3 py-3.5 text-sm font-semibold md:table-cell">Date</th>
                            <th class="hidden px-3 py-3.5 text-sm font-semibold md:table-cell">Distance</th>
                            <th class="px-3 py-3.5 text-sm font-semibold">Ends</th>
                            <th class="px-3 py-3.5 text-sm font-semibold">Score</th>
                            <th class="py-3.5 pl-3 pr-4"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse($this->sessions as $s)
                            <tr>
                                <td class="py-4 pl-4 pr-3 text-sm font-medium">
                                    {{ $s->title ?? 'Session' }}
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ ucfirst($s->round_type ?? 'practice') }}
                                        @if($s->location) · {{ $s->location }} @endif
                                    </div>
                                </td>
                                <td class="hidden px-3 py-4 text-sm md:table-cell">
                                    {{ optional($s->session_at)->format('Y-m-d H:i') ?? '—' }}
                                </td>
                                <td class="hidden px-3 py-4 text-sm md:table-cell">
                                    {{ $s->distance_m ? $s->distance_m.' m' : '—' }}
                                </td>
                                <td class="px-3 py-4 text-sm">
                                    {{ $s->ends_count }} @if($s->ends_planned)/ {{ $s->ends_planned }}@endif
                                </td>
                                <td class="px-3 py-4 text-sm">
                                    {{ (int)($s->total_score ?? 0) }} @if((int)($s->x_count ?? 0) > 0)<span class="text-xs opacity-60">({{ (int)$s->x_count }}X)</span>@endif
                                </td>
                                <td class="py-4 pl-3 pr-4 text-right text-sm space-x-3">
                                    <a href="{{ route('training.record', $s->id) }}" wire:navigate class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                        Open<span class="sr-only">, {{ $s->title ?? 'Session' }}</span>
                                    </a>
                                    <button wire:click="openEdit({{ $s->id }})" class="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100">
                                        Edit
                                    </button>
                                    <button wire:click="delete({{ $s->id }})" class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                                        Delete
                                    </button>
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

                @php($p = $this->sessions)
                @php($w = $this->pageWindow)
                <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 dark:border-white/10 dark:bg-transparent">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <button wire:click="prevPage" @disabled($p->onFirstPage()) class="relative inline-flex items-center rounded-md border px-4 py-2 text-sm">Previous</button>
                        <button wire:click="nextPage" @disabled(!$p->hasMorePages()) class="relative ml-3 inline-flex items-center rounded-md border px-4 py-2 text-sm">Next</button>
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
                                <button wire:click="prevPage" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:inset-ring-gray-700 dark:hover:bg-white/5" @disabled($p->onFirstPage())>
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
                                <button wire:click="nextPage" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:inset-ring-gray-700 dark:hover:bg-white/5" @disabled(!$p->hasMorePages())>
                                    <span class="sr-only">Next</span>
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" /></svg>
                                </button>
                            </nav>
                        </div>
                    </div>
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
