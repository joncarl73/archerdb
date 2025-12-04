{{-- resources/views/public/cls/lane.blade.php --}}
<x-layouts.public :league="null">
  <section class="w-full">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
      <h1 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
        Lane &amp; line time
      </h1>

      @if ($kind === 'event')
        <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-400">
          For events, lanes are assigned by the organizer. Please review your assignment and continue to scoring.
        </p>

        <div class="mt-4 rounded-xl border border-zinc-200 bg-white p-4 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
          @php
            $name = trim(($participant->first_name ?? '').' '.($participant->last_name ?? ''));
          @endphp

          <dl class="space-y-2">
            <div class="flex justify-between">
              <dt class="text-zinc-500">Archer</dt>
              <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $name ?: 'Unknown name' }}</dd>
            </div>

            @if ($lineTime)
              <div class="flex justify-between">
                <dt class="text-zinc-500">Line time</dt>
                <dd class="font-medium text-zinc-900 dark:text-zinc-100">
                  {{ $lineTime->range_display ?? "{$lineTime->line_date} {$lineTime->start_time}" }}
                </dd>
              </div>
            @endif

            <div class="flex justify-between">
              <dt class="text-zinc-500">Lane</dt>
              <dd class="font-medium text-zinc-900 dark:text-zinc-100">
                {{ $participant->assigned_lane ?? 'TBD' }}
              </dd>
            </div>

            <div class="flex justify-between">
              <dt class="text-zinc-500">Slot</dt>
              <dd class="font-medium text-zinc-900 dark:text-zinc-100">
                {{ $participant->assigned_slot ?? 'TBD' }}
              </dd>
            </div>
          </dl>
        </div>

        <form
          method="POST"
          action="{{ route('public.cls.lane.submit', [$kind, $owner->public_uuid, $participant->id]) }}"
          class="mt-6 flex items-center justify-between"
        >
          @csrf

          <a
            href="{{ route('public.cls.participants', [$kind, $owner->public_uuid]) }}"
            class="text-sm text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
          >
            &larr; Back
          </a>

          <flux:button type="submit" variant="primary">
            Continue to scoring
          </flux:button>
        </form>
      @else
        {{-- League lane logic will be wired into CLS in a later step --}}
        <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-400">
          League lane selection for CLS will be implemented in a later step.
        </p>
      @endif
    </div>
  </section>
</x-layouts.public>
