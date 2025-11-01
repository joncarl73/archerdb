<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RulesetLookupsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Generic upsert for tables keyed by 'key' + simple 'label'
        $this->upsertSimple('disciplines', [
            ['key' => 'target',  'label' => 'Target'],
            ['key' => 'indoor',  'label' => 'Indoor'],
            ['key' => 'outdoor', 'label' => 'Outdoor'],
            ['key' => 'field',   'label' => 'Field'],
            ['key' => '3d',      'label' => '3D'],
        ]);

        $this->upsertSimple('bow_types', [
            ['key' => 'recurve', 'label' => 'Recurve'],
            ['key' => 'compound', 'label' => 'Compound'],
            ['key' => 'barebow', 'label' => 'Barebow'],
            ['key' => 'longbow', 'label' => 'Longbow'],
        ]);

        // Target faces have extra columns
        $this->upsertFaces([
            ['key' => '122cm',        'label' => '122cm WA',            'kind' => 'wa_target',          'diameter_cm' => 122, 'zones' => '10'],
            ['key' => '80cm',         'label' => '80cm WA',             'kind' => 'wa_target',          'diameter_cm' => 80,  'zones' => '10'],
            ['key' => '40cm_vert3',   'label' => '40cm Vertical Triple', 'kind' => 'wa_vertical_triple', 'diameter_cm' => 40,  'zones' => '10'],
        ]);

        $this->upsertSimple('divisions', [
            ['key' => 'adult',   'label' => 'Adult'],
            ['key' => 'junior',  'label' => 'Junior'],
            ['key' => 'masters', 'label' => 'Masters'],
        ]);

        // Using "ruleset classes" to avoid PHP reserved word conflicts
        $this->upsertSimple('classes', [
            ['key' => 'male',   'label' => 'Male'],
            ['key' => 'female', 'label' => 'Female'],
            ['key' => 'open',   'label' => 'Open'],
        ]);
    }

    /**
     * Upsert helper for tables with columns: key (unique), label, timestamps.
     */
    protected function upsertSimple(string $table, array $rows): void
    {
        $now = now();

        foreach ($rows as $row) {
            DB::table($table)->updateOrInsert(
                ['key' => $row['key']],
                [
                    'label' => $row['label'],
                    'updated_at' => $now,
                    // Create created_at if missing
                    'created_at' => DB::raw('COALESCE(created_at, "'.$now.'")'),
                ]
            );
        }
    }

    /**
     * Upsert helper for target_faces (key, label, kind, diameter_cm, zones, timestamps).
     */
    protected function upsertFaces(array $rows): void
    {
        $now = now();

        foreach ($rows as $row) {
            DB::table('target_faces')->updateOrInsert(
                ['key' => $row['key']],
                [
                    'label' => $row['label'],
                    'kind' => $row['kind'],
                    'diameter_cm' => $row['diameter_cm'],
                    'zones' => $row['zones'],
                    'updated_at' => $now,
                    'created_at' => DB::raw('COALESCE(created_at, "'.$now.'")'),
                ]
            );
        }
    }
}
