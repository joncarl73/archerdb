{{-- resources/views/public/events/participants.blade.php --}}
<x-layouts.public :event="$event">
  <section class="w-full">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
      {{-- Title --}}
      <h1 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
        Check in — {{ $event->title ?? $event->name ?? 'Event' }}
      </h1>

      {{-- Scoring mode badge (read-only) --}}
      @php
        $mode = $event->scoring_mode; // 'personal' | 'personal_device' | 'kiosk'
        $modeLabel = in_array($mode, ['personal', 'personal_device'], true) ? 'Personal device' : 'Kiosk';
      @endphp
      <div class="mt-2">
        <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-700
                     ring-1 ring-inset ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-200 dark:ring-zinc-700">
          <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M3 4.5A1.5 1.5 0 0 1 4.5 3h11A1.5 1.5 0 0 1 17 4.5v11a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 3 15.5v-11Zm2 1.5a1 1 0 0 0-1 1v7a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1v-7a1 1 0 0 0-1-1H5Z" clip-rule="evenodd"/>
          </svg>
          {{ $modeLabel }}
        </span>
      </div>
      <p class="mt-2 text-xs text-zinc-600 dark:text-zinc-400">
        After check-in, you’ll be sent to the {{ strtolower($modeLabel) }} page automatically.
      </p>

      {{-- Empty-state OR form card --}}
      @if ($participants->isEmpty())
        <div class="mt-6 overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 text-sm text-zinc-700 shadow-sm
                    dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
          No participants are available for this event. Please contact the organizer to be added.
          <div class="mt-4">
            <flux:button variant="ghost" onclick="window.location='{{ route('public.event.landing', ['uuid' => $event->public_uuid]) }}'">
              Back
            </flux:button>
          </div>
        </div>
      @else
        <div class="mt-6 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
          <form
            x-data="{ selectedId: '' }"
            method="POST"
            action="{{ route('public.event.checkin.participants.submit', ['uuid' => $event->public_uuid]) }}"
            class="p-6"
          >
            @csrf

{{-- Flux select --}}
<div class="max-w-xl">
  <flux:label for="participant_id" class="text-zinc-900 dark:text-zinc-200">
    Participant
  </flux:label>

  <flux:select id="participant_id" name="participant_id" class="w-full" required
               x-model="selectedId" autofocus aria-describedby="participants-help">
    {{-- NOTE: avoid bare "selected" to prevent Blade constant parse --}}
    <option value="" disabled x-bind:selected="!selectedId">Select a participant…</option>

    @foreach ($participants as $p)
      @php
        $hasNames = !empty($p->last_name) || !empty($p->first_name);
        $name = $hasNames
          ? trim(implode(', ', array_filter([$p->last_name, $p->first_name])))
          : ($p->display_name ?? '');
        $label = trim($name . (!empty($p->email) ? " — {$p->email}" : ''));
      @endphp
      <option value="{{ (int) $p->id }}">{{ $label !== '' ? $label : 'Unknown' }}</option>
    @endforeach
  </flux:select>

  @error('participant_id')
    <p class="mt-2 text-sm text-red-600 dark:text-red-500">{{ $message }}</p>
  @enderror

  <p id="participants-help" class="mt-2 text-xs text-zinc-600 dark:text-zinc-400">
    {{ $participants->count() }} participant{{ $participants->count() === 1 ? '' : 's' }}
  </p>
</div>

{{-- Footer --}}
<div class="mt-6 flex items-center justify-end gap-3">
  <flux:button type="button" variant="ghost"
    onclick="window.location='{{ route('public.event.landing', ['uuid' => $event->public_uuid]) }}'">
    Cancel
  </flux:button>

  {{-- Use x-bind:* instead of ":" to avoid Blade trying to interpret identifiers --}}
  <flux:button
    type="submit"
    variant="primary"
    x-bind:disabled="!selectedId"
    x-bind:class="!selectedId ? 'opacity-60 cursor-not-allowed' : ''"
  >
    Continue
  </flux:button>
</div>

          </form>
        </div>
      @endif
    </div>
  </section>
</x-layouts.public>
