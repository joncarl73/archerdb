<?php

// app/Enums/LaneBreakdown.php

namespace App\Enums;

enum LaneBreakdown: string
{
    case Single = 'single';
    case AB = 'ab';
    case ABCD = 'abcd';

    public function positionsPerLane(): int
    {
        return match ($this) {
            self::Single => 1,
            self::AB => 2,
            self::ABCD => 4,
        };
    }
}
