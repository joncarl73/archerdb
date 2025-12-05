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
        {{-- League: week + lane selection for CLS, with taken lanes hidden per week --}}
        <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-400">
          Select your week and lane assignment, then continue to scoring.
        </p>

        <div
          class="mt-4 rounded-xl border border-zinc-200 bg-white p-4 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-900"
          x-data="{
            selectedWeek: '{{ old('week_number') }}',
            takenByWeek: @js($takenLanesByWeek ?? []),
            isLaneTaken(week, code) {
              if (!week || !this.takenByWeek[week]) return false;
              return this.takenByWeek[week].includes(code);
            }
          }"
        >
          @php
            $name = trim(($participant->first_name ?? '').' '.($participant->last_name ?? ''));
          @endphp

          <dl class="space-y-2 mb-4">
            <div class="flex justify-between">
              <dt class="text-zinc-500">Archer</dt>
              <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $name ?: 'Unknown name' }}</dd>
            </div>
          </dl>

          <form
            method="POST"
            action="{{ route('public.cls.lane.submit', [$kind, $owner->public_uuid, $participant->id]) }}"
            class="space-y-5"
          >
            @csrf

            {{-- Week select --}}
            <div>
              <label class="block text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-1">
                Week
              </label>
              <flux:select
                name="week_number"
                class="w-full"
                required
                x-model="selectedWeek"
              >
                <option value="">Select week…</option>
                @foreach ($weeks as $week)
                  <option
                    value="{{ $week->week_number }}"
                    @selected(old('week_number') == $week->week_number)
                  >
                    Week {{ $week->week_number }}
                    @if ($week->starts_on)
                      — {{ $week->starts_on->format('M j, Y') }}
                    @endif
                  </option>
                @endforeach
              </flux:select>
              @error('week_number')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                  {{ $message }}
                </p>
              @enderror
            </div>

            {{-- Lane select --}}
            <div>
              <label class="block text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-1">
                Lane
              </label>
              <flux:select
                name="lane_code"
                class="w-full"
                required
              >
                <option value="">Select lane…</option>
                @foreach ($laneOptions as $code => $label)
                  <option
                    value="{{ $code }}"
                    x-show="!isLaneTaken(selectedWeek, '{{ $code }}')"
                    @selected(old('lane_code') === $code)
                  >
                    {{ $label }}
                  </option>
                @endforeach
              </flux:select>
              @error('lane_code')
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
                &larr; Back
              </a>

              <flux:button type="submit" variant="primary">
                Continue to scoring
              </flux:button>
            </div>
          </form>
        </div>
      @endif
    </div>
  </section>
</x-layouts.public>
