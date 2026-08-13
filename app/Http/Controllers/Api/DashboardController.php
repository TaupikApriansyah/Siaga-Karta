<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ambulance;
use App\Models\AuditLog;
use App\Models\Driver;
use App\Models\Program;
use App\Models\Report;
use App\Models\Transaction;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $role=$request->attributes->get('api_user')->role;
        $canOperations=in_array($role,['admin','petugas'],true);
        $canFinance=in_array($role,['admin','petugas'],true);

        $laporan=$canOperations
            ? Report::query()->select('id','code','citizen_id','ambulance_id','driver_id','pickup_location','medical_condition','type','category','status','source','scheduled_at','service_start_at','service_end_at','created_at')
                ->with(['citizen:id,name','ambulance:id,code','driver:id,name'])->latest()->limit(50)->get()->map(fn($r)=>[
                    'id'=>$r->code,'nama'=>$r->citizen->name,'lokasi'=>$r->pickup_location,'kondisi'=>$r->medical_condition,'jenis'=>$r->type,'kategori'=>$r->category,
                    'status'=>$r->status,'tgl'=>$r->created_at,'sumber'=>$r->source,'scheduled_at'=>$r->scheduled_at,'service_start_at'=>$r->service_start_at,
                    'service_end_at'=>$r->service_end_at,'ambulance'=>$r->ambulance?->code,'driver'=>$r->driver?->name,
                ]) : collect();

        $ambulans=$canOperations ? Ambulance::orderBy('code')->get()->map(fn($a)=>['db_id'=>$a->id,'id'=>$a->code,'nopol'=>$a->plate_number,'kapasitas'=>$a->capacity,'status'=>$a->status,'notes'=>$a->notes]) : collect();
        $driver=$canOperations ? Driver::orderBy('code')->get()->map(fn($d)=>['db_id'=>$d->id,'id'=>$d->code,'nama'=>$d->name,'status'=>$d->status]) : collect();
        $program=$canOperations ? Program::latest()->limit(50)->get()->map(fn($p)=>['id'=>$p->code,'nama'=>$p->name,'target'=>$p->target_amount,'terkumpul'=>$p->collected_amount,'tersalurkan'=>$p->distributed_amount,'status'=>$p->status,'img'=>$p->image_url]) : collect();
        $transaksi=$canFinance ? Transaction::latest('transaction_date')->latest('id')->limit(75)->get()->map(fn($t)=>[
            'db_id'=>$t->id,'id'=>$t->code,'tipe'=>$t->type,'kategori'=>$t->category,'nominal'=>$t->amount,'status'=>$t->status,'tgl'=>$t->transaction_date->format('Y-m-d'),
            'source'=>$t->source,'payer_name'=>$t->payer_name,'payer_phone_last4'=>$t->payer_phone_last4,'has_proof'=>(bool)$t->payment_proof_path,
            'rejection_reason'=>$t->rejection_reason,'description'=>$t->description,
        ]) : collect();

        $daily=collect();
        if($canOperations){
            $dailyRows=Report::selectRaw("date(created_at) as d, sum(case when category='ambulans' then 1 else 0 end) darurat, sum(case when category in ('bpjs','bencana') then 1 else 0 end) sosial")
                ->where('created_at','>=',now()->subDays(6)->startOfDay())->groupBy('d')->get()->keyBy('d');
            for($i=6;$i>=0;$i--){
                $date=now()->subDays($i); $row=$dailyRows->get($date->toDateString());
                $daily->push(['name'=>$date->translatedFormat('D'),'darurat'=>(int)($row?->darurat??0),'sosial'=>(int)($row?->sosial??0)]);
            }
        }

        $activityQuery=AuditLog::query()->select('id','user_id','action','subject_type','subject_id','created_at')->with('user:id,name')->latest()->limit(8);
        if($role==='petugas') $activityQuery->where(function($q){
            $q->where('action','like','report.%')->orWhere('action','like','ambulance.%')
              ->orWhere('action','like','transaction.%')->orWhere('action','like','infaq.%');
        });

        $saldo=$pemasukan=$pengeluaran=null;
        if($canFinance){
            $saldo=(int)Transaction::where('status','verified')->selectRaw("coalesce(sum(case when type='pemasukan' then amount else -amount end),0) s")->value('s');
            $pemasukan=(int)Transaction::where('status','verified')->where('type','pemasukan')->whereBetween('transaction_date',[now()->startOfMonth()->toDateString(),now()->endOfMonth()->toDateString()])->sum('amount');
            $pengeluaran=(int)Transaction::where('status','verified')->where('type','pengeluaran')->whereBetween('transaction_date',[now()->startOfMonth()->toDateString(),now()->endOfMonth()->toDateString()])->sum('amount');
        }

        return response()->json(['db'=>compact('laporan','ambulans','driver','transaksi','program'),'stats'=>[
            'saldo'=>$saldo,'pemasukan_bulan'=>$pemasukan,'pengeluaran_bulan'=>$pengeluaran,
            'laporan_aktif'=>$canOperations?Report::whereNotIn('status',['selesai','ditolak'])->count():null,
            'ambulans_tersedia'=>$canOperations?Ambulance::where('status','tersedia')->count():null,
            'daily'=>$daily,'activity'=>$activityQuery->get()->map(fn($a)=>['id'=>$a->id,'action'=>$a->action,'subject_type'=>$a->subject_type,'subject_id'=>$a->subject_id,'actor'=>$a->user?->name ?? 'Sistem','created_at'=>$a->created_at]),
        ]]);
    }
}
