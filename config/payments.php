<?php

return [
    'pro_price_id' => env('STRIPE_PRO_PRICE_ID', null),
    'pro_product_name' => env('PRO_PRODUCT_NAME', config('app.name').' Pro'),
    'pro_price_cents_year' => env('PRO_PRICE_CENTS_YEAR', 599),
    'platform_owner_user_id' => env('PLATFORM_OWNER_USER_ID', null),
    'default_platform_fee_bps' => env('DEFAULT_PLATFORM_FEE_BPS', 250),
];
