<x-layouts.app :title="__('Dashboard')">
@php
    $user = auth()->user();

    // Profile
    $profile = \App\Models\ArcherProfile::query()->where('user_id', $user->id)->first();

    // Avatar lookup (public disk first, then optional accessor)
    $avatarUrl = null;
    $uid = (int) $user->id;
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    if ($disk->exists("avatars/{$uid}.webp")) {
        $avatarUrl = \Illuminate\Support\Facades\Storage::url("avatars/{$uid}.webp");
    } elseif ($disk->exists("avatars/{$uid}.jpg")) {
        $avatarUrl = \Illuminate\Support\Facades\Storage::url("avatars/{$uid}.jpg");
    } elseif (method_exists($user, 'profile_photo_url')) {
        $avatarUrl = $user->profile_photo_url;
    }

    // Helpers
    $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->isoFormat('LL') : '—';
    $valOrDash = fn($v) => $v ? $v : '—';

    /* =========================
       Basic Statistics (middle)
       ========================= */

    // Training rollups
    $ts = \App\Models\TrainingSession::query()
        ->where('user_id', $user->id)
        ->get(['id','ends_completed','arrows_per_end','total_score']);

    $trainingArrows = (int) $ts->sum(fn($s) => (int)$s->ends_completed * (int)$s->arrows_per_end);
    $trainingPoints = (int) $ts->sum('total_score');
    $bestTraining   = (int) ($ts->max('total_score') ?? 0);

    // League rollups (via participant->user_id)
    $leagueScores = \App\Models\LeagueWeekScore::query()
        ->join('league_participants','league_participants.id','=','league_week_scores.league_participant_id')
        ->whereNotNull('league_participants.user_id')
        ->where('league_participants.user_id', $user->id)
        ->get([
            'league_week_scores.id',
            'league_week_scores.arrows_per_end',
            'league_week_scores.total_score',
        ]);

    $endsCounts = collect();
    if ($leagueScores->isNotEmpty()) {
        $endsCounts = \App\Models\LeagueWeekEnd::query()
            ->select('league_week_score_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as c'))
            ->whereIn('league_week_score_id', $leagueScores->pluck('id'))
            ->groupBy('league_week_score_id')
            ->pluck('c','league_week_score_id');
    }

    $leagueArrows = 0;
    foreach ($leagueScores as $row) {
        $completedEnds = (int) ($endsCounts[$row->id] ?? 0);
        $leagueArrows += $completedEnds * (int) $row->arrows_per_end;
    }
    $leaguePoints = (int) $leagueScores->sum('total_score');
    $bestLeague   = (int) ($leagueScores->max('total_score') ?? 0);

    // Final combined stats
    $totalArrows = $trainingArrows + $leagueArrows;
    $totalPoints = $trainingPoints + $leaguePoints;
    $avgArrow    = $totalArrows > 0 ? number_format($totalPoints / $totalArrows, 2) : '—';

    // ----- Events rollup for bottom panel -----
    $tz = config('app.timezone');
    $today = \Carbon\Carbon::now($tz)->startOfDay();
    $soonEnd = $today->copy()->addDays(30);

    // Common base: published & not archived, with an info page published
    $baseLeagues = \App\Models\League::query()
        ->where('is_archived', 0)
        ->where('is_published', 1)
        ->with('info')
        ->whereHas('info', fn($q) => $q->where('is_published', 1));

    // OPEN NOW: (start <= today || null) AND (end >= today || null)
    $openNow = (clone $baseLeagues)
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

    // OPENING SOON: start in (today, today+30]
    $openingSoon = (clone $baseLeagues)
        ->whereNotNull('registration_start_date')
        ->whereDate('registration_start_date', '>', $today->toDateString())
        ->whereDate('registration_start_date', '<=', $soonEnd->toDateString())
        ->orderBy('registration_start_date')
        ->limit(8)
        ->get();

    $fmtDateYmd = fn($d) => $d ? \Carbon\Carbon::parse($d, $tz)->format('Y-m-d') : '—';
    $publicInfoUrl = fn($league) => route('public.league.info', ['uuid' => $league->public_uuid]);

