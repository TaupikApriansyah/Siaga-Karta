<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('citizens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('nik_encrypted');
            $table->char('nik_hash', 64)->unique();
            $table->text('phone_encrypted');
            $table->char('phone_hash', 64)->index();
            $table->string('phone_last4', 4);
            $table->timestamps();
        });

        Schema::create('ambulances', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('plate_number')->unique();
            $table->unsignedTinyInteger('capacity')->default(1);
            $table->enum('status', ['tersedia', 'dipesan', 'bertugas', 'maintenance'])->default('tersedia')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('phone_encrypted')->nullable();
            $table->enum('status', ['aktif', 'nonaktif', 'bertugas'])->default('aktif')->index();
            $table->timestamps();
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->char('tracking_key_hash', 64);
            $table->foreignId('citizen_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('type', ['darurat', 'terjadwal'])->index();
            $table->enum('source', ['website', 'datang_langsung', 'whatsapp', 'telepon'])->default('website')->index();
            $table->enum('status', ['menunggu', 'diproses', 'dijemput', 'selesai', 'ditolak'])->default('menunggu')->index();
            $table->text('pickup_location');
            $table->text('destination')->nullable();
            $table->text('medical_condition');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('ktp_path')->nullable();
            $table->foreignId('ambulance_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('target_amount')->default(0);
            $table->unsignedBigInteger('collected_amount')->default(0);
            $table->unsignedBigInteger('distributed_amount')->default(0);
            $table->enum('status', ['aktif', 'selesai', 'nonaktif'])->default('aktif')->index();
            $table->string('image_url')->nullable();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('type', ['pemasukan', 'pengeluaran'])->index();
            $table->string('category')->index();
            $table->unsignedBigInteger('amount');
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending')->index();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->date('transaction_date')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('ambulances');
        Schema::dropIfExists('citizens');
    }
};
