<x-layouts.app :title="'Kiosk manager — '.$league->title">
  <div class="mx-auto w-full max-w-5xl space-y-6">
    <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-white/10 dark:bg-neutral-900">
      <h1 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Create kiosk session</h1>
      <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
        Choose the week, (optional) line time, and lanes to light up for this kiosk session.
      </p>

      {{-- flash --}}
      @if(session('ok'))
        <div class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-200 dark:ring-emerald-900/40">
          {{ session('ok') }}
          @if(session('token'))
            <div class="mt-1">
              Token: <code class="text-xs">{{ session('token') }}</code> —
              <a class="underline" href="{{ route('kiosk.landing', ['token' => session('token')]) }}">open kiosk</a>
            </div>
          @endif
        </div>
      @endif

      @if ($errors->any())
        <div class="mt-3 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-200 dark:bg-rose-900/20 dark:text-rose-200 dark:ring-rose-900/40">
          <div class="font-medium">Please fix the following:</div>
          <ul class="mt-1 list-disc pl-5">
            @foreach ($errors->all() as $err)
              <li>{{ $err }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form class="mt-4 space-y-5" method="POST" action="{{ route('manager.kiosk.store', $league) }}">
        @csrf

        {{-- Week --}}
        <div>
          <label class="block text-sm font-medium text-neutral-900 dark:text-neutral-100">Week</label>
          <select name="week_number" class="mt-1 w-full rounded-md border border-neutral-300 bg-white p-2 text-sm dark:border-white/10 dark:bg-neutral-900">
            @foreach($weeks as $w)
              <option value="{{ $w->week_number }}">Week {{ $w->week_number }} — {{ \Illuminate\Support\Carbon::parse($w->date)->toDateString() }}</option>
            @endforeach
          </select>
        </div>

        {{-- Line time (only if the linked Event has line times) --}}
        @php
          $event = $league->event;
          $lineTimes = $event ? $event->lineTimes()->orderBy('starts_at')->get() : collect();
        @endphp
        @if($lineTimes->count() > 0)
          <div>
            <label class="block text-sm font-medium text-neutral-900 dark:text-neutral-100">Line time</label>
            <select name="event_line_time_id" class="mt-1 w-full rounded-md border border-neutral-300 bg-white p-2 text-sm dark:border-white/10 dark:bg-neutral-900" required>
              <option value="">Select a line time…</option>
              @foreach($lineTimes as $lt)
                <option value="{{ $lt->id }}">
                  {{ $lt->label ?? $lt->starts_at }} {{ $lt->starts_at?->timezone(config('app.timezone'))?->format('Y-m-d H:i') }}
                </option>
              @endforeach
            </select>
            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Required for multi-day events.</p>
          </div>
        @endif

        {{-- Lanes --}}
        <div>
          <label class="block text-sm font-medium text-neutral-900 dark:text-neutral-100">Lanes</label>
          <div class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-6">
            @foreach($laneOptions as $opt)
              <label class="inline-flex items-center gap-2 rounded-md border border-neutral-200 p-2 text-sm dark:border-white/10">
                <input type="checkbox" name="lanes[]" value="{{ $opt }}" class="rounded">
                <span>{{ $opt }}</span>
              </label>
            @endforeach
          </div>
          <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Check at least one lane.</p>
        </div>

        <div class="flex items-center gap-2">
          <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
            Create session
          </button>
          <a href="{{ route('corporate.leagues.show', $league) }}" class="text-sm text-neutral-600 underline dark:text-neutral-400">Back to league</a>
        </div>
      </form>
    </div>

    {{-- Existing sessions --}}
    <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-white/10 dark:bg-neutral-900">
      <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Active / recent sessions</h2>
      <div class="mt-3 space-y-2">
        @forelse($sessions as $s)
          <div class="rounded-md border border-neutral-200 p-3 text-sm dark:border-white/10">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div>
                Week {{ $s->week_number }}
                @if($s->event_line_time_id && $s->lineTime)
                  • {{ $s->lineTime->label ?? $s->lineTime->starts_at }}
                @endif
                • {{ $s->is_active ? 'Active' : 'Ended' }}
              </div>
              <div class="flex items-center gap-3">
                <a href="{{ route('kiosk.landing', ['token' => $s->token]) }}" class="text-indigo-600 underline dark:text-indigo-400">Open</a>
                <code class="text-xs">{{ $s->token }}</code>
              </div>
            </div>
          </div>
        @empty
          <div class="text-sm text-neutral-500 dark:text-neutral-400">No sessions yet.</div>
        @endforelse
      </div>
    </div>
  </div>
</x-layouts.app>
