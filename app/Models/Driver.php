<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Driver extends Model { protected $fillable=['code','name','phone_encrypted','status']; protected $hidden=['phone_encrypted']; }
