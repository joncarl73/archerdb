<?php

// app/Observers/LeagueWeekScoreObserver.php

namespace App\Observers;

use App\Models\LeagueWeekScore;

class LeagueWeekScoreObserver
{
    public function saved(LeagueWeekScore $score): void
    {
        // Emit one final, post-commit broadcast with the parent id
    }
}
