<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Ambulance;
use Illuminate\Http\Request;
class AmbulanceTrackingController extends Controller {
 public function update(Request $r, Ambulance $ambulance){
  $d=$r->validate(['lat'=>'required|numeric|between:-90,90','lng'=>'required|numeric|between:-180,180']);
  $ambulance->update(['last_lat'=>$d['lat'],'last_lng'=>$d['lng'],'last_seen_at'=>now()]);
  return response()->json(['message'=>'Lokasi ambulans diperbarui','ambulance'=>$ambulance]);
 }
 public function show(Ambulance $ambulance){
  return response()->json(['id'=>$ambulance->id,'code'=>$ambulance->code,'status'=>$ambulance->status,'location'=>['lat'=>$ambulance->last_lat,'lng'=>$ambulance->last_lng,'seen_at'=>$ambulance->last_seen_at]]);
 }
}
