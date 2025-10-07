<x-layouts.public :title="$league->title.' — Scoring'">
  <div class="mx-auto w-full max-w-5xl space-y-6">

    {{-- Header with kiosk return + optional assignment details --}}
    @php
      $showKioskControls = !empty($kioskMode) && !empty($kioskReturnTo);

      // Safely pull participant and (optionally eager-loaded) line time
      $participant = $score->participant ?? null;

      $assignedLineTimeModel = null;
      if ($participant instanceof \Illuminate\Database\Eloquent\Model) {
          // Use eager-loaded relation if present to avoid extra queries.
          $assignedLineTimeModel = $participant->relationLoaded('assignedLineTime')
              ? $participant->assignedLineTime
              : null;
      }
      $assignedLine = optional($assignedLineTimeModel);
    @endphp

    <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-white/10 dark:bg-neutral-900">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
            {{ $league->title }} — Score #{{ $score->id }}
          </h1>

          <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
            @if($participant)
              Archer: {{ trim(($participant->first_name ?? '').' '.($participant->last_name ?? '')) }}
            @endif

            @if($assignedLineTimeModel)
              • Line:
              {{ $assignedLine->label
                  ?? optional($assignedLine->starts_at)->timezone(config('app.timezone'))?->format('Y-m-d H:i') }}
            @endif

            @if(!empty($participant?->assigned_lane_number))
              • Lane: {{ $participant->assigned_lane_number }}{{ $participant->assigned_lane_slot }}
            @endif
          </div>
        </div>

        @if($showKioskControls)
          <div class="flex items-center gap-2">
            <a href="{{ $kioskReturnTo }}"
               class="inline-flex items-center rounded-lg bg-neutral-100 px-3 py-1.5 text-sm font-medium text-neutral-900 ring-1 ring-inset ring-neutral-300 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-100 dark:ring-white/10 dark:hover:bg-neutral-700">
              ← Back to kiosk
            </a>
          </div>
        @endif
      </div>
    </div>

    {{-- Livewire scoring component --}}
    {{-- If your component name differs, adjust the name/props below --}}
    <livewire:public.scoring.record
      :uuid="$uuid"
      :league="$league"
      :score="$score"
      :kiosk-mode="$kioskMode"
      :kiosk-return-to="$kioskReturnTo"
    />

  </div>
</x-layouts.public>
