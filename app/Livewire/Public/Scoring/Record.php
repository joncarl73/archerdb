<?php

namespace App\Livewire\Public\Scoring;

use App\Models\League;
use App\Models\LeagueWeekScore;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;

class Record extends Component
{
    public string $uuid;                 // league public uuid (route param)

    public League $league;               // resolved in mount()

    public LeagueWeekScore $score;       // bound via :score="$score"

    // UI state
    public bool $showKeypad = false;

    public int $selectedEnd = 1;         // 1-based

    public int $selectedArrow = 0;       // 0-based

    // display helpers
    public int $maxScore = 10;           // usually 10

    public string $scoringSystem = '10'; // for showing "X" label

    // kiosk context (passed from controller/blade or session)
    public bool $kioskMode = false;

    public ?string $kioskReturnTo = null;

    /**
     * Props arrive via kebab-case in Blade:
     * <livewire:public.scoring.record
     *   :uuid="$uuid"
     *   :score="$score"
     *   :kiosk-mode="$kioskMode ?? false"
     *   :kiosk-return-to="$kioskReturnTo ?? null"
     * />
     */
    public function mount(
        string $uuid,
        LeagueWeekScore $score,
        ?bool $kioskMode = null,
        ?string $kioskReturnTo = null
    ): void {
        $this->uuid = $uuid;
        $this->league = League::where('public_uuid', $uuid)->firstOrFail();

        // Prefer explicit props; otherwise fall back to session flags set by kiosk handoff
        $this->kioskMode = ! is_null($kioskMode) ? (bool) $kioskMode : (bool) session('kiosk_mode', false);
        $this->kioskReturnTo = $kioskReturnTo ?? session('kiosk_return_to');

        // Guard: allow if kiosk handoff OR league mode is personal_device/kiosk/tablet
        $mode = $this->league->scoring_mode->value ?? $this->league->scoring_mode;
        $allowed = $this->kioskMode || in_array((string) $mode, ['personal_device', 'kiosk', 'tablet'], true);

        Log::debug('lw:record.mount', [
            'uuid' => $uuid,
            'league_id' => $this->league->id,
            'mode' => (string) $mode,
            'kioskMode' => $this->kioskMode,
            'kioskReturnTo' => $this->kioskReturnTo,
            'score_id' => $score->id ?? null,
            'score_league_id' => $score->league_id ?? null,
        ]);

        abort_unless($allowed, 404, 'LW-G1');
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
        if ($this->kioskMode && $this->kioskReturnTo) {
            // Livewire 3: client-side navigate so tablet returns to list
            $this->redirect($this->kioskReturnTo, navigate: true);

            return;
        }

        // Personal device flow: close keypad and stay on grid
        $this->closeKeypad();
    }

    public function render(): View
    {
        return view('livewire.public.scoring.record');
    }
}
