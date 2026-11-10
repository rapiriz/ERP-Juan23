# Correcciones de consistencia operativa de G1, G2, G3 y G4

Fecha: 09/11/2026. Alcance: los ocho puntos de la tabla seleccionada después de la revisión final.

## 1. Alcance, autorización y respaldo

Se modificó código de G1, G2, G3 y G4 con la autorización vigente del usuario. Se conservaron las modificaciones locales de las etapas anteriores. No se hizo pull, merge, commit ni push; tampoco se incorporaron archivos de otras ramas.

Antes de editar se respaldaron los 622 archivos de código/documentación existentes y se registraron sus huellas SHA-256. Antes de actualizar la base habitual se generó un respaldo SQL y una huella de los registros de cada tabla. Los respaldos y las pruebas auxiliares están fuera de la raíz Git:

```text
../Entregables/Correcciones_Operaciones_2026-11-09/
```

Ese directorio contiene datos privados: no debe publicarse como parte del código. Los registros de prueba se crearon exclusivamente en una base MySQL aislada `erp_qa_g3_g4`, puerto `3315`, o en SQLite en memoria. No se agregaron productos, clientes, cobros ni ventas de demostración a `sistema_gestion` durante esta etapa.

## 2. Hallazgos y correcciones

| Prioridad | Hallazgo confirmado | Consecuencia anterior | Corrección |
|---|---|---|---|
| Alta | Ingreso de mercadería transaccionado por ítem | Un lote podía devolver `400` y conservar stock, movimientos y asociaciones del primer producto. | Una transacción abarca el lote completo y sus asociaciones. Se bloquean proveedor y productos; las validaciones se repiten sobre el estado vigente. Cualquier fallo revierte el lote. |
| Alta | Alta de venta sin referencia idempotente | Reenviar la operación creaba otra venta, otra deuda y otro descuento de stock. | Se reserva una UUID con usuario y huella del cuerpo; venta, deuda, stock y resultado se confirman juntos. El reenvío devuelve la misma venta. La misma UUID con otros datos o usuario devuelve `409`. |
| Alta | Pagos de compra: sólo se tomaba `pagos[0]` | Un envío por 70 y 30 respondía éxito habiendo registrado sólo 70. | Se valida y procesa cada pago del array en una sola transacción. Si la suma excede la deuda o falla un ítem, no queda ninguno. Se conserva el formato individual. |
| Alta | Edición y anulación de ventas asignadas a entregas | Podían diferir venta y remito, devolverse stock de un pedido en camino y quedar asociaciones inconsistentes. | Se bloquea edición, anulación y restauración mientras exista asignación. Si la entrega está en preparación, primero se retira el pedido desde Entregas. Se vuelven a leer sus datos al reservarlo para el remito. |
| Alta | Pago directo con cheque sin cheque bancario | La deuda bajaba, pero no existía cheque para depositar/conciliar/rechazar; la venta no podía certificarse pagada. | Todo nuevo pago directo genera también el cobro canónico de G2, su vínculo financiero y aplicaciones a ventas. Para cheque se exigen datos reales y se crea la cartera. Rendiciones reutiliza este mismo registro, sin duplicarlo. |
| Alta | Devoluciones de rendición simuladas | Se informaba éxito con un ID aleatorio, sin persistencia ni cambio de estado logístico. | Se registra un ID real, rendición, entrega, pedido, cliente real, motivo, observaciones, usuario y fecha; se actualizan pedido y remito como no entregados, y se audita. |
| Alta | Diferencias de rendición calculadas pero no guardadas | Se perdían motivo, faltante/sobrante y situación pendiente; el total esperado podía provenir del navegador. | Se calcula con comprobantes reales y cobros declarados, se persiste y audita. Una diferencia pendiente bloquea aprobar. Un administrador puede documentar su resolución. |
| Alta | Nombres/descripciones de Compras interpolados en HTML | Datos guardados podían interpretarse como etiquetas o manejadores de eventos. | Se aplica el escape compartido en detalle, recepción, edición y sugerencias de Compras. Los incidentes de rendición se presentan mediante `textContent`. |

### 2.1. Ingreso de mercadería

Archivo: `app/Modules/Stock/Controllers/IngresoMercaderiaController.php`.

Se conserva el contrato de ingreso individual y por `items`. El modo individual deja de convertir la cantidad a entero antes de validarla, evitando aceptar fracciones truncadas. El lote bloquea productos en orden de ID. Movimientos, stock y asociaciones `PRODUCTO_PROVEEDOR` quedan incluidos en la misma transacción. La validación de una unidad ajena al producto también revierte los ítems anteriores.

