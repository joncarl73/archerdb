{{-- resources/views/public/kiosk/landing.blade.php --}}
<x-layouts.public :kiosk="true">
  <section class="w-full py-6">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">

      {{-- Header --}}
      <div class="flex items-start justify-between gap-4">
        <div>
          <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">
            Kiosk Scoring
          </h1>

          <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
            {{ $league->title }}
            • Week {{ $week->week_number }}
            @if(!empty($week->date))
              • {{ optional($week->date)->format('Y-m-d') }}
            @endif

            @php
              $assigned = is_array($session->participants)
                ? $session->participants
                : (json_decode((string) $session->participants, true) ?: []);

              $assignedCount  = count(array_unique(array_map('intval', $assigned)));
              $checkedInCount = $checkins->count();
            @endphp

            • {{ $checkedInCount }} / {{ $assignedCount }} assigned archers checked in
          </p>
        </div>

        <div class="text-right text-xs text-zinc-500 dark:text-zinc-400">
          Token:
          <span class="font-mono">
            {{ \Illuminate\Support\Str::limit($session->token, 8, '…') }}
          </span><br>
          League ID: {{ $league->id }}
        </div>
      </div>

      {{-- Grid of lanes / archers --}}
      <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($checkins as $checkin)
          @php
            $lane = (string) ($checkin->lane_number ?? '');
            if ($lane !== '' && $checkin->lane_slot && $checkin->lane_slot !== 'single') {
              $lane .= $checkin->lane_slot;
            }

            // Prefer the denormalized fields saved on the check-in (fast + consistent)
            $name  = trim((string) ($checkin->participant_name ?? ''));

            // Fallback if participant_name is empty (won’t break if relation not loaded)
            if ($name === '' && $checkin->relationLoaded('participant') && $checkin->participant) {
              $name = trim(($checkin->participant->first_name ?? '') . ' ' . ($checkin->participant->last_name ?? ''));
            }

            $email = (string) ($checkin->participant_email ?? '');
          @endphp

          <a href="{{ route('kiosk.score', ['token' => $session->token, 'checkin' => $checkin->id]) }}"
             class="group flex flex-col justify-between rounded-2xl border border-zinc-200 bg-white p-4 text-left shadow-sm
                    transition hover:-translate-y-0.5 hover:shadow-md
                    dark:border-zinc-700 dark:bg-zinc-900">

            <div class="flex items-baseline justify-between gap-2">
              <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                {{ $lane !== '' ? 'Lane '.$lane : 'Lane —' }}
              </div>
              <div class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400">
                Tap to start scoring →
              </div>
            </div>

            <div class="mt-2 text-base font-semibold text-zinc-900 dark:text-zinc-100">
              {{ $name !== '' ? $name : 'Unknown archer' }}
            </div>

            @if($email !== '')
              <div class="mt-1 text-xs text-zinc-600 dark:text-zinc-400">
                {{ $email }}
              </div>
            @endif
          </a>
        @empty
          <div class="col-span-full rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 p-6 text-center text-sm text-zinc-500
                      dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
            No assigned archers are checked in for Week {{ $week->week_number }} yet.
          </div>
        @endforelse
      </div>

      {{-- Footer hint --}}
      <div class="mt-6 text-center text-xs text-zinc-500 dark:text-zinc-400">
        Hand this tablet to the listed archers. Each archer taps their name to open the scoring screen;
        after finishing an end, the tablet returns here automatically.
      </div>
    </div>
  </section>
</x-layouts.public>
