<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class FinanceController extends Controller
{
    public function index(){return Transaction::with('program:id,code,name')->latest('transaction_date')->latest('id')->paginate(100);}
    public function store(Request $r){$d=$r->validate(['type'=>'required|in:pemasukan,pengeluaran','category'=>'required|string|max:80','amount'=>'required|integer|min:1|max:999999999999','program_id'=>'nullable|exists:programs,id','description'=>'nullable|string|max:2000','transaction_date'=>'required|date']);do{$code='TRX-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));}while(Transaction::where('code',$code)->exists());$d+=['code'=>$code,'created_by'=>$r->attributes->get('api_user')->id,'status'=>'pending','source'=>'internal'];$t=Transaction::create($d);AuditService::log($r,'transaction.created',$t);return response()->json($t,201);}
    public function verify(Request $r,Transaction $transaction){if($transaction->status==='verified')return response()->json(['message'=>'Transaksi sudah terverifikasi.']);$transaction->update(['status'=>'verified','rejection_reason'=>null,'verified_by'=>$r->attributes->get('api_user')->id,'verified_at'=>now()]);AuditService::log($r,'transaction.verified',$transaction);return response()->json(['message'=>'Transaksi terverifikasi dan masuk ke kas.']);}
    public function reject(Request $r,Transaction $transaction){$d=$r->validate(['reason'=>'required|string|min:3|max:1000']);$transaction->update(['status'=>'rejected','rejection_reason'=>$d['reason'],'verified_by'=>$r->attributes->get('api_user')->id,'verified_at'=>now()]);AuditService::log($r,'transaction.rejected',$transaction,['reason'=>$d['reason']]);return response()->json(['message'=>'Transaksi ditolak.']);}
}
