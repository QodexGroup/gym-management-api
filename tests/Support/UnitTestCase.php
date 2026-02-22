<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base for unit tests that do not need database (no RefreshDatabase).
 * Concrete so PHPUnit can run this file when it is discovered; placeholder test prevents "no tests" warning.
 */
class UnitTestCase extends BaseTestCase
{
    public function test_base_case_is_not_executed_as_test(): void
    {
        $this->assertTrue(true);
    }
}
