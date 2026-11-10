# Correcciones de G3 y G4: compras, stock y ventas

Fecha: 09/11/2026. Etapa: los ocho puntos del apartado «4. G3 y G4 — Compras, stock y ventas».

## 1. Alcance y conservación del trabajo anterior

Se trabajó sobre los archivos presentes en la copia local de `develop`. No se incorporó código de otras ramas, no se hizo commit/push y no se reemplazaron las modificaciones de las etapas anteriores. La autorización vigente permite modificar los distintos grupos para completar estos recorridos.

**Sí se modificó código de G3 y G4.** También se agregaron llamadas acotadas en dos servicios de G2 para que una reversión financiera no deje una venta indebidamente marcada como pagada. No se modificaron tablas históricas de esos módulos ni se importaron productos, clientes o pagos de demostración en la base de trabajo.

Antes de comenzar se conservaron 612 archivos de código/documentación, el diff previo, una copia privada del `.env` y una copia física del directorio de datos de MySQL. Antes de aplicar la migración se generó además un respaldo SQL de las dos bases del proyecto.

Evidencia y respaldos, fuera de la raíz Git de la aplicación:

```text
../Entregables/G3_G4_Compras_Stock_Ventas_2026-11-09/
```

Ese directorio contiene datos y configuración privados. No debe publicarse junto con el código.

## 2. Resultado por punto solicitado

| Prioridad | Punto | Problema encontrado | Corrección aplicada |
|---|---|---|---|
| Alta | G3 — Unidades | Se aceptaba una unidad por su ID sin comprobar a qué producto pertenecía. Órdenes y sugerencias podían tomar una unidad base global de otro producto. | Se centralizó la resolución por producto. Una unidad explícita debe pertenecer al artículo; omitirla significa unidades individuales de ese artículo. Se elimina el respaldo global. |
| Alta | G3 — Concurrencia | Stock calculaba sobre una instancia que podía estar vieja. Recepciones y pagos verificaban cantidades/saldo antes del bloqueo. | Se releen y bloquean las filas dentro de las transacciones; se repiten las validaciones con el valor vigente. Se probaron procesos simultáneos reales en MySQL. |
| Alta | G4 — Importes | El alta hacía `(int)` sobre el precio; un precio de 10,75 terminaba tratado como 10. | Alta y edición usan la misma calculadora, con redondeo monetario a centavos, precios del catálogo del servidor y control de valores/rangos. |
| Alta | G4 — Promociones | Un ID de promoción existente alcanzaba para etiquetar el detalle. Faltaba comprobar combo, fecha, estado y descuento. | Se valida el combo completo al crear, modificar y salir de pendiente, incluyendo transiciones que saltan la confirmación. Se verifica el descuento por unidad contra la configuración real. |
| Alta | G4 — Venta pagada | La transición admitía pagada sin demostrar pago suficiente. | Se exige cancelación del pendiente de la venta con pagos vinculados al libro y a Caja. Se repite la comprobación para facturar. Los cheques requieren conciliación. |
| Alta | G4–G3 — Stock | Los avisos de modificación/anulación/restauración podían perderse y un reenvío podía repetir el ajuste. | Se agrega una bandeja durable de G4 y un registro idempotente de G3. La misma clave y contenido no vuelve a modificar stock. Hay comando y programación de reintentos. |
| Media | G4–G2 — Cerrar caja | El botón mostraba «a implementar». | Ahora abre la pantalla real de Caja de G2, conservando su identificación, permisos, conteo y confirmación de cierre. No cierra una caja sin pasar por ese circuito. |
| Media | G4 — Exportación de saldos | Faltaba descargar el listado de saldos y movimientos/pagos con sus filtros. | Se agrega PDF del listado filtrado por búsqueda y fechas, con resumen, movimientos, importes y saldo filtrado, utilizando la misma fuente de la pantalla. |

## 3. Unidades: qué se cambió y por qué

### Resolución por producto

Nuevo servicio: `app/Modules/Stock/Services/ProductUnitService.php`.

Reglas:

1. La unidad solicitada debe existir **para el producto indicado**.
2. Si no se indica unidad en una compra, orden o sugerencia, se busca una equivalencia 1 del mismo artículo. Si no existe, se crea una «Unidad individual» para ese producto, dentro de la transacción.
3. No se consulta una unidad base de cualquier otro artículo.
4. `es_base` se usa en esta interfaz como selección visual; una caja seleccionada visualmente no convierte automáticamente una cantidad sin unidad en cajas.
5. El stock y el Kardex expresan cantidades enteras de unidades individuales. Una conversión que produzca una fracción, un valor no positivo o un desbordamiento se rechaza.

Se adaptaron Compras, Órdenes de compra, generación de órdenes desde sugerencias y los controladores de ingresos, devoluciones y ajustes que calculan equivalencias.

### Protección de equivalencias históricas

