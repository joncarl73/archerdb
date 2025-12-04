<?php

namespace App\Livewire\Public\Cls;

use App\Models\Event;
use App\Models\EventScore;
use App\Models\EventScoreEnd;
use Illuminate\View\View;
use Livewire\Component;

class Record extends Component
{
    // Basic context
    public $kind;

    public $uuid;

    public $kioskMode = false;

    public $kioskReturnTo = null;

    // ID of the EventScore row we’re working with
    public $scoreId;

    // Display-only props so Blade doesn’t need the model
    public $eventTitle = null;

    public $archerName = null;

    // Scoring config snapshot
    public $arrowsPerEnd = 3;

    public $endsPlanned = 10;

    public $scoringSystem = '10';

    public $xValue = null;

    public $maxScore = 10;

    public $showSeparateXButton = false;

    /**
     * Keypad scoring values (e.g. [14, 12, 10, 8, 5, 0]).
     *
     * @var array<int,int>
     */
    public $keypadValues = [];

    /**
     * Local buffer of arrow scores:
     * [endNumber => [arrowIndex => value|null]].
     *
     * @var array<int,array<int,int|null>>
     */
    public $buffer = [];

    public $selectedEnd = 1;

    public $selectedArrow = 0;

    public $showKeypad = false;

    public function mount(
        string $kind,
        string $uuid,
        int $scoreId,
        bool $kioskMode = false,
        ?string $kioskReturnTo = null,
    ): void {
        $this->kind = strtolower($kind);
        $this->uuid = $uuid;
        $this->scoreId = $scoreId;
        $this->kioskMode = $kioskMode;
        $this->kioskReturnTo = $kioskReturnTo;

        if ($this->kind !== 'event') {
            // League integration will be wired in a later step.
            abort(501, 'CLS league scoring not implemented yet.');
        }

        // Load the EventScore and related models locally (not stored as a property)
        $score = EventScore::query()
            ->with(['event', 'participant', 'ends'])
            ->findOrFail($scoreId);

        /** @var Event $event */
        $event = $score->event;

        if ($event->public_uuid !== $uuid) {
            abort(404, 'CLS-E1');
        }

        // Display-only props
        $this->eventTitle = $event->title;

        $participant = $score->participant;
        if ($participant) {
            $first = $participant->first_name ?? '';
            $last = $participant->last_name ?? '';
            $name = trim($first.' '.$last);
            $this->archerName = $name ?: null;
        }

        // Scoring config from snapshot
        $this->arrowsPerEnd = (int) ($score->arrows_per_end ?? 3);
        $this->endsPlanned = (int) ($score->ends_planned ?? 10);
        $this->scoringSystem = (string) ($score->scoring_system ?? '10');
        $this->xValue = $score->x_value;
        $this->maxScore = (int) ($score->max_score ?? 10);

        // Determine keypad scoring values from snapshot or fallback.
        // Determine keypad scoring values from snapshot or fallback.
        $values = $score->scoring_values ?? null;

        if (! is_array($values) || empty($values)) {
            // Fallback: WA-style max/max-1/.../0
            $max = $this->maxScore ?: 10;
            $values = range($max, 0);
        }

        // Normalize to distinct ints (numeric) in descending order.
        $values = array_values(array_unique(array_map('intval', $values)));
        rsort($values);

        // Decide if we should show a separate "X" button in addition to "10".
        // We only do this when:
        //   - scoring system is "10",
        //   - xValue is set and equals the max score,
        //   - and the scale is a simple 10..0 range.
        $this->showSeparateXButton = false;

        if ($this->scoringSystem === '10' && $this->xValue !== null) {
            $max = $this->maxScore ?: max($values);

            $expected = range($max, 0);
            sort($expected);

            $valsSorted = $values;
            sort($valsSorted);

            if ($valsSorted === $expected && (int) $this->xValue === (int) $max) {
                $this->showSeparateXButton = true;
            }
        }

        $this->keypadValues = $values;

        // Build local buffer from existing ends.
        $buffer = [];

        foreach ($score->ends as $end) {
            $endNumber = (int) $end->end_number;
            $scores = $end->scores ?? [];

            $row = [];
            for ($i = 0; $i < $this->arrowsPerEnd; $i++) {
                $row[$i] = $scores[$i] ?? null;
            }

            $buffer[$endNumber] = $row;
        }

        // Ensure we have rows for all planned ends.
        for ($endNumber = 1; $endNumber <= $this->endsPlanned; $endNumber++) {
            if (! array_key_exists($endNumber, $buffer)) {
                $row = [];
                for ($i = 0; $i < $this->arrowsPerEnd; $i++) {
                    $row[$i] = null;
                }
                $buffer[$endNumber] = $row;
            }
        }

        ksort($buffer);
        $this->buffer = $buffer;

        // Default focus on first cell.
        $this->selectedEnd = 1;
        $this->selectedArrow = 0;
    }

