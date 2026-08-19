<?php

namespace App\Services;

use App\Models\Region;
use App\Models\Report;

class TrackingCodeService
{
    public static function nextForKelurahan(Region $kelurahan): string
    {
        $district = $kelurahan->parent ?: Region::find($kelurahan->parent_id);
        if (!$district || $district->level !== 'kecamatan') {
            throw new \RuntimeException('Kelurahan belum terhubung ke Kecamatan yang valid.');
        }

        // Serialize nomor urut per Kecamatan agar dua pengaduan simultan tidak mendapat kode yang sama.
        // Method ini dipanggil di dalam transaksi pembuatan laporan.
        $district = Region::query()->whereKey($district->id)->lockForUpdate()->firstOrFail();

        $districtCode = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $district->short_code ?: $district->name));
        $year = now()->format('Y');
        $prefix = "SKB-{$districtCode}-{$year}-";

        $latest = Report::query()
            ->where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->lockForUpdate()
            ->value('code');

        $sequence = 1;
        if ($latest && preg_match('/-(\d{5})$/', $latest, $match)) {
            $sequence = ((int)$match[1]) + 1;
        }

        // Unique index pada reports.code tetap menjadi lapisan proteksi terakhir.
        return $prefix.str_pad((string)$sequence, 5, '0', STR_PAD_LEFT);
    }
}
