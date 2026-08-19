<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->string('reporter_name');
            $table->string('reporter_email');
            $table->foreignId('reporter_address_village_id')->constrained('villages');
            $table->string('reporter_status');
            $table->string('category');
            $table->text('description');
            $table->string('lat')->nullable();
            $table->string('lng')->nullable();
            $table->string('attachment')->nullable();
            $table->string('affected_name')->nullable();
            $table->string('affected_relation')->nullable();
            $table->enum('status', ['Diterima', 'Diverifikasi', 'Divalidasi', 'Diproses', 'Selesai'])->default('Diterima');
            $table->enum('priority', ['Reguler', 'Prioritas', 'Darurat'])->default('Reguler');
            $table->string('assigned_opd')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
