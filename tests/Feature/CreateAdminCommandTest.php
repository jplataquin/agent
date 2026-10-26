<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_create_command_with_options()
    {
        $this->artisan('admin:create', [
            '--name' => 'Alice Admin',
            '--email' => 'alice@company.com',
            '--password' => 'secret_password_123',
        ])
            ->expectsOutputToContain("Administrator 'Alice Admin' created successfully!")
            ->assertExitCode(0);

        $admin = User::where('email', 'alice@company.com')->first();
        $this->assertNotNull($admin);
        $this->assertEquals('Alice Admin', $admin->name);
        $this->assertEquals('admin', $admin->role);
        $this->assertTrue(Hash::check('secret_password_123', $admin->password));
    }

    public function test_admin_create_promotes_existing_user()
    {
        $regularUser = User::create([
            'name' => 'Bob Regular',
            'email' => 'bob@company.com',
            'password' => Hash::make('old_password'),
            'role' => 'regular',
        ]);

        $this->artisan('admin:create', [
            '--name' => 'Bob Regular',
            '--email' => 'bob@company.com',
            '--password' => 'new_secret_password',
        ])
            ->expectsConfirmation('Do you want to promote this user to Administrator and optionally reset password?', 'yes')
            ->expectsOutputToContain("User 'Bob Regular' (bob@company.com) has been successfully updated to Administrator!")
            ->assertExitCode(0);

        $regularUser->refresh();
        $this->assertEquals('admin', $regularUser->role);
        $this->assertTrue(Hash::check('new_secret_password', $regularUser->password));
    }
}
