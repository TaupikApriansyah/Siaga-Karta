<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationService
{
    public static function fromAudit(Request $request, string $action, ?object $subject = null, array $metadata = []): void
    {
        if ($subject instanceof Report && str_starts_with($action, 'report.')) {
            self::reportNotification($request, $action, $subject, $metadata);
            return;
        }

        $definition = self::definition($action, $subject, $metadata);
        if (!$definition) return;

        [$roles, $type, $title, $message, $targetMenu] = $definition;
        $actorId = $request->attributes->get('api_user')?->id;
        $recipientIds = User::query()
            ->where('is_active', true)
            ->whereIn('role', $roles)
            ->when($actorId, fn($q) => $q->where('id', '!=', $actorId))
            ->pluck('id');

        self::insertRows($recipientIds->all(), $type, $title, $message, $targetMenu, $subject);
    }

    private static function reportNotification(Request $request, string $action, Report $report, array $metadata): void
    {
        $report->loadMissing('region.parent');
        $actorId = $request->attributes->get('api_user')?->id;
        $kelurahanId = $report->region_id;
        $kecamatanId = $report->region?->parent_id;
        $kelurahan = $report->region?->name ?? 'Kelurahan belum ditetapkan';
        $kecamatan = $report->region?->parent?->name ?? 'Kecamatan belum ditetapkan';
        $code = $report->code;

        $targetRoles = match ($action) {
            'report.public_created', 'report.manual_created' => ['kota','kelurahan'],
            'report.forwarded_to_kecamatan' => ['kota','kecamatan'],
            'report.returned_to_kelurahan', 'report.rejected_by_kecamatan' => ['kota','kelurahan'],
            'report.validated_by_kecamatan' => ['kota','kelurahan'],
            'report.forwarded_to_opd', 'report.assigned', 'report.status_changed', 'report.verified' => ['kota','kecamatan','kelurahan'],
            default => ['kota'],
        };

        $recipientQuery = User::query()->where('is_active', true)->where(function ($q) use ($targetRoles, $kelurahanId, $kecamatanId) {
            if (in_array('kota', $targetRoles, true)) $q->orWhere('role', 'kota');
            if (in_array('kecamatan', $targetRoles, true) && $kecamatanId) {
                $q->orWhere(fn($x) => $x->where('role','kecamatan')->where('region_id',$kecamatanId));
            }
            if (in_array('kelurahan', $targetRoles, true) && $kelurahanId) {
                $q->orWhere(fn($x) => $x->where('role','kelurahan')->where('region_id',$kelurahanId));
            }
        });
        if ($actorId) $recipientQuery->where('id','!=',$actorId);
        $recipientIds = $recipientQuery->pluck('id')->all();
        if (!$recipientIds) return;

        [$title, $message] = match ($action) {
            'report.public_created' => ['Pengaduan warga baru', "{$code} tercatat untuk {$kelurahan}, {$kecamatan}. Pengaduan menunggu verifikasi awal Karang Taruna tingkat Kelurahan sebelum dapat diajukan ke Kecamatan."],
            'report.manual_created' => ['Pengaduan baru dicatat internal', "{$code} dicatat melalui kanal internal untuk {$kelurahan}, {$kecamatan}. Tahap awal mengikuti level akun penginput dan riwayat workflow tersimpan pada detail pengaduan."],
            'report.forwarded_to_kecamatan' => ['Pengaduan menunggu validasi Kecamatan', "{$code} dari {$kelurahan} telah diajukan ke {$kecamatan}. Lakukan validasi dan cross-check data sebelum meneruskannya ke Karang Taruna tingkat Kota."],
            'report.returned_to_kelurahan' => ['Perbaikan data diperlukan', "{$code} dikembalikan oleh {$kecamatan} ke {$kelurahan}. Buka detail pengaduan untuk melihat catatan perbaikan."],
            'report.rejected_by_kecamatan' => ['Pengaduan ditolak Kecamatan', "{$code} tidak lolos validasi {$kecamatan}. Alasan penolakan tercatat pada riwayat pengaduan."],
            'report.validated_by_kecamatan' => ['Pengaduan lolos validasi Kecamatan', "{$code} dari {$kelurahan} telah lolos validasi {$kecamatan} dan sekarang masuk ke Karang Taruna tingkat Kota untuk monitoring serta tindak lanjut."],
            'report.forwarded_to_opd' => ['Pengaduan diteruskan ke OPD', "{$code} diteruskan ke ".($report->assigned_agency ?: ($metadata['agency'] ?? 'OPD/instansi terkait')).". Jejak eskalasi telah diperbarui."],
            'report.assigned' => ['Penugasan operasional dibuat', "{$code} telah mendapat penugasan operasional setelah validasi wilayah."],
            'report.status_changed' => ['Status pengaduan berubah', "{$code} sekarang berstatus ".strtoupper(str_replace('_',' ',(string)($metadata['status'] ?? $report->status)))."."],
            'report.verified' => ['Administrasi pengaduan terverifikasi', "{$code} telah diverifikasi administrasinya oleh Karang Taruna tingkat Kota."],
            default => ['Pembaruan pengaduan', "{$code} memiliki pembaruan baru."],
        };

        self::insertRows($recipientIds, 'operations', $title, $message, 'pelayanan', $report);
    }

    private static function insertRows(array $recipientIds, string $type, string $title, string $message, string $targetMenu, ?object $subject): void
    {
        if (!$recipientIds) return;
        $now = now();
        $rows = array_map(fn($userId) => [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'target_menu' => $targetMenu,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $recipientIds);
        AppNotification::insert($rows);
    }

    private static function definition(string $action, ?object $subject, array $metadata): ?array
    {
        $code = $subject?->code ?? null;

        return match ($action) {
            'infaq.payment_submitted' => [
                ['kota'], 'finance', 'Pembayaran warga masuk',
                ($code ? "{$code} " : '').'menunggu verifikasi pengelola SIAGA KARTA.', 'kas',
            ],
            'transaction.created' => [
                ['kota'], 'finance', 'Transaksi baru',
                ($code ? "{$code} " : '').'menunggu verifikasi.', 'kas',
            ],
            'transaction.verified' => [
                ['kota'], 'finance', 'Transaksi terverifikasi',
                ($code ? "{$code} " : '').'telah masuk ke pencatatan kas terverifikasi.', 'kas',
            ],
            'transaction.rejected' => [
                ['kota'], 'finance', 'Transaksi ditolak',
                ($code ? "{$code} " : '').'ditolak dan tidak memengaruhi saldo.', 'kas',
            ],
            'infaq.settings_updated' => [
                ['kota'], 'finance', 'Pengaturan pembayaran berubah',
                'QR atau rekening pembayaran baru saja diperbarui.', 'kas',
            ],
            'ambulance.created', 'ambulance.updated' => [
                ['kota'], 'operations', 'Data ambulans berubah',
                ($code ? "Unit {$code} " : 'Data unit ').'baru saja diperbarui.', 'ambulans',
            ],
            'user.created', 'user.updated' => [
                ['kota'], 'security', 'Manajemen pengguna berubah',
                'Data akun, role, atau wilayah pengguna baru saja diperbarui.', 'users',
            ],
            'region.local_structure_updated' => [
                ['kota','kecamatan'], 'operations', 'Struktur RT/RW diperbarui',
                'Jumlah RT/RW pada salah satu Kelurahan baru saja disesuaikan oleh pengelola Kelurahan.', 'dashboard',
            ],
            'auth.login_failed' => ($metadata['reason'] ?? null) === 'rate_limited' ? [
                ['kota'], 'security', 'Login dibatasi',
                'Rate limiter memblokir percobaan login berlebih. Periksa audit log bila perlu.', 'dashboard',
            ] : null,
            default => null,
        };
    }
}
