<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    #[TestDox('The application home page redirects unauthenticated visitors to the login page')]
    public function test_the_application_returns_a_successful_response(): void
    {
        // Arrange — no authenticated user.

        // Act
        $response = $this->get('/');

        // Assert
        $response->assertRedirect(route('login'));
    }
}
