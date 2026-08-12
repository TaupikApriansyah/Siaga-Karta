<?php

namespace Database\Seeders;

use App\Models\Ambulance;
use App\Models\Driver;
use App\Models\InfaqSetting;
use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('Demo seeder dilewati di production. Buat akun Admin melalui prosedur bootstrap yang terkontrol.');
            return;
        }

        $admin = User::where('email', 'admin@siagakarta.local')->first();
        if (!$admin) {
            $password = (string) env('SEED_ADMIN_PASSWORD', Str::password(20));
            $admin = User::create([
                'name'=>'Admin Kelurahan','email'=>'admin@siagakarta.local','username'=>'admin',
                'role'=>'admin','is_active'=>true,'password'=>$password,
            ]);
            $this->command?->info("Admin development dibuat. Username: admin | Password: {$password}");
        }

        $petugas = User::where('email', 'petugas@siagakarta.local')->first();
        if (!$petugas) {
            $password = (string) env('SEED_PETUGAS_PASSWORD', Str::password(20));
            $petugas = User::create([
                'name'=>'Petugas Karang Taruna','email'=>'petugas@siagakarta.local','username'=>'petugas',
                'role'=>'petugas','is_active'=>true,'password'=>$password,
            ]);
            $this->command?->info("Petugas development dibuat. Username: petugas | Password: {$password}");
        }

        $karta = User::where('email', 'karta@siagakarta.local')->first();
        if (!$karta) {
            $password = (string) env('SEED_KARTA_PASSWORD', Str::password(20));
            User::create([
                'name'=>'Pengelola Karta','email'=>'karta@siagakarta.local','username'=>'karta',
                'role'=>'karta','is_active'=>true,'password'=>$password,
            ]);
            $this->command?->info("Karta development dibuat. Username: karta | Password: {$password}");
        }

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
            'created_by'=>$admin->id,'verified_by'=>$admin->id,'verified_at'=>now(),
        ]);
        Transaction::updateOrCreate(['code'=>'TRX-DEMO-002'],[
            'type'=>'pengeluaran','category'=>'bbm','amount'=>250000,'status'=>'verified','source'=>'internal',
            'program_id'=>null,'transaction_date'=>now()->startOfMonth()->addDays(4)->toDateString(),
            'created_by'=>$admin->id,'verified_by'=>$admin->id,'verified_at'=>now(),
        ]);

        $totals=Transaction::where('program_id',$p1->id)->where('status','verified')
            ->selectRaw("coalesce(sum(case when type='pemasukan' then amount else 0 end),0) as incoming, coalesce(sum(case when type='pengeluaran' then amount else 0 end),0) as outgoing")
            ->first();
        $p1->forceFill(['collected_amount'=>(int)$totals->incoming,'distributed_amount'=>(int)$totals->outgoing])->save();

        InfaqSetting::firstOrCreate([], [
            'title'=>'Infaq Siaga Karta',
            'description'=>'Dukungan operasional pelayanan sosial dan ambulans Karang Taruna.',
            'payment_instructions'=>'Gunakan QR atau rekening resmi, lalu unggah bukti pembayaran melalui portal warga.',
            'is_active'=>false,'updated_by'=>$admin->id,
        ]);
    }
}
