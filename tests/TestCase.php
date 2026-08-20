<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'sync']);

        // Cache array driver é global ao processo phpunit inteiro, não por
        // teste. Sem isso, o RateLimiter (throttle:auth-register etc, todos
        // por IP) acumula hits entre testes de suítes diferentes e passa a
        // devolver 429 pra requisições que nada tem a ver com rate limit.
        Cache::flush();
    }
}
