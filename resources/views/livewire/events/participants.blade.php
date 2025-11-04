<?php
use App\Models\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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

    // filters (aligned to trimmed schema)
    public ?string $filterDivision = null;     // division_name

    public ?string $filterBowType = null;      // bow_type

    public ?int $filterLineTime = null;     // line_time_id

    public bool $filterParaOnly = false;    // is_para = 1

    public bool $filterWheelchairOnly = false; // uses_wheelchair = 1

    // picklists
    public array $divisionOptions = [];

    public array $bowTypeOptions = [];

    public array $lineTimeOptions = []; // [id => label]

    // CSV drawer
    public bool $showCsvSheet = false;

    public $csv;

    // Apply this upload to selected line time
    public ?int $applyLineTimeId = null;

    public function mount(Event $event): void
    {
        Gate::authorize('manageParticipants', $event);
        $this->event = $event;

        // Build picklists from existing participants
        $db = \App\Models\EventParticipant::query()->where('event_id', $this->event->id);

        $this->divisionOptions = (clone $db)
            ->whereNotNull('division_name')
            ->distinct()->orderBy('division_name')
            ->pluck('division_name')->filter()->values()->all();

        $this->bowTypeOptions = (clone $db)
            ->whereNotNull('bow_type')
            ->distinct()->orderBy('bow_type')
            ->pluck('bow_type')->filter()->values()->all();

        // Line time options (from event_line_times)
        // Line time options (from event_line_times)
        $this->lineTimeOptions = [];

        $lts = \App\Models\EventLineTime::query()
            ->where('event_id', $this->event->id)
            ->orderBy('line_date')
            ->orderBy('start_time')
            ->get();

        foreach ($lts as $lt) {
            $this->lineTimeOptions[(int) $lt->id] = $this->formatLineTimeIsoAware($lt);
        }

    }

    /** Format using event_line_times schema exactly: line_date (DATE), start_time (TIME), end_time (TIME) */
    private function formatLineTimeSchemaStrict(object $lt): string
    {
        $date = trim((string) ($lt->line_date ?? ''));
        $st = trim((string) ($lt->start_time ?? ''));
        $et = trim((string) ($lt->end_time ?? ''));

        // Build start \Carbon\Carbon
        $start = null;
        if ($date !== '' && $st !== '') {
            try {
                // Normalize time to H:i:s if needed
                if (preg_match('/^\d{1,2}:\d{2}$/', $st)) {
                    $st .= ':00';
                }
                $start = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$st);
            } catch (\Throwable) {
            }
        }

        // Build end on the same day, if present
        $end = null;
        if ($date !== '' && $et !== '') {
            try {
                if (preg_match('/^\d{1,2}:\d{2}$/', $et)) {
                    $et .= ':00';
                }
                $end = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$et);
            } catch (\Throwable) {
            }
        }

        if ($start) {
            $startStr = $start->format('n/j/Y g:iA');         // 11/1/2026 12:30PM
            $endStr = $end ? $end->format('g:iA') : null;   // 1:30PM

            return 'Line '.$startStr.($endStr ? ' → '.$endStr : '');
        }

        // Ultimate fallback (only if something is truly wrong)
        return 'Line '.$lt->id;
    }

    /** Try to build a Carbon start from whatever fields exist */
    private function normalizeStartCarbon(object $lt): ?\Carbon\Carbon
    {
        $pick = fn (array $keys) => collect($keys)->map(fn ($k) => $lt->{$k} ?? null)->first(fn ($v) => $v !== null && $v !== '');
        $dateLike = $pick(['starts_at', 'start_at', 'starts_on', 'date', 'day']);
        $timeLike = $pick(['start_time', 'starts_time', 'time', 'at']);

        try {
            // If a single datetime exists
            if ($dateLike && ! $timeLike) {
                return $dateLike instanceof \Carbon\Carbon
                    ? $dateLike
                    : \Carbon\Carbon::parse((string) $dateLike);
            }
            // If separate date & time columns exist, combine them
            if ($dateLike && $timeLike) {
                return \Carbon\Carbon::parse(trim((string) $dateLike.' '.(string) $timeLike));
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /** Create label like "Line 11/1/2026 12:30PM" (and "→ 1:30PM" if end exists) */
    private function makeLineTimeLabel(object $lt): string
    {
        $start = $this->normalizeStartCarbon($lt);

        // End time: try common fields and combine if split
        $endRaw = $lt->ends_at ?? $lt->end_at ?? $lt->ends_on ?? $lt->end ?? null;
        $endTimeRaw = $lt->end_time ?? $lt->ends_time ?? null;
        $end = null;
        try {
            if ($endRaw && ! $endTimeRaw) {
                $end = $endRaw instanceof \Carbon\Carbon ? $endRaw : \Carbon\Carbon::parse((string) $endRaw);
            } elseif ($start && $endTimeRaw) {
                // Same day with explicit end_time
                $end = \Carbon\Carbon::parse($start->toDateString().' '.(string) $endTimeRaw);
            }
        } catch (\Throwable) {
        }

        // Optional custom text fields
        $text = $lt->label ?? $lt->title ?? $lt->name ?? null;

        // Formats
        $fmtStart = $start?->format('n/j/Y g:iA');               // 11/1/2026 12:30PM
        $fmtEnd = $end
            ? ($start && $start->isSameDay($end) ? $end->format('g:iA') : $end->format('n/j/Y g:iA'))
            : null;

        // If no custom text, use the requested "Line <Day> <Time>" format
        if (! $text && $fmtStart) {
            return 'Line '.$fmtStart.($fmtEnd ? ' → '.$fmtEnd : '');
        }

        // Otherwise show custom text plus times in same numeric style
        if ($text && $fmtStart && $fmtEnd) {
            return "{$text} ({$fmtStart} → {$fmtEnd})";
        }
        if ($text && $fmtStart) {
            return "{$text} ({$fmtStart})";
        }
        if ($text) {
            return $text;
        }

        // Fallbacks
        if ($fmtStart && $fmtEnd) {
            return "{$fmtStart} → {$fmtEnd}";
        }
        if ($fmtStart) {
            return 'Line '.$fmtStart;
        }

        return 'Line '.$lt->id; // ultimate fallback
    }

    // pagination helpers
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

    public function updatedFilterParaOnly()
    {
        $this->resetPage($this->pageName);
    }

    public function updatedFilterWheelchairOnly()
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

    /** Paged + filtered + sorted participants (trimmed columns) */
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
                        ->orWhere('bow_type', 'LIKE', $s)
                        ->orWhere('notes', 'LIKE', $s);
                });
            })
            ->when($this->filterDivision, fn ($q, $v) => $q->where('division_name', $v))
            ->when($this->filterBowType, fn ($q, $v) => $q->where('bow_type', $v))
            ->when($this->filterLineTime, fn ($q, $v) => $q->where('line_time_id', (int) $v))
            ->when($this->filterParaOnly, fn ($q) => $q->where('is_para', 1))
            ->when($this->filterWheelchairOnly, fn ($q) => $q->where('uses_wheelchair', 1));

        $allowed = ['last_name', 'first_name', 'email', 'division_name', 'bow_type', 'line_time_id', 'assigned_lane', 'assigned_slot', 'created_at'];
        $col = in_array($this->sort, $allowed, true) ? $this->sort : 'last_name';
        $dir = $this->direction === 'desc' ? 'desc' : 'asc';

        return $q->orderBy($col, $dir)->orderBy('first_name', 'asc')
            ->paginate($this->perPage, ['*'], $this->pageName);
    }

    /** Pager window for desktop buttons */
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

    /** Open the upload drawer (closed events forbidden) */
    public function openCsv(): void
    {
        Gate::authorize('update', $this->event);
        $typeVal = ($this->event->type->value ?? $this->event->type ?? null);
        if ($typeVal === 'closed') {
            $this->dispatch('toast', type: 'warning', message: 'CSV import is disabled for closed events.');

            return;
        }

        // Preselect the first actual line time id (int) if available
        $firstId = count($this->lineTimeOptions) ? (int) array_key_first($this->lineTimeOptions) : null;
        $this->applyLineTimeId = $firstId;

        $this->csv = null;
        $this->showCsvSheet = true;
    }

    /**
     * Stage the CSV for paywalled import (EVENT context) with selected line time.
     * Expected headers: first_name,last_name,email,division_name,bow_type,is_para,uses_wheelchair,notes
     * Billing counts only NEW rows by email or (first|last if email missing)
     */
    public function stageImportCsv()
    {
        Gate::authorize('update', $this->event);

        $typeVal = ($this->event->type->value ?? $this->event->type ?? null);
        if ($typeVal === 'closed') {
            $this->dispatch('toast', type: 'warning', message: 'CSV import is disabled for closed events.');

            return;
        }

        $this->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        // Store privately
        $storedPath = $this->csv->storeAs(
            'private/participant-imports',
            now()->format('Ymd_His').'_'.$this->csv->getClientOriginalName()
        );

        $stream = Storage::readStream($storedPath);
        if (! $stream) {
            Storage::delete($storedPath);
            $this->addError('csv', 'Unable to read uploaded file.');

            return;
        }

        // Parse headers & rows
        $headers = fgetcsv($stream) ?: [];
        $map = $this->mapHeaders($headers);

        $csvKeys = [];
        $csvRows = []; // full row (we only use name/email for billing here)
        while (($row = fgetcsv($stream)) !== false) {
            $joined = implode('', array_map(fn ($v) => trim((string) $v), $row));
            if ($joined === '') {
                continue;
            }

            $assoc = [];
            foreach ($map as $i => $key) {
                $assoc[$key] = trim((string) ($row[$i] ?? ''));
            }

            $first = (string) ($assoc['first_name'] ?? '');
            $last = (string) ($assoc['last_name'] ?? '');
            $email = (string) ($assoc['email'] ?? '');

            if ($first === '' && $last === '' && $email === '') {
                continue;
            }

            $key = $this->canonicalKey($first, $last, $email);
            if (isset($csvKeys[$key])) {
                continue;
            }
            $csvKeys[$key] = true;

            $csvRows[] = [
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'division_name' => (string) ($assoc['division_name'] ?? ''),
                'bow_type' => (string) ($assoc['bow_type'] ?? ''),
                'is_para' => $this->toBool((string) ($assoc['is_para'] ?? '')),
                'uses_wheelchair' => $this->toBool((string) ($assoc['uses_wheelchair'] ?? '')),
                'notes' => (string) ($assoc['notes'] ?? ''),
                'key' => $key,
            ];
        }
        if (is_resource($stream)) {
            fclose($stream);
        }

        if (! count($csvRows)) {
            Storage::delete($storedPath);
            $this->addError('csv', 'No participant rows detected. Please check your CSV.');

            return;
        }

        // Build existing sets for billing
        $emails = array_values(array_unique(array_filter(array_map(fn ($r) => $r['email'] ?: null, $csvRows))));
        $existingEmailSet = [];
        if ($emails) {
            $existing = $this->event->participants()
                ->whereIn('email', $emails)
                ->pluck('email')->all();
            foreach ($existing as $e) {
                if ($e !== null && $e !== '') {
                    $existingEmailSet[mb_strtolower(trim($e))] = true;
                }
            }
        }
        $existingNameOnlySet = [];
        $needsNameOnly = (bool) count(array_filter($csvRows, fn ($r) => $r['email'] === ''));
        if ($needsNameOnly) {
            $rows = $this->event->participants()->whereNull('email')->get(['first_name', 'last_name']);
            foreach ($rows as $p) {
                $k = mb_strtolower(trim((string) $p->first_name)).'|'.mb_strtolower(trim((string) $p->last_name));
                $existingNameOnlySet[$k] = true;
            }
        }

        // Count only NEW rows
        $billable = 0;
        foreach ($csvRows as $r) {
            if ($r['email'] !== '') {
                $exists = isset($existingEmailSet[mb_strtolower(trim($r['email']))]);
            } else {
                $k = mb_strtolower(trim($r['first_name'])).'|'.mb_strtolower(trim($r['last_name']));
                $exists = isset($existingNameOnlySet[$k]);
            }
            if (! $exists) {
                $billable++;
            }
        }

        if ($billable === 0) {
            Storage::delete($storedPath);
            $this->dispatch('toast', type: 'info', message: 'No new participants detected — nothing to charge.');
            $this->showCsvSheet = false;
            $this->csv = null;

            return;
        }

        // Pricing via company tier (EVENT)
        $company = $this->event->company ?? null;
        $unit = \App\Services\PricingService::participantFeeCents($company, 'event');
        $currency = \App\Services\PricingService::currency($company);

        $apply = $this->applyLineTimeId;
        $apply = (is_numeric($apply) && (int) $apply > 0) ? (int) $apply : null;

        // Stage the import — stash selected line time in meta
        $import = \App\Models\ParticipantImport::create([
            'event_id' => $this->event->id,
            'user_id' => auth()->id(),
            'file_path' => $storedPath,
            'original_name' => $this->csv->getClientOriginalName(),
            'row_count' => $billable,
            'unit_price_cents' => $unit,
            'amount_cents' => $billable * $unit,
            'currency' => $currency,
            'status' => 'pending_payment',
            'meta' => ['apply_line_time_id' => $apply],
        ]);

        // Close drawer & redirect to confirm/pay
        $this->showCsvSheet = false;
        $this->csv = null;

        return redirect()->route('corporate.events.participants.import.confirm', [
            'event' => $this->event->id,
            'import' => $import->id,
        ]);
    }

    /** Header mapping for new CSV shape */
    private function mapHeaders(?array $headers): array
    {
        if (! $headers) {
            return [
                0 => 'first_name', 1 => 'last_name', 2 => 'email', 3 => 'division_name', 4 => 'bow_type',
                5 => 'is_para', 6 => 'uses_wheelchair', 7 => 'notes',
            ];
        }

        $aliases = [
            'first_name' => ['first_name', 'first name', 'first', 'firstname', 'given', 'given_name', 'given name'],
            'last_name' => ['last_name', 'last name', 'last', 'lastname', 'surname', 'family', 'family_name', 'family name'],
            'email' => ['email', 'e-mail', 'mail'],
            'division_name' => ['division_name', 'division', 'division name'],
            'bow_type' => ['bow_type', 'bow type', 'bow'],
            'is_para' => ['is_para', 'para', 'is para', 'disabled'],
            'uses_wheelchair' => ['uses_wheelchair', 'wheelchair', 'uses wheelchair'],
            'notes' => ['notes', 'note', 'comments', 'comment'],
        ];

        $map = [];
        foreach ($headers as $i => $h) {
            $k = mb_strtolower(trim((string) $h));
            $k = str_replace(['-', ' '], '_', $k);
            foreach ($aliases as $target => $list) {
                if ($k === $target || in_array($k, $list, true)) {
                    $map[$i] = $target;

                    continue 2;
                }
            }
        }

        // Ensure all expected keys (fallback positional)
        $needs = ['first_name', 'last_name', 'email', 'division_name', 'bow_type', 'is_para', 'uses_wheelchair', 'notes'];
        foreach ($needs as $idx => $key) {
            if (! in_array($key, $map, true)) {
                $map[$idx] = $key;
            }
        }
        ksort($map);

        return $map;
    }

    /** Canonical billing key */
    private function canonicalKey(string $first, string $last, string $email): string
    {
        $e = mb_strtolower(trim($email));
        if ($e !== '') {
            return 'email:'.$e;
        }
        $f = mb_strtolower(trim($first));
        $l = mb_strtolower(trim($last));

        return 'name:'.$f.'|'.$l;
    }

    private function toBool(string $v): bool
    {
        $v = mb_strtolower(trim($v));
        if ($v === '' || $v === '0' || $v === 'no' || $v === 'false' || $v === 'n') {
            return false;
        }

        return (bool) filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? in_array($v, ['y', 'yes', '1', 'true'], true);
    }

    /** Normalize HH:MM to HH:MM:SS */
    private function normalizeTimeSeconds(?string $t): ?string
    {
        $t = trim((string) $t);
        if ($t === '') {
            return null;
        }
        // HH:MM
        if (preg_match('/^\d{1,2}:\d{2}$/', $t)) {
            return $t.':00';
        }
        // HH:MM:SS
        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $t)) {
            return $t;
        }
        // Fallback: let Carbon try (rare)
        try {
            return \Carbon\Carbon::parse($t)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /** Extract YYYY-MM-DD from a DATE or ISO-8601 string (e.g., 2025-11-10T05:00:00.000000Z) */
    private function extractDateYmd($dateVal): ?string
    {
        $raw = trim((string) ($dateVal ?? ''));
        if ($raw === '') {
            return null;
        }

        // Fast path: take first 10 chars if they look like YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw, $m)) {
            return $m[0];
        }
        // Fallback to Carbon for odd cases
        try {
            return \Carbon\Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Final label builder: "Line 11/10/2025 12:00PM → 3:00PM" (end time optional) */
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

        return 'Line '.$lt->id; // ultimate fallback if something is truly off
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
                    Manage event participants (search, filter, and import).
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
                                <flux:menu.item href="{{ route('corporate.events.participants.template', $event) }}" icon="table-cells">
                                    Download CSV template
                                </flux:menu.item>
                            @endunless
                            <flux:menu.item href="{{ route('corporate.events.participants.export', $event) }}" icon="users">
                                Export participants
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
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="col-span-2">
                <label class="block text-xs text-muted-foreground mb-1">Search</label>
                <flux:input icon="magnifying-glass" placeholder="Name, email, division, bow, notes"
                            wire:model.live.debounce.300ms="search" />
            </div>

            <div>
                <label class="block text-xs text-muted-foreground mb-1">Division</label>
                <flux:select wire:model.live="filterDivision" placeholder="All">
                    <option value="">All</option>
                    @foreach($divisionOptions as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <label class="block text-xs text-muted-foreground mb-1">Bow Type</label>
                <flux:select wire:model.live="filterBowType" placeholder="All">
                    <option value="">All</option>
                    @foreach($bowTypeOptions as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <label class="block text-xs text-muted-foreground mb-1">Line Time</label>
                <flux:select wire:model.live="filterLineTime" placeholder="All">
                    <option value="">All</option>
                    @foreach($lineTimeOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </flux:select>
            </div>


            <div class="flex items-center gap-2">
                <input id="para" type="checkbox" wire:model.live="filterParaOnly" class="rounded border-border/60">
                <label for="para" class="text-sm">Para only</label>
            </div>

            <div class="flex items-center gap-2">
                <input id="wc" type="checkbox" wire:model.live="filterWheelchairOnly" class="rounded border-border/60">
                <label for="wc" class="text-sm">Wheelchair only</label>
            </div>
        </div>

        {{-- Table --}}
        <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
            <table class="min-w-full text-left">
                <thead class="bg-white dark:bg-gray-900 text-xs uppercase tracking-wide">
                    <tr>
                        @php
                            $th = function($label, $col) {
                                $is = $this->sort === $col;
                                $dir = $is ? ($this->direction === 'asc' ? '▲' : '▼') : '';
                                return '<button type="button" wire:click="sortBy(\''.$col.'\')" class="px-3 py-2 text-left w-full">'.$label.' <span class="opacity-60">'.$dir.'</span></button>';
                            };
                        @endphp
                        <th class="whitespace-nowrap">{!! $th('Name','last_name') !!}</th>
                        <th class="whitespace-nowrap">{!! $th('Email','email') !!}</th>
                        <th class="whitespace-nowrap">{!! $th('Division','division_name') !!}</th>
                        <th class="whitespace-nowrap">{!! $th('Bow','bow_type') !!}</th>
                        <th class="whitespace-nowrap">{!! $th('Line Time','line_time_id') !!}</th>
                        <th class="whitespace-nowrap">{!! $th('Lane','assigned_lane') !!}</th>
                        <th class="whitespace-nowrap">{!! $th('Slot','assigned_slot') !!}</th>
                        <th class="whitespace-nowrap text-center">Para</th>
                        <th class="whitespace-nowrap text-center">Wheelchair</th>
                        <th class="whitespace-nowrap">{!! $th('Notes','created_at') !!}</th>
                        <th class="whitespace-nowrap">{!! $th('Added','created_at') !!}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10 text-xs">
                    @forelse($this->participants as $p)
                        <tr class="hover:bg-muted/30">
                            <td class="px-3 py-2 whitespace-nowrap">
                                {{ $p->last_name }}, {{ $p->first_name }}
                            </td>
                            <td class="px-3 py-2">{{ $p->email ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $p->division_name ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $p->bow_type ?? '—' }}</td>
                            <td class="px-3 py-2">
                                @php
                                    $lt = $p->line_time_id;
                                    $label = $lt ? ($lineTimeOptions[$lt] ?? ('#'.$lt)) : '—';
                                @endphp
                                {{ $label }}
                            </td>
                            <td class="px-3 py-2 text-center">{{ $p->assigned_lane ?? '—' }}</td>
                            <td class="px-3 py-2 text-center">{{ $p->assigned_slot ?? '—' }}</td>
                            <td class="px-3 py-2 text-center">
                                @if($p->is_para)
                                    <span class="inline-flex items-center rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">Yes</span>
                                @else
                                    <span class="text-xs opacity-60">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if($p->uses_wheelchair)
                                    <span class="inline-flex items-center rounded bg-blue-100 px-2 py-0.5 text-xs text-blue-800 dark:bg-blue-900/30 dark:text-blue-200">Yes</span>
                                @else
                                    <span class="text-xs opacity-60">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 max-w-[18rem] truncate" title="{{ $p->notes }}">{{ $p->notes }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ optional($p->created_at)->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-8 px-4 text-sm text-gray-500 dark:text-gray-400">
                                No participants yet. Upload a CSV to get started.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Pager --}}
            @php($p = $this->participants)
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
                                    <span aria-current="page" class="relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white dark:bg-indigo-500">{{ $i }}</span>
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
        </div>

        {{-- CSV Sheet --}}
        @if($showCsvSheet)
            <div class="fixed inset-0 z-40">
                <div class="absolute inset-0 bg-black/40" wire:click="$set('showCsvSheet', false)"></div>

                <div class="absolute inset-y-0 right-0 w-full max-w-2xl h-full overflow-y-auto bg-white p-6 shadow-xl dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Upload participants CSV</h2>
                        <button class="rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg:white/10"
                                wire:click="$set('showCsvSheet', false)">✕</button>
                    </div>

                    <form wire:submit.prevent="stageImportCsv" class="mt-6 space-y-6">
                        <div>
                            <flux:label for="csv">CSV file</flux:label>
                            <input id="csv" type="file" wire:model="csv" accept=".csv,text/csv" class="mt-1 block w-full text-sm" />
                            @error('csv') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                            <p class="mt-2 text-xs opacity-70">
                                Expected headers:
                                <code>first_name,last_name,email,division_name,bow_type,is_para,uses_wheelchair,notes</code>
                            </p>
                        </div>

                        <div class="space-y-2">
                            <flux:label>Apply this upload to the line</flux:label>
                            <flux:select
                                wire:model.number="applyLineTimeId"
                                class="w-full"
                            >
                                {{-- Optional "no preference" — remove this <option> if you want to force a selection --}}
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
                            <flux:button type="button" variant="ghost" wire:click="$set('showCsvSheet', false)">Cancel</flux:button>
                            <flux:button type="submit" variant="primary">Upload & Review</flux:button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</section>
