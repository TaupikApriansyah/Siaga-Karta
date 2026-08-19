<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $districts = [
            'Andir' => ['Campaka', 'Ciroyom', 'Dunguscariang', 'Garuda', 'Kebonjeruk', 'Maleber'],
            'Astanaanyar' => ['Cibadak', 'Karanganyar', 'Karasak', 'Nyengseret', 'Panjunan', 'Pelindunghewan'],
            // Add a couple more for testing
            'Babakan Ciparay' => ['Babakan', 'Babakanciparay', 'Cirangrang', 'Margahayu Utara', 'Margasuka', 'Sukahaji'],
        ];

        foreach ($districts as $districtName => $villages) {
            $district = \App\Models\District::create(['name' => $districtName]);
            foreach ($villages as $villageName) {
                $district->villages()->create(['name' => $villageName]);
            }
        }
    }
}
