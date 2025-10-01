<?php
use App\Models\League;
use App\Models\LeagueCheckin;
use App\Models\LeagueParticipant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads, WithPagination;

    public League $league;

    // page/pagination
    protected string $pageName = 'participantsPage';

    public int $perPage = 10;

    // search participants
    public string $search = '';

    // CSV import sheet
    public bool $showCsvSheet = false;

    public $csv; // temporary uploaded file

    public string $checkinUrl = '';    // public check-in URL for QR/link

    // --- lifecycle ---
    public function mount(League $league): void
    {
        Gate::authorize('view', $league);

        // Weeks in order
        $this->league = $league->load(['weeks' => fn ($q) => $q->orderBy('week_number')]);

        // Public check-in URL (the participants picker page)
        $this->checkinUrl = route('public.checkin.participants', ['uuid' => $this->league->public_uuid]);
    }

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

    // computed: filtered/paginated participants
    public function getParticipantsProperty()
    {
        $base = $this->league->participants()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('first_name', 'like', "%{$this->search}%")
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

    public function getCheckinsByWeekProperty(): array
    {
        return LeagueCheckin::query()
            ->where('league_id', $this->league->id)
            ->selectRaw('week_number, COUNT(*) as c')
            ->groupBy('week_number')
            ->pluck('c', 'week_number')
            ->toArray();
    }

    // pager window like Loadouts
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

    public function openCsv(): void
    {
        Gate::authorize('update', $this->league);
        $this->csv = null;
        $this->showCsvSheet = true;
    }

    public function importCsv(): void
    {
        Gate::authorize('update', $this->league);

        $this->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'], // 10 MB
        ]);

        $path = $this->csv->store('tmp');
        $full = Storage::path($path);

        $handle = fopen($full, 'r');
        if (! $handle) {
            $this->addError('csv', 'Unable to read uploaded file.');

            return;
        }

        // read header row
        $headers = fgetcsv($handle);
        $map = $this->normalizeHeaders($headers); // index => key

        $created = 0;
        $updated = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = $this->rowToAssoc($map, $row);
            $first = trim((string) ($data['first_name'] ?? ''));
            $last = trim((string) ($data['last_name'] ?? ''));
            $email = trim((string) ($data['email'] ?? ''));

            if ($first === '' && $last === '') {
                $skipped++;

                continue;
            }

            // For closed leagues, membership is required
            $userId = null;
            if ($email !== '') {
                $userId = optional(\App\Models\User::where('email', $email)->first())->id;
                if ($this->league->type->value === 'closed' && ! $userId) {
                    $skipped++; // not a member -> skip

                    continue;
                }
            } elseif ($this->league->type->value === 'closed') {
                $skipped++; // closed + no email = skip

                continue;
            }

            // Dedup by email if provided, else (first,last) tuple without email
            $existing = null;
            if ($email !== '') {
                $existing = LeagueParticipant::where('league_id', $this->league->id)
                    ->where('email', $email)->first();
            } else {
                $existing = LeagueParticipant::where('league_id', $this->league->id)
                    ->whereNull('email')
                    ->where('first_name', $first)
                    ->where('last_name', $last)
                    ->first();
            }

            if ($existing) {
                $existing->update([
                    'first_name' => $first,
                    'last_name' => $last,
                    'user_id' => $userId,
                ]);
                $updated++;
            } else {
                LeagueParticipant::create([
                    'league_id' => $this->league->id,
                    'user_id' => $userId,
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $email !== '' ? $email : null,
                ]);
                $created++;
            }
        }

        fclose($handle);
        @unlink($full);

        // refresh participants relation for the view
        $this->league->load('participants');

        $this->showCsvSheet = false;
        $this->csv = null;

        $this->dispatch('toast', type: 'success',
            message: "Import complete — created {$created}, updated {$updated}, skipped {$skipped}."
        );
    }

    private function normalizeHeaders(?array $headers): array
    {
        $map = [];
        if (! $headers) {
            return $map;
        }
        foreach ($headers as $i => $h) {
            $k = strtolower(trim((string) $h));
            $k = str_replace([' ', '-'], '_', $k);
            $map[$i] = $k; // expected: first_name,last_name,email
        }

        return $map;
    }

    private function rowToAssoc(array $map, array $row): array
    {
        $out = [];
        foreach ($map as $i => $key) {
            $out[$key] = $row[$i] ?? null;
        }

        return $out;
    }
};
?>

