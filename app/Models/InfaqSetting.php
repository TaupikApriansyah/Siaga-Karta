<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InfaqSetting extends Model
{
    protected $fillable=['title','description','qr_path','bank_name','account_number','account_name','payment_instructions','is_active','updated_by'];
    protected $hidden=['qr_path'];
    protected function casts(): array { return ['is_active'=>'boolean']; }
}
