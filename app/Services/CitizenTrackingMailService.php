<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Support\Facades\Mail;

class CitizenTrackingMailService
{
    public static function sendCreated(Report $report): bool
    {
        $report->loadMissing(['citizen', 'region.parent']);
        $email = $report->citizen?->getEmail();
        if (!$email) return false;

        // Driver log/array/null tidak benar-benar mengirim email. Jangan pernah melaporkan
        // notifikasi Gmail berhasil jika aplikasi masih memakai mailer development tersebut.
        if (in_array((string) config('mail.default'), ['log','array','null'], true)) return false;

        $kelurahan = $report->region?->name ?? 'Kelurahan terkait';
        $kecamatan = $report->region?->parent?->name ?? 'Kecamatan terkait';
        $category = ucwords(str_replace('_', ' ', $report->category));
        $url = rtrim((string)config('app.url'), '/').'/#lacak-'.$report->code;
        $body = "Yth. {$report->citizen->name},\n\n"
            ."Pengaduan Anda telah tercatat pada SIAGA KARTA.\n\n"
            ."Kode pelacakan: {$report->code}\n"
            ."Kategori: {$category}\n"
            ."Wilayah: {$kelurahan}, {$kecamatan}\n"
            ."Status awal: Menunggu verifikasi Karang Taruna tingkat Kelurahan\n\n"
            ."Simpan kode pelacakan tersebut. Kode digunakan untuk memeriksa perkembangan pengaduan melalui menu Periksa Status Layanan pada portal SIAGA KARTA.\n"
            ."Portal: {$url}\n\n"
            ."Pesan ini dikirim otomatis. Jangan membagikan kode pelacakan kepada pihak yang tidak berkepentingan.\n\n"
            ."SIAGA KARTA\nKarang Taruna Kota Bandung";

        try {
            Mail::raw($body, function ($message) use ($email, $report) {
                $message->to($email)
                    ->subject('Kode Pelacakan Pengaduan SIAGA KARTA - '.$report->code);
            });
            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }
}
