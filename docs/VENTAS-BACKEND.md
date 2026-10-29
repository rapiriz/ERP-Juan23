# Módulo Ventas — Backend de Modificar, Anular y Estados

> **Para:** todo el equipo G4.
> **Qué es:** el backend de los botones **Modificar** y **Eliminar** del historial de ventas, más el cambio de **fases** de una venta (preparada, entregada, pagada…).
> **Estado:** ✅ 272 tests automáticos en verde · ✅ probado a mano con `php artisan serve` · ⚠ falta correr 2 migraciones en MySQL (ver [Pendientes](#9-pendientes-y-cosas-a-coordinar)).

---

## Índice

1. [Resumen en 1 minuto](#1-resumen-en-1-minuto)
2. [Qué hicimos, paso a paso](#2-qué-hicimos-paso-a-paso)
3. [Estados de una venta](#3-estados-de-una-venta)
4. [Endpoints](#4-endpoints)
5. [Errores y excepciones](#5-errores-y-excepciones)
6. [Anular y restaurar (cuenta corriente)](#6-anular-y-restaurar-cuenta-corriente)
7. [Mapa: qué parte del back hace cada cosa](#7-mapa-qué-parte-del-back-hace-cada-cosa)
8. [Archivos nuevos y modificados](#8-archivos-nuevos-y-modificados)
9. [Pendientes y cosas a coordinar](#9-pendientes-y-cosas-a-coordinar)
10. [Cómo probarlo vos mismo](#10-cómo-probarlo-vos-mismo)

---

## 1. Resumen en 1 minuto

- Una venta pasa por **fases**: `pendiente → confirmada → preparada → entregada → pagada → facturada`.
- **Antes de `pagada`** la venta se puede **modificar** y **eliminar**. **Desde `pagada`** ya no.
- **Eliminar = anular**: la venta **no se borra**, queda `cancelada` (el front la muestra en gris) y se puede **restaurar**.
- El backend le dice al front qué botones mostrar con 3 flags: `puede_modificar`, `puede_eliminar` y `puede_restaurar`. El front **no decide** nada: solo lee los flags.
- Aunque el front mostrara un botón por error, el back **igual lo bloquea** (responde `409`).
- Productos, clientes y stock siguen siendo **mocks**. El código para las APIs reales ya está escrito y testeado, pero apagado (ver [paso 3](#paso-3--acá-dejamos-lista-la-conexión-con-otros-módulos-productos-clientes-stock)).
- **No es un microservicio**: modificar una venta y su cuenta corriente tiene que pasar en una sola transacción, y separarlo complicaría todo sin ganar nada. Igual quedó preparado para separarse (contratos e interruptor mock/http).

---

## 2. Qué hicimos, paso a paso

Cada paso se probó antes de pasar al siguiente.

### Paso 1 — Acá integramos la lógica del back que define los estados y sus reglas

- `app/Modules/Ventas/Enums/EstadoVenta.php`: los 7 estados y **todas** las reglas (qué se puede modificar, anular, restaurar, a qué estado se puede pasar).
- `app/Modules/Ventas/Exceptions/*`: los errores de negocio (404, 409, 503) con su respuesta JSON.
- ✅ Tests: `tests/Unit/Ventas/EstadoVentaTest.php`. Prueba las **49 combinaciones** de transición (7 × 7).

### Paso 2 — Acá integramos la calculadora del total (la misma cuenta para crear y modificar)

- `app/Modules/Ventas/Services/CalculadoraVenta.php`: calcula subtotales, descuentos y total con los **precios del servidor**. Usa las mismas reglas que `VentaController@store`.
- ✅ Tests: `tests/Unit/Ventas/CalculadoraVentaTest.php` (descuentos, redondeo, límites).

### Paso 3 — Acá dejamos lista la conexión con otros módulos (productos, clientes, stock)

- `app/Modules/Ventas/Contracts/*`: **contratos** (interfaces). Ventas pide productos, clientes y stock sin saber de dónde vienen.
- Los **mocks actuales siguen activos**. Solo se les agregó `implements`, así que el front se ve igual que antes.
- `app/Modules/Ventas/Integraciones/Http*Api.php`: el **código real** para cuando los otros grupos publiquen sus APIs. Está **testeado con respuestas simuladas** (`Http::fake`), pero apagado.
- Interruptor en `.env`: `VENTAS_INTEGRACION=mock` (por defecto) o `http`. Ver `config/ventas.php`.
- ✅ Tests: `tests/Unit/Integraciones/*` (formato, 404, servidor caído → 503, el interruptor).

### Paso 4 — Acá agregamos el historial de cambios de cada venta

- Tabla nueva **`venta_historial`**: guarda quién, qué, cuándo y por qué. Es la que permite **restaurar** una venta al estado que tenía antes de anularla.
- Modelo `app/Modules/Ventas/Models/VentaHistorial.php`. Al modelo `Venta` se le agregó la relación `historial()` y el método `estadoActual()`.

### Paso 5 — Acá registramos la lógica del back de los botones Modificar y Eliminar (y de los cambios de estado)

- `app/Modules/Ventas/Services/VentaService.php`: **el cerebro**. Ahí están modificar, anular, restaurar y cambiar estado, todo en transacción y con la venta bloqueada mientras se cambia.
- `app/Modules/Ventas/Controllers/VentaApiController.php`: recibe el pedido, llama al servicio y responde.
- `app/Modules/Ventas/Requests/*`: validan los datos antes de llegar al controller (si están mal → `422`).
- `app/Modules/Ventas/Resources/VentaResource.php`: el formato JSON de una venta (igual en todas las rutas).
- `app/Modules/Ventas/routes/api.php`: rutas nuevas, y se **arregló `GET /api/ventas`**, que devolvía HTML por error. Ninguna pantalla lo usaba, así que no rompe nada.
- `VentaLogger`: ahora deja el **paso a paso** de cada operación en `storage/logs/ventas/`.
- ✅ Tests: `tests/Feature/Ventas/*` (cada endpoint con casos buenos, 404, 409, 422, 500 con rollback, 503 y log).

### Paso 6 — Acá conectamos los botones con el historial de ventas

- `VentaController@historial` (pantalla `/historial-ventas`) ahora manda en cada venta: `anulada`, `puede_modificar`, `puede_eliminar` y `puede_restaurar`. Es solo un agregado: lo que ya había no cambió.
- ✅ Tests: `tests/Feature/Ventas/HistorialYFlujoCompletoTest.php`, que además recorre la **vida completa** de una venta: alta → modificar → anular → restaurar → fases → pagada (bloqueada) → facturada.

### Paso 7 — Acá verificamos que no se rompió nada

- Suite completa: **272 tests en verde** (antes: 51 en verde y 14 rotos).
- Los 14 tests viejos de `tests/Feature/VentasApiTest.php` se adaptaron al formato nuevo.
- Se rompió la regla **a propósito** para confirmar que los tests lo detectan. Al permitir modificar una venta pagada fallan 10 tests; al sacar la transacción falla el test de rollback. Después se restauró el código.
- Prueba manual con `php artisan serve` de todos los endpoints y las pantallas `/`, `/ventas`, `/historial-ventas`, `/saldo` y `/promociones`: todas responden `200`.

---

## 3. Estados de una venta

Fuente única de verdad: **`app/Modules/Ventas/Enums/EstadoVenta.php`**.

```
pendiente → confirmada → preparada → entregada → pagada → facturada
     └──────────┴────────────┴───────────┴──→ cancelada (anulada)
```

| Estado | ¿Modificar? | ¿Eliminar (anular)? | ¿Restaurar? | Puede pasar a (PATCH) |
|---|:-:|:-:|:-:|---|
| `pendiente` | ✅ | ✅ | — | confirmada, preparada, entregada, pagada |
| `confirmada` | ✅ | ✅ | — | preparada, entregada, pagada |
| `preparada` | ✅ | ✅ | — | entregada, pagada |
| `entregada` | ✅ | ✅ | — | pagada |
| `pagada` | ❌ 409 | ❌ 409 | — | facturada |
| `facturada` | ❌ 409 | ❌ 409 | — | — (final) |
| `cancelada` | ❌ 409 | ❌ 409 | ✅ | — (solo se restaura) |

**Reglas de los cambios de estado:**
- Siempre **hacia adelante**; nunca se vuelve atrás.
- Se pueden **saltear** fases (ejemplo: cobrar en el mostrador, `confirmada → pagada`).
- `facturada` solo se alcanza desde `pagada`.
- A `cancelada` **no** se pasa con PATCH. Se usa `DELETE`, que pide motivo y corrige la cuenta corriente.
- Cambiar de fase **no** toca la cuenta corriente ni el stock.

> Las ventas que hoy crea el POS (`VentaController@store`) nacen en `confirmada`.
> El paso a `pagada` lo va a hacer el grupo de **Rendiciones** cuando se integre, llamando a `PATCH /api/ventas/{id}/estado`.

---

## 4. Endpoints

Todos en `app/Modules/Ventas/routes/api.php` y atendidos por `app/Modules/Ventas/Controllers/VentaApiController.php`.

| Método | Ruta | Para qué | Body / filtros | Respuestas |
|---|---|---|---|---|
| `GET` | `/api/ventas` | Lista paginada, la más nueva primero | `?estado=&cliente_id=&desde=AAAA-MM-DD&hasta=&texto=&por_pagina=(1-100)&page=` | 200 · 422 |
| `GET` | `/api/ventas/estados` | Catálogo de estados con sus reglas | — | 200 |
| `GET` | `/api/ventas/productos` | Catálogo para "Agregar producto" en el editor de Modificar (sale de `ProductoGateway`: hoy mock) | — | 200 · 503 |
| `GET` | `/api/ventas/{id}` | Una venta completa: detalle, botones e historial | — | 200 · 404 |
| `PUT` | `/api/ventas/{id}` | **Botón Modificar** | ver abajo | 200 · 404 · 409 · 422 · 500 · 503 |
| `DELETE` | `/api/ventas/{id}` | **Botón Eliminar** (anula, no borra) | `{"motivo": "5 a 150 caracteres"}` | 200 · 404 · 409 · 422 · 500 |
| `POST` | `/api/ventas/{id}/restaurar` | Deshace una anulación | — | 200 · 404 · 409 · 500 |
| `PATCH` | `/api/ventas/{id}/estado` | Avanza de fase | `{"estado": "pagada", "motivo": "opcional"}` | 200 · 404 · 409 · 422 · 500 |

Sin cambios: `POST /api/ventas` y `POST /ventas` (alta, `VentaController@store`) y `GET /api/ventas/productos-simulados` (lo usa `promociones.js`).

### Body de `PUT /api/ventas/{id}` (mismo formato que el alta, sin cliente)

```json
{
  "lista": "minorista",
  "observaciones": "Entregar el lunes",
  "descuento_global": { "modo": "porcentaje", "valor": 10 },
  "items": [ { "id": 2, "cantidad": 3, "descuento": 100 } ]
}
```

- **No se puede cambiar:** el cliente (si se manda `cliente_id` → `422` con mensaje claro), el número de comprobante ni la fecha.
- Los **precios salen del servidor**: si el front manda precios o totales, se ignoran.
- Si no se mandan `observaciones`, se mantienen las que había.

### Respuesta OK (las 4 operaciones que cambian datos)

```json
{
  "message": "Venta modificada correctamente.",
  "data": {
    "id": 1,
    "numero_comprobante": "POS-01M51EA96BQS2CVYTGS43F66T5",
    "fecha": "2026-10-16",
    "cliente": { "id": 915736, "nombre": "María González", "cuit": "27-98765432-1" },
    "usuario": { "id": 1, "nombre": "Administrador" },
    "estado": { "valor": "confirmada", "etiqueta": "Confirmada" },
    "anulada": false,
    "total": 5100, "descuento_global": 0, "observaciones": "Entregar el lunes",
    "cantidad_articulos": 3,
    "detalles": [ { "id_producto": 2, "codigo": "P002", "producto": "Pan lactal grande", "cantidad": 3, "precio_unitario": 1800, "descuento": 100, "subtotal": 5100 } ],
    "acciones": { "puede_modificar": true, "puede_eliminar": true, "puede_restaurar": false },
    "transiciones_posibles": ["preparada", "entregada", "pagada"],
    "historial": [ { "accion": "modificacion", "estado_anterior": "confirmada", "estado_nuevo": "confirmada", "motivo": null, "datos": { "total_anterior": 5000, "total_nuevo": 5100 }, "usuario": "Administrador", "fecha": "2026-10-16 04:07:56" } ]
  }
}
```

### Lo que el front tiene que hacer

| En pantalla | Qué mirar / llamar |
|---|---|
| Mostrar botón **Modificar** | `puede_modificar === true` |
| Mostrar botón **Eliminar** | `puede_eliminar === true` |
| Mostrar la venta **en gris** | `anulada === true` |
| Mostrar botón **Restaurar** | `puede_restaurar === true` |
| Al confirmar Eliminar | `DELETE /api/ventas/{id}` con `{"motivo": "..."}` (pedirlo en un modal) |
| Mostrar un error | Leer `message`. Para decidir qué hacer, usar `codigo` (`VENTA_NO_EDITABLE`, etc.) |

En `/historial-ventas` los flags ya vienen en cada `$v` (`$v['puede_modificar']`, etc.).

---

## 5. Errores y excepciones

En PHP todo error es un `Throwable`, que tiene dos ramas:
- **`Exception`**: problemas **esperables**, como datos mal enviados o una regla de negocio. Se responden con un código claro.
- **`Error`**: **bugs** de código, como `TypeError` o división por cero. Siempre terminan en `500`.

| Excepción | Cuándo pasa | HTTP | `codigo` en el JSON |
|---|---|:-:|---|
| `ValidationException` | Datos mal enviados (falta `lista`, cantidad 0, motivo corto…) | **422** | — (viene `errors` campo por campo) |
| `VentaNoEncontradaException` *(propia)* | La venta no existe | **404** | `VENTA_NO_ENCONTRADA` |
| `VentaNoEditableException` *(propia)* | Modificar o eliminar una venta pagada, facturada o anulada; restaurar una no anulada | **409** | `VENTA_NO_EDITABLE` |
| `TransicionEstadoInvalidaException` *(propia)* | Volver atrás, facturar sin pagar, anular por PATCH, mismo estado | **409** | `TRANSICION_INVALIDA` (+ `transiciones_posibles`) |
| `IntegracionNoDisponibleException` *(propia)* | Otro módulo (productos, clientes, stock) no responde. Solo con `VENTAS_INTEGRACION=http` | **503** | `INTEGRACION_NO_DISPONIBLE` |
| `QueryException` / `PDOException` | La base falla (tabla o columna inexistente, FK, conexión) | **500** | — Se **deshace todo** (rollback) |
| `RuntimeException`, `LogicException`, `TypeError`… | Cualquier otro error inesperado | **500** | — Se deshace todo y queda en el log |

- Las excepciones propias extienden **`DomainException`** (la excepción de PHP para "esto viola una regla del negocio") e implementan **`ShouldntReport`**, para que Laravel no llene `laravel.log` con errores esperables.
- En los `500`, el campo `detalle` (el error técnico) **solo** se muestra con `APP_DEBUG=true`. En producción no se le muestran errores internos al usuario.
- **Stock** es la excepción a la regla: si falla **después** de guardar la venta, la venta **no** se deshace. Queda escrito en el log para ajustar el stock a mano.

---

## 6. Anular y restaurar (cuenta corriente)

La tabla `cuenta_corriente_movimientos` es del módulo de cuenta corriente y tiene `venta_id` **único**: una venta tiene **un solo** movimiento. Para no tocar esa tabla, ese **único movimiento se actualiza**:

| Acción | Venta | Movimiento de cuenta corriente | Saldo del cliente |
|---|---|---|---|
| Alta (POS) | `confirmada` | `importe = -total`, `tipo = 'venta'` | Debe el total |
| **Modificar** | Igual estado, nuevo total | `importe = -nuevo total` | Debe el nuevo total |
| **Anular** | `cancelada` (en gris) | `importe = 0`, `tipo = 'venta_anulada'`, descripción `Venta POS #X (ANULADA)` | Vuelve a como estaba |
| **Restaurar** | Vuelve al estado anterior | `importe = -total`, `tipo = 'venta'` | Debe el total otra vez |

- En `/saldo` sigue viéndose que hubo una venta anulada (con `$0`).
- El estado anterior a la anulación se guarda en `venta_historial`. Si una venta se anuló por fuera del sistema y no tiene historial, al restaurarla vuelve a `pendiente`, que es lo más seguro.
- El **pago en cuenta corriente** (`/saldo`, `CuentaCorrienteController@registrarMovimiento`) **no** está atado a ninguna venta (`venta_id = null`): baja el saldo general del cliente. Por eso pagar ahí **no** pone una venta en `pagada`. Asignar pagos a ventas es tarea de Rendiciones.

---

## 7. Mapa: qué parte del back hace cada cosa

| Cosa (lo que ve o hace el usuario) | Ruta | Código del back que lo resuelve |
|---|---|---|
| Lista del historial (pantalla) | `GET /historial-ventas` | `VentaController@historial` (flags de `EstadoVenta::accionesPara`) |
| Lista de ventas (JSON) | `GET /api/ventas` | `VentaApiController@index` → `VentaService::listar` → `VentaResource` |
| Desplegar una venta | `GET /api/ventas/{id}` | `VentaApiController@show` → `VentaService::buscar` |
| Botón **Modificar** | `PUT /api/ventas/{id}` | `ModificarVentaRequest` → `VentaApiController@update` → `VentaService::modificar` → `CalculadoraVenta` |
| Botón **Eliminar** | `DELETE /api/ventas/{id}` | `AnularVentaRequest` → `VentaApiController@destroy` → `VentaService::anular` |
| Botón **Restaurar** | `POST /api/ventas/{id}/restaurar` | `VentaApiController@restaurar` → `VentaService::restaurar` |
| Cambiar de fase / **Cobrar** | `PATCH /api/ventas/{id}/estado` | `CambiarEstadoVentaRequest` → `VentaApiController@cambiarEstado` → `VentaService::cambiarEstado` |
| Qué botones se ven | — | `EstadoVenta::accionesPara()` / `permiteEdicion()` / `permiteAnulacion()` |
| "¿Quién cambió esto?" | — | Tabla `venta_historial` (modelo `VentaHistorial`) |
| Venta en gris | — | Flag `anulada` (estado `cancelada`) |
| Deuda del cliente | `/saldo` | `cuenta_corriente_movimientos`, actualizado por `VentaService::guardarMovimientoCuentaCorriente` |
| Avisar a Stock | — | `StockGateway` → hoy `MockStockApi`, mañana `HttpStockApi` |
| Paso a paso de cada operación | — | `VentaLogger` → `storage/logs/ventas/ventas-AAAA-MM-DD.log` |

---

## 8. Archivos nuevos y modificados

### Nuevos

| Archivo | Para qué sirve | Por qué es importante |
|---|---|---|
| `app/Modules/Ventas/Enums/EstadoVenta.php` | Los 7 estados y sus reglas | Es la **única** fuente de verdad. Cambiar una regla = cambiar un solo archivo. |
| `app/Modules/Ventas/Exceptions/VentaException.php` | Base de los errores de negocio | Todas responden JSON con `message` + `codigo` sin código extra en el controller. |
| `app/Modules/Ventas/Exceptions/VentaNoEncontradaException.php` | 404 | Mensaje claro en castellano en vez del genérico de Laravel. |
| `app/Modules/Ventas/Exceptions/VentaNoEditableException.php` | 409 | Bloquea modificar o eliminar después del pago **aunque el front se equivoque**. |
| `app/Modules/Ventas/Exceptions/TransicionEstadoInvalidaException.php` | 409 | Impide saltos de fase inválidos y dice cuáles **sí** se pueden hacer. |
| `app/Modules/Ventas/Exceptions/IntegracionNoDisponibleException.php` | 503 | Si otro módulo se cae, el usuario ve "probá en unos minutos" y no un error 500. |
| `app/Modules/Ventas/Services/CalculadoraVenta.php` | Cuenta del total | Crear y modificar calculan **exactamente igual**. Sin base de datos, fácil de testear. |
| `app/Modules/Ventas/Services/VentaService.php` | Lógica de modificar, anular, restaurar y cambiar estado | Transacción, bloqueo de la venta, historial, cuenta corriente y stock en un solo lugar. Rendiciones lo va a reutilizar. |
| `app/Modules/Ventas/Controllers/VentaApiController.php` | Atiende las rutas `/api/ventas` | Controller fino: recibe, delega y responde. Es **nuevo** para no tocar el `VentaController` del equipo. |
| `app/Modules/Ventas/Requests/VentaRequest.php` | Base de las validaciones | Si la validación falla, también queda en el log de ventas. |
| `app/Modules/Ventas/Requests/ModificarVentaRequest.php` | Valida el PUT | Mensajes en castellano; prohíbe cambiar el cliente. |
| `app/Modules/Ventas/Requests/AnularVentaRequest.php` | Valida el DELETE | El motivo es obligatorio: toda anulación tiene que poder explicarse. |
| `app/Modules/Ventas/Requests/CambiarEstadoVentaRequest.php` | Valida el PATCH | Solo acepta los 7 estados; tolera mayúsculas y espacios. |
| `app/Modules/Ventas/Requests/ListarVentasRequest.php` | Valida los filtros del GET | Fechas con formato correcto, `hasta ≥ desde`, máximo 100 por página. |
| `app/Modules/Ventas/Resources/VentaResource.php` | Formato JSON de una venta | Todas las rutas devuelven la venta **con la misma forma**, con los flags de los botones. |
| `app/Modules/Ventas/Contracts/ProductoGateway.php` | Contrato de productos | Ventas no depende de si los productos son mock o API real. |
| `app/Modules/Ventas/Contracts/ClienteGateway.php` | Contrato de clientes | Ídem para clientes. |
| `app/Modules/Ventas/Contracts/StockGateway.php` | Contrato de stock | Deja **apuntada la API de Stock** con la convención de signos (− sale, + vuelve). |
| `app/Modules/Ventas/Integraciones/MockStockApi.php` | Stock simulado (activo) | No descuenta nada real; los tests verifican qué se le avisó. |
| `app/Modules/Ventas/Integraciones/HttpProductoApi.php` | API real de productos (apagada) | Código listo y testeado para el día de la integración. |
| `app/Modules/Ventas/Integraciones/HttpClienteApi.php` | API real de clientes (apagada) | Ídem. |
| `app/Modules/Ventas/Integraciones/HttpStockApi.php` | API real de stock (apagada) | Ídem. |
| `app/Modules/Ventas/Models/VentaHistorial.php` | Modelo de `venta_historial` | Auditoría: quién, qué, cuándo y por qué. |
| `config/ventas.php` | Configuración del módulo | Interruptor mock/http, URLs de los otros módulos, timeout, paginación. |
| `app/Modules/Ventas/database/migrations/2026_10_16_000000_create_venta_historial_table.php` | Crea `venta_historial` | Tabla propia; no toca ninguna tabla existente. |
| `app/Modules/Ventas/database/migrations/2026_10_16_000001_agregar_estados_preparada_entregada_a_venta.php` | Agrega `preparada` y `entregada` al ENUM de MySQL | **Sin esto, MySQL rechaza esos estados** (ver pendientes). |
| `tests/Concerns/PreparaModuloVentas.php` | Ayuda compartida de tests | Crea las tablas `venta` y `detalle_venta` como en MySQL y ventas de prueba. |
| `tests/Unit/Ventas/*`, `tests/Unit/Integraciones/*`, `tests/Feature/Ventas/*` | Tests | Ver [paso 7](#paso-7--acá-verificamos-que-no-se-rompió-nada). |

### Modificados (solo agregados: lo que existía sigue igual)

| Archivo | Qué cambió |
|---|---|
| `app/Modules/Ventas/routes/api.php` (antes en `routes/api.php`) | `GET /api/ventas` ahora devuelve JSON (antes HTML por error) + 6 rutas nuevas. |
| `app/Modules/Ventas/Controllers/VentaController.php` | `historial()` agrega 4 flags a cada venta (3 líneas). |
| `app/Modules/Ventas/Models/Venta.php` | + relación `historial()` y método `estadoActual()`. |
| `app/Modules/Ventas/Logging/VentaLogger.php` | Sirve para todas las operaciones (título, controller y cantidad de pasos) + método `bloqueada()`. |
| `app/Providers/AppServiceProvider.php` | Interruptor mock/http de los contratos. |
| `app/Services/MockClienteApi.php`, `MockProductoApi.php` | Solo `implements ...Gateway`. Los datos no cambiaron. |
| `tests/Feature/VentasApiTest.php` | Los 14 tests que fallaban se adaptaron al formato nuevo. |

---

## 9. Pendientes y cosas a coordinar

| # | Tema | Qué pasa | Qué hacer |
|---|---|---|---|
| 1 | ⚠ **ENUM de MySQL** | En MySQL, `venta.estado` es `ENUM('pendiente','confirmada','pagada','facturada','cancelada')`: **no acepta `preparada` ni `entregada`**. | Coordinar y correr la migración `2026_10_16_000001_…` (comando abajo). Todos deberían hacerlo. |
| 2 | ⚠ **Tabla `venta_historial`** | Hay que crearla en MySQL. Sin ella, modificar, anular y cambiar estado dan `500` (con rollback, no rompe datos). | Correr la migración `2026_10_16_000000_…`. |
| 3 | `php artisan migrate` a secas falla | La tabla `cuenta_corriente_movimientos` ya existe en MySQL, pero su migración no figura como corrida. | Correr **solo** las migraciones nuevas con `--path` (abajo). |
| 4 | Montos sin centavos en MySQL | `total`, `precio_unitario` y `subtotal` son `decimal(10,0)`. Un descuento del 33,333 % se redondea al peso. | Decidir en equipo si se pasa a `decimal(10,2)`. |
| 5 | KPIs del historial | Los totales de arriba de `/historial-ventas` suman también las ventas **anuladas**. | Que el historial excluya `estado = cancelada` de los KPIs (código del historial). |
| 6 | Seguridad | Las rutas `/api/*` no tienen login: cualquiera que llegue al servidor puede anular. | Antes de producción, proteger con Sanctum. |
| 7 | Contratos supuestos | Las URLs y campos de `Http*Api` son **supuestos**. | Ajustarlos cuando Productos, Clientes y Stock publiquen sus APIs. |
| 8 | Alta sin log | `VentaController@store` (develop-g4) todavía no escribe el log paso a paso. | Opcional: sumarle `VentaLogger`. |
| 9 | Front | En `/historial-ventas` hay botones **de prueba sin estilos**: Modificar (editor para agregar, quitar y cambiar cantidades con total en vivo), Eliminar, Restaurar y Cambiar estado. Falta el diseño final, el modal de motivo y el gris. | Darles estilo usando los flags de la [sección 4](#lo-que-el-front-tiene-que-hacer). |

> Cada integrante tiene que correr las migraciones de los puntos 1 y 2 en su propio MySQL (una sola vez).

**Migraciones (solo estas dos, sin tocar otras):**

```bash
php artisan migrate --path=app/Modules/Ventas/database/migrations/2026_10_16_000000_create_venta_historial_table.php
php artisan migrate --path=app/Modules/Ventas/database/migrations/2026_10_16_000001_agregar_estados_preparada_entregada_a_venta.php
```

Ambas tienen vuelta atrás (`php artisan migrate:rollback --step=1`). La del ENUM, al deshacerse, pasa las ventas `preparada` o `entregada` a `confirmada`.

---

## 10. Cómo probarlo vos mismo

### A. Tests automáticos

```bash
php artisan test                         # todo (272 tests)
php artisan test tests/Unit/Ventas       # reglas de estados y calculadora
php artisan test tests/Feature/Ventas    # endpoints de punta a punta
php artisan test --filter=Modificar      # solo lo de modificar
```

### B. Prueba manual con Thunder Client / Postman (o curl)

1. Levantá el servidor: `php artisan serve`. Para las pruebas de 1 a 7 necesitás antes las migraciones de la [sección 9](#9-pendientes-y-cosas-a-coordinar).
2. En cada pedido poné el header **`Accept: application/json`**. Sin él, los errores vuelven como HTML.
3. Seguí este orden. Si cada paso responde lo esperado, está todo bien:

| # | Pedido | Esperado |
|---|---|---|
| 0 | `POST /api/ventas` `{"cliente_id":915736,"lista":"minorista","items":[{"id":1,"cantidad":2,"descuento":0}]}` | 201, `venta_id` |
| 1 | `GET /api/ventas` | 200, JSON con `data` y `meta` |
| 2 | `GET /api/ventas/{id}` | 200, `acciones.puede_modificar = true` |
| 3 | `PUT /api/ventas/{id}` `{"lista":"minorista","items":[{"id":2,"cantidad":3,"descuento":100}]}` | 200, `total = 5100`. En `/saldo` el cliente debe $5.100 |
| 4 | `PUT` igual pero con `"cliente_id": 1` | 422 "El cliente de una venta no se puede cambiar…" |
| 5 | `DELETE /api/ventas/{id}` sin body | 422 "Indicá el motivo de la anulación." |
| 6 | `DELETE /api/ventas/{id}` `{"motivo":"El cliente canceló"}` | 200, `anulada = true`. En `/saldo` la deuda desaparece |
| 7 | `POST /api/ventas/{id}/restaurar` | 200, vuelve a `confirmada`, la deuda vuelve |
| 8 | `PATCH /api/ventas/{id}/estado` `{"estado":"pagada"}` | 200 |
| 9 | `PUT` y `DELETE` de nuevo | **409** `VENTA_NO_EDITABLE` |
| 10 | `PATCH … {"estado":"confirmada"}` | **409** `TRANSICION_INVALIDA` con `transiciones_posibles: ["facturada"]` |
| 11 | `GET /api/ventas/999` | 404 `VENTA_NO_ENCONTRADA` |

4. Mirá el informe de cada operación en `storage/logs/ventas/ventas-AAAA-MM-DD.log`. Muestra los datos recibidos, cada paso con ✔ o ✘, en qué paso falló y la línea de nuestro código.

### C. Probar sin tocar tu MySQL (base descartable)

Si todavía no corriste las migraciones, podés usar una base SQLite temporal. Las variables de entorno pisan el `.env`:

```bash
touch /tmp/ventas.sqlite
DB_CONNECTION=sqlite DB_DATABASE=/tmp/ventas.sqlite php artisan migrate
# crear las tablas venta y detalle_venta (copiar la estructura de tests/Concerns/PreparaModuloVentas.php)
DB_CONNECTION=sqlite DB_DATABASE=/tmp/ventas.sqlite php artisan serve
```
