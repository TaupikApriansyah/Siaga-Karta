<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Role Karta lama digabung ke Petugas Karang Taruna agar hanya ada dua role operasional.
        DB::table('users')->where('role', 'karta')->update(['role' => 'petugas']);
    }

    public function down(): void
    {
        // Tidak ada rollback otomatis karena sistem tidak dapat menentukan akun petugas mana yang dahulu ber-role Karta.
    }
};
