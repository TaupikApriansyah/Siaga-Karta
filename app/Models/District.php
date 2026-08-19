<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    protected $guarded = [];

    public function villages()
    {
        return $this->hasMany(Village::class);
    }
}
