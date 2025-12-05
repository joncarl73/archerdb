{{-- resources/views/public/cls/scoring-summary.blade.php --}}
@php
  $isEvent = ($kind === 'event');

  // Title: event title or league title/name
  $title = $isEvent
      ? ($owner->title ?? 'Event')
      : ($owner->title ?? $owner->name ?? 'League');

  // Participant (both EventScore and LeagueWeekScore have a participant relationship)
  $participant = $score->participant ?? null;
  $archerName = null;

  if ($participant) {
      $first = $participant->first_name ?? '';
      $last  = $participant->last_name ?? '';
      $archerName = trim($first . ' ' . $last) ?: null;
  }

  // Scoring config from snapshot (both models have these columns now)
  $arrowsPerEnd = (int) ($score->arrows_per_end ?? 3);
  $endsPlanned  = (int) ($score->ends_planned ?? ($score->ends?->count() ?? 10));
  $xValue       = $score->x_value ?? null;
  $maxScore     = (int) ($score->max_score ?? 10);

  $endsCollection = $score->ends ?? collect();

  // Build display rows similar to CLS record component
  $displayEnds   = [];
  $completedEnds = 0;
  $totalScore    = 0;
  $totalX        = 0;

  $xVal = $xValue !== null ? (int) $xValue : null;

  foreach ($endsCollection as $endModel) {
      $endNumber = (int) $endModel->end_number;
      $scores    = $endModel->scores ?? [];

      $rowScores = [];
      $sum       = 0;
      $xCount    = 0;
      $hasAny    = false;

      for ($i = 0; $i < $arrowsPerEnd; $i++) {
          $v = $scores[$i] ?? null;
          $rowScores[$i] = $v;

          if ($v !== null) {
              $hasAny = true;
              $sum   += (int) $v;

              if ($xVal !== null && (int) $v === $xVal) {
                  $xCount++;
              }
          }
      }

      if ($hasAny) {
          $completedEnds++;
      }

      $totalScore += $sum;
      $totalX     += $xCount;

      $displayEnds[] = [
          'end_number' => $endNumber,
          'scores'     => $rowScores,
          'total'      => $sum,
          'x_count'    => $xCount,
          'has_any'    => $hasAny,
      ];
  }

  usort($displayEnds, static fn (array $a, array $b) => $a['end_number'] <=> $b['end_number']);
@endphp

<x-layouts.public :league="null">
  <section class="w-full mb-5">
    {{-- Header --}}
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="sm:flex sm:items-center sm:justify-between">
        <div class="sm:flex-auto">
          <h1 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
            {{ $isEvent ? 'Event scoring summary' : 'League scoring summary' }}
          </h1>

          <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-400">
            {{ $title }}
            @if ($archerName)
              • Archer: {{ $archerName }}
            @endif
            <br>
            {{ $arrowsPerEnd }} arrows/end • up to {{ $maxScore }} points/arrow
            @if(($xValue ?? 0) > $maxScore)
              (X={{ $xValue }})
            @endif
          </p>
        </div>

        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
          @if ($isEvent)
            <a
              href="{{ route('public.cls.participants', ['kind' => 'event', 'uuid' => $owner->public_uuid]) }}"
              class="text-sm text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
            >
              Back to event check-in
            </a>
          @else
            <a
              href="{{ route('public.cls.participants', ['kind' => 'league', 'uuid' => $owner->public_uuid]) }}"
              class="text-sm text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
            >
              Back to league check-in
            </a>
          @endif
        </div>
      </div>
    </div>

    {{-- Score table --}}
    <div class="mt-8">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="overflow-hidden rounded-xl border border-zinc-200 shadow-sm dark:border-zinc-700">
          <table class="w-full text-left">
            <thead class="bg-white dark:bg-zinc-900">
              <tr>
                <th class="py-3.5 pl-4 pr-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                  End
                </th>
                <th class="px-3 py-3.5 text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                  Arrows
                </th>
                <th class="px-3 py-3.5 text-sm font-semibold text-zinc-900 dark:text-zinc-100 w-24">
                  End&nbsp;Total
                </th>
                <th class="px-3 py-3.5 text-sm font-semibold text-zinc-900 dark:text-zinc-100 w-20">
                  X
                </th>
              </tr>
            </thead>

            <tbody class="divide-y divide-zinc-100 dark:divide-white/10">
              @foreach ($displayEnds as $row)
                @php
                  $endNumber = $row['end_number'];
                  $scores    = $row['scores'] ?? [];
                  $endTotal  = $row['total'] ?? 0;
                  $endX      = $row['x_count'] ?? 0;
                @endphp

                <tr>
                  <td class="py-4 pl-4 pr-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                    {{ $endNumber }}
                  </td>
                  <td class="px-3 py-3">
                    <div
                      class="grid gap-2"
                      style="grid-template-columns: repeat({{ $arrowsPerEnd }}, minmax(0,1fr));"
                    >
                      @for ($i = 0; $i < $arrowsPerEnd; $i++)
                        @php
                          $rawVal = $scores[$i] ?? null;

                          if ($rawVal === null) {
                              $cellLabel = null;
                          } elseif ((int)$rawVal === 0) {
                              $cellLabel = 'M';
                          } elseif ($xValue !== null && (int)$rawVal === (int)$xValue) {
                              $cellLabel = 'X';
                          } else {
                              $cellLabel = $rawVal;
                          }
                        @endphp

                        <div
                          class="h-10 rounded-lg inset-ring inset-ring-zinc-300 dark:inset-ring-zinc-700 flex items-center justify-center text-sm"
                        >
                          @if ($cellLabel === null)
                            <span class="opacity-40">·</span>
                          @else
                            {{ $cellLabel }}
                          @endif
                        </div>
                      @endfor
                    </div>
                  </td>
                  <td class="px-3 py-3 text-sm tabular-nums text-zinc-900 dark:text-zinc-100">
                    {{ $endTotal }}
                  </td>
                  <td class="px-3 py-3 text-sm tabular-nums text-zinc-900 dark:text-zinc-100">
                    {{ $endX }}
                  </td>
                </tr>
              @endforeach
            </tbody>

            <tfoot class="bg-zinc-50/60 dark:bg-white/5">
              <tr>
                <th class="py-3.5 pl-4 pr-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                  Totals
                </th>
                <td class="px-3 py-3 text-sm text-zinc-700 dark:text-zinc-400">
                  Ends completed: {{ $completedEnds }} / {{ $endsPlanned }}
                </td>
                <td class="px-3 py-3 text-sm font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
                  {{ $totalScore }}
                </td>
                <td class="px-3 py-3 text-sm font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
                  {{ $totalX }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </section>
</x-layouts.public>
