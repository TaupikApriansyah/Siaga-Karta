<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BandungRegionSeeder extends Seeder
{
    public function run(): void
    {
        $data=require database_path('data/bandung_regions.php');

        $city=Region::updateOrCreate(['code'=>$data['city']['code']], [
            'short_code'=>$data['city']['short_code'],
            'name'=>$data['city']['name'],
            'level'=>'kota','parent_id'=>null,
            'geojson_name'=>Str::upper($data['city']['name']),
            'is_active'=>true,
        ]);

        $districtCount=0; $villageCount=0;
        foreach($data['districts'] as $districtData) {
            $district=Region::updateOrCreate(['code'=>$districtData['code']], [
                'short_code'=>$districtData['short_code'],
                'name'=>$districtData['name'],
                'level'=>'kecamatan','parent_id'=>$city->id,
                'geojson_name'=>Str::upper($districtData['name']),
                'is_active'=>true,
            ]);
            $districtCount++;

            foreach($districtData['villages'] as [$suffix,$name]) {
                $code=$districtData['code'].'.'.$suffix;
                $attributes=[
                    'short_code'=>strtoupper(preg_replace('/[^A-Z0-9]+/i','',$name)),
                    'name'=>$name,
                    'level'=>'kelurahan','parent_id'=>$district->id,
                    'geojson_name'=>Str::upper($name),
                    'is_active'=>true,
                ];
                // Nilai pilot tetap dipertahankan untuk akun demo yang sudah dipakai.
                if($code==='32.73.05.1002') $attributes += ['rt_count'=>11,'rw_count'=>11];
                Region::updateOrCreate(['code'=>$code],$attributes);
                $villageCount++;
            }
        }

        $this->command?->info("Master wilayah Bandung sinkron: {$districtCount} kecamatan, {$villageCount} kelurahan.");
    }
}
