<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = [
        'code','short_code','name','level','parent_id','rt_count','rw_count',
        'centroid_latitude','centroid_longitude','geojson_name','is_active',
    ];

    protected function casts(): array
    {
        return [
            'rt_count' => 'integer', 'rw_count' => 'integer', 'is_active' => 'boolean',
            'centroid_latitude' => 'float', 'centroid_longitude' => 'float',
        ];
    }

    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }

    public function descendantIds(bool $includeSelf = true): array
    {
        $ids = $includeSelf ? [$this->id] : [];
        $queue = [$this->id];
        while ($queue) {
            $children = self::query()->whereIn('parent_id', $queue)->pluck('id')->all();
            $children = array_values(array_diff($children, $ids));
            if (!$children) break;
            $ids = array_merge($ids, $children);
            $queue = $children;
        }
        return $ids;
    }
    public function users() { return $this->hasMany(User::class); }
    public function reports() { return $this->hasMany(Report::class); }
    public function infaqSetting() { return $this->hasOne(InfaqSetting::class); }

    public function district(): ?self
    {
        if ($this->level === 'kecamatan') return $this;
        if ($this->level === 'kelurahan') return $this->parent;
        return null;
    }
}
