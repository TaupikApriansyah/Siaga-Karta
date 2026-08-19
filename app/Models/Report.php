<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    public const CATEGORIES = [
        'kesehatan','bpjs','ambulans','lansia_disabilitas','bantuan_sosial','orang_terlantar',
        'anak_keluarga','data_sosial_desil','kebencanaan','lainnya',
    ];
    public const PRIORITIES = ['darurat','prioritas','reguler'];

    protected $fillable = [
        'request_uuid','code','tracking_key_hash','citizen_id','region_id','type','category','priority','source','status',
        'workflow_status','escalation_level','pickup_location','rt_number','rw_number','latitude','longitude','destination',
        'medical_condition','description','scheduled_at','service_start_at','service_end_at','ktp_path','ambulance_id','driver_id',
        'handled_by','submitted_by','verified_by','kecamatan_verified_by','verified_at','kecamatan_verified_at','kota_received_at',
        'assigned_agency','agency_referred_at','completed_at','internal_notes',
    ];
    protected $hidden = ['tracking_key_hash','ktp_path'];

    protected function casts(): array
    {
        return [
            'scheduled_at'=>'datetime','service_start_at'=>'datetime','service_end_at'=>'datetime','verified_at'=>'datetime',
            'kecamatan_verified_at'=>'datetime','kota_received_at'=>'datetime','agency_referred_at'=>'datetime','completed_at'=>'datetime',
            'latitude'=>'float','longitude'=>'float',
        ];
    }

    public function getRouteKeyName(): string { return 'code'; }
    public function citizen(){ return $this->belongsTo(Citizen::class); }
    public function region(){ return $this->belongsTo(Region::class); }
    public function ambulance(){ return $this->belongsTo(Ambulance::class); }
    public function driver(){ return $this->belongsTo(Driver::class); }
    public function handler(){ return $this->belongsTo(User::class,'handled_by'); }
    public function submitter(){ return $this->belongsTo(User::class,'submitted_by'); }
    public function verifier(){ return $this->belongsTo(User::class,'verified_by'); }
    public function kecamatanVerifier(){ return $this->belongsTo(User::class,'kecamatan_verified_by'); }

    public function district(): ?Region
    {
        if (!$this->relationLoaded('region')) $this->load('region.parent');
        return $this->region?->level === 'kelurahan' ? $this->region?->parent : ($this->region?->level === 'kecamatan' ? $this->region : null);
    }
}
