<?php

namespace Database\Seeders;

// database/seeders/ManufacturerSeeder.php
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ManufacturerSeeder extends Seeder {
    public function run(): void {
        $rows = [
            ['name'=>'Hoyt','categories'=>['bow'],'website'=>'https://hoyt.com','country'=>'US'],
            ['name'=>'Mathews','categories'=>['bow'],'website'=>'https://mathewsinc.com','country'=>'US'],
            ['name'=>'PSE','categories'=>['bow'],'website'=>'https://psearchery.com','country'=>'US'],
            ['name'=>'Win&Win','categories'=>['bow'],'website'=>'https://win-archery.com','country'=>'KR'],
            ['name'=>'Easton','categories'=>['arrow'],'website'=>'https://eastonarchery.com','country'=>'US'],
            ['name'=>'Gold Tip','categories'=>['arrow'],'website'=>'https://goldtip.com','country'=>'US'],
            ['name'=>'Carbon Express','categories'=>['arrow'],'website'=>'https://carbonexpressarrows.com','country'=>'US'],
            ['name'=>'Shibuya','categories'=>['sight'],'website'=>'https://shibuya-archery.com','country'=>'JP'],
            ['name'=>'Beiter','categories'=>['rest','plunger','accessory'],'website'=>'https://www.wernerbeiter.com','country'=>'DE'],
            ['name'=>'Doinker','categories'=>['stabilizer'],'website'=>'https://doinker.com','country'=>'US'],
        ];
        foreach ($rows as $r) {
            DB::table('manufacturers')->updateOrInsert(
                ['name'=>$r['name']],
                ['categories'=>json_encode($r['categories']), 'website'=>$r['website'], 'country'=>$r['country'], 'created_at'=>now(),'updated_at'=>now()]
            );
        }
    }
}
