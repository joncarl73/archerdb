<?php

namespace App\Enums;

enum EventKind: string
{
    case League = 'league';         // legacy leagues
    case RemoteLeague = 'remote_league';  // “Back Yard Challenge”
    case SingleDay = 'single_day';
    case MultiDay = 'multi_day';
}