Las altas, cambios, selección y eliminación de unidades se serializan bloqueando el producto. La API rechaza equivalencias fraccionarias: el esquema actual de G3 las guarda en una columna entera y antes podía redondearlas silenciosamente.

Una unidad utilizada en movimientos, detalles de compra, orden o recepción no puede cambiar su equivalencia ni borrarse. Para una presentación nueva corresponde crear otra unidad. No se reescriben cantidades históricas.

Las unidades incorrectamente vinculadas en datos antiguos no se reasignaron automáticamente. Esos casos requieren revisión de los comprobantes; una operación nueva no debe perpetuar una relación inválida.

## 4. Concurrencia y atomicidad de G3

### Stock

`StockService::registrarMovimiento()` vuelve a leer `Producto` con `lockForUpdate()` dentro de su transacción. El disponible se calcula desde esa fila, aunque quien llamó conserve una instancia vieja. La actualización del stock y el movimiento del Kardex se confirman o revierten juntos.

Se comprueba que el producto siga activo, que el resultado no sea negativo y que las cantidades entren en el rango entero de la base. En el lote de integración los productos se procesan ordenados por ID para reducir conflictos de bloqueos.

### Recepciones

`RecepcionController` bloquea la compra y sus detalles, vuelve a comprobar su estado y valida lo recibido contra el pendiente vigente. Reúne las cantidades de la solicitud antes de escribir para que tampoco se pueda exceder el pendiente repitiendo una línea en el mismo pedido.

Si un producto figura en distintas unidades dentro de la misma compra, se debe indicar `id_detalle_compra` o una `id_unidad` que identifique una única línea. Antes se indexaba solo por producto y se podía elegir la presentación equivocada. Una recepción sin identificación suficiente se rechaza y no suma stock.

Se validan cantidades enteras y se conservan las referencias de recepción y unidad en el Kardex. La respuesta obtiene el estado actualizado de la compra.

### Pagos y modificación/cancelación de compras

`PagoCompraService` bloquea la compra **antes** de comprobar estado y saldo. La suma pagada se verifica dentro de la misma transacción que inserta el pago. Dos solicitudes no pueden usar simultáneamente el saldo anterior para sobrepagar.

Se rechazan importes no finitos, fuera de rango o con más de dos decimales. Se conservan separados los conceptos existentes de G3:

- `saldo_pendiente`: valor de mercadería todavía no recibida, según el flujo de recepción existente.
- `saldo_a_pagar`: importe total menos los pagos registrados al proveedor.

La modificación vuelve a bloquear y comprobar la compra; no permite reducir el total por debajo de lo ya pagado. La cancelación exige una compra pendiente sin pagos. No se ocultan ni eliminan pagos para permitir una cancelación.

### Ajustes absolutos y conteo físico

El ajuste que fija una cantidad calcula su diferencia después de bloquear el producto. También funciona el caso de fijar stock cero.

El conteo físico bloquea sesión, detalles y productos al aplicarse. Si el stock cambió desde la toma del conteo, se exige actualizarlo en lugar de aplicar una diferencia obsoleta. La finalización queda serializada para evitar repetir ajustes. Se corrigió además la posición del parámetro `id_sesion_conteo`: antes se enviaba un argumento adicional y la referencia podía no quedar en el movimiento. No se permite eliminar ítems de un conteo finalizado.

## 5. Importes y promociones de G4

### Una misma calculadora para alta y edición

El alta en `VentaController` utiliza `CalculadoraVenta`, igual que la modificación. Se eliminó la conversión del precio a entero y la copia separada del cálculo de descuentos y totales.

Los precios salen de `ProductoGateway`, según la lista minorista/mayorista, y se guardan con sus centavos. El detalle, total y movimiento de cuenta corriente usan el mismo resultado. Se rechazan valores negativos/no finitos, cantidades inválidas y operaciones que excedan la capacidad monetaria de las columnas.

Se corrigió también la captura de `fecha_vencimiento` dentro de la transacción del alta: el dato validado se conserva ahora en la venta.

La calculadora conserva su responsabilidad de cálculo sin consultar la base. La validación de promociones se ejecuta en los casos de uso de alta/edición, dentro de sus transacciones, y en las transiciones de estado.

### Validación de promociones

Nuevo servicio: `app/Modules/Promociones/Services/PromotionSaleService.php`.

Comprueba en el servidor:

- Promoción existente, activa y vigente en la fecha de la operación.
- Todos los productos del combo, sin ajenos, faltantes o duplicados.
- Cantidades completas y el mismo múltiplo de combos para todos sus componentes.
- Productos disponibles en el catálogo real.
- Tipo de descuento permitido y valor válido.
- Descuento unitario coincidente con el autorizado para los precios de la operación.
- Ausencia de un descuento global adicional sobre un carrito con promociones.

