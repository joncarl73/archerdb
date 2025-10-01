<?php

namespace App\Livewire\Public\Scoring;

use App\Models\League;
use App\Models\LeagueWeekScore;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;

class Record extends Component
{
    public string $uuid;

    public League $league;

    public LeagueWeekScore $score;

    public bool $showKeypad = false;

    public int $selectedEnd = 1;

    public int $selectedArrow = 0;

    public int $maxScore = 10;

    public string $scoringSystem = '10';

    // Kiosk flags (normalized so personal_device NEVER uses kiosk)
    public bool $kioskMode = false;

    public ?string $kioskReturnTo = null;

    /** Is the score’s week date equal to “today” in app TZ? */
    private function isScoresWeekToday(League $league, LeagueWeekScore $score): bool
    {
        $tz = config('app.timezone');
        $today = Carbon::now($tz)->toDateString();

        $score->loadMissing('week');
        $weekDate = optional($score->week)->date;

        if (! $weekDate) {
            return false;
        }

        return Carbon::parse($weekDate, $tz)->toDateString() === $today;
    }

    /** Is today the league’s configured day_of_week? */
    private function isLeagueNight(League $league): bool
    {
        return (int) Carbon::now(config('app.timezone'))->dayOfWeek === (int) $league->day_of_week;
    }

    /** Single gate used anywhere we need to decide kiosk behavior */
    protected function shouldShowKioskControls(): bool
    {
        $isTabletMode = ($this->league->scoring_mode === 'tablet');
        $isLeagueNight = $this->isLeagueNight($this->league);
        $isWeekIsToday = $this->isScoresWeekToday($this->league, $this->score);

        return $isTabletMode
            && $isLeagueNight
            && $isWeekIsToday
            && $this->kioskMode === true
            && ! empty($this->kioskReturnTo);
    }

    public function mount(string $uuid, LeagueWeekScore $score): void
    {
        $this->uuid = $uuid;
        $this->league = League::where('public_uuid', $uuid)->firstOrFail();

        // --- KIOSK NORMALIZATION ---
        // Only honor kiosk session flags if league is TABLET mode AND it’s the correct night/date.
        $isTabletMode = ($this->league->scoring_mode === 'tablet');
        $isLeagueNight = $this->isLeagueNight($this->league);
        $isToday = $this->isScoresWeekToday($this->league, $score);

        $sessionKiosk = (bool) session('kiosk_mode', false);
        $returnTo = (string) session('kiosk_return_to', '');

        if ($isTabletMode && $isLeagueNight && $isToday && $sessionKiosk && $returnTo !== '') {
            $this->kioskMode = true;
            $this->kioskReturnTo = $returnTo;
        } else {
            // Force-disable kiosk in all other cases
            $this->kioskMode = false;
            $this->kioskReturnTo = null;
            session()->forget('kiosk_mode');
            session()->forget('kiosk_return_to');
        }
        // --------------------------------

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

        $this->score = $score->load([
            'ends' => fn ($q) => $q->orderBy('end_number'),
            'week',
        ]);

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
        $scores[$this->selectedArrow] = $this->mapKeyToPoints($key);
        $end->fillScoresAndSave($scores);

        if ($this->selectedArrow < $this->score->arrows_per_end - 1) {
            $this->selectedArrow++;
        }

        $this->score->refresh()->load('ends');
    }

    public function finalizeEnd(): void
    {
        // If kiosk is truly active (tablet + league night + today + flags), go back to kiosk board.
        if ($this->shouldShowKioskControls()) {
            $this->redirect($this->kioskReturnTo, navigate: true);

            return;
        }

        // Personal-device flow: do NOT go to kiosk — just close keypad (remain on scoring grid page).
        $this->closeKeypad();
    }

    public function done(): void
    {
        $this->finalizeEnd();
    }

    public function render(): View
    {
        return view('livewire.public.scoring.record');
    }
}
