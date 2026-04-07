<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('The forgot password screen renders successfully')]
    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        // Arrange — no setup needed; the route is public.

        // Act
        $response = $this->get('/forgot-password');

        // Assert
        $response->assertStatus(200);
    }

    #[TestDox('Submitting a valid email address sends a reset link notification')]
    public function test_reset_password_link_can_be_requested(): void
    {
        // Arrange
        Notification::fake();
        $user = User::factory()->create();

        // Act
        $this->post('/forgot-password', ['email' => $user->email]);

        // Assert
        Notification::assertSentTo($user, ResetPassword::class);
    }

    #[TestDox('The reset password screen renders when accessed via a valid token link')]
    public function test_reset_password_screen_can_be_rendered(): void
    {
        // Arrange
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email]);

        // Act & Assert — token is only accessible inside the notification callback.
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/' . $notification->token);

            // Assert
            $response->assertStatus(200);

            return true;
        });
    }

    #[TestDox('A password can be reset using a valid token, email, and matching confirmation')]
    public function test_password_can_be_reset_with_valid_token(): void
    {
        // Arrange
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email]);

        // Act & Assert — token is only accessible inside the notification callback.
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            // Assert
            $response->assertSessionHasNoErrors()->assertRedirect(route('login'));

            return true;
        });
    }

    #[TestDox('Requesting a reset link for an unknown email surfaces a session error instead of status')]
    public function test_reset_password_link_failure_returns_email_error(): void
    {
        // Arrange — no user with this email, so the broker returns INVALID_USER, not RESET_LINK_SENT.
        Notification::fake();

        // Act
        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'no-such-user@example.com',
        ]);

        // Assert
        $response->assertRedirect('/forgot-password');
        $response->assertSessionHasErrors('email');
        $response->assertSessionMissing('status');
        Notification::assertNothingSent();
    }

    #[TestDox('Resetting with an invalid token re-renders the form with an email error')]
    public function test_password_reset_with_invalid_token_returns_email_error(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->from('/reset-password/bad-token')->post('/reset-password', [
            'token' => 'this-token-was-never-issued',
            'email' => $user->email,
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        // Assert
        $response->assertSessionHasErrors('email');
        $response->assertSessionMissing('status');
    }
}
