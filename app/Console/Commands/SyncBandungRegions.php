<?php

namespace App\Console\Commands;

use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncBandungRegions extends Command
{
    protected $signature = 'siagakarta:sync-bandung-regions {--force : Tetap jalankan walau data wilayah sudah lengkap}';
    protected $description = 'Sinkronkan master 30 Kecamatan dan Kelurahan Kota Bandung dari API wilayah administratif.';

    public function handle(): int
    {
        $base = rtrim((string) config('siagakarta.map.region_api_base_url'), '/');
        if ($base === '') {
            $this->error('BANDUNG_REGION_API_BASE_URL belum dikonfigurasi.');
            return self::FAILURE;
        }

        $city = Region::query()->firstOrCreate(
            ['code' => '32.73'],
            [
                'short_code' => 'BANDUNG', 'name' => 'Kota Bandung', 'level' => 'kota', 'parent_id' => null,
                'centroid_latitude' => -6.9175, 'centroid_longitude' => 107.6191, 'geojson_name' => 'KOTA BANDUNG', 'is_active' => true,
            ]
        );

        if (!$this->option('force') && Region::where('level', 'kecamatan')->count() >= 30 && Region::where('level', 'kelurahan')->count() >= 151) {
            $this->info('Master wilayah Kota Bandung sudah lengkap; sinkronisasi tidak diperlukan. Gunakan --force untuk menyegarkan data.');
            return self::SUCCESS;
        }

        try {
            $districtResponse = Http::acceptJson()->timeout(25)->retry(2, 600)->get($base.'/districts/32.73.json');
            $districtResponse->throw();
            $districts = $this->extractRows($districtResponse->json());
        } catch (\Throwable $e) {
            $this->error('Gagal mengambil daftar Kecamatan Kota Bandung: '.$e->getMessage());
            return self::FAILURE;
        }

        if (count($districts) < 1) {
            $this->error('API wilayah tidak mengembalikan daftar Kecamatan Kota Bandung.');
            return self::FAILURE;
        }

        $districtCount = 0;
        $villageCount = 0;

        foreach ($districts as $districtRow) {
            $districtCode = $this->codeOf($districtRow);
            $districtRawName = $this->nameOf($districtRow);
            if (!$districtCode || !$districtRawName) continue;

            $district = DB::transaction(function () use ($city, $districtCode, $districtRawName) {
                return Region::query()->updateOrCreate(
                    ['code' => $districtCode],
                    [
                        'short_code' => $this->shortCode($districtRawName),
                        'name' => 'Kecamatan '.$this->displayName($districtRawName),
                        'level' => 'kecamatan', 'parent_id' => $city->id,
                        'geojson_name' => Str::upper($districtRawName), 'is_active' => true,
                    ]
                );
            });
            $districtCount++;

            try {
                $villageResponse = Http::acceptJson()->timeout(25)->retry(2, 600)->get($base.'/villages/'.$districtCode.'.json');
                $villageResponse->throw();
                $villages = $this->extractRows($villageResponse->json());
            } catch (\Throwable $e) {
                $this->warn("Kelurahan untuk {$districtRawName} gagal diambil: {$e->getMessage()}");
                continue;
            }

            DB::transaction(function () use ($villages, $district, &$villageCount) {
                foreach ($villages as $villageRow) {
                    $code = $this->codeOf($villageRow);
                    $rawName = $this->nameOf($villageRow);
                    if (!$code || !$rawName) continue;

                    Region::query()->updateOrCreate(
                        ['code' => $code],
                        [
                            'short_code' => $this->shortCode($rawName),
                            'name' => 'Kelurahan '.$this->displayName($rawName),
                            'level' => 'kelurahan', 'parent_id' => $district->id,
                            // Nilai awal operasional sesuai kebutuhan SIAGA KARTA; setiap akun Kelurahan dapat menyesuaikannya kemudian.
                            'rt_count' => 11, 'rw_count' => 11,
                            'geojson_name' => Str::upper($rawName), 'is_active' => true,
                        ]
                    );
                    $villageCount++;
                }
            });
        }

        // Pastikan penamaan pilot tetap konsisten dengan UI sekaligus mempertahankan kode resmi.
        if ($dungus = Region::where('code', '32.73.05.1002')->first()) {
            $dungus->update([
                'short_code' => 'DUNGUSCARIANG', 'name' => 'Kelurahan Dungus Cariang',
                'geojson_name' => 'DUNGUS CARIANG', 'rt_count' => $dungus->rt_count ?: 11, 'rw_count' => $dungus->rw_count ?: 11,
            ]);
        }

        $this->info("Sinkronisasi selesai: {$districtCount} Kecamatan dan {$villageCount} Kelurahan diproses. Tidak ada akun pengguna baru yang dibuat.");
        return self::SUCCESS;
    }

    private function extractRows(mixed $payload): array
    {
        if (!is_array($payload)) return [];
        if (isset($payload['data']) && is_array($payload['data'])) return array_values($payload['data']);
        if (array_is_list($payload)) return $payload;
        return [];
    }

    private function codeOf(array $row): ?string
    {
        $value = $row['code'] ?? $row['id'] ?? null;
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    private function nameOf(array $row): ?string
    {
        $value = $row['name'] ?? $row['nama'] ?? null;
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    private function displayName(string $name): string
    {
        return Str::title(Str::lower(preg_replace('/\s+/', ' ', trim($name))));
    }

    private function shortCode(string $name): string
    {
        $clean = Str::upper(preg_replace('/[^A-Za-z0-9]+/', '', Str::ascii($name)));
        return Str::limit($clean ?: 'WILAYAH', 40, '');
    }
}
