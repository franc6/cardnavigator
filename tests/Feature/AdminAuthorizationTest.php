<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Non-admin access
    // -------------------------------------------------------------------------

    #[TestDox('Guests are redirected away from admin pages')]
    public function test_guest_cannot_access_admin_pages(): void
    {
        // Arrange — no authenticated user.

        // Act & Assert
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
        $this->get(route('admin.database.index'))->assertRedirect(route('login'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function adminRouteProvider(): array
    {
        return [
            'users index' => ['/admin/users'],
            'database index' => ['/admin/database'],
        ];
    }

    #[DataProvider('adminRouteProvider')]
    #[TestDox('A non-admin authenticated user is forbidden from admin routes')]
    public function test_non_admin_user_receives_403(string $path): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->get($path);

        // Assert
        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Admin access
    // -------------------------------------------------------------------------

    #[DataProvider('adminRouteProvider')]
    #[TestDox('An admin user can access admin routes')]
    public function test_admin_user_can_access_admin_routes(string $path): void
    {
        // Arrange
        $user = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($user)->get($path);

        // Assert
        $response->assertOk();
    }

    // -------------------------------------------------------------------------
    // user:create command
    // -------------------------------------------------------------------------

    #[TestDox('user:create creates a regular user')]
    public function test_user_create_command_creates_regular_user(): void
    {
        // Arrange & Act
        $this->artisan('user:create')
            ->expectsQuestion(__('Name'), 'Jane Doe')
            ->expectsQuestion(__('Email'), 'jane@example.com')
            ->expectsQuestion(__('Password'), 'secret123')
            ->expectsQuestion(__('Confirm password'), 'secret123')
            ->assertSuccessful();

        // Assert
        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'is_admin' => false,
        ]);
    }

    #[TestDox('user:create --admin creates an admin user')]
    public function test_user_create_command_with_admin_flag_grants_admin(): void
    {
        // Arrange & Act
        $this->artisan('user:create --admin')
            ->expectsQuestion(__('Name'), 'Admin User')
            ->expectsQuestion(__('Email'), 'admin@example.com')
            ->expectsQuestion(__('Password'), 'secret123')
            ->expectsQuestion(__('Confirm password'), 'secret123')
            ->assertSuccessful();

        // Assert
        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'is_admin' => true,
        ]);
    }

    #[TestDox('user:create fails when passwords do not match')]
    public function test_user_create_command_fails_on_mismatched_passwords(): void
    {
        // Arrange & Act
        $this->artisan('user:create')
            ->expectsQuestion(__('Name'), 'Jane Doe')
            ->expectsQuestion(__('Email'), 'jane@example.com')
            ->expectsQuestion(__('Password'), 'secret123')
            ->expectsQuestion(__('Confirm password'), 'different')
            ->assertFailed();

        // Assert
        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
    }

    #[TestDox('user:create fails when email is already taken')]
    public function test_user_create_command_fails_on_duplicate_email(): void
    {
        // Arrange
        User::factory()->create(['email' => 'taken@example.com']);

        // Act
        $this->artisan('user:create')
            ->expectsQuestion(__('Name'), 'Jane Doe')
            ->expectsQuestion(__('Email'), 'taken@example.com')
            ->assertFailed();

        // Assert
        $this->assertDatabaseCount('users', 1);
    }
}
