<?php

namespace App\Livewire\Public\Scoring;

use App\Models\League;
use App\Models\LeagueWeekScore;
use Illuminate\View\View;
use Livewire\Component;

class Record extends Component
{
    public string $uuid;                 // league public uuid (route param)

    public League $league;               // resolved in mount()

    public LeagueWeekScore $score;       // bound via :score="$score"

    // UI state (replaces “Undefined variable $selectedEnd”)
    public bool $showKeypad = false;

    public int $selectedEnd = 1;        // 1-based

    public int $selectedArrow = 0;      // 0-based

    // display helpers
    public int $maxScore = 10;           // usually 10

    public string $scoringSystem = '10'; // for showing "X" label

    public function mount(string $uuid, LeagueWeekScore $score): void
    {
        // Load league from uuid and guard mode + ownership
        $this->uuid = $uuid;
        $this->league = League::where('public_uuid', $uuid)->firstOrFail();

        $mode = $this->league->scoring_mode->value ?? $this->league->scoring_mode;
        abort_unless($mode === 'personal_device', 404);
        abort_unless($score->league_id === $this->league->id, 404);

        $this->score = $score->load(['ends' => fn ($q) => $q->orderBy('end_number')]);

        $this->maxScore = (int) ($this->score->max_score ?? 10);
        $this->scoringSystem = $this->maxScore === 10 ? '10' : (string) $this->maxScore;

        // Ensure planned ends exist (safety)
        $planned = (int) ($this->score->ends_planned ?? 0);
        $existing = $this->score->ends->count();
        if ($planned > $existing) {
            for ($i = $existing + 1; $i <= $planned; $i++) {
                $this->score->ends()->create([
                    'end_number' => $i,
                    'scores' => array_fill(0, (int) $this->score->arrows_per_end, null),
                    'end_score' => 0,
                    'x_count' => 0,
                ]);
            }
            $this->score->load(['ends' => fn ($q) => $q->orderBy('end_number')]);
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
        $this->selectedEnd = $endNumber;
        $this->selectedArrow = $arrowIndex;
        $this->showKeypad = true;
    }

    public function closeKeypad(): void
    {
        $this->showKeypad = false;
    }

    private function mapKeyToPoints(string $key): int
    {
        if ($key === 'M') {
            return 0;
        }
        if ($key === 'X') {
            return (int) ($this->score->x_value ?? 10);
        }
        $maxAllowed = max($this->maxScore, (int) ($this->score->x_value ?? 10));

        return max(0, min($maxAllowed, (int) $key));
    }

    public function clearCurrent(): void
    {
        $end = $this->score->ends()->where('end_number', $this->selectedEnd)->first();
        if (! $end) {
            return;
        }

        $scores = $end->scores ?? array_fill(0, (int) $this->score->arrows_per_end, null);
        $scores[$this->selectedArrow] = null;
        $end->fillScoresAndSave($scores);
        $this->score->refresh()->load('ends');
    }

    public function keypad(string $key): void
    {
        $end = $this->score->ends()->where('end_number', $this->selectedEnd)->first();
        if (! $end) {
            return;
        }

        $scores = $end->scores ?? array_fill(0, (int) $this->score->arrows_per_end, null);
        $val = $this->mapKeyToPoints($key);
        $scores[$this->selectedArrow] = $val;
        $end->fillScoresAndSave($scores);

        if ($this->selectedArrow < $this->score->arrows_per_end - 1) {
            $this->selectedArrow++;
        }

        $this->score->refresh()->load('ends');
    }

    public function done(): void
    {
        // League scoring: no auto-advance; “Done” just returns to the grid
        $this->closeKeypad();
    }

    public function render(): View
    {
        return view('livewire.public.scoring.record');
    }
}