<section class="w-full">
    @php
        // Hide kiosk buttons unless league is tablet mode.
        $mode = $league->scoring_mode->value ?? $league->scoring_mode;
        $isTabletMode = ($mode === 'tablet');
    @endphp
    {{-- Header --}}
    <div class="mx-auto max-w-7xl">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ $league->title }}
                </h1>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    {{ $league->location ?: '—' }} •
                    {{ ucfirst($league->type->value) }} •
                    Starts {{ optional($league->start_date)->format('Y-m-d') ?: '—' }} •
                    {{ $league->length_weeks }} weeks
                </p>
            </div>

            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <div class="flex items-center gap-2">
                    {{-- Kiosk sessions --}}
                    @if($isTabletMode)
                        <flux:button as="a" href="{{ route('corporate.manager.kiosks.index', $league) }}" variant="primary" color="emerald" icon="computer-desktop">
                            Kiosk sessions
                        </flux:button>
                    @endif
                    {{-- Actions dropdown --}}
                    <flux:dropdown>
                        <flux:button icon:trailing="chevron-down">Actions</flux:button>

                        <flux:menu class="min-w-64">
                            <flux:menu.item href="{{ route('corporate.leagues.info.edit', $league) }}" icon="pencil-square">
                                Create/Update league info
                            </flux:menu.item>

                            <flux:menu.item href="{{ route('public.league.info', ['uuid' => $league->public_uuid]) }}" target="_blank" icon="arrow-top-right-on-square">
                                View public page
                            </flux:menu.item>
                            
                            <flux:menu.item href="{{ route('corporate.leagues.scoring_sheet', $league) }}" icon="document-arrow-down">
                                Download scoring sheet (PDF)
                            </flux:menu.item>

                            <flux:menu.item href="{{ route('corporate.leagues.participants.template', $league) }}" icon="table-cells">
                                Download CSV template
                            </flux:menu.item>

                            <flux:menu.item href="{{ route('corporate.leagues.participants.export', $league) }}" icon="users">
                                Export participants
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>

                    {{-- Upload CSV --}}
        
                    <flux:button wire:click="openCsv" variant="primary" color="indigo" icon="arrow-up-tray">
                        Upload CSV
                    </flux:button>
                </div>
            </div>
        </div>

        {{-- Public check-in URL + QR --}}
        <div class="mt-6 grid gap-4 md:grid-cols-[1fr_auto]">
            <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                <div class="text-sm font-medium text-gray-900 dark:text-white">Public check-in link</div>
                <div class="mt-2 flex items-center gap-2">
                    <input type="text"
                           readonly
                           value="{{ $checkinUrl }}"
                           class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-xs
                                  focus:border-indigo-500 focus:ring-2 focus:ring-indigo-600 dark:border-white/10 dark:bg-white/5
                                  dark:text-gray-200 dark:focus:border-indigo-400 dark:focus:ring-indigo-400" />
                    <a href="{{ $checkinUrl }}"
                       target="_blank"
                       class="rounded-md bg-white px-3 py-2 text-sm font-medium inset-ring inset-ring-gray-300 hover:bg-gray-50
                              dark:bg-white/5 dark:text-gray-200 dark:inset-ring-white/10 dark:hover:bg-white/10">
                        Open
                    </a>
                </div>
                <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                    Share this link or the QR code with archers to check in on their phone.
                </p>
            </div>

            <div class="flex items-center justify-center rounded-lg border border-gray-200 p-3 dark:border-white/10">
                {{-- Zero-dep QR (uses a public QR image service). Replace with your in-app QR generator if preferred. --}}
                <img
                    alt="Check-in QR"
                    class="h-36 w-36"
                    src="{{ 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($checkinUrl) }}"
                />
            </div>
        </div>
    </div>

    {{-- Schedule --}}
    <div class="mt-6">
        <div class="mx-auto max-w-7xl">
            <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
                <table class="w-full text-left">
                    <thead class="bg-white dark:bg-gray-900">
                        <tr>
                            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Week</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Date</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Day</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Checked in</th>
                            <th class="py-3.5 pl-3 pr-4"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse($league->weeks as $w)
                            <tr>
                                <td class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $w->week_number }}
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($w->date)->format('Y-m-d') }}
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($w->date)->format('l') }}
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $this->checkinsByWeek[$w->week_number] ?? 0 }}
                                </td>
                                <td class="py-4 pl-3 pr-4 text-right text-sm font-medium">
                                    <flux:button
                                    as="a"
                                    href="{{ route('corporate.leagues.weeks.live', [$league, $w]) }}?kiosk=1"
                                    target="_blank"
                                    size="sm"
                                    variant="primary"
                                    color="blue"
                                    icon="presentation-chart-bar"
                                    >
                                    Live scoring (Kiosk)
                                    </flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 px-4 text-sm text-gray-500 dark:text-gray-400">
                                    No weeks scheduled. Edit league to regenerate weeks.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Participants --}}
    <div class="mt-8">
        <div class="mx-auto max-w-7xl">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Participants</h2>
                <div class="w-full max-w-sm">
                    <flux:input icon="magnifying-glass" placeholder="Search participants…" wire:model.live.debounce.300ms="search" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
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

                {{-- Pagination footer (same UX as Loadouts) --}}
                @php($p = $this->participants)
                @php($w = $this->pageWindow)
                <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 dark:border-white/10 dark:bg-transparent">
                    <!-- Mobile Prev/Next -->
                    <div class="flex flex-1 justify-between sm:hidden">
                        <button wire:click="prevPage" @disabled($p->onFirstPage()) class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10">Previous</button>
                        <button wire:click="nextPage" @disabled(!$p->hasMorePages()) class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10">Next</button>
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
                                <button wire:click="prevPage" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:inset-ring-gray-700 dark:hover:bg-white/5" @disabled($p->onFirstPage())>
                                    <span class="sr-only">Previous</span>
                                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5"><path d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" fill-rule="evenodd" /></svg>
                                </button>

                                @for ($i = $w['start']; $i <= $w['end']; $i++)
                                    @if ($i === $w['current'])
                                        <span aria-current="page" class="relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:focus-visible:outline-indigo-500">{{ $i }}</span>
                                    @else
                                        <button wire:click="goto({{ $i }})" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:text-gray-200 dark:inset-ring-gray-700 dark:hover:bg-white/5">{{ $i }}</button>
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

    {{-- Right "sheet" for CSV import --}}
    @if($showCsvSheet)
        <div class="fixed inset-0 z-40">
            <div class="absolute inset-0 bg-black/40" wire:click="$set('showCsvSheet', false)"></div>

            <div class="absolute inset-y-0 right-0 w-full max-w-2xl h-full overflow-y-auto bg-white p-6 shadow-xl dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Upload participants CSV</h2>
                    <button class="rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10"
                            wire:click="$set('showCsvSheet', false)">✕</button>
                </div>

                <form wire:submit.prevent="importCsv" class="mt-6 space-y-6">
                    <div>
                        <flux:label for="csv">CSV file</flux:label>
                        <input id="csv" type="file" wire:model="csv" accept=".csv,text/csv" class="mt-1 block w-full text-sm" />
                        @error('csv') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                        <p class="mt-2 text-xs opacity-70">Expected headers: <code>first_name,last_name,email</code></p>
                    </div>

                    <div class="flex justify-end gap-3">
                        <flux:button type="button" variant="ghost" wire:click="$set('showCsvSheet', false)">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">Import</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</section>
