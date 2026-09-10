<?php
namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class SimplePdf
{
    public static function make(string $title, array $lines): string
    {
        $html = '<html><body style="font-family: sans-serif;">';
        $html .= '<h2>'.e($title).'</h2>';
        $html .= '<p>Dibuat: '.now()->format('d-m-Y H:i').'</p>';
        $html .= '<hr><table width="100%">';

        foreach ($lines as $line) {
            $html .= '<tr><td style="padding:4px;">'.e((string) $line).'</td></tr>';
        }

        $html .= '</table></body></html>';

        return Pdf::loadHTML($html)->output();
    }
}
