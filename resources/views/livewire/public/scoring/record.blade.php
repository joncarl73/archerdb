@php
  // Gate kiosk UI strictly by league settings + timing.
  $isLeagueNight     = (int)\Carbon\Carbon::now(config('app.timezone'))->dayOfWeek === (int)$league->day_of_week;
  $isTabletMode      = ($league->scoring_mode === 'tablet');

  // Final flag: show kiosk-only controls (e.g., "Back to kiosk") iff ALL are true.
  $showKioskControls = $isTabletMode && $isLeagueNight && !empty($kioskMode) && !empty($kioskReturnTo);
@endphp

<section class="w-full mb-5">
  {{-- Header --}}
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
      <div class="sm:flex-auto">
        <h1 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">League scoring</h1>
        <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-400">
          Week #{{ $score->week?->week_number ?? '—' }} •
          {{ $score->arrows_per_end }} arrows/end • up to {{ $score->max_score }} points/arrow
          @if(($score->x_value ?? 10) > $score->max_score)
            (X={{ $score->x_value }})
          @endif
        </p>
      </div>

      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        @if($showKioskControls)
          {{-- Tablet mode + league night + kiosk context => go back to lane board --}}
          <flux:button as="a" variant="primary" href="{{ $kioskReturnTo }}">
            Back to kiosk
          </flux:button>
        @else
          {{-- Personal-device flow: finish this score and go to summary (or your scoring grid if different) --}}
          <flux:button as="a" variant="primary"
            href="{{ route('public.scoring.summary', [$league->public_uuid, $score->id]) }}">
            {{-- If your "scoring grid" is a different route, replace the route() above. --}}
            End scoringbb
          </flux:button>
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
              <th class="py-3.5 pl-4 pr-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">End</th>
              <th class="px-3 py-3.5 text-sm font-semibold text-zinc-900 dark:text-zinc-100">Arrows</th>
              <th class="px-3 py-3.5 text-sm font-semibold text-zinc-900 dark:text-zinc-100 w-24">End&nbsp;Total</th>
              <th class="px-3 py-3.5 text-sm font-semibold text-zinc-900 dark:text-zinc-100 w-20">X</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-zinc-100 dark:divide-white/10">
            @foreach ($score->ends as $end)
              <tr>
                <td class="py-4 pl-4 pr-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                  {{ $end->end_number }}
                </td>
                <td class="px-3 py-3">
                  <div class="grid gap-2" style="grid-template-columns: repeat({{ $score->arrows_per_end }}, minmax(0,1fr));">
                    @for ($i = 0; $i < $score->arrows_per_end; $i++)
                      @php($val = $end->scores[$i] ?? null)
                      <button
                        wire:click="startEntry({{ $end->end_number }}, {{ $i }})"
                        class="h-10 rounded-lg inset-ring inset-ring-zinc-300 hover:bg-zinc-50 dark:inset-ring-zinc-700 dark:hover:bg-white/5
                               @if($selectedEnd === $end->end_number && $selectedArrow === $i) ring-2 ring-indigo-500 @endif"
                      >
                        @if ($val === null)
                          <span class="opacity-40">·</span>
                        @elseif ((int)$val === 0)
                          M
                        @elseif ($scoringSystem === '10' && (int)$val === (int)($score->x_value ?? 10))
                          X
                        @else
                          {{ $val }}
                        @endif
                      </button>
                    @endfor
                  </div>
                </td>
                <td class="px-3 py-3 text-sm tabular-nums text-zinc-900 dark:text-zinc-100">{{ $end->end_score ?? 0 }}</td>
                <td class="px-3 py-3 text-sm tabular-nums text-zinc-900 dark:text-zinc-100">{{ $end->x_count ?? 0 }}</td>
              </tr>
            @endforeach
          </tbody>

          <tfoot class="bg-zinc-50/60 dark:bg-white/5">
            @php($completedEnds = 0)
            @foreach ($score->ends as $e)
              @php($hasAny = false)
              @if (is_array($e->scores))
                @foreach ($e->scores as $sv)
                  @if (!is_null($sv)) @php($hasAny = true) @break @endif
                @endforeach
              @endif
              @if ($hasAny) @php($completedEnds++) @endif
            @endforeach
            @php($plannedEnds = $score->ends_planned ?? $score->ends->count())
            <tr>
              <th class="py-3.5 pl-4 pr-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">Totals</th>
              <td class="px-3 py-3 text-sm text-zinc-700 dark:text-zinc-400">
                Ends completed: {{ $completedEnds }} / {{ $plannedEnds }}
              </td>
              <td class="px-3 py-3 text-sm font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $score->total_score }}</td>
              <td class="px-3 py-3 text-sm font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $score->x_count }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  {{-- Right-hand keypad drawer --}}
  @if($showKeypad)
    <div class="fixed inset-0 z-40">
      <div class="absolute inset-0 bg-black/40" wire:click="closeKeypad"></div>

      <div
        class="absolute inset-y-0 right-0 w-full max-w-md h-full overflow-y-auto bg-white p-6 shadow-xl dark:bg-zinc-900"
        x-data
        x-transition:enter="transform transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
      >
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
            End #{{ $selectedEnd }}, Arrow {{ $selectedArrow + 1 }}
          </h2>
          <button
            class="rounded-md p-2 text-zinc-500 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/10"
            wire:click="closeKeypad"
            aria-label="Close keypad"
          >✕</button>
        </div>

        <div class="mt-6 space-y-6">
          {{-- Keypad --}}
          <div>
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
              @foreach ($this->keypadKeys as $key)
                <button
                  wire:click="keypad('{{ $key }}')"
                  class="h-16 text-lg sm:h-12 sm:text-base rounded-lg inset-ring inset-ring-zinc-300 hover:bg-zinc-50 dark:inset-ring-zinc-700 dark:hover:bg-white/5 touch-manipulation select-none"
                  aria-label="Enter {{ $key }}"
                >
                  {{ $key }}
                </button>
              @endforeach
            </div>

            <div class="mt-5 flex items-center gap-3">
              <flux:button variant="ghost" size="sm" class="px-4 py-2" wire:click="clearCurrent">Clear</flux:button>
              {{-- IMPORTANT: finalizeEnd should redirect using the SAME kiosk gating logic server-side --}}
              <flux:button variant="primary" size="sm" class="px-4 py-2" wire:click="finalizeEnd">Done</flux:button>
            </div>
          </div>

          {{-- Current end preview --}}
          <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            @php($e = $score->ends->firstWhere('end_number', $selectedEnd))
            <div class="text-sm text-zinc-700 dark:text-zinc-300">
              <div class="mb-2 font-medium">Current end</div>
              <div class="flex flex-wrap gap-2">
                @for ($i = 0; $i < $score->arrows_per_end; $i++)
                  @php($v = $e?->scores[$i] ?? null)
                  <button
                    type="button"
                    wire:click="startEntry({{ $selectedEnd }}, {{ $i }})"
                    class="inline-flex h-12 w-12 sm:h-9 sm:w-9 items-center justify-center rounded-md inset-ring inset-ring-zinc-200 dark:inset-ring-white/10 hover:bg-zinc-50 dark:hover:bg:white/5
                           @if($selectedArrow === $i) ring-2 ring-indigo-500 @endif"
                    aria-pressed="{{ $selectedArrow === $i ? 'true' : 'false' }}"
                    aria-label="Select arrow {{ $i + 1 }}"
                  >
                    @if ($v === null)
                      <span class="opacity-40">·</span>
                    @elseif ((int)$v === 0)
                      M
                    @elseif ($scoringSystem === '10' && (int)$v === (int)($score->x_value ?? 10))
                      X
                    @else
                      {{ $v }}
                    @endif
                  </button>
                @endfor
              </div>
              <div class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                End total: {{ $e?->end_score ?? 0 }} • X: {{ $e?->x_count ?? 0 }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif
</section>
