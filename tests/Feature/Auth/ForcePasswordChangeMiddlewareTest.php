<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class ForcePasswordChangeMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('A user with force_password_change=true is redirected to the password change route on any other page')]
    public function test_flagged_user_is_redirected_on_other_routes(): void
    {
        // Arrange
        $user = User::factory()->create(['force_password_change' => true]);

        // Act
        $response = $this->actingAs($user)->get(route('dashboard'));

        // Assert
        $response->assertRedirect(route('password.change'));
    }

    #[TestDox('A flagged user is not redirected when accessing the password.change route itself')]
    public function test_flagged_user_can_reach_password_change(): void
    {
        // Arrange
        $user = User::factory()->create(['force_password_change' => true]);

        // Act
        $response = $this->actingAs($user)->get(route('password.change'));

        // Assert
        $response->assertOk();
    }

    #[TestDox('A flagged user is not redirected away from the logout route')]
    public function test_flagged_user_can_logout(): void
    {
        // Arrange
        $user = User::factory()->create(['force_password_change' => true]);

        // Act
        $response = $this->actingAs($user)->post(route('logout'));

        // Assert
        $response->assertRedirect('/');
        $this->assertGuest();
    }

    #[TestDox('A user without the flag passes through to the requested route')]
    public function test_unflagged_user_passes_through(): void
    {
        // Arrange
        $user = User::factory()->create(['force_password_change' => false]);

        // Act
        $response = $this->actingAs($user)->get(route('dashboard'));

        // Assert
        $response->assertOk();
    }

    #[TestDox('Submitting a valid new password clears the flag, updates the hash, and redirects to the dashboard')]
    public function test_update_password_clears_flag_and_redirects(): void
    {
        // Arrange
        $user = User::factory()->create(['force_password_change' => true]);

        // Act
        $response = $this->actingAs($user)->put(route('password.change.update'), [
            'password' => 'brand-new-pw',
            'password_confirmation' => 'brand-new-pw',
        ]);

        // Assert
        $response->assertRedirect(route('dashboard'));
        $user->refresh();
        $this->assertFalse((bool) $user->force_password_change);
        $this->assertTrue(Hash::check('brand-new-pw', $user->password));
    }

    public static function invalidForcedPasswordProvider(): array
    {
        return [
            'missing password' => [['password_confirmation' => 'whatever']],
            'too short' => [['password' => 'short', 'password_confirmation' => 'short']],
            'unconfirmed' => [['password' => 'long-enough', 'password_confirmation' => 'different']],
        ];
    }

    #[DataProvider('invalidForcedPasswordProvider')]
    #[TestDox('Submitting an invalid password keeps the flag set and returns validation errors')]
    public function test_update_password_validation(array $payload): void
    {
        // Arrange
        $user = User::factory()->create(['force_password_change' => true]);

        // Act
        $response = $this->actingAs($user)
            ->from(route('password.change'))
            ->put(route('password.change.update'), $payload);

        // Assert
        $response->assertSessionHasErrors('password');
        $this->assertTrue((bool) $user->fresh()->force_password_change);
    }
}
