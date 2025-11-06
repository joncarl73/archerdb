<?php
use App\Models\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads, WithPagination;

    public Event $event;

    // pagination
    protected string $pageName = 'participantsPage';

    public int $perPage = 25;

    // search/sort
    public string $search = '';

    public string $sort = 'last_name';

    public string $direction = 'asc';

    // filters (trimmed)
    public ?string $filterDivision = null;   // division_name

    public ?string $filterBowType = null;    // bow_type

    public ?int $filterLineTime = null;      // line_time_id

    // picklists
    public array $divisionOptions = [];

    public array $bowTypeOptions = [];

    public array $lineTimeOptions = [];      // [id => label]

    public array $laneOptions = [];          // [1,2,3,...]

    public array $slotOptions = [];          // ['A','B','C',...]

    // ruleset slot mode
    public bool $slotsSingle = false;        // true => N/A slot, store NULL

    // occupancy map: [line_time_id][lane][slot_or_null|'SC'] = participant_id
    public array $taken = [];

    // CSV drawer
    public bool $showCsvSheet = false;

    public $csv;

    public ?int $applyLineTimeId = null;

    // scratch
    public array $assign = [];

    public bool $debug = false;

    /**
     * Boot the component: authorize, cache event, build picklists and occupancy.
     */
    public function mount(Event $event): void
    {
        Gate::authorize('manageParticipants', $event);
        $this->event = $event;

        $this->rebuildPicklists();

        // Build line time options (sorted)
        $this->lineTimeOptions = [];
        $lts = \App\Models\EventLineTime::query()
            ->where('event_id', $this->event->id)
            ->orderBy('line_date')
            ->orderBy('start_time')
            ->get();

        foreach ($lts as $lt) {
            $this->lineTimeOptions[(int) $lt->id] = $this->formatLineTimeIsoAware($lt);
        }

        \Log::debug('Participants: lineTimeOptions built', [
            'event_id' => $this->event->id,
            'lineTimeOptions' => $this->lineTimeOptions,
        ]);

        $this->buildTaken();

        if (request()->boolean('log')) {
            $this->logDebugState('mount+log=1');
        }
    }

    /**
     * Open the CSV upload sheet.
     */
    public function openCsv(): void
    {
        $this->showCsvSheet = true;
    }

    /**
     * Extract an array of "name" values from a ruleset relation/array property.
     * Falls back to distinct values observed on participants for the event.
     *
     * @param  mixed  $ruleset
     * @param  string  $prop  'divisions' | 'bow_types'
     * @return array<string>
     */
    private function extractNames($ruleset, string $prop): array
    {
        if (! $ruleset) {
            return [];
        }

        $val = $ruleset->{$prop} ?? null;

        // Try as relation with pluck('name')
        if (method_exists($ruleset, $prop)) {
            try {
                $rel = $ruleset->{$prop}();
                if (method_exists($rel, 'pluck')) {
                    $names = $rel->pluck('name')->filter()->values()->all();
                    if ($names) {
                        return $names;
                    }
                }
            } catch (\Throwable) {
                // swallow and fall through
            }
        }

        // Try as array of strings or objects/arrays with ->name/name
        if (is_array($val) && $val) {
            if (is_string(reset($val))) {
                return array_values(array_filter(array_map('trim', $val)));
            }
            $names = [];
            foreach ($val as $item) {
                $n = is_array($item) ? ($item['name'] ?? null)
                    : (is_object($item) ? ($item->name ?? null) : null);
                if (is_string($n) && trim($n) !== '') {
                    $names[] = trim($n);
                }
            }
            if ($names) {
                return array_values(array_unique($names));
            }
        }

        // Fallback from existing participants
        $db = \App\Models\EventParticipant::query()->where('event_id', $this->event->id);
        $col = $prop === 'divisions' ? 'division_name' : 'bow_type';

        return (clone $db)
            ->whereNotNull($col)
            ->distinct()
            ->orderBy($col)
            ->pluck($col)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Derive slot labels from ruleset->lane_breakdown (supports several formats).
     *
     * @param  mixed  $ruleset
     * @return array<string>
     */
    private function extractSlotLabels($ruleset): array
    {
        if (! $ruleset) {
            return [];
        }

        $v = $ruleset->lane_breakdown ?? null;
        if (! $v) {
            return [];
        }

        // Array input (strings or objects with label/name)
        if (is_array($v) && $v) {
            if (is_string(reset($v))) {
                return array_values(array_filter(array_map('trim', $v)));
            }
            $labels = [];
            foreach ($v as $item) {
                $label = is_array($item) ? ($item['label'] ?? $item['name'] ?? null)
                    : (is_object($item) ? ($item->label ?? $item->name ?? null) : null);
                if (is_string($label) && trim($label) !== '') {
                    $labels[] = trim($label);
                }
            }

            return $labels ? array_values(array_unique($labels)) : [];
        }

        // String input: "A,B" or "A/B" or "AB" or "SINGLE/N/A"
        if (is_string($v)) {
            $raw = trim($v);
            $up = mb_strtoupper($raw);

            if (in_array($up, ['SINGLE', 'N/A', 'NA'], true)) {
                return ['N/A'];
            }
            if (str_contains($raw, ',')) {
                return array_values(array_filter(array_map('trim', explode(',', $raw))));
            }
            if (str_contains($raw, '/')) {
                return array_values(array_filter(array_map('trim', explode('/', $raw))));
            }
            if (preg_match('/^[A-Za-z]+$/', $raw)) {
                return array_map('mb_strtoupper', preg_split('//u', $raw, -1, PREG_SPLIT_NO_EMPTY));
            }
        }

        return [];
    }

    /**
     * Return ['A', 'B', 'C', ...] up to length $n.
     */
    private function letters(int $n): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = chr(65 + $i);
        }

        return $out;
    }

    /**
     * Build the occupancy map $this->taken from existing assignments.
     */
    private function buildTaken(): void
    {
        $rows = $this->event->participants()
            ->select(['id', 'line_time_id', 'assigned_lane', 'assigned_slot'])
            ->whereNotNull('line_time_id')
            ->whereNotNull('assigned_lane')
            ->get();

        $taken = [];
        foreach ($rows as $r) {
            $lt = (int) $r->line_time_id;
            $lane = (int) $r->assigned_lane;

            $taken[$lt] ??= [];
            $taken[$lt][$lane] ??= [];

            if ($this->slotsSingle) {
                // In single-slot mode, any occupant claims the lane
                $taken[$lt][$lane][null] ??= (int) $r->id;

                continue;
            }

            $slotRaw = $r->assigned_slot;
            if ($slotRaw === null || $slotRaw === '') {
                continue;
            }
            $slot = mb_strtoupper((string) $slotRaw);

            if ($slot === 'SC') {
                // 'SC' claims full lane regardless of other slot letters
                $taken[$lt][$lane]['SC'] ??= (int) $r->id;
            } else {
                $taken[$lt][$lane][$slot] ??= (int) $r->id;
            }
        }

        $this->taken = $taken;
    }

    /**
     * Return true if any occupant exists in lane (optionally ignoring $exceptId).
     */
    private function hasAnyOccupant(int $lineTimeId, int $lane, int $exceptId = 0): bool
    {
        if (! isset($this->taken[$lineTimeId][$lane])) {
            return false;
        }
        foreach ($this->taken[$lineTimeId][$lane] as $pid) {
            if ($pid !== $exceptId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lane is locked if 'SC' is present on that lane for the line time.
     */
    public function isLaneLockedBySC(int $lineTimeId, int $lane, int $exceptId = 0): bool
    {
        return isset($this->taken[$lineTimeId][$lane]['SC'])
            && $this->taken[$lineTimeId][$lane]['SC'] !== $exceptId;
    }

    /**
     * In single-slot mode, any occupant makes the lane taken.
     * In multi-slot, "taken" here is used for quick checks (e.g., SC).
     */
    public function isLaneTaken(int $lineTimeId, int $lane, int $exceptId = 0): bool
    {
        if (! isset($this->taken[$lineTimeId][$lane])) {
            return false;
        }
        foreach ($this->taken[$lineTimeId][$lane] as $pid) {
            if ($pid !== $exceptId) {
                return true;
            }
        }

        return false;
    }

    /**
     * True if a specific slot (or lane via SC) is already taken on that lane.
     */
    public function isSlotTaken(int $lineTimeId, int $lane, string $slot, int $exceptId = 0): bool
    {
        if ($slot === '' || ! isset($this->taken[$lineTimeId][$lane])) {
            return false;
        }
        if ($this->isLaneLockedBySC($lineTimeId, $lane, $exceptId)) {
            return true;
        }
        $slotKey = mb_strtoupper((string) $slot);

        return isset($this->taken[$lineTimeId][$lane][$slotKey])
            && $this->taken[$lineTimeId][$lane][$slotKey] !== $exceptId;
    }

    /**
     * Normalize "H:i" or any parsable time to "H:i:s". Return null if invalid.
     */
    private function normalizeTimeSeconds(?string $t): ?string
    {
        $t = trim((string) $t);
        if ($t === '') {
            return null;
        }
        if (preg_match('/^\d{1,2}:\d{2}$/', $t)) {
            return $t.':00';
        }
        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $t)) {
            return $t;
        }
        try {
            return \Carbon\Carbon::parse($t)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Extract a Y-m-d date string from various date inputs.
     */
    private function extractDateYmd($dateVal): ?string
    {
        $raw = trim((string) ($dateVal ?? ''));
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw, $m)) {
            return $m[0];
        }
        try {
            return \Carbon\Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Format an EventLineTime as "Line m/d/Y h:iAM → h:iAM" (fallback to "Line {id}").
     */
    private function formatLineTimeIsoAware(object $lt): string
    {
        $ymd = $this->extractDateYmd($lt->line_date ?? null);
        $st = $this->normalizeTimeSeconds($lt->start_time ?? null);
        $et = $this->normalizeTimeSeconds($lt->end_time ?? null);

        if ($ymd && $st) {
            try {
                $start = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ymd.' '.$st);
                $end = $et ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ymd.' '.$et) : null;
                $startStr = $start->format('n/j/Y g:iA');
                $endStr = $end ? $end->format('g:iA') : null;

                return 'Line '.$startStr.($endStr ? ' → '.$endStr : '');
            } catch (\Throwable) {
                // fall through
            }
        }

        return 'Line '.$lt->id;
    }

    /**
     * Reset pagination when search changes.
     */
    public function updatingSearch(): void
    {
        $this->resetPage($this->pageName);
    }

    /**
     * Reset pagination when Division filter changes.
     */
    public function updatedFilterDivision()
    {
        $this->resetPage($this->pageName);
    }

    /**
     * Reset pagination when Bow Type filter changes.
     */
    public function updatedFilterBowType()
    {
        $this->resetPage($this->pageName);
    }

    /**
     * Reset pagination when Line Time filter changes.
     */
    public function updatedFilterLineTime()
    {
        $this->resetPage($this->pageName);
    }

    /**
     * Jump to a specific page (custom pager buttons).
     */
    public function goto(int $page): void
    {
        $this->gotoPage($page, $this->pageName);
    }

    /**
     * Previous page (mobile pager).
     */
    public function prevPage(): void
    {
        $this->previousPage($this->pageName);
    }

    /**
     * Next page (mobile pager).
     */
    public function nextPage(): void
    {
        $this->nextPage($this->pageName);
    }

    /**
     * Change sort column/direction and reset pagination.
     */
    public function sortBy(string $col): void
    {
        if ($this->sort === $col) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $col;
            $this->direction = 'asc';
        }
        $this->resetPage($this->pageName);
    }

    /**
     * Participants accessor with search, filters, and sorting applied.
     * Paginates using $perPage and the custom page name.
     */
    public function getParticipantsProperty()
    {
        $q = $this->event->participants()
            ->when($this->search, function ($q) {
                $s = '%'.str_replace('%', '\%', trim($this->search)).'%';
                $q->where(function ($w) use ($s) {
                    $w->where('first_name', 'LIKE', $s)
                        ->orWhere('last_name', 'LIKE', $s)
                        ->orWhere('email', 'LIKE', $s)
                        ->orWhere('division_name', 'LIKE', $s)
                        ->orWhere('bow_type', 'LIKE', $s);
                });
            })
            ->when($this->filterDivision, fn ($q, $v) => $q->where('division_name', $v))
            ->when($this->filterBowType, fn ($q, $v) => $q->where('bow_type', $v))
            ->when($this->filterLineTime, fn ($q, $v) => $q->where('line_time_id', (int) $v));

        $allowed = ['last_name', 'first_name', 'division_name', 'bow_type', 'line_time_id', 'assigned_lane', 'assigned_slot'];
        $col = in_array($this->sort, $allowed, true) ? $this->sort : 'last_name';
        $dir = $this->direction === 'desc' ? 'desc' : 'asc';

        return $q->orderBy($col, $dir)
            ->orderBy('first_name', 'asc')
            ->paginate($this->perPage, ['*'], $this->pageName);
    }

    /**
     * Compute a compact pager window (e.g., current ± 2).
     *
     * @return array{current:int,last:int,start:int,end:int}
     */
    public function getPageWindowProperty(): array
    {
        $p = $this->participants;
        $window = 2;
        $current = max(1, (int) $p->currentPage());
        $last = max(1, (int) $p->lastPage());
        $start = max(1, $current - $window);
        $end = min($last, $current + $window);

        return compact('current', 'last', 'start', 'end');
    }

    /**
     * Save inline edits (division/bow/line/lane/slot), enforcing rules:
     * - Changing line clears lane/slot.
     * - Single-slot rulesets null slot.
     * - SC claims entire lane; conflicts are prevented.
     * - Emits toasts on success/conflict.
     *
     * @param  int  $id  participant id
     * @param  'division_name'|'bow_type'|'line_time_id'|'assigned_lane'|'assigned_slot'  $field
     * @param  mixed  $value
     */
    public function save(int $id, string $field, $value): void
    {
        Gate::authorize('update', $this->event);

        $allowed = ['division_name', 'bow_type', 'line_time_id', 'assigned_lane', 'assigned_slot'];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        // Normalize value
        $val = is_string($value) ? trim($value) : $value;
        if ($val === '' || $val === 'null') {
            $val = null;
        }

        // Validate choices against picklists when present
        if ($field === 'division_name' && $this->divisionOptions && $val !== null && ! in_array($val, $this->divisionOptions, true)) {
            return;
        }
        if ($field === 'bow_type' && $this->bowTypeOptions && $val !== null && ! in_array($val, $this->bowTypeOptions, true)) {
            return;
        }
        if ($field === 'line_time_id') {
            $val = $val !== null ? (int) $val : null;
            if ($val !== null && ! array_key_exists($val, $this->lineTimeOptions)) {
                return;
            }
        }
        if ($field === 'assigned_lane') {
            $val = $val !== null ? (int) $val : null;
            if ($val !== null && $this->laneOptions && ! in_array($val, $this->laneOptions, true)) {
                return;
            }
        }
        if ($field === 'assigned_slot') {
            if ($this->slotsSingle) {
                $this->dispatch('toast', type: 'info', message: 'Slots are N/A for this ruleset.');

                return;
            }
            $val = $val !== null ? mb_strtoupper((string) $val) : null;
            if ($val !== null && $this->slotOptions && ! in_array($val, $this->slotOptions, true)) {
                return;
            }
        }

        // Load participant
        $p = $this->event->participants()->whereKey($id)->first();
        if (! $p) {
            return;
        }

        // Changing line resets lane/slot
        if ($field === 'line_time_id') {
            $p->line_time_id = $val;
            $p->assigned_lane = null;
            $p->assigned_slot = null;
            $p->save();
            $this->buildTaken();
            $this->dispatch('toast', type: 'success', message: 'Saved.');

            return;
        }

        // Compute would-be values
        $newLine = $p->line_time_id;
        $newLane = $p->assigned_lane;
        $newSlot = $p->assigned_slot;

        if ($field === 'assigned_lane') {
            $newLane = $val;
        }
        if ($field === 'assigned_slot') {
            $newSlot = $val;
        }
        if ($this->slotsSingle) {
            $newSlot = null;
        }

        // Conflict checks
        $conflict = null;
        if ($newLine && $newLane) {
            $qBase = $this->event->participants()
                ->where('line_time_id', (int) $newLine)
                ->where('assigned_lane', (int) $newLane)
                ->where('id', '<>', $p->id);

            if ($this->slotsSingle) {
                $conflict = $qBase->first();
            } else {
                if ($newSlot) {
                    $slotU = mb_strtoupper((string) $newSlot);
                    if ($slotU === 'SC') {
                        $conflict = $qBase->first();
                    } else {
                        $hasSC = (clone $qBase)->where('assigned_slot', 'SC')->exists();
                        $conflict = $hasSC ? true : (clone $qBase)->where('assigned_slot', $slotU)->first();
                    }
                }
            }
        }

        if ($conflict) {
            $this->dispatch('toast', type: 'warning', message: 'That lane/slot is already taken for the selected line.');

            return;
        }

        // Save and rebuild occupancy
        $p->{$field} = $val;
        if ($this->slotsSingle && $field === 'assigned_lane') {
            $p->assigned_slot = null;
        }
        $p->save();

        $this->buildTaken();
        $this->dispatch('toast', type: 'success', message: 'Saved.');
    }

    /**
     * Clear lane/slot assignment for a participant.
     */
    public function clearParticipantAssignment(int $participantId): void
    {
        Gate::authorize('update', $this->event);

        $p = $this->event->participants()->whereKey($participantId)->first();
        if (! $p) {
            return;
        }

        $p->assigned_lane = null;
        $p->assigned_slot = null;
        $p->save();

        $this->buildTaken();
        $this->dispatch('toast', type: 'success', message: 'Participant lane/slot cleared.');
    }

    /**
     * Clear ALL lane/slot assignments for the event.
     */
    public function resetAssignments(): void
    {
        Gate::authorize('update', $this->event);

        $this->event->participants()->update(['assigned_lane' => null, 'assigned_slot' => null]);
        $this->buildTaken();
        $this->dispatch('toast', type: 'success', message: 'All lane/slot assignments have been cleared.');
    }

    /**
     * Rebuild picklists for divisions, bow types, lanes and slot options.
     * Detects single-slot mode (N/A) and appends 'SC' when multi-slot.
     */
    private function rebuildPicklists(): void
    {
        $ruleset = $this->event->ruleset ?? null;

        // Division/Bow picklists
        $this->divisionOptions = $this->extractNames($ruleset, 'divisions');
        $this->bowTypeOptions = $this->extractNames($ruleset, 'bow_types');

        // Lanes (ruleset -> event override -> observed max -> default)
        $laneCount = (int) ($ruleset->lane_count ?? 0);
        if ($laneCount <= 0 && property_exists($this->event, 'lane_count') && (int) $this->event->lane_count > 0) {
            $laneCount = (int) $this->event->lane_count;
        }
        if ($laneCount <= 0) {
            $maxLane = (int) $this->event->participants()->whereNotNull('assigned_lane')->max('assigned_lane');
            if ($maxLane > 0) {
                $laneCount = $maxLane;
            }
        }
        if ($laneCount <= 0) {
            $laneCount = (int) (config('archerdb.default_lane_count', 12));
        }
        $this->laneOptions = range(1, max(1, $laneCount));

        // Slots & single-slot detection
        $slotLabels = $this->extractSlotLabels($ruleset);
        if ($slotLabels) {
            $u = array_map(fn ($s) => mb_strtoupper(trim($s)), $slotLabels);
            $isSingleByLabel = (count($u) === 1) && in_array($u[0], ['N/A', 'NA', 'SINGLE'], true);

            if ($isSingleByLabel) {
                $this->slotsSingle = true;
                $this->slotOptions = ['N/A'];
            } else {
                $this->slotsSingle = false;
                $this->slotOptions = $slotLabels;
            }
        } else {
            $this->slotsSingle = false;
            $this->slotOptions = ['A', 'B'];
        }

        // Add SC for multi-slot layouts
        if (! $this->slotsSingle && count($this->slotOptions) >= 2 && ! in_array('SC', $this->slotOptions, true)) {
            $this->slotOptions[] = 'SC';
        }

        \Log::debug('Participants: rebuilt picklists', [
            'event_id' => $this->event->id,
            'laneOptions' => $this->laneOptions,
            'slotOptions' => $this->slotOptions,
            'slotsSingle' => $this->slotsSingle,
            'ruleset' => [
                'id' => $ruleset->id ?? null,
                'lane_count' => $ruleset->lane_count ?? null,
                'lane_breakdown' => $ruleset->lane_breakdown ?? null,
            ],
        ]);
    }

    /**
     * Dump a compact snapshot of current state to logs for debugging.
     */
    private function logDebugState(string $tag = 'snapshot'): void
    {
        \Log::debug("Participants: {$tag}", [
            'event_id' => $this->event->id,
            'laneOptions' => $this->laneOptions,
            'slotOptions' => $this->slotOptions,
            'slotsSingle' => $this->slotsSingle,
            'lineTimeOptions' => $this->lineTimeOptions,
            'taken_counts' => [
                'line_times' => count($this->taken),
                'by_line' => array_map(fn ($x) => count($x), $this->taken),
            ],
        ]);
    }
};
?>
<section class="w-full">
    @php
        $typeVal  = ($event->type->value ?? $event->type ?? null);
        $isClosed = ($typeVal === 'closed');
    @endphp

    <div class="mx-auto max-w-7xl">
        {{-- Header --}}
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ $event->title }} — Participants
                </h1>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    Manage event participants (search, filter, inline edit, and import).
                </p>
            </div>

            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <div class="flex items-center gap-2">
                    <flux:button as="a" href="{{ route('corporate.events.show', $event) }}" variant="ghost">
                        ← Back to event
                    </flux:button>

                    <flux:dropdown>
                        <flux:button icon:trailing="chevron-down">Actions</flux:button>
                        <flux:menu class="min-w-64">
                            @unless($isClosed)
                                <flux:menu.item
                                    href="{{ route('corporate.events.participants.template', $event) }}"
                                    icon="table-cells"
                                >
                                    Download CSV template
                                </flux:menu.item>
                            @endunless

                            <flux:menu.item
                                href="{{ route('corporate.events.participants.export', $event) }}"
                                icon="users"
                            >
                                Export participants
                            </flux:menu.item>

                            <flux:menu.item
                                icon="trash"
                                class="text-red-600"
                                x-on:click.prevent="if(confirm('Reset ALL lane/slot assignments for this event?')) {$wire.resetAssignments()}"
                            >
                                Reset all lane/slot assignments
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>

                    @unless($isClosed)
                        <flux:button wire:click="openCsv" variant="primary" color="indigo" icon="arrow-up-tray">
                            Upload CSV
                        </flux:button>
                    @endunless
                </div>
            </div>
        </div>

        {{-- Search + Filters --}}
        <div class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-3 lg:grid-cols-6">
            <div class="col-span-2">
                <label class="mb-1 block text-xs text-muted-foreground">Search</label>
                <flux:input
                    icon="magnifying-glass"
                    placeholder="Name, division, bow (email searchable)"
                    wire:model.live.debounce.300ms="search"
                />
            </div>

            <div>
                <label class="mb-1 block text-xs text-muted-foreground">Division</label>
                <flux:select wire:model.live="filterDivision" placeholder="All">
                    <option value="">All</option>
                    @foreach($divisionOptions as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <label class="mb-1 block text-xs text-muted-foreground">Bow Type</label>
                <flux:select wire:model.live="filterBowType" placeholder="All">
                    <option value="">All</option>
                    @foreach($bowTypeOptions as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <label class="mb-1 block text-xs text-muted-foreground">Line Time</label>
                <flux:select wire:model.live="filterLineTime" placeholder="All">
                    <option value="">All</option>
                    @foreach($lineTimeOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        {{-- Table --}}
        <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
            <table class="min-w-full text-left">
                <thead class="bg-white text-xs uppercase tracking-wide dark:bg-gray-900">
                    <tr>
                        @php
                            $th = function ($label, $col) {
                                $is  = $this->sort === $col;
                                $dir = $is ? ($this->direction === 'asc' ? '▲' : '▼') : '';
                                return '<button type="button" wire:click="sortBy(\'' . $col . '\')" class="w-full px-3 py-2 text-left">'
                                       . $label . ' <span class="opacity-60">' . $dir . '</span></button>';
                            };
                        @endphp
                        <th class="whitespace-nowrap">{!! $th('Name', 'last_name') !!}</th>
                        <th class="whitespace-nowrap">{!! $th('Division', 'division_name') !!}</th>
                        <th class="whitespace-nowrap">{!! $th('Bow', 'bow_type') !!}</th>
                        <th class="whitespace-nowrap">{!! $th('Line Time', 'line_time_id') !!}</th>
                        <th class="whitespace-nowrap">{!! $th('Lane', 'assigned_lane') !!}</th>
                        <th class="whitespace-nowrap">{!! $th('Slot', 'assigned_slot') !!}</th>
                        <th class="whitespace-nowrap pr-3 text-right">Actions</th>
                    </tr>
                </thead>

                <tbody class="text-xs divide-y divide-gray-100 dark:divide-white/10">
                    @forelse($this->participants as $p)
                        <tr class="hover:bg-muted/30">
                            {{-- Name + para/wheelchair marker --}}
                            <td class="whitespace-nowrap px-3 py-2">
                                @if($p->is_para || $p->uses_wheelchair)
                                    <span class="text-yellow-500" title="Para/Wheelchair">★</span>
                                @endif
                                {{ $p->last_name }}, {{ $p->first_name }}
                            </td>

                            {{-- Division --}}
                            <td class="px-3 py-2">
                                <flux:select
                                    placeholder="—"
                                    :disabled="$divisionOptions === []"
                                    wire:change="save({{ $p->id }}, 'division_name', $event.target.value)"
                                >
                                    <option value="">—</option>
                                    @foreach($divisionOptions as $opt)
                                        <option value="{{ $opt }}" @selected($p->division_name === $opt)>{{ $opt }}</option>
                                    @endforeach
                                </flux:select>
                            </td>

                            {{-- Bow --}}
                            <td class="px-3 py-2">
                                <flux:select
                                    placeholder="—"
                                    :disabled="$bowTypeOptions === []"
                                    wire:change="save({{ $p->id }}, 'bow_type', $event.target.value)"
                                >
                                    <option value="">—</option>
                                    @foreach($bowTypeOptions as $opt)
                                        <option value="{{ $opt }}" @selected($p->bow_type === $opt)>{{ $opt }}</option>
                                    @endforeach
                                </flux:select>
                            </td>

                            {{-- Line Time --}}
                            <td class="px-3 py-2">
                                <flux:select wire:change="save({{ $p->id }}, 'line_time_id', $event.target.value)">
                                    <option value="">—</option>
                                    @foreach($lineTimeOptions as $id => $label)
                                        <option value="{{ $id }}" @selected((int) $p->line_time_id === (int) $id)>{{ $label }}</option>
                                    @endforeach
                                </flux:select>
                            </td>

                            {{-- Lane (full-width bg + ring) --}}
                            <td class="px-3 py-2">
                                @php
                                    $ltId    = (int) ($p->line_time_id ?? 0);
                                    $laneSel = (int) ($p->assigned_lane ?? 0);

                                    $laneBad = false; $laneGood = false;
                                    if ($ltId && $laneSel) {
                                        if ($slotsSingle) { $laneBad = $this->isLaneTaken($ltId, $laneSel, (int) $p->id); }
                                        else { $laneBad = $this->isLaneLockedBySC($ltId, $laneSel, (int) $p->id); }
                                        $laneGood = ! $laneBad;
                                    }

                                    $laneBg = $laneGood ? 'bg-emerald-500/15 ring-1 ring-emerald-500/30'
                                            : ($laneBad ? 'bg-red-500/15 ring-1 ring-red-500/30'
                                            : 'bg-white dark:bg-white/5');
                                @endphp

                                <div x-data="{ open: false }" class="relative w-full rounded-md {{ $laneBg }}">
                                    <button
                                        type="button"
                                        class="w-full rounded-md border border-gray-300/60 px-2 py-1.5 text-left dark:border-white/10 bg-transparent"
                                        x-on:click="open = !open"
                                    >
                                        {{ $laneSel ?: '—' }}
                                    </button>

                                    {{-- flyout --}}
                                    <div
                                        x-show="open"
                                        x-cloak
                                        x-transition
                                        x-on:click.outside="open = false"
                                        class="absolute left-0 top-full z-40 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-md dark:border-white/10 dark:bg-zinc-900"
                                    >
                                        <ul class="max-h-60 overflow-auto py-1">
                                            <li>
                                                <button
                                                    type="button"
                                                    class="w-full px-2 py-1 text-left hover:bg-gray-50 dark:hover:bg-white/10"
                                                    x-on:click="$wire.save({{ $p->id }}, 'assigned_lane', null); open=false"
                                                >
                                                    —
                                                </button>
                                            </li>

                                            @foreach($laneOptions as $lane)
                                                @php
                                                    $disabled = ($slotsSingle && $ltId)
                                                        ? $this->isLaneTaken($ltId, (int) $lane, (int) $p->id)
                                                        : ($ltId ? $this->isLaneLockedBySC($ltId, (int) $lane, (int) $p->id) : false);
                                                @endphp
                                                <li>
                                                    <button
                                                        type="button"
                                                        :disabled="{{ $disabled ? 'true' : 'false' }}"
                                                        class="w-full px-2 py-1 text-left hover:bg-gray-50 dark:hover:bg-white/10 {{ $disabled ? 'line-through opacity-60 cursor-not-allowed' : '' }}"
                                                        @click="$el.disabled || ($wire.save({{ $p->id }}, 'assigned_lane', {{ (int) $lane }}), open=false)"
                                                    >
                                                        {{ $lane }}
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </td>

                            {{-- Slot (full-width bg + ring + SC) --}}
                            <td class="px-3 py-2">
                                @php
                                    $ltId = (int) ($p->line_time_id ?? 0);
                                    $lane = (int) ($p->assigned_lane ?? 0);
                                @endphp

                                @if($slotsSingle)
                                    <span class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                        N/A
                                    </span>
                                @else
                                    @php
                                        $slotSel = $p->assigned_slot ? mb_strtoupper($p->assigned_slot) : null;
                                        $slotBad = false; $slotGood = false;
                                        if ($ltId && $lane && $slotSel) {
                                            $slotBad = $this->isLaneLockedBySC($ltId, $lane, (int) $p->id)
                                                    || $this->isSlotTaken($ltId, $lane, $slotSel, (int) $p->id);
                                            $slotGood = ! $slotBad;
                                        }

                                        $slotBg = $slotGood ? 'bg-emerald-500/15 ring-1 ring-emerald-500/30'
                                                : ($slotBad ? 'bg-red-500/15 ring-1 ring-red-500/30'
                                                : 'bg-white dark:bg-white/5');
                                    @endphp

                                    <div x-data="{ open: false }" class="relative w-full rounded-md {{ $slotBg }}">
                                        <button
                                            type="button"
                                            class="w-full rounded-md border border-gray-300/60 px-2 py-1.5 text-left dark:border-white/10 bg-transparent"
                                            x-on:click="if({{ (int) ($lane && $ltId) }}) open = !open"
                                        >
                                            {{ $slotSel ?: '—' }}
                                        </button>

                                        {{-- flyout --}}
                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition
                                            x-on:click.outside="open = false"
                                            class="absolute left-0 top-full z-40 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-md dark:border-white/10 dark:bg-zinc-900"
                                        >
                                            <ul class="max-h-60 overflow-auto py-1">
                                                <li>
                                                    <button
                                                        type="button"
                                                        class="w-full px-2 py-1 text-left hover:bg-gray-50 dark:hover:bg-white/10"
                                                        x-on:click="$wire.save({{ $p->id }}, 'assigned_slot', null); open=false"
                                                    >
                                                        —
                                                    </button>
                                                </li>

                                                {{-- normal slots --}}
                                                @foreach($slotOptions as $slot)
                                                    @continue($slot === 'SC')
                                                    @php
                                                        $disabled = ($ltId && $lane)
                                                            ? ($this->isLaneLockedBySC($ltId, $lane, (int) $p->id)
                                                                || $this->isSlotTaken($ltId, $lane, (string) $slot, (int) $p->id))
                                                            : true;
                                                    @endphp
                                                    <li>
                                                        <button
                                                            type="button"
                                                            :disabled="{{ $disabled ? 'true' : 'false' }}"
                                                            class="w-full px-2 py-1 text-left hover:bg-gray-50 dark:hover:bg-white/10 {{ $disabled ? 'line-through opacity-60 cursor-not-allowed' : '' }}"
                                                            @click="$el.disabled || ($wire.save({{ $p->id }}, 'assigned_slot', '{{ $slot }}'), open=false)"
                                                        >
                                                            {{ $slot }}
                                                        </button>
                                                    </li>
                                                @endforeach

                                                {{-- SC (use whole lane) --}}
                                                @if(!$slotsSingle && count($slotOptions) >= 2)
                                                    @php
                                                        $disabledSC = ($ltId && $lane)
                                                            ? $this->hasAnyOccupant($ltId, $lane, (int) $p->id)
                                                            : true;
                                                    @endphp
                                                    <li>
                                                        <button
                                                            type="button"
                                                            :disabled="{{ $disabledSC ? 'true' : 'false' }}"
                                                            class="w-full px-2 py-1 text-left hover:bg-gray-50 dark:hover:bg-white/10 {{ $disabledSC ? 'line-through opacity-60 cursor-not-allowed' : '' }}"
                                                            @click="$el.disabled || ($wire.save({{ $p->id }}, 'assigned_slot', 'SC'), open=false)"
                                                        >
                                                            SC
                                                        </button>
                                                    </li>
                                                @endif
                                            </ul>
                                        </div>
                                    </div>
                                @endif
                            </td>

                            {{-- Row actions --}}
                            <td class="px-3 py-2 text-right">
                                <flux:dropdown align="end">
                                    <flux:button size="sm" variant="ghost" icon:trailing="chevron-down">
                                        Actions
                                    </flux:button>
                                    <flux:menu class="min-w-44">
                                        <flux:menu.item
                                            icon="x-mark"
                                            x-on:click.prevent="$wire.clearParticipantAssignment({{ $p->id }})"
                                        >
                                            Clear lane/slot
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-sm text-gray-500 dark:text-gray-400">
                                No participants yet. Upload a CSV to get started.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Pager --}}
            @php($p = $this->participants)
            @php($w = $this->pageWindow)

            <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-transparent sm:px-6">
                <div class="flex flex-1 justify-between sm:hidden">
                    <button
                        wire:click="prevPage"
                        @disabled($p->onFirstPage())
                        class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                    >
                        Previous
                    </button>

                    <button
                        wire:click="nextPage"
                        @disabled(!$p->hasMorePages())
                        class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                    >
                        Next
                    </button>
                </div>

                <div class="hidden flex-1 items-center justify-between sm:flex">
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
                            <button
                                wire:click="prevPage"
                                class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:inset-ring-gray-700 dark:hover:bg:white/5"
                                @disabled($p->onFirstPage())
                            >
                                <span class="sr-only">Previous</span>
                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
                                    <path d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" fill-rule="evenodd" />
                                </svg>
                            </button>

                            @for ($i = $w['start']; $i <= $w['end']; $i++)
                                @if ($i === $w['current'])
                                    <span
                                        aria-current="page"
                                        class="relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white dark:bg-indigo-500"
                                    >
                                        {{ $i }}
                                    </span>
                                @else
                                    <button
                                        wire:click="goto({{ $i }})"
                                        class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:text-gray-200 dark:inset-ring-gray-700 dark:hover:bg:white/5"
                                    >
                                        {{ $i }}
                                    </button>
                                @endif
                            @endfor

                            <button
                                wire:click="nextPage"
                                class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:inset-ring-gray-700 dark:hover:bg-white/5"
                                @disabled(!$p->hasMorePages())
                            >
                                <span class="sr-only">Next</span>
                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
                                    <path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
                                </svg>
                            </button>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        {{-- CSV Sheet --}}
        @if($showCsvSheet)
            <div class="fixed inset-0 z-40">
                <div class="absolute inset-0 bg-black/40" wire:click="$set('showCsvSheet', false)"></div>

                <div class="absolute inset-y-0 right-0 h-full w-full max-w-2xl overflow-y-auto bg-white p-6 shadow-xl dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Upload participants CSV
                        </h2>
                        <button
                            class="rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg:white/10"
                            wire:click="$set('showCsvSheet', false)"
                        >
                            ✕
                        </button>
                    </div>

                    <form wire:submit.prevent="stageImportCsv" class="mt-6 space-y-6">
                        <div>
                            <flux:label for="csv">CSV file</flux:label>
                            <input
                                id="csv"
                                type="file"
                                wire:model="csv"
                                accept=".csv,text/csv"
                                class="mt-1 block w-full text-sm"
                            />
                            @error('csv')
                                <flux:text size="sm" class="mt-1 text-red-500">{{ $message }}</flux:text>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <flux:label>Apply this upload to the line</flux:label>
                            <flux:select wire:model.number="applyLineTimeId" class="w-full">
                                <option value="">— No preference (set later) —</option>
                                @foreach($lineTimeOptions as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </flux:select>
                            <flux:text size="xs" muted>
                                Every uploaded participant will be assigned this line time. Lanes/slots can be set later.
                            </flux:text>
                        </div>

                        <div class="flex justify-end gap-3">
                            <flux:button type="button" variant="ghost" wire:click="$set('showCsvSheet', false)">
                                Cancel
                            </flux:button>
                            <flux:button type="submit" variant="primary">
                                Upload & Review
                            </flux:button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</section>
