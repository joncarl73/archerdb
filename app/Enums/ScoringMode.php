<?php

// app/Enums/ScoringMode.php

namespace App\Enums;

enum ScoringMode: string
{
    case Personal = 'personal';
    case Kiosk = 'kiosk';
    case Either = 'either';
}
