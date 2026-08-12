<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model
{
    protected $fillable=['user_id','request_id','action','subject_type','subject_id','metadata','old_values','new_values','ip_address','user_agent'];
    protected function casts(): array { return ['metadata'=>'array','old_values'=>'array','new_values'=>'array']; }
    public function user(){ return $this->belongsTo(User::class); }
}