Los errores de validación de este endpoint conservan `400`, con mensaje y campos. Un error inesperado devuelve `500` con la transacción revertida. El incremento de stock se continúa haciendo con el servicio propietario de Stock.

### 2.2. Idempotencia del alta de ventas

Archivos principales:

- `app/Modules/Ventas/Controllers/VentaController.php`.
- Nuevo `app/Modules/Ventas/Services/SaleCreationRequests.php`.
- `resources/views/ventas/ventas.blade.php`.

`POST /api/ventas` admite `request_key` UUID o la cabecera `Idempotency-Key`. Si se envían ambas deben coincidir. La tabla `g4_sale_requests` conserva usuario, huella del contenido validado y resultado. Su clave primaria impide reservar dos veces la misma referencia, incluso con procesos simultáneos. Los campos de objetos se ordenan al calcular la huella; no se altera el orden comercial de los ítems.

Primera confirmación: `201`, `repetido: false`. Reenvío válido: `200`, `repetido: true`, mismo `venta_id`. Datos o usuario diferentes: `409`. Un alta rechazada revierte también la reserva. Recuperar una venta confirmada no necesita volver a consultar el catálogo; esto se verificó con el adaptador de productos indisponible.

El POS conserva en el navegador la UUID y el cuerpo pendiente antes del envío. No los elimina ante una respuesta de servidor fallida o una desconexión. Si el carrito cambió, permite recuperar explícitamente el envío original. Los elimina al recibir una confirmación válida. La información está separada por usuario.

**Compatibilidad:** las integraciones antiguas que no envían UUID siguen pudiendo crear ventas; reciben una referencia generada por el servidor. Para garantizar que un reenvío sea la misma operación, un consumidor debe enviar y conservar su propia UUID desde el primer intento. Repetir sin referencia no puede distinguirse de una venta nueva legítima. Una nueva venta debe utilizar otra UUID.

### 2.3. Pagos múltiples de compras

Archivos: `PagoCompraController.php` y `PagoCompraService.php`, dentro de `app/Modules/Compras`.

El método nuevo `registrarMultiples` mantiene el bloqueo de la compra hasta terminar el lote y reutiliza las reglas de pagos individuales. Se validan medio, fecha, rango y hasta dos decimales. Se admite `monto` como alias de `importe` y `fecha` como alias de `fecha_pago`. Un cuerpo que mezcle `pagos` con campos de pago individual se rechaza: no se elige silenciosamente una variante.

El resultado del formato múltiple contiene `data.pagos` y `data.resumen`; el individual conserva `data.pago` y `data.resumen`.

### 2.4. Ventas asignadas a entregas

Archivos: `VentaService.php`, `VentaResource.php`, `VentaController.php`, `DeliveryPlanningService.php` y `EntregaController.php`.

La restricción se aplica en el servidor, bajo bloqueo, además de deshabilitar acciones en las respuestas y el historial. La API de detalle informa `entrega_asignada`. No se modifica un remito emitido indirectamente desde Ventas.

Mientras la entrega siga en preparación se puede retirar el pedido usando la operación ya existente de Entregas: se anula su remito, se libera su reserva y vuelve a confirmada. A partir de allí puede editarse. Una entrega en camino o finalizada requiere su circuito logístico/comercial correspondiente; la anulación común de una venta no sustituye una devolución.

Al agregar un pedido a una entrega se vuelven a consultar sus datos después de reservar la venta, para no emitir un remito con datos leídos antes de una edición simultánea. Confirmar una entrega conserva ahora el código de error HTTP del servicio; no convierte todos los conflictos en `400`.

### 2.5. Cobros directos y cheques

Archivos principales: `ClientPaymentService.php`, `ClientPaymentRequest.php`, `CuentaCorrienteController.php`, `FinancialReadService.php`, `CollectionSettlementService.php`, vistas de Clientes/Saldos y los nuevos campos compartidos de cheque.

Todo nuevo pago crea atómicamente:

1. Movimiento en la caja abierta del administrador.
2. Movimiento en cuenta corriente y registro de pago con su UUID.
3. Cobro en `cobro`, vínculo en `g2_cobro_aplicaciones` y relación con el movimiento de caja.
4. Aplicaciones en `cobro_venta`: por antigüedad para pagos generales; al comprobante concreto para Rendiciones.
5. Para cheque, registro `CHEQUE` en estado `en_cartera`.
6. Auditoría del pago y, cuando corresponde, de su aplicación desde Rendiciones.

