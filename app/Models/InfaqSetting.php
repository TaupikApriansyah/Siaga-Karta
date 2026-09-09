<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Region;
class InfaqSetting extends Model
{
    protected $fillable=['region_id','title','description','qr_path','bank_name','account_number','account_name','payment_instructions','is_active','updated_by'];
    protected $hidden=['qr_path'];
    public function region(){ return $this->belongsTo(Region::class); }
    protected function casts(): array { return ['is_active'=>'boolean']; }
}
