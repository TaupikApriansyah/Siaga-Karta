<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ApiToken extends Model
{
    protected $fillable=['user_id','token_hash','last_used_at','expires_at','absolute_expires_at','ip_address','user_agent'];
    protected function casts(): array { return ['last_used_at'=>'datetime','expires_at'=>'datetime','absolute_expires_at'=>'datetime']; }
    public function user(){return $this->belongsTo(User::class);}
}
