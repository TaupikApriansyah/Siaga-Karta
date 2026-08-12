<?php
namespace App\Services;

use App\Models\StatusHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class StatusHistoryService
{
    public static function record(Request $request, Model $subject, ?string $from, string $to, ?string $reason=null, array $metadata=[]): void
    {
        StatusHistory::create([
            'subject_type'=>get_class($subject),
            'subject_id'=>$subject->getKey(),
            'from_status'=>$from,
            'to_status'=>$to,
            'changed_by'=>$request->attributes->get('api_user')?->id,
            'reason'=>$reason,
            'metadata'=>$metadata ?: null,
        ]);
    }
}
