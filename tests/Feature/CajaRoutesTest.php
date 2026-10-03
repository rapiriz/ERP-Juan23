<?php
// Indica que el archivo contiene código PHP.

namespace Tests\Feature;
// Ubica esta clase dentro del grupo de pruebas Feature.
// Composer y PHPUnit encuentran las pruebas bajo tests/Feature.

use App\Services\Ventas\CajaService;
// Importa la clase cuya llamada al controlador se va a simular.

use Tests\TestCase;
// Importa la clase base del proyecto para pruebas.
// Esta inicia Laravel y ofrece helpers como getJson(), postJson() y mock().

class CajaRoutesTest extends TestCase
// Declara la prueba y hereda las herramientas de Laravel.
{
    public function test_caja_summary_route_returns_the_service_result(): void
    // PHPUnit reconoce como prueba los métodos cuyo nombre empieza con test_.
    // Esta prueba verifica GET /ventas/caja/resumen.
    {
        $resumen = ['fecha' => '2026-10-01', 'efectivo_esperado' => 1250.0];
        // Prepara una respuesta ficticia para el resumen.
        // No consulta MySQL ni calcula el efectivo real.

        $this->mock(CajaService::class, function ($mock) use ($resumen): void {
            // Registra un reemplazo de CajaService en el contenedor de Laravel.
            // El controlador recibirá este doble de prueba en vez del servicio real.
            // "use ($resumen)" permite que la función interna use el arreglo externo.

            $mock->shouldReceive('resumen')
                // Indica que se espera una llamada al método resumen().

                ->once()
                // Exige que se llame exactamente una vez.

                ->with(1, '2026-10-01')
                // Exige esos argumentos: usuario 1 y fecha solicitada.
                // El 1 proviene del usuario demo de config/ventas.php,
                // porque en esta prueba no se inicia sesión con otro usuario.

                ->andReturn($resumen);
                // Hace que la llamada devuelva el resumen ficticio.
        });

        $this->getJson('/ventas/caja/resumen?fecha=2026-10-01')
            // Envía un GET al endpoint como petición JSON.
            // Laravel resuelve la ruta y ejecuta CajaController::resumen().

            ->assertOk()
            // Comprueba que la respuesta HTTP sea 200.

            ->assertExactJson(['ok' => true, 'caja' => $resumen]);
            // Comprueba que el JSON tenga exactamente estas claves y valores.
    }

    public function test_close_route_validates_input_and_returns_the_arqueo(): void
    // Verifica POST /ventas/caja/cerrar.
    // Aunque el nombre menciona validación, el caso actual envía datos válidos:
    // no prueba todavía qué pasa cuando los datos son inválidos.
    {
        $resumen = ['fecha' => '2026-10-01', 'diferencia' => 0.0];
        // Prepara otra respuesta ficticia del servicio.

        $this->mock(CajaService::class, function ($mock) use ($resumen): void {
            // Sustituye otra vez CajaService por un mock.

            $mock->shouldReceive('resumen')
                // Espera que el controlador invoque resumen().

                ->once()
                // Exige una sola llamada.

                ->with(1, '2026-10-01', 1250.0)
                // Comprueba usuario, fecha y efectivo contado.
                // El controlador convierte efectivo_contado a float antes de llamar.

                ->andReturn($resumen);
                // Devuelve el resultado preparado.
        });

        $this->postJson('/ventas/caja/cerrar', [
            // Envía un POST JSON al endpoint de arqueo.

            'fecha'            => '2026-10-01',
            // Fecha que recibe el controlador.

            'efectivo_contado' => 1250.0,
            // Importe contado por el cajero.
        ])
            ->assertOk()
            // Comprueba que Laravel respondió HTTP 200.

            ->assertExactJson(['ok' => true, 'caja' => $resumen]);
            // Comprueba el contrato JSON esperado por el frontend.
    }
}

/* Logica de la prueba:
¿Cómo se conecta con las demás partes?

1. ventas.php declara GET /ventas/caja/resumen y POST /ventas/caja/cerrar,
y los dirige a CajaController.

2.web.php carga ventas.php.
 Por eso las rutas están dentro del grupo web de Laravel, con sesión y protección CSRF.

3.CajaController.php valida los datos de la petición y llama a CajaService::resumen().

4.CajaService.php hace el cálculo real consultando movimientos de caja y ventas.

5.Esta prueba intercepta esa llamada al servicio y devuelve datos preparados.
Así aísla la ruta y el controlador: no necesita MySQL ni depende de datos reales.

¿Qué significa para conectar el frontend?
Para el boton Cerrar Caja, el frontend puede enviar POST /ventas/caja/cerrar con fecha y efectivo_contado.
 La respuesta tiene la forma { "ok": true, "caja": { ... } }. Para previsualizar el arqueo sin informar
 el efectivo contado, existe GET /ventas/caja/resumen?fecha=AAAA-MM-DD.


Limites de la prueba:
Esta prueba no comprueba que los números calculados por CajaService sean correctos ni que las tablas/columnas
existan en MySQL; comprueba el cableado HTTP y el formato de respuesta.

Caja/cerrar hoy calcula una diferencia, pero no persiste un cierre ni bloquea nuevas ventas.
El frontend debería presentarlo como arqueo mientras no exista almacenamiento de cierres.

Como mejora de cobertura, convendría agregar una prueba que envíe una fecha inválida o un efectivo negativo y espere 422;
 la prueba actual solo cubre la petición válida

*/
