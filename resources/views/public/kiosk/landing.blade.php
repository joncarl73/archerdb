{{-- resources/views/public/kiosk/landing.blade.php --}}
<x-layouts.public :league="$league">
  <section class="w-full py-6">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">

      {{-- Header --}}
      <div class="flex items-start justify-between gap-4">
        <div>
          <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Kiosk Scoring</h1>
          <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
            Week {{ $week->week_number }} •
            @php
              // Normalize participants array in case casting isn't applied
              $assigned = is_array($session->participants)
                ? $session->participants
                : (json_decode((string) $session->participants, true) ?: []);
              $assignedCount = count($assigned);
            @endphp
            Assigned archers: <span class="font-medium">{{ $assignedCount }}</span>
          </p>

          {{-- Quick chips to preview who’s assigned (optional) --}}
          @if(isset($assignedNames) && count($assignedNames))
            <div class="mt-2 flex flex-wrap gap-1">
              @foreach ($assignedNames as $nm)
                <span class="rounded-md bg-zinc-100 px-2 py-0.5 text-xs text-zinc-700 dark:bg-white/10 dark:text-zinc-300">
                  {{ $nm }}
                </span>
              @endforeach
            </div>
          @endif
        </div>
        <div class="flex items-center gap-2">
          <flux:button as="a" variant="ghost" href="{{ request()->fullUrl() }}">Refresh</flux:button>
        </div>
      </div>

      {{-- Group assigned & checked-in archers by their actual lane (nice for on-site flow) --}}
      @php
        $byLane = collect($checkins)->groupBy(function($c) {
          $lane = (string) ($c->lane_number ?? '');
          if ($lane !== '' && ($c->lane_slot ?? null) && $c->lane_slot !== 'single') {
            $lane .= $c->lane_slot;
          }
          return $lane === '' ? '—' : $lane;
        })->sortKeys();
      @endphp

      <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($byLane as $laneCode => $laneCheckins)
          <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-3 flex items-center justify-between">
              <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">
                {{ $laneCode === '—' ? 'No lane set' : 'Lane '.$laneCode }}
              </div>
              <div class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ $laneCheckins->count() }} archer{{ $laneCheckins->count() === 1 ? '' : 's' }}
              </div>
            </div>

            <div class="space-y-3">
              @foreach ($laneCheckins as $ci)
                @php
                  $name = trim(($ci->participant->first_name ?? '').' '.($ci->participant->last_name ?? ''));
                @endphp

                <div class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950">
                  <div class="min-w-0">
                    <div class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $name ?: 'Unknown archer' }}</div>
                    @if ($ci->participant->email)
                      <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $ci->participant->email }}</div>
                    @endif
                  </div>

                  <div class="shrink-0">
                    <flux:button
                      as="a"
                      size="sm"
                      variant="primary"
                      href="{{ route('kiosk.score', [$session->token, $ci->id]) }}"
                    >
                      Score this end
                    </flux:button>
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        @empty
          <div class="col-span-full rounded-2xl border border-zinc-200 bg-white p-6 text-center text-sm text-zinc-500 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
            No assigned archers are checked in for Week {{ $week->week_number }}.
          </div>
        @endforelse
      </div>

      {{-- Footer hint --}}
      <div class="mt-6 text-center text-xs text-zinc-500 dark:text-zinc-400">
        Hand this tablet to the listed archers. Each archer taps their name to enter scores; after finishing an end, the tablet returns here automatically.
      </div>
    </div>
  </section>
</x-layouts.public>
