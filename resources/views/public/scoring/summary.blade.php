{{-- resources/views/public/scoring/summary.blade.php --}}
<x-layouts.public :league="$league">
  <section class="w-full py-8">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

      {{-- Thanks banner --}}
      <div class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-gradient-to-br from-indigo-500/10 via-emerald-500/10 to-sky-500/10 p-6 shadow-sm dark:border-white/10">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Thanks for participating!</h1>
            <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
              Week #{{ $score->week?->week_number ?? '—' }} •
              {{ $score->arrows_per_end }} arrows/end •
              max {{ $maxPerArrow }} points/arrow •
              X = {{ $score->x_value }}
            </p>
          </div>
          <div class="shrink-0">
            <flux:badge color="indigo">League: {{ $league->title }}</flux:badge>
          </div>
        </div>
      </div>

      {{-- Stats grid (inline cards) --}}
      <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
          <div class="text-sm text-zinc-500 dark:text-zinc-400">Total score</div>
          <div class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-100 tabular-nums">
            {{ $totalScore }}
          </div>
          <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Max (entered): {{ $maxPossibleEntered }}</div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
          <div class="text-sm text-zinc-500 dark:text-zinc-400">Average / arrow</div>
          <div class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-100 tabular-nums">
            {{ number_format($avgPerArrow, 2) }}
          </div>
          <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Across {{ $arrowsEntered }} arrows</div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
          <div class="text-sm text-zinc-500 dark:text-zinc-400">Ends completed</div>
          <div class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-100 tabular-nums">
            {{ $endsCompleted }} / {{ $plannedEnds }}
          </div>
          <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $completionPct }}% complete</div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
          <div class="text-sm text-zinc-500 dark:text-zinc-400">X count</div>
          <div class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-100 tabular-nums">
            {{ $xCount }}
          </div>
          <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">X rate: {{ $xRate }}%</div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
          <div class="text-sm text-zinc-500 dark:text-zinc-400">Config</div>
          <div class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
            {{ $arrowsPerEnd }} arrows/end • max {{ $maxPerArrow }} pts/arrow
          </div>
          @if(($score->x_value ?? 10) > $maxPerArrow)
            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">X = {{ $score->x_value }}</div>
          @endif
        </div>
      </div>

    </div>
  </section>
</x-layouts.public>
