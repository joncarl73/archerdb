<?php

namespace App\Enums;

enum EventScoringMode: string
{
    case Personal = 'personal'; // personal device
    case Kiosk = 'kiosk';    // tablet/kiosk

    /**
     * Map a League.scoring_mode (string or enum) to EventScoringMode
     * - leagues: 'personal_device' | 'tablet'
     */
    public static function fromLeague(null|string|\UnitEnum $leagueMode): self
    {
        $value = $leagueMode instanceof \UnitEnum ? $leagueMode->value : (string) $leagueMode;

        return ($value === 'tablet') ? self::Kiosk : self::Personal;
    }
}
