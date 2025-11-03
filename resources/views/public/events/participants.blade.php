{{-- resources/views/public/events/participants.blade.php --}}
<x-layouts.public :title="$event->title . ' — Check-In'">
  <div class="mx-auto max-w-3xl px-4 py-10">
    <header class="mb-6">
      <h1 class="text-2xl font-semibold">{{ $event->title }}</h1>
      <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">
        {{ $event->location ?: '—' }}
        @if($event->starts_on)
          • {{ \Illuminate\Support\Carbon::parse($event->starts_on)->toFormattedDateString() }}
        @endif
      </p>
    </header>

    <div class="rounded-xl border border-gray-200 p-4 shadow-sm dark:border-white/10">
      <h2 class="text-lg font-medium">Find your name</h2>
      <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">
        Tap your name and continue. Lanes are assigned by staff.
      </p>

      @if ($errors->any())
        <div class="mt-3 rounded-md bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
          {{ $errors->first() }}
        </div>
      @endif

      @if($participants->isEmpty())
        <div class="mt-4 rounded-md bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
          We don’t have a participant roster for this event yet. Please check with staff.
        </div>
      @else
        <form method="POST"
              action="{{ route('public.event.checkin.participants.submit', ['uuid' => $event->public_uuid]) }}"
              class="mt-4">
          @csrf

          <div x-data="{ q: '' }">
            <input
              x-model="q"
              type="search"
              placeholder="Search by name…"
              class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-xs
                     focus:border-indigo-500 focus:ring-2 focus:ring-indigo-600
                     dark:border-white/10 dark:bg-white/5 dark:text-zinc-100 dark:focus:border-indigo-400 dark:focus:ring-indigo-400" />

            <div class="mt-3 max-h-96 overflow-auto rounded-md border border-gray-200 dark:border-white/10">
              @foreach($participants as $p)
                @php($needle = \Illuminate\Support\Str::lower($p['name']))
                <label x-show="q === '' || '{{ $needle }}'.includes(q.toLowerCase())"
                       class="flex cursor-pointer items-center gap-3 border-b border-gray-100 p-3 last:border-0
                              hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5">
                  <input type="radio" name="participant_id" value="{{ $p['id'] }}" class="size-4">
                  <div class="min-w-0">
                    <div class="truncate text-sm font-medium">{{ $p['name'] }}</div>
                    @if(!empty($p['email']))
                      <div class="truncate text-xs text-gray-500 dark:text-zinc-400">{{ $p['email'] }}</div>
                    @endif
                  </div>
                </label>
              @endforeach
            </div>
          </div>

          {{-- device mode selector --}}
          <div class="mt-4 flex flex-wrap items-center gap-3">
            <label class="inline-flex items-center gap-2 text-sm">
              <input type="radio" name="mode" value="personal" class="size-4" checked>
              <span>Personal device</span>
            </label>
            <label class="inline-flex items-center gap-2 text-sm">
              <input type="radio" name="mode" value="kiosk" class="size-4">
              <span>Kiosk device</span>
            </label>
          </div>

          <div class="mt-4 flex items-center justify-between">
            <a href="{{ route('public.event.landing', ['uuid' => $event->public_uuid]) }}"
               class="rounded-md px-3 py-2 text-sm font-medium inset-ring inset-ring-gray-300 hover:bg-gray-50
                      dark:inset-ring-white/10 dark:hover:bg-white/10">Back</a>

            <button type="submit"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow
                           hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-600">
              Continue
            </button>
          </div>
        </form>
      @endif
    </div>

    <footer class="mt-8 text-center text-xs text-gray-500 dark:text-zinc-500">
      Powered by ArcherDB
    </footer>
  </div>

  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</x-layouts.public
