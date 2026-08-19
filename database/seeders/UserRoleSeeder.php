<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = \Illuminate\Support\Facades\Hash::make('password');

        \App\Models\User::updateOrCreate(
            ['email' => 'admin@siagakarta.local'],
            [
                'name' => 'Administrator',
                'role' => 'admin',
                'password' => $password,
            ]
        );

        \App\Models\User::updateOrCreate(
            ['email' => 'kota@siagakarta.local'],
            [
                'name' => 'Operator Kota',
                'role' => 'kota',
                'password' => $password,
            ]
        );

        $andir = \App\Models\District::where('name', 'Andir')->first();
        if ($andir) {
            \App\Models\User::updateOrCreate(
                ['email' => 'andir@siagakarta.local'],
                [
                    'name' => 'Operator Kecamatan Andir',
                    'role' => 'kecamatan',
                    'district_id' => $andir->id,
                    'password' => $password,
                ]
            );
        }

        $ciroyom = \App\Models\Village::where('name', 'Ciroyom')->first();
        if ($ciroyom) {
            \App\Models\User::updateOrCreate(
                ['email' => 'ciroyom@siagakarta.local'],
                [
                    'name' => 'Operator Kelurahan Ciroyom',
                    'role' => 'kelurahan',
                    'village_id' => $ciroyom->id,
                    'password' => $password,
                ]
            );
        }
    }
}
