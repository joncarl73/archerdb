<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlatformSellerSeeder extends Seeder
{
    public function run(): void
    {
        $id = (int) config('services.platform.seller_id', 0);
        if ($id <= 0) {
            return;
        }

        // Use query builder to avoid fillable/guarded issues and to allow setting id explicitly.
        DB::table('sellers')->updateOrInsert(
            ['id' => $id],
            [
                // Provide sane defaults; adjust columns to match your sellers table.
                'owner_id' => env('PLATFORM_OWNER_USER_ID'),
                'name' => 'ArcherDB Platform',
                'stripe_account_id' => null,            // platform (not a connected account)
                'default_platform_fee_cents' => 0,      // not used for participant imports
                'active' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
