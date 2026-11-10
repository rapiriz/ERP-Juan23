<?php

use App\Modules\Facturacion\Controllers\FiscalStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/operaciones/fiscal', FiscalStatusController::class)->middleware(['auth', 'session.current', 'role:administrativo,contador'])->name('fiscal.status');
