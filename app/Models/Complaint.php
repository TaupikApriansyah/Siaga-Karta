<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    protected $guarded = [];

    public function village()
    {
        return $this->belongsTo(Village::class, 'reporter_address_village_id');
    }

    public function logs()
    {
        return $this->hasMany(ComplaintLog::class);
    }
}
