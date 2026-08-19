<?php

namespace App\Services;

use App\Models\Region;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ReportAccessService
{
    public static function scope(Builder $query, User $user): Builder
    {
        if ($user->role === 'kota') return $query;

        if (!$user->region_id) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->role === 'kelurahan') {
            return $query->where('region_id', $user->region_id);
        }

        if ($user->role === 'kecamatan') {
            // Subquery menjaga filtering di database. Tidak perlu memuat seluruh ID kelurahan ke memori PHP.
            return $query->whereIn('region_id', Region::query()
                ->select('id')
                ->where('level', 'kelurahan')
                ->where('parent_id', $user->region_id));
        }

        return $query->whereRaw('1 = 0');
    }

    public static function canAccess(User $user, Report $report): bool
    {
        if ($user->role === 'kota') return true;
        if (!$user->region_id || !$report->region_id) return false;
        if ($user->role === 'kelurahan') return (int)$report->region_id === (int)$user->region_id;
        if ($user->role === 'kecamatan') {
            return Region::query()->whereKey($report->region_id)->where('parent_id', $user->region_id)->exists();
        }
        return false;
    }

    public static function allowedKelurahan(User $user): Builder
    {
        $query = Region::query()->where('level', 'kelurahan')->where('is_active', true);
        if ($user->role === 'kota') return $query;
        if ($user->role === 'kelurahan') return $query->whereKey($user->region_id);
        if ($user->role === 'kecamatan') return $query->where('parent_id', $user->region_id);
        return $query->whereRaw('1 = 0');
    }
}
