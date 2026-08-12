<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->timestamp('service_start_at')->nullable()->after('scheduled_at')->index();
            $table->timestamp('service_end_at')->nullable()->after('service_start_at')->index();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('source', 30)->default('internal')->after('status')->index();
            $table->string('payer_name')->nullable()->after('source');
            $table->text('payer_phone_encrypted')->nullable()->after('payer_name');
            $table->string('payer_phone_last4', 4)->nullable()->after('payer_phone_encrypted');
            $table->string('payment_proof_path')->nullable()->after('payer_phone_last4');
            $table->char('payment_proof_hash',64)->nullable()->unique()->after('payment_proof_path');
            $table->text('rejection_reason')->nullable()->after('payment_proof_hash');
        });

        Schema::create('infaq_settings', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default('Infaq Siaga Karta');
            $table->text('description')->nullable();
            $table->string('qr_path')->nullable();
            $table->text('payment_instructions')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infaq_settings');
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['source','payer_name','payer_phone_encrypted','payer_phone_last4','payment_proof_path','payment_proof_hash','rejection_reason']);
        });
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['service_start_at','service_end_at']);
        });
    }
};
