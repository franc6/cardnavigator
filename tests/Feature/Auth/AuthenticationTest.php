<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('The login screen renders successfully for guests')]
    public function test_login_screen_can_be_rendered(): void
    {
        // Arrange — no setup needed; the login route is public.

        // Act
        $response = $this->get('/login');

        // Assert
        $response->assertStatus(200);
    }

    #[TestDox('A user with valid credentials is authenticated and redirected to the dashboard')]
    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Assert
        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    #[TestDox('A user supplying a wrong password is not authenticated')]
    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        // Assert
        $this->assertGuest();
    }

    #[TestDox('An authenticated user can log out and is redirected to the home page')]
    public function test_users_can_logout(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->post('/logout');

        // Assert
        $this->assertGuest();
        $response->assertRedirect('/');
    }

    #[TestDox('Repeated failed logins throttle the user and dispatch the Lockout event')]
    public function test_login_throttles_after_too_many_failed_attempts(): void
    {
        // Arrange
        Event::fake([Lockout::class]);
        $user = User::factory()->create();
        $credentials = ['email' => $user->email, 'password' => 'wrong-password'];

        // Act — 5 failed attempts populate the rate limiter; the 6th trips the lockout.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', $credentials);
        }
        $response = $this->post('/login', $credentials);

        // Assert
        Event::assertDispatched(Lockout::class);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
