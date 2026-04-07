<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('The password confirmation screen renders for authenticated users')]
    public function test_confirm_password_screen_can_be_rendered(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->get('/confirm-password');

        // Assert
        $response->assertStatus(200);
    }

    #[TestDox('Providing the correct password confirms the session successfully')]
    public function test_password_can_be_confirmed(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->post('/confirm-password', [
            'password' => 'password',
        ]);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    #[TestDox('Providing the wrong password does not confirm the session')]
    public function test_password_is_not_confirmed_with_invalid_password(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->post('/confirm-password', [
            'password' => 'wrong-password',
        ]);

        // Assert
        $response->assertSessionHasErrors();
    }
}