    public function getKeypadKeysProperty(): array
    {
        // Same pattern as legacy league scoring:
        // [ 'X', maxScore, maxScore-1, ..., 0, 'M' ]
        $nums = range($this->maxScore, 0);
        array_unshift($nums, 'X');
        $nums[] = 'M';

        return $nums;
    }

    private function mapKeyToPoints(string $key): int
    {
        if ($key === 'M') {
            return 0;
        }

        if ($key === 'X') {
            return (int) ($this->xValue ?? $this->maxScore ?? 10);
        }

        $maxAllowed = max(
            (int) $this->maxScore,
            (int) ($this->xValue ?? $this->maxScore ?? 10)
        );

        return max(0, min($maxAllowed, (int) $key));
    }

    public function keypad(string $key): void
    {
        $points = $this->mapKeyToPoints($key);

        // Reuse applyValue so all cursor + buffer behavior stays in one place
        $this->applyValue($points);
    }

    public function startEntry(int $endNumber, int $arrowIndex): void
    {
        $this->selectedEnd = $endNumber;
        $this->selectedArrow = $arrowIndex;
        $this->showKeypad = true;
    }

    public function applyValue(int $value): void
    {
        $end = $this->selectedEnd;
        $idx = $this->selectedArrow;

        if (! isset($this->buffer[$end])) {
            $this->buffer[$end] = array_fill(0, $this->arrowsPerEnd, null);
        }

        $this->buffer[$end][$idx] = $value;

        // Only auto-advance within the same end; keep focus on last arrow
        $nextEnd = $end;
        $nextIdx = $idx + 1;

        if ($nextIdx >= $this->arrowsPerEnd) {
            $nextIdx = $this->arrowsPerEnd - 1;
        }

        $this->selectedEnd = $nextEnd;
        $this->selectedArrow = $nextIdx;
    }

    public function clearCell(): void
    {
        $end = $this->selectedEnd;
        $idx = $this->selectedArrow;

        if (isset($this->buffer[$end][$idx])) {
            $this->buffer[$end][$idx] = null;
        }
    }

    protected function persistBufferedScores(): EventScore
    {
        $score = EventScore::query()
            ->with('ends')
            ->findOrFail($this->scoreId);

        $existingByEnd = $score->ends->keyBy('end_number');

        foreach ($this->buffer as $endNumber => $row) {
            $endNumber = (int) $endNumber;

            if ($endNumber < 1 || $endNumber > $this->endsPlanned) {
                continue;
            }

            // Normalize row length to arrowsPerEnd
            $scores = [];
            for ($i = 0; $i < $this->arrowsPerEnd; $i++) {
                $scores[$i] = $row[$i] ?? null;
            }

            /** @var EventScoreEnd $endModel */
            $endModel = $existingByEnd[$endNumber] ?? new EventScoreEnd([
                'event_score_id' => $score->id,
                'end_number' => $endNumber,
            ]);

            $endModel->event_score_id = $score->id;
            $endModel->end_number = $endNumber;

            $endModel->fillScoresAndSave($scores);
        }

        // Keep a fresh model if needed later
        $score->refresh()->load('ends');

        return $score;
    }

    public function closeKeypad(): void
    {
        // Flush current buffer so scores survive refresh
        $this->persistBufferedScores();

        // Stay on the scoring grid
        $this->showKeypad = false;
    }

    /**
     * Persist the buffered scores to the database and redirect to summary (or kiosk).
     */
    public function done(): void
    {
        $score = $this->persistBufferedScores();

        // Redirect behavior: kiosk vs personal device
        if ($this->kioskMode && $this->kioskReturnTo) {
            $this->redirect($this->kioskReturnTo);

            return;
        }

        $this->redirectRoute('public.cls.scoring.summary', [
            'kind' => $this->kind,
            'uuid' => $this->uuid,
            'score' => $score->id,
        ]);
    }

    /**
     * Display rows with computed totals for the grid.
     *
     * @return array<int,array{end_number:int,scores:array,total:int,x_count:int,has_any:bool}>
     */
    public function getDisplayEndsProperty(): array
    {
        $result = [];

        $xVal = $this->xValue !== null ? (int) $this->xValue : null;

        foreach ($this->buffer as $endNumber => $row) {
            $endNumber = (int) $endNumber;

            $sum = 0;
            $xCount = 0;
            $hasAny = false;

            foreach ($row as $v) {
                if ($v !== null) {
                    $hasAny = true;
                    $sum += (int) $v;

                    if ($xVal !== null && (int) $v === $xVal) {
                        $xCount++;
                    }
                }
            }

            $result[] = [
                'end_number' => $endNumber,
                'scores' => $row,
                'total' => $sum,
                'x_count' => $xCount,
                'has_any' => $hasAny,
            ];
        }

        usort($result, static fn (array $a, array $b) => $a['end_number'] <=> $b['end_number']);

        return $result;
    }

    public function render(): View
    {
        return view('livewire.public.cls.record');
    }
}
