<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InfaqSetting extends Model
{
    protected $fillable=['title','description','qr_path','payment_instructions','is_active','updated_by'];
    protected function casts(): array { return ['is_active'=>'boolean']; }
}
