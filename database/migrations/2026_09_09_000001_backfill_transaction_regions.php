<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Keep the backfill portable across MySQL and SQLite. Laravel's joined UPDATE
        // shape is database-specific, so resolve user -> region mappings per chunk and
        // update only transactions that are still unscoped.
        DB::table('transactions')
            ->whereNull('region_id')
            ->whereNotNull('created_by')
            ->select('id','created_by')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $creatorIds=$rows->pluck('created_by')->filter()->unique()->values();
                if($creatorIds->isEmpty()) return;

                $regionByUser=DB::table('users')
                    ->whereIn('id',$creatorIds)
                    ->whereNotNull('region_id')
                    ->pluck('region_id','id');

                foreach($rows as $row) {
                    $regionId=$regionByUser[$row->created_by] ?? null;
                    if(!$regionId) continue;
                    DB::table('transactions')
                        ->where('id',$row->id)
                        ->whereNull('region_id')
                        ->update(['region_id'=>$regionId]);
                }
            }, 'id');
    }

    public function down(): void
    {
        // Keep historical transaction scope intact.
    }
};
