<?php

namespace App\Modules\ConciliacionBancaria\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class BankColumnMapper
{
    public function __construct(private BankStatementParserService $parser) {}

    public function parsear(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'xls' => $this->xls($path), 'txt' => $this->txt($path),
            default => throw new InvalidArgumentException('Sólo se admiten extractos XLS o TXT.'),
        };
    }

    private function xls(string $path): array
    {
        $reader = new Xls;
        if (! $reader->canRead($path)) {
            throw new InvalidArgumentException('El contenido no es un archivo XLS válido.');
        }
        $book = $reader->load($path);
        try {
            $rows = $book->getActiveSheet()->toArray(null, true, false, false);
            $map = null;
            $moves = [];
            $errors = [];
            foreach ($rows as $index => $row) {
                if (! $map) {
                    $headers = array_map(fn ($v) => mb_strtolower($this->parser->normalizarDescripcion((string) $v)), $row);
                    if (in_array('fecha', $headers, true) && in_array('importe', $headers, true)) {
                        $map = ['date' => array_search('fecha', $headers, true), 'amount' => array_search('importe', $headers, true),
                            'description' => array_search('descripcion', $headers, true), 'balance' => array_search('saldo', $headers, true)];
                        if ($map['description'] === false) {
                            throw new InvalidArgumentException('Falta la columna Descripción.');
                        }
                    }

                    continue;
                }
                if (implode('', array_map('strval', $row)) === '') {
                    continue;
                }
                if (preg_match('/esta informaci[oó]n|supeditada a los ajustes/iu', implode(' ', array_map('strval', $row)))) {
                    break;
                }
                try {
                    $date = $row[$map['date']] ?? null;
                    $date = is_numeric($date) ? $this->date(Date::excelToDateTimeObject((float) $date)->format('Y-m-d')) : $this->date((string) $date);
                    $cents = BankMoney::cents($row[$map['amount']] ?? '');
                    $balance = $map['balance'] !== false && ($row[$map['balance']] ?? '') !== '' && $row[$map['balance']] !== null ? BankMoney::cents($row[$map['balance']]) : null;
                    $move = $this->movement($index + 1, $date, $cents, (string) ($row[$map['description']] ?? ''));
                    $move['_balance'] = $balance;
                    $moves[] = $move;
                } catch (\Throwable $error) {
                    $errors[] = 'Fila '.($index + 1).': '.$error->getMessage();
                }
            }
            if (! $map) {
                throw new InvalidArgumentException('No se encontró una cabecera Fecha / Descripción / Importe.');
            }

            return $this->result($moves, $errors);
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function txt(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new InvalidArgumentException('No se pudo leer el extracto.');
        }
        $moves = [];
        $errors = [];
        foreach ($lines as $index => $line) {
            if (trim($line) === '' || in_array(substr($line, 0, 1), ['H', 'T'], true)) {
                continue;
            }
            try {
                if (substr($line, 0, 1) !== 'D' || strlen($line) < 176) {
                    throw new InvalidArgumentException('Registro TXT desconocido o truncado.');
                }
                $date = $this->date(trim(substr($line, 7, 10)));
                $sign = substr($line, 158, 1);
                $raw = trim(substr($line, 159, 17));
                if (! in_array($sign, ['+', '-'], true) || ! preg_match('/^\d+$/D', $raw)) {
                    throw new InvalidArgumentException('Signo o centavos inválidos.');
                }
                if (strlen(ltrim($raw, '0')) > 12) {
                    throw new InvalidArgumentException('Importe fuera de rango.');
                }
                $cents = (int) $raw * ($sign === '-' ? -1 : 1);
                $description = trim(substr($line, 17, 80));
                if (! mb_check_encoding($description, 'UTF-8')) {
                    $description = mb_convert_encoding($description, 'UTF-8', 'Windows-1252');
                }
                $move = $this->movement($index + 1, $date, $cents, $description);
                $move['nro_comprobante_extraido'] = trim(substr($line, 97, 20)) ?: $move['nro_comprobante_extraido'];
                $move['cuit_extraido'] ??= $this->parser->extraerCuit(substr($line, 130, 23));
                $balance = trim(substr($line, 176));
                if ($balance !== '' && ! preg_match('/^[+-]?\d+$/D', $balance)) {
                    throw new InvalidArgumentException('Saldo en centavos inválido.');
                }
                $move['_balance'] = $balance === '' ? null : (int) $balance;
                $moves[] = $move;
            } catch (\Throwable $error) {
                $errors[] = 'Línea '.($index + 1).': '.$error->getMessage();
            }
        }

        return $this->result($moves, $errors);
    }

    private function date(string $value): string
    {
        $value = mb_strtolower(trim(str_replace('/', '-', $value)));
        $months = ['ene' => 1, 'enero' => 1, 'feb' => 2, 'febrero' => 2, 'mar' => 3, 'marzo' => 3, 'abr' => 4, 'abril' => 4, 'may' => 5, 'mayo' => 5, 'jun' => 6, 'junio' => 6, 'jul' => 7, 'julio' => 7, 'ago' => 8, 'agosto' => 8, 'sep' => 9, 'sept' => 9, 'set' => 9, 'septiembre' => 9, 'oct' => 10, 'octubre' => 10, 'nov' => 11, 'noviembre' => 11, 'dic' => 12, 'diciembre' => 12];
        $parts = explode('-', $value);
        if (count($parts) === 3 && isset($months[$parts[1]])) {
            $value = sprintf('%02d-%02d-%04d', (int) $parts[0], $months[$parts[1]], (int) $parts[2]);
        }
        foreach (['!d-m-Y', '!Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }
        throw new InvalidArgumentException('Fecha inválida.');
    }

    private function movement(int $row, string $date, int $cents, string $description): array
    {
        if ($cents === 0 || abs($cents) > 999999999999 || trim($description) === '') {
            throw new InvalidArgumentException('Importe cero, fuera de rango o descripción vacía.');
        }
        $normal = $this->parser->normalizarDescripcion($description);
        $sign = $cents < 0 ? 'egreso' : 'ingreso';

        return ['nro_fila' => $row, 'fecha_operacion' => $date, 'monto' => abs($cents) / 100, 'signo_monto' => $sign,
            'descripcion_original' => $description, 'descripcion_normalizada' => mb_substr($normal, 0, 255),
            'nro_comprobante_extraido' => $this->parser->extraerComprobante($description), 'cuit_extraido' => $this->parser->extraerCuit($description),
            ...$this->parser->clasificar($normal, $cents / 100, $sign)];
    }

    private function result(array $moves, array $errors): array
    {
        if (! $moves && ! $errors) {
            throw new InvalidArgumentException('El archivo no contiene movimientos reconocibles.');
        }
        $dates = array_column($moves, 'fecha_operacion');
        $balances = array_column($moves, '_balance');
        $ordered = $dates;
        sort($ordered);
        $start = $end = null;
        if ($moves && $dates === $ordered && ! in_array(null, $balances, true)) {
            $first = $moves[0];
            $signed = (int) round($first['monto'] * 100) * ($first['signo_monto'] === 'egreso' ? -1 : 1);
            $start = ($balances[0] - $signed) / 100;
            $end = end($balances) / 100;
        }
        foreach ($moves as &$move) {
            unset($move['_balance']);
        }

        return ['saldo_inicial' => $start, 'saldo_final' => $end, 'fecha_desde' => $dates ? min($dates) : null,
            'fecha_hasta' => $dates ? max($dates) : null, 'movimientos' => $moves, 'errores' => $errors];
    }
}
