<?php

namespace App\Modules\Ventas\Middleware;

use App\Modules\Login\Middleware\AuthenticateApiSessionRequest;

/** Conserva el punto de entrada de Ventas usando la autenticación común. */
class AutenticarApiVentas extends AuthenticateApiSessionRequest {}
