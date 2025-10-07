<?php

namespace App\Console\Commands;

use App\Enums\EventKind;
use App\Models\Event;
use App\Models\League;
use App\Models\LeagueWeek;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EventsEnsurePeriods extends Command
{
    protected $signature = 'events:ensure-periods {--dry}';

    protected $description = 'Backfill event_id on weeks and ensure periods (sessions) exist per event kind.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $added = 0;
        $labeled = 0;
        $backfilled = 0;

        // 1) Backfill league_weeks.event_id from leagues.event_id
        if (Schema::hasColumn('league_weeks', 'event_id')) {
            $count = DB::table('league_weeks as w')
                ->join('leagues as l', 'l.id', '=', 'w.league_id')
                ->whereNull('w.event_id')
                ->whereNotNull('l.event_id')
                ->update(['w.event_id' => DB::raw('l.event_id')]);
            $backfilled += $count;
            $this->info("Backfilled event_id on weeks: {$count}");
        }

        // 2) Ensure periods exist per event
        Event::query()->with(['league'])->orderBy('id')
            ->chunkById(200, function ($events) use (&$added, &$labeled, $dry) {
                foreach ($events as $E) {
                    $kind = $E->kind;
                    $existing = LeagueWeek::query()
                        ->where('event_id', $E->id)
                        ->orderBy('week_number')
                        ->get(['id', 'week_number', 'label', 'date']);

                    if ($kind === EventKind::LeagueClosed->value || $kind === EventKind::LeagueOpen->value) {
                        // Legacy league-backed event
                        $L = $E->league;
                        if (! $L) {
                            continue;
                        }
                        $target = max(1, (int) ($L->length_weeks ?? 10));
                        $added += $this->ensureCount($E, $existing, $target, $dry, labelPrefix: 'Week');

                        continue;
                    }

                    if ($kind === EventKind::RemoteLeague->value) {
                        // Remote league behaves like a normal league in periods
                        $L = $E->league;
                        $target = max(1, (int) ($L?->length_weeks ?? 10));
                        $added += $this->ensureCount($E, $existing, $target, $dry, labelPrefix: 'Week');

                        continue;
                    }

                    if ($kind === EventKind::SingleDay->value) {
                        $target = 1;
                        $added += $this->ensureCount($E, $existing, $target, $dry, defaultDate: $E->starts_on, labelPrefix: 'Session');

                        continue;
                    }

                    if ($kind === EventKind::MultiDay->value) {
                        // Prefer event_line_times if you have them; else derive from date range
                        $lineTimesExist = Schema::hasTable('event_line_times');
                        if ($lineTimesExist) {
                            $lines = DB::table('event_line_times')->where('event_id', $E->id)->orderBy('starts_at')->get();
                            $target = max(1, $lines->count());
                            // create one per line time with label = line label or start time
                            $i = (int) ($existing->max('week_number') ?? 0);
                            foreach ($lines as $line) {
                                $i++;
                                if ($this->missingWeek($existing, $i)) {
                                    if (! $dry) {
                                        LeagueWeek::create([
                                            'event_id' => $E->id,
                                            'league_id' => $E->league?->id,
                                            'week_number' => $i,
                                            'label' => $line->label ?: ('Session '.$i),
                                            'date' => $line->starts_at ? \Illuminate\Support\Carbon::parse($line->starts_at)->toDateString() : $E->starts_on,
                                        ]);
                                    }
                                    $added++;
                                }
                            }
                        } else {
                            // Fallback: one session per calendar day between starts_on..ends_on
                            $start = $E->starts_on ?: now()->toDateString();
                            $end = $E->ends_on ?: $start;
                            $d1 = \Illuminate\Support\Carbon::parse($start)->startOfDay();
                            $d2 = \Illuminate\Support\Carbon::parse($end)->startOfDay();
                            if ($d2->lt($d1)) {
                                $d2 = $d1;
                            }

                            $days = (int) $d1->diffInDays($d2) + 1;
                            $added += $this->ensureCount($E, $existing, $days, $dry, defaultDate: $start, labelPrefix: 'Day', spreadDates: true, endDate: $end);
                        }

                        continue;
                    }
                }
            });

        $this->info(($dry ? '[DRY] ' : '')."Added periods: {$added}; Backfilled weeks: {$backfilled}");

        return self::SUCCESS;
    }

    private function missingWeek($existing, int $n): bool
    {
        return ! $existing->firstWhere('week_number', $n);
    }

    /**
     * Ensure exactly N periods exist for an event.
     * If $spreadDates = true, assigns sequential dates between $defaultDate..$endDate.
     */
    private function ensureCount(Event $E, $existing, int $target, bool $dry, ?string $defaultDate = null, string $labelPrefix = 'Session', bool $spreadDates = false, ?string $endDate = null): int
    {
        $added = 0;
        $max = (int) ($existing->max('week_number') ?? 0);

        // Date seeds
        $startDate = $defaultDate ? \Illuminate\Support\Carbon::parse($defaultDate)->startOfDay() : now()->startOfDay();
        $finishDate = $endDate ? \Illuminate\Support\Carbon::parse($endDate)->startOfDay() : $startDate;

        for ($n = 1; $n <= $target; $n++) {
            if (! $this->missingWeek($existing, $n)) {
                continue;
            }

            $date = $startDate->copy();
            if ($spreadDates && $n > 1) {
                $date = $startDate->copy()->addDays($n - 1);
                if ($date->gt($finishDate)) {
                    $date = $finishDate->copy();
                } // clamp
            }

            if (! $dry) {
                LeagueWeek::create([
                    'event_id' => $E->id,
                    'league_id' => $E->league?->id, // keep legacy link if present
                    'week_number' => $n,
                    'label' => $labelPrefix.' '.$n,
                    'date' => $date->toDateString(),
                ]);
            }
            $added++;
        }

        return $added;
    }
}
