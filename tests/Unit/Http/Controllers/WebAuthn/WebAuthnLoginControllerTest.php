<?php

namespace Tests\Unit\Http\Controllers\WebAuthn;

use App\Http\Controllers\WebAuthn\WebAuthnLoginController;
use App\Models\User;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;
use Mockery;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class WebAuthnLoginControllerTest extends TestCase
{
    #[TestDox('login() returns 204 when the AssertedRequest reports a successful login')]
    public function test_login_returns_204_on_success(): void
    {
        // Arrange
        $authenticated = Mockery::mock(WebAuthnAuthenticatable::class, User::class);
        $request = Mockery::mock(AssertedRequest::class);
        $request->shouldReceive('login')->once()->andReturn($authenticated);

        // Act
        $response = (new WebAuthnLoginController())->login($request);

        // Assert
        $this->assertSame(204, $response->getStatusCode());
    }

    #[TestDox('login() returns 422 when the AssertedRequest reports a failed login')]
    public function test_login_returns_422_on_failure(): void
    {
        // Arrange
        $request = Mockery::mock(AssertedRequest::class);
        $request->shouldReceive('login')->once()->andReturn(null);

        // Act
        $response = (new WebAuthnLoginController())->login($request);

        // Assert
        $this->assertSame(422, $response->getStatusCode());
    }

    #[TestDox('options() delegates to the AssertionRequest with the validated email payload')]
    public function test_options_delegates_to_request(): void
    {
        // Arrange
        $sentinel = new class implements \Illuminate\Contracts\Support\Responsable
        {
            public function toResponse($request)
            {
                return response('challenge');
            }
        };
        $request = Mockery::mock(AssertionRequest::class);
        $request->shouldReceive('validate')->once()->with(['email' => 'sometimes|email|string'])->andReturn(['email' => 'user@example.com']);
        $request->shouldReceive('toVerify')->once()->with(['email' => 'user@example.com'])->andReturn($sentinel);

        // Act
        $result = (new WebAuthnLoginController())->options($request);

        // Assert
        $this->assertSame($sentinel, $result);
    }
}
