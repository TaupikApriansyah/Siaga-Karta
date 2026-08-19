<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if(!Schema::hasTable('regions')) return;
        $data=require database_path('data/bandung_regions.php');
        $now=now();

        DB::table('regions')->updateOrInsert(['code'=>$data['city']['code']], [
            'short_code'=>$data['city']['short_code'],'name'=>$data['city']['name'],'level'=>'kota','parent_id'=>null,
            'geojson_name'=>mb_strtoupper($data['city']['name']),'is_active'=>true,'updated_at'=>$now,
        ]);
        $cityId=DB::table('regions')->where('code',$data['city']['code'])->value('id');

        foreach($data['districts'] as $districtData) {
            DB::table('regions')->updateOrInsert(['code'=>$districtData['code']], [
                'short_code'=>$districtData['short_code'],'name'=>$districtData['name'],'level'=>'kecamatan','parent_id'=>$cityId,
                'geojson_name'=>mb_strtoupper($districtData['name']),'is_active'=>true,'updated_at'=>$now,
            ]);
            $districtId=DB::table('regions')->where('code',$districtData['code'])->value('id');

            foreach($districtData['villages'] as [$suffix,$name]) {
                $code=$districtData['code'].'.'.$suffix;
                $payload=[
                    'short_code'=>strtoupper(preg_replace('/[^A-Z0-9]+/i','',$name)),
                    'name'=>$name,'level'=>'kelurahan','parent_id'=>$districtId,
                    'geojson_name'=>mb_strtoupper($name),'is_active'=>true,'updated_at'=>$now,
                ];
                if($code==='32.73.05.1002') $payload += ['rt_count'=>11,'rw_count'=>11];
                DB::table('regions')->updateOrInsert(['code'=>$code],$payload);
            }
        }

        // Pastikan counter sinkronisasi yang dipakai semua aksi create/update selalu tersedia.
        if(Schema::hasTable('system_revisions')) {
            foreach(['operations','finance','users','settings'] as $scope) {
                DB::table('system_revisions')->updateOrInsert(['scope'=>$scope], ['updated_at'=>$now]);
            }
        }
    }

    public function down(): void
    {
        // Master wilayah adalah reference data. Rollback tidak menghapusnya agar relasi laporan/user tidak rusak.
    }
};
