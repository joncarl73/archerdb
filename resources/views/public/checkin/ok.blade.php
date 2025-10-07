{{-- resources/views/public/checkin/ok.blade.php --}}
<x-layouts.public :league="$league">
  @php
    // Inputs flashed by controller:
    // $name, $repeat, $week, $lane, $checkinId

    $mode = $league->scoring_mode->value ?? $league->scoring_mode;

    // Resolve the selected week's scheduled date (if week number is present)
    $weekRow = null;
    if (!empty($week)) {
        $event = $league->event ?? null;
        $weekRow = \App\Models\LeagueWeek::query()
            ->forContext($event, $league)
            ->where('week_number', (int) $week)
            ->first();
    }
    $today    = \Illuminate\Support\Carbon::today();
    $weekDate = $weekRow ? \Illuminate\Support\Carbon::parse($weekRow->date) : null;

    // NEW: Decide PD vs kiosk
    // - If league is personal_device => always PD
    // - Else (kiosk/tablet) => PD only when today != scheduled date for that week
    $usePersonalDevice = ($mode === 'personal_device')
        || ($weekDate ? !$today->isSameDay($weekDate) : true);

    // Build Start URL (PD path: append ?pd=1 only when league mode is NOT personal_device)
    $baseStart = ($checkinId ?? null)
      ? route('public.scoring.start', [$league->public_uuid, $checkinId])
      : null;

    $startUrl = null;
    if ($baseStart && $usePersonalDevice) {
        $startUrl = ($mode === 'personal_device') ? $baseStart : $baseStart.'?pd=1';
    }

    // Back to picker
    $backUrl = route('public.checkin.participants', ['uuid' => $league->public_uuid]);
  @endphp

  <section class="w-full py-10">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
      <div class="text-center">
        <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">
          {{ $name ? "Thanks, {$name}!" : 'Check-in complete' }}
        </h1>

        @if ($repeat)
          <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            You’re already checked in{{ $week ? " for week {$week}" : '' }}{{ $lane ? " • Lane {$lane}" : '' }}.
          </p>
        @else
          <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            You’re checked in{{ $week ? " for week {$week}" : '' }}{{ $lane ? " • Lane {$lane}" : '' }}.
          </p>
        @endif

        {{-- Show Start button ONLY for personal-device cases --}}
        @if ($startUrl)
          <div class="mt-6 flex items-center justify-center gap-3">
            <flux:button as="a" href="{{ $startUrl }}" variant="primary">
              Start scoring
            </flux:button>

            <flux:button as="a" href="{{ $backUrl }}" variant="ghost">
              Choose a different archer
            </flux:button>
          </div>

          @if ($mode !== 'personal_device')
            <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
              Using <span class="font-medium">personal-device scoring</span> because today is not the scheduled league night for this week.
            </p>
          @endif
        @else
          {{-- Kiosk night (today == selected week’s date) for kiosk/tablet leagues --}}
          <div class="mt-6 space-y-2">
            @if ($checkinId && $mode !== 'personal_device')
              <p class="text-sm text-zinc-600 dark:text-zinc-400">
                It’s league night for Week {{ $week }}. Please use the kiosk tablet at your lane to enter scores.
              </p>
            @endif

            <flux:button as="a" href="{{ $backUrl }}" variant="ghost">
              Back to check-in
            </flux:button>
          </div>
        @endif
      </div>

      <div class="mt-8 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div>
            <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">League</dt>
            <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $league->title }}</dd>
          </div>
          <div>
            <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Week</dt>
            <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $week ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Lane</dt>
            <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $lane ?? '—' }}</dd>
          </div>
        </dl>
        @if ($weekDate)
          <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
            Scheduled date for Week {{ $week }}: {{ $weekDate->toFormattedDateString() }}.
          </p>
        @endif
      </div>
    </div>
  </section>
</x-layouts.public>
