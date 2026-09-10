<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(){
  Schema::table('ambulances',function(Blueprint $t){
   if(!Schema::hasColumn('ambulances','last_lat')) $t->decimal('last_lat',10,7)->nullable();
   if(!Schema::hasColumn('ambulances','last_lng')) $t->decimal('last_lng',10,7)->nullable();
   if(!Schema::hasColumn('ambulances','last_seen_at')) $t->timestamp('last_seen_at')->nullable();
  });
 }
 public function down(){}
};
