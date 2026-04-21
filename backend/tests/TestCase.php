<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $statefulDomain = explode(',', (string) config('sanctum.stateful')[0] ?? 'localhost')[0];
        $this->withHeader('Referer', 'http://' . $statefulDomain);
    }
}
