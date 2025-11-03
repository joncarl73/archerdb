{{-- Pick participant (events) --}}
<x-layouts.public title="Event Check-in">
  <div class="mx-auto max-w-3xl py-8">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
      {{ $event->title }} — Check-in
    </h1>
    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
      Choose your name below to begin. Lanes are assigned by event staff.
    </p>

    <form class="mt-6 space-y-4" method="POST" action="{{ route('public.event.checkin.participants.submit', ['uuid' => $event->public_uuid]) }}">
      @csrf

      @if ($errors->any())
        <div class="rounded-md bg-rose-50 p-3 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
          {{ $errors->first() }}
        </div>
      @endif

      <div>
        <label class="block text-sm font-medium text-gray-800 dark:text-gray-200">Participant</label>
        <select name="participant_id"
                class="mt-2 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-xs
                       focus:border-indigo-500 focus:ring-2 focus:ring-indigo-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
          <option value="">— Select your name —</option>
          @foreach ($participants as $p)
            <option value="{{ $p['id'] }}">{{ $p['name'] }} @if(!empty($p['email'])) ({{ $p['email'] }}) @endif</option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-800 dark:text-gray-200">Device mode</label>
        <div class="mt-2 flex gap-4">
          <label class="inline-flex items-center gap-2">
            <input type="radio" name="mode" value="personal" class="h-4 w-4" checked>
            <span class="text-sm">Personal device</span>
          </label>
          <label class="inline-flex items-center gap-2">
            <input type="radio" name="mode" value="kiosk" class="h-4 w-4">
            <span class="text-sm">Kiosk tablet</span>
          </label>
        </div>
      </div>

      <div class="pt-2">
        <button type="submit"
                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500
                       focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600
                       dark:bg-indigo-500 dark:hover:bg-indigo-400">
          Continue
        </button>
      </div>
    </form>

    @if (empty($participants) || count($participants) === 0)
      <p class="mt-6 text-xs text-gray-500 dark:text-gray-400">
        No participants are listed yet for this event. Please contact event staff.
      </p>
    @endif
  </div>
</x-layouts.public>
