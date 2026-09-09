<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('infaq_settings', function(Blueprint $table){
   if(!Schema::hasColumn('infaq_settings','region_id')){
    $table->foreignId('region_id')->nullable()->after('id')->constrained('regions')->nullOnDelete();
    $table->unique('region_id','infaq_settings_region_unique');
   }
  });
 }
 public function down(): void {
  Schema::table('infaq_settings', function(Blueprint $table){
   if(Schema::hasColumn('infaq_settings','region_id')){
    $table->dropUnique('infaq_settings_region_unique');
    $table->dropConstrainedForeignId('region_id');
   }
  });
 }
};
