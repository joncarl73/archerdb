<?php

namespace App\Services;

use App\Models\Company;

class PricingService
{
    /**
     * Resolve per-participant flat fee (in cents) for a given company and context.
     * $context: 'league' | 'competition'
     */
    public static function participantFeeCents(?Company $company, string $context = 'league'): int
    {
        // Fallbacks
        $defaultLeagueFee = (int) config('pricing.defaults.league_participant_fee_cents', 200);
        $defaultCompFee = (int) config('pricing.defaults.competition_participant_fee_cents', 200);

        $tier = $company?->pricingTier;
        if (! $tier || ! $tier->is_active) {
            return $context === 'competition' ? $defaultCompFee : $defaultLeagueFee;
        }

        return $context === 'competition'
            ? (int) $tier->competition_participant_fee_cents
            : (int) $tier->league_participant_fee_cents;
    }

    public static function currency(?Company $company): string
    {
        return $company?->pricingTier?->currency ?: config('pricing.defaults.currency', 'usd');
    }
}
