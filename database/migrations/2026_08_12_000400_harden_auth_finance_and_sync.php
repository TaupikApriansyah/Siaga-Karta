<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

return new class extends Migration {
    public function up(): void
    {
        // Normalize existing identities before changing auth semantics. Fail safely on collisions.
        $users = DB::table('users')->select('id', 'username', 'email')->orderBy('id')->get();
        $seenUsernames = [];
        $seenEmails = [];
        foreach ($users as $user) {
            $usernameRaw = trim((string) $user->username);
            $username = $usernameRaw === '' ? null : mb_strtolower($usernameRaw);
            $email = mb_strtolower(trim((string) $user->email));
            if ($username !== null && isset($seenUsernames[$username]) && $seenUsernames[$username] !== $user->id) {
                throw new RuntimeException("Username collision after normalization: {$username}. Resolve the duplicate before migrating.");
            }
            if (isset($seenEmails[$email]) && $seenEmails[$email] !== $user->id) {
                throw new RuntimeException("Email collision after normalization: {$email}. Resolve the duplicate before migrating.");
            }
            if ($username !== null) $seenUsernames[$username] = $user->id;
            $seenEmails[$email] = $user->id;
        }
        foreach ($users as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'username' => trim((string) $user->username) === '' ? null : mb_strtolower(trim((string) $user->username)),
                'email' => mb_strtolower(trim((string) $user->email)),
            ]);
        }

        // Decouple citizen lookup fingerprints from APP_KEY so encryption key rotation does not break identity lookup.
        if (Schema::hasTable('citizens')) {
            $fingerprintKey = (string) config('siagakarta.data_fingerprint_key', config('app.key'));
            foreach (DB::table('citizens')->select('id','nik_encrypted','phone_encrypted')->orderBy('id')->get() as $citizen) {
                $nik = Crypt::decryptString($citizen->nik_encrypted);
                $phone = Crypt::decryptString($citizen->phone_encrypted);
                DB::table('citizens')->where('id',$citizen->id)->update([
                    'nik_hash' => hash_hmac('sha256',$nik,$fingerprintKey),
                    'phone_hash' => hash_hmac('sha256',$phone,$fingerprintKey),
                ]);
            }
        }

        // String role menjaga kompatibilitas SQLite/MySQL dan memudahkan pengelolaan role aplikasi.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role',20)->default('petugas')->change();
        });

        Schema::table('api_tokens', function (Blueprint $table) {
            $table->timestamp('absolute_expires_at')->nullable()->after('expires_at')->index();
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->uuid('request_uuid')->nullable()->unique()->after('id');
            $table->index(['category','status','created_at'],'reports_category_status_created_idx');
            $table->index(['ambulance_id','status','service_start_at','service_end_at'],'reports_ambulance_schedule_idx');
            $table->index(['driver_id','status','service_start_at','service_end_at'],'reports_driver_schedule_idx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->uuid('request_uuid')->nullable()->unique()->after('id');
            $table->index(['status','type','transaction_date'],'transactions_status_type_date_idx');
        });

        Schema::table('infaq_settings', function (Blueprint $table) {
            $table->string('bank_name',80)->nullable()->after('qr_path');
            $table->string('account_number',80)->nullable()->after('bank_name');
            $table->string('account_name',120)->nullable()->after('account_number');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('request_id')->nullable()->after('user_id')->index();
            $table->json('old_values')->nullable()->after('metadata');
            $table->json('new_values')->nullable()->after('old_values');
            $table->index(['action','created_at'],'audit_action_created_idx');
            $table->index(['user_id','created_at'],'audit_user_created_idx');
        });

        Schema::create('status_histories', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('from_status',40)->nullable();
            $table->string('to_status',40);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['subject_type','subject_id','created_at'],'status_subject_created_idx');
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type',40)->default('info');
            $table->string('title',160);
            $table->text('message');
            $table->string('target_menu',40)->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id','read_at','id'],'notifications_user_unread_idx');
            $table->index(['subject_type','subject_id'],'notifications_subject_idx');
        });

        Schema::create('system_revisions', function (Blueprint $table) {
            $table->string('scope',40)->primary();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        $now = now();
        DB::table('system_revisions')->insert([
            ['scope' => 'operations', 'version' => 0, 'updated_at' => $now],
            ['scope' => 'finance', 'version' => 0, 'updated_at' => $now],
            ['scope' => 'users', 'version' => 0, 'updated_at' => $now],
            ['scope' => 'settings', 'version' => 0, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_revisions');
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('status_histories');
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_action_created_idx');
            $table->dropIndex('audit_user_created_idx');
            $table->dropIndex(['request_id']);
            $table->dropColumn(['request_id','old_values','new_values']);
        });
        Schema::table('infaq_settings', fn(Blueprint $table) => $table->dropColumn(['bank_name','account_number','account_name']));
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_status_type_date_idx');
            $table->dropUnique(['request_uuid']);
            $table->dropColumn('request_uuid');
        });
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('reports_category_status_created_idx');
            $table->dropIndex('reports_ambulance_schedule_idx');
            $table->dropIndex('reports_driver_schedule_idx');
            $table->dropUnique(['request_uuid']);
            $table->dropColumn('request_uuid');
        });
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropIndex(['absolute_expires_at']);
            $table->dropColumn('absolute_expires_at');
        });
    }
};
