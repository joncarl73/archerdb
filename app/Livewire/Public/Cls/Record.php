<?php

namespace App\Livewire\Public\Cls;

use App\Models\Event;
use App\Models\EventScore;
use App\Models\EventScoreEnd;
use App\Models\League;
use App\Models\LeagueWeekEnd;
use App\Models\LeagueWeekScore;
use Illuminate\View\View;
use Livewire\Component;

class Record extends Component
{
    // Basic context
    public $kind;          // 'event' | 'league'

    public $uuid;

    public $kioskMode = false;

    public $kioskReturnTo = null;

    // ID of the score row we’re working with
    public $scoreId;

    // Display-only props
    public $eventTitle = null;   // also used for league title

    public $archerName = null;

    // Scoring config snapshot
    public $arrowsPerEnd = 3;

    public $endsPlanned = 10;

    public $scoringSystem = '10';  // mainly for events

    public $xValue = null;

    public $maxScore = 10;

    public $showSeparateXButton = false;

    /**
     * Keypad scoring values (e.g. [10, 9, 8, ..., 1]).
     *
     * @var array<int,int|string>
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
        $this->kind = strtolower($kind);   // 'event' or 'league'
        $this->uuid = $uuid;
        $this->scoreId = $scoreId;
        $this->kioskMode = $kioskMode;
        $this->kioskReturnTo = $kioskReturnTo;

        if ($this->kind === 'event') {
            $this->mountEventScore($scoreId, $uuid);
        } elseif ($this->kind === 'league') {
            $this->mountLeagueScore($scoreId, $uuid);
        } else {
            abort(404, 'CLS-UNKNOWN-KIND');
        }

        // Default keypad values based on maxScore if not already set.
        // (getKeypadKeysProperty will ensure we never show a numeric 0 key;
        // zero is always entered via "M".)
        if (empty($this->keypadValues)) {
            $max = $this->maxScore ?: 10;
            $this->keypadValues = range($max, 0);
        }

        // Default focus on first cell.
        $this->selectedEnd = 1;
        $this->selectedArrow = 0;
    }

    /**
     * Initialize state for an EventScore (ruleset-driven).
     */
    protected function mountEventScore(int $scoreId, string $uuid): void
    {
        $score = EventScore::query()
            ->with(['event.ruleset', 'event.rulesetOverrides', 'participant', 'ends'])
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

        // Scoring config from snapshot on the score row
        $this->arrowsPerEnd = (int) ($score->arrows_per_end ?? 3);
        $this->endsPlanned = (int) ($score->ends_planned ?? 10);
        $this->scoringSystem = (string) ($score->scoring_system ?? '10');
        $this->xValue = $score->x_value;
        $this->maxScore = (int) ($score->max_score ?? 10);

        //
        // Determine keypad scoring values:
        //
        //   1. Prefer the event's ruleset (+ overrides) → this is the *authoritative*
        //      scoring scale for events.
        //   2. If missing, fall back to the snapshot on event_scores.scoring_values.
        //   3. If still missing, fall back to a simple max..1 scale.
        //
        $ruleset = $event->ruleset;
        $overrides = $event->rulesetOverrides?->schema ?? [];

        $values = $overrides['scoring_values'] ?? $ruleset?->scoring_values ?? null;

        if (! is_array($values) || empty($values)) {
            // Secondary source: per-score snapshot (if you ever stamp it).
            $values = $score->scoring_values ?? null;
        }

        if (! is_array($values) || empty($values)) {
            // Final fallback: ruleset-style max..1.
            // Zero is entered via "M", not a numeric key.
            $max = $this->maxScore ?: 10;
            $values = range($max, 1);
        }

        // Normalize to distinct ints in descending order.
        $values = array_values(array_unique(array_map('intval', $values)));
        rsort($values);

        $this->keypadValues = $values;

        // EVENTS: show a separate X button whenever the snapshot/ruleset
        // provides an x_value. X will map to that value in mapKeyToPoints().
        $this->showSeparateXButton = ($this->xValue !== null);

        // DEBUG LOG (optional – comment out when done)
        \Log::debug('CLS EVENT DEBUG', [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'score_id' => $score->id,
            'xValue' => $this->xValue,
            'maxScore' => $this->maxScore,
            'raw_scoring_values_snapshot' => $score->scoring_values ?? null,
            'ruleset_scoring_values' => $ruleset?->scoring_values ?? null,
            'override_scoring_values' => $overrides['scoring_values'] ?? null,
            'final_values_after_precedence' => $values,
        ]);

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
    }

