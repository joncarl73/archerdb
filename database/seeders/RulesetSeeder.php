<?php

namespace Database\Seeders;

use App\Models\Ruleset;
use Illuminate\Database\Seeder;

class RulesetSeeder extends Seeder
{
    public function run(): void
    {
        $defs = [];

        // World Archery Target (Indoor + Outdoor)
        $defs[] = [
            'org' => 'WA',
            'name' => 'World Archery — Target (Indoor/Outdoor)',
            'slug' => 'wa-target',
            'is_system' => true,
            'description' => 'WA 10-zone scoring; indoor 18/25m (40cm faces incl. vertical triple); outdoor 70m/1440; X=10.',
            'schema' => [
                'org' => 'WA',
                'disciplines' => ['target', 'indoor', 'outdoor'],
                'bows_allowed' => ['recurve', 'compound', 'barebow', 'longbow'],
                'target_faces' => [
                    ['id' => '122cm', 'kind' => 'wa_target', 'diameter_cm' => 122, 'zones' => '10'],
                    ['id' => '80cm', 'kind' => 'wa_target', 'diameter_cm' => 80, 'zones' => '10'],
                    ['id' => '40cm_vert3', 'kind' => 'wa_vertical_triple', 'diameter_cm' => 40, 'zones' => '10'],
                ],
                'scoring' => [
                    'mode' => 'wa_10', 'x_rule' => 'counts_10', 'line_cutter_rule' => 'higher_value',
                    'inner_ten_compound' => true, // USA/WA indoor compounds inner-10 convention
                ],
                'rounds' => [
                    ['id' => 'wa_indoor_18', 'name' => 'WA Indoor 18m', 'arrows_per_end' => 3, 'ends' => 20, 'distances_m' => [18], 'face_id' => '40cm_vert3', 'timing_sec_per_end' => 120],
                    ['id' => 'wa_outdoor_70_72', 'name' => 'WA 70m 72-arrow', 'arrows_per_end' => 6, 'ends' => 12, 'distances_m' => [70], 'face_id' => '122cm', 'timing_sec_per_end' => 240],
                    ['id' => 'wa_1440', 'name' => 'WA 1440', 'arrows_per_end' => 6, 'ends' => 24, 'distances_m' => [90, 70, 50, 30], 'face_id' => '122cm'], // simplified
                ],
                'tie_break' => ['order' => ['score', '10_count', 'x_count', 'closest_to_center']],
                'equipment_restrictions' => [],
            ],
        ];

        // USA Archery (inherits WA scoring, inner-10 for compound indoor, outer-10 outdoor)
        $defs[] = [
            'org' => 'USAA',
            'name' => 'USA Archery — Target',
            'slug' => 'usaa-target',
            'is_system' => true,
            'description' => 'USA Archery target rules (indoor inner-10 for compound; outdoor outer-10).',
            'schema' => [
                'org' => 'USAA', 'disciplines' => ['target', 'indoor', 'outdoor'],
                'bows_allowed' => ['recurve', 'compound', 'barebow', 'longbow'],
                'scoring' => [
                    'mode' => 'wa_10',
                    'x_rule' => 'counts_10',
                    'inner_ten_compound' => true,
                ],
                'rounds' => [
                    ['id' => 'usaa_indoor_18', 'name' => 'USAA Indoor 18m', 'arrows_per_end' => 3, 'ends' => 20, 'distances_m' => [18], 'timing_sec_per_end' => 120],
                    ['id' => 'usaa_outdoor_72', 'name' => 'USAA Outdoor 72-arrow', 'arrows_per_end' => 6, 'ends' => 12, 'distances_m' => [50, 60, 70], 'timing_sec_per_end' => 240],
                ],
            ],
        ];

        // Archery GB (adds 5-zone imperial option)
        $defs[] = [
            'org' => 'AGB',
            'name' => 'Archery GB — Target (Metric & Imperial)',
            'slug' => 'agb-target',
            'is_system' => true,
            'description' => 'Metric 10-zone + Imperial 5-zone rounds (e.g., York/Hereford/Warwick/Short Metrics).',
            'schema' => [
                'org' => 'AGB', 'disciplines' => ['target', 'indoor', 'outdoor'],
                'bows_allowed' => ['recurve', 'compound', 'barebow', 'longbow'],
                'scoring' => ['mode' => 'wa_10', 'x_rule' => 'counts_10'],
                'rounds' => [
                    ['id' => 'agb_short_metric', 'name' => 'Short Metric', 'arrows_per_end' => 6, 'ends' => 12, 'distances_m' => [50, 30]],
                    ['id' => 'agb_warwick', 'name' => 'Warwick (imperial 5-zone)', 'arrows_per_end' => 6, 'ends' => 8, 'distances_yards' => [60, 50], 'scoring_override' => ['mode' => 'agb_5']],
                ],
            ],
        ];

        // NFAA Indoor 300
        $defs[] = [
            'org' => 'NFAA',
            'name' => 'NFAA — Indoor 300',
            'slug' => 'nfaa-indoor-300',
            'is_system' => true,
            'description' => '12 ends × 5 arrows = 60; blue/white single or five-spot; 5-4-3 with X for tie-break.',
            'schema' => [
                'org' => 'NFAA', 'disciplines' => ['indoor', 'target'],
                'bows_allowed' => ['recurve', 'compound', 'barebow', 'longbow', 'traditional'],
                'target_faces' => [['id' => 'nfaa_40cm', 'kind' => 'nfaa_single', 'diameter_cm' => 40, 'zones' => '5']],
                'scoring' => ['mode' => 'nfaa_5', 'x_rule' => 'tiebreak_only'],
                'rounds' => [['id' => 'nfaa_300', 'name' => 'NFAA 300', 'arrows_per_end' => 5, 'ends' => 12, 'distances_yards' => [20], 'face_id' => 'nfaa_40cm', 'timing_sec_per_end' => 240]],
            ],
        ];

        // ASA 3D
        $defs[] = [
            'org' => 'ASA',
            'name' => 'ASA — 3D',
            'slug' => 'asa-3d',
            'is_system' => true,
            'description' => '3D scoring 12/10/8/5 with callable upper-12; 14 used for special shoot-downs.',
            'schema' => [
                'org' => 'ASA', 'disciplines' => ['3d'],
                'bows_allowed' => ['recurve', 'compound', 'barebow', 'longbow', 'traditional'],
                'scoring' => ['mode' => 'asa_12_10_8_5', 'x_rule' => 'tiebreak_only'],
                'field_3d_rules' => ['asa_call_upper_12' => true, 'use_14_in_shootdown' => true],
                'rounds' => [['id' => 'asa_3d_20', 'name' => 'ASA 20-target', 'arrows_per_target' => 1, 'targets' => 20, 'unknown_distance' => true]],
            ],
        ];

        // IBO 3D
        $defs[] = [
            'org' => 'IBO',
            'name' => 'IBO — 3D',
            'slug' => 'ibo-3d',
            'is_system' => true,
            'description' => '3D scoring 11/10/8/5; 11s commonly used for tie-breaks.',
            'schema' => [
                'org' => 'IBO', 'disciplines' => ['3d'],
                'bows_allowed' => ['recurve', 'compound', 'barebow', 'longbow', 'traditional'],
                'scoring' => ['mode' => 'ibo_11_10_8_5', 'x_rule' => 'tiebreak_only'],
                'rounds' => [['id' => 'ibo_3d_20', 'name' => 'IBO 3D 20-target', 'arrows_per_target' => 1, 'targets' => 20]],
            ],
        ];

        // IFAA field/hunter/animal
        $defs[] = [
            'org' => 'IFAA',
            'name' => 'IFAA — Field/Hunter/Animal',
            'slug' => 'ifaa-field-suite',
            'is_system' => true,
            'description' => 'Field/Hunter 5-4-3; Animal round progressive 20/16/12 (kill) or 18/14/10 (wound) by arrow order.',
            'schema' => [
                'org' => 'IFAA', 'disciplines' => ['field', '3d', 'indoor'],
                'bows_allowed' => ['recurve', 'compound', 'barebow', 'longbow', 'traditional'],
                'scoring' => ['mode' => 'ifaa_5_4_3', 'x_rule' => 'tiebreak_only'],
                'field_3d_rules' => ['ifaa_animal_progression' => ['kill' => [20, 16, 12], 'wound' => [18, 14, 10]]],
                'rounds' => [
                    ['id' => 'ifaa_field_28', 'name' => 'IFAA Field 28-target', 'arrows_per_target' => 4, 'targets' => 28],
                    ['id' => 'ifaa_hunter_28', 'name' => 'IFAA Hunter 28-target', 'arrows_per_target' => 4, 'targets' => 28, 'face_color' => 'black/white_center'],
                    ['id' => 'ifaa_animal_28', 'name' => 'IFAA Animal 28-target', 'arrows_per_target' => '1–3', 'targets' => 28, 'progressive' => true],
                ],
            ],
        ];

        foreach ($defs as $d) {
            Ruleset::query()->updateOrCreate(
                ['slug' => $d['slug']],
                [
                    'org' => $d['org'],
                    'name' => $d['name'],
                    'slug' => $d['slug'],
                    'description' => $d['description'] ?? null,
                    'is_system' => $d['is_system'] ?? false,
                    'schema' => $d['schema'],
                ]
            );
        }
    }
}
