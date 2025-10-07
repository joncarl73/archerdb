<x-layouts.public :title="'Kiosk — '.$league->title">
  <div class="mx-auto w-full max-w-5xl space-y-6">

    {{-- Header --}}
    <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-white/10 dark:bg-neutral-900">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
            {{ $league->title }} — Week {{ $week->week_number }}
          </h1>
          <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
            {{ \Illuminate\Support\Carbon::parse($week->date)->toFormattedDateString() }}
            @if($session->event_line_time_id && optional($session->lineTime)->starts_at)
              • Line: {{ $session->lineTime->label ?? $session->lineTime->starts_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
            @endif
          </p>
        </div>
        <div class="text-sm text-neutral-600 dark:text-neutral-400">
          Session: <code class="text-xs">{{ $session->token }}</code>
        </div>
      </div>
    </div>

    {{-- Assigned names summary (if kiosk is restricted to certain participants) --}}
    @if(!empty($assignedNames))
      <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-white/10 dark:bg-neutral-900">
        <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Assigned to this kiosk</h2>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
          {{ implode(', ', $assignedNames) }}
        </p>
      </div>
    @endif

    {{-- Lane board --}}
    <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-white/10 dark:bg-neutral-900">
      <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Lanes</h2>
      <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
        @forelse($checkins as $c)
          <div class="rounded-md border border-neutral-200 p-3 dark:border-white/10">
            <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
              Lane {{ $c->lane_number }}{{ $c->lane_slot !== 'single' ? $c->lane_slot : '' }}
            </div>
            <div class="mt-1 text-sm text-neutral-700 dark:text-neutral-300">
              {{ $c->participant?->first_name }} {{ $c->participant?->last_name }}
            </div>
            <div class="mt-2">
              <a href="{{ route('kiosk.score', ['token' => $session->token, 'checkin' => $c->id]) }}"
                class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                Start scoring
              </a>
            </div>
          </div>
        @empty
          <div class="text-sm text-neutral-600 dark:text-neutral-400">No check-ins yet.</div>
        @endforelse
      </div>
    </div>

  </div>
</x-layouts.public>
