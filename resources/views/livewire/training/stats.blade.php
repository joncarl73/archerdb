<?php
use Livewire\Volt\Component;
use App\Models\TrainingSession;

new class extends Component
{
    public TrainingSession $session;

    // derived
    public int $maxPerArrow;
    public int $arrowsPerEnd;

    // KPIs
    public int $totalScore = 0;
    public int $maxPossible = 0;
    public int $xCount = 0;
    public int $tenPlusCount = 0; // 10 or X
    public int $missCount = 0;
    public float $avgPerArrow = 0.0;
    public float $avgPerEnd = 0.0;
    public int $bestEnd = 0;
    public int $bestEndNumber = 0;
    public int $worstEnd = 0;
    public int $worstEndNumber = 0;
    public float $endStdDev = 0.0;

    // Series / distributions
    public array $endSeries = [];       // [[end, total], ...]
    public array $cumSeries = [];       // [[end, cumulative], ...]
    public array $perfectPace = [];     // [[end, perfect], ...]
    public array $distLabels = [];      // ['X','10','9',...,'M']
    public array $distSeries = [];      // [counts...]

    public function mount(TrainingSession $session): void
    {
        $this->session = $session->load(['ends' => fn($q) => $q->orderBy('end_number')]);

        $this->arrowsPerEnd = (int) ($this->session->arrows_per_end ?? 3);
        $this->maxPerArrow  = max((int)($this->session->max_score ?? 10), (int)($this->session->x_value ?? 10));

        // Gate: require every arrow to be scored (no nulls)
        $incomplete = $this->session->ends->contains(function ($e) {
            $scores = is_array($e->scores) ? $e->scores : [];
            // if end not seeded, treat as incomplete
            if (count($scores) < $this->arrowsPerEnd) return true;
            foreach ($scores as $v) { if (is_null($v)) return true; }
            return false;
        });

        if ($incomplete) {
            // send them to record page
            redirect()->route('training.record', $this->session->id)->with('toast', [
                'type' => 'info',
                'message' => 'Stats are available after all arrows are entered.',
            ])->send();
            exit;
        }

        $this->computeStats();
    }

    protected function computeStats(): void
    {
        $ends       = $this->session->ends;
        $xVal       = (int)($this->session->x_value ?? 10);
        $totalEnds  = max(1, $ends->count());
        $totalArrows= $totalEnds * $this->arrowsPerEnd;

        // KPI base
        $this->totalScore = (int) $this->session->total_score;
        $this->xCount     = (int) $this->session->x_count;
        $this->maxPossible= $totalArrows * $this->maxPerArrow;

        // Buckets (X,10..1,M)
        $buckets = ['X','10','9','8','7','6','5','4','3','2','1','M'];
        $counts  = array_fill_keys($buckets, 0);

        // Trend + cumulative + best/worst + tenPlus/miss
        $cum = 0;
        $best = -INF; $bestN = 0;
        $worst = INF; $worstN = 0;

        foreach ($ends as $e) {
            $endTotal = (int)($e->end_score ?? 0);
            $n = (int)$e->end_number;

            $this->endSeries[] = [$n, $endTotal];
            $cum += $endTotal;
            $this->cumSeries[] = [$n, $cum];
            $this->perfectPace[] = [$n, $n * $this->arrowsPerEnd * $this->maxPerArrow];

            if ($endTotal > $best) { $best = $endTotal; $bestN = $n; }
            if ($endTotal < $worst) { $worst = $endTotal; $worstN = $n; }

            foreach ((array)$e->scores as $v) {
                if ($v === null) continue;
                $iv = (int)$v;
                if ($iv === 0) { $counts['M']++; $this->missCount++; continue; }
                if ($iv === $xVal) { $counts['X']++; }
                elseif ($iv >= 10) { $counts['10']++; } // 10s (non-X)
                else {
                    $k = (string)$iv;
                    if (isset($counts[$k])) $counts[$k]++;
                }
                if ($iv >= 10 || $iv === $xVal) $this->tenPlusCount++;
            }
        }

        $this->bestEnd = (int)$best;  $this->bestEndNumber = $bestN ?: 1;
        $this->worstEnd = (int)$worst; $this->worstEndNumber = $worstN ?: 1;

        // Averages
        $this->avgPerArrow = round($this->totalScore / $totalArrows, 2);
        $this->avgPerEnd   = round($this->totalScore / $totalEnds, 2);

        // Std dev of end totals
        $mu = $this->avgPerEnd;
        $var = 0.0;
        foreach ($this->endSeries as [, $v]) { $var += pow($v - $mu, 2); }
        $this->endStdDev = round(sqrt($var / $totalEnds), 2);

        // Donut data
        $this->distLabels = array_keys($counts);
        $this->distSeries = array_values($counts);
    }
};
?>