@endphp

    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            {{-- Top-left: Profile card (with gradient header) --}}
            <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
                <div class="flex items-center gap-4 border-b border-neutral-200 p-4 dark:border-neutral-700
                            bg-gradient-to-r from-indigo-50 to-white dark:from-indigo-900/40 dark:to-neutral-900">
                    <div class="relative">
                        @if($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="{{ $user->name }}" class="h-16 w-16 rounded-full object-cover ring-1 ring-black/5" />
                        @else
                            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-neutral-200 text-neutral-600 ring-1 ring-black/5 dark:bg-neutral-800 dark:text-neutral-300">
                                <span class="text-lg font-semibold">
                                    {{ \Illuminate\Support\Str::of($user->name)
                                        ->replaceMatches('/[^A-Za-z ]/', '')
                                        ->trim()
                                        ->explode(' ')
                                        ->map(fn($p)=>\Illuminate\Support\Str::substr($p,0,1))
                                        ->take(2)
                                        ->join('') ?: 'U' }}
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="min-w-0">
                        <div class="truncate text-base font-semibold text-neutral-900 dark:text-neutral-100">
                            {{ $user->name }}
                        </div>
                        <div class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
                            Member since {{ optional($user->created_at)->format('M Y') ?? '—' }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-0 divide-y divide-neutral-200 dark:divide-neutral-800">
                    <div class="grid grid-cols-3 items-center gap-3 px-4 py-3">
                        <div class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Birth date</div>
                        <div class="col-span-2 text-sm text-neutral-900 dark:text-neutral-100">
                            {{ $fmtDate($profile?->birth_date) }}
                        </div>
                    </div>

                    <div class="grid grid-cols-3 items-center gap-3 px-4 py-3">
                        <div class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Country</div>
                        <div class="col-span-2 text-sm text-neutral-900 dark:text-neutral-100">
                            {{ $valOrDash($profile?->country) }}
                        </div>
                    </div>

                    <div class="grid grid-cols-3 items-center gap-3 px-4 py-3">
                        <div class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Club</div>
                        <div class="col-span-2 text-sm text-neutral-900 dark:text-neutral-100">
                            {{ $valOrDash($profile?->club_affiliation) }}
                        </div>
                    </div>

                    <div class="grid grid-cols-3 items-center gap-3 px-4 py-3">
                        <div class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">USA Archery #</div>
                        <div class="col-span-2 text-sm text-neutral-900 dark:text-neutral-100">
                            {{ $valOrDash($profile?->us_archery_number) }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Top-middle: Basic statistics --}}
            <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-900">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Basic statistics</h2>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                        <div class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Arrows shot</div>
                        <div class="mt-1 text-2xl font-semibold text-neutral-900 dark:text-neutral-100">
                            {{ number_format($totalArrows) }}
                        </div>
                        <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            Training {{ number_format($trainingArrows) }} • League {{ number_format($leagueArrows) }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                        <div class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Avg arrow score</div>
                        <div class="mt-1 text-2xl font-semibold text-neutral-900 dark:text-neutral-100">
                            {{ $avgArrow }}
                        </div>
                        <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            Points {{ number_format($totalPoints) }} / {{ number_format($totalArrows) }} arrows
                        </div>
                    </div>

                    <div class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                        <div class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Best training score</div>
                        <div class="mt-1 text-2xl font-semibold text-neutral-900 dark:text-neutral-100">
                            {{ number_format($bestTraining) }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                        <div class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Best league score</div>
                        <div class="mt-1 text-2xl font-semibold text-neutral-900 dark:text-neutral-100">
                            {{ number_format($bestLeague) }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Top-right placeholder --}}
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
        </div>

        {{-- Bottom: Events (registration) --}}
        <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
            <div class="p-4 border-b border-neutral-200 dark:border-white/10">
                <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Events & Registration</h2>
                <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
                    Showing Events with open registration and those opening soon (next 30 days).
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
                                    $start = $lg->registration_start_date;
                                    $end   = $lg->registration_end_date;
                                @endphp
                                <li class="px-4 py-3">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <div class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                                {{ $lg->title }}
                                            </div>
                                            <div class="mt-0.5 text-xs text-neutral-600 dark:text-neutral-400">
                                                {{ $lg->location ?: '—' }} • Starts {{ $fmtDateYmd($lg->start_date) }} • {{ $lg->length_weeks }} weeks
                                            </div>
                                            <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                                @if($start) Opened {{ $fmtDateYmd($start) }}. @endif
                                                @if($end) Closes {{ $fmtDateYmd($end) }}. @endif
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <a href="{{ $publicInfoUrl($lg) }}" 
                                            class="rounded-md px-3 py-1.5 text-xs font-medium inset-ring inset-ring-neutral-300 hover:bg-neutral-50 dark:inset-ring-white/10 dark:hover:bg-white/5">
                                                View details
                                            </a>
                                            @if($hasUrl)
                                                <a href="{{ $lg->info->registration_url }}" target="_blank" rel="noopener"
                                                class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                                                    Register
                                                </a>
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
                        <div class="p-4 text-sm text-neutral-600 dark:text-neutral-300">No upcoming registration windows in the next 30 days.</div>
                    @else
                        <ul class="divide-y divide-neutral-200 dark:divide-white/10">
                            @foreach($openingSoon as $lg)
                                @php
                                    $start = $lg->registration_start_date;
                                    $end   = $lg->registration_end_date;
                                @endphp
                                <li class="px-4 py-3">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <div class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                                {{ $lg->title }}
                                            </div>
                                            <div class="mt-0.5 text-xs text-neutral-600 dark:text-neutral-400">
                                                {{ $lg->location ?: '—' }} • Starts {{ $fmtDateYmd($lg->start_date) }} • {{ $lg->length_weeks }} weeks
                                            </div>
                                            <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                                Opens {{ $fmtDateYmd($start) }}@if($end) • Closes {{ $fmtDateYmd($end) }}@endif
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <a href="{{ $publicInfoUrl($lg) }}" target="_blank"
                                            class="rounded-md px-3 py-1.5 text-xs font-medium inset-ring inset-ring-neutral-300 hover:bg-neutral-50 dark:inset-ring-white/10 dark:hover:bg-white/5">
                                                View details
                                            </a>
                                            @if(filled($lg->info?->registration_url))
                                                <a href="{{ $lg->info->registration_url }}" target="_blank" rel="noopener"
                                                class="inline-flex items-center rounded-md bg-neutral-200 px-3 py-1.5 text-xs font-semibold text-neutral-800 hover:bg-neutral-300 dark:bg-white/10 dark:text-neutral-200 dark:hover:bg-white/20">
                                                    Pre-register
                                                </a>
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

    </div>
</x-layouts.app>
