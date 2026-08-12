<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InfaqSetting;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\RevisionService;
use App\Services\StatusHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InfaqController extends Controller
{
    public function publicInfo()
    {
        $s=InfaqSetting::first();
        $hasQr=(bool)($s?->qr_path && Storage::disk('local')->exists($s->qr_path));
        $hasAccount=(bool)($s?->bank_name && $s?->account_number && $s?->account_name);
        return response()->json(['infaq'=>[
            'active'=>(bool)($s?->is_active ?? false),
            'title'=>$s?->title ?? 'Infaq Siaga Karta',
            'description'=>$s?->description,
            'payment_instructions'=>$s?->payment_instructions,
            'has_qr'=>$hasQr,
            'qr_url'=>($hasQr && $s?->is_active)?'/api/public/infaq/qr':null,
            'bank_name'=>$s?->is_active ? $s?->bank_name : null,
            'account_number'=>$s?->is_active ? $s?->account_number : null,
            'account_name'=>$s?->is_active ? $s?->account_name : null,
            'has_payment_channel'=>(bool)($s?->is_active && ($hasQr || $hasAccount)),
        ]]);
    }

    public function publicQr()
    {
        $s=InfaqSetting::where('is_active',true)->firstOrFail();
        abort_unless($s->qr_path && Storage::disk('local')->exists($s->qr_path),404);
        return response()->file(Storage::disk('local')->path($s->qr_path),[
            'Cache-Control'=>'public, max-age=300','X-Content-Type-Options'=>'nosniff'
        ]);
    }

    public function submitPayment(Request $r)
    {
        $setting=InfaqSetting::where('is_active',true)->first();
        $hasChannel=$setting && (($setting->qr_path && Storage::disk('local')->exists($setting->qr_path)) || ($setting->bank_name && $setting->account_number && $setting->account_name));
        if(!$hasChannel) return response()->json(['message'=>'Pembayaran infaq belum diaktifkan.'],422);

        $d=$r->validate([
            'request_uuid'=>'nullable|uuid',
            'payer_name'=>'required|string|min:3|max:120',
            'payer_phone'=>['required','string','max:20','regex:/^(?:\+62|62|0)8[1-9][0-9]{6,11}$/'],
            'amount'=>'required|integer|min:1000|max:999999999999',
            'description'=>'nullable|string|max:500',
            'payment_proof'=>'required|image|mimes:jpg,jpeg,png,webp|max:5120|dimensions:max_width=5000,max_height=5000',
            'website'=>'nullable|string|max:0',
        ]);
        if(!empty($d['request_uuid']) && ($existing=Transaction::where('request_uuid',$d['request_uuid'])->first())) {
            return response()->json(['message'=>'Bukti pembayaran sudah diterima sebelumnya.','payment_code'=>$existing->code,'status'=>$existing->status]);
        }

        $phone=preg_replace('/\D+/','',$d['payer_phone']);
        if(str_starts_with($phone,'62'))$phone='0'.substr($phone,2);
        $proofHash=hash_file('sha256',$r->file('payment_proof')->getRealPath());
        if(Transaction::where('payment_proof_hash',$proofHash)->exists()) return response()->json(['message'=>'Bukti pembayaran yang sama sudah pernah dikirim.'],422);

        $proof=$r->file('payment_proof')->storeAs('infaq/proofs',Str::uuid().'.'.$r->file('payment_proof')->extension(),'local');
        if(!$proof) return response()->json(['message'=>'Bukti pembayaran gagal disimpan. Silakan coba lagi.'],500);
        try {
            $t=DB::transaction(function() use($d,$r,$phone,$proof,$proofHash){
                do{$code='INF-'.now()->format('Ymd').'-'.strtoupper(Str::random(10));}while(Transaction::where('code',$code)->exists());
                $t=new Transaction([
                    'request_uuid'=>$d['request_uuid']??null,
                    'code'=>$code,'type'=>'pemasukan','category'=>'infaq','amount'=>$d['amount'],'status'=>'pending','source'=>'public_infaq',
                    'payer_name'=>trim($d['payer_name']),'description'=>$d['description']??'Infaq warga','transaction_date'=>now()->toDateString(),
                    'payment_proof_path'=>$proof,'payment_proof_hash'=>$proofHash,
                ]);
                $t->setPayerPhone($phone); $t->save();
                StatusHistoryService::record($r,$t,null,'pending');
                return $t;
            });
        } catch(\Throwable $e) {
            Storage::disk('local')->delete($proof);
            if(!empty($d['request_uuid']) && ($existing=Transaction::where('request_uuid',$d['request_uuid'])->first())) {
                return response()->json(['message'=>'Bukti pembayaran sudah diterima sebelumnya.','payment_code'=>$existing->code,'status'=>$existing->status]);
            }
            if(Transaction::where('payment_proof_hash',$proofHash)->exists()) {
                return response()->json(['message'=>'Bukti pembayaran yang sama sudah pernah dikirim.'],422);
            }
            throw $e;
        }
        AuditService::log($r,'infaq.payment_submitted',$t,['amount'=>$t->amount,'source'=>'public_infaq']);
        RevisionService::bump('finance');
        return response()->json(['message'=>'Bukti pembayaran berhasil dikirim dan menunggu verifikasi.','payment_code'=>$t->code,'status'=>'pending'],201);
    }

    public function settings()
    {
        $s=InfaqSetting::first();
        return response()->json(['setting'=>$s ? [
            'id'=>$s->id,'title'=>$s->title,'description'=>$s->description,'payment_instructions'=>$s->payment_instructions,
            'bank_name'=>$s->bank_name,'account_number'=>$s->account_number,'account_name'=>$s->account_name,'is_active'=>(bool)$s->is_active,
            'has_qr'=>(bool)($s->qr_path && Storage::disk('local')->exists($s->qr_path)),
            'updated_at'=>$s->updated_at,
        ] : null,'qr_url'=>($s?->qr_path && Storage::disk('local')->exists($s->qr_path))?'/api/infaq/qr':null]);
    }

    public function updateSettings(Request $r)
    {
        $d=$r->validate([
            'title'=>'required|string|max:150',
            'description'=>'nullable|string|max:1500',
            'payment_instructions'=>'nullable|string|max:1500',
            'bank_name'=>'nullable|string|max:80',
            'account_number'=>'nullable|string|max:80|regex:/^[0-9 .-]+$/',
            'account_name'=>'nullable|string|max:120',
            'is_active'=>'nullable|boolean',
            'remove_qr'=>'nullable|boolean',
            'qr'=>'nullable|image|mimes:jpg,jpeg,png,webp|max:5120|dimensions:max_width=5000,max_height=5000',
        ]);
        $s=InfaqSetting::first() ?? new InfaqSetting();
        $before=$s->exists?$s->only('title','description','bank_name','account_number','account_name','payment_instructions','is_active','qr_path'):null;

        $bankName=trim((string)($d['bank_name']??'')) ?: null;
        $accountNumber=preg_replace('/\s+/','',trim((string)($d['account_number']??''))) ?: null;
        $accountName=trim((string)($d['account_name']??'')) ?: null;
        $hasAccount=(bool)($bankName && $accountNumber && $accountName);
        if(($bankName || $accountNumber || $accountName) && !$hasAccount) {
            return response()->json(['message'=>'Nama bank, nomor rekening, dan nama pemilik rekening harus diisi lengkap.'],422);
        }

        $oldQr=$s->qr_path;
        $oldQrExists=(bool)($oldQr && Storage::disk('local')->exists($oldQr));
        $removeQr=$r->boolean('remove_qr');
        $willHaveQr=$r->hasFile('qr') || ($oldQrExists && !$removeQr);
        if($r->boolean('is_active') && !$willHaveQr && !$hasAccount) {
            return response()->json(['message'=>'Tambahkan QR pembayaran atau data rekening lengkap sebelum mengaktifkan pembayaran publik.'],422);
        }

        $newQr=null;
        if($r->hasFile('qr')) {
            $newQr=$r->file('qr')->storeAs('infaq/qr',Str::uuid().'.'.$r->file('qr')->extension(),'local');
            if(!$newQr) return response()->json(['message'=>'Gagal menyimpan file QR pembayaran.'],500);
        }

        try {
            DB::transaction(function()use($r,$d,$s,$bankName,$accountNumber,$accountName,$removeQr,$newQr){
                if($newQr) $s->qr_path=$newQr;
                elseif($removeQr) $s->qr_path=null;
                $s->fill([
                    'title'=>$d['title'],'description'=>$d['description']??null,
                    'payment_instructions'=>$d['payment_instructions']??null,
                    'bank_name'=>$bankName,'account_number'=>$accountNumber,'account_name'=>$accountName,
                    'is_active'=>$r->boolean('is_active'),'updated_by'=>$r->attributes->get('api_user')->id,
                ]);
                $s->save();
            },3);
        } catch(\Throwable $e) {
            if($newQr) Storage::disk('local')->delete($newQr);
            throw $e;
        }
        if($oldQr && $oldQr!==$s->qr_path) Storage::disk('local')->delete($oldQr);

        $after=$s->fresh()->only('title','description','bank_name','account_number','account_name','payment_instructions','is_active','qr_path');
        AuditService::log($r,'infaq.settings_updated',$s,[], $before, $after);
        RevisionService::bump('settings','finance');
        return response()->json(['message'=>'Pengaturan pembayaran diperbarui.']);
    }

    public function privateQr(Request $r)
    {
        $s=InfaqSetting::firstOrFail();
        abort_unless($s->qr_path && Storage::disk('local')->exists($s->qr_path),404);
        return response()->file(Storage::disk('local')->path($s->qr_path),['Cache-Control'=>'private, no-store']);
    }

    public function proof(Request $r, Transaction $transaction)
    {
        abort_unless($transaction->payment_proof_path && Storage::disk('local')->exists($transaction->payment_proof_path),404);
        AuditService::log($r,'infaq.proof_viewed',$transaction);
        return response()->file(Storage::disk('local')->path($transaction->payment_proof_path),['Cache-Control'=>'private, no-store']);
    }
}
