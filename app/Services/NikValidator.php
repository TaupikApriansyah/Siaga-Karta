<?php
namespace App\Services;
use DateTimeImmutable;
class NikValidator
{
    public static function isValid(string $nik): bool
    {
        if (!preg_match('/^\d{16}$/', $nik)) return false;
        if (preg_match('/^(\d)\1{15}$/', $nik)) return false;
        if (substr($nik,0,6) === '000000') return false;
        $dd=(int)substr($nik,6,2); $mm=(int)substr($nik,8,2); $yy=(int)substr($nik,10,2);
        if ($dd > 40) $dd -= 40;
        if ($dd < 1 || $dd > 31 || $mm < 1 || $mm > 12) return false;
        $now=(int)date('Y'); $century = $yy <= ((int)date('y')) ? 2000 : 1900;
        $year=$century+$yy;
        return checkdate($mm,$dd,$year) && $year <= $now;
    }
}
