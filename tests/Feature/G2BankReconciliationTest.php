<?php

namespace Tests\Feature;

use App\Modules\Caja\Services\CajaService;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\ConciliacionBancaria\Models\MovimientoBancario;
use App\Modules\ConciliacionBancaria\Services\BankMoney;
use App\Modules\ConciliacionBancaria\Services\BankPeriodService;
use App\Modules\Login\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Tests\TestCase;

class G2BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $period;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 11, 9));
        $this->admin = User::factory()->create(['rol' => 'administrativo']);
        $this->signInAs($this->admin);
        $this->period = app(BankPeriodService::class)->create('2026-11-01', '2026-11-30', $this->admin->id)->id_periodo;
    }

    private function bank(float $amount = 100, array $extra = []): int
    {
        return MovimientoBancario::create(['id_periodo' => $this->period, 'id_usuario' => $this->admin->id, 'fecha_operacion' => '2026-11-09', 'monto' => $amount,
            'signo_monto' => 'ingreso', 'descripcion_original' => 'TRANSFERENCIA RECIBIDA', 'descripcion_normalizada' => 'TRANSFERENCIA RECIBIDA', 'tipo_movimiento' => 'cobranza_cliente', 'estado_conciliacion' => 'pendiente', ...$extra])->id_movimiento_bancario;
    }

    private function source(float $amount = 100): array
    {
        $cash = app(CajaService::class);
        $open = $cash->obtenerCajaAbiertaDeUsuario($this->admin->id) ?? $cash->abrirCaja($this->admin->id, 'admin', 0);
        $move = $cash->registrarMovimiento($open['id_caja'], $this->admin->id, 'admin', 'ingreso_manual', 'Depósito registrado', $amount, null, 'transferencia');

        return ['origen' => 'caja', 'id' => $move['id_movimiento_caja']];
    }

    private function payload(array $bank, array $sources): array
    {
        return ['request_key' => (string) Str::uuid(), 'movimientos' => $bank, 'origenes' => $sources];
    }

    private function xls(array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'g2xls');
        try {
            (new Xls($book))->save($path);

            return UploadedFile::fake()->createWithContent('extracto.xls', file_get_contents($path));
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_localized_money_and_bad_formats_do_not_truncate(): void
    {
        foreach (['1.250,75' => 125075, '1250.75' => 125075, '-1.250,75' => -125075, '1.250' => 125000, '0,75' => 75] as $input => $expected) {
            $this->assertSame($expected, BankMoney::cents($input));
        }
        foreach (['12oops', '1,250.75', '1.23.4', '2,555', 'NaN'] as $bad) {
            try {
                BankMoney::cents($bad);
                $this->fail('Importe inválido aceptado');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_import_uses_headers_locale_private_copy_and_duplicate_hash(): void
    {
        $file = $this->xls([['Fecha', 'Descripción', 'Importe', 'Saldo'], ['09/11/2026', 'TRANSFERENCIA RECIBIDA', '1.250,75', '2.000,75']]);
        $this->postJson('/g2/api/bancos/extractos', ['archivo' => $file])->assertCreated()->assertJsonPath('filas_insertadas', 1);
        $this->assertDatabaseHas('MOVIMIENTO_BANCARIO', ['monto' => 1250.75, 'id_periodo' => $this->period, 'signo_monto' => 'ingreso']);
        $this->postJson('/g2/api/bancos/extractos', ['archivo' => $file])->assertConflict();
        $this->assertCount(1, Storage::disk('local')->allFiles('extractos_bancarios'));
    }

    public function test_invalid_row_rejects_entire_file_and_leaves_no_archive(): void
    {
        $file = $this->xls([['Fecha', 'Descripción', 'Importe', 'Saldo'], ['09/11/2026', 'TRANSFERENCIA', '1.250,75', null], ['31/02/2026', 'Otra', 'garbage', null]]);
        $this->postJson('/g2/api/bancos/extractos', ['archivo' => $file])->assertUnprocessable();
        $this->assertDatabaseCount('MOVIMIENTO_BANCARIO', 0);
        $this->assertDatabaseCount('archivo_importado_log', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_manual_one_to_many_exact_sum_retry_and_availability(): void
    {
        $id = $this->bank();
        $one = $this->source(60);
        $two = $this->source(40);
        $payload = $this->payload([$id], [$one, $two]);
        $response = $this->postJson('/g2/api/bancos/grupos', $payload)->assertCreated();
        $this->postJson('/g2/api/bancos/grupos', $payload)->assertCreated()->assertJsonPath('data.repetido', true);
        $this->assertDatabaseCount('CONCILIACION_DETALLE', 2);
        $this->assertEquals(100, DB::table('CONCILIACION_DETALLE')->sum('monto_aplicado'));
        $this->postJson('/g2/api/bancos/grupos', $this->payload([$this->bank(60)], [$one]))->assertConflict();
        $this->postJson('/g2/api/bancos/grupos/'.$response->json('data.id').'/revertir', ['motivo' => 'Revisión del cruce'])->assertOk();
        $this->assertDatabaseCount('CONCILIACION_DETALLE', 0);
        $this->assertDatabaseHas('g2_conciliacion_grupos', ['id' => $response->json('data.id'), 'estado' => 'revertido']);
        $legacyBank = $this->bank(10);
        $legacySource = $this->source(10);
        $this->postJson('/g2/api/conciliacion/movimientos/'.$legacyBank.'/conciliar-manual', ['id_movimiento_caja' => $legacySource['id']])
            ->assertCreated()->assertJsonStructure(['data' => ['id_detalle', 'grupo_id']]);
    }

    public function test_many_bank_rows_can_match_one_origin_and_wrong_totals_are_atomic(): void
    {
        $one = $this->bank(60);
        $two = $this->bank(40);
        $source = $this->source(100);
        $this->postJson('/g2/api/bancos/grupos', $this->payload([$one], [$source]))->assertUnprocessable();
        $this->assertDatabaseCount('CONCILIACION_DETALLE', 0);
        $this->postJson('/g2/api/bancos/grupos', $this->payload([$one, $two], [$source]))->assertCreated();
        $this->assertDatabaseCount('CONCILIACION_DETALLE', 2);
        $this->assertDatabaseHas('MOVIMIENTO_BANCARIO', ['id_movimiento_bancario' => $one, 'estado_conciliacion' => 'matcheado', 'estado' => 'conciliado']);
    }

    public function test_disputes_expenses_and_closed_period_restrictions(): void
    {
        $id = $this->bank();
        $this->postJson('/g2/api/bancos/periodos/'.$this->period.'/cerrar')->assertConflict();
        $this->postJson('/g2/api/bancos/movimientos/'.$id.'/clasificar', ['accion' => 'disputar', 'nota' => 'Importe en revisión'])->assertOk();
        $this->postJson('/g2/api/bancos/grupos', $this->payload([$id], [$this->source()]))->assertConflict();
        $this->postJson('/g2/api/bancos/movimientos/'.$id.'/clasificar', ['accion' => 'resolver', 'nota' => 'Revisión completada'])->assertOk();
        $result = $this->postJson('/g2/api/bancos/grupos', $this->payload([$id], [$this->source()]))->assertCreated();
        $expense = $this->bank(5, ['signo_monto' => 'egreso', 'tipo_movimiento' => 'gasto_bancario', 'estado_conciliacion' => 'no_aplica']);
        $this->postJson('/g2/api/bancos/movimientos/'.$expense.'/clasificar', ['accion' => 'gasto', 'nota' => 'Comisión bancaria'])->assertOk();
        $this->assertDatabaseCount('AJUSTE_BANCARIO', 1);
        $this->postJson('/g2/api/bancos/periodos/'.$this->period.'/cerrar')->assertOk();
        $this->postJson('/g2/api/bancos/grupos/'.$result->json('data.id').'/revertir', ['motivo' => 'No permitido'])->assertConflict();
        $this->postJson('/g2/api/bancos/movimientos/'.$expense.'/clasificar', ['accion' => 'disputar', 'nota' => 'No permitido'])->assertConflict();
    }

    public function test_automatic_requires_identity_and_does_not_resolve_ambiguous_amounts(): void
    {
        $client = Cliente::factory()->create(['creado_por' => $this->admin->id]);
        DB::table('cobro')->insert(['id_cliente' => $client->id, 'id_usuario' => $this->admin->id, 'fecha' => '2026-11-09 10:00:00', 'monto_total' => 100, 'medio_pago' => 'transferencia', 'estado' => 'registrado']);
        $id = $this->bank();
        $this->postJson('/g2/api/bancos/periodos/'.$this->period.'/automatico')->assertOk()->assertJsonPath('data.conciliados_automaticamente', 0);
        MovimientoBancario::find($id)->update(['cuit_extraido' => $client->dni_cuit]);
        $this->patchJson('/g2/api/conciliacion/'.$this->period.'/conciliar')->assertOk()->assertJsonPath('data.conciliados_automaticamente', 1)
            ->assertJsonPath('data.detalle_conciliados.0.id_movimiento_bancario', $id)->assertJsonStructure(['data' => ['detalle_conciliados' => [['id_detalle', 'origen', 'id_origen', 'grupo_id']]]]);
        $this->postJson('/g2/api/bancos/periodos/'.$this->period.'/automatico')->assertOk()->assertJsonPath('data.conciliados_automaticamente', 0);
    }

    public function test_closed_period_import_and_overlapping_period_are_rejected(): void
    {
        $this->postJson('/g2/api/bancos/periodos', ['fecha_desde' => '2026-11-15', 'fecha_hasta' => '2026-12-15'])->assertConflict();
        $this->postJson('/g2/api/bancos/periodos/'.$this->period.'/cerrar')->assertOk();
        $file = $this->xls([['Fecha', 'Descripción', 'Importe', 'Saldo'], ['09/11/2026', 'TRANSFERENCIA', '10,50', null]]);
        $this->postJson('/g2/api/bancos/extractos', ['archivo' => $file])->assertConflict();
        $this->assertDatabaseCount('MOVIMIENTO_BANCARIO', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_txt_centavos_and_invalid_calendar_date_are_validated(): void
    {
        $row = str_repeat(' ', 190);
        $row[0] = 'D';
        $row = substr_replace($row, '09-11-2026', 7, 10);
        $row = substr_replace($row, str_pad('TRANSFERENCIA RECIBIDA', 80), 17, 80);
        $row[158] = '+';
        $row = substr_replace($row, str_pad('125075', 17, '0', STR_PAD_LEFT), 159, 17);
        $row = substr_replace($row, str_pad('125075', 14, '0', STR_PAD_LEFT), 176);
        $this->postJson('/g2/api/bancos/extractos', ['archivo' => UploadedFile::fake()->createWithContent('extracto.txt', $row)])->assertCreated();
        $this->assertDatabaseHas('MOVIMIENTO_BANCARIO', ['monto' => 1250.75]);
        $bad = substr_replace($row, '31-02-2026', 7, 10);
        $this->postJson('/g2/api/bancos/extractos', ['archivo' => UploadedFile::fake()->createWithContent('invalido.txt', $bad)])->assertUnprocessable();
        $this->assertDatabaseCount('MOVIMIENTO_BANCARIO', 1);
    }

    public function test_same_origin_for_two_identical_bank_entries_is_ambiguous_not_arbitrarily_chosen(): void
    {
        $client = Cliente::factory()->create(['creado_por' => $this->admin->id]);
        DB::table('cobro')->insert(['id_cliente' => $client->id, 'id_usuario' => $this->admin->id, 'fecha' => '2026-11-09 10:00:00', 'monto_total' => 100, 'medio_pago' => 'transferencia', 'estado' => 'registrado']);
        $this->bank(100, ['cuit_extraido' => $client->dni_cuit]);
        $this->bank(100, ['cuit_extraido' => $client->dni_cuit]);
        $this->postJson('/g2/api/bancos/periodos/'.$this->period.'/automatico')->assertOk()->assertJsonPath('data.conciliados_automaticamente', 0);
        $this->assertDatabaseCount('CONCILIACION_DETALLE', 0);
    }
}
