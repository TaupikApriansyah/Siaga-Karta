<?php
namespace App\Services;

use App\Models\Ambulance;
use App\Models\Driver;
use App\Models\Report;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ScheduleService
{
    public const DEFAULT_DURATION_MINUTES = 120;

    public static function intervalFor(Report $report): array
    {
        $start = $report->service_start_at ?? $report->scheduled_at ?? now();
        $end = $report->service_end_at ?? $start->copy()->addMinutes(self::DEFAULT_DURATION_MINUTES);
        return [$start, $end];
    }

    public static function reportConflictQuery(Carbon $start, Carbon $end, ?int $excludeReportId = null): Builder
    {
        return Report::query()
            ->whereNotIn('status', ['selesai', 'ditolak'])
            ->when($excludeReportId, fn (Builder $q) => $q->where('id','!=',$excludeReportId))
            ->where(function (Builder $q) use ($start, $end) {
                $q->where(function (Builder $q2) use ($start, $end) {
                    $q2->whereNotNull('service_start_at')
                        ->whereNotNull('service_end_at')
                        ->where('service_start_at', '<', $end)
                        ->where('service_end_at', '>', $start);
                })->orWhere(function (Builder $q2) {
                    // Compatibility for active records created before interval fields existed.
                    $q2->whereNull('service_start_at')->whereIn('status', ['diproses', 'dijemput']);
                });
            });
    }

    public static function ambulanceHasConflict(int $ambulanceId, Carbon $start, Carbon $end, ?int $excludeReportId = null): bool
    {
        return self::reportConflictQuery($start, $end, $excludeReportId)->where('ambulance_id', $ambulanceId)->exists();
    }

    public static function driverHasConflict(int $driverId, Carbon $start, Carbon $end, ?int $excludeReportId = null): bool
    {
        return self::reportConflictQuery($start, $end, $excludeReportId)->where('driver_id', $driverId)->exists();
    }

    public static function isCurrentWindow(Carbon $start, Carbon $end): bool
    {
        $now = now();
        return $start->lte($now->copy()->addMinutes(5)) && $end->gt($now);
    }

    public static function syncAmbulanceStatus(Ambulance $ambulance): void
    {
        if ($ambulance->status === 'maintenance') return;
        $busy = Report::where('ambulance_id', $ambulance->id)
            ->whereNotIn('status', ['selesai','ditolak'])
            ->whereIn('status', ['diproses','dijemput'])
            ->where(function ($q) {
                $q->where(function ($x) {
                    $x->whereNotNull('service_start_at')->whereNotNull('service_end_at')
                      ->where('service_start_at', '<=', now())->where('service_end_at', '>', now());
                })->orWhereNull('service_start_at');
            })->exists();
        $ambulance->update(['status'=>$busy?'bertugas':'tersedia']);
    }

    public static function syncDriverStatus(Driver $driver): void
    {
        if ($driver->status === 'nonaktif') return;
        $busy = Report::where('driver_id', $driver->id)
            ->whereNotIn('status', ['selesai','ditolak'])
            ->whereIn('status', ['diproses','dijemput'])
            ->where(function ($q) {
                $q->where(function ($x) {
                    $x->whereNotNull('service_start_at')->whereNotNull('service_end_at')
                      ->where('service_start_at', '<=', now())->where('service_end_at', '>', now());
                })->orWhereNull('service_start_at');
            })->exists();
        $driver->update(['status'=>$busy?'bertugas':'aktif']);
    }
}
