<?php
namespace App\Services;
use App\Models\AuditLog;
use Illuminate\Http\Request;
class AuditService
{
    public static function log(Request $request, string $action, ?object $subject = null, array $metadata = []): void
    {
        AuditLog::create([
            'user_id' => $request->attributes->get('api_user')?->id,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'metadata' => $metadata ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(),0,1000),
        ]);
    }
}