    /**
     * Initialize state for a LeagueWeekScore (legacy design, CLS UI).
     */
    protected function mountLeagueScore(int $scoreId, string $uuid): void
    {
        $score = LeagueWeekScore::query()
            ->with(['league', 'participant', 'ends'])
            ->findOrFail($scoreId);

        /** @var League $league */
        $league = $score->league;

        if ($league->public_uuid !== $uuid) {
            abort(404, 'CLS-L2');
        }

        $this->eventTitle = $league->title ?? $league->name ?? 'League';

        $participant = $score->participant;
        if ($participant) {
            $first = $participant->first_name ?? '';
            $last = $participant->last_name ?? '';
            $name = trim($first.' '.$last);
            $this->archerName = $name ?: null;
        }

        // League scoring config comes directly from the league/week snapshot.
        $this->arrowsPerEnd = (int) ($score->arrows_per_end ?? 3);
        $this->endsPlanned = (int) ($score->ends_planned ?? 10);
        $this->xValue = $score->x_value;
        $this->maxScore = (int) ($score->max_score ?? 10);

        // League keypad: X is separate (x_value from league),
        // numbers are max..1, and "M" represents 0.
        $max = $this->maxScore ?: 10;

        // Numeric keys only: max → 1; we skip 0 here because "M" will be 0.
        $values = range($max, 1);

        $this->keypadValues = $values;

        // For leagues, always show an X key when an x_value is configured.
        // That X will resolve to either 10 or 11 based on league setup.
        $this->showSeparateXButton = ($this->xValue !== null);

        // Build buffer from league_week_ends (scores JSON only).
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
    }

    public function getKeypadKeysProperty(): array
    {
        //
        // For both events and leagues we build the **UI** keypad off
        // $this->keypadValues, then decorate with X/M when appropriate.
        //
        $keys = [];

        // Optional X button (when configured).
        if ($this->showSeparateXButton) {
            $keys[] = 'X';
        }

        foreach ($this->keypadValues as $v) {
            $int = (int) $v;

            // Never show a numeric "0" key — zero is always entered as "M".
            if ($int === 0) {
                continue;
            }

            $keys[] = (string) $int;
        }

        // Always include M (miss) at the end; this maps to 0 points.
        $keys[] = 'M';

        return $keys;
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

    /**
     * Persist the buffered scores to the database and return the score model.
     *
     * @return \App\Models\EventScore|\App\Models\LeagueWeekScore
     */
    protected function persistBufferedScores()
    {
        if ($this->kind === 'league') {
            return $this->persistLeagueScores();
        }

        // Default: event
        return $this->persistEventScores();
    }

    protected function persistEventScores(): EventScore
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

            // This will populate scores, total, x_count on the event_score_ends table
            $endModel->fillScoresAndSave($scores);
        }

        $score->refresh()->load('ends');

        return $score;
    }

    protected function persistLeagueScores(): LeagueWeekScore
    {
        $score = LeagueWeekScore::query()
            ->with('ends')
            ->findOrFail($this->scoreId);

        $existingByEnd = $score->ends->keyBy('end_number');

        $totalScore = 0;
        $totalX = 0;
        $xVal = $this->xValue !== null ? (int) $this->xValue : null;

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

            /** @var LeagueWeekEnd $endModel */
            $endModel = $existingByEnd[$endNumber] ?? new LeagueWeekEnd([
                'league_week_score_id' => $score->id,
                'end_number' => $endNumber,
            ]);

            $endModel->league_week_score_id = $score->id;
            $endModel->end_number = $endNumber;
            $endModel->scores = $scores;   // <-- only scores on league_week_ends
            $endModel->save();

            // Compute per-end totals in-memory
            $endSum = 0;
            $endX = 0;

            foreach ($scores as $v) {
                if ($v === null) {
                    continue;
                }

                $v = (int) $v;
                $endSum += $v;

                if ($xVal !== null && $v === $xVal) {
                    $endX++;
                }
            }

            $totalScore += $endSum;
            $totalX += $endX;
        }

        // Persist aggregate totals back to league_week_scores
        $score->total_score = $totalScore;
        $score->x_count = $totalX;
        $score->save();

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
        // 1) Flush any buffered scores to the DB
        $score = $this->persistBufferedScores();

        // 2) Kiosk / tablet mode → bounce back to kiosk board
        if ($this->kioskMode && $this->kioskReturnTo) {
            $this->redirect($this->kioskReturnTo, navigate: true);

            return;
        }

        // 3) Personal device → CLS summary (event or league)
        $kind = $this->kind ?? 'event';
        $uuid = $this->uuid;

        $this->redirectRoute('public.cls.scoring.summary', [
            'kind' => $kind,
            'uuid' => $uuid,
            'score' => $score->id,
        ], navigate: true);
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
