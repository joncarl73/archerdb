{{-- resources/views/public/cls/scoring-summary.blade.php --}}
<x-layouts.public :league="null">
  @php
    /** @var \App\Models\EventScore|null $score */
    $score       = $score ?? null;
    $event       = $score?->event ?? null;
    $participant = $score?->participant ?? null;

    $name = $participant
      ? trim(($participant->first_name ?? '').' '.($participant->last_name ?? ''))
      : null;
  @endphp

  <section class="w-full">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
      <h1 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
        Scoring summary
      </h1>

      <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-400">
        This is the summary of your scores for this event session.
      </p>

      <div class="mt-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
          @if ($event)
            <div>
              <dt class="text-zinc-500 dark:text-zinc-400">Event</dt>
              <dd class="font-medium text-zinc-900 dark:text-zinc-100">
                {{ $event->title }}
              </dd>
            </div>
          @endif

          @if ($name)
            <div>
              <dt class="text-zinc-500 dark:text-zinc-400">Archer</dt>
              <dd class="font-medium text-zinc-900 dark:text-zinc-100">
                {{ $name }}
              </dd>
            </div>
          @endif

          <div>
            <dt class="text-zinc-500 dark:text-zinc-400">Ends</dt>
            <dd class="font-medium text-zinc-900 dark:text-zinc-100">
              {{ $score?->ends_planned ?? '—' }}
            </dd>
          </div>

          <div>
            <dt class="text-zinc-500 dark:text-zinc-400">Total score</dt>
            <dd class="font-medium text-zinc-900 dark:text-zinc-100">
              {{ $score?->total_score ?? 0 }}
            </dd>
          </div>

          <div>
            <dt class="text-zinc-500 dark:text-zinc-400">X / bonus count</dt>
            <dd class="font-medium text-zinc-900 dark:text-zinc-100">
              {{ $score?->x_count ?? 0 }}
            </dd>
          </div>
        </dl>
      </div>

      @if ($score)
        <div class="mt-6 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
              <thead class="bg-zinc-50 dark:bg-zinc-900/60">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-zinc-500 uppercase tracking-wide">
                    End
                  </th>
                  <th class="px-4 py-2 text-right text-xs font-semibold text-zinc-500 uppercase tracking-wide">
                    Score
                  </th>
                  <th class="px-4 py-2 text-right text-xs font-semibold text-zinc-500 uppercase tracking-wide">
                    X / bonus
                  </th>
                </tr>
              </thead>

              <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($score->ends()->orderBy('end_number')->get() as $end)
                  <tr class="bg-white dark:bg-zinc-950/60">
                    <td class="whitespace-nowrap px-4 py-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                      {{ $end->end_number }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-2 text-right text-sm text-zinc-900 dark:text-zinc-100">
                      {{ $end->end_score }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-2 text-right text-sm text-zinc-900 dark:text-zinc-100">
                      {{ $end->x_count }}
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <div class="flex items-center justify-end border-t border-zinc-200 px-4 py-3 text-sm dark:border-zinc-800">
            <a
              href="{{ route('public.cls.participants', [$kind, $owner->public_uuid]) }}"
              class="text-sm text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
            >
              Back to check-in
            </a>
          </div>
        </div>
      @endif
    </div>
  </section>
</x-layouts.public>
