<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Entregas\Controllers\EntregaController;
use App\Modules\ConciliacionBancaria\Controllers\ConciliacionController;
use App\Modules\ConciliacionBancaria\Controllers\ChequeController;
use App\Modules\Caja\Controllers\CajaController;
use App\Modules\Rendiciones\Controllers\RendicionController;
use App\Modules\Ventas\Controllers\VentasProxyController;

Route::prefix('v1')->group(function () {
    // --- VENTAS (Consumo API Grupo 4) ---
    Route::get('/ventas/remotas', [VentasProxyController::class, 'obtenerVentasRemotas']);
    Route::get('/mock/grupo4/ventas', [VentasProxyController::class, 'mockGrupo4Ventas']);
    Route::get('/mock/grupo4/ventas/{id}', [VentasProxyController::class, 'mockGrupo4VentaDetalle']);
    Route::patch('/mock/grupo4/ventas/{id}/estado', [VentasProxyController::class, 'mockGrupo4ActualizarEstado']);

    // --- CONCILIACIÓN BANCARIA (Grupo 2 - Sofía) ---
    Route::get('/conciliacion', [ConciliacionController::class, 'index']);
    Route::get('/conciliacion/{id}', [ConciliacionController::class, 'show']);
    Route::post('/conciliacion', [ConciliacionController::class, 'store']);
    Route::patch('/conciliacion/{id}/conciliar', [ConciliacionController::class, 'conciliarAutomatico']);
    Route::post('/conciliacion/movimientos/{id}/conciliar-manual', [ConciliacionController::class, 'conciliarManual']);
    Route::patch('/conciliacion/{id}/cerrar', [ConciliacionController::class, 'cerrar']);

    // --- CHEQUES ---
    Route::get('/cheques', [ChequeController::class, 'index']);
    Route::get('/cheques/{id}', [ChequeController::class, 'show']);
    Route::patch('/cheques/{id}/depositar', [ChequeController::class, 'depositar']);
    Route::patch('/cheques/{id}/rechazar', [ChequeController::class, 'rechazar']);

    // --- CAJA (Grupo 2 - Sofía) ---
    // OJO con el orden: las rutas más específicas van antes de /caja/{fecha},
    // si no Laravel interpreta "cierres" o "movimiento" como un valor de {fecha}.
    Route::get('/caja', [CajaController::class, 'listar']);
    Route::post('/caja/apertura', [CajaController::class, 'abrir']);
    Route::get('/caja/cierres', [CajaController::class, 'historialCierres']);
    Route::get('/caja/cierres/{id}', [CajaController::class, 'detalleCierre']);
    Route::post('/caja/movimiento', [CajaController::class, 'registrarMovimiento']);
    Route::get('/caja/movimiento/{id}', [CajaController::class, 'detalleMovimiento']);
    Route::patch('/caja/movimiento/{id}', [CajaController::class, 'modificarMovimiento']);
    Route::patch('/caja/{id}/cerrar', [CajaController::class, 'cerrar']);
    Route::get('/caja/{fecha}/{id_usuario}', [CajaController::class, 'porUsuarioYFecha']);
    Route::get('/caja/{fecha}', [CajaController::class, 'porFecha']);

    // --- ENTREGAS (Grupo 2 - Estefanía Gianovich) ---
    Route::get('/entregas/pendientes-despacho', [EntregaController::class, 'pedidosPendientes']);
    Route::get('/entregas/pendientes-rendicion', [EntregaController::class, 'entregasPendientesRendicion']);
    Route::post('/entregas', [EntregaController::class, 'crearEntrega']);
    Route::get('/entregas', [EntregaController::class, 'listar']);
    Route::get('/entregas/{id}', [EntregaController::class, 'detalle']);
    Route::patch('/entregas/{id}/pedidos/{idVenta}/entregado', [EntregaController::class, 'confirmarPedidoEntregado']);
    Route::post('/entregas/confirmar-pedido-entregado', [EntregaController::class, 'confirmarPedidoEntregado']);
    Route::get('/remitos/{id}', [EntregaController::class, 'verRemito']);
    Route::get('/zonas', [EntregaController::class, 'zonas']);
    Route::get('/repartidores', [EntregaController::class, 'repartidores']);

    // --- RENDICIONES (Grupo 2 - Nicolás) ---
    Route::get('/rendiciones', [RendicionController::class, 'index']);
    Route::get('/rendiciones/historial', [RendicionController::class, 'index']);
    Route::get('/rendiciones/preparar/{idEntrega}', [RendicionController::class, 'preparar']);
    Route::get('/rendiciones/{id}', [RendicionController::class, 'show']);
    Route::post('/rendiciones', [RendicionController::class, 'store']);
    Route::post('/rendiciones/cobros', [RendicionController::class, 'agregarCobroDirecto']);
    Route::post('/rendiciones/{id}/cobros', [RendicionController::class, 'agregarCobro']);
    Route::post('/rendiciones/devoluciones', [RendicionController::class, 'registrarDevolucionDirecta']);
    Route::post('/rendiciones/{id}/devoluciones', [RendicionController::class, 'registrarDevolucion']);
    Route::post('/rendiciones/diferencias', [RendicionController::class, 'registrarDiferenciaDirecta']);
    Route::post('/rendiciones/{id}/diferencias', [RendicionController::class, 'registrarDiferencia']);
    Route::post('/rendiciones/validar', [RendicionController::class, 'validarDirecta']);
    Route::patch('/rendiciones/{id}/cerrar', [RendicionController::class, 'cerrar']);
    Route::patch('/rendiciones/{id}/revisar', [RendicionController::class, 'revisar']);
});

