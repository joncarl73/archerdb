<?php

namespace App\Services;

use App\Models\Event;
use App\Models\LeagueParticipant;
use Illuminate\Support\Facades\DB;

class LaneAssignmentService
{
    /**
     * Assign lanes for MULTI-DAY events.
     * - Honors preferred line time when capacity allows.
     * - Uses lane map capacity; falls back to line time capacity; else waitlist.
     * - Deterministic: sorts by created_at, id.
     */
    public function assign(Event $event, bool $resetExisting = false): array
    {
        if ($event->kind !== 'multi.day') {
            return ['assigned' => 0, 'waitlisted' => 0, 'skipped' => 0, 'reason' => 'Not multi.day'];
        }

        return DB::transaction(function () use ($event, $resetExisting) {
            // 1) Build capacity model
            $lineTimes = $event->lineTimes()->with('lanes')->orderBy('starts_at')->get();
            $cap = [];      // remaining capacity per line_time_id
            $grid = [];     // [line_time_id][lane_number][slot] => remaining int

            foreach ($lineTimes as $lt) {
                $ltCap = 0;
                if ($lt->lanes->count()) {
                    foreach ($lt->lanes as $lm) {
                        $grid[$lt->id][$lm->lane_number][$lm->slot ?: '_'] = (int) $lm->capacity;
                        $ltCap += (int) $lm->capacity;
                    }
                } else {
                    $ltCap = (int) ($lt->capacity ?? 0);
                }
                $cap[$lt->id] = $ltCap;
            }

            // 2) Fetch participants for this event (tune your filter as needed)
            $partsQ = LeagueParticipant::query()->where('event_id', $event->id);
            if ($resetExisting) {
                $partsQ->update([
                    'assigned_line_time_id' => null,
                    'assigned_lane_number' => null,
                    'assigned_lane_slot' => null,
                    'assignment_status' => 'pending',
                ]);
            }

            $participants = $partsQ
                // Adjust filter: e.g., ->whereNotNull('paid_at') if you have payments
                ->orderBy('created_at')->orderBy('id')
                ->get(['id', 'event_division_id', 'preferred_line_time_id', 'assigned_line_time_id', 'assigned_lane_number', 'assigned_lane_slot', 'assignment_status']);

            $assigned = 0;
            $waitlisted = 0;
            $skipped = 0;

            foreach ($participants as $p) {
                // skip already assigned unless resetExisting
                if (! $resetExisting && $p->assignment_status === 'assigned') {
                    $skipped++;

                    continue;
                }

                // choose a line time
                $choice = $this->pickLineTime($p->preferred_line_time_id, $cap);

                if (! $choice) {
                    // no capacity anywhere
                    $p->assigned_line_time_id = null;
                    $p->assigned_lane_number = null;
                    $p->assigned_lane_slot = null;
                    $p->assignment_status = 'waitlist';
                    $p->save();
                    $waitlisted++;

                    continue;
                }

                // consume capacity, optionally pick lane/slot
                $lane = $this->pickLaneSlot($grid, $choice);
                if ($lane) {
                    [$laneNum, $slot] = $lane;
                    $p->assigned_line_time_id = $choice;
                    $p->assigned_lane_number = $laneNum;
                    $p->assigned_lane_slot = $slot;
                    $p->assignment_status = 'assigned';
                    $p->save();
                    $assigned++;
                } else {
                    // fall back to line-time level capacity
                    $p->assigned_line_time_id = $choice;
                    $p->assigned_lane_number = null;
                    $p->assigned_lane_slot = null;
                    $p->assignment_status = 'assigned';
                    $p->save();
                    $assigned++;
                }

                // decrement capacity
                if (isset($cap[$choice]) && $cap[$choice] > 0) {
                    $cap[$choice]--;
                }
            }

            return compact('assigned', 'waitlisted', 'skipped');
        });
    }

    /** Choose preferred if capacity exists; else smallest-fill line time. */
    private function pickLineTime(?int $preferred, array $cap): ?int
    {
        if ($preferred && ($cap[$preferred] ?? 0) > 0) {
            return $preferred;
        }
        // pick any with capacity, bias to earliest id
        $candidates = array_filter($cap, fn ($v) => $v > 0);
        if (! $candidates) {
            return null;
        }
        // choose the one with max remaining (or earliest if tie)
        arsort($candidates);

        return (int) array_key_first($candidates);
    }

    /** Returns [lane_number, slot] or null if no lane grid. Consumes capacity in $grid by reference. */
    private function pickLaneSlot(array &$grid, int $lineTimeId): ?array
    {
        if (! isset($grid[$lineTimeId])) {
            return null;
        }
        ksort($grid[$lineTimeId]); // stable lane order
        foreach ($grid[$lineTimeId] as $laneNum => &$slots) {
            ksort($slots);
            foreach ($slots as $slot => $rem) {
                if ($rem > 0) {
                    $slots[$slot] = $rem - 1;

                    return [(int) $laneNum, $slot === '_' ? null : $slot];
                }
            }
        }

        return null;
    }
}
