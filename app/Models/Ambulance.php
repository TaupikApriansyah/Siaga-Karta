<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Ambulance extends Model { protected $fillable=['code','plate_number','capacity','status','notes']; }
