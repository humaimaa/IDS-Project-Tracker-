<?php

namespace Database\Seeders;

use App\Models\District;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reference: https://kpese.gov.pk/district-education-offices/ (36-district directory).
        $names = ['Abbottabad', 'Bajaur', 'Bannu', 'Battagram', 'Buner', 'Charsadda', 'Dera Ismail Khan', 'Hangu', 'Haripur', 'Karak', 'Khyber', 'Kohat', 'Kolai-Palas', 'Kurram', 'Lakki Marwat', 'Lower Chitral', 'Lower Dir', 'Lower Kohistan', 'Lower South Waziristan', 'Malakand', 'Mansehra', 'Mardan', 'Mohmand', 'North Waziristan', 'Nowshera', 'Orakzai', 'Peshawar', 'Shangla', 'Swabi', 'Swat', 'Tank', 'Torghar', 'Upper Chitral', 'Upper Dir', 'Upper Kohistan', 'Upper South Waziristan'];
        foreach ($names as $name) {
            District::firstOrCreate(['name' => $name], ['code' => 'KP-'.strtoupper(Str::slug($name)), 'active' => true]);
        }
    }
}