Al confirmar se usan los precios unitarios persistidos de la venta como cotización de esa operación, y se revalida la configuración vigente del combo. Una promoción vencida o desactivada obliga a actualizar la venta; no se evita esta validación saltando directamente de pendiente a preparada/entregada/pagada.

Las lecturas de la promoción y sus componentes se bloquean durante la validación. La edición, baja y cambio de estado de promociones utilizan el mismo bloqueo de cabecera. Se rechazan productos repetidos al administrar el combo y se corrigió la relación del modelo `Promocion` hacia el modelo real de Productos.

### Centavos en el reparto del descuento

El detalle tiene un descuento monetario por unidad con dos decimales. Se usa un orden estable de productos y se distribuye el descuento de cada combo sin redondear hacia arriba un descuento unitario que exceda el presupuesto autorizado.

Ejemplo comprobado: 2 unidades de 10,75 con 10 % de promoción. El presupuesto es 2,15, pero no se pueden repartir 1,075 por unidad en una columna de dos decimales. Se aplica 1,07 por unidad: descuento efectivo 2,14 y total 19,36. El centavo indivisible permanece en el precio, sin otorgar un descuento superior al autorizado.

El servidor, la vista previa de Promociones y el precio final calculado al administrar la promoción siguen ese criterio. El cálculo se define por combo individual para que agregar dos veces el mismo combo no cambie el descuento unitario por un reparto diferente de redondeos.

## 6. Estado pagada y respaldo financiero

Nuevo servicio de G4: `app/Modules/CuentaCorriente/Services/SalePaymentEvidence.php`.

Antes de permitir `pagada`, y nuevamente antes de `facturada`, se verifica que la venta no conserve importe pendiente al asignar pagos verificables. Un pago verificable relaciona:

```text
cuenta_corriente_pagos
  -> movimiento de cuenta corriente del cliente, con importe coincidente
  -> movimiento de Caja de tipo cobro, con importe coincidente
```

Un crédito agregado únicamente al libro, un cobro declarado, una rendición sin aplicación individual, un pago parcial o una promesa de pago no alcanzan. Los pagos revertidos se excluyen mediante los vínculos de reversión existentes. No se certificaron retroactivamente pagos históricos sin respaldo.

Para cheques se exige además el cheque asociado al cobro en estado `conciliado`; tenerlo en cartera o depositado no basta para certificar que la venta fue pagada.

### Corrección necesaria en la pantalla de Cuentas corrientes

El formulario antiguo de pago escribía solo un crédito en el libro. Se lo conectó con `ClientPaymentService`, que registra pago, Caja, libro y auditoría en una misma transacción y exige caja abierta del administrativo.

El frontend genera una UUID por intento de pago y la conserva al reintentar. Reenviar la misma clave no genera otro pago ni otro movimiento. Si se recibe una respuesta repetida, se recarga la información persistida para no dejar el saldo visual desactualizado tras una respuesta perdida. Tarjetas/QR se mapean al medio `otro` admitido por Caja, conservando su etiqueta en el comprobante.

El acceso antiguo que acreditaba directamente un cobro de rendición dejó de escribir dinero al libro. Los cobros ya aplicados responden sin duplicar; los restantes indican usar el circuito de Rendiciones y Cobros. Se retiró esa opción directa del selector y se dejó un enlace a la operación real. La nota de crédito conserva su recorrido propio y no se hace pasar por un pago de Caja.

### Reversiones posteriores

Se modificaron dos puntos de G2:

- `CollectionSettlementService::cancel()`: luego de la reversión financiera pide a G4 revisar las ventas vinculadas.
- `BankReconciliationService::reverse()`: al quitar la conciliación de un cheque pide la misma revisión.

Si una venta estaba pagada y perdió el respaldo, G4 restaura la fase anterior que consta en su historial y registra `reversion_financiera`. No adivina una fase logística. Si una venta histórica pagada carece de una fase anterior verificable, la reversión se bloquea para revisión; no se fabrican antecedentes.

Este mecanismo no anula facturas fiscales ni implementa un proveedor fiscal. Una venta que ya está facturada requiere el procedimiento fiscal correspondiente para notas de crédito/anulación. No se borra su fase fiscal para simular esa gestión.

## 7. Stock durable e idempotencia G4–G3

### Nuevas tablas

Migración nueva:

```text
app/Modules/Ventas/database/migrations/
2026_11_09_000004_create_stock_delivery_records.php
```

| Tabla | Propietario | Finalidad | Campos principales |
|---|---|---|---|
| `g4_stock_operations` | G4 | Guardar la intención de ajuste y su resultado, junto con la transacción de venta. | ID secuencial, `request_key` UUID única, venta, actor, motivo, cambios JSON, estado, intentos, último error y fechas. |
| `g3_stock_requests` | G3 | Reconocer reenvíos y conservar su respuesta original. | UUID primaria, hash del contenido, actor de servicio, respuesta JSON y fechas. |

