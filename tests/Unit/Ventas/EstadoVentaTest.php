<?php

namespace Tests\Unit\Ventas;

use App\Modules\Ventas\Enums\EstadoVenta;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests UNITARIOS de las reglas de estados (app/Modules/Ventas/Enums/EstadoVenta.php).
 * No usan base de datos: prueban solo la lógica pura.
 */
class EstadoVentaTest extends TestCase
{
    public function test_hay_exactamente_siete_estados(): void
    {
        $this->assertSame(
            ['pendiente', 'confirmada', 'preparada', 'entregada', 'pagada', 'facturada', 'cancelada'],
            EstadoVenta::valores()
        );
    }

    #[DataProvider('reglasPorEstado')]
    public function test_reglas_de_cada_estado(EstadoVenta $estado, bool $modificar, bool $eliminar, bool $restaurar): void
    {
        $this->assertSame($modificar, $estado->permiteEdicion(), "modificar en {$estado->value}");
        $this->assertSame($eliminar, $estado->permiteAnulacion(), "eliminar en {$estado->value}");
        $this->assertSame($restaurar, $estado->permiteRestauracion(), "restaurar en {$estado->value}");
    }

    public static function reglasPorEstado(): array
    {
        // estado => [puede modificar, puede eliminar, puede restaurar]
        return [
            'pendiente' => [EstadoVenta::Pendiente, true, true, false],
            'confirmada' => [EstadoVenta::Confirmada, true, true, false],
            'preparada' => [EstadoVenta::Preparada, true, true, false],
            'entregada' => [EstadoVenta::Entregada, true, true, false],
            'pagada' => [EstadoVenta::Pagada, false, false, false],
            'facturada' => [EstadoVenta::Facturada, false, false, false],
            'cancelada' => [EstadoVenta::Cancelada, false, false, true],
        ];
    }

    /**
     * Prueba las 49 combinaciones (7 estados x 7 destinos) contra la tabla esperada.
     */
    #[DataProvider('todasLasTransiciones')]
    public function test_matriz_completa_de_transiciones(EstadoVenta $desde, EstadoVenta $hacia, bool $esperado): void
    {
        $this->assertSame($esperado, $desde->puedePasarA($hacia), "{$desde->value} → {$hacia->value}");
    }

    public static function todasLasTransiciones(): array
    {
        $permitidas = [
            'pendiente' => ['confirmada', 'preparada', 'entregada', 'pagada'],
            'confirmada' => ['preparada', 'entregada', 'pagada'],
            'preparada' => ['entregada', 'pagada'],
            'entregada' => ['pagada'],
            'pagada' => ['facturada'],
            'facturada' => [],
            'cancelada' => [],
        ];

        $casos = [];
        foreach (EstadoVenta::cases() as $desde) {
            foreach (EstadoVenta::cases() as $hacia) {
                $casos["{$desde->value} → {$hacia->value}"] = [
                    $desde,
                    $hacia,
                    in_array($hacia->value, $permitidas[$desde->value], true),
                ];
            }
        }

        return $casos;
    }

    public function test_nunca_se_puede_pasar_a_cancelada_por_cambio_de_estado(): void
    {
        foreach (EstadoVenta::cases() as $estado) {
            $this->assertFalse($estado->puedePasarA(EstadoVenta::Cancelada), $estado->value);
        }
    }

    public function test_nunca_se_vuelve_atras(): void
    {
        $orden = EstadoVenta::cases();
        foreach ($orden as $i => $desde) {
            foreach (array_slice($orden, 0, $i + 1) as $anterior) {
                $this->assertFalse($desde->puedePasarA($anterior), "{$desde->value} → {$anterior->value}");
            }
        }
    }

    public function test_desde_texto_tolera_mayusculas_y_espacios(): void
    {
        $this->assertSame(EstadoVenta::Pagada, EstadoVenta::desdeTexto(' PAGADA '));
        $this->assertSame(EstadoVenta::Confirmada, EstadoVenta::desdeTexto('confirmada'));
    }

    public function test_desde_texto_devuelve_null_si_no_es_un_estado(): void
    {
        $this->assertNull(EstadoVenta::desdeTexto('inventado'));
        $this->assertNull(EstadoVenta::desdeTexto(''));
        $this->assertNull(EstadoVenta::desdeTexto(null));
    }

    public function test_acciones_para_cada_estado_guardado(): void
    {
        $this->assertSame(
            ['anulada' => false, 'puede_modificar' => true, 'puede_eliminar' => true, 'puede_restaurar' => false],
            EstadoVenta::accionesPara('preparada')
        );
        $this->assertSame(
            ['anulada' => false, 'puede_modificar' => false, 'puede_eliminar' => false, 'puede_restaurar' => false],
            EstadoVenta::accionesPara('pagada')
        );
        $this->assertSame(
            ['anulada' => true, 'puede_modificar' => false, 'puede_eliminar' => false, 'puede_restaurar' => true],
            EstadoVenta::accionesPara('cancelada')
        );
    }

    public function test_estado_desconocido_no_habilita_ningun_boton(): void
    {
        $this->assertSame(
            ['anulada' => false, 'puede_modificar' => false, 'puede_eliminar' => false, 'puede_restaurar' => false],
            EstadoVenta::accionesPara('roto')
        );
    }

    public function test_a_array_trae_todo_lo_que_necesita_el_front(): void
    {
        $this->assertSame([
            'valor' => 'entregada',
            'etiqueta' => 'Entregada',
            'permite_modificar' => true,
            'permite_eliminar' => true,
            'permite_restaurar' => false,
            'siguientes' => ['pagada'],
        ], EstadoVenta::Entregada->aArray());
    }
}
