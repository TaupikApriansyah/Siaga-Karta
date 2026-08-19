<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ambulance;
use App\Models\AuditLog;
use App\Models\Driver;
use App\Models\Program;
use App\Models\Region;
use App\Models\Report;
use App\Models\Transaction;
use App\Services\ReportAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user=$request->attributes->get('api_user');
        $role=$user->role;
        $canOperations=in_array($role,['kota','kecamatan','kelurahan'],true);
        $canCityResources=$role==='kota';
        $canFinance=$role==='kota';

        $reportScope=fn()=>ReportAccessService::scope(Report::query(),$user);

        // Daftar pelayanan dan transaksi memiliki endpoint pagination sendiri.
        // Dashboard hanya mengirim data ringkas agar payload tidak membengkak setiap sinkronisasi.
        $laporan=collect();
        $transaksi=collect();
        $ambulans=$canCityResources ? Ambulance::orderBy('code')->get()->map(fn($a)=>['db_id'=>$a->id,'id'=>$a->code,'nopol'=>$a->plate_number,'kapasitas'=>$a->capacity,'status'=>$a->status,'notes'=>$a->notes]) : collect();
        $driver=$canCityResources ? Driver::orderBy('code')->get()->map(fn($d)=>['db_id'=>$d->id,'id'=>$d->code,'nama'=>$d->name,'status'=>$d->status]) : collect();
        $program=$canCityResources ? Program::latest()->limit(30)->get()->map(fn($p)=>['id'=>$p->code,'nama'=>$p->name,'target'=>$p->target_amount,'terkumpul'=>$p->collected_amount,'tersalurkan'=>$p->distributed_amount,'status'=>$p->status,'img'=>$p->image_url]) : collect();

        $daily=collect();
        if($canOperations){
            $dailyRows=$reportScope()->selectRaw("date(created_at) as d, sum(case when priority='darurat' then 1 else 0 end) darurat, sum(case when priority<>'darurat' then 1 else 0 end) sosial")
                ->where('created_at','>=',now()->subDays(6)->startOfDay())->groupBy('d')->get()->keyBy('d');
            for($i=6;$i>=0;$i--){
                $date=now()->subDays($i); $row=$dailyRows->get($date->toDateString());
                $daily->push(['name'=>$date->translatedFormat('D'),'darurat'=>(int)($row?->darurat??0),'sosial'=>(int)($row?->sosial??0)]);
            }
        }

        $activityQuery=AuditLog::query()->select('id','user_id','action','subject_type','subject_id','created_at')->with('user:id,name')->latest()->limit(8);
        if(in_array($role,['kecamatan','kelurahan'],true)) {
            $accessibleReportIds=ReportAccessService::scope(Report::query(),$user)->select('reports.id');
            $activityQuery->where(function($q)use($accessibleReportIds,$user){
                $q->where(function($x)use($accessibleReportIds){
                    $x->where('subject_type',Report::class)->whereIn('subject_id',$accessibleReportIds);
                });
                if($user->role==='kelurahan' && $user->region_id) {
                    $q->orWhere(function($x)use($user){
                        $x->where('subject_type',Region::class)->where('subject_id',$user->region_id);
                    });
                }
            });
        }

        $saldo=$pemasukan=$pengeluaran=null;
        $financePending=0;
        if($canFinance){
            $saldo=(int)Transaction::where('status','verified')->selectRaw("coalesce(sum(case when type='pemasukan' then amount else -amount end),0) s")->value('s');
            $pemasukan=(int)Transaction::where('status','verified')->where('type','pemasukan')->whereBetween('transaction_date',[now()->startOfMonth()->toDateString(),now()->endOfMonth()->toDateString()])->sum('amount');
            $pengeluaran=(int)Transaction::where('status','verified')->where('type','pengeluaran')->whereBetween('transaction_date',[now()->startOfMonth()->toDateString(),now()->endOfMonth()->toDateString()])->sum('amount');
            $financePending=Transaction::where('status','pending')->count();
        }

        $activeReports=$canOperations?$reportScope()->whereNotIn('status',['selesai','ditolak'])->count():null;
        $workflowSummary=$canOperations?$reportScope()->selectRaw('workflow_status, count(*) total')->groupBy('workflow_status')->pluck('total','workflow_status'):collect();
        $region=$user->region?->loadMissing('parent');

        return response()->json(['db'=>compact('laporan','ambulans','driver','transaksi','program'),'stats'=>[
            'saldo'=>$saldo,'pemasukan_bulan'=>$pemasukan,'pengeluaran_bulan'=>$pengeluaran,'finance_pending'=>$financePending,
            'laporan_aktif'=>$activeReports,
            'ambulans_tersedia'=>$canOperations?Ambulance::where('status','tersedia')->count():null,
            'daily'=>$daily,'workflow_summary'=>$workflowSummary,
            'region'=>$region?->only('id','code','short_code','name','level','parent_id','rt_count','rw_count'),
            'activity'=>$activityQuery->get()->map(fn($a)=>['id'=>$a->id,'action'=>$a->action,'subject_type'=>$a->subject_type,'subject_id'=>$a->subject_id,'actor'=>$a->user?->name ?? 'Sistem','created_at'=>$a->created_at]),
        ]]);
    }

    public function cityMap(Request $request)
    {
        $data=$request->validate([
            'category'=>['nullable',Rule::in(Report::CATEGORIES)],
            'status'=>'nullable|in:menunggu,diproses,dijemput,selesai,ditolak',
            'priority'=>['nullable',Rule::in(Report::PRIORITIES)],
            'kecamatan_id'=>'nullable|integer|exists:regions,id',
            'kelurahan_id'=>'nullable|integer|exists:regions,id',
            'date_from'=>'nullable|date',
            'date_to'=>'nullable|date|after_or_equal:date_from',
            'marker_limit'=>'nullable|integer|min:100|max:5000',
        ]);

        $base=Report::query()->whereNotNull('region_id');
        $this->applyMapFilters($base,$data);

        // Laporan historis yang belum memiliki relasi wilayah tidak dipaksakan ke
        // kelurahan tertentu. Jumlahnya dilaporkan terpisah agar monitoring tetap jujur.
        $unmappedQuery=Report::query()->whereNull('region_id');
        $this->applyMapFilters($unmappedQuery,$data);
        $unmappedReports=(clone $unmappedQuery)->count();

        $total=(clone $base)->count();
        $today=(clone $base)->whereDate('created_at',today())->count();
        $processing=(clone $base)->whereIn('status',['diproses','dijemput'])->count();
        $completed=(clone $base)->where('status','selesai')->count();
        $emergency=(clone $base)->where('priority','darurat')->count();

        $categoryRows=(clone $base)->selectRaw('category, count(*) total')->groupBy('category')->orderByDesc('total')->get();
        $categoryDistribution=$categoryRows->map(fn($row)=>[
            'category'=>$row->category,'total'=>(int)$row->total,
            'percentage'=>$total>0?round(((int)$row->total/$total)*100,1):0,
        ])->values();

        $kelurahanTotals=(clone $base)->selectRaw('region_id, count(*) total')->groupBy('region_id')->pluck('total','region_id');
        $kelurahanCategoryRows=(clone $base)->selectRaw('region_id, category, count(*) total')->groupBy('region_id','category')->get()->groupBy('region_id');

        $kelurahanQuery=Region::query()->where('level','kelurahan')->where('is_active',true)->with('parent:id,code,short_code,name,level');
        if(!empty($data['kecamatan_id'])) $kelurahanQuery->where('parent_id',$data['kecamatan_id']);
        if(!empty($data['kelurahan_id'])) $kelurahanQuery->whereKey($data['kelurahan_id']);
        $kelurahanStats=$kelurahanQuery->orderBy('name')->get()->map(function($region)use($kelurahanTotals,$kelurahanCategoryRows){
            $regionTotal=(int)($kelurahanTotals[$region->id]??0);
            $categories=collect($kelurahanCategoryRows[$region->id]??[])->map(fn($row)=>[
                'category'=>$row->category,'total'=>(int)$row->total,
                'percentage'=>$regionTotal>0?round(((int)$row->total/$regionTotal)*100,1):0,
            ])->sortByDesc('total')->values();
            return [
                'id'=>$region->id,'code'=>$region->code,'short_code'=>$region->short_code,'name'=>$region->name,'geojson_name'=>$region->geojson_name,
                'kecamatan_id'=>$region->parent_id,'kecamatan'=>$region->parent?->name,'kecamatan_code'=>$region->parent?->short_code,
                'rt_count'=>$region->rt_count,'rw_count'=>$region->rw_count,'total'=>$regionTotal,'categories'=>$categories,
            ];
        })->values();

        $topKelurahan=$kelurahanStats->sortByDesc('total')->take(5)->values();
        $topCategory=$categoryDistribution->first();
        $topVillage=$topKelurahan->first();

        $markerLimit=(int)($data['marker_limit']??2000);
        $markerBase=clone $base;
        $markerCount=(clone $markerBase)->whereNotNull('latitude')->whereNotNull('longitude')->count();
        $markers=$markerBase->whereNotNull('latitude')->whereNotNull('longitude')
            ->select('id','code','region_id','category','priority','status','workflow_status','pickup_location','rt_number','rw_number','latitude','longitude','created_at')
            ->with(['region:id,name,parent_id','region.parent:id,name'])
            ->latest('id')->limit($markerLimit)->get()->map(fn($r)=>[
                'id'=>$r->code,'category'=>$r->category,'priority'=>$r->priority,'status'=>$r->status,'workflow_status'=>$r->workflow_status,
                'kelurahan'=>$r->region?->name,'kecamatan'=>$r->region?->parent?->name,'time'=>$r->created_at,
                'location'=>$r->pickup_location ?: (($r->rt_number || $r->rw_number) ? 'RT '.($r->rt_number??'-').' / RW '.($r->rw_number??'-') : $r->region?->name),
                'latitude'=>$r->latitude,'longitude'=>$r->longitude,
            ]);

        $latest=(clone $base)->select('id','code','region_id','category','priority','status','workflow_status','created_at')
            ->with(['region:id,name,parent_id','region.parent:id,name'])->latest('id')->limit(12)->get()->map(fn($r)=>[
                'id'=>$r->code,'category'=>$r->category,'priority'=>$r->priority,'status'=>$r->status,'workflow_status'=>$r->workflow_status,
                'kelurahan'=>$r->region?->name,'kecamatan'=>$r->region?->parent?->name,'created_at'=>$r->created_at,
            ]);

        $districts=Region::query()->where('level','kecamatan')->where('is_active',true)->orderBy('name')->get(['id','code','short_code','name']);
        $villages=Region::query()->where('level','kelurahan')->where('is_active',true)->orderBy('name')->get(['id','code','short_code','name','parent_id']);

        return response()->json([
            'stats'=>[
                'total'=>$total,'today'=>$today,'processing'=>$processing,'completed'=>$completed,'emergency'=>$emergency,
                'unmapped_reports'=>$unmappedReports,
                'top_kelurahan'=>$topVillage['name']??'-','top_kelurahan_total'=>(int)($topVillage['total']??0),
                'top_category'=>$topCategory['category']??'-','top_category_total'=>(int)($topCategory['total']??0),
            ],
            'category_distribution'=>$categoryDistribution,
            'top_kelurahan'=>$topKelurahan,
            'kelurahan_stats'=>$kelurahanStats,
            'markers'=>$markers,'marker_count'=>$markerCount,'markers_truncated'=>$markerCount>$markerLimit,
            'latest'=>$latest,'filters'=>['kecamatan'=>$districts,'kelurahan'=>$villages],
            'geojson'=>[
                'url'=>(string)config('siagakarta.map.bandung_kelurahan_geojson_url'),
                'source'=>'Batas administrasi Kota Bandung berbasis GeoJSON; dapat diganti melalui BANDUNG_KELURAHAN_GEOJSON_URL.',
            ],
            'generated_at'=>now()->toIso8601String(),
        ]);
    }

    public function kelurahanDetail(Request $request, Region $region)
    {
        abort_unless($region->level==='kelurahan',404);
        $data=$request->validate([
            'category'=>['nullable',Rule::in(Report::CATEGORIES)],'status'=>'nullable|in:menunggu,diproses,dijemput,selesai,ditolak',
            'priority'=>['nullable',Rule::in(Report::PRIORITIES)],'date_from'=>'nullable|date','date_to'=>'nullable|date|after_or_equal:date_from',
        ]);
        $q=Report::query()->where('region_id',$region->id);
        $this->applyMapFilters($q,$data);
        $total=(clone $q)->count();
        $categories=(clone $q)->selectRaw('category, count(*) total')->groupBy('category')->orderByDesc('total')->get()->map(fn($r)=>[
            'category'=>$r->category,'total'=>(int)$r->total,'percentage'=>$total>0?round(((int)$r->total/$total)*100,1):0,
        ]);
        $latest=(clone $q)->select('id','code','category','priority','status','workflow_status','pickup_location','created_at')->latest('id')->limit(10)->get();
        $region->load('parent:id,name,short_code');
        return response()->json(['kelurahan'=>[
            'id'=>$region->id,'name'=>$region->name,'kecamatan'=>$region->parent?->name,'rt_count'=>$region->rt_count,'rw_count'=>$region->rw_count,
            'total'=>$total,'categories'=>$categories,'latest'=>$latest,
        ]]);
    }

    private function applyMapFilters(Builder $q, array $data): void
    {
        if(!empty($data['category'])) $q->where('category',$data['category']);
        if(!empty($data['status'])) $q->where('status',$data['status']);
        if(!empty($data['priority'])) $q->where('priority',$data['priority']);
        if(!empty($data['kelurahan_id'])) $q->where('region_id',$data['kelurahan_id']);
        elseif(!empty($data['kecamatan_id'])) {
            $q->whereIn('region_id',Region::query()->where('level','kelurahan')->where('parent_id',$data['kecamatan_id'])->select('id'));
        }
        if(!empty($data['date_from'])) $q->where('created_at','>=',\Carbon\Carbon::parse($data['date_from'])->startOfDay());
        if(!empty($data['date_to'])) $q->where('created_at','<=',\Carbon\Carbon::parse($data['date_to'])->endOfDay());
    }
}
