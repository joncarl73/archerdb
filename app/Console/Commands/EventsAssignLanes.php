<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\LaneAssignmentService;
use Illuminate\Console\Command;

class EventsAssignLanes extends Command
{
    protected $signature = 'events:assign-lanes {event_id} {--reset}';

    protected $description = 'Assign line times and lanes for a multi-day event.';

    public function handle(LaneAssignmentService $svc): int
    {
        $E = Event::findOrFail((int) $this->argument('event_id'));
        $res = $svc->assign($E, (bool) $this->option('reset'));
        $this->info("Assigned: {$res['assigned']}, Waitlisted: {$res['waitlisted']}, Skipped: {$res['skipped']}");

        return self::SUCCESS;
    }
}
