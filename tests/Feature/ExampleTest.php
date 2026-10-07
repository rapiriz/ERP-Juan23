<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_pages_return_successful_responses(): void
    {
        foreach (['/', '/promociones', '/ventas', '/saldo'] as $path) {
            $response = $this->get($path);

            $response->assertOk();
        }
    }

    public function test_the_current_accounts_page_includes_mock_clients(): void
    {
        $this->get('/saldo')
            ->assertOk()
            ->assertSee('CUENTAS CORRIENTES')
            ->assertSee('Supermercado El Norte SRL')
            ->assertSee('Almac\\u00e9n Don Pedro', false)
            ->assertSee('Mar\\u00eda Gonz\\u00e1lez', false)
            ->assertSee('Registrar pago de deuda');
    }
}
