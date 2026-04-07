<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('An authenticated user can view the profile edit page')]
    public function test_profile_page_is_displayed(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->get('/profile');

        // Assert
        $response->assertOk();
    }

    #[TestDox('An authenticated user can update their name and email')]
    public function test_profile_information_can_be_updated(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        // Assert
        $response->assertSessionHasNoErrors()->assertRedirect('/profile');
        $user->refresh();
        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    #[TestDox('Email verification status is not cleared when the email address is unchanged')]
    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        // Assert
        $response->assertSessionHasNoErrors()->assertRedirect('/profile');
        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    #[TestDox('An authenticated user can delete their own account')]
    public function test_user_can_delete_their_account(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)
            ->delete('/profile', ['password' => 'password']);

        // Assert
        $response->assertSessionHasNoErrors()->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    #[TestDox('The correct password must be provided to delete an account')]
    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)
            ->from('/profile')
            ->delete('/profile', ['password' => 'wrong-password']);

        // Assert
        $response->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');
        $this->assertNotNull($user->fresh());
    }
}
