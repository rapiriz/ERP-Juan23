<?php

namespace Tests\Unit\Rendiciones;

use App\Modules\Entregas\Integraciones\VentasApiClient;
use App\Modules\Rendiciones\Models\Rendicion;
use App\Modules\Rendiciones\Models\RendicionCobro;
use App\Modules\Rendiciones\Repositories\RendicionRepository;
use App\Modules\Rendiciones\Services\RendicionService;
use Tests\TestCase;

/**
 * Pruebas unitarias de RendicionService (Grupo 2).
 * Valida la lógica de negocio, cálculos de totales, estados y resiliencia
 * aislando dependencias externas.
 */
class RendicionServiceTest extends TestCase
{
    private function rendicionFake(array $attrs): Rendicion
    {
        $r = new Rendicion($attrs);
        if (isset($attrs['id_rendicion'])) {
            $r->id_rendicion = $attrs['id_rendicion'];
        }

        return $r;
    }

    public function test_registrar_rendicion_calcula_total_y_guarda_en_estado_pendiente(): void
    {
        $repoMock = $this->createMock(RendicionRepository::class);
        $ventasMock = $this->createMock(VentasApiClient::class);

        $repoMock->method('repartidorEstaActivo')->with(2)->willReturn(true);
        $repoMock->method('entregaExiste')->with(1)->willReturn(true);

        $datosCobros = [
            [
                'id_cliente' => 1,
                'id_factura' => 10,
                'monto' => 15000.50,
                'medio_pago' => 'efectivo',
            ],
            [
                'id_cliente' => 2,
                'id_factura' => 11,
                'monto' => 25000.00,
                'medio_pago' => 'transferencia',
            ],
        ];

        $repoMock->expects($this->once())
            ->method('crear')
            ->with($this->callback(function ($datos) {
                return $datos['id_repartidor'] === 2
                    && $datos['id_entrega'] === 1;
            }))
            ->willReturn($this->rendicionFake([
                'id_rendicion' => 1,
                'id_repartidor' => 2,
                'id_entrega' => 1,
                'total_rendido' => 0.0,
                'estado' => Rendicion::ESTADO_PENDIENTE,
            ]));

        $repoMock->expects($this->exactly(2))
            ->method('agregarCobro')
            ->willReturnCallback(function ($idRendicion, $cobro) {
                return new RendicionCobro(array_merge(['id_rendicion' => $idRendicion], $cobro));
            });

        $service = new RendicionService($repoMock, $ventasMock);
        $resultado = $service->registrarRendicion([
            'id_repartidor' => 2,
            'id_entrega' => 1,
            'cobros' => $datosCobros,
            'observaciones' => 'Rendición de prueba',
        ]);

        $this->assertEquals(1, $resultado['id_rendicion']);
        $this->assertEquals(40000.50, $resultado['total_rendido']);
        $this->assertEquals(Rendicion::ESTADO_PENDIENTE, $resultado['estado']);
        $this->assertEquals(2, $resultado['cantidad_cobros_asociados']);
    }

    // La revisión se prueba con persistencia real en G2FinancialOperationsTest.
    // Se reemplazaron los cuatro casos de revisión con mocks por pruebas del circuito transaccional.
}
