<?php

namespace App\Console\Commands;

use App\Enums\EventKind;
use App\Enums\EventScoringMode;
use App\Models\Event;
use App\Models\League;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillLeagueEvents extends Command
{
    protected $signature = 'archerdb:backfill-league-events';

    protected $description = 'Create/attach Event(kind=league) for leagues missing event_id';

    public function handle(): int
    {
        $count = 0;

        League::withTrashed()->whereNull('event_id')->chunkById(200, function ($batch) use (&$count) {
            foreach ($batch as $league) {
                $event = new Event;
                $event->owner_id = $league->owner_id;
                $event->public_uuid = Str::uuid()->toString();
                $event->title = $league->title;
                $event->kind = EventKind::League;
                $event->scoring_mode = EventScoringMode::fromLeague($league->scoring_mode);
                $event->is_published = (bool) $league->is_published;
                $event->starts_on = $league->registration_start_date ?: $league->start_date;
                $event->ends_on = $league->registration_end_date;
                $event->price_cents = $league->price_cents;
                $event->currency = $league->currency;
                $event->save();

                $league->forceFill(['event_id' => $event->id])->saveQuietly();
                $count++;
            }
        });

        $this->info("Backfilled {$count} leagues.");

        return self::SUCCESS;
    }
}
