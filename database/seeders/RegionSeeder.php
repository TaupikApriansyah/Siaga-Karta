<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Alias kompatibilitas untuk perintah lama `php artisan db:seed --class=RegionSeeder`.
 * Semua sumber wilayah sekarang memakai master Bandung yang sama agar tidak membuat
 * tabel wilayah uji dengan ejaan/nama yang berbeda dari aplikasi utama.
 */
class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(BandungRegionSeeder::class);
    }
}
