<?php

namespace App\Modules\Facturacion\Contracts;

/** Futuro adaptador: la emisión debe obtener una autorización real, nunca generar un CAE local. */
interface FiscalIssuer
{
    public function issue(array $document): array;
}
