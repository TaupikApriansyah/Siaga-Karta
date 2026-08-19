<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Citizen extends Model
{
    protected $fillable = ['name','nik_encrypted','nik_hash','phone_encrypted','phone_hash','phone_last4','email_encrypted','email_hash'];
    protected $hidden = ['nik_encrypted','nik_hash','phone_encrypted','phone_hash','email_encrypted','email_hash'];

    public static function fingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('siagakarta.data_fingerprint_key', config('app.key')));
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

    public function setEmail(?string $email): void
    {
        $email = strtolower(trim((string)$email));
        $this->email_encrypted = $email === '' ? null : Crypt::encryptString($email);
        $this->email_hash = $email === '' ? null : self::fingerprint($email);
    }

    public function getNik(): string { return Crypt::decryptString($this->nik_encrypted); }
    public function getPhone(): string { return Crypt::decryptString($this->phone_encrypted); }
    public function getEmail(): ?string { return $this->email_encrypted ? Crypt::decryptString($this->email_encrypted) : null; }
    public function reports() { return $this->hasMany(Report::class); }
}
