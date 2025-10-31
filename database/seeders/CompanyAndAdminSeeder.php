<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompanyAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        // ---- A) Ensure Administrator user exists FIRST (so FK can reference it)
        $adminName = env('ADMIN_NAME', 'Jon Carl');
        $adminEmail = env('ADMIN_EMAIL', 'jcarl@sidium.com');
        $adminPass = env('ADMIN_PASSWORD', 'letmein123!');

        /** @var User $admin */
        $admin = User::query()->firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => Hash::make($adminPass),
                'role' => UserRole::Administrator ?? 'administrator',
                'email_verified_at' => now(),
                'remember_token' => Str::random(20),
            ]
        );

        // ---- B) Companies table state
        $isEmpty = Company::query()->count() === 0;

        // If empty, (optionally) reset AUTO_INCREMENT to 1 BEFORE any transaction/commit logic.
        if ($isEmpty) {
            try {
                $driver = DB::getDriverName();
                if (in_array($driver, ['mysql', 'mariadb'])) {
                    // DDL: must NOT be inside a transaction
                    DB::statement('ALTER TABLE companies AUTO_INCREMENT = 1');
                }
            } catch (\Throwable $e) {
                // Non-fatal if not supported
            }
        }

        // ---- C) Create/Upsert the ArcherDB company with owner_user_id = admin->id
        // (No transaction; MySQL DDL already committed and we don't need multi-step atomicity here.)
        if ($isEmpty) {
            // Allow explicit ID on empty table so ArcherDB becomes id=1
            Company::unguard();
            $company = Company::query()->create([
                'id' => 1,
                'owner_user_id' => $admin->id,              // FK valid now
                'company_name' => 'ArcherDB',
                'legal_name' => 'ArcherDB',
                'support_email' => 'support@archerdb.cloud',
                'phone' => '1112223333',
                'address_line1' => '210 Church Ave',
                'city' => 'Ephrata',
                'state_region' => 'PA',
                'postal_code' => '17522',
                'country' => 'US',
                'industry' => 'website',
                'website' => 'https://archerdb.cloud',
            ]);
            Company::reguard();
        } else {
            // Upsert by a stable unique key you have (company_name here; use slug if that's your unique)
            $company = Company::query()->firstOrCreate(
                ['company_name' => 'ArcherDB'],
                [
                    'legal_name' => 'ArcherDB',
                    'support_email' => 'support@archerdb.cloud',
                    'phone' => '1112223333',
                    'address_line1' => '210 Church Ave',
                    'city' => 'Ephrata',
                    'state_region' => 'PA',
                    'postal_code' => '17522',
                    'country' => 'US',
                    'industry' => 'website',
                    'website' => 'https://archerdb.cloud',
                ]
            );

            if ($company->owner_user_id !== $admin->id) {
                $company->owner_user_id = $admin->id;
                $company->save();
            }
        }

        // ---- D) Align the admin’s company_id to ArcherDB
        if ($admin->company_id !== $company->id) {
            $admin->company_id = $company->id;
            $admin->save();
        }
    }
}
