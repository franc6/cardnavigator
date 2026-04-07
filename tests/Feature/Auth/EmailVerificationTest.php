<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('The email verification prompt renders for an unverified user')]
    public function test_email_verification_screen_can_be_rendered(): void
    {
        // Arrange
        $user = User::factory()->unverified()->create();

        // Act
        $response = $this->actingAs($user)->get('/verify-email');

        // Assert
        $response->assertStatus(200);
    }

    #[TestDox('A valid signed verification URL marks the user as verified')]
    public function test_email_can_be_verified(): void
    {
        // Arrange
        $user = User::factory()->unverified()->create();
        Event::fake();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Act
        $response = $this->actingAs($user)->get($verificationUrl);

        // Assert
        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false) . '?verified=1');
    }

    #[TestDox('A verification URL with an invalid hash does not verify the email')]
    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        // Arrange
        $user = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        // Act
        $this->actingAs($user)->get($verificationUrl);

        // Assert
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    #[TestDox('A user who is already verified is redirected from the prompt to the dashboard')]
    public function test_already_verified_user_is_redirected_from_prompt(): void
    {
        // Arrange — factory default sets email_verified_at = now().
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->get('/verify-email');

        // Assert
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    #[TestDox('A signed verify link for an already-verified user redirects without re-firing the Verified event')]
    public function test_already_verified_user_signed_link_short_circuits(): void
    {
        // Arrange
        Event::fake([Verified::class]);
        $user = User::factory()->create();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Act
        $response = $this->actingAs($user)->get($verificationUrl);

        // Assert
        $response->assertRedirect(route('dashboard', absolute: false) . '?verified=1');
        Event::assertNotDispatched(Verified::class);
    }
}
