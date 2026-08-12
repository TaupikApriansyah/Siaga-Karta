<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationService
{
    public static function fromAudit(Request $request, string $action, ?object $subject = null, array $metadata = []): void
    {
        $definition = self::definition($action, $subject, $metadata);
        if (!$definition) return;

        [$roles, $type, $title, $message, $targetMenu] = $definition;
        $actorId = $request->attributes->get('api_user')?->id;
        $recipientIds = User::query()
            ->where('is_active', true)
            ->whereIn('role', $roles)
            ->when($actorId, fn($q) => $q->where('id', '!=', $actorId))
            ->pluck('id');

        if ($recipientIds->isEmpty()) return;
        $now = now();
        $rows = $recipientIds->map(fn($userId) => [
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
        ])->all();

        AppNotification::insert($rows);
    }

    private static function definition(string $action, ?object $subject, array $metadata): ?array
    {
        $code = $subject?->code ?? null;

        return match ($action) {
            'report.public_created' => [
                ['admin','petugas'], 'operations', 'Laporan warga baru',
                ($code ? "{$code} " : '').'menunggu verifikasi dan tindak lanjut.', 'pelayanan',
            ],
            'report.manual_created' => [
                ['admin','petugas'], 'operations', 'Laporan manual baru',
                ($code ? "{$code} " : '').'ditambahkan oleh petugas.', 'pelayanan',
            ],
            'report.assigned' => [
                ['admin','petugas'], 'operations', 'Pelayanan ditugaskan',
                ($code ? "{$code} " : '').'sudah mendapat penugasan.', 'pelayanan',
            ],
            'report.status_changed' => [
                ['admin','petugas'], 'operations', 'Status pelayanan berubah',
                ($code ? "{$code}: " : '').'status menjadi '.($metadata['status'] ?? 'terbaru').'.', 'pelayanan',
            ],
            'infaq.payment_submitted' => [
                ['admin','karta'], 'finance', 'Pembayaran warga masuk',
                ($code ? "{$code} " : '').'menunggu verifikasi Karta/Admin.', 'kas',
            ],
            'transaction.created' => [
                ['admin','karta'], 'finance', 'Transaksi baru',
                ($code ? "{$code} " : '').'menunggu verifikasi.', 'kas',
            ],
            'transaction.verified' => [
                ['admin','karta'], 'finance', 'Transaksi terverifikasi',
                ($code ? "{$code} " : '').'sudah masuk ke ledger terverifikasi.', 'kas',
            ],
            'transaction.rejected' => [
                ['admin','karta'], 'finance', 'Transaksi ditolak',
                ($code ? "{$code} " : '').'ditolak dan tidak memengaruhi saldo.', 'kas',
            ],
            'infaq.settings_updated' => [
                ['admin','karta'], 'finance', 'Pengaturan pembayaran berubah',
                'QR atau rekening pembayaran baru saja diperbarui.', 'kas',
            ],
            'ambulance.created', 'ambulance.updated' => [
                ['admin','petugas'], 'operations', 'Data ambulans berubah',
                ($code ? "Unit {$code} " : 'Data unit ').'baru saja diperbarui.', 'ambulans',
            ],
            'user.created', 'user.updated' => [
                ['admin'], 'security', 'Manajemen user berubah',
                'Data akun atau hak akses user baru saja diperbarui.', 'users',
            ],
            'auth.login_failed' => ($metadata['reason'] ?? null) === 'rate_limited' ? [
                ['admin'], 'security', 'Login dibatasi',
                'Rate limiter memblokir percobaan login berlebih. Periksa audit log bila perlu.', 'dashboard',
            ] : null,
            default => null,
        };
    }
}
