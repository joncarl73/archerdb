<?php

namespace App\Observers;

use App\Enums\EventKind;
use App\Enums\EventScoringMode;
use App\Models\Event;
use App\Models\League;

class LeagueObserver
{
    /** Map League.scoring_mode -> Event.scoring_mode */
    private function mapScoring(?string $leagueMode): string
    {
        $leagueMode = (string) ($leagueMode ?? '');

        // leagues: 'personal_device' | 'tablet'
        // events:  'personal' | 'kiosk'
        return $leagueMode === 'tablet' ? 'kiosk' : 'personal';
    }

    /** Create a shadow Event when a League is created via legacy UI */
    public function created(League $league): void
    {
        if ($league->event_id) {
            return;
        }

        $event = new Event;
        $event->owner_id = $league->owner_id;
        $event->public_uuid = \Illuminate\Support\Str::uuid()->toString();
        $event->title = $league->title;
        $event->kind = EventKind::League; // we’re treating legacy leagues as Event(kind='league')
        $event->scoring_mode = EventScoringMode::fromLeague($league->scoring_mode);
        $event->is_published = (bool) $league->is_published;

        // keep registration window as the event window
        $event->starts_on = $league->registration_start_date ?: $league->start_date;
        $event->ends_on = $league->registration_end_date;

        // mirror price to event if you’re using closed/paid leagues
        $event->price_cents = $league->price_cents;
        $event->currency = $league->currency;

        $event->save();

        // link back (quietly to avoid recursion)
        $league->forceFill(['event_id' => $event->id])->saveQuietly();
    }

    /** Keep the linked Event in sync with key display fields */
    public function updated(League $league): void
    {
        if (! $league->event_id) {
            $this->created($league);

            return;
        }

        $event = $league->event; // belongsTo
        if (! $event) {
            return;
        }

        $event->title = $league->title;
        $event->scoring_mode = $this->mapScoring($league->scoring_mode instanceof \UnitEnum ? $league->scoring_mode->value : $league->scoring_mode);
        $event->is_published = (bool) $league->is_published;

        $event->starts_on = $league->registration_start_date ?: $league->start_date;
        $event->ends_on = $league->registration_end_date;

        // Only mirror price if you’re still selling via League product
        $event->price_cents = $league->price_cents;
        $event->currency = $league->currency;

        $event->save();
    }
}
