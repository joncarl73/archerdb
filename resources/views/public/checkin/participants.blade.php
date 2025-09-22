<x-layouts.public :league="$league">
  <section class="w-full">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
      {{-- Title --}}
      <h1 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
        Check in — {{ $league->title }}
      </h1>
      <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-400">
        Select the archer you want to check in. You’ll pick week and lane on the next step.
      </p>

      {{-- Card (theme-aware) --}}
      <div class="mt-6 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <form method="POST" action="{{ route('public.checkin.participants.submit', $league->public_uuid) }}" class="p-6">
          @csrf

          {{-- Dropdown (Flux select) --}}
          <div class="max-w-xl">
            <flux:label for="participant_id" class="text-zinc-900 dark:text-zinc-200">Participant</flux:label>
            <flux:select id="participant_id" name="participant_id" class="w-full" required>
              <option value="" disabled selected>Select a participant…</option>
              @foreach ($participants as $p)
                @php($label = trim("{$p->last_name}, {$p->first_name}".($p->email ? " — {$p->email}" : "")))
                <option value="{{ $p->id }}">{{ $label }}</option>
              @endforeach
            </flux:select>

            @error('participant_id')
              <p class="mt-2 text-sm text-red-600 dark:text-red-500">{{ $message }}</p>
            @enderror

            <p class="mt-2 text-xs text-zinc-600 dark:text-zinc-400">
              {{ $participants->count() }} participant{{ $participants->count() === 1 ? '' : 's' }}
            </p>
          </div>

          {{-- Footer actions (Flux buttons) --}}
          <div class="mt-6 flex items-center justify-end gap-3">
            <flux:button type="button" variant="ghost" onclick="window.location='{{ route('home') }}'">
              Cancel
            </flux:button>
            <flux:button type="submit" variant="primary">
              Continue
            </flux:button>
          </div>
        </form>
      </div>
    </div>
  </section>
</x-layouts.public>
