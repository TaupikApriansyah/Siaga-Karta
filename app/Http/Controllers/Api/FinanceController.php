<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\SystemRevision;
use App\Models\StatusHistory;
use App\Models\Transaction;
use App\Models\CancellationRequest;
use App\Models\Region;
use App\Services\AuditService;
use App\Services\RevisionService;
use App\Services\StatusHistoryService;
use App\Traits\RegionScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinanceController extends Controller
{
    use RegionScope;
    public function index(Request $r)
    {
        $filters=$r->validate([
            'status'=>'nullable|in:pending,verified,rejected',
            'type'=>'nullable|in:pemasukan,pengeluaran',
            'per_page'=>'nullable|integer|min:10|max:100',
            'page'=>'nullable|integer|min:1',
        ]);
        $perPage=min(max((int)($filters['per_page']??50),10),100);
        $user=$r->attributes->get('api_user');
        $q=Transaction::query()->whereIn('region_id', $this->allowedRegionIds($user))->select([
            'id','code','type','category','amount','status','source','payer_name','payer_phone_last4',
            'payment_proof_path','rejection_reason','program_id','region_id','description','transaction_date','created_at'
        ])->latest('transaction_date')->latest('id');
        if(!empty($filters['status'])) $q->where('status',$filters['status']);
        if(!empty($filters['type'])) $q->where('type',$filters['type']);
        $page=$q->paginate($perPage);
        $page->through(fn($t)=>[
            'id'=>$t->id,'code'=>$t->code,'type'=>$t->type,'category'=>$t->category,'amount'=>$t->amount,'status'=>$t->status,
            'source'=>$t->source,'payer_name'=>$t->payer_name,'payer_phone_last4'=>$t->payer_phone_last4,'has_proof'=>(bool)$t->payment_proof_path,
            'rejection_reason'=>$t->rejection_reason,'program_id'=>$t->program_id,'region_id'=>$t->region_id,'description'=>$t->description,'transaction_date'=>$t->transaction_date?->format('Y-m-d'),'created_at'=>$t->created_at,
        ]);
        return response()->json($page);
    }


    public function show(Request $r, Transaction $transaction)
    {
        $this->assertTransactionAccess($r, $transaction);
        $history=StatusHistory::where('subject_type',Transaction::class)->where('subject_id',$transaction->id)
            ->with('changedBy:id,name')->latest('id')->limit(20)->get()->map(fn($h)=>[
                'from_status'=>$h->from_status,'to_status'=>$h->to_status,'reason'=>$h->reason,
                'changed_by'=>$h->changedBy?->name,'created_at'=>$h->created_at,
            ]);
        $payload=$this->transactionPayload($transaction);
        $payload['status_history']=$history;
        return response()->json(['transaction'=>$payload]);
    }

    public function store(Request $r)
    {
        $d=$r->validate([
            'request_uuid'=>'nullable|uuid',
            'type'=>'required|in:pemasukan,pengeluaran',
            'category'=>'required|string|max:80',
            'amount'=>'required|integer|min:1|max:999999999999',
            'program_id'=>'nullable|exists:programs,id',
            'description'=>'nullable|string|max:2000',
            'transaction_date'=>'required|date',
        ]);
        $user=$r->attributes->get('api_user');
        if(!empty($d['program_id']) && $user?->role!=='kota') {
            return response()->json(['message'=>'Program bantuan tingkat Kota tidak dapat ditautkan ke Kas Kecamatan/Kelurahan.'],422);
        }
        if(!empty($d['request_uuid']) && ($existing=Transaction::where('request_uuid',$d['request_uuid'])->first())) {
            return response()->json(['message'=>'Transaksi sudah dicatat sebelumnya.','transaction'=>$this->transactionPayload($existing)]);
        }
        try {
            $t=DB::transaction(function() use($d,$r){
                do{$code='TRX-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));}while(Transaction::where('code',$code)->exists());
                $payload=$d+['code'=>$code,'created_by'=>$r->attributes->get('api_user')->id,'region_id'=>$r->attributes->get('api_user')->region_id,'status'=>'pending','source'=>'internal'];
                $t=Transaction::create($payload);
                StatusHistoryService::record($r,$t,null,'pending');
                return $t;
            });
        } catch(\Throwable $e) {
            if(!empty($d['request_uuid']) && ($existing=Transaction::where('request_uuid',$d['request_uuid'])->first())) return response()->json(['message'=>'Transaksi sudah dicatat sebelumnya.','transaction'=>$this->transactionPayload($existing)]);
            throw $e;
        }
        AuditService::log($r,'transaction.created',$t,['type'=>$t->type,'amount'=>$t->amount],null,$t->only('code','type','category','amount','status','program_id','transaction_date'));
        RevisionService::bump('finance');
        return response()->json(['message'=>'Transaksi dicatat dan menunggu verifikasi.','transaction'=>$this->transactionPayload($t)],201);
    }

    private function transactionPayload(Transaction $t): array
    {
        return [
            'id'=>$t->id,'code'=>$t->code,'type'=>$t->type,'category'=>$t->category,'amount'=>$t->amount,'status'=>$t->status,
            'source'=>$t->source,'payer_name'=>$t->payer_name,'payer_phone_last4'=>$t->payer_phone_last4,'has_proof'=>(bool)$t->payment_proof_path,
            'rejection_reason'=>$t->rejection_reason,'program_id'=>$t->program_id,'region_id'=>$t->region_id,'description'=>$t->description,
            'transaction_date'=>$t->transaction_date?->format('Y-m-d'),'created_at'=>$t->created_at,
        ];
    }

    public function verify(Request $r, Transaction $transaction)
    {
        $this->assertTransactionAccess($r, $transaction);
        $transaction=DB::transaction(function() use($r,$transaction){
            $t=Transaction::lockForUpdate()->findOrFail($transaction->id);
            $this->assertMakerChecker($r, $t);
            if($t->status!=='pending') {
                abort(422,'Hanya transaksi pending yang dapat diverifikasi.');
            }
            if($t->type==='pengeluaran') {
                // Serialize expense verification on one tiny mutex row, then calculate balance in SQL.
                SystemRevision::where('scope','finance')->lockForUpdate()->firstOrFail();
                $balance=(int)Transaction::where('region_id',$t->region_id)->where('status','verified')
                    ->selectRaw("coalesce(sum(case when type='pemasukan' then amount else -amount end),0) as balance")
                    ->value('balance');
                if($balance < (int)$t->amount) abort(422,'Saldo kas tidak mencukupi untuk memverifikasi pengeluaran ini.');
            }
            $before=$t->only('status','verified_by','verified_at','rejection_reason');
            $t->update(['status'=>'verified','rejection_reason'=>null,'verified_by'=>$r->attributes->get('api_user')->id,'verified_at'=>now()]);
            StatusHistoryService::record($r,$t,'pending','verified');
            if($t->program_id) $this->syncProgramTotals($t->program_id);
            AuditService::log($r,'transaction.verified',$t,[], $before, $t->fresh()->only('status','verified_by','verified_at','rejection_reason'));
            return $t;
        },3);
        RevisionService::bump('finance');
        return response()->json(['message'=>'Transaksi terverifikasi dan masuk ke kas.','transaction'=>$this->transactionPayload($transaction)]);
    }

    public function reject(Request $r, Transaction $transaction)
    {
        $this->assertTransactionAccess($r, $transaction);
        $d=$r->validate(['reason'=>'required|string|min:5|max:1000']);
        $transaction=DB::transaction(function() use($r,$transaction,$d){
            $t=Transaction::lockForUpdate()->findOrFail($transaction->id);
            $this->assertMakerChecker($r, $t);
            if($t->status!=='pending') abort(422,'Hanya transaksi pending yang dapat ditolak.');
            $before=$t->only('status','verified_by','verified_at','rejection_reason');
            $t->update(['status'=>'rejected','rejection_reason'=>$d['reason'],'verified_by'=>$r->attributes->get('api_user')->id,'verified_at'=>now()]);
            StatusHistoryService::record($r,$t,'pending','rejected',$d['reason']);
            AuditService::log($r,'transaction.rejected',$t,['reason'=>$d['reason']],$before,$t->fresh()->only('status','verified_by','verified_at','rejection_reason'));
            return $t;
        },3);
        RevisionService::bump('finance');
        return response()->json(['message'=>'Transaksi ditolak.']);
    }



    public function cancelRequest(Request $r, Transaction $transaction)
    {
        $this->assertTransactionAccess($r,$transaction);
        $d=$r->validate(['reason'=>'required|string|min:5|max:1000']);
        abort_unless($transaction->status === 'verified', 422, 'Hanya transaksi terverifikasi yang dapat diajukan pembatalan.');
        abort_if(CancellationRequest::where('transaction_id',$transaction->id)->where('status','pending')->exists(), 422, 'Sudah ada pengajuan pembatalan yang menunggu proses.');
        $c=CancellationRequest::create([
            'transaction_id'=>$transaction->id,
            'requester_id'=>$r->attributes->get('api_user')->id,
            'reason'=>$d['reason']
        ]);
        AuditService::log($r,'transaction.cancel.requested',$c,['reason'=>$d['reason']]);
        return response()->json(['message'=>'Pengajuan pembatalan dikirim.','request'=>$c],201);
    }

    public function approveCancellation(Request $r, CancellationRequest $cancellation)
    {
        return $this->processCancellation($r,$cancellation,'approved');
    }

    public function rejectCancellation(Request $r, CancellationRequest $cancellation)
    {
        return $this->processCancellation($r,$cancellation,'rejected');
    }

    private function processCancellation(Request $r, CancellationRequest $cancellation, string $status)
    {
        $this->assertTransactionAccess($r, $cancellation->transaction);

        $cancellation = DB::transaction(function() use($r,$cancellation,$status){
            $cancellation = CancellationRequest::lockForUpdate()->findOrFail($cancellation->id);
            abort_unless($cancellation->status === 'pending', 422, 'Pengajuan sudah diproses.');

            $transaction = Transaction::lockForUpdate()->findOrFail($cancellation->transaction_id);
            abort_unless($transaction->status === 'verified', 422, 'Hanya transaksi terverifikasi yang dapat dibatalkan.');
            $this->assertTransactionAccess($r, $transaction);

            $from = $transaction->status;
            $cancellation->update([
                'status'=>$status,
                'approver_id'=>$r->attributes->get('api_user')->id,
                'processed_at'=>now()
            ]);

            if($status==='approved') {
                $transaction->update(['status'=>'rejected','rejection_reason'=>'Dibatalkan','verified_at'=>now()]);
                StatusHistoryService::record($r,$transaction,$from,'rejected','Dibatalkan');
                RevisionService::bump('finance');
            }

            AuditService::log($r,'transaction.cancel.'.$status,$cancellation);
            return $cancellation->fresh();
        },3);

        return response()->json(['message'=>'Status pembatalan diperbarui.','request'=>$cancellation]);
    }

    private function assertTransactionAccess(Request $r, Transaction $transaction): void
    {
        $user = $r->attributes->get('api_user');
        abort_unless(in_array((int)$transaction->region_id, $this->allowedRegionIds($user), true), 403, 'Transaksi bukan wilayah Anda.');
    }

    private function assertMakerChecker(Request $r, Transaction $t): void
    {
        $userId=(int)$r->attributes->get('api_user')->id;
        if((int)$t->created_by === $userId){
            abort(422, 'Tidak bisa memverifikasi transaksi buatan sendiri.');
        }
    }

    private function syncProgramTotals(int $programId): void
    {
        $program=Program::lockForUpdate()->find($programId);
        if(!$program) return;
        $totals=Transaction::where('program_id',$programId)->where('status','verified')
            ->selectRaw("coalesce(sum(case when type='pemasukan' then amount else 0 end),0) as incoming, coalesce(sum(case when type='pengeluaran' then amount else 0 end),0) as outgoing")
            ->first();
        $program->forceFill(['collected_amount'=>(int)$totals->incoming,'distributed_amount'=>(int)$totals->outgoing])->save();
    }
}
