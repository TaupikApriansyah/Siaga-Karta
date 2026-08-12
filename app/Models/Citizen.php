<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Citizen extends Model
{
    protected $fillable = ['name','nik_encrypted','nik_hash','phone_encrypted','phone_hash','phone_last4'];
    protected $hidden = ['nik_encrypted','nik_hash','phone_encrypted','phone_hash'];

    public static function fingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    public function setNik(string $nik): void
    {
        $this->nik_encrypted = Crypt::encryptString($nik);
        $this->nik_hash = self::fingerprint($nik);
    }

    public function setPhone(string $phone): void
    {
        $this->phone_encrypted = Crypt::encryptString($phone);
        $this->phone_hash = self::fingerprint($phone);
        $this->phone_last4 = substr($phone, -4);
    }

    public function getNik(): string { return Crypt::decryptString($this->nik_encrypted); }
    public function getPhone(): string { return Crypt::decryptString($this->phone_encrypted); }
    public function reports() { return $this->hasMany(Report::class); }
}
