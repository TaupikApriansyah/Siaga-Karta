<?php
namespace Database\Seeders;
use App\Models\Ambulance;
use App\Models\Driver;
use App\Models\InfaqSetting;
use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin=User::updateOrCreate(['email'=>'admin@siagakarta.local'],['name'=>'Admin Kelurahan','username'=>'admin','role'=>'admin','is_active'=>true,'password'=>'AdminSiagaKarta!2026']);
        $petugas=User::updateOrCreate(['email'=>'petugas@siagakarta.local'],['name'=>'Petugas Karang Taruna','username'=>'petugas','role'=>'petugas','is_active'=>true,'password'=>'PetugasSiagaKarta!2026']);
        Ambulance::updateOrCreate(['code'=>'KT-01'],['plate_number'=>'Z 1992 AB','capacity'=>2,'status'=>'tersedia']);
        Ambulance::updateOrCreate(['code'=>'KT-02'],['plate_number'=>'Z 8812 XY','capacity'=>1,'status'=>'tersedia']);
        Driver::updateOrCreate(['code'=>'D-01'],['name'=>'Agus Riyanto','status'=>'aktif']); Driver::updateOrCreate(['code'=>'D-02'],['name'=>'Hendra','status'=>'aktif']);
        $p1=Program::updateOrCreate(['code'=>'PRG-01'],['name'=>'Bantuan Warga Sakit Menahun','target_amount'=>5000000,'collected_amount'=>3500000,'distributed_amount'=>1000000,'status'=>'aktif','image_url'=>'/siaga-karta-community.png']);
        Program::updateOrCreate(['code'=>'PRG-02'],['name'=>'Santunan Yatim Piatu','target_amount'=>10000000,'collected_amount'=>8500000,'distributed_amount'=>8000000,'status'=>'aktif','image_url'=>'/hero-ambulance.png']);
        Transaction::updateOrCreate(['code'=>'TRX-001'],['type'=>'pemasukan','category'=>'donasi_program','amount'=>1500000,'status'=>'verified','program_id'=>$p1->id,'transaction_date'=>'2026-08-01','created_by'=>$admin->id,'verified_by'=>$admin->id,'verified_at'=>now()]);
        Transaction::updateOrCreate(['code'=>'TRX-002'],['type'=>'pengeluaran','category'=>'bbm','amount'=>250000,'status'=>'verified','source'=>'internal','transaction_date'=>'2026-08-05','created_by'=>$admin->id,'verified_by'=>$admin->id,'verified_at'=>now()]);
        Transaction::where('code','TRX-001')->update(['source'=>'internal']);
        InfaqSetting::firstOrCreate([],['title'=>'Infaq Siaga Karta','description'=>'Dukungan operasional pelayanan sosial dan ambulans Karang Taruna.','payment_instructions'=>'Scan QR, lakukan pembayaran, lalu unggah bukti pembayaran melalui portal warga.','is_active'=>false,'updated_by'=>$admin->id]);
    }
}
