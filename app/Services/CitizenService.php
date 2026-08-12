<?php

namespace App\Services;

use App\Models\Citizen;
use Illuminate\Support\Facades\Crypt;

class CitizenService
{
    public static function upsertIdentity(string $nik, string $name, string $phone): Citizen
    {
        $nikHash=Citizen::fingerprint($nik);
        $citizen=Citizen::firstOrCreate(
            ['nik_hash'=>$nikHash],
            [
                'name'=>trim($name),
                'nik_encrypted'=>Crypt::encryptString($nik),
                'phone_encrypted'=>Crypt::encryptString($phone),
                'phone_hash'=>Citizen::fingerprint($phone),
                'phone_last4'=>substr($phone,-4),
            ]
        );

        if(!$citizen->wasRecentlyCreated) {
            $citizen->name=trim($name);
            $citizen->setPhone($phone);
            $citizen->save();
        }

        return $citizen;
    }
}
