<?php
use Livewire\Volt\Component;
use App\Models\TrainingSession;
use App\Models\TrainingEnd;

new class extends Component {
    public TrainingSession $session;

    public int $rounds = 0;           // number of ends
    public int $arrowsPerEnd = 0;     // arrows per end
    public int $maxScore = 10;        // 10 or 11

    /** map endId => array of length $arrowsPerEnd (null|0..$maxScore) */
    public array $grid = [];

    // keypad state
    public bool $pickerOpen = false;
    public ?int $activeEndId = null;
    public int $activeCol = 0;

    public function mount(TrainingSession $session): void
    {
        $this->session = $session->load('ends');
        $this->rounds = max(1, (int) $session->rounds);
        $this->arrowsPerEnd = max(1, (int) $session->arrows_per_round);
        $this->maxScore = (int) ($session->max_score ?? 10);

        // Ensure ends exist 1..rounds
        $existing = $this->session->ends->keyBy('end_number');
        for ($i = 1; $i <= $this->rounds; $i++) {
            if (!isset($existing[$i])) {
                $this->session->ends()->create([
                    'end_number' => $i,
                    'scores' => array_fill(0, $this->arrowsPerEnd, null),
                ]);
            }
        }
        $this->session->refresh()->load('ends');

        // Build $grid (endId => score array normalized to arrowsPerEnd)
        $this->grid = [];
        foreach ($this->session->ends as $end) {
            $row = is_array($end->scores) ? $end->scores : [];
            // normalize to $arrowsPerEnd
            $norm = [];
            for ($c=0; $c<$this->arrowsPerEnd; $c++) {
                $v = $row[$c] ?? null; // null = not entered
                $norm[$c] = is_null($v) ? null : (int) $v;
            }
            $this->grid[$end->id] = $norm;
        }
    }

    /** Open keypad for a specific end (row). Optionally set a column. */
    public function openEnd(int $endId, ?int $col = null): void
    {
        $this->activeEndId = $endId;

        if (is_null($col)) {
            $this->activeCol = $this->firstEmptyCol($endId);
        } else {
            $this->activeCol = max(0, min($this->arrowsPerEnd - 1, $col));
        }
        $this->pickerOpen = true;
    }

    protected function firstEmptyCol(int $endId): int
    {
        foreach ($this->grid[$endId] as $i => $v) {
            if ($v === null) return $i;
        }
        return 0;
    }

    /** Enter a score (string 'M' or numeric '1'..'maxScore'). Auto-advance to next col. */
    public function enter(string $raw): void
    {
        if (!$this->activeEndId) return;

        $val = ($raw === 'M') ? 0 : (int) $raw;
        // clamp
        if ($val < 0) $val = 0;
        if ($val > $this->maxScore) $val = $this->maxScore;

        $this->grid[$this->activeEndId][$this->activeCol] = $val;

        // persist this end immediately (partial save)
        $this->persistEnd($this->activeEndId);

        // auto-advance within row
        if ($this->activeCol < $this->arrowsPerEnd - 1) {
            $this->activeCol++;
        } else {
            $this->pickerOpen = false;
        }
    }

    public function backspace(): void
    {
        if (!$this->activeEndId) return;

        if ($this->grid[$this->activeEndId][$this->activeCol] !== null) {
            $this->grid[$this->activeEndId][$this->activeCol] = null;
        } else {
            if ($this->activeCol > 0) {
                $this->activeCol--;
                $this->grid[$this->activeEndId][$this->activeCol] = null;
            }
        }

        $this->persistEnd($this->activeEndId);
    }

    public function clearEnd(int $endId): void
    {
        $this->grid[$endId] = array_fill(0, $this->arrowsPerEnd, null);
        if ($this->activeEndId === $endId) $this->activeCol = 0;
        $this->persistEnd($endId);
    }

    protected function persistEnd(int $endId): void
    {
        /** @var TrainingEnd $end */
        $end = $this->session->ends->firstWhere('id', $endId);
        if ($end) {
            $end->update(['scores' => $this->grid[$endId]]);
        }
    }

    public function saveAll(): void
    {
        foreach ($this->grid as $endId => $row) {
            $this->session->ends()->whereKey($endId)->update(['scores' => $row]);
        }
        $this->dispatch('toast', type:'success', message:'Scores saved');
    }

    /** Per-end total (M=0, 1..maxScore as-is) */
    public function endTotal(array $row): int
    {
        $sum = 0;
        foreach ($row as $v) {
            if ($v === null) continue;
            $sum += (int) $v;
        }
        return $sum;
    }

    public function getGrandTotalProperty(): int
    {
        $total = 0;
        foreach ($this->grid as $row) $total += $this->endTotal($row);
        return $total;
    }
};
?>

