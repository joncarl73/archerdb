<?php
use App\Models\KioskSession;
use App\Models\League;
use App\Models\LeagueCheckin;
use App\Models\LeagueWeek;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component
{
    public League $league;

    // form state
    public int $week_number = 1;

    public array $participantIds = [];

    // derived for UI
    public array $participantsForWeek = []; // available (checked-in but not assigned) archers

    public $weeks;

    public $sessions;              // filtered list shown in the table

    public ?string $createdToken = null;

    public bool $showAll = false;

    // QOL: collapse create panel
    public bool $createOpen = true;

    public function toggleCreateOpen(): void
    {
        $this->createOpen = ! $this->createOpen;
    }

    // Server-rendered QR modal
    public bool $showQr = false;

    public ?string $qrUrl = null;

    public ?int $event_line_time_id = null;

    public function openQr(string $token): void
    {
        $this->qrUrl = url('/k/'.$token);
        $this->showQr = true;
    }

    public function closeQr(): void
    {
        $this->showQr = false;
        $this->qrUrl = null;
    }

    public function mount(League $league): void
    {
        Gate::authorize('update', $league);

        $this->league = $league->load(['weeks' => fn ($q) => $q->orderBy('week_number')]);

        $this->weeks = $this->league->weeks->map(fn ($w) => [
            'week_number' => (int) $w->week_number,
            'date' => $w->date,
        ]);

        $this->week_number = (int) ($this->weeks[0]['week_number'] ?? 1);

        $this->refreshParticipantsForWeek();
        $this->refreshSessions();
    }

    protected function normalizeParticipants($val): array
    {
        if (is_array($val)) {
            return array_values(array_unique(array_map('intval', $val)));
        }
        $arr = json_decode((string) $val, true) ?: [];

        return array_values(array_unique(array_map('intval', $arr)));
    }

    protected function assignedParticipantIdsForWeek(): array
    {
        $sessions = KioskSession::query()
            ->where('league_id', $this->league->id)
            ->where('week_number', $this->week_number)
            ->get(['participants']);

        $ids = [];
        foreach ($sessions as $s) {
            $ids = array_merge($ids, $this->normalizeParticipants($s->participants));
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    protected function refreshParticipantsForWeek(): void
    {
        $alreadyAssigned = $this->assignedParticipantIdsForWeek();

        $checkins = LeagueCheckin::query()
            ->where('league_id', $this->league->id)
            ->where('week_number', $this->week_number)
            ->when(! empty($alreadyAssigned), fn ($q) => $q->whereNotIn('participant_id', $alreadyAssigned))
            ->orderBy('lane_number')->orderBy('lane_slot')
            ->get(['participant_id', 'participant_name', 'lane_number', 'lane_slot']);

        $this->participantsForWeek = $checkins->map(function ($c) {
            $lane = (string) ($c->lane_number ?? '');
            if ($lane !== '' && $c->lane_slot && $c->lane_slot !== 'single') {
                $lane .= $c->lane_slot;
            }

            return [
                'id' => (int) $c->participant_id,
                'name' => (string) ($c->participant_name ?: 'Unknown'),
                'lane' => $lane !== '' ? $lane : null,
            ];
        })->values()->all();

        $this->participantIds = [];
    }

    public function refreshSessions(): void
    {
        $base = KioskSession::where('league_id', $this->league->id)->latest();

        if (! $this->showAll) {
            $base->where('week_number', $this->week_number);
        }

        $this->sessions = $base->get();
    }

    public function updatedWeekNumber(): void
    {
        $event = $this->league->event ?? null;
        $exists = \App\Models\LeagueWeek::query()
            ->forContext($event, $this->league)
            ->where('week_number', $this->week_number)
            ->exists();

        if (! $exists) {
            $this->addError('week_number', 'Selected week does not exist for this league.');

            return;
        }

        $this->refreshParticipantsForWeek();
        $this->refreshSessions();
    }

    public function toggleShowAll(): void
    {
        $this->showAll = ! $this->showAll;
        $this->refreshSessions();
    }

    public function createSession(): void
    {
        Gate::authorize('update', $this->league);

        $this->validate([
            'week_number' => ['required', 'integer', 'between:1,'.$this->league->length_weeks],
            'participantIds' => ['required', 'array', 'min:1'],
            'participantIds.*' => ['integer'],
        ]);

        $exists = LeagueWeek::where('league_id', $this->league->id)
            ->where('week_number', $this->week_number)->exists();
        if (! $exists) {
            $this->addError('week_number', 'Selected week does not exist for this league.');

            return;
        }

        // race check: ensure not already assigned
        $dupes = array_intersect($this->assignedParticipantIdsForWeek(), array_map('intval', $this->participantIds));
        if (! empty($dupes)) {
            $this->addError('participantIds', 'One or more selected archers were already assigned. Please refresh.');
            $this->refreshParticipantsForWeek();

            return;
        }

        $session = KioskSession::create([
            'event_id' => optional($this->league->event)->id,           // NEW
            'event_line_time_id' => $this->event_line_time_id ?? null,            // NEW (nullable)
            'league_id' => $this->league->id,
            'week_number' => $this->week_number,
            'participants' => array_values(array_unique(array_map('intval', $this->participantIds))),
            'lanes' => array_values($this->lanes ?? []),             // keep legacy if you still support it
            'token' => Str::random(40),
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        $this->participantIds = [];
        $this->createdToken = $session->token;

        $this->refreshSessions();
        $this->refreshParticipantsForWeek();

        $this->dispatch('toast', type: 'success', message: 'Kiosk session created.');
    }

    /** Delete immediately (no modal), then re-expose those archers in the picker */
    public function deleteSession(int $id): void
    {
        Gate::authorize('update', $this->league);

        $s = KioskSession::where('league_id', $this->league->id)->findOrFail($id);
        $s->delete();

        $this->refreshSessions();
        $this->refreshParticipantsForWeek();

        // clear any flash URL banner for deleted session
        $this->createdToken = null;

        $this->dispatch('toast', type: 'success', message: 'Kiosk session deleted.');
    }

    protected function rules()
    {
        $hasEventLineTimes = (bool) optional($this->league->event)->lineTimes()->exists();

        return [
            'week_number' => ['required', 'integer', 'between:1,'.$this->league->length_weeks],
            'participantIds' => ['array'],
            'participantIds.*' => ['integer'],
            'lanes' => ['array'],
            'lanes.*' => ['string', 'max:10'],
            'event_line_time_id' => [$hasEventLineTimes ? 'required' : 'nullable', 'integer', 'exists:event_line_times,id'],
        ];
    }
};
?>

<section class="w-full">
    <div class="mx-auto max-w-7xl">
        {{-- Page header (stays at the top) --}}
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ $league->title }} — Kiosk Sessions
                </h1>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    Assign a tablet to specific archers (checked in for the selected week) and share the tokenized URL.
                </p>
            </div>
            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <a href="{{ route('corporate.leagues.show', $league) }}"
                   class="rounded-md bg-white px-3 py-2 text-sm font-medium inset-ring inset-ring-gray-300 hover:bg-gray-50
                          dark:bg-white/5 dark:text-gray-200 dark:inset-ring-white/10 dark:hover:bg-white/10">
                    Back to League
                </a>
            </div>
        </div>

        {{-- Flash URL for last created session --}}
        @if ($createdToken)
            @php($kioskUrl = url('/k/'.$createdToken))
            <div class="mt-4 rounded-xl border border-emerald-300/40 bg-emerald-50 p-4 text-emerald-900
                        dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="font-medium">Kiosk session created</div>
                        <div class="mt-1 text-sm">
                            Tablet URL:
                            <a class="underline hover:no-underline" href="{{ $kioskUrl }}" target="_blank" rel="noopener">
                                {{ $kioskUrl }}
                            </a>
                        </div>
                    </div>
                    <div class="shrink-0">
                        <button
                            type="button"
                            wire:click="openQr('{{ $createdToken }}')"
                            class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-xs font-medium text-emerald-700 inset-ring inset-ring-emerald-300/60 hover:bg-emerald-100
                                   dark:bg-white/5 dark:text-emerald-200 dark:inset-ring-emerald-500/30 dark:hover:bg-emerald-500/20">
                            Show QR
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Create session (collapsible) --}}
        <div
            x-data="{ open: @entangle('createOpen') }"
            class="mt-6 rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5"
        >
            <div class="flex items-start justify-between p-6">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Create kiosk session</h2>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        Pick a week, then select one or more archers who have checked in.
                    </p>
                </div>

                <button
                    type="button"
                    x-on:click="$wire.toggleCreateOpen()"
                    class="ml-4 inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1.5 text-xs font-medium inset-ring inset-ring-gray-300 hover:bg-gray-50
                           dark:bg-white/5 dark:text-gray-200 dark:inset-ring-white/10 dark:hover:bg-white/10"
                    aria-controls="create-kiosk-panel"
                    x-bind:aria-expanded="open ? 'true' : 'false'"
                >
                    <span x-show="open">Hide</span>
                    <span x-show="!open">Show</span>
                    <svg x-show="open" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 15l6-6 6 6"/></svg>
                    <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9l-6 6-6-6"/></svg>
                </button>
            </div>

            <div
                id="create-kiosk-panel"
                x-show="open"
                x-collapse
                x-cloak
                class="border-t border-gray-200 p-6 dark:border-white/10"
            >
                <form wire:submit.prevent="createSession" class="space-y-6">
                    {{-- Week --}}
                    <div>
                        <flux:label for="week_number">Week</flux:label>
                        <flux:select id="week_number" wire:model.live="week_number" class="mt-2 w-full">
                            @foreach ($weeks as $w)
                                <option value="{{ $w['week_number'] }}">
                                    Week {{ $w['week_number'] }} — {{ \Illuminate\Support\Carbon::parse($w['date'])->toFormattedDateString() }}
                                </option>
                            @endforeach
                        </flux:select>
                        @error('week_number') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Participant checkboxes (available only) --}}
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="block text-sm font-medium text-gray-800 dark:text-gray-200">Archers checked in</label>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Select one or more</div>
                        </div>

                        @if (count($participantsForWeek))
                            <div class="mt-2 grid gap-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                                @foreach ($participantsForWeek as $p)
                                    <label class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white p-2 text-sm
                                                   dark:border-white/10 dark:bg-white/5">
                                        <input type="checkbox"
                                               wire:model.live="participantIds"
                                               value="{{ (int) $p['id'] }}"
                                               class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600">
                                        <span class="text-gray-800 dark:text-gray-200">
                                            {{ $p['name'] }}
                                            @if($p['lane'])
                                                <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                                    Lane {{ $p['lane'] }}
                                                </span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                No available archers to assign for week {{ $week_number }}.
                            </p>
                        @endif

                        @error('participantIds') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        @error('participantIds.*') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit"
                                class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs
                                       hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600
                                       dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500"
                                @disabled(count($participantsForWeek) === 0)>
                            Create kiosk session
                        </button>
                        <p class="text-xs text-gray-500 dark:text-gray-400">A unique tokenized URL will be generated.</p>
                    </div>
                </form>
            </div>
        </div>

        {{-- Sessions table --}}
        <div class="mt-8">
            <div class="mb-2 flex items-center justify-between">
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    Showing
                    @if ($showAll)
                        <span class="font-medium">all</span>
                    @else
                        sessions for <span class="font-medium">week {{ $week_number }}</span>
                    @endif
                </div>
                <flux:button variant="ghost" size="sm" wire:click="toggleShowAll">
                    {{ $showAll ? 'Filter by week' : 'Show all' }}
                </flux:button>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 shadow-sm dark:border-white/10">
                <table class="w-full text-left">
                    <thead class="bg-white dark:bg-gray-900">
                        <tr>
                            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Created</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Week</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Assigned archers</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Status</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Tablet URL</th>
                            <th class="py-3.5 pl-3 pr-4 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10 bg-white dark:bg-transparent">
                        @forelse ($sessions as $s)
                            @php($url = url('/k/'.$s->token))
                            @php($ids = is_array($s->participants) ? $s->participants : (json_decode((string)$s->participants, true) ?: []))
                            @php($rows = \App\Models\LeagueParticipant::where('league_id', $league->id)->whereIn('id', $ids)->get(['id','first_name','last_name'])->keyBy('id'))
                            @php($checkins = \App\Models\LeagueCheckin::where('league_id', $league->id)->where('week_number', $s->week_number)->whereIn('participant_id', $ids)->get(['participant_id','lane_number','lane_slot'])->keyBy('participant_id'))
                            <tr>
                                <td class="py-3.5 pl-4 pr-3 text-sm text-gray-800 dark:text-gray-200">
                                    {{ $s->created_at?->format('Y-m-d H:i') ?? '—' }}
                                </td>
                                <td class="px-3 py-3.5 text-sm text-gray-800 dark:text-gray-200">
                                    Week {{ $s->week_number }}
                                </td>
                                <td class="px-3 py-3.5 text-sm text-gray-800 dark:text-gray-200">
                                    @if (!empty($ids))
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($ids as $pid)
                                                @php($p = $rows->get($pid))
                                                @php($nm = $p ? trim(($p->first_name ?? '').' '.($p->last_name ?? '')) : '#'.$pid)
                                                @php($c = $checkins->get($pid))
                                                @php($lane = null)
                                                @if($c)
                                                    @php($lane = (string) ($c->lane_number ?? ''))
                                                    @if($lane !== '' && $c->lane_slot && $c->lane_slot !== 'single')
                                                        @php($lane .= $c->lane_slot)
                                                    @endif
                                                    @php($lane = $lane !== '' ? $lane : null)
                                                @endif
                                                <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                                    {{ $nm }}@if($lane) — Lane {{ $lane }}@endif
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-3.5 text-sm">
                                    @if ($s->is_active)
                                        <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">Active</span>
                                    @else
                                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-300">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5 text-sm">
                                    <a href="{{ $url }}" class="truncate text-indigo-600 underline hover:no-underline dark:text-indigo-400" target="_blank" rel="noopener">
                                        {{ $url }}
                                    </a>
                                </td>
                                <td class="py-3.5 pl-3 pr-4 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <a href="{{ $url }}" target="_blank" rel="noopener"
                                           class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                                            Open
                                        </a>

                                        <button type="button"
                                                x-data="{copied:false}"
                                                @click="navigator.clipboard.writeText('{{ $url }}'); copied=true; setTimeout(()=>copied=false,1500)"
                                                class="rounded-md bg-white px-3 py-1.5 text-xs font-medium inset-ring inset-ring-gray-300 hover:bg-gray-50
                                                       dark:bg-white/5 dark:text-gray-200 dark:inset-ring-white/10 dark:hover:bg-white/10">
                                            <span x-show="!copied">Copy</span>
                                            <span x-show="copied">Copied!</span>
                                        </button>

                                        {{-- NEW: QR button (opens Livewire-driven modal) --}}
                                        <button
                                            type="button"
                                            wire:click="openQr('{{ $s->token }}')"
                                            class="rounded-md bg-white px-3 py-1.5 text-xs font-medium inset-ring inset-ring-gray-300 hover:bg-gray-50
                                                   dark:bg-white/5 dark:text-gray-200 dark:inset-ring-white/10 dark:hover:bg-white/10"
                                            title="Show QR"
                                        >
                                            QR
                                        </button>

                                        {{-- Delete immediately: no modal --}}
                                        <button wire:click="deleteSession({{ $s->id }})"
                                                class="rounded-md bg-white px-3 py-1.5 text-xs font-medium text-rose-600 inset-ring inset-ring-gray-300 hover:bg-rose-50
                                                       dark:bg-white/5 dark:text-rose-300 dark:inset-ring-white/10 dark:hover:bg-rose-500/10">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 px-4 text-sm text-gray-500 dark:text-gray-400">
                                    No kiosk sessions {{ $showAll ? 'yet' : 'for week '.$week_number }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Livewire-driven QR Modal (server-rendered SVG via QrCode) --}}
    @if ($showQr && $qrUrl)
        <div
            x-data
            x-init="$nextTick(() => { document.body.classList.add('overflow-hidden') })"
            x-on:keydown.escape.window="$wire.closeQr()"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            aria-modal="true"
        >
            <div class="absolute inset-0 bg-black/50" wire:click="closeQr()"></div>

            <div class="relative w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Kiosk QR</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Scan to open kiosk on a device.</p>

                <div class="mt-4 flex items-center justify-center">
                    {{-- Server-rendered SVG (no external requests) --}}
                    {!! QrCode::format('svg')
                        ->size(240)
                        ->margin(1)
                        ->errorCorrection('M')
                        ->generate($qrUrl) !!}
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="rounded-md bg-white px-3 py-1.5 text-sm font-medium inset-ring inset-ring-gray-300 hover:bg-gray-50
                               dark:bg-white/5 dark:text-gray-200 dark:inset-ring-white/10 dark:hover:bg-white/10"
                        wire:click="closeQr()"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</section>
