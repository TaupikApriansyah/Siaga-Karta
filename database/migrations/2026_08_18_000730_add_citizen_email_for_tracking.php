<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizens', function (Blueprint $table) {
            $table->text('email_encrypted')->nullable()->after('phone_last4');
            $table->char('email_hash', 64)->nullable()->after('email_encrypted')->index();
        });
    }

    public function down(): void
    {
        Schema::table('citizens', function (Blueprint $table) {
            $table->dropIndex(['email_hash']);
            $table->dropColumn(['email_encrypted', 'email_hash']);
        });
    }
};
