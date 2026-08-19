<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Instalasi lama memakai ENUM yang belum mengenal Gmail. Ubah menjadi string
        // agar kanal dapat berkembang tanpa migrasi ENUM berulang.
        Schema::table('reports', function (Blueprint $table) {
            $table->string('source', 30)->default('website')->change();
        });
        DB::table('reports')->where('source', 'whatsapp')->update(['source' => 'gmail']);
    }

    public function down(): void
    {
        DB::table('reports')->where('source', 'gmail')->update(['source' => 'whatsapp']);
    }
};
