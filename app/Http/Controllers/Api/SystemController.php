<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\AppNotification;
use App\Models\Report;
use App\Models\Region;
use App\Services\ReportAccessService;
use App\Services\RevisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SystemController extends Controller
{
    public function sync(Request $request)
    {
        $user=$request->attributes->get('api_user');
        $notificationState=AppNotification::query()->where('user_id',$user->id)
            ->selectRaw('coalesce(max(id),0) as latest_id, sum(case when read_at is null then 1 else 0 end) as unread')
            ->first();
        return response()->json([
            'revisions'=>RevisionService::snapshot($user->role),
            'notifications'=>['latest_id'=>(int)($notificationState?->latest_id ?? 0),'unread'=>(int)($notificationState?->unread ?? 0)],
            'server_time'=>now()->toIso8601String(),
        ]);
    }

    public function health()
    {
        $db=false; $storage=false; $queue=false;
        try { DB::select('select 1'); $db=true; } catch(\Throwable $e) {}
        try {
            $probe='health/.probe'; Storage::disk('local')->put($probe,'ok'); $storage=Storage::disk('local')->exists($probe); Storage::disk('local')->delete($probe);
        } catch(\Throwable $e) {}
        try {
            $driver=(string)config('queue.default');
            if($driver==='database') {
                $table=(string)config('queue.connections.database.table','jobs');
                DB::table($table)->limit(1)->count();
                $queue=true;
            } else {
                $queue=in_array($driver,['sync','null'],true);
            }
        } catch(\Throwable $e) {}
        $ok=$db && $storage && $queue;
        return response()->json(['status'=>$ok?'ok':'degraded','checks'=>['database'=>$db,'storage'=>$storage,'queue_backend'=>$queue,'queue_driver'=>config('queue.default')]],$ok?200:503);
    }

    public function activity(Request $request)
    {
        $user=$request->attributes->get('api_user');
        $role=$user->role;
        $q=AuditLog::query()->select('id','user_id','action','subject_type','subject_id','created_at')->with('user:id,name')->latest()->limit(20);
        if(in_array($role,['kecamatan','kelurahan'],true)) {
            $accessibleReportIds=ReportAccessService::scope(Report::query(),$user)->select('reports.id');
            $q->where(function($x)use($accessibleReportIds,$user){
                $x->where(function($r)use($accessibleReportIds){
                    $r->where('subject_type',Report::class)->whereIn('subject_id',$accessibleReportIds);
                });
                if($user->role==='kelurahan' && $user->region_id) {
                    $x->orWhere(function($r)use($user){
                        $r->where('subject_type',Region::class)->where('subject_id',$user->region_id);
                    });
                }
            });
        }
        return response()->json(['activity'=>$q->get()->map(fn($a)=>['id'=>$a->id,'action'=>$a->action,'subject_type'=>$a->subject_type,'subject_id'=>$a->subject_id,'actor'=>$a->user?->name ?? 'Sistem','created_at'=>$a->created_at])]);
    }
}
