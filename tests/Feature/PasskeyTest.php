<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laragear\WebAuthn\Models\WebAuthnCredential;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class PasskeyTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Insert a bare-minimum credential row directly, bypassing the WebAuthn ceremony.
     */
    private function createCredentialFor(User $user, string $credentialId = 'test-cred-id'): WebAuthnCredential
    {
        return WebAuthnCredential::forceCreate([
            'id' => $credentialId,
            'authenticatable_type' => User::class,
            'authenticatable_id' => $user->id,
            'user_id' => Str::uuid()->toString(),
            'alias' => null,
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'transports' => null,
            'aaguid' => null,
            'public_key' => 'test-public-key',
            'attestation_format' => 'none',
            'certificates' => null,
            'disabled_at' => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Registration routes — access control
    // -------------------------------------------------------------------------

    #[TestDox('Guests cannot fetch passkey registration options')]
    public function test_register_options_requires_authentication(): void
    {
        // Arrange — unauthenticated.

        // Act
        $response = $this->postJson(route('webauthn.register.options'));

        // Assert
        $response->assertUnauthorized();
    }

    #[TestDox('Guests cannot submit a passkey registration')]
    public function test_register_requires_authentication(): void
    {
        // Arrange — unauthenticated.

        // Act
        $response = $this->postJson(route('webauthn.register'));

        // Assert
        $response->assertUnauthorized();
    }

    #[TestDox('Guests cannot delete a passkey credential')]
    public function test_destroy_requires_authentication(): void
    {
        // Arrange — unauthenticated.
        $user = User::factory()->create();
        $credential = $this->createCredentialFor($user);

        // Act
        $response = $this->deleteJson(route('webauthn.destroy', $credential->id));

        // Assert
        $response->assertUnauthorized();
        $this->assertDatabaseHas('webauthn_credentials', ['id' => $credential->id]);
    }

    // -------------------------------------------------------------------------
    // Destroy — authorisation
    // -------------------------------------------------------------------------

    #[TestDox('An authenticated user can delete their own passkey')]
    public function test_destroy_removes_own_credential(): void
    {
        // Arrange
        $user = User::factory()->create();
        $credential = $this->createCredentialFor($user);

        // Act
        $response = $this->actingAs($user)->deleteJson(route('webauthn.destroy', $credential->id));

        // Assert
        $response->assertNoContent();
        $this->assertDatabaseMissing('webauthn_credentials', ['id' => $credential->id]);
    }

    #[TestDox('A user cannot delete another user\'s passkey')]
    public function test_destroy_ignores_other_users_credential(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $credential = $this->createCredentialFor($owner);

        // Act — other user attempts deletion
        $response = $this->actingAs($other)->deleteJson(route('webauthn.destroy', $credential->id));

        // Assert — 204 (silent no-op because the WHERE narrows to the acting user)
        $response->assertNoContent();
        $this->assertDatabaseHas('webauthn_credentials', ['id' => $credential->id]);
    }

    // -------------------------------------------------------------------------
    // Assertion routes — access control
    // -------------------------------------------------------------------------

    #[TestDox('Authenticated users cannot fetch passkey login options')]
    public function test_login_options_blocked_for_authenticated_users(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->postJson(route('webauthn.login.options'));

        // Assert
        $response->assertRedirect();
    }

    #[TestDox('Guests receive a JSON challenge when requesting passkey login options')]
    public function test_login_options_returns_json_challenge(): void
    {
        // Arrange — unauthenticated.

        // Act
        $response = $this->postJson(route('webauthn.login.options'));

        // Assert
        $response->assertOk();
        $response->assertJsonStructure(['challenge']);
    }

    #[TestDox('Passkey login options accept an optional email to scope allowed credentials')]
    public function test_login_options_accepts_email(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->postJson(route('webauthn.login.options'), ['email' => $user->email]);

        // Assert
        $response->assertOk();
        $response->assertJsonStructure(['challenge']);
    }

    // -------------------------------------------------------------------------
    // Profile view
    // -------------------------------------------------------------------------

    #[TestDox('The profile edit page renders the passkey management section')]
    public function test_profile_shows_passkey_section(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->get(route('profile.edit'));

        // Assert
        $response->assertOk();
        $response->assertSee('Passkeys');
        $response->assertSee('passkey-register-section');
    }

    #[TestDox('The profile passkey section lists existing registered passkeys')]
    public function test_profile_lists_existing_passkeys(): void
    {
        // Arrange
        $user = User::factory()->create();
        $credential = $this->createCredentialFor($user);
        $credential->alias = 'My iPhone';
        $credential->save();

        // Act
        $response = $this->actingAs($user)->get(route('profile.edit'));

        // Assert
        $response->assertOk();
        $response->assertSee('My iPhone');
        $response->assertSee('data-credential-id="' . $credential->id . '"', false);
    }

    // -------------------------------------------------------------------------
    // Login page
    // -------------------------------------------------------------------------

    #[TestDox('The login page includes the biometric checkbox and passkey JS hooks')]
    public function test_login_page_renders_passkey_elements(): void
    {
        // Arrange — unauthenticated.

        // Act
        $response = $this->get(route('login'));

        // Assert
        $response->assertOk();
        $response->assertSee('use_biometric');
        $response->assertSee('biometric-checkbox-label');
        $response->assertSee(route('webauthn.login.options', [], false));
        $response->assertSee(route('webauthn.login', [], false));
    }

    /**
     * Pages whose inline scripts reference window.cn.webauthn and therefore
     * must run AFTER app.js (a deferred module). Each case is a route plus a
     * flag for whether the route needs an authenticated user.
     *
     * @return array<string, array{route: string, authenticate: bool}>
     */
    public static function pagesWithModuleScopedWebauthnProvider(): array
    {
        return [
            'login page' => ['route' => 'login', 'authenticate' => false],
            'dashboard (biometric setup modal)' => ['route' => 'dashboard', 'authenticate' => true],
        ];
    }

    #[TestDox('Inline scripts that read window.cn.webauthn must be rendered with type="module" so they run after app.js')]
    #[DataProvider('pagesWithModuleScopedWebauthnProvider')]
    public function test_inline_webauthn_scripts_are_modules(string $route, bool $authenticate): void
    {
        // Arrange — module scripts are deferred; a classic <script> would parse
        // before app.js loaded window.cn.webauthn and throw on the first line.
        $request = $authenticate ? $this->actingAs(User::factory()->create()) : $this;

        // Act
        $response = $request->get(route($route));

        // Assert
        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/<script\s+type="module">[^<]*window\.cn\.webauthn/s',
            $response->getContent(),
            "Page [{$route}] must render its window.cn.webauthn-using inline script with type=\"module\"."
        );
    }
}
