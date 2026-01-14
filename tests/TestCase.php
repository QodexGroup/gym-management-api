<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set account_id to 1 for all tests (matching command behavior)
        if (!defined('TEST_ACCOUNT_ID')) {
            define('TEST_ACCOUNT_ID', 1);
        }
    }
}
