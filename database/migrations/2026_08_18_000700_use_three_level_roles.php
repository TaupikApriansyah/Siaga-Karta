<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'admin')->update(['role' => 'kota']);
        DB::table('users')->whereIn('role', ['petugas', 'karta'])->update(['role' => 'kelurahan']);

        // Pastikan tidak ada role operasional lama di luar tiga tingkat resmi.
        DB::table('users')
            ->whereNotIn('role', ['kota', 'kecamatan', 'kelurahan'])
            ->update(['role' => 'kelurahan']);

        // Role default baru mengikuti level operasional terendah. Perubahan dilakukan
        // lewat migration baru agar migration historis proyek tidak perlu ditulis ulang.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('kelurahan')->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'kota')->update(['role' => 'admin']);
        DB::table('users')->whereIn('role', ['kecamatan', 'kelurahan'])->update(['role' => 'petugas']);
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('petugas')->change();
        });
    }
};
