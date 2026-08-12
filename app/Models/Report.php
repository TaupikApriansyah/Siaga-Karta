<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Report extends Model
{
    protected $fillable = ['request_uuid','code','tracking_key_hash','citizen_id','type','category','source','status','pickup_location','destination','medical_condition','scheduled_at','service_start_at','service_end_at','ktp_path','ambulance_id','driver_id','handled_by','verified_by','verified_at','completed_at','internal_notes'];
    protected $hidden = ['tracking_key_hash','ktp_path'];
    protected function casts(): array { return ['scheduled_at'=>'datetime','service_start_at'=>'datetime','service_end_at'=>'datetime','verified_at'=>'datetime','completed_at'=>'datetime']; }
    public function getRouteKeyName(): string { return 'code'; }
    public function citizen(){ return $this->belongsTo(Citizen::class); }
    public function ambulance(){ return $this->belongsTo(Ambulance::class); }
    public function driver(){ return $this->belongsTo(Driver::class); }
    public function handler(){ return $this->belongsTo(User::class,'handled_by'); }
    public function verifier(){ return $this->belongsTo(User::class,'verified_by'); }
}
