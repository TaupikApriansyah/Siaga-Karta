<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InfaqSetting;
use App\Models\Transaction;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InfaqController extends Controller
{
    public function publicInfo()
    {
        $s=InfaqSetting::first();
        return response()->json(['infaq'=>[
            'active'=>(bool)($s?->is_active ?? false),
            'title'=>$s?->title ?? 'Infaq Siaga Karta',
            'description'=>$s?->description,
            'payment_instructions'=>$s?->payment_instructions,
            'has_qr'=>(bool)($s?->qr_path && Storage::disk('local')->exists($s->qr_path)),
            'qr_url'=>($s?->qr_path && $s?->is_active)?'/api/public/infaq/qr':null,
        ]]);
    }

    public function publicQr()
    {
        $s=InfaqSetting::where('is_active',true)->firstOrFail();
        abort_unless($s->qr_path && Storage::disk('local')->exists($s->qr_path),404);
        $path=Storage::disk('local')->path($s->qr_path);
        return response()->file($path,['Cache-Control'=>'public, max-age=300','X-Content-Type-Options'=>'nosniff']);
    }

    public function submitPayment(Request $r)
    {
        $setting=InfaqSetting::where('is_active',true)->first();
        if(!$setting || !$setting->qr_path) return response()->json(['message'=>'Pembayaran infaq melalui QR belum diaktifkan.'],422);
        $d=$r->validate([
            'payer_name'=>'required|string|min:3|max:120',
            'payer_phone'=>['required','string','max:20','regex:/^(?:\+62|62|0)8[1-9][0-9]{6,11}$/'],
            'amount'=>'required|integer|min:1000|max:999999999999',
            'description'=>'nullable|string|max:500',
            'payment_proof'=>'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'website'=>'nullable|string|max:0',
        ]);
        $phone=preg_replace('/\D+/','',$d['payer_phone']); if(str_starts_with($phone,'62'))$phone='0'.substr($phone,2);
        do{$code='INF-'.now()->format('Ymd').'-'.strtoupper(Str::random(10));}while(Transaction::where('code',$code)->exists());
        $proofHash=hash_file('sha256',$r->file('payment_proof')->getRealPath());
        if(Transaction::where('payment_proof_hash',$proofHash)->exists()) return response()->json(['message'=>'Bukti pembayaran yang sama sudah pernah dikirim. Gunakan bukti pembayaran yang benar.'],422);
        $proof=$r->file('payment_proof')->storeAs('infaq/proofs',Str::uuid().'.'.$r->file('payment_proof')->extension(),'local');
        $t=new Transaction([
            'code'=>$code,'type'=>'pemasukan','category'=>'infaq','amount'=>$d['amount'],'status'=>'pending','source'=>'public_infaq',
            'payer_name'=>trim($d['payer_name']),'description'=>$d['description']??'Infaq warga','transaction_date'=>now()->toDateString(),'payment_proof_path'=>$proof,'payment_proof_hash'=>$proofHash,
        ]);
        $t->setPayerPhone($phone); $t->save();
        return response()->json(['message'=>'Bukti pembayaran berhasil dikirim dan menunggu verifikasi admin.','payment_code'=>$code,'status'=>'pending'],201);
    }

    public function settings()
    {
        $s=InfaqSetting::first();
        return response()->json(['setting'=>$s,'qr_url'=>$s?->qr_path?'/api/infaq/qr':null]);
    }

    public function updateSettings(Request $r)
    {
        $d=$r->validate(['title'=>'required|string|max:150','description'=>'nullable|string|max:1500','payment_instructions'=>'nullable|string|max:1500','is_active'=>'nullable|boolean','qr'=>'nullable|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $s=InfaqSetting::first() ?? new InfaqSetting();
        if($r->boolean('is_active') && !$r->hasFile('qr') && !$s->qr_path) return response()->json(['message'=>'Upload QR Code terlebih dahulu sebelum mengaktifkan infaq publik.'],422);
        if($r->hasFile('qr')){
            if($s->qr_path) Storage::disk('local')->delete($s->qr_path);
            $s->qr_path=$r->file('qr')->storeAs('infaq/qr',Str::uuid().'.'.$r->file('qr')->extension(),'local');
        }
        $s->fill(['title'=>$d['title'],'description'=>$d['description']??null,'payment_instructions'=>$d['payment_instructions']??null,'is_active'=>$r->boolean('is_active'),'updated_by'=>$r->attributes->get('api_user')->id]);
        $s->save(); AuditService::log($r,'infaq.settings_updated',$s);
        return response()->json(['message'=>'Pengaturan infaq diperbarui.']);
    }

    public function privateQr(Request $r)
    {
        $s=InfaqSetting::firstOrFail(); abort_unless($s->qr_path && Storage::disk('local')->exists($s->qr_path),404);
        return response()->file(Storage::disk('local')->path($s->qr_path),['Cache-Control'=>'private, no-store']);
    }

    public function proof(Request $r, Transaction $transaction)
    {
        abort_unless($transaction->payment_proof_path && Storage::disk('local')->exists($transaction->payment_proof_path),404);
        AuditService::log($r,'infaq.proof_viewed',$transaction);
        return response()->file(Storage::disk('local')->path($transaction->payment_proof_path),['Cache-Control'=>'private, no-store']);
    }
}
