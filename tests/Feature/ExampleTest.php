<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_pages_return_successful_responses(): void
    {
        foreach (['/', '/promociones', '/ventas'] as $path) {
            $response = $this->get($path);

            $response->assertOk();
        }
    }
}
