<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

#[Signature('admin:create {--name= : Full name of the administrator} {--email= : Email address of the administrator} {--password= : Password of the administrator}')]
#[Description('Create or promote an administrator user')]
class CreateAdminUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== Create Admin User ===');

        // 1. Get or ask for name
        $name = $this->option('name');
        while (empty($name)) {
            $name = $this->ask('Administrator full name');
            if (empty(trim((string) $name))) {
                $this->error('Name cannot be empty.');
                $name = null;
            }
        }

        // 2. Get or ask for email
        $email = $this->option('email');
        while (empty($email)) {
            $email = $this->ask('Administrator email address');
            $validator = Validator::make(['email' => $email], [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                $this->error($validator->errors()->first('email'));
                $email = null;
            }
        }

        // 3. Check if user already exists
        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            $this->warn("A user with email '{$email}' already exists (Current Role: {$existingUser->role}).");
            if (! $this->confirm('Do you want to promote this user to Administrator and optionally reset password?', true)) {
                $this->info('Operation cancelled.');

                return self::FAILURE;
            }

            $password = $this->option('password');
            if (empty($password)) {
                if ($this->confirm('Do you want to reset their password?', false)) {
                    $password = $this->secret('New password (min: 8 characters)');
                }
            }

            $data = ['role' => User::ROLE_ADMIN];
            if (! empty($name) && $name !== $existingUser->name) {
                $data['name'] = $name;
            }
            if (! empty($password)) {
                if (strlen($password) < 8) {
                    $this->error('Password must be at least 8 characters long.');

                    return self::FAILURE;
                }
                $data['password'] = Hash::make($password);
            }

            $existingUser->update($data);

            $this->info("User '{$existingUser->name}' ({$email}) has been successfully updated to Administrator!");

            return self::SUCCESS;
        }

        // 4. Get or ask for password
        $password = $this->option('password');
        while (empty($password) || strlen($password) < 8) {
            $password = $this->secret('Administrator password (min: 8 characters)');
            if (strlen((string) $password) < 8) {
                $this->error('Password must be at least 8 characters long.');
                $password = null;
            }
        }

        // 5. Create new admin user
        $admin = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_ADMIN,
        ]);

        $this->newLine();
        $this->table(
            ['ID', 'Name', 'Email', 'Role', 'Created At'],
            [
                [$admin->id, $admin->name, $admin->email, $admin->role, $admin->created_at->toDateTimeString()],
            ]
        );

        $this->info("Administrator '{$admin->name}' created successfully!");

        return self::SUCCESS;
    }
}
