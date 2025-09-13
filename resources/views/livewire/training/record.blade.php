<?php
use Livewire\Volt\Component;
use App\Models\TrainingSession;

new class extends Component
{
    public TrainingSession $session;

    // session config
    public int $arrowsPerEnd;
    public int $maxScore;
    public string $scoringSystem = '10'; // used only for showing "X" label when max is 10

    // keypad drawer UI state
    public bool $showKeypad   = false;
    public int  $selectedEnd  = 1; // 1-based end number
    public int  $selectedArrow = 0; // 0-based arrow index

    public function mount(TrainingSession $session): void
    {
        $this->session = $session->load(['ends' => fn ($q) => $q->orderBy('end_number')]);

        $this->arrowsPerEnd  = (int) ($this->session->arrows_per_end ?? 3);
        $this->maxScore      = (int) ($this->session->max_score ?? 10);
        $this->scoringSystem = (string) ($this->session->scoring_system ?? '10');

        // Seed ends up to ends_planned with empty scores arrays
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

    /** Keypad keys: X, max..0, M */
    public function getKeypadKeysProperty(): array
    {
        $keys = range($this->maxScore, 0);
        array_unshift($keys, 'X');
        $keys[] = 'M';
        return $keys;
    }

    /** Open keypad for a given cell */
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

    /** Clear just the currently selected cell */
    public function clearCurrent(): void
    {
        $end = $this->session->ends()->where('end_number', $this->selectedEnd)->first();
        if (!$end) return;

        $scores = $end->scores ?? array_fill(0, $this->arrowsPerEnd, null);
        $scores[$this->selectedArrow] = null;

        $end->scores = $scores;
        $end->recalcTotals(maxScore: $this->maxScore);
        $this->session->refreshTotals();

        $this->session->refresh()->load('ends');
    }

    /** Handle keypad press and auto-advance */
    public function keypad(string $key): void
    {
        $end = $this->session->ends()->where('end_number', $this->selectedEnd)->first();
        if (!$end) return;

        $scores = $end->scores ?? array_fill(0, $this->arrowsPerEnd, null);

        $val = match ($key) {
            'X' => $this->maxScore,
            'M' => 0,
            default => max(0, min($this->maxScore, (int) $key)),
        };

        $scores[$this->selectedArrow] = $val;

        $end->scores = $scores;
        $end->recalcTotals(maxScore: $this->maxScore);
        $this->session->refreshTotals();

        // Auto-advance to next arrow / end
        if ($this->selectedArrow < $this->arrowsPerEnd - 1) {
            $this->selectedArrow++;
        } else {
            $planned = (int) ($this->session->ends_planned ?: $this->session->ends()->count());
            if ($this->selectedEnd < $planned) {
                $this->selectedEnd++;
                $this->selectedArrow = 0;
            }
        }

        $this->session->refresh()->load('ends');
    }
};
?>

<section class="w-full">
    {{-- Header --}}
    <div class="mx-auto max-w-7xl">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">
                    Record scores
                </h1>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    {{ $session->title ?? 'Session' }} — {{ $session->distance_m ? $session->distance_m . 'm' : '—' }} •
                    {{ $session->arrows_per_end }} arrows/end • up to {{ $session->max_score }} points/arrow
                </p>
            </div>
            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <a href="{{ route('training.index') }}" wire:navigate
                   class="block rounded-md px-3 py-2 text-center text-sm font-semibold inset-ring inset-ring-gray-300
                          hover:bg-gray-50 dark:inset-ring-white/10 dark:hover:bg-white/5">
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
                            <td class="py-4 pl-4 pr-3 text-sm font-medium">
                                {{ $end->end_number }}
                            </td>

                            <td class="px-3 py-3">
                                <div class="grid gap-2"
                                     style="grid-template-columns: repeat({{ $this->arrowsPerEnd }}, minmax(0,1fr));">
                                    @for ($i = 0; $i < $this->arrowsPerEnd; $i++)
                                        @php($val = $end->scores[$i] ?? null)
                                        <button
                                            wire:click="startEntry({{ $end->end_number }}, {{ $i }})"
                                            class="h-10 rounded-lg inset-ring inset-ring-gray-300 hover:bg-gray-50
                                                   dark:inset-ring-gray-700 dark:hover:bg-white/5
                                                   @if($this->selectedEnd === $end->end_number && $this->selectedArrow === $i) ring-2 ring-indigo-500 @endif">
                                            @if ($val === null)
                                                <span class="opacity-40">·</span>
                                            @elseif ($val === 0)
                                                M
                                            @elseif ($val === $this->maxScore && $this->scoringSystem === '10')
                                                X
                                            @else
                                                {{ $val }}
                                            @endif
                                        </button>
                                    @endfor
                                </div>
                            </td>

                            <td class="px-3 py-3 text-sm tabular-nums">
                                {{ $end->end_score }}
                            </td>
                            <td class="px-3 py-3 text-sm tabular-nums">
                                {{ $end->x_count }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>

                    {{-- Footer totals --}}
                    <tfoot class="bg-gray-50/60 dark:bg-white/5">
                        <tr>
                            <th class="py-3.5 pl-4 pr-3 text-sm font-semibold text-gray-900 dark:text-white">Totals</th>
                            <td class="px-3 py-3 text-sm text-gray-600 dark:text-gray-300">
                                Ends completed: {{ $session->ends_completed }} / {{ $session->ends_planned ?? $session->ends->count() }}
                            </td>
                            <td class="px-3 py-3 text-sm font-semibold tabular-nums">
                                {{ $session->total_score }}
                            </td>
                            <td class="px-3 py-3 text-sm font-semibold tabular-nums">
                                {{ $session->x_count }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Right-hand keypad drawer --}}
    @if($showKeypad)
        <div class="fixed inset-0 z-40">
            {{-- overlay --}}
            <div class="absolute inset-0 bg-black/40" wire:click="closeKeypad"></div>

            {{-- panel --}}
            <div
                class="absolute inset-y-0 right-0 w-full max-w-md h-full overflow-y-auto bg-white p-6 shadow-xl dark:bg-zinc-900"
                x-data
                x-transition:enter="transform transition ease-out duration-200"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transform transition ease-in duration-150"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
            >
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        End #{{ $this->selectedEnd }}, Arrow {{ $this->selectedArrow + 1 }}
                    </h2>
                    <button class="rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10"
                            wire:click="closeKeypad">✕</button>
                </div>

                <div class="mt-6 space-y-6">
                    {{-- Keypad --}}
                    <div>
                        <div class="grid grid-cols-6 gap-2">
                            @foreach ($this->keypadKeys as $key)
                                <button
                                    wire:click="keypad('{{ $key }}')"
                                    class="h-12 rounded inset-ring inset-ring-gray-300 hover:bg-gray-50
                                           dark:inset-ring-gray-700 dark:hover:bg-white/5">
                                    {{ $key }}
                                </button>
                            @endforeach
                        </div>

                        <div class="mt-4 flex items-center gap-3">
                            <flux:button variant="ghost" wire:click="clearCurrent">Clear</flux:button>
                            <flux:button variant="primary" wire:click="closeKeypad">Done</flux:button>
                        </div>
                    </div>

                    {{-- Current end preview --}}
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-zinc-700">
                        @php($e = $session->ends->firstWhere('end_number', $this->selectedEnd))
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            <div class="mb-2 font-medium">Current end</div>
                            <div class="flex gap-2">
                                @for ($i = 0; $i < $this->arrowsPerEnd; $i++)
                                    @php($v = $e?->scores[$i] ?? null)
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-md inset-ring inset-ring-gray-200 dark:inset-ring-white/10
                                                 @if($this->selectedArrow === $i) ring-2 ring-indigo-500 @endif">
                                        @if ($v === null)
                                            <span class="opacity-40">·</span>
                                        @elseif ($v === 0)
                                            M
                                        @elseif ($v === $this->maxScore && $this->scoringSystem === '10')
                                            X
                                        @else
                                            {{ $v }}
                                        @endif
                                    </span>
                                @endfor
                            </div>
                            <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                End total: {{ $e?->end_score ?? 0 }} • X: {{ $e?->x_count ?? 0 }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</section>
