<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServicesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        \App\Models\Service::create([
            'id' => 1,
            'name' => 'General Consultation',
            'price' => 100.00,
            'description' => 'Standard doctor consultation'
        ]);

        // Add more services as needed
    }
}
