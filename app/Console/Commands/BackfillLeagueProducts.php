<?php

// app/Console/Commands/BackfillLeagueProducts.php

namespace App\Console\Commands;

use App\Models\League;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Console\Command;

class BackfillLeagueProducts extends Command
{
    protected $signature = 'marketplace:backfill-league-products';

    public function handle(): int
    {
        $leagues = League::query()->get();
        foreach ($leagues as $l) {
            // seller per league owner
            $seller = Seller::firstOrCreate(
                ['owner_id' => $l->owner_id, 'name' => $l->location ?: 'Organizer '.$l->owner_id],
                ['default_platform_fee_bps' => 500]
            );
            // product if missing
            $exists = Product::where('productable_type', League::class)->where('productable_id', $l->id)->first();
            if (! $exists) {
                Product::create([
                    'seller_id' => $seller->id,
                    'productable_type' => League::class,
                    'productable_id' => $l->id,
                    'name' => $l->title.' registration',
                    'currency' => $l->currency,
                    'price_cents' => $l->price_cents ?? 0,
                    'platform_fee_bps' => null,                 // use seller default unless you want league-specific override
                    'settlement_mode' => $l->type === 'closed' ? 'closed' : 'open',
                    'metadata' => ['league_public_uuid' => $l->public_uuid],
                    'is_active' => true,
                ]);
            }
        }
        $this->info('Backfill complete.');

        return 0;
    }
}
