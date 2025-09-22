<x-layouts.public :league="$league">
  <section class="w-full">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
      {{-- Title --}}
      <h1 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
        @if(!empty($repeat))
          You’re already checked in
        @else
          You’re all set
        @endif
      </h1>

      {{-- Card --}}
      <div class="mt-6 max-w-xl overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        @php
          $who  = $name ? "{$name}, " : '';
          $wk   = $week ? "week {$week}" : "this session";
          $ln   = $lane ? " (Lane {$lane})" : '';
          $scoringMode = $league->scoring_mode->value ?? $league->scoring_mode ?? 'personal_device';
          $checkinIdForRoute = isset($checkin) ? $checkin->id : ($checkinId ?? null);
        @endphp

        @if(!empty($repeat))
          <p class="text-sm text-zinc-700 dark:text-zinc-300">
            {{ $who }}you had already checked in for {{ $wk }}{{ $ln }}. You’re good to go—good shooting!
          </p>
        @else
          <p class="text-sm text-zinc-700 dark:text-zinc-300">
            {{ $who }}you’ve been checked in for {{ $wk }}{{ $ln }}. Good shooting!
          </p>
        @endif

        <div class="mt-5 flex flex-wrap items-center gap-3">
          {{-- Start scoring (personal device only) --}}
          @if($scoringMode === 'personal_device' && $checkinIdForRoute)
            <flux:button
              :href="route('public.scoring.start', [$league->public_uuid, $checkinIdForRoute])"
              variant="ghost"
            >
              Start scoring
            </flux:button>
          @endif

          {{-- Check in another archer --}}
          <flux:button
            :href="route('public.checkin.participants', $league->public_uuid)"
            variant="primary"
          >
            Check in another archer
          </flux:button>
        </div>

        @if(!$name && !$week && !$lane)
          <p class="mt-4 text-xs text-zinc-600 dark:text-zinc-400">
            Don’t see details? Start over on the participants page.
          </p>
        @endif
      </div>
    </div>
  </section>
</x-layouts.public>
