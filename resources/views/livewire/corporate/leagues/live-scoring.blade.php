@php
  $isKiosk = request()->boolean('kiosk');

  // helper to compute a stable domKey for a row
  $domKeyFor = function (array $r): string {
      if (!empty($r['id']))             return 's-'.$r['id'];           // score id
      if (!empty($r['checkin_id']))     return 'c-'.$r['checkin_id'];
      if (!empty($r['participant_id'])) return 'p-'.$r['participant_id'];
      return 'n-'.substr(md5(($r['name']??'').'|'.($r['lane']??'').'|'.($r['slot']??'')),0,10);
  };

  // Sort the rows using LiveScoring::sorted()
  $sortedRows = $this->sorted($rows);

  // Split evenly into 3 columns for display
  $cols = [[],[],[]];
  foreach ($sortedRows as $i => $r) {
      $cols[$i % 3][] = $r;
  }
@endphp

<section class="min-h-screen w-full">
  {{-- flash animation style --}}
  <style>
    .ag-flash { animation: agFlash 1400ms ease-out 1; }
    @keyframes agFlash {
      0%   { background-color: rgba(16,185,129,0.40); }
      60%  { background-color: rgba(16,185,129,0.18); }
      100% { background-color: transparent; }
    }
  </style>

  <div class="mx-auto w-full max-w-screen-2xl px-4 py-6">
    <header class="mb-4 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold">
          Week {{ $week->week_number }} — Live scoring
        </h1>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
          {{ \Carbon\Carbon::parse($week->date)->format('l, F j, Y') }}
        </p>
      </div>

      <div class="flex items-center gap-2 text-xs">
        <span id="echoStatus" wire:ignore class="rounded bg-zinc-100 px-2 py-1 dark:bg-zinc-800">Connecting…</span>
        <span class="rounded bg-zinc-100 px-2 py-1 dark:bg-zinc-800">Rows: {{ count($rows) }}</span>
      </div>
    </header>

    {{-- Scrolling container --}}
    <div
      x-data="{
        speedPxPerSec: 20, pauseMs: 1200, dir: 1, lastTs: 0, maxScroll: 0,
        init() {
          const viewport = this.$refs.viewport, content = this.$refs.content;
          const recalc = () => {
            this.maxScroll = Math.max(0, content.scrollHeight - viewport.clientHeight);
            if (viewport.scrollTop > this.maxScroll) viewport.scrollTop = this.maxScroll;
          };
          recalc(); window.addEventListener('resize', recalc);
          const loop = (ts) => {
            if (!this.lastTs) this.lastTs = ts;
            const dt = (ts - this.lastTs) / 1000; this.lastTs = ts;
            if (this.maxScroll > 0) {
              viewport.scrollTop += this.speedPxPerSec * dt * this.dir;
              if (viewport.scrollTop <= 0) { viewport.scrollTop = 0; setTimeout(() => requestAnimationFrame(loop), this.pauseMs); this.dir = 1; this.lastTs = 0; return; }
              if (viewport.scrollTop >= this.maxScroll) { viewport.scrollTop = this.maxScroll; setTimeout(() => requestAnimationFrame(loop), this.pauseMs); this.dir = -1; this.lastTs = 0; return; }
            }
            requestAnimationFrame(loop);
          };
          requestAnimationFrame(loop);
        }
      }"
      x-init="init()"
      class="relative {{ $isKiosk ? 'h-[calc(100vh-2rem)]' : 'h-[calc(100vh-10rem)]' }} overflow-hidden rounded-2xl border border-zinc-200 bg-white/60 dark:border-zinc-700 dark:bg-zinc-900/60"
    >
      <div class="absolute inset-0 overflow-hidden" x-ref="viewport">
        <div id="tickerContent" class="p-3 text-sm leading-tight" x-ref="content">

          @if (count($sortedRows) === 0)
            <div class="px-4 py-6 text-center text-zinc-600 dark:text-zinc-300">
              No check-ins yet.
            </div>
          @else
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              @foreach ($cols as $col)
                <div class="space-y-2">
                  @foreach ($col as $r)
                    @php($domKey = $domKeyFor($r))
                    <div
                      id="row-{{ $domKey }}"
                      wire:key="row-{{ $domKey }}"
                      class="rounded-lg border border-zinc-200 bg-white/80 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900/70 transition-colors"
                    >
                      <div class="grid grid-cols-[1fr_auto_auto_auto_auto_auto] items-center gap-x-2">
                        <div class="truncate font-medium">{{ $r['name'] }}</div>
                        <div class="text-right pr-1 hidden sm:block">
                          {{ $r['lane'] ?? '—' }}<span class="opacity-60">{{ !empty($r['slot']) ? ' · '.$r['slot'] : '' }}</span>
                        </div>
                        <div class="text-right pr-1 tabular-nums">X&nbsp;{{ $r['x'] }}</div>
                        <div class="text-right pr-1 tabular-nums">10&nbsp;{{ $r['tens'] }}</div>
                        <div class="text-right pr-1 tabular-nums">9&nbsp;{{ $r['nines'] }}</div>
                        <div class="text-right font-semibold tabular-nums">{{ $r['score'] }}</div>
                      </div>
                    </div>
                  @endforeach
                </div>
              @endforeach
            </div>
          @endif

        </div>
      </div>
    </div>
  </div>

  {{-- Polling + highlight --}}
  <script>
  (function () {
    const channelName = 'league.week.{{ $league->id }}.{{ $week->id }}';
    window.__ArcherLive = window.__ArcherLive || { timers:{}, hiQueue:[], boundHook:false };

    const setStatus = (t) => { const el = document.getElementById('echoStatus'); if (el) el.textContent = t; };

    function highlightCard(domKey) {
      const el = domKey && document.getElementById('row-' + domKey);
      if (!el) return;
      el.classList.remove('ag-flash'); void el.offsetWidth; el.classList.add('ag-flash');
      setTimeout(() => { if (el) el.classList.remove('ag-flash'); }, 1500);
    }

    // One-time Livewire hook to drain the highlight queue in FIFO order
    document.addEventListener('livewire:load', () => {
      if (window.__ArcherLive.boundHook) return;
      if (window.Livewire && typeof Livewire.hook === 'function') {
        window.__ArcherLive.boundHook = true;
        Livewire.hook('message.processed', () => {
          // flash up to 3 per processed message to feel snappy on bursts
          for (let i = 0; i < 3; i++) {
            const key = window.__ArcherLive.hiQueue.shift();
            if (!key) break;
            setTimeout(() => highlightCard(key), 30 + i * 60);
          }
        });
      }
    });

    const state = {
      cursor: '', // JSON string e.g. {"ts":"...","last_id":123}
      intervalMs: 1200, maxIntervalMs: 10000, backoffFactor: 1.6,
      inFlight:false, stopped:false, hiddenPauseMs:3500, consecutiveErrors:0
    };

    function computeDomKey(row) {
      if (row.id) return 's-' + row.id;
      if (row.checkin_id) return 'c-' + row.checkin_id;
      if (row.participant_id) return 'p-' + row.participant_id;
      return null;
    }

    async function tick() {
      if (state.stopped || state.inFlight) return;
      state.inFlight = true;

      const sinceParam = state.cursor ? ('?since=' + encodeURIComponent(state.cursor)) : '';
      const url = `/api/live/league/{{ $league->id }}/week/{{ $week->id }}${sinceParam}`;

      try {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 8000);
        const res = await fetch(url, { method:'GET', cache:'no-store', signal:controller.signal, headers:{ 'Accept':'application/json' } });
        clearTimeout(timeout);
        if (!res.ok) throw new Error('HTTP ' + res.status);

        const payload = await res.json();
        // IMPORTANT: cursor is an object, keep it as a JSON string to echo back verbatim
        if (payload && payload.cursor) state.cursor = JSON.stringify(payload.cursor);

        const rows = (payload && payload.rows) || [];
        if (rows.length > 0) {
          // Process ALL rows, oldest→newest (server already orders asc)
          for (const r of rows) {
            const key = computeDomKey(r);
            if (key) window.__ArcherLive.hiQueue.push(key);
            @this.call('applyDelta', r);
          }
          setStatus('Live (Polling)');
        } else {
          setStatus('Live (Polling)');
        }

        state.consecutiveErrors = 0;
        state.intervalMs = document.hidden ? state.hiddenPauseMs : 1000;
      } catch (e) {
        state.consecutiveErrors++;
        state.intervalMs = Math.min(Math.round((state.intervalMs || 1200) * state.backoffFactor), state.maxIntervalMs);
        setStatus(state.consecutiveErrors >= 3 ? 'Reconnecting…' : 'Live (Polling)');
      } finally {
        state.inFlight = false;
        if (!state.stopped) {
          const next = document.hidden ? state.hiddenPauseMs : state.intervalMs;
          window.__ArcherLive.timers[channelName] = setTimeout(tick, next);
        }
      }
    }

    function start() {
      if (window.__ArcherLive.timers[channelName]) clearTimeout(window.__ArcherLive.timers[channelName]);
      state.stopped = false;
      setStatus('Connecting…');
      tick();
    }
    function stop() {
      state.stopped = true;
      if (window.__ArcherLive.timers[channelName]) {
        clearTimeout(window.__ArcherLive.timers[channelName]);
        window.__ArcherLive.timers[channelName] = null;
      }
    }

    document.addEventListener('visibilitychange', () => { if (!document.hidden) state.intervalMs = 400; });
    start();
    window.__ArcherLive.startPolling = start;
    window.__ArcherLive.stopPolling  = stop;
  })();
  </script>

</section>
