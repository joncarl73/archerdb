<?php

namespace App\Enums;

enum EventKind: string
{
    case LeagueClosed = 'league.closed';
    case LeagueOpen = 'league.open';
    case RemoteLeague = 'remote.league';
    case SingleDay = 'single.day';
    case MultiDay = 'multi.day';
}
