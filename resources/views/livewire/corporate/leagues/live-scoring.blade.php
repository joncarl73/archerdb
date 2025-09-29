@php($isKiosk = request()->boolean('kiosk'))

<section class="min-h-screen w-full">
  {{-- Auto-poll for live updates --}}
  <div wire:poll.{{ $refreshMs }}ms class="mx-auto w-full max-w-screen-2xl px-4 py-6">
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
        <span class="rounded bg-zinc-100 px-2 py-1 dark:bg-zinc-800">Auto-refresh: {{ $refreshMs }}ms</span>
        <span class="rounded bg-zinc-100 px-2 py-1 dark:bg-zinc-800">Rows: {{ count($rows) }}</span>
        <flux:button size="sm" variant="ghost" wire:click="$toggle('compact')">Toggle compact</flux:button>
      </div>
    </header>

    {{-- Scrolling container --}}
    <div
        x-data="liveTicker()"
        x-init="init()"
        class="relative {{ $isKiosk ? 'h-[calc(100vh-2rem)]' : 'h-[calc(100vh-10rem)]' }} overflow-hidden rounded-2xl border border-zinc-200 bg-white/60 dark:border-zinc-700 dark:bg-zinc-900/60"
    >

      <div class="absolute inset-0 overflow-hidden" x-ref="viewport">
        <div id="tickerContent"
             class="divide-y divide-zinc-200 dark:divide-white/10"
             :class="{ 'text-sm leading-tight': @js($compact), 'text-base leading-6': !@js($compact) }"
             x-ref="content">

          <div class="grid grid-cols-[1fr_auto_auto_auto_auto_auto] items-center px-4 py-3 font-semibold
                      bg-zinc-50 text-zinc-900 dark:bg-white/5 dark:text-zinc-100">
            <div>Archer</div>
            <div class="text-right pr-1 hidden sm:block">Lane</div>
            <div class="text-right pr-1">X</div>
            <div class="text-right pr-1">10</div>
            <div class="text-right pr-1">9</div>
            <div class="text-right">Score</div>
          </div>

          @forelse($rows as $r)
            <div class="grid grid-cols-[1fr_auto_auto_auto_auto_auto] items-center px-4 py-3">
              <div class="truncate">{{ $r['name'] }}</div>
              <div class="text-right pr-1 hidden sm:block">
                {{ $r['lane'] ?? '—' }}<span class="opacity-60">{{ $r['slot'] ? ' · '.$r['slot'] : '' }}</span>
              </div>
              <div class="text-right pr-1 tabular-nums">{{ $r['x'] }}</div>
              <div class="text-right pr-1 tabular-nums">{{ $r['tens'] }}</div>
              <div class="text-right pr-1 tabular-nums">{{ $r['nines'] }}</div>
              <div class="text-right font-semibold tabular-nums">{{ $r['score'] }}</div>
            </div>
          @empty
            <div class="px-4 py-6 text-center text-zinc-600 dark:text-zinc-300">No check-ins yet.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>

  {{-- Alpine ticker --}}
  <script>
    function liveTicker() {
      return {
        speedPxPerSec: 20,
        pauseMs: 1200,
        dir: 1,
        lastTs: 0,
        maxScroll: 0,
        init() {
          const viewport = this.$refs.viewport;
          const content  = this.$refs.content;
          const recalc = () => {
            this.maxScroll = Math.max(0, content.scrollHeight - viewport.clientHeight);
            if (viewport.scrollTop > this.maxScroll) viewport.scrollTop = this.maxScroll;
          };
          recalc();
          window.addEventListener('resize', recalc);
          const loop = (ts) => {
            if (!this.lastTs) this.lastTs = ts;
            const dt = (ts - this.lastTs) / 1000;
            this.lastTs = ts;

            if (this.maxScroll > 0) {
              viewport.scrollTop += this.speedPxPerSec * dt * this.dir;
              if (viewport.scrollTop <= 0) {
                viewport.scrollTop = 0;
                setTimeout(() => requestAnimationFrame(loop), this.pauseMs);
                this.dir = 1; this.lastTs = 0; return;
              }
              if (viewport.scrollTop >= this.maxScroll) {
                viewport.scrollTop = this.maxScroll;
                setTimeout(() => requestAnimationFrame(loop), this.pauseMs);
                this.dir = -1; this.lastTs = 0; return;
              }
            }
            requestAnimationFrame(loop);
          };
          requestAnimationFrame(loop);
        },
      }
    }
  </script>
</section>
