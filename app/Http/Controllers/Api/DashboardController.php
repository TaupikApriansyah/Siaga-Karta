<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Ambulance;
use App\Models\Driver;
use App\Models\Program;
use App\Models\Report;
use App\Models\Transaction;
use Illuminate\Http\Request;
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $isAdmin=$request->attributes->get('api_user')->role === 'admin';
        $laporan=Report::with(['citizen:id,name','ambulance:id,code','driver:id,name'])->latest()->limit(100)->get()->map(fn($r)=>[
            'id'=>$r->code,'nama'=>$r->citizen->name,'lokasi'=>$r->pickup_location,'kondisi'=>$r->medical_condition,'jenis'=>$r->type,'kategori'=>$r->category,'status'=>$r->status,'tgl'=>$r->created_at,'sumber'=>$r->source,
            'scheduled_at'=>$r->scheduled_at,'service_start_at'=>$r->service_start_at,'service_end_at'=>$r->service_end_at,'ambulance'=>$r->ambulance?->code,'driver'=>$r->driver?->name,
        ]);
        $ambulans=Ambulance::orderBy('code')->get()->map(fn($a)=>['db_id'=>$a->id,'id'=>$a->code,'nopol'=>$a->plate_number,'kapasitas'=>$a->capacity,'status'=>$a->status]);
        $driver=Driver::orderBy('code')->get()->map(fn($d)=>['db_id'=>$d->id,'id'=>$d->code,'nama'=>$d->name,'status'=>$d->status]);
        $transaksi=($isAdmin ? Transaction::latest('transaction_date')->latest('id')->limit(150)->get() : collect())->map(fn($t)=>['db_id'=>$t->id,'id'=>$t->code,'tipe'=>$t->type,'kategori'=>$t->category,'nominal'=>$t->amount,'status'=>$t->status,'tgl'=>$t->transaction_date->format('Y-m-d'),'source'=>$t->source,'payer_name'=>$t->payer_name,'payer_phone_last4'=>$t->payer_phone_last4,'has_proof'=>(bool)$t->payment_proof_path,'rejection_reason'=>$t->rejection_reason]);
        $program=Program::latest()->get()->map(fn($p)=>['id'=>$p->code,'nama'=>$p->name,'target'=>$p->target_amount,'terkumpul'=>$p->collected_amount,'tersalurkan'=>$p->distributed_amount,'status'=>$p->status,'img'=>$p->image_url]);
        $daily=Report::selectRaw("date(created_at) as d, sum(case when category='ambulans' then 1 else 0 end) darurat, sum(case when category in ('bpjs','bencana') then 1 else 0 end) sosial")->where('created_at','>=',now()->subDays(6)->startOfDay())->groupBy('d')->orderBy('d')->get();
        return response()->json(['db'=>compact('laporan','ambulans','driver','transaksi','program'),'stats'=>[
            'saldo'=>$isAdmin ? (int)Transaction::where('status','verified')->selectRaw("coalesce(sum(case when type='pemasukan' then amount else -amount end),0) s")->value('s') : null,
            'pemasukan_bulan'=>$isAdmin ? (int)Transaction::where('status','verified')->where('type','pemasukan')->whereBetween('transaction_date',[now()->startOfMonth()->toDateString(),now()->endOfMonth()->toDateString()])->sum('amount') : null,
            'pengeluaran_bulan'=>$isAdmin ? (int)Transaction::where('status','verified')->where('type','pengeluaran')->whereBetween('transaction_date',[now()->startOfMonth()->toDateString(),now()->endOfMonth()->toDateString()])->sum('amount') : null,
            'laporan_aktif'=>Report::whereNotIn('status',['selesai','ditolak'])->count(),
            'ambulans_tersedia'=>Ambulance::where('status','!=','maintenance')->whereNotIn('id',Report::whereNotIn('status',['selesai','ditolak'])->whereIn('status',['diproses','dijemput'])->whereNotNull('ambulance_id')->where(function($q){$q->where(function($x){$x->whereNotNull('service_start_at')->whereNotNull('service_end_at')->where('service_start_at','<=',now())->where('service_end_at','>',now());})->orWhereNull('service_start_at');})->pluck('ambulance_id'))->count(),
            'daily'=>$daily,
        ]]);
    }
}
