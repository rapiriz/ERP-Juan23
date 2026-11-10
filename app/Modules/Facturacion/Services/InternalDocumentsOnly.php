<?php

namespace App\Modules\Facturacion\Services;

use App\Modules\Facturacion\Contracts\FiscalIssuer;

class InternalDocumentsOnly implements FiscalIssuer
{
    public function issue(array $document): array
    {
        throw new \DomainException('La emisión fiscal no está habilitada. El sistema genera únicamente comprobantes internos, sin CAE ni validez fiscal.');
    }
}
