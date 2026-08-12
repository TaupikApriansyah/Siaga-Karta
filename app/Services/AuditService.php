<?php
namespace App\Services;
use App\Models\AuditLog;
use Illuminate\Http\Request;
class AuditService
{
    public static function log(Request $request, string $action, ?object $subject = null, array $metadata = [], ?array $oldValues=null, ?array $newValues=null): void
    {
        AuditLog::create([
            'user_id' => $request->attributes->get('api_user')?->id,
            'request_id' => $request->attributes->get('request_id'),
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'metadata' => $metadata ?: null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(),0,1000),
        ]);
        try {
            NotificationService::fromAudit($request, $action, $subject, $metadata);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
