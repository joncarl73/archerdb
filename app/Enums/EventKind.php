<?php

namespace App\Enums;

enum EventKind: string
{
    case SingleDay = 'single_day';   // ends_on must equal starts_on
    case MultiDay = 'multi_day';    // 2–5 days, same week/weekend typically
    case Clinic = 'clinic';
    case Social = 'social';
}
