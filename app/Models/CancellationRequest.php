<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CancellationRequest extends Model { protected $fillable=['transaction_id','requester_id','approver_id','reason','status','processed_at']; protected $casts=['processed_at'=>'datetime']; public function transaction(){return $this->belongsTo(Transaction::class);} }
