<?php

use Symfony\Component\Intl\Countries;

return [
    // Default selection (change if you want another default)
    'default' => 'US',

    // Localized "code => name" pairs (e.g., ['US' => 'United States', ...])
    // This uses the app locale, so it will translate names automatically.
    'list' => Countries::getNames(app()->getLocale()),
];
