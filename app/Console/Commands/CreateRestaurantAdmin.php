<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class CreateRestaurantAdmin extends Command
{
    protected $signature = 'restaurant:create-admin {email} {username} {first_name} {last_name}';

    protected $description = 'Create the first administrator in this restaurant database';

    public function handle(): int
    {
        $email = $this->argument('email');
        $username = $this->argument('username');
        $firstName = trim($this->argument('first_name'));
        $lastName = trim($this->argument('last_name'));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255
            || !preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username)
            || $firstName === '' || strlen($firstName) > 255
            || $lastName === '' || strlen($lastName) > 255) {
            $this->error('Provide a valid email, username, first name, and last name.');

            return self::FAILURE;
        }

        if (User::query()->exists()) {
            $this->error('This command only creates the first user in an empty restaurant database.');

            return self::FAILURE;
        }

        if (!Role::query()->where('name', 'Admin')->exists()) {
            $this->error('Run the RolesAndPermissionsSeeder before creating the administrator.');

            return self::FAILURE;
        }

        $password = $this->secret('Administrator password (12 characters minimum)');
        $confirmation = $this->secret('Confirm administrator password');

        if (!is_string($password) || strlen($password) < 12 || $password !== $confirmation) {
            $this->error('Password is too short or confirmation does not match.');

            return self::FAILURE;
        }

        do {
            $loginCode = random_int(10000000, 99999999);
        } while (User::query()->where('login_code', $loginCode)->exists());

        DB::transaction(function () use ($email, $username, $firstName, $lastName, $password, $loginCode): void {
            $admin = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'username' => $username,
                'password' => $password,
                'login_code' => $loginCode,
            ]);

            $admin->assignRole('Admin');
        });

        $this->info('Restaurant administrator created. Use email/password login.');

        return self::SUCCESS;
    }
}
