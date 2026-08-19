<?php

namespace App\Services;

use App\Models\Citizen;
use Illuminate\Support\Facades\Crypt;

class CitizenService
{
    public static function upsertIdentity(string $nik, string $name, string $phone, ?string $email = null): Citizen
    {
        $nikHash=Citizen::fingerprint($nik);
        $normalizedEmail=strtolower(trim((string)$email));
        $citizen=Citizen::firstOrCreate(
            ['nik_hash'=>$nikHash],
            [
                'name'=>trim($name),
                'nik_encrypted'=>Crypt::encryptString($nik),
                'phone_encrypted'=>Crypt::encryptString($phone),
                'phone_hash'=>Citizen::fingerprint($phone),
                'phone_last4'=>substr($phone,-4),
                'email_encrypted'=>$normalizedEmail===''?null:Crypt::encryptString($normalizedEmail),
                'email_hash'=>$normalizedEmail===''?null:Citizen::fingerprint($normalizedEmail),
            ]
        );

        if(!$citizen->wasRecentlyCreated) {
            $citizen->name=trim($name);
            $citizen->setPhone($phone);
            if($normalizedEmail!=='') $citizen->setEmail($normalizedEmail);
            $citizen->save();
        }

        return $citizen;
    }
}
