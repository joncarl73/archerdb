<?php
use App\Models\League;
use App\Models\LeagueCheckin;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    public League $league;

    public string $checkinUrl = '';

    public function mount(League $league): void
    {
        Gate::authorize('view', $league);
        $this->league = $league->load(['weeks' => fn ($q) => $q->orderBy('week_number')]);
        $this->checkinUrl = route('public.checkin.participants', ['uuid' => $this->league->public_uuid]);
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
};
?>

<section class="w-full">
    @php
        $mode = $league->scoring_mode->value ?? $league->scoring_mode;
        $isTabletMode = ($mode === 'tablet');
        $canManageKiosks = Gate::check('manageKiosks', $league);
        $canUpdateLeague = Gate::check('update', $league);
    @endphp

    <div class="mx-auto max-w-7xl">
        {{-- Header --}}
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
                    {{-- NEW: Participants --}}
                    <flux:button
                        as="a"
                        href="{{ route('corporate.leagues.participants.index', $league) }}"
                        variant="primary"
                        color="indigo"
                        icon="users">
                        Participants
                    </flux:button>

                    {{-- Kiosk sessions --}}
                    @if($isTabletMode && $canManageKiosks)
                        <flux:button as="a" href="{{ route('corporate.manager.kiosks.index', $league) }}" variant="primary" color="emerald" icon="computer-desktop">
                            Kiosk sessions
                        </flux:button>
                    @endif

                    {{-- Actions dropdown --}}
                    <flux:dropdown>
                        <flux:button icon:trailing="chevron-down">Actions</flux:button>
                        <flux:menu class="min-w-64">
                            @if($canUpdateLeague)
                            <flux:menu.item href="{{ route('corporate.leagues.info.edit', $league) }}" icon="pencil-square">
                                Create/Update league info
                            </flux:menu.item>
                            @endif
                            <flux:menu.item href="{{ route('public.league.info', ['uuid' => $league->public_uuid]) }}" target="_blank" icon="arrow-top-right-on-square">
                                View public page
                            </flux:menu.item>
                            <flux:menu.item href="{{ route('corporate.leagues.scoring_sheet', $league) }}" icon="document-arrow-down">
                                Download scoring sheet (PDF)
                            </flux:menu.item>
                            <flux:menu.item href="{{ route('corporate.leagues.participants.export', $league) }}" icon="users">
                                Export participants
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </div>
        </div>

        {{-- Public check-in URL + QR (unchanged) --}}
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
                    <a href="{{ $checkinUrl }}" target="_blank"
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
                <a href="{{ route('corporate.leagues.qr.pdf', $league) }}"
                   title="Download printable QR (PDF)"
                   class="block transition hover:opacity-90 focus:opacity-90">
                    <div class="h-36 w-36">
                        <div class="h-full w-full [&>svg]:h-full [&>svg]:w-full">
                            {!! QrCode::format('svg')->size(300)->margin(1)->errorCorrection('M')->generate($checkinUrl) !!}
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    {{-- Schedule (unchanged) --}}
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
                                    <flux:button as="a" href="{{ route('corporate.leagues.weeks.live', [$league, $w]) }}?kiosk=1"
                                                 target="_blank" size="sm" variant="primary" color="blue"
                                                 icon="presentation-chart-bar">
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
</section>
