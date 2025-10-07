<?php

// app/Console/Commands/LeaguesLinkEvents.php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\League;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class LeaguesLinkEvents extends Command
{
    protected $signature = 'leagues:link-events {--dry}';

    protected $description = 'Create/link Events for Leagues that have no event_id';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $count = 0;

        League::with('event')->whereNull('event_id')->chunkById(200, function ($chunk) use ($dry, &$count) {
            foreach ($chunk as $league) {
                // Map league.type -> event.kind
                $type = $league->type instanceof \BackedEnum ? $league->type->value : (string) $league->type;
                $kind = $type === 'open' ? 'league.open' : 'league.closed';

                // Create minimal event
                $event = new Event;
                $event->public_uuid = (string) Str::uuid();
                $event->owner_id = $league->owner_id ?? ($league->user_id ?? auth()->id() ?? 1);
                $event->title = $league->title;
                $event->kind = $kind;
                $event->scoring_mode = null; // leagues keep their own scoring behavior
                $event->is_published = (bool) ($league->info->is_published ?? false);
                $event->starts_on = $league->registration_start_date;
                $event->ends_on = $league->registration_end_date;
                // DO NOT copy stripe fields for league.* kinds; checkout remains on League

                if (! $dry) {
                    $event->save();
                    $league->event_id = $event->id;
                    $league->save();
                }

                $this->line(($dry ? '[dry] ' : '')."Linked League #{$league->id} → Event #".($event->id ?? 'new'));
                $count++;
            }
        });

        $this->info("Processed {$count} leagues.");

        return self::SUCCESS;
    }
}
