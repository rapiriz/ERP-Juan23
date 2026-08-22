<?php
/**
 * Punto de entrada único del backend.
 * Acá se inicializa el Router y se despachan las peticiones a los Controllers
 * de cada módulo (Entregas, Facturacion, Caja, Cobros, ConciliacionBancaria, Rendiciones).
 */

require_once __DIR__ . '/../src/Shared/Http/Router.php';

// TODO: registrar rutas de cada módulo acá
