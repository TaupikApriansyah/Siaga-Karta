<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model { protected $fillable=['user_id','action','subject_type','subject_id','metadata','ip_address','user_agent']; protected function casts(): array { return ['metadata'=>'array']; } }
