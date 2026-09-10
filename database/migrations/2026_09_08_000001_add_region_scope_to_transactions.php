<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->after('program_id')->constrained('regions')->nullOnDelete();
            $table->index(['region_id','status','transaction_date'], 'transactions_region_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_region_status_date_idx');
            $table->dropConstrainedForeignId('region_id');
        });
    }
};