Se exigen `numero_cheque`, `banco_emisor` y `fecha_cobro`. La misma UUID no puede reutilizarse con otros datos de cheque. Las pantallas de Clientes y Saldos muestran los campos al seleccionar ese medio y los deshabilitan para otros medios. El historial financiero expone el cobro y el estado del cheque; la ficha muestra si está pendiente de acreditación bancaria y ofrece acceso a su gestión.

Se reutilizan depósito, conciliación, rechazo y anulación de G2. Un cheque en cartera reduce contablemente la deuda al registrarse, pero **no certifica por sí solo que la venta esté pagada**: para esa fase se exige la conciliación. Su rechazo revierte el cobro en caja/cuenta y restablece la deuda; si había una venta marcada pagada, se recupera su fase anterior comprobable. Repetir el rechazo no repite la deuda.

No se fabricaron vínculos para pagos históricos anteriores. Una operación antigua sin evidencia suficiente requiere conciliación de sus registros antes de aplicarla o revertirla automáticamente.

### 2.6. Devoluciones de rendición

Nuevo servicio: `app/Modules/Rendiciones/Services/RenditionIncidentService.php`.

Se comprueban rendición pendiente, identidad del usuario, repartidor de la entrega, pertenencia del pedido y estado preparado. No se confía en el nombre del cliente enviado desde el navegador. Se rechazan pedidos ajenos, entregados/cerrados o con cobros declarados en esa rendición que requieren revisión previa.

Se persiste una devolución por entrega/pedido. Un reenvío de los mismos datos recupera el mismo ID; otros datos para esa devolución producen conflicto. El pedido queda `no_entregada` y su remito `no_entregado`. La entrega finaliza cuando no quedan pedidos en preparación. Un pedido devuelto no puede después confirmarse como entregado mediante el botón común.

**Esta operación registra un pedido no entregado de G2. No incrementa automáticamente stock ni genera una nota de crédito.** La recepción física de mercadería, condición del producto y devolución comercial de G4/G3 son otro circuito pendiente, señalado en la revisión. Las reservas se conservan como protección contra una reasignación o anulación incorrecta.

### 2.7. Diferencias de rendición

El total esperado se obtiene de los comprobantes entregados asociados a la entrega; se excluyen los ya revisados en otra rendición de esa entrega. Para una rendición manual sin entrega se utilizan los comprobantes asociados a sus cobros. Es un total comercial de comprobantes de la rendición, no una afirmación de saldo bancario conciliado.

El total rendido se calcula desde `RENDICION_COBRO`, evitando confiar en un total de cabecera desactualizado. Se trabaja en centavos y se valida el rango antes de persistir. Si el cliente envía `total_esperado`, debe coincidir con el cálculo del servidor. Una diferencia distinta de cero exige motivo.

Se guarda importe esperado, rendido, diferencia, tipo, motivo, observaciones, usuario y situación pendiente. Recalcular actualiza el registro y conserva la versión anterior en auditoría. Una nueva declaración que vuelva obsoleto el cálculo obliga a recalcular antes de aprobar.

Resolver exige administrador y explicación. **Resolver no crea dinero, cobros, notas de crédito ni ajustes de caja:** deja constancia de la revisión de una diferencia. Un abono parcial justificado puede resolverse y aprobarse conservando la deuda pendiente real. La resolución no convierte ese abono en pago total.

Los procesadores antiguos quedan como validadores/cálculos puros: no inventan IDs ni anuncian que guardaron información. La persistencia la realiza el servicio. Listados, detalle API y modal de Rendiciones incluyen devoluciones y diferencia. El modal permite calcular y resolver con controles separados.

### 2.8. Presentación segura y registro de Ventas

Se corrigieron cuatro interpolaciones de nombres/descripciones en `resources/views/compras/index.blade.php`: detalle, recepción, edición y sugerencias. Se mantiene el escape de códigos y proveedores que ya existía. Los textos de incidentes nuevos se insertan con nodos y `textContent`; no se convierten en HTML.

Durante la verificación también se encontró el uso del canal de log `ventas` sin definición. Se agregó un canal diario en `config/logging.php`, con archivos bajo `storage/logs/ventas/` y retención de 14 días. La auditoría transaccional de la venta continúa en su tabla correspondiente.

