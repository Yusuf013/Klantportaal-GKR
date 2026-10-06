<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests mogen nooit echte HTTP-verzoeken doen (naar Meta, Google, enz.).
        // Elk verzoek moet met Http::fake() nagebootst worden, anders faalt de test.
        Http::preventStrayRequests();
    }
}