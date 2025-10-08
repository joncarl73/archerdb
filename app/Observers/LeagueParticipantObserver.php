<?php

namespace App\Observers;

use App\Models\League;
use App\Models\LeagueParticipant;

class LeagueParticipantObserver
{
    public function creating(LeagueParticipant $p): void
    {
        $this->ensureEventId($p);
    }

    public function updating(LeagueParticipant $p): void
    {
        $this->ensureEventId($p);
    }

    private function ensureEventId(LeagueParticipant $p): void
    {
        if (! empty($p->event_id)) {
            return;
        }
        if (! $p->league_id) {
            return;
        }

        $league = $p->relationLoaded('league')
            ? $p->league
            : League::select('id', 'event_id')->find($p->league_id);

        if ($league && $league->event_id) {
            $p->event_id = (int) $league->event_id;
        }
    }
}