## 3. Cambios de base de datos

Se agregaron exclusivamente dos migraciones nuevas; no se borraron ni reescribieron las anteriores:

| Archivo | Tablas creadas | Propósito |
|---|---|---|
| `app/Modules/Ventas/database/migrations/2026_11_09_000005_create_sale_creation_requests.php` | `g4_sale_requests` | Reservar una UUID y conservar el resultado del alta de venta. |
| `app/Modules/Rendiciones/database/migrations/2026_11_09_000006_create_rendition_incidents.php` | `g2_rendicion_devoluciones`, `g2_rendicion_diferencias` | Persistir incidentes y su resolución. |

Primero se aplicaron sobre MySQL aislado. Después se respaldó y actualizó `sistema_gestion` local usando sólo esas dos rutas. Resultado: 90 tablas antes, 93 después. Se compararon huellas y cantidades de todas las tablas existentes: **ninguna cambió sus registros**, excepto la incorporación de las dos entradas esperadas en `migrations`. Se conservaron los 1.343 registros existentes fuera de esa tabla.

Los métodos `down()` son deliberadamente conservadores: no eliminan referencias de ventas ni incidentes auditados. Compartir el código no comparte la base local; otro equipo que ejecute estas migraciones sí agregará esas tres tablas en su propia base.

Para otra instalación actualizada, hacer respaldo y ejecutar desde la raíz del proyecto:

```powershell
C:\xampp\php\php.exe artisan migrate --path=app/Modules/Ventas/database/migrations/2026_11_09_000005_create_sale_creation_requests.php --force
C:\xampp\php\php.exe artisan migrate --path=app/Modules/Rendiciones/database/migrations/2026_11_09_000006_create_rendition_incidents.php --force
```

Requieren el esquema integrado de las etapas anteriores. No sustituyen una instalación completa ni habilitan ejecutar indiscriminadamente SQL históricos.

## 4. Contratos y ejemplos de prueba manual

Todas las operaciones conservan sus controles de autenticación, rol, propiedad y CSRF cuando usan sesión web. Los ejemplos presuponen IDs reales y una cuenta administrativa autorizada; no deben pegarse como GET en la barra del navegador.

### Venta con referencia reutilizable

`POST /api/ventas`:

```json
{
  "request_key": "a4605c2c-ad0a-4de4-a93d-080be78f96bc",
  "cliente_id": 1,
  "lista": "minorista",
  "items": [{"id": 1, "cantidad": 2, "descuento": 0}]
}
```

Enviar dos veces exactamente el mismo cuerpo: debe devolverse el mismo `venta_id`, una vez `201` y luego `200`. Comprobar que no se repiten venta, deuda ni movimiento de stock. Cambiar cantidad conservando la UUID debe devolver `409`. Usar otra UUID sólo para una operación nueva.

### Compra con dos pagos

`POST /api/compras/{compra}/pagos`:

```json
{"pagos": [{"metodo_pago": "efectivo", "importe": 70}, {"metodo_pago": "transferencia", "importe": 30}]}
```

Sobre una compra con saldo 100, deben aparecer ambos pagos y saldo cero. En otra compra con saldo 100, enviar 70 y 40: debe rechazarse sin registrar el primero.

### Pago con cheque desde G1

`POST /api/v1/finanzas/clientes/{cliente}/pagos`:

```json
{
  "request_key": "4f70857e-a355-4b8c-85af-cbe0c0787f87",
  "monto": 21.50,
  "medio_pago": "cheque",
  "comprobante": "RECIBO-PRUEBA",
  "datos_medio_pago": {
    "numero_cheque": "123456",
    "banco_emisor": "Banco del cheque",
    "fecha_cobro": "2026-11-09"
  }
}
```

La caja debe estar abierta y el importe no puede superar la deuda. Consultar la cartera desde Conciliación. El pago desde `/saldo/{cliente}/movimientos` utiliza `tipo: pago`, `metodo_pago: Cheque` y el mismo objeto `datos_medio_pago`. No se omiten esos campos al enviar desde las pantallas.

### Incidentes de Rendiciones

