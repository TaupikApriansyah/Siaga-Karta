<?php
namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditService
{
    /**
     * Audit adalah telemetry pendukung, bukan transaksi bisnis utama.
     * Kegagalan audit/notifikasi tidak boleh mengubah create/update yang sebenarnya sukses menjadi HTTP 500.
     */
    public static function log(Request $request, string $action, ?object $subject = null, array $metadata = [], ?array $oldValues=null, ?array $newValues=null): void
    {
        try {
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
        } catch (\Throwable $e) {
            Log::warning('Audit log gagal disimpan; transaksi utama tetap dipertahankan.', [
                'action'=>$action,
                'subject_type'=>$subject ? get_class($subject) : null,
                'subject_id'=>$subject?->id,
                'error'=>$e->getMessage(),
            ]);
            report($e);
            return;
        }

        try {
            NotificationService::fromAudit($request, $action, $subject, $metadata);
        } catch (\Throwable $e) {
            Log::warning('Notifikasi audit gagal dibuat.', ['action'=>$action,'error'=>$e->getMessage()]);
            report($e);
        }
    }
}
