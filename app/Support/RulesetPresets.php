<?php

namespace App\Support;

final class RulesetPresets
{
    public static function all(): array
    {
        return [
            'custom' => [
                'label' => 'Custom (set everything manually)',
                'org' => null,
                'scoring_csv' => null,
                'x_value' => null,
                'distances_csv' => null,
            ],

            'world_archery_target' => [
                'label' => 'World Archery (Target)',
                'org' => 'WA',
                'scoring_csv' => '1,2,3,4,5,6,7,8,9,10',
                'x_value' => 10,
                'distances_csv' => '18,50,60',
            ],

            'usa_archery_target' => [
                'label' => 'USA Archery (Target)',
                'org' => 'USAA',
                'scoring_csv' => '1,2,3,4,5,6,7,8,9,10',
                'x_value' => 10,
                'distances_csv' => '18,50,60',
            ],

            'nfaa_indoor_300' => [
                'label' => 'NFAA Indoor (300 Round)',
                'org' => 'NFAA',
                'scoring_csv' => '4,5',
                'x_value' => 5,
                'distances_csv' => '18',
            ],

            'asa_3d' => [
                'label' => 'ASA 3D',
                'org' => 'ASA',
                'scoring_csv' => '5,8,10,12,14',
                'x_value' => 14,
                'distances_csv' => '20,30,40,45,50',
            ],

            'las_classic' => [
                'label' => 'LAS Classic',
                'org' => 'LAS',
                'scoring_csv' => '1,2,3,4,5,6,7,8,9,10',
                'x_value' => 11,
                'distances_csv' => '18',
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        $all = self::all();

        return $all[$key] ?? null;
    }
}
