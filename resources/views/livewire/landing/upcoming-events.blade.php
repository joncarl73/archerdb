<?php
use App\Models\League;
use Carbon\Carbon;
use Livewire\Volt\Component;

new class extends Component
{
    // Public props (optional filters later)
    public int $windowDays = 30;

    // Derived data
    public $openNow;

    public $openingSoon;

    public function mount(): void
    {
        $tz = config('app.timezone');
        $today = Carbon::now($tz)->startOfDay();
        $soonEnd = $today->copy()->addDays($this->windowDays);

        // Common base: published & not archived, with an info page published
        $baseLeagues = League::query()
            ->where('is_archived', 0)
            ->where('is_published', 1)
            ->with('info')
            ->whereHas('info', fn ($q) => $q->where('is_published', 1));

        // OPEN NOW: (start <= today || null) AND (end >= today || null)
        $this->openNow = (clone $baseLeagues)
            ->where(function ($q) use ($today) {
                $q->whereNull('registration_start_date')
                    ->orWhereDate('registration_start_date', '<=', $today->toDateString());
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('registration_end_date')
                    ->orWhereDate('registration_end_date', '>=', $today->toDateString());
            })
            ->orderBy('start_date')
            ->limit(8)
            ->get();

        // OPENING SOON: start in (today, today+window]
        $this->openingSoon = (clone $baseLeagues)
            ->whereNotNull('registration_start_date')
            ->whereDate('registration_start_date', '>', $today->toDateString())
            ->whereDate('registration_start_date', '<=', $soonEnd->toDateString())
            ->orderBy('registration_start_date')
            ->limit(8)
            ->get();
    }

    // View helpers
    public function publicInfoUrl($league): string
    {
        return route('public.league.info.landing', ['uuid' => $league->public_uuid]);
    }

    public function fmtYmd($d, $tz = null): string
    {
        if (! $d) {
            return '—';
        }
        $tz = $tz ?: config('app.timezone');

        return Carbon::parse($d, $tz)->format('Y-m-d');
    }
};
?>

<div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
    <div class="p-4 border-b border-neutral-200 dark:border-white/10">
        <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Upcoming events & registration</h2>
        <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
            Showing events with open registration and those opening soon (next {{ $windowDays }} days).
        </p>
    </div>

    <div class="p-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Open now --}}
        <div class="rounded-lg border border-neutral-200 dark:border-white/10">
            <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-white/10">
                <h3 class="text-sm font-medium text-neutral-900 dark:text-neutral-100">Open now</h3>
                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-200 dark:ring-emerald-900/40">
                    {{ $openNow->count() }}
                </span>
            </div>

            @if($openNow->isEmpty())
                <div class="p-4 text-sm text-neutral-600 dark:text-neutral-300">No events currently open.</div>
            @else
                <ul class="divide-y divide-neutral-200 dark:divide-white/10">
                    @foreach($openNow as $lg)
                        @php
                            $hasUrl = filled($lg->info?->registration_url);
                        @endphp
                        <li class="px-4 py-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                        {{ $lg->title }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-neutral-600 dark:text-neutral-400">
                                        {{ $lg->location ?: '—' }} • Starts {{ $this->fmtYmd($lg->start_date) }} • {{ $lg->length_weeks }} weeks
                                    </div>
                                    <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                        @if($lg->registration_start_date) Opened {{ $this->fmtYmd($lg->registration_start_date) }}. @endif
                                        @if($lg->registration_end_date) Closes {{ $this->fmtYmd($lg->registration_end_date) }}. @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <x-flux::button as="a" href="{{ $this->publicInfoUrl($lg) }}" variant="outline" size="sm">
                                        View details
                                    </x-flux::button>
                                    @if($hasUrl)
                                        <x-flux::button as="a" href="{{ $lg->info->registration_url }}" target="_blank" rel="noopener" variant="primary" size="sm">
                                            Register
                                        </x-flux::button>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Opening soon --}}
        <div class="rounded-lg border border-neutral-200 dark:border-white/10">
            <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-white/10">
                <h3 class="text-sm font-medium text-neutral-900 dark:text-neutral-100">Opening soon</h3>
                <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-200 dark:bg-amber-900/20 dark:text-amber-200 dark:ring-amber-900/40">
                    {{ $openingSoon->count() }}
                </span>
            </div>

            @if($openingSoon->isEmpty())
                <div class="p-4 text-sm text-neutral-600 dark:text-neutral-300">No upcoming registration windows in the next {{ $windowDays }} days.</div>
            @else
                <ul class="divide-y divide-neutral-200 dark:divide-white/10">
                    @foreach($openingSoon as $lg)
                        <li class="px-4 py-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                        {{ $lg->title }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-neutral-600 dark:text-neutral-400">
                                        {{ $lg->location ?: '—' }} • Starts {{ $this->fmtYmd($lg->start_date) }} • {{ $lg->length_weeks }} weeks
                                    </div>
                                    <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                        Opens {{ $this->fmtYmd($lg->registration_start_date) }}
                                        @if($lg->registration_end_date) • Closes {{ $this->fmtYmd($lg->registration_end_date) }} @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <x-flux::button as="a" href="{{ $this->publicInfoUrl($lg) }}" variant="outline" size="sm">
                                        View details
                                    </x-flux::button>
                                    @if(filled($lg->info?->registration_url))
                                        <x-flux::button as="a" href="{{ $lg->info->registration_url }}" target="_blank" rel="noopener" variant="secondary" size="sm">
                                            Pre-register
                                        </x-flux::button>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
