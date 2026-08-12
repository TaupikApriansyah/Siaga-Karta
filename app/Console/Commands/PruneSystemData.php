<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use App\Models\AppNotification;
use Illuminate\Console\Command;

class PruneSystemData extends Command
{
    protected $signature = 'siagakarta:prune';
    protected $description = 'Hapus token kedaluwarsa dan notifikasi lama agar tabel operasional tetap ringan.';

    public function handle(): int
    {
        $expiredTokens = ApiToken::query()
            ->where(function ($q) {
                $q->where('expires_at', '<', now()->subDay())
                  ->orWhere('absolute_expires_at', '<', now()->subDay());
            })->delete();

        $readDays = max(7, (int) config('siagakarta.retention.read_notifications_days', 90));
        $unreadDays = max($readDays, (int) config('siagakarta.retention.unread_notifications_days', 180));

        $readNotifications = AppNotification::query()
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays($readDays))
            ->delete();
        $unreadNotifications = AppNotification::query()
            ->whereNull('read_at')
            ->where('created_at', '<', now()->subDays($unreadDays))
            ->delete();

        $this->info("Prune selesai: {$expiredTokens} token, {$readNotifications} notifikasi terbaca, {$unreadNotifications} notifikasi belum terbaca lama.");
        return self::SUCCESS;
    }
}
