<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Unlimited untuk suite besar (77 test ~140s) — phpunit.xml <ini> tidak cukup
        // karena max_execution_time tidak selalu bisa di-ini_set setelah startup pada CLI Windows.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('max_execution_time', '0');
        parent::setUp();
    }
}
