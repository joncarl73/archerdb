<x-layouts.public :league="$league">
  <section class="w-full">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
      {{-- Title --}}
      <h1 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
        {{ $league->title }} — Check-in
      </h1>
      <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-400">
        {{ $p->first_name }} {{ $p->last_name }}, choose the week you’re shooting and your lane.
      </p>

      {{-- Card --}}
      <div class="mt-6 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <form method="POST" action="{{ route('public.checkin.details.submit', [$league->public_uuid, $p->id]) }}" class="p-6 space-y-6">
          @csrf

          {{-- League week --}}
          <div class="max-w-xl">
            <flux:label for="week_number" class="text-zinc-900 dark:text-zinc-200">League week</flux:label>
            <flux:select id="week_number" name="week_number" class="w-full" required>
              <option value="" disabled selected>Select week…</option>
              @foreach($weeks as $w)
                <option value="{{ $w->week_number }}">
                  Week {{ $w->week_number }} — {{ \Illuminate\Support\Carbon::parse($w->date)->format('M j, Y') }}
                </option>
              @endforeach
            </flux:select>
            @error('week_number')
              <p class="mt-2 text-sm text-red-600 dark:text-red-500">{{ $message }}</p>
            @enderror
          </div>

          {{-- Lane --}}
          <div class="max-w-xl">
            <flux:label for="lane" class="text-zinc-900 dark:text-zinc-200">Lane</flux:label>
            <flux:select id="lane" name="lane" class="w-full" required>
              <option value="" disabled selected>{{ __('Select…') }}</option>
              @foreach($laneOptions as $opt)
                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
              @endforeach
            </flux:select>
            @error('lane')
              <p class="mt-2 text-sm text-red-600 dark:text-red-500">{{ $message }}</p>
            @enderror

            <p class="mt-2 text-xs text-zinc-600 dark:text-zinc-400">
              Lanes reflect the league’s configured breakdown:
              <span class="font-medium">Single</span> (1 per lane),
              <span class="font-medium">A/B</span> (2 per lane),
              or <span class="font-medium">A/B/C/D</span> (4 per lane).
            </p>
          </div>

          {{-- Actions --}}
          <div class="flex items-center justify-end gap-3">
            <flux:button type="button" variant="ghost" onclick="window.location='{{ route('public.checkin.participants', $league->public_uuid) }}'">
              Cancel
            </flux:button>
            <flux:button type="submit" variant="primary">
              Check in
            </flux:button>
          </div>
        </form>
      </div>
    </div>
  </section>
</x-layouts.public>
