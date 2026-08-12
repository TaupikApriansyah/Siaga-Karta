<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusHistory extends Model
{
    protected $fillable = ['subject_type','subject_id','from_status','to_status','changed_by','reason','metadata'];
    protected function casts(): array { return ['metadata'=>'array']; }
    public function changedBy(){ return $this->belongsTo(User::class,'changed_by'); }
}

