<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Program extends Model { protected $fillable=['code','name','description','target_amount','collected_amount','distributed_amount','status','image_url']; }
