<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base for command tests that do not need database migrations.
 * Concrete so PHPUnit can run this file when it is discovered; placeholder test prevents "no tests" warning.
 */
class CommandTestCase extends BaseTestCase
{
    public function test_base_case_is_not_executed_as_test(): void
    {
        $this->assertTrue(true);
    }
}
