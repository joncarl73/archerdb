<?php
use Livewire\Volt\Component;
use App\Models\TrainingSession;

new class extends Component
{
    public TrainingSession $session;

    // session config
    public int $arrowsPerEnd;
    public int $maxScore;
    public string $scoringSystem = '10'; // used only to display "X" label when max is 10
    public bool $autoAdvanceEnds = true; // when false, “Done” returns to the overview instead of jumping to the next end

    // keypad drawer UI state
    public bool $showKeypad   = false;
    public int  $selectedEnd  = 1; // 1-based end number
    public int  $selectedArrow = 0; // 0-based arrow index

    public function mount(TrainingSession $session): void
    {
        $this->session = $session->load(['ends' => fn ($q) => $q->orderBy('end_number')]);

        $this->arrowsPerEnd  = (int) ($this->session->arrows_per_end ?? 3);
        $this->maxScore      = (int) ($this->session->max_score ?? 10);
        $this->scoringSystem = $this->maxScore === 10 ? '10' : (string) $this->maxScore;

        $planned  = (int) ($this->session->ends_planned ?? 0);
        $existing = $this->session->ends->count();

        if ($planned > $existing) {
            for ($i = $existing + 1; $i <= $planned; $i++) {
                $this->session->ends()->create([
                    'end_number' => $i,
                    'scores'     => array_fill(0, $this->arrowsPerEnd, null),
                    'end_score'  => 0,
                    'x_count'    => 0,
                ]);
            }
            $this->session->load(['ends' => fn ($q) => $q->orderBy('end_number')]);
        }
    }

    public function getKeypadKeysProperty(): array
    {
        $nums = range($this->maxScore, 0);
        array_unshift($nums, 'X');
        $nums[] = 'M';
        return $nums;
    }

    public function startEntry(int $endNumber, int $arrowIndex): void
    {
        $this->selectedEnd   = $endNumber;
        $this->selectedArrow = $arrowIndex;
        $this->showKeypad    = true;
    }

    public function closeKeypad(): void
    {
        $this->showKeypad = false;
    }

    private function mapKeyToPoints(string $key): int
    {
        if ($key === 'M') return 0;
        if ($key === 'X') return (int) ($this->session->x_value ?? 10);
        $maxAllowed = max($this->maxScore, (int) ($this->session->x_value ?? 10));
        return max(0, min($maxAllowed, (int) $key));
    }

    public function clearCurrent(): void
    {
        $end = $this->session->ends()->where('end_number', $this->selectedEnd)->first();
        if (!$end) return;

        $scores = $end->scores ?? array_fill(0, $this->arrowsPerEnd, null);
        $scores[$this->selectedArrow] = null;
        $end->fillScoresAndSave($scores);
        $this->session->refresh()->load('ends');
    }

    public function keypad(string $key): void
    {
        $end = $this->session->ends()->where('end_number', $this->selectedEnd)->first();
        if (!$end) return;

        $scores = $end->scores ?? array_fill(0, $this->arrowsPerEnd, null);
        $val = $this->mapKeyToPoints($key);
        $scores[$this->selectedArrow] = $val;
        $end->fillScoresAndSave($scores);

        if ($this->selectedArrow < $this->arrowsPerEnd - 1) {
            $this->selectedArrow++;
        } else {
            $planned = (int) ($this->session->ends_planned ?: $this->session->ends()->count());
            if ($this->autoAdvanceEnds && $this->selectedEnd < $planned) {
                $this->selectedEnd++;
                $this->selectedArrow = 0;
            }
        }

        $this->session->refresh()->load('ends');
    }

    public function done(): void
    {
        if ($this->autoAdvanceEnds) {
            $planned = (int) ($this->session->ends_planned ?: $this->session->ends()->count());
            $next = $this->selectedEnd + 1;

            if ($next <= $planned) {
                $this->selectedEnd   = $next;
                $this->selectedArrow = 0;
                return;
            }
        }

        $this->closeKeypad();
    }
}; ?>