No se borró ni sustituyó ninguna migración existente. La migración agrega estas tablas si faltan; no altera tipos de columnas de terceros ni crea claves foráneas incompatibles entre los esquemas históricos. Su `down()` conserva los registros deliberadamente: no es seguro eliminar ajustes pendientes o comprobantes de aplicación al revertir código.

### Recorrido

1. Alta, modificación, anulación o restauración guarda una operación con UUID en la misma transacción que la venta y el libro.
2. `StockDeliveryService` envía esa operación mediante `HttpStockApi::ajustarConClave()`.
3. G3 compara UUID, actor y hash; bloquea el registro y aplica todo el lote o nada.
4. Un reenvío idéntico devuelve la respuesta guardada. Reutilizar la clave con otro contenido/actor devuelve 409.
5. G4 marca la operación aplicada. Si una comunicación remota falla, guarda el intento/error y deja la operación pendiente.

**Modo `internal`**: G3 se ejecuta dentro de la misma instancia y transacción de base de datos. Si falta stock o falla una validación, se revierte el alta/cambio de venta, el libro, la bandeja y el movimiento de stock. No hace falta Apache ni un segundo servidor HTTP.

**Modo `http`**: la intención se confirma antes de enviar. Una caída o respuesta perdida no pierde la operación. El reintento usa siempre la misma UUID; el receptor G3 actualizado reconoce lo que ya aplicó. Esto requiere que ambos extremos utilicen el contrato actualizado, no un servidor antiguo que ignore la clave.

La API de alta informa `stock_operation_id` y `stock_estado`. Los recursos de Ventas informan si hay ajustes pendientes. La pantalla de venta avisa cuando el envío quedó pendiente y pide no repetir el alta. Una venta con un ajuste pendiente no admite otra modificación, avance de fase o preparación logística hasta resolverlo; así se conserva el orden de los ajustes.

Los avisos históricos que solo quedaron en logs antes de esta etapa no se transformaron automáticamente en operaciones nuevas: podrían haber sido aplicados externamente y repetirlos sin conciliación duplicaría stock.

### Recuperación

```powershell
C:\xampp\php\php.exe artisan ventas:reenviar-stock
C:\xampp\php\php.exe artisan ventas:reenviar-stock --limit=100
```

Se agregó el comando al scheduler cada minuto con `withoutOverlapping()`. Para usarlo automáticamente el entorno debe ejecutar `artisan schedule:run` cada minuto; no se instaló una tarea de Windows ni un servicio de fondo. En desarrollo también puede ejecutarse el comando manualmente.

Si persiste un error de conexión, configuración o falta de stock, primero debe resolverse su causa y después reintentarse. No se borran manualmente UUID/recibos ni se cambia pendiente a aplicado para destrabar una venta.

Las llamadas antiguas sin `request_key` siguen siendo solicitudes nuevas: no se promete idempotencia para una petición que no identifica el intento. El flujo nuevo de Ventas sí envía la clave.

## 8. Presentación y PDF

### Cerrar Caja

`resources/views/ventas/ventas.blade.php` dejó de mostrar el aviso pendiente. Usa la ruta nombrada `caja.dia` para abrir la pantalla real de G2. El cierre sigue requiriendo el formulario, conteo, permisos y validaciones existentes; pulsar el botón de Ventas no cierra indiscriminadamente cajas de otros usuarios.

### PDF de saldos y pagos

Nueva ruta protegida:

```http
GET /saldo/exportar/pdf
```

Usa la misma sesión y los roles administrativo/contador de Cuentas corrientes. No es una descarga pública ni un endpoint Bearer nuevo.

Parámetros:

| Parámetro | Uso |
|---|---|
| `search` | Búsqueda por nombre/DNI-CUIT, como en el listado. |
| `modoFecha` | `todas`, `especifica` o `periodo`. |
| `fecha` | Obligatoria para fecha específica, formato `YYYY-MM-DD`. |
| `fechaDesde`, `fechaHasta` | Límites del período; se rechaza un rango invertido. |

El botón «Descargar PDF del listado filtrado» envía los filtros activos. Exporta todos los clientes que cumplen esos filtros, no solo el cliente seleccionado en el panel de detalle. Incluye resumen por cliente, fecha, descripción, medio/referencia, importe y saldo acumulado dentro del filtro.

Se usa Dompdf, ya instalado; no se agregó ninguna dependencia. La plantilla mantiene el azul del ERP, escapa los textos y muestra un estado explícito sin resultados. El PDF aclara que el saldo filtrado es la suma de los movimientos del período y no equivale necesariamente a la deuda total ni al saldo de apertura.

La generación se probó a través de la ruta real, con y sin resultados, extrayendo texto y renderizando las páginas para revisión visual. Se comprobó el ejemplo 1.250,75 menos pago 250,25: saldo -1.000,50 y texto con etiquetas mostrado literalmente, sin ejecutar HTML.