| Método y ruta web autenticada | Cuerpo / resultado |
|---|---|
| `POST /g2/api/rendiciones/{id}/devoluciones` | `{"id_pedido": 2, "motivo": "Local cerrado", "observaciones": "Visita realizada"}`. Devuelve un ID persistido. |
| `POST /g2/api/rendiciones/{id}/diferencias` | `{"motivo": "Abono parcial acordado"}`. El servidor calcula importes; `total_esperado` es opcional y, si se envía, se verifica. |
| `PATCH /g2/api/rendiciones/{id}/diferencias/{diferencia}/resolver` | `{"resolucion": "Se verificó el pago parcial; el resto permanece adeudado"}`. Sólo administrador. |
| `GET /g2/api/rendiciones/{id}` | Incluye `devoluciones` y `diferencia`, además del detalle anterior. |
| `GET /api/v1/rendiciones` | Conserva paginación y agrega los incidentes en cada registro. |

Desde el navegador, abrir el detalle de Rendiciones para consultar incidentes, calcular diferencia y resolverla. La aprobación debe fallar con `409` si la diferencia sigue pendiente. Las rutas `g2/api` usan sesión web y CSRF; no se las convierte en APIs públicas por tener `api` en el nombre.

## 5. Validaciones y límites de certificación


| Validación | Resultado |
|---|---|
| Casos nuevos + regresión anterior de G3/G4 sobre MySQL aislado | 36 pruebas, 269 comprobaciones, todas aprobadas. |
| Regresión relacionada de pagos, transferencias, bancos, entregas, reportes, seguridad e instalación | 109 pruebas, 748 comprobaciones, todas aprobadas. |
| Frontend con Node | 43 pruebas aprobadas, incluyendo los tres casos nuevos de HTML malicioso, campos de cheque y presentación de incidentes. |
| Reenvío simultáneo de alta de venta | Dos procesos PHP; una venta, un movimiento de cuenta y un movimiento de stock. Una respuesta 201 y otra 200. |
| Vistas renderizadas de Clientes, Saldos, Ventas y Rendiciones | Respuestas correctas; los dos bloques JavaScript inline generados también pasaron la verificación sintáctica. |
| Regresión general con configuración segura | 512 pruebas, 2.446 comprobaciones; los mismos cuatro fallos previos, 36 omitidas y una riesgosa. No se presenta como una suite completamente aprobada. |
| Preservación de base habitual | Tres tablas nuevas, ninguna tabla previa con datos cambiados fuera de `migrations`; 1.343 registros conservados. |

Las pruebas nuevas verifican los recorridos solicitados. Las pruebas nuevas verifican el estado final en base de datos, no sólo un código HTTP. La prueba simultánea usó dos procesos PHP contra el mismo MySQL aislado: respuestas `201` y `200`, mismo ID, una venta, una deuda, un movimiento y stock 10 → 8. Sus fixtures se retiraron al finalizar.

La regresión general no está completamente verde: conserva los cuatro fallos anteriores de `G4DependencyRegistrationTest` y `G4SalesSchemaTest` relacionados con vistas/fixtures antiguos; también queda un test marcado riesgoso. Siete clases históricas se excluyeron mediante la configuración segura externa ya utilizada para no conectar tests a la base habitual. Las pruebas MySQL son de activación explícita en puerto 3315; que estén omitidas en una ejecución sin esa configuración no equivale a haberlas ejecutado allí.

Este informe cierra los ocho puntos seleccionados en los escenarios detallados. No declara terminadas las otras funcionalidades de la revisión: límite de crédito, devolución comercial/notas de crédito, el resto de concurrencia, actualización remota de `develop`, revisión visual integral y preparación de producción siguen teniendo su propio alcance. Tampoco se certificó en esta etapa una instalación Linux ni un despliegue de los módulos en servidores separados.

## 5.1. Inventario exacto de archivos de esta etapa

Los servicios, controladores, vistas y contratos se explican en las secciones anteriores. Este inventario distingue los archivos que ya existían de los agregados; el formateador se aplicó únicamente a los PHP tocados en esta etapa.

