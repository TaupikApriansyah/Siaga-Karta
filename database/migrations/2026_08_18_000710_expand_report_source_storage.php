<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Instalasi lama dapat memakai ENUM untuk kanal laporan. Ubah menjadi string
        // agar kanal WhatsApp/telepon/dateng langsung dapat berkembang tanpa migrasi ENUM berulang.
        Schema::table('reports', function (Blueprint $table) {
            $table->string('source', 30)->default('website')->change();
        });
    }

    public function down(): void
    {
    }
};