## 9. Inventario de archivos de código y pruebas

Las rutas son relativas a `ERP-Juan23`. «Modificado» es respecto del inicio de esta etapa, no respecto de HEAD: se conservan cambios anteriores que ya estaban en esos archivos.

| Archivo o conjunto | Tipo | Motivo |
|---|---|---|
| `app/Modules/Stock/Services/ProductUnitService.php` | Nuevo | Resolver unidades del producto y validar conversión. |
| `app/Modules/Stock/Services/StockService.php` | Modificado | Lectura vigente bloqueada, conversión válida y actualización/Kardex atómicos. |
| `app/Modules/Stock/Services/ConteoFisicoService.php` | Modificado | Bloqueo de sesiones, conteo obsoleto y referencia del Kardex. |
| `app/Modules/Stock/Controllers/StockController.php` | Modificado | Lotes ordenados, validación, recepción idempotente y respuesta persistida. |
| `app/Modules/Stock/Controllers/UnidadController.php` | Modificado | Operaciones serializadas y protección de equivalencias históricas. |
| `app/Modules/Stock/Controllers/AjusteStockController.php` | Modificado | Ajuste absoluto bajo bloqueo y stock cero. |
| `app/Modules/Stock/Controllers/DevolucionController.php` | Modificado | Conversión con producto explícito. |
| `app/Modules/Stock/Controllers/IngresoMercaderiaController.php` | Modificado | Conversión con producto explícito. |
| `app/Modules/Compras/Controllers/CompraController.php` | Modificado | Unidad propia, validación, bloqueo y resguardo de pagos al editar/cancelar. |
| `app/Modules/Compras/Controllers/RecepcionController.php` | Modificado | Revalidación bloqueada, cantidades y selección de detalle/unidad. |
| `app/Modules/Compras/Services/PagoCompraService.php` | Modificado | Evitar sobrepagos simultáneos y validar importes. |
| `app/Modules/PedidosDeCompra/Controllers/OrdenCompraController.php` | Modificado | Retirar unidad global y validar unidades/cantidades/importes. |
| `app/Modules/PedidosDeCompra/Services/GeneradorSugerenciasReposicionService.php` | Modificado | Resolver unidad individual del producto al generar la orden. |
| `app/Modules/Promociones/Services/PromotionSaleService.php` | Nuevo | Validar aplicación real del combo. |
| `app/Modules/Promociones/Controllers/PromocionController.php` | Modificado | Bloqueos, productos distintos, precisión y reparto consistente. |
| `app/Modules/Promociones/Models/Promocion.php` | Modificado | Relación hacia el modelo correcto de Producto. |
| `public/js/promociones.js` | Modificado | Orden estable y descuento unitario que no excede lo autorizado. |
| `app/Modules/Ventas/Controllers/VentaController.php` | Modificado | Calculadora compartida, promociones, vencimiento y bandeja de stock en el alta. |
| `app/Modules/Ventas/Controllers/VentaApiController.php` | Modificado | Conservar errores HTTP de conflicto/validación en vez de convertirlos en 500 genérico. |
| `app/Modules/Ventas/Services/CalculadoraVenta.php` | Modificado | Cálculo puro compartido, límites y política de acumulación. |
| `app/Modules/Ventas/Services/VentaService.php` | Modificado | Bandeja en edición/anulación/restauración, promociones y respaldo de pago. |
| `app/Modules/Ventas/Services/DeliverySaleService.php` | Modificado | No preparar/entregar una venta con ajuste de stock pendiente. |
| `app/Modules/Ventas/Services/StockDeliveryService.php` | Nuevo | Persistencia, envío y reintento de ajustes. |
| `app/Modules/Ventas/Integraciones/HttpStockApi.php` | Modificado | Enviar la UUID durable a G3. |
| `app/Modules/Ventas/Resources/VentaResource.php` | Modificado | Exponer pendientes de stock. |
| `app/Modules/Ventas/database/migrations/2026_11_09_000004_create_stock_delivery_records.php` | Nuevo | Crear las dos tablas de seguimiento. |
| `app/Console/Commands/RetrySalesStock.php` | Nuevo | Recuperación manual y programada de ajustes. |
| `routes/console.php` | Modificado | Registrar la programación del comando. |
| `app/Modules/CuentaCorriente/Services/SalePaymentEvidence.php` | Nuevo | Prueba de pago y coherencia ante reversión. |
| `app/Modules/CuentaCorriente/Controllers/CuentaCorrienteController.php` | Modificado | Fuente compartida para vista/PDF y pagos reales de Caja. |
| `app/Modules/CuentaCorriente/routes/web.php` | Modificado | Ruta autenticada de descarga PDF. |
| `resources/views/saldos/pdf.blade.php` | Nuevo | Plantilla de exportación. |
| `resources/views/saldos/saldo.blade.php` | Modificado | Botón PDF, clave de pago y enlace al circuito real de cobros. |
| `resources/views/ventas/ventas.blade.php` | Modificado | Navegación a Caja y aviso de stock pendiente. |
| `app/Modules/Rendiciones/Services/CollectionSettlementService.php` | Modificado, G2 | Revisar fase pagada tras anular un cobro. |
| `app/Modules/ConciliacionBancaria/Services/BankReconciliationService.php` | Modificado, G2 | Revisar fase pagada al revertir conciliación de cheque. |
| `tests/Feature/G3G4OperationsMySqlTest.php` | Nuevo | Pruebas reales del recorrido integrado, aisladas y transaccionales. |
| `tests/Frontend/promotions-pricing.test.cjs` | Nuevo | Regresiones del cálculo utilizado por la interfaz de Promociones. |
| `README.md` | Modificado | Enlace al informe y uso de migración/reintento. |
| Este informe | Nuevo | Hallazgos, decisiones, validación y reversión. |

