<?php
namespace App\Services;

use App\Models\SystemRevision;
use Illuminate\Support\Facades\DB;

class RevisionService
{
    public static function bump(string ...$scopes): void
    {
        foreach (array_unique($scopes) as $scope) {
            DB::transaction(function () use ($scope) {
                $row = SystemRevision::query()->where('scope',$scope)->lockForUpdate()->first();
                if (!$row) {
                    SystemRevision::create(['scope'=>$scope,'version'=>1,'updated_at'=>now()]);
                    return;
                }
                $row->forceFill(['version'=>$row->version + 1,'updated_at'=>now()])->save();
            }, 3);
        }
    }

    public static function snapshot(string $role): array
    {
        $wanted = match ($role) {
            'kota' => ['operations','finance','users','settings'],
            'kecamatan', 'kelurahan' => ['operations'],
            default => [],
        };
        $rows = SystemRevision::query()->whereIn('scope',$wanted)->pluck('version','scope');
        $out=[];
        foreach($wanted as $scope) $out[$scope]=(int)($rows[$scope] ?? 0);
        return $out;
    }
}
