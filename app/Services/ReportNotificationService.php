<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReportNotificationService
{
    public static function send(Report $report, string $event): void
    {
        if (!$report->reporter_email) return;

        $labels = [
            'created' => 'Aduan Anda telah diterima dan mendapatkan nomor tiket.',
            'kelurahan_verified' => 'Aduan telah diverifikasi oleh tingkat Kelurahan.',
            'kecamatan_validated' => 'Aduan telah divalidasi oleh tingkat Kecamatan.',
            'emergency_escalated' => 'Aduan darurat telah dieskalasi langsung ke tingkat Kota.',
            'kota_coordinated' => 'Aduan sedang dikoordinasikan oleh tingkat Kota ke instansi terkait.',
            'closed' => 'Aduan telah dinyatakan selesai dan ditutup.',
            'rejected' => 'Aduan tidak dapat dilanjutkan. Silakan cek detail status untuk keterangan petugas.',
        ];

        $message = $labels[$event] ?? 'Status aduan Anda telah diperbarui.';
        $stage = strtoupper(str_replace('_', ' ', (string) $report->workflow_stage));
        $priority = strtoupper(str_replace('_', ' ', (string) $report->priority));
        $subject = "SIAGA KARTA - Update {$report->code}";
        $body = "Halo {$report->reporter_name},\n\n{$message}\n\n"
            . "Nomor tiket: {$report->code}\n"
            . "Tahap: {$stage}\n"
            . "Prioritas: {$priority}\n"
            . ($report->opd_target ? "Instansi tujuan: {$report->opd_target}\n" : '')
            . "\nSimpan nomor tiket untuk melacak perkembangan aduan melalui portal SIAGA KARTA.\n\n"
            . "SIAGA KARTA Kota Bandung";

        try {
            Mail::raw($body, function ($mail) use ($report, $subject) {
                $mail->to($report->reporter_email)->subject($subject);
            });
            $report->forceFill(['last_notified_at' => now()])->saveQuietly();
        } catch (\Throwable $e) {
            Log::warning('SIAGA KARTA Gmail notification failed', [
                'report_id' => $report->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