No se modificó `.env`, `.env.example`, Composer ni el contrato base `StockGateway` en esta etapa. Se agregó un método específico al adaptador real sin cambiar la firma usada por los consumidores existentes.

## 10. Validación realizada

### Pruebas específicas sobre MySQL aislado

`G3G4OperationsMySqlTest`: **20 pruebas y 117 aserciones aprobadas**.

Se ejecutaron sobre una base `erp_qa_g3_g4`, creada únicamente en el servidor de prueba del puerto 3315. La clase requiere habilitación explícita y un nombre `erp_qa_...`; no se conecta por sí sola al MySQL habitual 3306 ni ejecuta migraciones sobre él. Cada prueba revierte su transacción.

Cubren:

- Precio 10,75: total 21,50, detalle, vencimiento y stock real.
- Stock insuficiente: reversión de venta, libro y bandeja.
- Instancia vieja del producto: se usa el valor vigente bloqueado.
- Unidad de otro producto, unidad visual de caja y conversión fraccionaria.
- Reenvío de la misma clave de stock y conflicto con otro contenido.
- Recepciones que exceden el pendiente y pagos que exceden la deuda.
- Cancelación de compra que ya tiene pagos.
- Combo válido, incompleto, manipulado, inactivo o vencido.
- No acumular descuento global con promoción y no evitar la comprobación saltando de fase.
- Pago parcial, pago completo y crédito sin Caja verificable.
- Modificar/anular/restaurar: deltas reales e intento aplicado que no repite stock.
- Fallo HTTP simulado: intención persistida, venta bloqueada y misma clave al recuperar.
- Producto con dos unidades en una misma compra: selección inequívoca del detalle.
- Equivalencia con movimientos: no se reescribe.
- Ajuste a cero y conteo que quedó viejo por un ingreso posterior.
- Pago desde Cuentas corrientes: caja abierta, persistencia e idempotencia.
- Cheque: cartera/depositado no certifican pago; conciliación sí; rechazo restituye deuda y fase anterior con historial.
- PDF real, períodos con ambos límites o un único límite, filtros inválidos, acceso anónimo y navegación a Caja.

La simulación HTTP se limita a reproducir un servicio no disponible y su recuperación. Las pruebas de ventas internas, stock, cobros, Caja, cheques y conciliación usan los servicios y persistencia reales del proyecto.

### Procesos concurrentes reales

Se ejecutaron cuatro pares de procesos PHP contra MySQL 3315, con transacciones superpuestas y una espera deliberada después de adquirir bloqueos. Los datos pertenecen únicamente a la base de prueba.

| Carrera | Resultado observado |
|---|---|
| Dos salidas de 7 sobre stock 10 | Una operación aprobada; la otra rechazada por disponible 3. |
| Dos recepciones de 7 sobre una compra de 10 | Una recepción aprobada; la otra rechazada. Recibido final 7. |
| Dos pagos de 70 sobre deuda 100 | Un pago aprobado; el otro rechazado por saldo 30. Pagado final 70. |
| Dos solicitudes de stock con la misma UUID | Ambas obtienen éxito, con un solo recibo y un solo descuento. |

En la secuencia combinada el stock terminó en 9: 10 - 7 + 7 - 1. Hubo tres movimientos reales de stock y un único registro de recepción idempotente para la última operación. No hubo escrituras parciales ni stock negativo en esas carreras.

Esto verifica las carreras reproducidas; no equivale a un ensayo exhaustivo de carga ni a demostrar ausencia de cualquier bloqueo posible entre todos los módulos.

### Regresiones y comprobaciones adicionales

