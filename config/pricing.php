<?php

return [
    'defaults' => [
        'league_participant_fee_cents' => env('DEFAULT_LEAGUE_PARTICIPANT_FEE_CENTS', 200),
        'competition_participant_fee_cents' => env('DEFAULT_COMPETITION_PARTICIPANT_FEE_CENTS', 200),
        'currency' => env('DEFAULT_PRICING_CURRENCY', 'usd'),
    ],
];
