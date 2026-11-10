<?php

namespace App\Modules\ConciliacionBancaria\Services;

use App\Modules\ConciliacionBancaria\Models\ConciliacionDetalle;
use App\Modules\ConciliacionBancaria\Models\MovimientoBancario;
use App\Modules\CuentaCorriente\Services\SalePaymentEvidence;
use App\Modules\Shared\Services\G2OperationAudit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class BankReconciliationService
{
    private const SOURCES = ['cobro' => ['cobro', 'id_cobro'], 'caja' => ['CAJA_MOVIMIENTO', 'id_movimiento_caja'],
        'cheque' => ['CHEQUE', 'id_cheque'], 'ajuste' => ['AJUSTE_BANCARIO', 'id_ajuste']];

    private const FIELDS = ['cobro' => 'id_cobro', 'caja' => 'id_movimiento_caja', 'cheque' => 'id_cheque', 'ajuste' => 'id_ajuste'];

    public function __construct(private BankPeriodService $periods) {}

    public function source(string $origin, int $id, bool $lock = false): array
    {
        abort_unless(isset(self::SOURCES[$origin]), 422, 'Origen desconocido.');
        [$table,$key] = self::SOURCES[$origin];
        $query = DB::table($table)->where($key, $id);
        $row = ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
        $collection = $origin === 'cobro' ? $row : ($origin === 'cheque' ? DB::table('cobro')->where('id_cobro', $row->id_cobro)->firstOrFail() : null);
        if ($collection) {
            abort_unless($collection->estado === 'registrado', 409, 'El cobro está anulado.');
        }
        if ($origin === 'cobro') {
            abort_if($row->medio_pago === 'cheque', 422, 'Seleccioná el cheque depositado, no su cobro.');
        }
        if ($origin === 'cheque') {
            abort_unless($row->estado === 'depositado', 409, 'Sólo se concilian cheques depositados.');
        }
        if ($origin === 'caja') {
            abort_if($row->medio_pago === 'cheque', 422, 'Usá el cheque depositado como origen.');
        }
        if ($origin === 'caja') {
            abort_if($row->id_cobro !== null && $row->tipo === 'cobro', 422, 'Usá el cobro vinculado para evitar duplicar o conciliar un cobro anulado.');
        }
        $amount = BankMoney::cents($collection?->monto_total ?? $row->monto);
        $date = $origin === 'cheque' ? $row->fecha_deposito : ($origin === 'ajuste' ? $row->fecha_registro : $row->fecha);
        abort_unless($amount > 0 && preg_match('/^\d{4}-\d{2}-\d{2}/D', (string) $date), 422, 'El origen no tiene un importe y una fecha válidos.');
        $sign = $origin === 'caja' && ! in_array($row->tipo, ['cobro', 'ingreso_manual'], true) || $origin === 'ajuste' ? 'egreso' : 'ingreso';
        $aliases = [[$origin, $id]];
        $collectionId = $collection?->id_cobro;
        if ($collectionId) {
            $aliases[] = ['cobro', (int) $collectionId];
            foreach (DB::table('CAJA_MOVIMIENTO')->where('id_cobro', $collectionId)->where('tipo', 'cobro')->pluck('id_movimiento_caja') as $cashId) {
                $aliases[] = ['caja', (int) $cashId];
            }
            foreach (DB::table('CHEQUE')->where('id_cobro', $collectionId)->pluck('id_cheque') as $checkId) {
                $aliases[] = ['cheque', (int) $checkId];
            }
        }
        $client = $collection ? DB::table('clientes')->where('id', $collection->id_cliente)->first() : null;

        return ['origen' => $origin, 'id' => $id, 'monto' => $amount / 100, 'centavos' => $amount, 'fecha' => substr((string) $date, 0, 10), 'signo' => $sign,
            'referencia' => $collection?->comprobante_nro ?? null, 'cuit' => $client?->dni_cuit, 'aliases' => array_values(array_unique($aliases, SORT_REGULAR))];
    }

    private function available(array $source): bool
    {
        foreach ($source['aliases'] as [$origin,$id]) {
            if (DB::table('g2_conciliacion_reservas')->where('origen', $origin)->where('origen_id', $id)->exists()) {
                return false;
            }
            if (ConciliacionDetalle::where(self::FIELDS[$origin], $id)->exists()) {
                return false;
            }
        }

        return true;
    }

    public function sources(): array
    {
        $result = [];
        foreach (self::SOURCES as $origin => [$table,$key]) {
            foreach (DB::table($table)->orderBy($key)->get() as $row) {
                if ($origin === 'caja' && $row->id_cobro !== null && $row->tipo === 'cobro') {
                    continue;
                } // mismo dinero representado por su cobro/cheque
                try {
                    $source = $this->source($origin, (int) $row->$key);
                    if ($this->available($source)) {
                        $result[] = $source;
                    }
                } catch (HttpExceptionInterface $e) {
                    if (! in_array($e->getStatusCode(), [409, 422], true)) {
                        throw $e;
                    }
                }
            }
        }

        return $result;
    }

    public function reconcile(array $bankIds, array $origins, string $key, int $actor, string $method = 'manual', ?string $note = null): array
    {
        $this->periods->actor($actor);
        sort($bankIds);
        usort($origins, fn ($a, $b) => [$a['origen'], $a['id']] <=> [$b['origen'], $b['id']]);
        abort_unless($bankIds && $origins && count($bankIds) === count(array_unique($bankIds)) && count($origins) <= 100 && count($bankIds) <= 100, 422, 'Selección vacía, repetida o demasiado grande.');

        return DB::transaction(function () use ($bankIds, $origins, $key, $actor, $method, $note) {
            DB::table('usuarios')->orderBy('id')->lockForUpdate()->first();
            $selection = ['banco' => $bankIds, 'origenes' => $origins];
            $existing = DB::table('g2_conciliacion_grupos')->where('request_key', $key)->first();
            if ($existing) {
                abort_unless(json_decode($existing->seleccion, true) === $selection && (int) $existing->usuario_id === $actor && $existing->estado === 'activo', 409, 'La clave ya corresponde a otra operación o a un grupo revertido.');

                return ['id' => $existing->id, 'repetido' => true, 'monto' => (float) $existing->monto];
            }
            $banks = MovimientoBancario::whereIn('id_movimiento_bancario', $bankIds)->orderBy('id_movimiento_bancario')->lockForUpdate()->get();
            abort_unless($banks->count() === count($bankIds), 404, 'Movimiento inexistente.');
            abort_unless($banks->pluck('id_periodo')->unique()->count() === 1 && $banks->first()->id_periodo, 422, 'Todos los movimientos deben pertenecer a un mismo período.');
            $period = $this->periods->open($banks->first()->id_periodo);
            $sign = $banks->first()->signo_monto;
            $total = 0;
            foreach ($banks as $bank) {
                abort_unless(in_array($bank->estado_conciliacion, ['pendiente', 'revision_manual'], true) && $bank->signo_monto === $sign, 409, 'Hay movimientos no disponibles o de distinto signo.');
                abort_if(ConciliacionDetalle::where('id_movimiento_bancario', $bank->id_movimiento_bancario)->exists(), 409, 'Movimiento ya conciliado.');
                abort_unless($bank->fecha_operacion->toDateString() >= $period->fecha_desde->toDateString() && $bank->fecha_operacion->toDateString() <= $period->fecha_hasta->toDateString(), 422, 'Movimiento fuera de período.');
                $total += BankMoney::cents($bank->monto);
            }
            $sources = [];
            $aliases = [];
            foreach ($origins as $origin) {
                $source = $this->source($origin['origen'], (int) $origin['id'], true);
                abort_unless($source['signo'] === $sign && $this->available($source), 409, 'Origen no disponible o con signo incompatible.');
                foreach ($source['aliases'] as [$type,$id]) {
                    $alias = $type.':'.$id;
                    abort_if(isset($aliases[$alias]), 422, 'Se seleccionó dos veces el mismo dinero.');
                    $aliases[$alias] = [$type, $id];
                }
                $sources[] = $source;
            }
            abort_unless(array_sum(array_column($sources, 'centavos')) === $total, 422, 'La suma del banco y del sistema debe coincidir exactamente.');
            abort_if($total > 999999999999, 422, 'Total fuera de rango.');
            $group = (string) Str::uuid();
            DB::table('g2_conciliacion_grupos')->insert(['id' => $group, 'request_key' => $key, 'id_periodo' => $period->id_periodo, 'estado' => 'activo', 'metodo' => $method, 'monto' => $total / 100,
                'usuario_id' => $actor, 'seleccion' => json_encode($selection, JSON_THROW_ON_ERROR), 'nota' => $note, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($aliases as [$type,$id]) {
                DB::table('g2_conciliacion_reservas')->insert(['origen' => $type, 'origen_id' => $id, 'grupo_id' => $group]);
            }
            foreach ($banks as $bank) {
                DB::table('g2_conciliacion_reservas')->insert(['origen' => 'banco', 'origen_id' => $bank->id_movimiento_bancario, 'grupo_id' => $group]);
            }
            $index = 0;
            $left = $sources[0]['centavos'];
            foreach ($banks as $bank) {
                $remaining = BankMoney::cents($bank->monto);
                while ($remaining > 0) {
                    $take = min($left, $remaining);
                    $source = $sources[$index];
                    ConciliacionDetalle::create(['id_movimiento_bancario' => $bank->id_movimiento_bancario, self::FIELDS[$source['origen']] => $source['id'],
                        'id_grupo' => $group, 'monto_aplicado' => $take / 100, 'fecha_conciliacion' => now(), 'metodo' => $method, 'id_usuario' => $actor]);
                    $remaining -= $take;
                    $left -= $take;
                    if ($left === 0 && isset($sources[$index + 1])) {
                        $left = $sources[++$index]['centavos'];
                    }
                }
                $bank->estado_conciliacion = 'matcheado';
                $bank->save();
            }
            foreach ($sources as $source) {
                if ($source['origen'] === 'cheque') {
                    DB::table('CHEQUE')->where('id_cheque', $source['id'])->update(['estado' => 'conciliado']);
                }
            }
            G2OperationAudit::record('bancos', $group, 'conciliado', $actor, ['seleccion' => $selection, 'monto' => $total / 100, 'metodo' => $method]);

            return ['id' => $group, 'monto' => $total / 100, 'repetido' => false];
        });
    }

    public function proposals(int $period): array
    {
        $sources = $this->sources();
        $proposals = [];
        $review = [];
        foreach (MovimientoBancario::where('id_periodo', $period)->whereIn('estado_conciliacion', ['pendiente', 'revision_manual'])->get() as $bank) {
            $matches = array_values(array_filter($sources, function ($source) use ($bank) {
                $reference = $bank->nro_comprobante_extraido && $source['referencia'] && (string) $bank->nro_comprobante_extraido === (string) $source['referencia'];
                $cuit = $bank->cuit_extraido && $source['cuit'] && preg_replace('/\D/', '', $bank->cuit_extraido) === preg_replace('/\D/', '', $source['cuit']);

                return $source['signo'] === $bank->signo_monto && $source['centavos'] === BankMoney::cents($bank->monto)
                    && abs(Carbon::parse($source['fecha'])->diffInDays($bank->fecha_operacion, false)) <= 2 && ($reference || $cuit);
            }));
            $entry = ['id_movimiento_bancario' => $bank->id_movimiento_bancario, 'candidatos' => array_map(fn ($s) => collect($s)->except('aliases')->all(), $matches)];
            if (count($matches) === 1) {
                $proposals[] = $entry;
            } else {
                $review[] = $entry;
            }
        }
        $uses = [];
        foreach ($proposals as $entry) {
            $candidate = $entry['candidatos'][0];
            $key = $candidate['origen'].':'.$candidate['id'];
            $uses[$key] = ($uses[$key] ?? 0) + 1;
        }
        $unique = [];
        foreach ($proposals as $entry) {
            $candidate = $entry['candidatos'][0];
            if ($uses[$candidate['origen'].':'.$candidate['id']] === 1) {
                $unique[] = $entry;
            } else {
                $review[] = $entry;
            }
        }

        return ['alta_confianza' => $unique, 'para_revisar' => $review];
    }

    public function automatic(int $period, int $actor): array
    {
        return DB::transaction(function () use ($period, $actor) {
            $this->periods->actor($actor);
            DB::table('usuarios')->orderBy('id')->lockForUpdate()->first();
            $this->periods->open($period);
            $matches = [];
            foreach ($this->proposals($period)['alta_confianza'] as $entry) {
                $source = $entry['candidatos'][0];
                // Recalcular disponibilidad después de cada aplicación; dos bancos no reutilizan un origen.
                if (! $this->available($this->source($source['origen'], $source['id']))) {
                    continue;
                }
                $matches[] = $this->reconcile([$entry['id_movimiento_bancario']], [['origen' => $source['origen'], 'id' => $source['id']]], (string) Str::uuid(), $actor, 'automatico');
            }

            return ['conciliados_automaticamente' => count($matches), 'detalle_conciliados' => $matches, ...$this->proposals($period)];
        });
    }

    public function reverse(string $id, int $actor, string $reason): array
    {
        $this->periods->actor($actor);
        abort_if(mb_strlen(trim($reason)) < 3, 422, 'Indicá un motivo.');

        return DB::transaction(function () use ($id, $actor, $reason) {
            DB::table('usuarios')->orderBy('id')->lockForUpdate()->first();
            $group = DB::table('g2_conciliacion_grupos')->where('id', $id)->lockForUpdate()->firstOrFail();
            $this->periods->open($group->id_periodo);
            if ($group->estado === 'revertido') {
                return ['id' => $id, 'repetido' => true];
            }
            $details = ConciliacionDetalle::where('id_grupo', $id)->get();
            foreach ($details->pluck('id_movimiento_bancario')->unique() as $bankId) {
                $bank = MovimientoBancario::findOrFail($bankId);
                $bank->estado_conciliacion = 'pendiente';
                $bank->save();
            }
            foreach ($details->pluck('id_cheque')->filter()->unique() as $checkId) {
                DB::table('CHEQUE')->where('id_cheque', $checkId)->update(['estado' => 'depositado']);
            }
            G2OperationAudit::record('bancos', $id, 'conciliacion_revertida', $actor, ['motivo' => $reason, 'detalles' => $details->toArray()]);
            ConciliacionDetalle::where('id_grupo', $id)->delete();
            DB::table('g2_conciliacion_reservas')->where('grupo_id', $id)->delete();
            DB::table('g2_conciliacion_grupos')->where('id', $id)->update(['estado' => 'revertido', 'updated_at' => now()]);
            $collections = DB::table('CHEQUE')->whereIn('id_cheque', $details->pluck('id_cheque')->filter())->pluck('id_cobro');
            foreach (DB::table('cobro_venta')->whereIn('id_cobro', $collections)->pluck('id_venta')->unique()->sort() as $saleId) {
                app(SalePaymentEvidence::class)->refreshAfterReversal(
                    (int) $saleId, $actor, 'Reversión conciliación '.$id.': '.$reason);
            }

            return ['id' => $id, 'repetido' => false];
        });
    }

    public function classify(int $id, int $actor, string $action, string $note): array
    {
        $this->periods->actor($actor);
        abort_if(mb_strlen(trim($note)) < 3, 422, 'Indicá un motivo.');

        return DB::transaction(function () use ($id, $actor, $action, $note) {
            DB::table('usuarios')->orderBy('id')->lockForUpdate()->first();
            $bank = MovimientoBancario::lockForUpdate()->findOrFail($id);
            $this->periods->open((int) $bank->id_periodo);
            abort_if(ConciliacionDetalle::where('id_movimiento_bancario', $id)->exists(), 409, 'Primero debe revertirse la conciliación.');
            if ($action === 'gasto') {
                abort_unless($bank->signo_monto === 'egreso' && in_array($bank->tipo_movimiento, ['gasto_bancario', 'impuesto_bancario'], true), 422, 'No es un gasto o impuesto bancario de débito.');
                $existing = DB::table('AJUSTE_BANCARIO')->where('id_movimiento_bancario', $id)->first();
                $adjustment = $existing?->id_ajuste ?? DB::table('AJUSTE_BANCARIO')->insertGetId(['id_movimiento_bancario' => $id, 'categoria' => $bank->tipo_movimiento, 'observacion' => $note, 'monto' => $bank->monto, 'fecha_registro' => now(), 'id_usuario' => $actor]);
                $bank->estado_conciliacion = 'pendiente';
                $bank->save();

                return $this->reconcile([$id], [['origen' => 'ajuste', 'id' => (int) $adjustment]], (string) Str::uuid(), $actor, 'manual', $note);
            }
            abort_unless(in_array($action, ['disputar', 'resolver'], true), 422, 'Acción desconocida.');
            abort_if($action === 'resolver' && $bank->estado_conciliacion !== 'en_disputa', 409, 'El movimiento no está en disputa.');
            $bank->estado_conciliacion = $action === 'disputar' ? 'en_disputa' : 'revision_manual';
            $bank->save();
            G2OperationAudit::record('bancos', $id, $action, $actor, ['nota' => $note]);

            return $bank->toArray();
        });
    }

    public function close(int $id, int $actor): array
    {
        $this->periods->actor($actor);

        return DB::transaction(function () use ($id, $actor) {
            DB::table('usuarios')->orderBy('id')->lockForUpdate()->first();
            $period = $this->periods->open($id);
            abort_if(MovimientoBancario::where('id_periodo', $id)->whereNotIn('estado_conciliacion', ['matcheado', 'no_aplica'])->exists(), 409, 'Hay movimientos pendientes, en revisión o en disputa.');
            $period->estado = 'cerrado';
            $period->fecha_cierre = now();
            $period->save();
            G2OperationAudit::record('bancos', $id, 'periodo_cerrado', $actor, []);

            return $period->toArray();
        });
    }
}
