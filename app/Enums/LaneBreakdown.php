<?php

// app/Enums/LaneBreakdown.php

namespace App\Enums;

enum LaneBreakdown: string
{
    case Single = 'single';
    case AB = 'ab';
    case ABCD = 'abcd';

    /** Human label for settings UIs */
    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single lane (1 per lane)',
            self::AB => 'A/B split (2 per lane)',
            self::ABCD => 'A/B/C/D split (4 per lane)',
        };
    }

    /** How many shooting positions per physical lane */
    public function positionsPerLane(): int
    {
        return match ($this) {
            self::Single => 1,
            self::AB => 2,
            self::ABCD => 4,
        };
    }

    /** Short letters to suffix lane numbers with */
    public function letters(): array
    {
        return match ($this) {
            self::Single => [],               // no letters
            self::AB => ['A', 'B'],
            self::ABCD => ['A', 'B', 'C', 'D'],
        };
    }
}
