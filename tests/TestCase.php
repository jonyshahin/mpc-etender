<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Tests\Support\FakeDecisionEngine;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every test gets a healthy stand-in for the Decision Engine, and no test
     * can reach a real one: publishing a tender now locks a profile over HTTP.
     */
    protected function setUp(): void
    {
        parent::setUp();

        FakeDecisionEngine::install();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
