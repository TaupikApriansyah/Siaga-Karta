<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(){ Schema::create('cancellation_requests',function(Blueprint $t){$t->id();$t->foreignId('transaction_id')->constrained()->cascadeOnDelete();$t->foreignId('requester_id')->constrained('users');$t->foreignId('approver_id')->nullable()->constrained('users');$t->text('reason');$t->enum('status',['pending','approved','rejected'])->default('pending')->index();$t->timestamp('processed_at')->nullable();$t->timestamps();}); } public function down(){Schema::dropIfExists('cancellation_requests');}};
