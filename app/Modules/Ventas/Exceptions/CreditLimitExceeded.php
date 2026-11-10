<?php

namespace App\Modules\Ventas\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class CreditLimitExceeded extends HttpException
{
    public function __construct(public readonly array $credit)
    {
        parent::__construct(422, 'El cliente supera su límite de crédito. Registrá un pago acreditado o revisá el límite antes de confirmar.');
    }
}
