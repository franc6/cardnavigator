<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Project-wide base class for tests that need the Laravel application bootstrapped
 * (DB, cache, routing, container). Unit tests for classes without framework dependencies
 * should extend PHPUnit\Framework\TestCase instead to avoid the boot cost.
 */
abstract class TestCase extends BaseTestCase
{
    //
}
