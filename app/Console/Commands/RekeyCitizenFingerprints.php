<?php
namespace App\Console\Commands;

use App\Models\Citizen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RekeyCitizenFingerprints extends Command
{
    protected $signature = 'siagakarta:rekey-fingerprints {--force : Jalankan tanpa konfirmasi production}';
    protected $description = 'Hitung ulang fingerprint NIK/telepon menggunakan DATA_FINGERPRINT_KEY saat ini.';

    public function handle(): int
    {
        if (app()->environment('production') && !$this->option('force') && !$this->confirm('Pastikan aplikasi maintenance mode. Lanjutkan?')) return self::FAILURE;
        $key=(string)config('siagakarta.data_fingerprint_key');
        if(strlen($key)<32){$this->error('DATA_FINGERPRINT_KEY wajib minimal 32 karakter.');return self::FAILURE;}
        $citizens=Citizen::query()->select('id','nik_encrypted','phone_encrypted')->orderBy('id')->get();
        DB::transaction(function()use($citizens,$key){
            foreach($citizens as $citizen){
                DB::table('citizens')->where('id',$citizen->id)->update([
                    'nik_hash'=>hash_hmac('sha256',$citizen->getNik(),$key),
                    'phone_hash'=>hash_hmac('sha256',$citizen->getPhone(),$key),
                    'updated_at'=>now(),
                ]);
            }
        });
        $this->info('Fingerprint '.count($citizens).' warga berhasil diperbarui.');
        return self::SUCCESS;
    }
}
