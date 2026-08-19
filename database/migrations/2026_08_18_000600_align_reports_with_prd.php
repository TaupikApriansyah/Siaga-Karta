<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('kecamatan', 100)->nullable()->after('role')->index();
            $table->string('kelurahan', 100)->nullable()->after('kecamatan')->index();
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->foreignId('citizen_id')->nullable()->change();

            // PRD SIAGA KARTA: identitas pelapor dan wilayah tidak lagi bergantung pada NIK/layanan ambulans.
            $table->string('reporter_name', 120)->nullable()->after('citizen_id');
            $table->string('reporter_email', 190)->nullable()->after('reporter_name')->index();
            $table->string('reporter_phone', 30)->nullable()->after('reporter_email');
            $table->string('reporter_status', 60)->nullable()->after('reporter_phone');
            $table->string('kecamatan', 100)->nullable()->after('reporter_status')->index();
            $table->string('kelurahan', 100)->nullable()->after('kecamatan')->index();
            $table->string('rt', 10)->nullable()->after('kelurahan');
            $table->string('rw', 10)->nullable()->after('rt');

            $table->string('complaint_type', 80)->nullable()->after('category')->index();
            $table->text('complaint_description')->nullable()->after('complaint_type');
            $table->text('incident_location')->nullable()->after('complaint_description');
            $table->decimal('gps_lat', 10, 7)->nullable()->after('incident_location');
            $table->decimal('gps_lng', 10, 7)->nullable()->after('gps_lat');
            $table->string('attachment_path')->nullable()->after('gps_lng');
            $table->string('affected_person_name', 120)->nullable()->after('attachment_path');
            $table->string('affected_person_relation', 80)->nullable()->after('affected_person_name');

            $table->string('source_channel', 30)->default('form_online')->after('source')->index();
            $table->string('priority', 20)->default('belum_ditentukan')->after('status')->index();
            $table->string('workflow_stage', 30)->default('kelurahan')->after('priority')->index();
            $table->string('opd_target', 160)->nullable()->after('workflow_stage')->index();
            $table->boolean('direct_escalation')->default(false)->after('opd_target');
            $table->unsignedInteger('ticket_sequence')->nullable()->after('direct_escalation');
            $table->timestamp('kelurahan_verified_at')->nullable()->after('ticket_sequence');
            $table->timestamp('kecamatan_validated_at')->nullable()->after('kelurahan_verified_at');
            $table->timestamp('kota_validated_at')->nullable()->after('kecamatan_validated_at');
            $table->timestamp('sla_due_at')->nullable()->after('kota_validated_at')->index();
            $table->timestamp('last_notified_at')->nullable()->after('sla_due_at');
            $table->boolean('consent_data')->default(false)->after('last_notified_at');

            $table->index(['kecamatan','kelurahan','workflow_stage','status'], 'reports_prd_area_stage_idx');
            $table->index(['priority','status','sla_due_at'], 'reports_prd_priority_sla_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('reports_prd_area_stage_idx');
            $table->dropIndex('reports_prd_priority_sla_idx');
            $table->dropColumn([
                'reporter_name','reporter_email','reporter_phone','reporter_status','kecamatan','kelurahan','rt','rw',
                'complaint_type','complaint_description','incident_location','gps_lat','gps_lng','attachment_path',
                'affected_person_name','affected_person_relation','source_channel','priority','workflow_stage','opd_target',
                'direct_escalation','ticket_sequence','kelurahan_verified_at','kecamatan_validated_at','kota_validated_at',
                'sla_due_at','last_notified_at','consent_data',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['kecamatan']);
            $table->dropIndex(['kelurahan']);
            $table->dropColumn(['kecamatan','kelurahan']);
        });
    }
};
