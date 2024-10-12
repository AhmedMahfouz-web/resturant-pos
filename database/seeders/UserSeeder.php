<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $usedCodes = [];
        User::create([
            'first_name' => 'Admin',
            'last_name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@admin.com',
            'password' => 'password',
            'login_code' => 1234,
        ]);
        for ($i = 0; $i < 10; $i++) {
            // Generate unique 4-digit code
            do {
                $loginCode = rand(1000, 9999);
            } while (in_array($loginCode, $usedCodes));

            $usedCodes[] = $loginCode;

            User::create([
                'first_name' => $faker->name,
                'last_name' => $faker->name,
                'username' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'password' => 'password',
                'login_code' => $loginCode,
            ]);
        }
    }
}
