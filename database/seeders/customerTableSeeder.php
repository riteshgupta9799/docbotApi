<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class customerTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
          DB::table('customers')->insert([
            'customer_id' => 1,
            'customer_unique_id' => Str::uuid(),
            'name' => 'jaydip',
            'username' => 'jaydip',
            'email' => 'jaydip@gmail.com',
            'password' => Hash::make('Ronit@123'),
            'mobile' => '1234567890',
            'machine_id' => '1',
            'inserted_date' => now()->toDateString(),
            'inserted_time' => now()->toTimeString(),
        ]);
    }
}
