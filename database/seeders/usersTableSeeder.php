<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class usersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        DB::table('customers')->insert([
            'user_id' => 1,
            'user_unique_id' => Str::uuid(),
            'name' => 'Ronit',
            'username' => 'Ronit',
            'email' => 'ronit@gmail.com',
            'password' => Hash::make('Ronit@123'),
            'mobile' => '1234567890',
            'machine_id' => 'MACH123456',
            'inserted_date' => now()->toDateString(),
            'inserted_time' => now()->toTimeString(),
        ]);
    }
}
