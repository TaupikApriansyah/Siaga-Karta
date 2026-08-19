<?php
namespace App\Services;

use App\Models\SystemRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RevisionService
{
    /**
     * Revision dipakai untuk sinkronisasi UI. Jika counter gagal, data bisnis yang baru disimpan
     * tetap valid dan pengguna tidak boleh mendapat false-negative "Server Error".
     */
    public static function bump(string ...$scopes): void
    {
        foreach (array_unique($scopes) as $scope) {
            try {
                DB::transaction(function () use ($scope) {
                    $row = SystemRevision::query()->where('scope',$scope)->lockForUpdate()->first();
                    if (!$row) {
                        SystemRevision::create(['scope'=>$scope,'version'=>1,'updated_at'=>now()]);
                        return;
                    }
                    $row->forceFill(['version'=>(int)$row->version + 1,'updated_at'=>now()])->save();
                }, 3);
            } catch (\Throwable $e) {
                Log::warning('Counter sinkronisasi gagal dinaikkan; transaksi utama tetap dipertahankan.', [
                    'scope'=>$scope,'error'=>$e->getMessage(),
                ]);
                report($e);
            }
        }
    }

    public static function snapshot(string $role): array
    {
        $wanted = match ($role) {
            'kota' => ['operations','finance','users','settings'],
            'kecamatan', 'kelurahan' => ['operations'],
            default => [],
        };
        if (!$wanted) return [];

        try {
            $rows = SystemRevision::query()->whereIn('scope',$wanted)->pluck('version','scope');
        } catch (\Throwable $e) {
            Log::warning('Snapshot sinkronisasi gagal dibaca.', ['role'=>$role,'error'=>$e->getMessage()]);
            report($e);
            $rows=collect();
        }
        $out=[];
        foreach($wanted as $scope) $out[$scope]=(int)($rows[$scope] ?? 0);
        return $out;
    }
}
