<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Ambulance;
use App\Models\Citizen;
use App\Models\Program;
use App\Models\Report;
use App\Services\NikValidator;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicController extends Controller
{
    public function bootstrap()
    {
        $programs=Program::where('status','aktif')->orderByDesc('created_at')->get()->map(fn($p)=>[
            'id'=>$p->code,'nama'=>$p->name,'target'=>$p->target_amount,'terkumpul'=>$p->collected_amount,
            'tersalurkan'=>$p->distributed_amount,'status'=>$p->status,'img'=>$p->image_url,
        ]);
        $busyIds=Report::where('category','ambulans')->whereNotIn('status',['selesai','ditolak'])->whereIn('status',['diproses','dijemput'])
            ->whereNotNull('ambulance_id')->where(function($q){$q->where(function($x){$x->whereNotNull('service_start_at')->whereNotNull('service_end_at')->where('service_start_at','<=',now())->where('service_end_at','>',now());})->orWhereNull('service_start_at');})->pluck('ambulance_id');
        return response()->json([
            'ambulans_tersedia'=>Ambulance::where('status','!=','maintenance')->whereNotIn('id',$busyIds)->count(),
            'layanan_selesai'=>Report::where('status','selesai')->count(),'program_aktif'=>$programs->count(),'bantuan_disalurkan'=>(int)Program::sum('distributed_amount'),'program'=>$programs,
        ]);
    }

    public function storeReport(Request $request)
    {
        $data=$request->validate([
            'category'=>'nullable|in:ambulans,bpjs,bencana',
            'type'=>'required|in:darurat,terjadwal',
            'name'=>'required|string|min:3|max:120',
            'phone'=>['required','string','max:20','regex:/^(?:\+62|62|0)8[1-9][0-9]{6,11}$/'],
            'nik'=>'required|digits:16',
            'medical_condition'=>'required|string|min:3|max:1500',
            'pickup_location'=>'required|string|min:5|max:2000',
            'destination'=>'nullable|string|max:1000',
            'scheduled_at'=>'nullable|date|after:now',
            'service_duration_minutes'=>'nullable|integer|min:30|max:720',
            'ktp'=>'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'website'=>'nullable|string|max:0',
        ]);
        $category=$data['category']??'ambulans';
        if($category==='ambulans' && $data['type']==='terjadwal') {
            if(empty($data['scheduled_at'])) return response()->json(['message'=>'Waktu penjemputan wajib diisi untuk ambulans terjadwal.'],422);
            if(!$request->hasFile('ktp')) return response()->json(['message'=>'Foto KTP wajib diunggah untuk ambulans terjadwal.'],422);
        }
        if($category!=='ambulans') {
            $data['type']='darurat';
            $data['scheduled_at']=null;
            $data['service_duration_minutes']=null;
            $data['destination']=null;
        }
        if (!NikValidator::isValid($data['nik'])) return response()->json(['message'=>'NIK tidak lolos pemeriksaan struktur dasar. Periksa kembali 16 digit NIK.','errors'=>['nik'=>['NIK tidak valid.']]],422);
        $phone=preg_replace('/\D+/','',$data['phone']); if (str_starts_with($phone,'62')) $phone='0'.substr($phone,2);

        [$report,$code]=DB::transaction(function() use($request,$data,$phone,$category){
            $citizen=Citizen::where('nik_hash',Citizen::fingerprint($data['nik']))->first();
            if (!$citizen) { $citizen=new Citizen(['name'=>trim($data['name'])]); $citizen->setNik($data['nik']); $citizen->setPhone($phone); $citizen->save(); }
            else { $citizen->name=trim($data['name']); $citizen->setPhone($phone); $citizen->save(); }
            $prefix=$category==='bpjs'?'BPJ':($category==='bencana'?'BNC':($data['type']==='darurat'?'LPR':'JDL'));
            do { $code=$prefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(10)); } while(Report::where('code',$code)->exists());
            $ktpPath=null;
            if ($category==='ambulans' && $request->hasFile('ktp')) $ktpPath=$request->file('ktp')->storeAs('ktp',Str::uuid().'.'.$request->file('ktp')->extension(),'local');
            $start=($category==='ambulans' && $data['type']==='terjadwal')?\Carbon\Carbon::parse($data['scheduled_at']):null;
            $end=$start?->copy()->addMinutes((int)($data['service_duration_minutes']??ScheduleService::DEFAULT_DURATION_MINUTES));
            $report=Report::create([
                'code'=>$code,'tracking_key_hash'=>hash('sha256',$code),'citizen_id'=>$citizen->id,'type'=>$data['type'],'category'=>$category,
                'source'=>'website','status'=>'menunggu','pickup_location'=>$data['pickup_location'],'destination'=>$data['destination']??null,
                'medical_condition'=>$data['medical_condition'],'scheduled_at'=>$data['scheduled_at']??null,'service_start_at'=>$start,'service_end_at'=>$end,'ktp_path'=>$ktpPath,
            ]);
            return [$report,$code];
        });
        $message=$category==='ambulans'?'Permohonan berhasil diterima. Penugasan unit akan divalidasi agar tidak bentrok jadwal.':'Pengaduan berhasil diterima Karang Taruna dan masuk antrean penanganan.';
        return response()->json(['message'=>$message,'tracking_code'=>$code,'status'=>$report->status,'category'=>$report->category],201);
    }

    public function track(string $code)
    {
        $report=Report::with(['ambulance:id,code,plate_number','driver:id,code,name'])->where('code',$code)->firstOrFail();
        return response()->json(['report'=>[
            'code'=>$report->code,'category'=>$report->category,'type'=>$report->type,'status'=>$report->status,'created_at'=>$report->created_at,
            'scheduled_at'=>$report->scheduled_at,'service_start_at'=>$report->service_start_at,'service_end_at'=>$report->service_end_at,
            'ambulance'=>$report->category==='ambulans'?$report->ambulance?->code:null,'driver'=>$report->category==='ambulans'?$report->driver?->name:null,
        ]]);
    }

    public function bot(Request $request)
    {
        $q=strtolower(trim((string)$request->validate(['message'=>'required|string|max:300'])['message']));
        if (str_contains($q,'ambulans') || str_contains($q,'tersedia') || str_contains($q,'pinjam')) { $n=$this->bootstrap()->getData(true)['ambulans_tersedia']; $reply=$n>0?"Saat ini ada {$n} ambulans yang tidak sedang digunakan. Jadwal final tetap diperiksa sistem agar tidak bentrok.":'Saat ini belum ada ambulans yang bebas. Untuk keadaan gawat, hubungi layanan darurat medis setempat.'; }
        elseif (str_contains($q,'bpjs')) $reply='Pengaduan BPJS dapat dicatat melalui form laporan warga yang sama. Pilih kategori Pengaduan BPJS pada form.';
        elseif (str_contains($q,'bencana')) $reply='Laporan bencana dapat dicatat melalui form laporan warga yang sama. Pilih kategori Laporan Bencana pada form.';
        elseif (str_contains($q,'halo')||str_contains($q,'hai')) $reply='Halo. Saya SiagaBot. Anda dapat menanyakan ketersediaan ambulans dan layanan Siaga Karta.';
        else $reply='Saya belum memahami pertanyaan itu. Coba tanyakan ketersediaan ambulans, BPJS, bencana, atau status layanan.';
        return response()->json(['reply'=>$reply]);
    }
}
