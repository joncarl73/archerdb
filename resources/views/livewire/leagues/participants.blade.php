<?php
use App\Models\League;
use App\Models\ParticipantImport;
use App\Services\PricingService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads, WithPagination;

    public League $league;

    // pagination
    protected string $pageName = 'participantsPage';

    public int $perPage = 10;

    // search
    public string $search = '';

    // CSV
    public bool $showCsvSheet = false;

    public $csv;

    public function mount(League $league): void
    {
        Gate::authorize('view', $league);
        $this->league = $league;
    }

    // pagination helpers
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

    /** Paged participants list */
    public function getParticipantsProperty()
    {
        $base = $this->league->participants()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$this->search}%")
                ->orWhere('last_name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
            ))
            ->orderBy('last_name')
            ->orderBy('first_name');

        $total = (clone $base)->count();
        $lastPage = max(1, (int) ceil($total / $this->perPage));

        $requested = (int) ($this->paginators[$this->pageName] ?? 1);
        $page = min(max(1, $requested), $lastPage);
        if ($requested !== $page) {
            $this->setPage($page, $this->pageName);
        }

        return $base->paginate($this->perPage, ['*'], $this->pageName, $page);
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

    /** Open the upload drawer (disallowed for closed leagues) */
    public function openCsv(): void
    {
        Gate::authorize('update', $this->league);
        $typeVal = ($this->league->type->value ?? $this->league->type);
        if ($typeVal === 'closed') {
            $this->dispatch('toast', type: 'warning', message: 'CSV import is disabled for closed leagues.');

            return;
        }
        $this->csv = null;
        $this->showCsvSheet = true;
    }

    /**
     * Stage the CSV for paywalled import using company pricing:
     * - store privately
     * - count ONLY new participants (dedup + existing filtered)
     * - determine unit price via PricingService (league context)
     * - create ParticipantImport with unit & total
     * - redirect to confirm & pay page
     */
    public function stageImportCsv()
    {
        Gate::authorize('update', $this->league);

        $typeVal = ($this->league->type->value ?? $this->league->type);
        if ($typeVal === 'closed') {
            $this->dispatch('toast', type: 'warning', message: 'CSV import is disabled for closed leagues.');

            return;
        }

        $this->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        // Store privately with original name + timestamp
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

        // --- Parse headers and rows
        $headers = fgetcsv($stream) ?: [];
        $map = $this->normalizeHeaders($headers); // expects first_name,last_name,email

        // Collect CSV rows (dedup within the same CSV by email or name pair when email missing)
        $csvKeys = [];   // set of canonical keys found in this CSV
        $csvRows = [];   // [{first,last,email,key}]
        while (($row = fgetcsv($stream)) !== false) {
            $joined = implode('', array_map(fn ($v) => trim((string) $v), $row));
            if ($joined === '') {
                continue; // skip blank lines
            }

            // map row -> assoc
            $assoc = [];
            foreach ($map as $i => $key) {
                $assoc[$key] = trim((string) ($row[$i] ?? ''));
            }

            $first = (string) ($assoc['first_name'] ?? '');
            $last = (string) ($assoc['last_name'] ?? '');
            $email = (string) ($assoc['email'] ?? '');

            // ignore rows with no data in all three key fields
            if ($first === '' && $last === '' && $email === '') {
                continue;
            }

            $key = $this->canonicalParticipantKey($first, $last, $email);
            if (isset($csvKeys[$key])) {
                continue; // de-duplicate within this CSV
            }
            $csvKeys[$key] = true;
            $csvRows[] = compact('first', 'last', 'email', 'key');
        }

        if (is_resource($stream)) {
            fclose($stream);
        }

        if (count($csvRows) === 0) {
            Storage::delete($storedPath);
            $this->addError('csv', 'No participant rows detected. Please check your CSV headers and content.');

            return;
        }

        // --- Build "existing" sets from DB for fast membership checks
        // 1) emails present in CSV
        $emails = array_values(array_filter(array_map(fn ($r) => $r['email'] ?: null, $csvRows)));
        $emails = array_values(array_unique($emails));

        // 2) name-only rows (no email)
        $nameOnlyPairs = array_values(array_unique(array_map(
            fn ($r) => $r['email'] === '' ? mb_strtolower(trim($r['first'])).'|'.mb_strtolower(trim($r['last'])) : null,
            $csvRows
        )));
        $nameOnlyPairs = array_values(array_filter($nameOnlyPairs));

        $existingEmailSet = [];
        if (! empty($emails)) {
            $existingEmails = $this->league->participants()
                ->whereIn('email', $emails)
                ->pluck('email')
                ->all();
            foreach ($existingEmails as $e) {
                if ($e !== null && $e !== '') {
                    $existingEmailSet[mb_strtolower(trim($e))] = true;
                }
            }
        }

        $existingNameOnlySet = [];
        if (! empty($nameOnlyPairs)) {
            $nullEmailParticipants = $this->league->participants()
                ->whereNull('email')
                ->get(['first_name', 'last_name']);

            foreach ($nullEmailParticipants as $p) {
                $k = mb_strtolower(trim((string) $p->first_name)).'|'.mb_strtolower(trim((string) $p->last_name));
                $existingNameOnlySet[$k] = true;
            }
        }

        // --- Count only NEW/billable rows
        $billable = 0;
        foreach ($csvRows as $r) {
            if ($r['email'] !== '') {
                $exists = isset($existingEmailSet[mb_strtolower(trim($r['email']))]);
            } else {
                $k = mb_strtolower(trim($r['first'])).'|'.mb_strtolower(trim($r['last']));
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

        // ✅ Pricing from company tier (single source of truth)
        $company = $this->league->company ?? null;
        $unit = PricingService::participantFeeCents($company, 'league'); // cents
        $currency = PricingService::currency($company);

        // Create staged import with company-tiered price (unit × billable)
        $import = ParticipantImport::create([
            'league_id' => $this->league->id,
            'user_id' => auth()->id(),
            'file_path' => $storedPath,
            'original_name' => $this->csv->getClientOriginalName(),
            'row_count' => $billable,             // only new participants are billable
            'unit_price_cents' => $unit,                 // per company tier
            'amount_cents' => $billable * $unit,     // derived total
            'currency' => $currency,
            'status' => 'pending_payment',
        ]);

        // Close drawer and clear file
        $this->showCsvSheet = false;
        $this->csv = null;

        // Off you go—confirm & pay!
        return redirect()->route('corporate.leagues.participants.import.confirm', [
            'league' => $this->league->id,
            'import' => $import->id,
        ]);
    }

    /** Map CSV indexes → expected keys: first_name,last_name,email */
    private function normalizeHeaders(?array $headers): array
    {
        $map = [];
        if (! $headers) {
            // Assume positional if no header row
            return [0 => 'first_name', 1 => 'last_name', 2 => 'email'];
        }

        $expected = [
            'first_name' => ['first_name', 'first name', 'first', 'firstname', 'given', 'given_name', 'given name'],
            'last_name' => ['last_name', 'last name', 'last', 'lastname', 'surname', 'family', 'family_name', 'family name'],
            'email' => ['email', 'e-mail', 'mail'],
        ];

        foreach ($headers as $i => $h) {
            $k = mb_strtolower(trim((string) $h));
            $k = str_replace(['-', ' '], '_', $k);
            foreach ($expected as $target => $aliases) {
                if ($k === $target || in_array($k, $aliases, true)) {
                    $map[$i] = $target;

                    continue 2;
                }
            }
            // ignore unknown columns
        }

        // Ensure all three keys have a mapping; fallback by position if needed
        if (! in_array('first_name', $map, true)) {
            $map[0] = 'first_name';
        }
        if (! in_array('last_name', $map, true)) {
            $map[1] = 'last_name';
        }
        if (! in_array('email', $map, true)) {
            $map[2] = 'email';
        }

        ksort($map);

        return $map;
    }

    /** Canonical key: email (if present) else "first|last" */
    private function canonicalParticipantKey(string $first, string $last, string $email): string
    {
        $e = mb_strtolower(trim($email));
        if ($e !== '') {
            return 'email:'.$e;
        }
        $f = mb_strtolower(trim($first));
        $l = mb_strtolower(trim($last));

        return 'name:'.$f.'|'.$l;
    }
};
?>

<section class="w-full">
    @php
        $typeVal  = ($league->type->value ?? $league->type);
        $isClosed = ($typeVal === 'closed');
    @endphp

    <div class="mx-auto max-w-7xl">
        {{-- Header --}}
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ $league->title }} — Participants
                </h1>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    Manage league participants.
                </p>
            </div>
            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <div class="flex items-center gap-2">
                    <flux:button as="a" href="{{ route('corporate.leagues.show', $league) }}" variant="ghost">
                        ← Back to league
                    </flux:button>

                    <flux:dropdown>
                        <flux:button icon:trailing="chevron-down">Actions</flux:button>
                        <flux:menu class="min-w-64">
                            @unless($isClosed)
                                <flux:menu.item href="{{ route('corporate.leagues.participants.template', $league) }}" icon="table-cells">
                                    Download CSV template
                                </flux:menu.item>
                            @endunless
                            <flux:menu.item href="{{ route('corporate.leagues.participants.export', $league) }}" icon="users">
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

        {{-- Search --}}
        <div class="mt-6 max-w-sm">
            <flux:input icon="magnifying-glass" placeholder="Search participants…" wire:model.live.debounce.300ms="search" />
        </div>

        {{-- Table --}}
        <div class="mt-4 overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
            <table class="w-full text-left">
                <thead class="bg-white dark:bg-gray-900">
                    <tr>
                        <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Name</th>
                        <th class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 sm:table-cell dark:text-white">Email</th>
                        <th class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 md:table-cell dark:text-white">Member</th>
                        <th class="py-3.5 pl-3 pr-4"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse($this->participants as $p)
                        <tr>
                            <td class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white">
                                {{ $p->last_name }}, {{ $p->first_name }}
                            </td>
                            <td class="hidden px-3 py-4 text-sm text-gray-500 sm:table-cell dark:text-gray-400">
                                {{ $p->email ?? '—' }}
                            </td>
                            <td class="hidden px-3 py-4 text-sm text-gray-500 md:table-cell dark:text-gray-400">
                                {{ $p->user_id ? 'Yes' : 'No' }}
                            </td>
                            <td class="py-4 pl-3 pr-4 text-right text-sm font-medium">
                                <span class="text-xs opacity-60">—</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 px-4 text-sm text-gray-500 dark:text-gray-400">
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
        </div>

        {{-- CSV sheet --}}
        @if($showCsvSheet)
            <div class="fixed inset-0 z-40">
                <div class="absolute inset-0 bg-black/40" wire:click="$set('showCsvSheet', false)"></div>

                <div class="absolute inset-y-0 right-0 w-full max-w-2xl h-full overflow-y-auto bg-white p-6 shadow-xl dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Upload participants CSV</h2>
                        <button class="rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10"
                                wire:click="$set('showCsvSheet', false)">✕</button>
                    </div>

                    <form wire:submit.prevent="stageImportCsv" class="mt-6 space-y-6">
                        <div>
                            <flux:label for="csv">CSV file</flux:label>
                            <input id="csv" type="file" wire:model="csv" accept=".csv,text/csv" class="mt-1 block w-full text-sm" />
                            @error('csv') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                            <p class="mt-2 text-xs opacity-70">Expected headers: <code>first_name,last_name,email</code></p>
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