<section class="w-full">
    {{-- Header --}}
    <div class="mx-auto max-w-7xl">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">Record scores</h1>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    {{ $session->title ?? 'Session' }} — {{ $session->distance_m ? $session->distance_m . 'm' : '—' }} •
                    {{ $session->arrows_per_end }} arrows/end • up to {{ $session->max_score }} points/arrow
                    @if(($session->x_value ?? 10) > $session->max_score)
                        (X={{ $session->x_value }})
                    @endif
                </p>
            </div>
            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <a href="{{ route('training.index') }}" wire:navigate
                   class="block rounded-md px-3 py-2 text-center text-sm font-semibold inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:inset-ring-white/10 dark:hover:bg-white/5">
                    Back to sessions
                </a>
            </div>
        </div>
    </div>

    {{-- Score table --}}
    <div class="mt-8">
        <div class="mx-auto max-w-7xl">
            <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
                <table class="w-full text-left">
                    <thead class="bg-white dark:bg-gray-900">
                    <tr>
                        <th class="py-3.5 pl-4 pr-3 text-sm font-semibold">End</th>
                        <th class="px-3 py-3.5 text-sm font-semibold">Arrows</th>
                        <th class="px-3 py-3.5 text-sm font-semibold w-24">End&nbsp;Total</th>
                        <th class="px-3 py-3.5 text-sm font-semibold w-20">X</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($session->ends as $end)
                        <tr>
                            <td class="py-4 pl-4 pr-3 text-sm font-medium">{{ $end->end_number }}</td>
                            <td class="px-3 py-3">
                                <div class="grid gap-2" style="grid-template-columns: repeat({{ $arrowsPerEnd }}, minmax(0,1fr));">
                                    @for ($i = 0; $i < $arrowsPerEnd; $i++)
                                        @php($val = $end->scores[$i] ?? null)
                                        <button wire:click="startEntry({{ $end->end_number }}, {{ $i }})" class="h-10 rounded-lg inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:inset-ring-gray-700 dark:hover:bg-white/5 @if($selectedEnd === $end->end_number && $selectedArrow === $i) ring-2 ring-indigo-500 @endif">
                                            @if ($val === null)
                                                <span class="opacity-40">·</span>
                                            @elseif ((int)$val === 0)
                                                M
                                            @elseif ($scoringSystem === '10' && (int)$val === (int)($session->x_value ?? 10))
                                                X
                                            @else
                                                {{ $val }}
                                            @endif
                                        </button>
                                    @endfor
                                </div>
                            </td>
                            <td class="px-3 py-3 text-sm tabular-nums">{{ $end->end_score ?? 0 }}</td>
                            <td class="px-3 py-3 text-sm tabular-nums">{{ $end->x_count ?? 0 }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50/60 dark:bg-white/5">
                        @php($completedEnds = 0)
                        @foreach ($session->ends as $e)
                            @php($hasAny = false)
                            @if (is_array($e->scores))
                                @foreach ($e->scores as $sv)
                                    @if (!is_null($sv))
                                        @php($hasAny = true)
                                        @break
                                    @endif
                                @endforeach
                            @endif
                            @if ($hasAny)
                                @php($completedEnds++)
                            @endif
                        @endforeach
                        @php($plannedEnds = $session->ends_planned ?? $session->ends->count())
                        <tr>
                            <th class="py-3.5 pl-4 pr-3 text-sm font-semibold text-gray-900 dark:text-white">Totals</th>
                            <td class="px-3 py-3 text-sm text-gray-600 dark:text-gray-300">Ends completed: {{ $completedEnds }} / {{ $plannedEnds }}</td>
                            <td class="px-3 py-3 text-sm font-semibold tabular-nums">{{ $session->total_score }}</td>
                            <td class="px-3 py-3 text-sm font-semibold tabular-nums">{{ $session->x_count }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Right-hand keypad drawer --}}
    @if($showKeypad)
        <div class="fixed inset-0 z-40">
            <div class="absolute inset-0 bg-black/40" wire:click="closeKeypad"></div>
            <div class="absolute inset-y-0 right-0 w-full max-w-md h-full overflow-y-auto bg-white p-6 shadow-xl dark:bg-zinc-900" x-data x-transition:enter="transform transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transform transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">End #{{ $selectedEnd }}, Arrow {{ $selectedArrow + 1 }}</h2>
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                            <span>Auto-advance ends</span>
                            <flux:switch wire:model.live="autoAdvanceEnds" />
                        </label>
                        <button class="rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10" wire:click="closeKeypad">✕</button>
                    </div>
                </div>

                <div class="mt-6 space-y-6">
                    {{-- Keypad (bigger buttons on mobile) --}}
                    <div>
                        <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                            @foreach ($this->keypadKeys as $key)
                                <button wire:click="keypad('{{ $key }}')" class="h-16 text-lg sm:h-12 sm:text-base rounded-lg inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:inset-ring-gray-700 dark:hover:bg-white/5 touch-manipulation select-none" aria-label="Enter {{ $key }}">
                                    {{ $key }}
                                </button>
                            @endforeach
                        </div>
                        <div class="mt-5 flex items-center gap-3">
                            <flux:button variant="ghost" size="sm" class="px-4 py-2" wire:click="clearCurrent">Clear</flux:button>
                            <flux:button variant="primary" size="sm" class="px-4 py-2" wire:click="done">Done</flux:button>
                        </div>
                    </div>

                    {{-- Current end preview with selectable arrows --}}
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-zinc-700">
                        @php($e = $session->ends->firstWhere('end_number', $selectedEnd))
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            <div class="mb-2 font-medium">Current end</div>
                            <div class="flex flex-wrap gap-2">
                                @for ($i = 0; $i < $arrowsPerEnd; $i++)
                                    @php($v = $e?->scores[$i] ?? null)
                                    <button type="button" wire:click="startEntry({{ $selectedEnd }}, {{ $i }})" class="inline-flex h-12 w-12 sm:h-9 sm:w-9 items-center justify-center rounded-md inset-ring inset-ring-gray-200 dark:inset-ring-white/10 hover:bg-gray-50 dark:hover:bg-white/5 @if($selectedArrow === $i) ring-2 ring-indigo-500 @endif" aria-pressed="{{ $selectedArrow === $i ? 'true' : 'false' }}" aria-label="Select arrow {{ $i + 1 }}">
                                        @if ($v === null)
                                            <span class="opacity-40">·</span>
                                        @elseif ((int)$v === 0)
                                            M
                                        @elseif ($scoringSystem === '10' && (int)$v === (int)($session->x_value ?? 10))
                                            X
                                        @else
                                            {{ $v }}
                                        @endif
                                    </button>
                                @endfor
                            </div>
                            <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">End total: {{ $e?->end_score ?? 0 }} • X: {{ $e?->x_count ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</section>
