<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    #[TestDox('Basic sanity check that the test suite is operational')]
    public function test_that_true_is_true(): void
    {
        // Arrange — no setup needed.

        // Act
        $result = true;

        // Assert
        $this->assertTrue($result);
    }
}