<section class="w-full">
    <div class="mx-auto max-w-7xl">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">
                    Session stats
                </h1>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    {{ $session->title ?? 'Session' }} — {{ $session->distance_m ? $session->distance_m.'m' : '—' }}
                    • {{ $session->arrows_per_end }} arrows/end
                    • up to {{ $session->max_score }} points/arrow
                    @if(($session->x_value ?? 10) > $session->max_score)
                        (X={{ $session->x_value }})
                    @endif
                </p>
            </div>
            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none space-x-2">
                <a href="{{ route('training.record', $session->id) }}" wire:navigate>
                    <flux:button variant="ghost" icon="arrow-left">Back to record</flux:button>
                </a>
                <a href="{{ route('training.index') }}" wire:navigate>
                    <flux:button variant="ghost" icon="list-bullet">Back to sessions</flux:button>
                </a>
            </div>
        </div>

        {{-- KPI cards --}}
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-zinc-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">Total score</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ $totalScore }} <span class="text-sm font-normal text-gray-500 dark:text-gray-400">/ {{ $maxPossible }}</span>
                </div>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-zinc-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">X ({{ (int)($session->x_value ?? 10) }})</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ $xCount }} <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ round(($xCount / max(1, $session->ends->count()*$session->arrows_per_end))*100,1) }}%)</span>
                </div>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-zinc-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">Avg / arrow</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $avgPerArrow }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-zinc-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">Avg / end • σ</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $avgPerEnd }} <span class="text-sm font-normal"> • {{ $endStdDev }}</span></div>
            </div>

            <div class="rounded-xl border border-gray-200 p-4 dark:border-zinc-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">10+ (incl. X)</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $tenPlusCount }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-zinc-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">Misses</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $missCount }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-zinc-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">Best end</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">#{{ $bestEndNumber }} • {{ $bestEnd }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-zinc-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">Worst end</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">#{{ $worstEndNumber }} • {{ $worstEnd }}</div>
            </div>
        </div>

        {{-- Charts --}}
        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            {{-- End-by-end trend (area) --}}
            <div
                x-data="{
                  chart:null,
                  data:@js($endSeries),
                  maxEnd: {{ (int)$session->arrows_per_end * max((int)$session->max_score, (int)($session->x_value ?? 10)) }},
                  options() {
                    const dark = document.documentElement.classList.contains('dark');
                    return {
                      chart:{ type:'area', height:280, toolbar:{show:false}, foreColor: dark ? '#e5e7eb' : '#374151' },
                      theme:{ mode: dark ? 'dark' : 'light' },
                      series:[{ name:'End total', data: this.data }],
                      xaxis:{ type:'category', title:{ text:'End' } },
                      yaxis:{ min:0, max:this.maxEnd },
                      dataLabels:{ enabled:false },
                      stroke:{ curve:'smooth', width:2 },
                      annotations:{
                        yaxis:[{ y:this.maxEnd, borderColor:'#94a3b8', label:{ text:'Max per end' } }]
                      }
                    }
                  },
                  init(){ this.chart = new ApexCharts(this.$refs.chart, this.options()); this.chart.render(); }
                }"
                class="rounded-xl border border-gray-200 p-4 dark:border-zinc-700"
            >
                <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">End totals</div>
                <div x-ref="chart"></div>
            </div>

            {{-- Cumulative vs perfect pace --}}
            <div
                x-data="{
                  chart:null,
                  cum:@js($cumSeries),
                  perfect:@js($perfectPace),
                  options() {
                    const dark = document.documentElement.classList.contains('dark');
                    return {
                      chart:{ type:'line', height:280, toolbar:{show:false}, foreColor: dark ? '#e5e7eb' : '#374151' },
                      theme:{ mode: dark ? 'dark' : 'light' },
                      series:[
                        { name:'Cumulative', data: this.cum },
                        { name:'Perfect pace', data: this.perfect }
                      ],
                      xaxis:{ type:'category', title:{ text:'End' } },
                      yaxis:{ min:0 },
                      dataLabels:{ enabled:false },
                      stroke:{ curve:'smooth', width:[3,2], dashArray:[0,5] },
                      legend:{ position:'bottom' }
                    }
                  },
                  init(){ this.chart = new ApexCharts(this.$refs.chart, this.options()); this.chart.render(); }
                }"
                class="rounded-xl border border-gray-200 p-4 dark:border-zinc-700"
            >
                <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Cumulative score</div>
                <div x-ref="chart"></div>
            </div>

            {{-- Score distribution (donut) --}}
            <div
                x-data="{
                  chart:null,
                  options(){
                    const dark = document.documentElement.classList.contains('dark');
                    return {
                      chart:{ type:'donut', height:300, toolbar:{show:false}, foreColor: dark ? '#e5e7eb' : '#374151' },
                      theme:{ mode: dark ? 'dark' : 'light' },
                      labels: @js($distLabels),
                      series: @js($distSeries),
                      legend:{ position:'bottom' }
                    }
                  },
                  init(){ this.chart = new ApexCharts(this.$refs.chart, this.options()); this.chart.render(); }
                }"
                class="rounded-xl border border-gray-200 p-4 dark:border-zinc-700 lg:col-span-2"
            >
                <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Score distribution</div>
                <div x-ref="chart"></div>
            </div>
        </div>
    </div>
</section>