- Regresiones relacionadas de integración/seguridad/finanzas: **95 pruebas, 465 aserciones aprobadas**.
- Unitarias existentes: **97 pruebas, 275 aserciones aprobadas**.
- Frontend Node: **40 pruebas aprobadas**, incluidas cuatro nuevas sobre precio/descuento del combo.
- Regresión general segura: **496 pruebas, 2.446 aserciones; 4 fallos anteriores, 20 pruebas MySQL omitidas por falta de habilitación explícita y 1 aviso de prueba riesgosa anterior**. Conserva los cuatro fallos anteriores de `G4DependencyRegistrationTest` y `G4SalesSchemaTest`, junto con el aviso previo de manejadores de errores. Son expectativas de vista/fixtures antiguas; no se modificaron esas pruebas ni se las presenta como aprobadas.
- La suite general omite automáticamente las nuevas pruebas MySQL si no se habilita su entorno aislado. Además se mantuvieron las exclusiones de siete clases antiguas que seleccionan conexiones de base por su cuenta; no se las ejecutó indiscriminadamente sobre los datos de trabajo.
- Se comprobó la sintaxis de los 36 archivos PHP/Blade afectados y el diff sin errores de espacios.
- Instalación de una base nueva con las migraciones actuales: aprobada.
- Actualización local: solo quedó pendiente y se aplicó la nueva migración de seguimiento de stock. Una segunda ejecución informó `Nothing to migrate`.
- Lectura local real por el kernel de Laravel, conservando autenticación: Inventario, Ventas, Historial, Cuentas corrientes, PDF de saldos y API de Ventas respondieron 200. Catálogo público también respondió 200. Este smoke de páginas no sustituye una prueba interactiva de todos sus botones.
- PDF con datos y PDF vacío: HTTP 200, texto extraído y renderizado revisado visualmente.

Los logs completos, JUnit, resultados de concurrencia y archivos de QA se conservan en el directorio de evidencia de esta etapa.

## 11. Resultado sobre la base local

La migración se aplicó a la base configurada `sistema_gestion`, después del respaldo SQL. Se crearon únicamente `g4_stock_operations` y `g3_stock_requests`, vacías en el entorno de trabajo. El registro de migraciones agregó su ejecución.

Se compararon columnas preexistentes, cantidad de filas y hashes ordenados de sus valores en **103 tablas de datos** entre `sistema_gestion` y `sistema_gestion_g1_g3_20261026`. No hubo diferencias. Las tablas de registro de migraciones se excluyen de esa comparación porque su actualización es intencional.

No se tocaron permisos internos de MySQL ni archivos de configuración de XAMPP. Los productos, clientes, ventas y pagos utilizados para pruebas se crearon en la base aislada del servidor de prueba o dentro de transacciones revertidas. No se cargaron en la base de trabajo.

## 12. Cómo usarlo y comprobarlo manualmente

1. Iniciar **MySQL** desde XAMPP y levantar Laravel como siempre:

   ```powershell
   C:\xampp\php\php.exe artisan serve
   ```

   Con `VENTAS_INTEGRACION=internal` no se necesita Apache ni otro puerto para comunicar módulos.

2. En otra copia que reciba estos archivos, respaldar la base y ejecutar:

   ```powershell
   C:\xampp\php\php.exe artisan migrate
   ```

   Hacer push o pull no cambia por sí solo una base de datos. Ejecutar esta migración sí agrega las dos tablas indicadas. No eliminar los archivos de código que dependen de ellas ni desplegar el código nuevo sin aplicar su migración.

3. Desde Ventas, registrar un producto cuyo precio tenga centavos y comprobar el detalle y el total. Probar una cantidad mayor al stock: en modo interno no debe quedar una venta nueva ni un cargo de cuenta corriente.
4. Agregar un combo vigente completo. Cambiar su cantidad o descuento por fuera del contrato debe devolver validación; un combo vencido/inactivo tampoco debe confirmar.
5. Abrir la caja del administrativo, registrar un pago parcial y comprobar que no habilita pagada. Completar el pago real y volver a probar la transición. Un cheque necesita conciliación bancaria.
6. Pulsar «Cerrar caja» en Ventas: debe abrir la caja operativa y su formulario real.
7. En Cuentas corrientes, elegir búsqueda/fechas y pulsar «Descargar PDF del listado filtrado». Comparar resumen y movimientos con los filtros de la pantalla.
8. Ante una operación remota pendiente, corregir la causa y ejecutar `artisan ventas:reenviar-stock`. Revisar el estado de la misma operación; no cargar otra venta como reemplazo del reintento.

Para repetir las pruebas MySQL se debe preparar **otra base aislada de QA** con las migraciones, iniciar el servidor de prueba 3315 y habilitar explícitamente:

```powershell
$env:ERP_G3_G4_TEST_DATABASE = 'erp_qa_g3_g4'
C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit tests/Feature/G3G4OperationsMySqlTest.php
Remove-Item Env:ERP_G3_G4_TEST_DATABASE
```

No ejecutar `migrate:fresh` sobre la base de trabajo para preparar una prueba.

## 13. Límites y trabajo que no se certifica con esta etapa

