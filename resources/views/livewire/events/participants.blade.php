<?php

use App\Models\Event;
use App\Models\EventLineTime;
use App\Models\EventParticipant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Event Participants Manager
 *
 * QOL:
 * - Manual lane/slot entry (no dropdowns)
 * - Enforce lane/slot bounds (no lane 13 if max is 12; no slot E if allowed is A-D (+LK))
 * - Keep green/red field backgrounds
 * - If user attempts a duplicate: toast + field turns red (even though value is not saved)
 * - Under each field show "Allowed" that reflects what's LEFT for that participant/line/lane
 *
 * Fixes included:
 * - Multi-slot lanes allow 1/A, 1/B, 1/C, etc. (conflict checks are slot-based, LK locks lane)
 * - Para/Wheelchair rows get a left→right blue→red gradient background
 */
new class extends Component
{
    use WithFileUploads, WithPagination;

    public Event $event;

    // pagination
    protected string $pageName = 'participantsPage';

    public int $perPage = 10;

    // search/sort
    public string $search = '';

    public string $sort = 'last_name';

    public string $direction = 'asc';

    // filters
    public ?string $filterDivision = null;

    public ?string $filterBowType = null;

    public ?int $filterLineTime = null;

    // picklists
    public array $divisionOptions = [];

    public array $bowTypeOptions = [];

    public array $lineTimeOptions = [];      // [id => label]

    public array $laneOptions = [];          // [1,2,...]

    public array $slotOptions = [];          // ['A','B',...,'LK']

    // ruleset slot mode
    public bool $slotsSingle = false;        // true => N/A, store NULL

    // occupancy map: [line_time_id][lane][slotKey] => participant_id
    public array $taken = [];

    // "attempted invalid" flags (for red highlight after a failed save)
    public array $invalidLane = [];          // [participant_id => true]

    public array $invalidSlot = [];          // [participant_id => true]

    // CSV drawer
    public bool $showCsvSheet = false;

    public $csv;

    public ?int $applyLineTimeId = null;

    public bool $debug = false;

    public int $renderNonce = 0;

    public function mount(Event $event): void
    {
        Gate::authorize('manageParticipants', $event);

        $this->event = $event->load(['ruleset']);

        $this->rebuildPicklists();

        $this->lineTimeOptions = [];
        $lts = EventLineTime::query()
            ->where('event_id', $this->event->id)
            ->orderBy('line_date')
            ->orderBy('start_time')
            ->get();

        foreach ($lts as $lt) {
            $this->lineTimeOptions[(int) $lt->id] = $this->formatLineTimeIsoAware($lt);
        }

        $this->buildTaken();
    }

    // ---------------------------
    // CSV flow
    // ---------------------------

    public function openCsv(): void
    {
        $this->showCsvSheet = true;
        $this->csv = null;
        $this->applyLineTimeId = null;
    }

    public function closeCsv(): void
    {
        $this->showCsvSheet = false;
        $this->csv = null;
        $this->applyLineTimeId = null;
    }

    public function importCsv(): void
    {
        Gate::authorize('manageParticipants', $this->event);

        if (! $this->csv) {
            $this->dispatch('toast', type: 'warning', message: 'Please choose a CSV file.');

            return;
        }

        // Store temp
        $path = $this->csv->store('tmp');
        $full = Storage::path($path);

        $handle = @fopen($full, 'r');
        if (! $handle) {
            $this->dispatch('toast', type: 'warning', message: 'Unable to read CSV.');

            return;
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            $this->dispatch('toast', type: 'warning', message: 'CSV appears empty.');

            return;
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $idx = function (string $col) use ($header): ?int {
            $i = array_search($col, $header, true);

            return $i === false ? null : (int) $i;
        };

        $iFirst = $idx('first_name');
        $iLast = $idx('last_name');
        $iEmail = $idx('email');
        $iDiv = $idx('division_name');
        $iBow = $idx('bow_type');
        $iPara = $idx('is_para');
        $iWheel = $idx('uses_wheelchair');

        if ($iFirst === null || $iLast === null) {
            fclose($handle);
            $this->dispatch('toast', type: 'warning', message: 'CSV must include first_name and last_name columns.');

            return;
        }

        $created = 0;
        $updated = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $first = trim((string) ($row[$iFirst] ?? ''));
            $last = trim((string) ($row[$iLast] ?? ''));
            if ($first === '' || $last === '') {
                continue;
            }

            $email = $iEmail !== null ? trim((string) ($row[$iEmail] ?? '')) : null;
            $div = $iDiv !== null ? trim((string) ($row[$iDiv] ?? '')) : null;
            $bow = $iBow !== null ? trim((string) ($row[$iBow] ?? '')) : null;

            $isPara = $iPara !== null ? (bool) filter_var($row[$iPara] ?? false, FILTER_VALIDATE_BOOLEAN) : false;
            $isWheel = $iWheel !== null ? (bool) filter_var($row[$iWheel] ?? false, FILTER_VALIDATE_BOOLEAN) : false;

            // Upsert preference: by event + email if present; else by event + name
            $q = $this->event->participants()->newQuery()->where('event_id', $this->event->id);

            if ($email) {
                $q->where('email', $email);
            } else {
                $q->where('first_name', $first)->where('last_name', $last);
            }

            /** @var \App\Models\EventParticipant|null $p */
            $p = $q->first();

            if (! $p) {
                $p = new EventParticipant;
                $p->event_id = $this->event->id;
                $created++;
            } else {
                $updated++;
            }

            $p->first_name = $first;
            $p->last_name = $last;
            $p->email = $email ?: $p->email;

            if ($div !== null && $div !== '') {
                $p->division_name = $div;
            }
            if ($bow !== null && $bow !== '') {
                $p->bow_type = $bow;
            }

            $p->is_para = $isPara;
            $p->uses_wheelchair = $isWheel;

            if ($this->applyLineTimeId && array_key_exists($this->applyLineTimeId, $this->lineTimeOptions)) {
                $p->line_time_id = $this->applyLineTimeId;
            }

            $p->save();
        }

        fclose($handle);
        Storage::delete($path);

        $this->buildTaken();
        $this->dispatch('toast', type: 'success', message: "Imported. Created {$created}, updated {$updated}.");
        $this->closeCsv();
    }

    // ---------------------------
    // Availability helpers (LEFT)
    // ---------------------------

    /** Uppercase slot options excluding LK. */
    private function normalSlots(): array
    {
        if ($this->slotsSingle) {
            return [];
        }

        return array_values(array_unique(array_map(
            fn ($s) => mb_strtoupper(trim((string) $s)),
            array_filter($this->slotOptions, fn ($s) => mb_strtoupper((string) $s) !== 'LK')
        )));
    }

    private function maxLane(): int
    {
        return (int) (count($this->laneOptions) ? max($this->laneOptions) : 0);
    }

    /** Lanes that still have capacity for THIS participant (based on line time + occupancy). */
    public function availableLanesLeft(int $lineTimeId, int $exceptId = 0): array
    {
        if (! $lineTimeId) {
            return [];
        }

        $lanes = $this->laneOptions ?: [];
        if (! $lanes) {
            return [];
        }

        // Single-slot: lane is available if not taken by someone else
        if ($this->slotsSingle) {
            return array_values(array_filter($lanes, function ($lane) use ($lineTimeId, $exceptId) {
                return ! $this->isLaneTaken($lineTimeId, (int) $lane, $exceptId);
            }));
        }

        // Multi-slot: lane is available if:
        // - not LK locked by someone else, AND
        // - has at least one normal slot free (or the participant already occupies a slot there)
        $norm = $this->normalSlots();

        return array_values(array_filter($lanes, function ($lane) use ($lineTimeId, $exceptId, $norm) {
            $lane = (int) $lane;

            if ($this->isLaneLockedByLK($lineTimeId, $lane, $exceptId)) {
                return false;
            }

            // if participant already on this lane, keep it allowed
            if (isset($this->taken[$lineTimeId][$lane])) {
                foreach ($this->taken[$lineTimeId][$lane] as $k => $pid) {
                    if ($pid === $exceptId) {
                        return true;
                    }
                }
            }

            // any free normal slot?
            foreach ($norm as $slot) {
                if (! isset($this->taken[$lineTimeId][$lane][$slot])) {
                    return true;
                }
            }

            return false;
        }));
    }

    /** Slots left for THIS participant on a specific lane (based on line time + lane occupancy). */
    public function availableSlotsLeft(int $lineTimeId, int $lane, int $exceptId = 0): array
    {
        if (! $lineTimeId || ! $lane || $this->slotsSingle) {
            return [];
        }

        $lane = (int) $lane;
        if ($this->isLaneLockedByLK($lineTimeId, $lane, $exceptId)) {
            return []; // someone else LK'd the lane, nothing available
        }

        $norm = $this->normalSlots();
        $left = [];

        foreach ($norm as $slot) {
            if (! isset($this->taken[$lineTimeId][$lane][$slot]) || (int) $this->taken[$lineTimeId][$lane][$slot] === $exceptId) {
                $left[] = $slot;
            }
        }

        // LK available only if lane has no other occupants
        $hasAnyOther = $this->hasAnyOccupant($lineTimeId, $lane, $exceptId);
        if (! $hasAnyOther) {
            $left[] = 'LK';
        }

        return $left;
    }

    /** Nicely format allowed lanes as either "2–12" or "1, 3, 4". */
    public function formatAllowedLanes(array $lanes): string
    {
        $lanes = array_values(array_unique(array_map('intval', $lanes)));
        sort($lanes);
        if (! $lanes) {
            return '—';
        }

        // contiguous range?
        if (count($lanes) >= 2 && $lanes === range($lanes[0], $lanes[count($lanes) - 1])) {
            return $lanes[0].'–'.$lanes[count($lanes) - 1];
        }

        // compact if large list
        if (count($lanes) > 10) {
            return $lanes[0].'–'.$lanes[count($lanes) - 1];
        }

        return implode(', ', $lanes);
    }

    public function formatAllowedSlots(array $slots): string
    {
        $slots = array_values(array_unique(array_map(fn ($s) => mb_strtoupper((string) $s), $slots)));
        if (! $slots) {
            return '—';
        }
        // show LK at end
        usort($slots, fn ($a, $b) => ($a === 'LK') <=> ($b === 'LK'));

        return implode(', ', $slots);
    }

    // ---------------------------
    // Occupancy + conflict checks
    // ---------------------------

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

            if (! isset($taken[$lt])) {
                $taken[$lt] = [];
            }
            if (! isset($taken[$lt][$lane])) {
                $taken[$lt][$lane] = [];
            }

            if ($this->slotsSingle) {
                $taken[$lt][$lane][null] ??= (int) $r->id;

                continue;
            }

            $slotRaw = $r->assigned_slot;
            if ($slotRaw === null || $slotRaw === '') {
                continue;
            }

            $slot = mb_strtoupper((string) $slotRaw);
            if ($slot === 'LK') {
                $taken[$lt][$lane]['LK'] ??= (int) $r->id;
            } else {
                $taken[$lt][$lane][$slot] ??= (int) $r->id;
            }
        }

        $this->taken = $taken;
    }

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

    public function isLaneLockedByLK(int $lineTimeId, int $lane, int $exceptId = 0): bool
    {
        return isset($this->taken[$lineTimeId][$lane]['LK'])
            && $this->taken[$lineTimeId][$lane]['LK'] !== $exceptId;
    }

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

    public function isSlotTaken(int $lineTimeId, int $lane, string $slot, int $exceptId = 0): bool
    {
        if ($slot === '' || ! isset($this->taken[$lineTimeId][$lane])) {
            return false;
        }
        if ($this->isLaneLockedByLK($lineTimeId, $lane, $exceptId)) {
            return true;
        }

        $slotKey = mb_strtoupper((string) $slot);

        return isset($this->taken[$lineTimeId][$lane][$slotKey])
            && $this->taken[$lineTimeId][$lane][$slotKey] !== $exceptId;
    }

    // ---------------------------
    // UX: reset invalid flags
    // ---------------------------

    private function clearInvalidFor(int $participantId, ?string $field = null): void
    {
        if ($field === null || $field === 'assigned_lane') {
            unset($this->invalidLane[$participantId]);
        }
        if ($field === null || $field === 'assigned_slot') {
            unset($this->invalidSlot[$participantId]);
        }
    }

    // ---------------------------
    // Save (manual lane/slot)
    // ---------------------------

    public function save(int $id, string $field, $value): void
    {
        Gate::authorize('manageParticipants', $this->event);

        $allowed = ['division_name', 'bow_type', 'line_time_id', 'assigned_lane', 'assigned_slot'];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        // Normalize value
        $val = is_string($value) ? trim($value) : $value;
        if ($val === '' || $val === 'null') {
            $val = null;
        }

        /** @var \App\Models\EventParticipant|null $p */
        $p = $this->event->participants()->whereKey($id)->first();
        if (! $p) {
            return;
        }

        // Changing line resets lane/slot + clears invalid markers
        if ($field === 'line_time_id') {
            $val = $val !== null ? (int) $val : null;
            if ($val !== null && ! array_key_exists($val, $this->lineTimeOptions)) {
                $this->dispatch('toast', type: 'warning', message: 'Invalid line time.');

                return;
            }

            $p->line_time_id = $val;
            $p->assigned_lane = null;
            $p->assigned_slot = null;
            $p->save();

            $this->clearInvalidFor($p->id);
            $this->buildTaken();
            $this->dispatch('toast', type: 'success', message: 'Saved.');

            return;
        }

        // Division/bow validations
        if ($field === 'division_name' && $this->divisionOptions && $val !== null && ! in_array($val, $this->divisionOptions, true)) {
            $this->dispatch('toast', type: 'warning', message: 'Invalid division selection.');

            return;
        }
        if ($field === 'bow_type' && $this->bowTypeOptions && $val !== null && ! in_array($val, $this->bowTypeOptions, true)) {
            $this->dispatch('toast', type: 'warning', message: 'Invalid bow type selection.');

            return;
        }

        // Lane/slot require line time
        if (in_array($field, ['assigned_lane', 'assigned_slot'], true) && ! $p->line_time_id) {
            $this->dispatch('toast', type: 'warning', message: 'Set Line Time first.');

            return;
        }

        // Candidate values
        $newLine = (int) $p->line_time_id;
        $newLane = $p->assigned_lane;
        $newSlot = $p->assigned_slot;

        // Lane validation + range
        if ($field === 'assigned_lane') {
            $newLane = $val !== null ? (int) $val : null;

            if ($newLane !== null) {
                $maxLane = $this->maxLane();
                if ($maxLane > 0 && ($newLane < 1 || $newLane > $maxLane)) {
                    $this->invalidLane[$p->id] = true;
                    $this->dispatch('toast', type: 'warning', message: "Lane must be between 1 and {$maxLane}.");

                    return;
                }
            }

            // clearing lane clears slot too
            if ($newLane === null) {
                $newSlot = null;
            }
        }

        // Slot validation (allowed set)
        if ($field === 'assigned_slot') {
            if ($this->slotsSingle) {
                $this->dispatch('toast', type: 'info', message: 'Slots are N/A for this ruleset.');

                return;
            }
            if (! $p->assigned_lane) {
                $this->dispatch('toast', type: 'warning', message: 'Set Lane first.');

                return;
            }

            $newSlot = $val !== null ? mb_strtoupper(trim((string) $val)) : null;
            if ($newSlot === '') {
                $newSlot = null;
            }

            if ($newSlot !== null) {
                $allowedSlots = array_map(fn ($s) => mb_strtoupper((string) $s), $this->slotOptions);
                if (! in_array($newSlot, $allowedSlots, true)) {
                    $this->invalidSlot[$p->id] = true;
                    $this->dispatch(
                        'toast',
                        type: 'warning',
                        message: 'Invalid slot. Use one of: '.$this->formatAllowedSlots($this->availableSlotsLeft((int) $p->line_time_id, (int) $p->assigned_lane, (int) $p->id)).'.'
                    );

                    return;
                }
            }
        }

        // Single-slot: always null slot
        if ($this->slotsSingle) {
            $newSlot = null;
        }

        // ---- Conflict checks (duplicate) ----
        $conflict = false;

        if ($newLine && $newLane) {
            $qBase = $this->event->participants()
                ->where('line_time_id', (int) $newLine)
                ->where('assigned_lane', (int) $newLane)
                ->where('id', '<>', $p->id);

            if ($this->slotsSingle) {
                $conflict = $qBase->exists(); // any occupant blocks lane
            } else {
                // Multi-slot:
                // - LK blocks the entire lane
                // - otherwise only the same slot is a conflict (case-insensitive)
                if ($newSlot) {
                    $slotU = mb_strtoupper((string) $newSlot);

                    if ($slotU === 'LK') {
                        $conflict = $qBase->exists(); // LK requires empty lane
                    } else {
                        $hasLK = (clone $qBase)
                            ->whereRaw('UPPER(assigned_slot) = ?', ['LK'])
                            ->exists();

                        $hasSameSlot = (clone $qBase)
                            ->whereRaw('UPPER(assigned_slot) = ?', [$slotU])
                            ->exists();

                        $conflict = $hasLK || $hasSameSlot;
                    }
                }
            }
        }

        if ($conflict) {
            // mark the field red (lane vs slot) + toast, but do NOT save
            if ($field === 'assigned_lane') {
                $this->invalidLane[$p->id] = true;
            }
            if ($field === 'assigned_slot') {
                $this->invalidSlot[$p->id] = true;
            }

            $this->dispatch('toast', type: 'warning', message: 'That lane/slot is already taken for the selected line.');

            return;
        }

        // Save
        if ($field === 'assigned_lane') {
            $p->assigned_lane = $newLane;
            $p->assigned_slot = $newSlot;
        } elseif ($field === 'assigned_slot') {
            $p->assigned_slot = $newSlot;
        } else {
            $p->{$field} = $val;
        }

        if ($this->slotsSingle) {
            $p->assigned_slot = null;
        }

        $p->save();

        // clear invalid flags for this field
        $this->clearInvalidFor($p->id, $field);

        $this->buildTaken();
        $this->dispatch('toast', type: 'success', message: 'Saved.');
    }

    // ---------------------------
    // Actions: clear/reset/auto-assign
    // ---------------------------

    public function clearParticipantAssignment(int $participantId): void
    {
        Gate::authorize('manageParticipants', $this->event);

        /** @var \App\Models\EventParticipant|null $p */
        $p = $this->event->participants()->whereKey($participantId)->first();
        if (! $p) {
            return;
        }

        $p->assigned_lane = null;
        $p->assigned_slot = null;
        $p->save();

        $this->clearInvalidFor($p->id);
        $this->buildTaken();
        $this->dispatch('toast', type: 'success', message: 'Lane/slot cleared.');
    }

    public function resetAssignments(): void
    {
        Gate::authorize('manageParticipants', $this->event);

        $this->event->participants()
            ->update(['assigned_lane' => null, 'assigned_slot' => null]);

        $this->invalidLane = [];
        $this->invalidSlot = [];

        $this->buildTaken();

        // Force Livewire to fully re-key the table/rows so inputs + Alpine reset visually
        $this->renderNonce++;

        $this->dispatch('toast', type: 'success', message: 'All lane/slot assignments reset.');
    }

    public function autoAssign(): void
    {
        Gate::authorize('manageParticipants', $this->event);

        // Rebuild taken so we don't collide
        $this->buildTaken();

        $maxLane = $this->maxLane();
        if ($maxLane <= 0) {
            $this->dispatch('toast', type: 'warning', message: 'No lane configuration found.');

            return;
        }

        $normalSlots = $this->normalSlots(); // excludes LK

        $participants = $this->event->participants()
            ->whereNotNull('line_time_id')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $assigned = 0;

        foreach ($participants as $p) {
            // skip already assigned
            if ($p->assigned_lane) {
                continue;
            }

            $lt = (int) $p->line_time_id;

            if ($this->slotsSingle) {
                // pick first free lane
                $lane = null;
                for ($l = 1; $l <= $maxLane; $l++) {
                    if (! $this->isLaneTaken($lt, $l, (int) $p->id)) {
                        $lane = $l;
                        break;
                    }
                }

                if (! $lane) {
                    continue;
                }

                $p->assigned_lane = $lane;
                $p->assigned_slot = null;
                $p->save();

                $assigned++;
                $this->buildTaken();

                continue;
            }

            // multi-slot: find first lane with a free normal slot and no LK lock
            $lane = null;
            $slot = null;

            for ($l = 1; $l <= $maxLane; $l++) {
                if ($this->isLaneLockedByLK($lt, $l, (int) $p->id)) {
                    continue;
                }

                foreach ($normalSlots as $s) {
                    if (! isset($this->taken[$lt][$l][$s])) {
                        $lane = $l;
                        $slot = $s;
                        break 2;
                    }
                }
            }

            if (! $lane || ! $slot) {
                continue;
            }

            $p->assigned_lane = $lane;
            $p->assigned_slot = $slot;
            $p->save();

            $assigned++;
            $this->buildTaken();
        }

        // ✅ Force Alpine slot inputs to re-initialize so x-data picks up the new slot value
        $this->renderNonce++;

        $this->dispatch('toast', type: 'success', message: "Auto-assign complete. Assigned {$assigned} participant(s).");
    }

    // ---------------------------
    // Picklists + formatting helpers
    // ---------------------------

    /**
     * Rebuild picklists for divisions, bow types, lanes and slot options.
     * Detects single-slot mode (N/A) and appends 'LK' for multi-slot lane-lock.
     */
    private function rebuildPicklists(): void
    {
        $ruleset = $this->event->ruleset ?? null;

        // Division/Bow picklists
        $this->divisionOptions = $this->extractNames($ruleset, 'divisions');
        $this->bowTypeOptions = $this->extractNames($ruleset, 'bow_types');

        // Lanes (ruleset -> event override -> observed max -> default)
        $laneCount = (int) ($ruleset->lane_count ?? 0);

        if ($laneCount <= 0 && isset($this->event->lane_count) && (int) $this->event->lane_count > 0) {
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

        // Add LK for multi-slot layouts
        if (
            ! $this->slotsSingle
            && count($this->slotOptions) >= 2
            && ! in_array('LK', array_map(fn ($s) => mb_strtoupper((string) $s), $this->slotOptions), true)
        ) {
            $this->slotOptions[] = 'LK';
        }
    }

    /**
     * Extract an array of "name" values from a ruleset relation/array property.
     * Fallback to distinct values observed on participants for the event.
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

        // Try relation first
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
                // ignore
            }
        }

        // Try raw array/json field
        $val = $ruleset->{$prop} ?? null;
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

        // Fallback: observed on participants
        $col = $prop === 'divisions' ? 'division_name' : 'bow_type';

        return $this->event->participants()
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

        // Array of strings/objects
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

        // String: "A,B" | "A/B" | "AB" | "SINGLE" | "N/A"
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

    /** Normalize "H:i" or any parsable time to "H:i:s". */
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

    /** Extract a Y-m-d date string from various date inputs. */
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

    /** Format EventLineTime as "Line m/d/Y h:iA → h:iA" (fallback to "Line {id}"). */
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
                // ignore
            }
        }

        return 'Line '.$lt->id;
    }

    // ---------------------------
    // Pagination helpers
    // ---------------------------

    /** Reset pagination when search changes. */
    public function updatingSearch(): void
    {
        $this->resetPage($this->pageName);
    }

    public function updatedFilterDivision()
    {
        $this->resetPage($this->pageName);
    }

    public function updatedFilterBowType()
    {
        $this->resetPage($this->pageName);
    }

    public function updatedFilterLineTime()
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
};

