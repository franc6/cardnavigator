<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class EmailVerificationNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('An unverified user posting to verification.send receives a verification notification')]
    public function test_unverified_user_receives_verification_notification(): void
    {
        // Arrange
        Notification::fake();
        $user = User::factory()->unverified()->create();

        // Act
        $response = $this->actingAs($user)->post(route('verification.send'));

        // Assert
        $response->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    #[TestDox('An already-verified user posting to verification.send is redirected to the dashboard without a notification')]
    public function test_already_verified_user_is_redirected_without_notification(): void
    {
        // Arrange
        Notification::fake();
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->post(route('verification.send'));

        // Assert
        $response->assertRedirect(route('dashboard', absolute: false));
        Notification::assertNothingSent();
    }

    #[TestDox('A guest posting to verification.send is redirected to login')]
    public function test_guest_is_redirected_to_login(): void
    {
        // Arrange — no setup; the route is behind the auth middleware.

        // Act
        $response = $this->post(route('verification.send'));

        // Assert
        $response->assertRedirect(route('login'));
    }
}
