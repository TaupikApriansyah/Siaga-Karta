<?php

namespace App\Traits;

use App\Models\Region;

trait RegionScope
{
    protected function allowedRegionIds($user): array
    {
        if (!$user?->region_id) {
            return [];
        }

        $regionId = (int) $user->region_id;

        return Region::query()
            ->whereKey($regionId)
            ->where('is_active', true)
            ->exists()
                ? [$regionId]
                : [];
    }
}
