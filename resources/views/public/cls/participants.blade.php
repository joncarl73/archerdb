{{-- resources/views/public/cls/participants.blade.php --}}
<x-layouts.public :league="null">
  @php
    $isEvent          = ($kind === 'event');
    $lineTimes        = $lineTimes ?? collect();
    $selectedLineTime = $selectedLineTime ?? null;
    $participants     = $participants ?? collect();
  @endphp

  <section class="w-full">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
      {{-- Title --}}
      <h1 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
        Check in — {{ $owner->title ?? $owner->name ?? ucfirst($kind) }}
      </h1>

      {{-- Description --}}
      @if ($isEvent)
        <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-400">
          For events, start by choosing your line time, then pick the archer. Lanes are assigned by the event
          organizer, and you’ll go straight into scoring afterwards.
        </p>
      @else
        <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-400">
          Select the archer you want to check in. Archers who have already checked in will not appear in this list.
          You’ll confirm lane and start scoring on the next steps.
        </p>
      @endif

      {{-- Card --}}
      <div class="mt-6 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="p-6">
          @if ($isEvent)
            {{-- EVENT FLOW: line time -> archer --}}
            @if (! $selectedLineTime)
              {{-- Step 1: choose line time --}}
              <form
                method="GET"
                action="{{ route('public.cls.participants', [$kind, $owner->public_uuid]) }}"
                class="space-y-4"
              >
                <div>
                  <label class="block text-sm font-medium text-zinc-900 dark:text-zinc-100">
                    Line time
                  </label>
                  <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    Choose the line time you’re assigned to. Only archers on that line will be shown next.
                  </p>

                  <flux:select
                    name="line_time_id"
                    required
                    class="mt-2 w-full"
                  >
                    <option value="">Select a line time…</option>
                    @foreach ($lineTimes as $lt)
                      @php
                        $date  = optional($lt->line_date)->format('D M j, Y');
                        $start = $lt->start_time ? \Carbon\Carbon::parse($lt->start_time)->format('g:i A') : null;
                        $end   = $lt->end_time   ? \Carbon\Carbon::parse($lt->end_time)->format('g:i A') : null;
                      @endphp
                      <option value="{{ $lt->id }}">
                        {{ $date }} — {{ $start }}{{ $end ? ' to '.$end : '' }}
                      </option>
                    @endforeach
                  </flux:select>
                </div>

                <div class="flex justify-end">
                  <flux:button type="submit" variant="primary">
                    Continue
                  </flux:button>
                </div>
              </form>
            @else
              {{-- Step 2: choose archer for selected line time --}}
              <div class="mb-4 text-xs text-zinc-500 dark:text-zinc-400">
                Line time:
                <span class="font-medium text-zinc-900 dark:text-zinc-100">
                  {{ optional($selectedLineTime->line_date)->format('D M j, Y') }}
                  @if ($selectedLineTime->start_time)
                    • {{ \Carbon\Carbon::parse($selectedLineTime->start_time)->format('g:i A') }}
                    @if ($selectedLineTime->end_time)
                      – {{ \Carbon\Carbon::parse($selectedLineTime->end_time)->format('g:i A') }}
                    @endif
                  @endif
                </span>
              </div>

              @if ($participants->isEmpty())
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                  No archers are assigned to this line time yet.
                </p>

                <div class="mt-4">
                  <a
                    href="{{ route('public.cls.participants', [$kind, $owner->public_uuid]) }}"
                    class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                  >
                    ← Choose a different line time
                  </a>
                </div>
              @else
                <form
                  method="POST"
                  action="{{ route('public.cls.participants.submit', [$kind, $owner->public_uuid]) }}"
                  class="space-y-4"
                >
                  @csrf
                  <input type="hidden" name="line_time_id" value="{{ $selectedLineTime->id }}">

                  {{-- Archer dropdown --}}
                  <div>
                    <label class="block text-sm font-medium text-zinc-900 dark:text-zinc-100">
                      Archer
                    </label>
                    <flux:select
                      name="participant_id"
                      required
                      class="mt-2 w-full"
                    >
                      @foreach ($participants as $p)
                        @php
                          $label = trim(($p->first_name ?? '').' '.($p->last_name ?? ''));
                          if (! $label) {
                              $label = $p->email ?: '#'.$p->id;
                          }

                          if (!empty($p->lane_number)) {
                              $label .= ' (Lane ' . $p->lane_number;
                              if (!empty($p->lane_slot)) {
                                  $label .= ' • ' . strtoupper($p->lane_slot);
                              }
                              $label .= ')';
                          }
                        @endphp

                        <option value="{{ $p->id }}">
                          {{ $label }}
                        </option>
                      @endforeach
                    </flux:select>
                    @error('participant_id')
                      <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ $message }}
                      </p>
                    @enderror
                  </div>

                  <div class="mt-4 flex items-center justify-between">
                    <a
                      href="{{ route('public.cls.participants', [$kind, $owner->public_uuid]) }}"
                      class="text-sm text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                    >
                      ← Change line time
                    </a>

                    <flux:button type="submit" variant="primary">
                      Continue
                    </flux:button>
                  </div>
                </form>
              @endif
            @endif
          @else
            {{-- LEAGUE FLOW: archer selection via dropdown --}}
            @if ($participants->isEmpty())
              <p class="text-sm text-zinc-600 dark:text-zinc-400">
                No participants are available to check in for this {{ $kind }}. Everyone may already be checked in.
              </p>
            @else
              <form
                method="POST"
                action="{{ route('public.cls.participants.submit', [$kind, $owner->public_uuid]) }}"
                class="space-y-4"
              >
                @csrf

                <div>
                  <label class="block text-sm font-medium text-zinc-900 dark:text-zinc-100">
                    Archer
                  </label>
                  <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    Choose the archer you want to check in. Archers who have already checked in will not be shown.
                  </p>

                  <flux:select
                    name="participant_id"
                    required
                    class="mt-2 w-full"
                  >
                    <option value="">Select archer…</option>
                    @foreach ($participants as $p)
                      @php
                        $name = trim(($p->first_name ?? '').' '.($p->last_name ?? ''));
                        $label = $name ?: ($p->email ?: '#'.$p->id);
                      @endphp
                      <option
                        value="{{ $p->id }}"
                        @selected(old('participant_id') == $p->id)
                      >
                        {{ $label }}
                      </option>
                    @endforeach
                  </flux:select>
                  @error('participant_id')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                      {{ $message }}
                    </p>
                  @enderror
                </div>

                <div class="mt-4 flex items-center justify-between">
                  <a
                    href="{{ url()->previous() }}"
                    class="text-sm text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                  >
                    ← Back
                  </a>

                  <flux:button type="submit" variant="primary">
                    Continue
                  </flux:button>
                </div>
              </form>
            @endif
          @endif
        </div>
      </div>
    </div>
  </section>
</x-layouts.public>