| Archivo | Tipo |
|---|---|
| `app/Modules/Compras/Controllers/PagoCompraController.php` | Modificado |
| `app/Modules/Compras/Services/PagoCompraService.php` | Modificado |
| `app/Modules/CuentaCorriente/Controllers/CuentaCorrienteController.php` | Modificado |
| `app/Modules/CuentaCorriente/Requests/ClientPaymentRequest.php` | Modificado |
| `app/Modules/CuentaCorriente/Services/ClientPaymentService.php` | Modificado |
| `app/Modules/CuentaCorriente/Services/FinancialReadService.php` | Modificado |
| `app/Modules/Entregas/Controllers/EntregaController.php` | Modificado |
| `app/Modules/Entregas/Services/DeliveryPlanningService.php` | Modificado |
| `app/Modules/Rendiciones/Controllers/Api/V1/RendicionApiController.php` | Modificado |
| `app/Modules/Rendiciones/Controllers/RendicionController.php` | Modificado |
| `app/Modules/Rendiciones/Repositories/RendicionRepository.php` | Modificado |
| `app/Modules/Rendiciones/Services/CollectionSettlementService.php` | Modificado |
| `app/Modules/Rendiciones/Services/ProcesadorDeDevolucionRendicion.php` | Modificado |
| `app/Modules/Rendiciones/Services/ProcesadorDeDiferenciaRendicion.php` | Modificado |
| `app/Modules/Rendiciones/Services/RendicionService.php` | Modificado |
| `app/Modules/Rendiciones/Services/RenditionIncidentService.php` | Nuevo |
| `app/Modules/Rendiciones/Services/RenditionReviewService.php` | Modificado |
| `app/Modules/Rendiciones/database/migrations/2026_11_09_000006_create_rendition_incidents.php` | Nuevo |
| `app/Modules/Rendiciones/routes/web.php` | Modificado |
| `app/Modules/Stock/Controllers/IngresoMercaderiaController.php` | Modificado |
| `app/Modules/Ventas/Controllers/VentaController.php` | Modificado |
| `app/Modules/Ventas/Resources/VentaResource.php` | Modificado |
| `app/Modules/Ventas/Services/SaleCreationRequests.php` | Nuevo |
| `app/Modules/Ventas/Services/VentaService.php` | Modificado |
| `app/Modules/Ventas/database/migrations/2026_11_09_000005_create_sale_creation_requests.php` | Nuevo |
| `config/logging.php` | Modificado |
| `docs/informe-correcciones-operativas-g1-g2-g3-g4-2026-11-09.md` | Nuevo |
| `public/assets/js/cheque-payment.js` | Nuevo |
| `public/js/g2-rendiciones.js` | Modificado |
| `resources/views/clientes/show.blade.php` | Modificado |
| `resources/views/compras/index.blade.php` | Modificado |
| `resources/views/partials/cheque-payment-fields.blade.php` | Nuevo |
| `resources/views/saldos/saldo.blade.php` | Modificado |
| `resources/views/ventas/ventas.blade.php` | Modificado |
| `tests/Feature/OperationsConsistencyMySqlTest.php` | Nuevo |
| `tests/Frontend/operations-consistency.test.cjs` | Nuevo |

## 6. Cómo revertir esta etapa

1. Detener las operaciones de usuarios y respaldar el estado actual. Conservar el respaldo de esta etapa: `codigo-antes.zip`, `codigo-antes.json`, `base-antes.sql` y los manifiestos de cambios, fuera del repositorio.
2. Restaurar **sólo los archivos modificados en esta etapa** desde `codigo-antes.zip`, y retirar exclusivamente los archivos nuevos enumerados en el manifiesto. El respaldo contiene también las modificaciones locales previas: no usar `git reset --hard`, `git clean` ni restaurar todo a `HEAD`, porque se perderían etapas anteriores.
3. Se dejó una ayuda externa `revertir-codigo.py`: primero ejecutar sin opciones para comprobar los archivos; `--aplicar` restaura únicamente esta etapa. Se niega a continuar si detecta modificaciones posteriores. No cambia la base de datos.
4. Conservar las tres tablas nuevas si ya tienen registros. `migrate:rollback` no las borra ni revierte cobros. Retirar el código nuevo puede impedir gestionar esos incidentes o recuperar reenvíos: revisar referencias antes de reabrir el sistema.
5. Si se necesita volver exactamente al estado de datos anterior, restaurar `base-antes.sql` primero en una base aparte y validarla. Reemplazar la base habitual sólo con autorización y respaldo actual: se perderían las operaciones posteriores al respaldo. No restaurar ese SQL sobre bases de otros equipos.
6. Los nuevos cobros/cheques posteriores al despliegue se revierten mediante sus operaciones financieras, con auditoría; no borrando filas ni referencias. Los datos históricos no fueron transformados durante la actualización.
7. Si se utilizan cachés de configuración/vistas, limpiarlas después de restaurar el código. Iniciar manualmente MySQL y Laravel para verificar la versión restaurada.

El servidor Laravel no se dejó iniciado. Las instancias de MySQL utilizadas para esta actualización se devuelven al estado apagado que tenían al comenzar.
