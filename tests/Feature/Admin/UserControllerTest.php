<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    #[TestDox('The index lists users ordered by name ascending')]
    public function test_index_lists_users_ordered_by_name(): void
    {
        // Arrange
        $admin = $this->admin();
        User::factory()->create(['name' => 'Mango']);
        User::factory()->create(['name' => 'Apple']);
        User::factory()->create(['name' => 'Banana']);

        // Act
        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        // Assert
        $response->assertOk();
        $names = $response->viewData('users')->pluck('name')->all();
        $sorted = $names;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $names);
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    #[TestDox('Store creates a regular user with a hashed password and redirects with a status')]
    public function test_store_creates_user(): void
    {
        // Arrange
        $admin = $this->admin();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // Assert
        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('status', __('User created.'));
        $created = User::where('email', 'new@example.com')->first();
        $this->assertNotNull($created);
        $this->assertSame('New Person', $created->name);
        $this->assertTrue(Hash::check('password123', $created->password));
        $this->assertFalse((bool) $created->is_admin);
    }

    #[TestDox('Store honours the force_password_change flag when provided')]
    public function test_store_honours_force_password_change(): void
    {
        // Arrange
        $admin = $this->admin();

        // Act
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Forced',
            'email' => 'forced@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'force_password_change' => '1',
        ]);

        // Assert
        $created = User::where('email', 'forced@example.com')->first();
        $this->assertNotNull($created);
        $this->assertTrue((bool) $created->force_password_change);
    }

    public static function invalidStoreDataProvider(): array
    {
        $valid = [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        return [
            'duplicate email' => [array_merge($valid, ['email' => 'taken@example.com']), 'email'],
            'short password' => [array_merge($valid, ['password' => 'short', 'password_confirmation' => 'short']), 'password'],
            'unconfirmed password' => [array_merge($valid, ['password_confirmation' => 'mismatch']), 'password'],
            'missing name' => [array_merge($valid, ['name' => '']), 'name'],
        ];
    }

    #[DataProvider('invalidStoreDataProvider')]
    #[TestDox('Store rejects invalid input and returns session errors')]
    public function test_store_validation(array $payload, string $expectedErrorKey): void
    {
        // Arrange
        $admin = $this->admin();
        User::factory()->create(['email' => 'taken@example.com']);

        // Act
        $response = $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.store'), $payload);

        // Assert
        $response->assertSessionHasErrors($expectedErrorKey);
    }

    // -------------------------------------------------------------------------
    // resetPassword
    // -------------------------------------------------------------------------

    #[TestDox('Reset password updates the password hash and force_password_change flag')]
    public function test_reset_password_updates_user(): void
    {
        // Arrange
        $admin = $this->admin();
        $target = User::factory()->create(['force_password_change' => false]);

        // Act
        $response = $this->actingAs($admin)->patch(route('admin.users.reset-password', $target), [
            'password' => 'brandnewpw',
            'password_confirmation' => 'brandnewpw',
            'force_password_change' => '1',
        ]);

        // Assert
        $response->assertRedirect(route('admin.users.index'));
        $target->refresh();
        $this->assertTrue(Hash::check('brandnewpw', $target->password));
        $this->assertTrue((bool) $target->force_password_change);
    }

    public static function invalidResetDataProvider(): array
    {
        return [
            'short password' => [['password' => 'short', 'password_confirmation' => 'short']],
            'unconfirmed password' => [['password' => 'longenough', 'password_confirmation' => 'different']],
            'missing password' => [['password_confirmation' => 'whatever']],
        ];
    }

    #[DataProvider('invalidResetDataProvider')]
    #[TestDox('Reset password rejects invalid input')]
    public function test_reset_password_validation(array $payload): void
    {
        // Arrange
        $admin = $this->admin();
        $target = User::factory()->create();

        // Act
        $response = $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.reset-password', $target), $payload);

        // Assert
        $response->assertSessionHasErrors('password');
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    #[TestDox('Destroy deletes another user and redirects with a status')]
    public function test_destroy_deletes_user(): void
    {
        // Arrange
        $admin = $this->admin();
        $target = User::factory()->create();

        // Act
        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $target));

        // Assert
        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('status', __('User deleted.'));
        $this->assertNull($target->fresh());
    }

    #[TestDox('Destroy aborts 403 when an admin tries to delete their own account')]
    public function test_destroy_forbids_self_delete(): void
    {
        // Arrange
        $admin = $this->admin();

        // Act
        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $admin));

        // Assert
        $response->assertStatus(403);
        $this->assertNotNull($admin->fresh());
    }
}