?>

<section class="w-full">
    @php
        $typeVal  = ($event->type->value ?? $event->type ?? null);
        $isClosed = ($typeVal === 'closed');

        $maxLane = count($laneOptions) ? (int) max($laneOptions) : 0;
    @endphp

    <div class="mx-auto max-w-7xl">
        {{-- Header --}}
        <div class="sm:flex sm:items-center sm:justify-between gap-4">
            <div class="sm:flex-auto">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ $event->title }} — Participants
                </h1>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    Manage event participants (search, filter, inline edit, and import).
                </p>

                {{-- Parameters banner --}}
                <div class="mt-3 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                        <div>
                            <span class="font-semibold">Lane range:</span>
                            <span class="font-mono">1–{{ $maxLane ?: '—' }}</span>
                        </div>
                        <div>
                            <span class="font-semibold">Slots:</span>
                            <span class="font-mono">
                                @if($slotsSingle)
                                    N/A
                                @else
                                    {{ implode(', ', array_values(array_filter($slotOptions, fn($s)=>mb_strtoupper((string)$s)!=='LK'))) }}
                                    @if(in_array('LK', array_map(fn($s)=>mb_strtoupper((string)$s), $slotOptions), true)) (+ LK)@endif
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sm:flex-none">
                <div class="flex items-center gap-2">
                    <flux:button as="a" href="{{ route('corporate.events.show', $event) }}" variant="ghost">
                        ← Back to event
                    </flux:button>

                    <flux:dropdown>
                        <flux:button icon:trailing="chevron-down">Actions</flux:button>
                        <flux:menu class="min-w-64">
                            @unless($isClosed)
                                <flux:menu.item href="{{ route('corporate.events.participants.template', $event) }}" icon="table-cells">
                                    Download CSV template
                                </flux:menu.item>
                            @endunless

                            <flux:menu.item href="{{ route('corporate.events.participants.export', $event) }}" icon="users">
                                Export participants
                            </flux:menu.item>

                            <flux:menu.item icon="sparkles" x-on:click.prevent="$wire.autoAssign()">
                                Auto-assign lanes & slots
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
                <flux:input icon="magnifying-glass" placeholder="Name, division, bow (email searchable)" wire:model.live.debounce.300ms="search" />
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
        <div class="mt-4 overflow-x-auto overflow-y-visible rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
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
                        @php
                            $pid   = (int) $p->id;
                            $ltId  = (int) ($p->line_time_id ?? 0);
                            $lane  = (int) ($p->assigned_lane ?? 0);
                            $slot  = $p->assigned_slot ? mb_strtoupper((string) $p->assigned_slot) : null;

                            // LEFT lists (dynamic)
                            $lanesLeft = $ltId ? $this->availableLanesLeft($ltId, $pid) : [];
                            $laneAllowedText = $ltId ? $this->formatAllowedLanes($lanesLeft) : '—';

                            $slotsLeft = ($ltId && $lane && ! $slotsSingle) ? $this->availableSlotsLeft($ltId, $lane, $pid) : [];
                            $slotAllowedText = ($ltId && $lane && ! $slotsSingle) ? $this->formatAllowedSlots($slotsLeft) : '—';

                            // lane bg (red if invalid attempt flagged)
                            $laneBad = isset($invalidLane[$pid]) ? true : false;
                            $laneGood = false;
                            if (! $laneBad && $ltId && $lane) {
                                if ($slotsSingle) { $laneBad = $this->isLaneTaken($ltId, $lane, $pid); }
                                else { $laneBad = $this->isLaneLockedByLK($ltId, $lane, $pid); }
                                $laneGood = ! $laneBad;
                            }

                            $laneBg = $laneGood ? 'bg-emerald-500/15 ring-1 ring-emerald-500/30'
                                    : ($laneBad ? 'bg-red-500/15 ring-1 ring-red-500/30'
                                    : 'bg-white dark:bg-white/5');

                            // slot bg (red if invalid attempt flagged)
                            $slotBad = isset($invalidSlot[$pid]) ? true : false;
                            $slotGood = false;
                            if (! $slotBad && $ltId && $lane && $slot && ! $slotsSingle) {
                                $slotBad = $this->isLaneLockedByLK($ltId, $lane, $pid) || $this->isSlotTaken($ltId, $lane, $slot, $pid);
                                $slotGood = ! $slotBad;
                            }
                            $slotBg = $slotGood ? 'bg-emerald-500/15 ring-1 ring-emerald-500/30'
                                    : ($slotBad ? 'bg-red-500/15 ring-1 ring-red-500/30'
                                    : 'bg-white dark:bg-white/5');

                            // Para/Wheelchair row gradient
                            $isParaRow = (bool) ($p->is_para || $p->uses_wheelchair);
                            $rowBg = $isParaRow
                                ? 'bg-gradient-to-r from-sky-500/10 via-indigo-500/5 to-rose-500/10 dark:from-sky-400/10 dark:via-indigo-400/5 dark:to-rose-400/10'
                                : '';
                        @endphp

                        <tr class="{{ $rowBg }} hover:bg-muted/30" wire:key="participant-{{ $p->id }}-{{ $renderNonce }}">
                            {{-- Name --}}
                            <td class="whitespace-nowrap px-3 py-2">
                                @if($p->is_para || $p->uses_wheelchair)
                                    <span class="text-yellow-500" title="Para/Wheelchair">★</span>
                                @endif
                                {{ $p->last_name }}, {{ $p->first_name }}
                            </td>

                            {{-- Division (select + subtitle) --}}
                            <td class="px-3 py-2">
                                <div class="w-full rounded-md bg-white dark:bg-white/5 px-2 py-1.5">
                                    <flux:select
                                        placeholder="—"
                                        :disabled="$divisionOptions === []"
                                        wire:change="save({{ $pid }}, 'division_name', $event.target.value)"
                                    >
                                        <option value="">—</option>
                                        @foreach($divisionOptions as $opt)
                                            <option value="{{ $opt }}" @selected($p->division_name === $opt)>{{ $opt }}</option>
                                        @endforeach
                                    </flux:select>
                                    <div class="mt-1 text-[10px] text-gray-500 dark:text-gray-400">
                                        Division
                                    </div>
                                </div>
                            </td>

                            {{-- Bow (select + subtitle) --}}
                            <td class="px-3 py-2">
                                <div class="w-full rounded-md bg-white dark:bg-white/5 px-2 py-1.5">
                                    <flux:select
                                        placeholder="—"
                                        :disabled="$bowTypeOptions === []"
                                        wire:change="save({{ $pid }}, 'bow_type', $event.target.value)"
                                    >
                                        <option value="">—</option>
                                        @foreach($bowTypeOptions as $opt)
                                            <option value="{{ $opt }}" @selected($p->bow_type === $opt)>{{ $opt }}</option>
                                        @endforeach
                                    </flux:select>
                                    <div class="mt-1 text-[10px] text-gray-500 dark:text-gray-400">
                                        Bow
                                    </div>
                                </div>
                            </td>

                            {{-- Line Time (select + subtitle) --}}
                            <td class="px-3 py-2">
                                <div class="w-full rounded-md bg-white dark:bg-white/5 px-2 py-1.5">
                                    <flux:select wire:change="save({{ $pid }}, 'line_time_id', $event.target.value)">
                                        <option value="">—</option>
                                        @foreach($lineTimeOptions as $id => $label)
                                            <option value="{{ $id }}" @selected((int) $p->line_time_id === (int) $id)>{{ $label }}</option>
                                        @endforeach
                                    </flux:select>
                                    <div class="mt-1 text-[10px] text-gray-500 dark:text-gray-400">
                                        Line time
                                    </div>
                                </div>
                            </td>

                            {{-- Lane (manual + dynamic Allowed left) --}}
                            <td class="px-3 py-2">
                                <div class="w-full rounded-md {{ $laneBg }} px-2 py-1.5">
                                    <flux:input
                                        type="number"
                                        inputmode="numeric"
                                        class="w-full"
                                        placeholder="—"
                                        :disabled="!$ltId"
                                        min="1"
                                        max="{{ $maxLane ?: 999 }}"
                                        value="{{ $lane ?: '' }}"
                                        wire:change="save({{ $pid }}, 'assigned_lane', $event.target.value)"
                                    />
                                    <div class="mt-1 text-[10px] text-gray-500 dark:text-gray-400">
                                        @if(!$ltId)
                                            Set line time first.
                                        @else
                                            Allowed: <span class="font-mono">{{ $laneAllowedText }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Slot (manual + dynamic Allowed left) --}}
                            <td class="px-3 py-2">
                                @if($slotsSingle)
                                    <span class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                        N/A
                                    </span>
                                @else
                                    <div class="w-full rounded-md {{ $slotBg }} px-2 py-1.5">
                                        <div
                                            wire:key="slotwrap-{{ $pid }}-{{ $renderNonce }}"
                                            x-data="{
                                                v: @js($slot ?: ''),
                                                onBlur() {
                                                    this.v = (this.v || '').toString().trim().toUpperCase();
                                                    $wire.save({{ $pid }}, 'assigned_slot', this.v === '' ? null : this.v);
                                                }
                                            }"
                                        >
                                            <flux:input
                                                type="text"
                                                class="w-full"
                                                placeholder="—"
                                                :disabled="!$ltId || !$lane"
                                                x-model="v"
                                                x-on:blur="onBlur()"
                                                maxlength="3"
                                                autocomplete="off"
                                                spellcheck="false"
                                            />
                                        </div>
                                        <div class="mt-1 text-[10px] text-gray-500 dark:text-gray-400">
                                            @if(!$ltId)
                                                Set line time first.
                                            @elseif(!$lane)
                                                Set lane first.
                                            @else
                                                Allowed: <span class="font-mono">{{ $slotAllowedText }}</span>
                                            @endif
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
                                        <flux:menu.item icon="x-mark" x-on:click.prevent="$wire.clearParticipantAssignment({{ $pid }})">
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
                    <button wire:click="prevPage" @disabled($p->onFirstPage())
                        class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10">
                        Previous
                    </button>
                    <button wire:click="nextPage" @disabled(!$p->hasMorePages())
                        class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10">
                        Next
                    </button>
                </div>

                <div class="hidden flex-1 items-center justify-between sm:flex">
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
                                class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:inset-ring-gray-700 dark:hover:bg:white/5"
                                @disabled($p->onFirstPage())>
                                <span class="sr-only">Previous</span>
                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
                                    <path d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" fill-rule="evenodd" />
                                </svg>
                            </button>

                            @for ($i = $w['start']; $i <= $w['end']; $i++)
                                @if ($i === $w['current'])
                                    <span aria-current="page" class="relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white dark:bg-indigo-500">
                                        {{ $i }}
                                    </span>
                                @else
                                    <button wire:click="goto({{ $i }})"
                                        class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:text-gray-200 dark:inset-ring-gray-700 dark:hover:bg:white/5">
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
        </div>

        {{-- CSV Sheet (drawer/modal) --}}
        @if($showCsvSheet)
            <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
                <div class="absolute inset-0 bg-black/40" x-on:click="$wire.closeCsv()"></div>

                <div class="relative w-full max-w-xl rounded-t-2xl bg-white p-5 shadow-xl dark:bg-gray-900 sm:rounded-2xl">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">Upload CSV</div>
                            <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                Columns required: <span class="font-mono">first_name, last_name</span> (email optional).
                            </div>
                        </div>

                        <flux:button variant="ghost" x-on:click.prevent="$wire.closeCsv()">Close</flux:button>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3">
                        <div>
                            <label class="mb-1 block text-xs text-muted-foreground">CSV file</label>
                            <input type="file" wire:model="csv" accept=".csv" class="block w-full text-sm" />
                            <div class="mt-1 text-[10px] text-gray-500 dark:text-gray-400">
                                Tip: download the template from Actions for the correct headers.
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs text-muted-foreground">Apply line time to imported rows (optional)</label>
                            <flux:select wire:model="applyLineTimeId" placeholder="Do not override">
                                <option value="">Do not override</option>
                                @foreach($lineTimeOptions as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </flux:select>
                        </div>

                        <div class="mt-2 flex items-center justify-end gap-2">
                            <flux:button variant="ghost" x-on:click.prevent="$wire.closeCsv()">Cancel</flux:button>
                            <flux:button variant="primary" color="indigo" wire:click="importCsv">
                                Import
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>
