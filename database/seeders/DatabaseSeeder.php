<?php

namespace Database\Seeders;

use App\Models\Ambulance;
use App\Models\Driver;
use App\Models\InfaqSetting;
use App\Models\Program;
use App\Models\Region;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $demoMode=filter_var(env('DEMO_MODE', false), FILTER_VALIDATE_BOOL);
        if (app()->environment('production') && !$demoMode) {
            $this->command?->warn('Seeder demo dilewati karena DEMO_MODE=false.');
            return;
        }

        $password=(string)env('DEMO_PASSWORD','Rajawali21');
        if(strlen($password)<8) {
            throw new \RuntimeException('DEMO_PASSWORD minimal 8 karakter.');
        }

        $kotaRegion=Region::where('code','32.73')->first();
        $andirRegion=Region::where('code','32.73.05')->first();
        $dungusRegion=Region::where('code','32.73.05.1002')->first();

        $kota=User::updateOrCreate(['username'=>'kota'],[
            'name'=>'Karang Taruna Kota Bandung','email'=>'kota@siagakarta.local',
            'role'=>'kota','region_id'=>$kotaRegion?->id,'is_active'=>true,'password'=>$password,
        ]);
        User::updateOrCreate(['username'=>'kecamatan'],[
            'name'=>'Karang Taruna Kecamatan Andir','email'=>'kecamatan@siagakarta.local',
            'role'=>'kecamatan','region_id'=>$andirRegion?->id,'is_active'=>true,'password'=>$password,
        ]);
        User::updateOrCreate(['username'=>'kelurahan'],[
            'name'=>'Karang Taruna Kelurahan Dungus Cariang','email'=>'kelurahan@siagakarta.local',
            'role'=>'kelurahan','region_id'=>$dungusRegion?->id,'is_active'=>true,'password'=>$password,
        ]);
        User::whereIn('username',['admin','petugas','karta'])->delete();
        $this->command?->info('Akun demo sinkron: kota, kecamatan, dan kelurahan menggunakan password DEMO_PASSWORD yang sama.');

        Ambulance::updateOrCreate(['code'=>'KT-01'],['plate_number'=>'Z 1992 AB','capacity'=>2,'status'=>'tersedia']);
        Ambulance::updateOrCreate(['code'=>'KT-02'],['plate_number'=>'Z 8812 XY','capacity'=>1,'status'=>'tersedia']);
        Driver::updateOrCreate(['code'=>'D-01'],['name'=>'Agus Riyanto','status'=>'aktif']);
        Driver::updateOrCreate(['code'=>'D-02'],['name'=>'Hendra','status'=>'aktif']);

        $p1=Program::updateOrCreate(['code'=>'PRG-01'],[
            'name'=>'Bantuan Warga Sakit Menahun','target_amount'=>5000000,
            'collected_amount'=>0,'distributed_amount'=>0,'status'=>'aktif','image_url'=>'/siaga-karta-community.png',
        ]);
        Program::updateOrCreate(['code'=>'PRG-02'],[
            'name'=>'Santunan Yatim Piatu','target_amount'=>10000000,
            'collected_amount'=>0,'distributed_amount'=>0,'status'=>'aktif','image_url'=>'/hero-ambulance.png',
        ]);

        Transaction::updateOrCreate(['code'=>'TRX-DEMO-001'],[
            'type'=>'pemasukan','category'=>'donasi_program','amount'=>1500000,'status'=>'verified','source'=>'internal',
            'program_id'=>$p1->id,'transaction_date'=>now()->startOfMonth()->toDateString(),
            'created_by'=>$kota->id,'verified_by'=>$kota->id,'verified_at'=>now(),
        ]);
        Transaction::updateOrCreate(['code'=>'TRX-DEMO-002'],[
            'type'=>'pengeluaran','category'=>'bbm','amount'=>250000,'status'=>'verified','source'=>'internal',
            'program_id'=>null,'transaction_date'=>now()->startOfMonth()->addDays(4)->toDateString(),
            'created_by'=>$kota->id,'verified_by'=>$kota->id,'verified_at'=>now(),
        ]);

        $totals=Transaction::where('program_id',$p1->id)->where('status','verified')
            ->selectRaw("coalesce(sum(case when type='pemasukan' then amount else 0 end),0) as incoming, coalesce(sum(case when type='pengeluaran' then amount else 0 end),0) as outgoing")
            ->first();
        $p1->forceFill(['collected_amount'=>(int)$totals->incoming,'distributed_amount'=>(int)$totals->outgoing])->save();

        InfaqSetting::firstOrCreate([], [
            'title'=>'Infaq Siaga Karta',
            'description'=>'Dukungan operasional pelayanan sosial dan ambulans Karang Taruna.',
            'payment_instructions'=>'Gunakan QR atau rekening resmi, lalu unggah bukti pembayaran melalui portal warga.',
            'is_active'=>false,'updated_by'=>$kota->id,
        ]);
    }
}