<section class="mx-auto max-w-7xl">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-base font-semibold text-gray-900 dark:text-white">Scoring</h1>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ $session->started_at?->format('Y-m-d') }}
                · {{ $rounds }} ends × {{ $arrowsPerEnd }} arrows
                @if($session->distance_m) · {{ $session->distance_m }}m @endif
                @if($session->target_face) · {{ $session->target_face }} @endif
                · Max {{ $maxScore }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="ghost" wire:click="saveAll">Save</flux:button>
        </div>
    </div>

    <div class="mt-6 overflow-x-auto rounded-xl border border-gray-200 dark:border-zinc-700">
        <table class="w-full text-left">
            <thead class="bg-white dark:bg-gray-900">
                <tr>
                    <th class="py-3.5 pl-4 pr-3 text-sm font-semibold">End</th>
                    @for($c = 0; $c < $arrowsPerEnd; $c++)
                        <th class="px-3 py-3.5 text-center text-sm font-semibold">{{ $c + 1 }}</th>
                    @endfor
                    <th class="px-3 py-3.5 text-right text-sm font-semibold">Total</th>
                    <th class="py-3.5 pr-4"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach($session->ends as $end)
                    @php($row = $grid[$end->id] ?? array_fill(0,$arrowsPerEnd,null))
                    @php($total = $this->endTotal($row))
                    <tr wire:key="end-{{ $end->id }}">
                        <td class="py-3.5 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white">
                            {{ $end->end_number }}
                        </td>

                        @foreach($row as $c => $v)
                            @php
                                $display = is_null($v) ? '—' : ($v === 0 ? 'M' : $v);
                                $isActive = $pickerOpen && $activeEndId === $end->id && $activeCol === $c;
                            @endphp
                            <td class="px-3 py-3.5 text-center text-sm">
                                <button
                                    wire:click="openEnd({{ $end->id }}, {{ $c }})"
                                    class="inline-flex min-w-[2.25rem] items-center justify-center rounded-md px-2 py-1
                                           {{ $display === '—' ? 'text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-white' }}
                                           {{ $isActive ? 'ring-2 ring-indigo-500' : 'ring-1 ring-black/10 dark:ring-white/10' }}">
                                    {{ $display }}
                                </button>
                            </td>
                        @endforeach

                        <td class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $total }}
                        </td>
                        <td class="py-3.5 pr-4 text-right text-sm">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button variant="ghost" size="sm" wire:click="openEnd({{ $end->id }})">
                                    Enter
                                </flux:button>
                                <flux:button variant="ghost" size="sm" class="text-red-600 dark:text-red-400"
                                             wire:click="clearEnd({{ $end->id }})">
                                    Clear
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @endforeach

                <tr class="bg-gray-50 dark:bg-white/5">
                    <td class="py-3.5 pl-4 pr-3 text-sm font-semibold">Total</td>
                    <td colspan="{{ $arrowsPerEnd }}" class="px-3 py-3.5"></td>
                    <td class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900 dark:text-white">
                        {{ $grandTotal }}
                    </td>
                    <td class="py-3.5 pr-4"></td>
                </tr>
            </tbody>
        </table>
    </div>

    @if($pickerOpen)
        <div class="fixed inset-x-0 bottom-0 z-40 bg-white/95 p-4 shadow-2xl backdrop-blur dark:bg-zinc-900/95">
            <div class="mx-auto max-w-3xl">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        End <span class="font-semibold">
                            {{ optional($session->ends->firstWhere('id', $activeEndId))->end_number }}
                        </span>,
                        Arrow <span class="font-semibold">{{ $activeCol + 1 }}</span>
                        <span class="ml-3 text-xs opacity-70">(Tap 1–{{ $maxScore }} or M)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:button size="sm" variant="ghost" wire:click="backspace">Backspace</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="$set('pickerOpen', false)">Done</flux:button>
                    </div>
                </div>

                <div class="grid grid-cols-4 gap-2">
                    @for($n = 1; $n <= $maxScore; $n++)
                        <button
                            wire:click="enter('{{ $n }}')"
                            class="rounded-lg border border-gray-300 px-4 py-3 text-center text-lg font-semibold hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/10">
                            {{ $n }}
                        </button>
                    @endfor
                    <button
                        wire:click="enter('M')"
                        class="rounded-lg border border-gray-300 px-4 py-3 text-center text-lg font-semibold hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/10">
                        M
                    </button>
                </div>
            </div>
        </div>
    @endif
</section>
