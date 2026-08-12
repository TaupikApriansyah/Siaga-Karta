<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemRevision extends Model
{
    public $timestamps = false;
    protected $fillable = ['scope', 'version', 'updated_at'];
    protected function casts(): array { return ['version'=>'integer','updated_at'=>'datetime']; }
}
