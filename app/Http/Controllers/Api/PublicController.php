<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ambulance;
use App\Models\Program;
use App\Models\Region;
use App\Models\Report;
use App\Services\AuditService;
use App\Services\CitizenService;
use App\Services\CitizenTrackingMailService;
use App\Services\NikValidator;
use App\Services\RevisionService;
use App\Services\ScheduleService;
use App\Services\StatusHistoryService;
use App\Services\TrackingCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicController extends Controller
{
    public function bootstrap()
    {
        return response()->json(Cache::remember('public.bootstrap',15,function(){
            $programs=Program::where('status','aktif')->orderByDesc('created_at')->limit(30)->get()->map(fn($p)=>[
                'id'=>$p->code,'nama'=>$p->name,'target'=>$p->target_amount,'terkumpul'=>$p->collected_amount,
                'tersalurkan'=>$p->distributed_amount,'status'=>$p->status,'img'=>$p->image_url,
            ]);
            $busyIds=Report::where('category','ambulans')->whereNotIn('status',['selesai','ditolak'])->whereIn('status',['diproses','dijemput'])
                ->whereNotNull('ambulance_id')->where(function($q){$q->where(function($x){$x->whereNotNull('service_start_at')->whereNotNull('service_end_at')->where('service_start_at','<=',now())->where('service_end_at','>',now());})->orWhereNull('service_start_at');})->pluck('ambulance_id');
            return [
                'ambulans_tersedia'=>Ambulance::where('status','!=','maintenance')->whereNotIn('id',$busyIds)->count(),
                'layanan_selesai'=>Report::where('status','selesai')->count(),
                'program_aktif'=>$programs->count(),
                'bantuan_disalurkan'=>(int)Program::sum('distributed_amount'),
                'program'=>$programs,
                'report_categories'=>Report::CATEGORIES,
                'report_priorities'=>Report::PRIORITIES,
                'demo'=>filter_var(env('DEMO_MODE',false), FILTER_VALIDATE_BOOL) ? [
                    'enabled'=>true,
                    'usernames'=>['kota','kecamatan','kelurahan'],
                    'password'=>(string)env('DEMO_PASSWORD','Rajawali21'),
                ] : ['enabled'=>false],
            ];
        }));
    }

    public function regions()
    {
        $rows=Region::query()->where('level','kelurahan')->where('is_active',true)
            ->with('parent:id,code,short_code,name,level')
            ->orderBy('name')->get(['id','code','short_code','name','parent_id','rt_count','rw_count','centroid_latitude','centroid_longitude']);
        return response()->json(['kelurahan'=>$rows]);
    }

    public function storeReport(Request $request)
    {
        $request->merge([
            'email'=>strtolower(trim((string)$request->input('email'))),
            'priority'=>$request->input('priority','reguler'),
            'type'=>$request->input('type'),
        ]);
        $data=$request->validate([
            'request_uuid'=>'nullable|uuid',
            'category'=>['required',Rule::in(Report::CATEGORIES)],
            'priority'=>['required',Rule::in(Report::PRIORITIES)],
            'type'=>[Rule::requiredIf(fn()=> $request->input('category')==='ambulans'),'nullable',Rule::in(['darurat','terjadwal'])],
            'name'=>'required|string|min:3|max:120',
            'phone'=>['required','string','max:20','regex:/^(?:\+62|62|0)8[1-9][0-9]{6,11}$/'],
            'email'=>'required|email:rfc|max:190',
            'nik'=>'required|digits:16',
            'region_id'=>'required|integer|exists:regions,id',
            'pickup_location'=>[Rule::requiredIf(fn()=> $request->input('category')==='ambulans'),'nullable','string','min:5','max:2000'],
            'rt_number'=>'nullable|string|max:10',
            'rw_number'=>'nullable|string|max:10',
            'latitude'=>'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude'=>'nullable|numeric|between:-180,180|required_with:latitude',
            'description'=>'nullable|string|min:3|max:3000',
            'medical_condition'=>'nullable|string|min:3|max:1500',
            'destination'=>'nullable|string|max:1000',
            'scheduled_at'=>'nullable|date|after:now',
            'service_duration_minutes'=>'nullable|integer|min:30|max:720',
            'ktp'=>'nullable|image|mimes:jpg,jpeg,png,webp|max:4096|dimensions:max_width=5000,max_height=5000',
            'website'=>'nullable|string|max:0',
        ]);

        if(!empty($data['request_uuid']) && ($existing=Report::where('request_uuid',$data['request_uuid'])->first())) {
            return response()->json(['message'=>'Pengaduan sudah diterima sebelumnya.','tracking_code'=>$existing->code,'status'=>$existing->status,'category'=>$existing->category]);
        }

        $region=Region::with('parent')->whereKey($data['region_id'])->where('level','kelurahan')->where('is_active',true)->first();
        if(!$region || !$region->parent || $region->parent->level!=='kecamatan') {
            return response()->json(['message'=>'Kelurahan yang dipilih belum terhubung ke Kecamatan yang valid.'],422);
        }

        $category=$data['category'];
        if($category==='ambulans') {
            if(empty($data['medical_condition'])) return response()->json(['message'=>'Kondisi medis wajib diisi untuk layanan ambulans.','errors'=>['medical_condition'=>['Kondisi medis wajib diisi.']]],422);
            if($data['type']==='terjadwal') {
                if(empty($data['scheduled_at'])) return response()->json(['message'=>'Waktu penjemputan wajib diisi untuk ambulans terjadwal.'],422);
                if(!$request->hasFile('ktp')) return response()->json(['message'=>'Foto KTP wajib diunggah untuk ambulans terjadwal.'],422);
            }
        } else {
            if(empty($data['description'])) return response()->json(['message'=>'Isi pengaduan wajib diisi.','errors'=>['description'=>['Jelaskan masalah atau kebutuhan warga.']]],422);
            $data['type']=null;
            $data['scheduled_at']=null;
            $data['service_duration_minutes']=null;
            $data['destination']=null;
            $data['medical_condition']=null;
        }

        if (!NikValidator::isValid($data['nik'])) return response()->json(['message'=>'NIK tidak lolos pemeriksaan struktur dasar.','errors'=>['nik'=>['NIK tidak valid.']]],422);
        $phone=preg_replace('/\D+/','',$data['phone']);
        if (str_starts_with($phone,'62')) $phone='0'.substr($phone,2);

        $ktpPath=null;
        if ($category==='ambulans' && $request->hasFile('ktp')) {
            $ktpPath=$request->file('ktp')->storeAs('ktp',Str::uuid().'.'.$request->file('ktp')->extension(),'local');
            if(!$ktpPath) return response()->json(['message'=>'Foto KTP gagal disimpan. Silakan coba lagi.'],500);
        }

        try {
            $report=DB::transaction(function() use($request,$data,$phone,$category,$ktpPath,$region){
                $citizen=CitizenService::upsertIdentity($data['nik'],$data['name'],$phone,$data['email']);
                $code=TrackingCodeService::nextForKelurahan($region);
                $start=($category==='ambulans' && $data['type']==='terjadwal')?\Carbon\Carbon::parse($data['scheduled_at']):null;
                $end=$start?->copy()->addMinutes((int)($data['service_duration_minutes']??ScheduleService::DEFAULT_DURATION_MINUTES));
                $report=Report::create([
                    'request_uuid'=>$data['request_uuid']??null,'code'=>$code,'tracking_key_hash'=>hash('sha256',$code),'citizen_id'=>$citizen->id,
                    'region_id'=>$region->id,'type'=>$data['type'],'category'=>$category,'priority'=>$data['priority'],'source'=>'website','status'=>'menunggu',
                    'workflow_status'=>'menunggu_kelurahan','escalation_level'=>'kelurahan','pickup_location'=>$data['pickup_location']??null,
                    'rt_number'=>$data['rt_number']??null,'rw_number'=>$data['rw_number']??null,'latitude'=>$data['latitude']??null,'longitude'=>$data['longitude']??null,
                    'destination'=>$data['destination']??null,'medical_condition'=>$data['medical_condition']??null,'description'=>$data['description']??null,
                    'scheduled_at'=>$data['scheduled_at']??null,'service_start_at'=>$start,'service_end_at'=>$end,'ktp_path'=>$ktpPath,
                ]);
                StatusHistoryService::record($request,$report,null,'menunggu_kelurahan');
                return $report;
            },3);
        } catch(\Throwable $e) {
            if($ktpPath) Storage::disk('local')->delete($ktpPath);
            if(!empty($data['request_uuid']) && ($existing=Report::where('request_uuid',$data['request_uuid'])->first())) {
                return response()->json(['message'=>'Pengaduan sudah diterima sebelumnya.','tracking_code'=>$existing->code,'status'=>$existing->status,'category'=>$existing->category]);
            }
            throw $e;
        }

        AuditService::log($request,'report.public_created',$report,['category'=>$report->category,'priority'=>$report->priority,'region_id'=>$report->region_id]);
        RevisionService::bump('operations');
        Cache::forget('public.bootstrap');
        $mailSent=CitizenTrackingMailService::sendCreated($report);
        $message='Pengaduan berhasil diterima dan masuk ke Karang Taruna tingkat Kelurahan untuk verifikasi awal.';
        if($mailSent) $message.=' Kode pelacakan telah dikirim ke Gmail warga.';
        else $message.=' Kode pelacakan tersedia di layar; pengiriman Gmail belum berhasil dan dapat dicoba kembali setelah konfigurasi email aktif.';

        return response()->json([
            'message'=>$message,'tracking_code'=>$report->code,'status'=>$report->status,'workflow_status'=>$report->workflow_status,
            'category'=>$report->category,'email_notification_sent'=>$mailSent,
        ],201);
    }

    public function track(string $code)
    {
        $code=strtoupper(trim($code));
        if(!preg_match('/^(?:SKB-[A-Z0-9]+-\d{4}-\d{5}|(?:LPR|BPJ|BNC|JDL)-\d{8}-[A-Z0-9]{10})$/',$code)) {
            return response()->json(['message'=>'Format kode laporan tidak valid. Gunakan kode pelacakan lengkap, misalnya SKB-ANDIR-2026-00001.'],422);
        }
        $report=Report::with(['ambulance:id,code,plate_number','driver:id,code,name','region:id,name,parent_id','region.parent:id,name'])->where('code',$code)->first();
        if(!$report) return response()->json(['message'=>'Kode laporan tidak ditemukan. Periksa kembali kode yang Anda masukkan.'],404);
        return response()->json(['report'=>[
            'code'=>$report->code,'category'=>$report->category,'priority'=>$report->priority,'type'=>$report->type,'status'=>$report->status,
            'workflow_status'=>$report->workflow_status,'escalation_level'=>$report->escalation_level,'created_at'=>$report->created_at,
            'scheduled_at'=>$report->scheduled_at,'service_start_at'=>$report->service_start_at,'service_end_at'=>$report->service_end_at,
            'kelurahan'=>$report->region?->name,'kecamatan'=>$report->region?->parent?->name,'assigned_agency'=>$report->assigned_agency,
            'ambulance'=>$report->category==='ambulans'?$report->ambulance?->code:null,'driver'=>$report->category==='ambulans'?$report->driver?->name:null,
        ]]);
    }

    public function bot(Request $request)
    {
        $raw=trim((string)$request->validate(['message'=>'required|string|max:300'])['message']);
        $q=strtolower($raw);
        preg_match('/(?:SKB-[A-Z0-9]+-\d{4}-\d{5}|(?:LPR|BPJ|BNC|JDL)-\d{8}-[A-Z0-9]{10})/i',$raw,$codeMatch);
        if(!empty($codeMatch[0])) {
            $code=strtoupper($codeMatch[0]);
            $report=Report::with(['region.parent','ambulance:id,code','driver:id,name'])->where('code',$code)->first();
            if(!$report) $reply="Kode {$code} tidak ditemukan. Periksa kembali kode laporan Anda.";
            else {
                $wilayah=$report->region ? ' Wilayah: '.$report->region->name.', '.($report->region->parent?->name ?? '-').'.' : '';
                $reply="Status {$code}: ".strtoupper(str_replace('_',' ',$report->workflow_status)).'.'.$wilayah;
            }
        }
        elseif (str_contains($q,'ambulans') || str_contains($q,'tersedia') || str_contains($q,'pinjam')) { $n=$this->bootstrap()->getData(true)['ambulans_tersedia']; $reply=$n>0?"Saat ini ada {$n} ambulans yang tidak sedang digunakan. Jadwal final tetap diperiksa sistem agar tidak bentrok.":'Saat ini belum ada ambulans yang bebas. Untuk keadaan gawat, hubungi layanan darurat medis setempat.'; }
        elseif ($q==='cek' || str_contains($q,'cek status') || str_contains($q,'status layanan') || str_contains($q,'lacak')) $reply='Untuk memeriksa status, kirim kode lengkap seperti SKB-ANDIR-2026-00001 atau gunakan menu Periksa Status Layanan.';
        elseif (str_contains($q,'kategori')) $reply='Kategori pengaduan: kesehatan, BPJS, ambulans, lansia/disabilitas, bantuan sosial, orang terlantar, anak & keluarga, data sosial/desil, kebencanaan, dan lainnya.';
        elseif (str_contains($q,'halo')||str_contains($q,'hai')) $reply='Halo. Saya SiagaBot. Anda dapat menanyakan ketersediaan ambulans, kategori pengaduan, atau status layanan dengan kode pelacakan.';
        else $reply='Saya belum memahami pertanyaan itu. Coba ketik "cek ambulans", "kategori pengaduan", atau kirim kode pelacakan lengkap.';
        return response()->json(['reply'=>$reply]);
    }
}