- Se completó este bloque de ocho puntos; esto no es una declaración de que todo el ERP carece de pendientes. Queda el análisis general que se realizará en una etapa posterior.
- Los cuatro fallos de pruebas antiguas de G4 siguen identificados; no se ocultaron ni se corrigieron cambiando pruebas ajenas a este alcance.
- No se regularizaron pagos, fases pagadas ni avisos de stock históricos sin evidencia suficiente.
- No se implementó emisión/anulación fiscal ni devoluciones fiscales de ventas facturadas.
- No se hizo un ensayo masivo de rendimiento, ni una prueba de corte eléctrico en dos servidores remotos. La persistencia, exclusión por UUID, recuperación y concurrencia descriptas sí se probaron con los casos indicados.
- El reintento automático necesita un scheduler activo. El modo interno habitual no depende de ese proceso para confirmar una operación correcta.
- No se agregaron inventarios fraccionarios: el esquema vigente trabaja con unidades individuales enteras.
- No se certificó el comportamiento de un receptor G3 antiguo que ignore `request_key`. La idempotencia requiere este contrato en el receptor.

## 14. Cómo revertir esta etapa

### Antes de revertir

Detener la operación y respaldar los datos actuales. Revisar si ya existen operaciones nuevas, pagos o reversiones desde esta corrección. El respaldo anterior no contiene esas transacciones posteriores.

Los siguientes archivos del directorio de evidencia permiten una reversión selectiva:

- `codigo-antes/`: versiones al inicio de esta etapa, que ya contienen el trabajo anterior.
- `estado-inicial.json`: hashes de esas versiones.
- `manifest-reversion.json`: listado de archivos modificados/agregados en esta etapa y hashes finales.
- `cambios-de-esta-etapa.patch`: diferencias de esta etapa respecto de ese respaldo, no respecto de HEAD.
- `proyecto-antes.sql`: respaldo de ambas bases antes de la migración.
- `proyecto-despues.sql`: respaldo posterior a la validación.
- `columnas-y-datos-previos.privado.json`: comparación de las columnas y registros conservados.

### Código

1. Usar `manifest-reversion.json` para identificar los archivos de **esta etapa**.
2. Para una entrada `restore_existing`, copiar su versión de `codigo-antes/` a la misma ruta de la aplicación, después de comprobar que no haya cambios posteriores que también deban conservarse.
3. Para una entrada `new_file_review`, archivar o retirar ese archivo nuevo solo después de retirar las referencias que lo usan. Revisar particularmente la migración, comando, servicios, plantilla PDF y pruebas.
4. Restaurar también los dos servicios de G2 que llaman a la comprobación de G4; no dejar llamadas a un servicio retirado.
5. Limpiar las cachés de Laravel con `C:\xampp\php\php.exe artisan optimize:clear` y verificar las rutas y recorridos que se decida mantener.

**No usar `git reset --hard`, `git restore .` ni restaurar toda la carpeta desde HEAD para deshacer solo esta etapa.** Eso también descartaría modificaciones anteriores que no forman parte de esta corrección.

### Base de datos

La opción de menor impacto al volver al código anterior es **conservar las dos tablas de seguimiento**: el código anterior no las usa. No se ejecutó ninguna modificación destructiva de los datos anteriores que necesite deshacerse para recuperar ese código.

No usar rollback como si fuera una eliminación automática: el `down()` conserva los registros financieros/operativos por diseño. No borrar la fila de `migrations` mientras se conservan datos que dependen del código nuevo sin revisar el próximo despliegue.

Solo retirar `g4_stock_operations` y `g3_stock_requests` después de exportarlas, confirmar que no haya ajustes pendientes, resolver los reenvíos y verificar que ninguna instancia siga usando el contrato nuevo. En especial, borrar recibos de G3 mientras un emisor conserva solicitudes pendientes puede permitir repetir descuentos de stock.

Restaurar `proyecto-antes.sql` es una recuperación completa de esas bases al momento del respaldo, **no** una reversión selectiva de esta migración. Puede eliminar pagos, clientes, productos o movimientos cargados después. Requiere evaluar y conservar previamente esas operaciones; no se debe importar indiscriminadamente sobre una base compartida en uso.

Los registros generados en la base aislada `erp_qa_g3_g4` no necesitan trasladarse ni revertirse en la base de trabajo.


## 15. Estado al finalizar

Los servidores MySQL original (3306) y de prueba (3315) se apagaron normalmente después de los respaldos y las pruebas. No quedó ningún servidor Laravel levantado en 8000/8001. El usuario puede iniciar MySQL y Laravel manualmente como de costumbre.

El manifiesto final identifica **40 archivos: 30 existentes modificados y 10 nuevos**. `.env`, `.env.example`, `composer.json`, `composer.lock` y `phpunit.xml` conservan sus versiones del inicio de esta etapa. No se hizo commit ni push.
