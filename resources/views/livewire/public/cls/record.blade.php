{{-- resources/views/livewire/public/cls/record.blade.php --}}
@php
  $eventTitle        = $this->eventTitle ?? null;
  $name              = $this->archerName ?? null;
  $kioskMode         = $this->kioskMode ?? false;
  $kioskReturnTo     = $this->kioskReturnTo ?? null;
  $showKioskControls = $kioskMode && !empty($kioskReturnTo);

  $scoringSystem = $this->scoringSystem ?? '10';
  $xValue        = $this->xValue;
  $maxScore      = $this->maxScore ?? 10;

  $ends = $this->displayEnds;

  // Totals for footer
  $completedEnds = 0;
  $totalScore    = 0;
  $totalX        = 0;

  foreach ($ends as $row) {
      if (!empty($row['has_any'])) {
          $completedEnds++;
      }
      $totalScore += (int) ($row['total'] ?? 0);
      $totalX     += (int) ($row['x_count'] ?? 0);
  }

  $plannedEnds = $this->endsPlanned ?? count($ends);
@endphp

<section class="w-full mb-5">
  {{-- Header --}}
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
      <div class="sm:flex-auto">
        <h1 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
          Event scoring
        </h1>

        <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-400">
          {{ $eventTitle ?? 'Event' }}
          @if ($name)
            • Archer: {{ $name }}
          @endif
          <br>
          {{ $this->arrowsPerEnd }} arrows/end • up to {{ $maxScore }} points/arrow
          @if(($xValue ?? 0) > $maxScore)
            (X={{ $xValue }})
          @endif
        </p>
      </div>

      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        @if($showKioskControls)
          {{-- Tablet mode + kiosk context => go back to lane board --}}
          <flux:button as="a" variant="primary" href="{{ $kioskReturnTo }}">
            Back to kiosk
          </flux:button>
        @else
          {{-- Personal-device flow: finish this score and go to summary --}}
          <flux:button type="button" variant="primary" wire:click="done">
            End scoring
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
            @foreach ($ends as $row)
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
                    style="grid-template-columns: repeat({{ $this->arrowsPerEnd }}, minmax(0,1fr));"
                  >
                    @for ($i = 0; $i < $this->arrowsPerEnd; $i++)
                      @php
                        $rawVal     = $scores[$i] ?? null;
                        $isSelected = ($this->selectedEnd === $endNumber && $this->selectedArrow === $i);

                        if ($rawVal === null) {
                            $cellLabel = null;
                        } elseif ((int)$rawVal === 0) {
                            $cellLabel = 'M';
                        } elseif ($scoringSystem === '10' && $xValue !== null && (int)$rawVal === (int)$xValue) {
                            // Match legacy league behavior: show X for any score equal to x_value
                            $cellLabel = 'X';
                        } else {
                            $cellLabel = $rawVal;
                        }
                      @endphp

                      <button
                        type="button"
                        wire:click="startEntry({{ $endNumber }}, {{ $i }})"
                        class="h-10 rounded-lg inset-ring inset-ring-zinc-300 hover:bg-zinc-50 dark:inset-ring-zinc-700 dark:hover:bg-white/5
                               @if($isSelected) ring-2 ring-indigo-500 @endif"
                      >
                        @if ($cellLabel === null)
                          <span class="opacity-40">·</span>
                        @else
                          {{ $cellLabel }}
                        @endif
                      </button>
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
                Ends completed: {{ $completedEnds }} / {{ $plannedEnds }}
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

  {{-- Right-hand keypad drawer --}}
  @if($this->showKeypad)
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
            End #{{ $this->selectedEnd }}, Arrow {{ $this->selectedArrow + 1 }}
          </h2>
          <button
            type="button"
            class="rounded-md p-2 text-zinc-500 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/10"
            wire:click="closeKeypad"
            aria-label="Close keypad"
          >✕</button>
        </div>

        <div class="mt-6 space-y-6">
          {{-- Keypad (league-style) --}}
          <div>
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
              @foreach ($this->keypadKeys as $key)
                <button
                  type="button"
                  wire:click="keypad('{{ $key }}')"
                  class="h-16 text-lg sm:h-12 sm:text-base rounded-lg inset-ring inset-ring-zinc-300 hover:bg-zinc-50 dark:inset-ring-zinc-700 dark:hover:bg-white/5 touch-manipulation select-none"
                  aria-label="Enter {{ $key }}"
                >
                  {{ $key }}
                </button>
              @endforeach
            </div>

            <div class="mt-5 flex items-center gap-3">
              <flux:button variant="ghost" size="sm" class="px-4 py-2" type="button" wire:click="clearCell">
                Clear
              </flux:button>
              <flux:button variant="primary" size="sm" class="px-4 py-2" type="button" wire:click="closeKeypad">
                Close
              </flux:button>
            </div>
          </div>

          {{-- Current end preview --}}
          @php
            $current       = collect($ends)->firstWhere('end_number', $this->selectedEnd);
            $currentScores = $current['scores'] ?? [];
            $currentTotal  = $current['total'] ?? 0;
            $currentX      = $current['x_count'] ?? 0;
          @endphp

          <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="text-sm text-zinc-700 dark:text-zinc-300">
              <div class="mb-2 font-medium">Current end</div>
              <div class="flex flex-wrap gap-2">
                @for ($i = 0; $i < $this->arrowsPerEnd; $i++)
                  @php
                    $rawVal     = $currentScores[$i] ?? null;
                    $isSelected = ($this->selectedArrow === $i);

                    if ($rawVal === null) {
                        $previewLabel = null;
                    } elseif ((int)$rawVal === 0) {
                        $previewLabel = 'M';
                    } elseif ($scoringSystem === '10' && $xValue !== null && (int)$rawVal === (int)$xValue) {
                        $previewLabel = 'X';
                    } else {
                        $previewLabel = $rawVal;
                    }
                  @endphp

                  <button
                    type="button"
                    wire:click="startEntry({{ $this->selectedEnd }}, {{ $i }})"
                    class="inline-flex h-12 w-12 sm:h-9 sm:w-9 items-center justify-center rounded-md inset-ring inset-ring-zinc-200 dark:inset-ring-white/10 hover:bg-zinc-50 dark:hover:bg-white/5
                           @if($isSelected) ring-2 ring-indigo-500 @endif"
                    aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                    aria-label="Select arrow {{ $i + 1 }}"
                  >
                    @if ($previewLabel === null)
                      <span class="opacity-40">·</span>
                    @else
                      {{ $previewLabel }}
                    @endif
                  </button>
                @endfor
              </div>
              <div class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                End total: {{ $currentTotal }} • X: {{ $currentX }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif
</section>
