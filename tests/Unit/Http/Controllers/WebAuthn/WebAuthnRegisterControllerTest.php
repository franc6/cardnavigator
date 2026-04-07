<?php

namespace Tests\Unit\Http\Controllers\WebAuthn;

use App\Http\Controllers\WebAuthn\WebAuthnRegisterController;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;
use Mockery;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class WebAuthnRegisterControllerTest extends TestCase
{
    #[TestDox('options() returns the fast-registration create challenge from the AttestationRequest')]
    public function test_options_returns_create_challenge(): void
    {
        // Arrange
        $sentinel = new class implements \Illuminate\Contracts\Support\Responsable
        {
            public function toResponse($request)
            {
                return response('challenge');
            }
        };
        $request = Mockery::mock(AttestationRequest::class);
        $request->shouldReceive('fastRegistration')->once()->andReturnSelf();
        $request->shouldReceive('toCreate')->once()->andReturn($sentinel);

        // Act
        $result = (new WebAuthnRegisterController())->options($request);

        // Assert
        $this->assertSame($sentinel, $result);
    }

    #[TestDox('register() saves the attested credential and returns 204')]
    public function test_register_saves_and_returns_204(): void
    {
        // Arrange
        $request = Mockery::mock(AttestedRequest::class);
        $request->shouldReceive('save')->once();

        // Act
        $response = (new WebAuthnRegisterController())->register($request);

        // Assert
        $this->assertSame(204, $response->getStatusCode());
    }
}
