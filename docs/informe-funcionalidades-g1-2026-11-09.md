# Funcionalidades pendientes de G1: implementación, pruebas y reversión

Fecha: 09/11/2026. Rama de trabajo: `develop`, sobre `060cb9badcb2ff5bc9301975edf038f27fd1091d` y los cambios locales previos.

## 1. Alcance y resultado

Se implementaron los nueve incisos del apartado **Funcionalidades pendientes de G1**. La autorización de esta etapa permite modificar otros grupos cuando el circuito lo requiere. Se conservaron los cambios locales de la etapa anterior; no se hizo commit, push ni mezcla de otra rama.

Esta entrega cubre el recorrido nuevo de G1 y las ampliaciones financieras necesarias de G4. No certifica que todo el ERP esté terminado. Permanecen trabajos de otras etapas y cuatro fallos heredados de pruebas de G4, especificados al final.

| Prioridad original | Inciso | Qué faltaba o estaba mal | Solución y resultado |
| --- | --- | --- | --- |
| Alta | Historial de pagos | Los consumidores podían quedarse en la primera página y una rendición aprobada podía confundirse con un pago aplicado. | Lectura completa de páginas; pagos aplicados certificados por su origen; rendiciones separadas y estados explícitos en la ficha. |
| Alta | Pagos parciales | Faltaba un recorrido completo y consistente desde la ficha hasta caja, cuenta corriente y auditoría. | Formulario de G1 y caso de uso de G4 compartido con una API nueva. Transacción única, deuda restante, validaciones e idempotencia. |
| Alta | Notificaciones de transferencia, HU #120 | No existía el circuito local completo de aviso con comprobante y validación. | Módulo `NotificacionesTransferencia`, archivo privado, listado filtrable, revisión administrativa y vinculación explícita con un pago. |
| Alta | Ventas diarias, HU #119 | El historial general no era un consolidado diario con cantidad e importes. | Reporte por fecha, operaciones e importes; excluye pendientes y canceladas; estado vacío. |
| Media | Caja diaria | Existía la API de G2, pero faltaba el consumidor y la presentación de G1. | Lectura de la API real de G2, verificación de sus movimientos y totales, filtros y exportaciones. |
| Media | Stock bajo | G1 no tenía una consulta completa de disponibilidad, mínimos y ordenamiento. | Consumo de productos de G3, activos con disponibilidad menor o igual al mínimo, orden ascendente/descendente y agotados identificados. |
| Media | Vencimientos | No había ventanas de 7, 15 y 30 días y faltaban fechas en ventas antiguas. | Consulta de pendientes actuales de G4; ventanas inclusivas, vencidas y fechas desconocidas separadas; fecha opcional en el alta de venta. |
| Media | Exportaciones | PDF/Excel estaban concentrados en el historial del cliente. | Descargas de siete reportes adicionales, con filtros, detalle completo y resúmenes cuando corresponden. Protección contra fórmulas de Excel. |
| Media | Recomendaciones | Un agotado sin alternativas no tenía explicación explícita. | Mensaje de ausencia de alternativas; si falla el proveedor, se informa otra situación y se ofrece reintentar. |

## 2. Respaldo y aplicación a la base local

Antes de esta etapa se guardaron código, configuración y las dos bases del proyecto en una carpeta externa al repositorio:

```text
C:\Users\juanf\OneDrive\Escritorio\Repositorio\Entregables\Funcionalidades_G1_2026-11-09
```

Elementos para recuperar el estado anterior:

- `codigo-antes/`: copia de archivos versionados y no ignorados que existían al comenzar, incluido trabajo local anterior.
- `estado-inicial.json`: huellas SHA-256 de esos archivos.
- `cambios-previos.patch`: diferencias que ya existían antes de esta etapa.
- `env-antes.privado`: configuración anterior; contiene secretos y no debe compartirse.
- `proyecto-antes.sql`: respaldo lógico de `sistema_gestion` y `sistema_gestion_g1_g3_20261026`.
- `columnas-y-datos-previos.privado.json`: columnas y huellas de los registros anteriores.
- `manifest-reversion.json` y `plan-reversion.txt`: lista selectiva para revertir esta etapa, sin descartar la anterior.

El manifiesto final identifica **54 archivos de esta etapa: 32 con una versión anterior para restaurar y 22 nuevos para revisar antes de retirar**. Incluye este informe y el README; no incluye `.env`, que tiene su respaldo privado separado.

Primero se usó una copia aislada de MariaDB, puerto **3314**, tomada del respaldo físico verificado de la etapa anterior. Se probaron migraciones, escrituras reales y preservación de datos. También se migró desde cero una base independiente, `erp_g1_instalacion_limpia`.

Después se ejecutó `erp-install.php` contra la base local, puerto **3306**. Sólo estaban pendientes las dos migraciones de esta etapa. Se aplicaron correctamente y la configuración agregó `CASH_TRANSPORT=internal`; se conservó `APP_KEY` y se reutilizó la cuenta técnica existente #22.

La comparación posterior verificó **94 tablas de datos entre las dos bases, sin diferencias en sus registros ni en los valores de las columnas anteriores**. Las dos tablas `migrations` se excluyen de esa igualdad porque registran los pasos nuevos. Se agregaron cuatro tablas vacías; no se insertaron clientes, productos, ventas, pagos ni avisos de demostración en la base de trabajo. Las escrituras de prueba se realizaron en la copia y se revirtieron.

Los respaldos SQL, `.env`, copias físicas, recibos y evidencias con información de clientes permanecen fuera de Git. No deben publicarse junto con el código.

## 3. Archivos y responsabilidad por módulo

### 3.1 G1: clientes, reportes y catálogo

| Archivo o carpeta | Cambio |
| --- | --- |
| `app/Modules/Clientes/Controllers/ClienteController.php` | Consulta separada de saldo, pagos aplicados y rendiciones; disponibilidad del registro local de pago; precarga de aviso validado. Una falla opcional no impide abrir la ficha. |
| `resources/views/clientes/show.blade.php` | Formulario administrativo de pago, deuda restante, mensajes de validación y separación entre pagos aplicados y cobros de rendiciones. |
| `app/Modules/Reportes/Services/OperationalReportService.php` | Construye los cuatro reportes nuevos y los conjuntos completos para siete exportaciones, usando los contratos de origen. |
| `app/Modules/Reportes/Controllers/OperationalReportController.php` | Respuestas web y JSON de los reportes nuevos; distingue indisponibilidad de ausencia de resultados. |
| `app/Modules/Reportes/Requests/OperationalReportRequest.php` | Validación de fechas, ventanas, orden, producto y período. |
| `app/Modules/Reportes/Controllers/ReportExportController.php` | Exportaciones nuevas con filtros y resúmenes; mantiene el historial del cliente. |
| `app/Modules/Reportes/Services/SpreadsheetExportService.php` | XLSX con tipos seguros, encabezados y resumen; los textos externos se escriben como texto literal. |
| `resources/views/reports/operational.blade.php` | Vista común de G1: filtros, indicadores, tabla, vacío, indisponibilidad y descargas. |
| `resources/views/reports/index.blade.php` | Nueve accesos de consulta de G1, incluidos caja, vencimientos y avisos. |
| `resources/views/reports/partials/exports.blade.php` | Botones de descarga reutilizables, conservando filtros. |
| `resources/views/reports/{debtors,product-ranking,client-product-reach,pdf}.blade.php` | Botones de exportación y resúmenes del PDF. |
| `app/Modules/Catalogo/Controllers/CatalogoController.php`, `resources/views/catalogo/show.blade.php` | Diferencian producto agotado sin alternativas de error al consultar recomendaciones. |
| `app/Modules/NotificacionesTransferencia/` | Módulo nuevo: controlador, modelo, request, servicio, rutas y migración propios. |
| `resources/views/transferencias/index.blade.php` | Registro, filtros, lista, descarga y revisión de avisos usando el layout de G1. |
| `resources/views/partials/form-errors.blade.php` | Presentación reutilizable de errores de formularios. |
| `resources/views/layouts/app.blade.php`, `resources/views/partials/sidebar-links.blade.php` | Mensajes de éxito y selección activa del área Reportes cuando se consultan transferencias. |
| `routes/web.php`, `routes/api.php` | Registro de las consultas nuevas y acceso al pago desde la ficha, con sus permisos. |
| `app/Services/Gateways/{ExternalFinanceGateway,RendicionesGateway,ExternalCatalogGateway,HttpSalesGateway}.php` | Consumo hasta la última página en pagos, rendiciones, productos, ranking y compradores. |
| `config/services.php`, `.env.example`, `app/Console/Commands/ConfigureIntegration.php` | Configuración del consumidor de caja y modo interno local. |
| `phpunit.xml`, `tests/Feature/` | Aislamiento HTTP de fuentes externas, 23 pruebas nuevas y ajuste de fixtures existentes al contrato paginado. |
| `README.md` | Uso, actualización y referencia a este informe. |

### 3.2 G4: cambios expresamente realizados

Sí se agregaron componentes de otro grupo, con la autorización actual. No sería correcto afirmar que esta etapa sólo modifica G1.

| Archivo | Motivo |
| --- | --- |
| `app/Modules/CuentaCorriente/Services/ClientPaymentService.php` | El responsable de aplicar un pago a la cuenta es el dominio financiero. Centraliza saldo, caja, registro, auditoría e idempotencia. |
| `app/Modules/CuentaCorriente/Requests/ClientPaymentRequest.php` | Autorización administrativa y validación de importe, medio, referencia, UUID y aviso opcional. |
| `app/Modules/CuentaCorriente/Controllers/ClientPaymentController.php` | Una implementación para la ficha web de G1 y la API financiera de G4. |
| `app/Modules/CuentaCorriente/Services/FinancialReadService.php` | Expone la vinculación, referencia y actor de pagos nuevos y permite correlacionar cobros históricos de rendiciones con movimientos aplicados. |
| `app/Modules/CuentaCorriente/routes/api.php` | `POST /api/v1/finanzas/clientes/{cliente}/pagos`, sólo para administrativo real. |
| `app/Modules/CuentaCorriente/database/migrations/2026_11_09_000001_create_client_payment_records.php` | Tablas de pagos y eventos financieros, sin reescribir movimientos anteriores. |
| `resources/views/ventas/ventas.blade.php` | Fecha de vencimiento opcional al registrar una venta. Usa el campo que el backend de G4 ya admitía desde la etapa anterior. |

La ficha de G1 y la API llaman el mismo **servicio de aplicación de G4 dentro de Laravel** para registrar el pago. Esta escritura no se implementó como una llamada HTTP ni como un consumidor GET. Las lecturas sí continúan usando las APIs y sus transportes configurados.

### 3.3 G2 y G3

En esta etapa no se editaron archivos fuente, migraciones ni esquemas existentes de G2 o G3. Se reutiliza `CajaService::registrarCobro` de G2 para el asiento de caja y su API diaria para las consultas. Se consume la API de productos de G3 para disponibilidad y mínimos.

Registrar un pago nuevo sí produce un movimiento real en la tabla de Caja de G2, además del movimiento de Cuenta Corriente de G4. Eso forma parte del circuito autorizado; no es una modificación del código de G2. Las pruebas de esas escrituras se hicieron en la copia aislada.

Los cambios anteriores de otros grupos siguen en el árbol local y tienen su informe propio: `informe-bloqueos-generales-e-integracion-2026-11-09.md`. No hay que confundir el diff completo respecto de Git con los archivos agregados en esta etapa.

## 4. Historial de pagos: reglas y paginación

Los consumidores usan `getList` para recorrer las páginas anunciadas por el origen. En pagos, rendiciones y fuentes financieras se solicita hasta 100 elementos por página cuando el contrato lo admite. Se comprueban metadatos consistentes, última página, cantidad total cuando se informa y ausencia de IDs duplicados en las colecciones que lo requieren.

Se mantiene un límite de **100 páginas por consulta**. Si se supera o el proveedor cambia/interrumpe la paginación, se informa error en lugar de mostrar una suma parcial como si fuera completa. Un contrato sin `meta` debe entregar una lista completa; el consumidor no puede descubrir por sí mismo una truncación que el proveedor no declara.

La ficha distingue:

| Situación | Interpretación |
| --- | --- |
| Declarado, pendiente de aprobación | El cobro figura en una rendición, pero no acredita aplicación financiera. |
| Rendición aprobada, sin aplicación informada | La aprobación del conjunto no demuestra que cada cobro haya sido aplicado. |
| Rechazado | Se conserva como información de la rendición; no se suma como pago aplicado. |
| Aplicado | El origen financiero certifica `confirmacion_individual: true` y existe un movimiento efectivamente registrado. |

Los cobros de rendiciones que ya están aplicados se correlacionan por su identificador para evitar presentarlos como dos pagos distintos. No se calcula deuda restando nuevamente los cobros declarados: el saldo lo determina el origen financiero.

## 5. Pago parcial y consistencia

Recorrido de uso:

1. Iniciar sesión como administrativo y abrir su caja desde Caja y Tesorería.
2. Abrir la ficha de un cliente con deuda.
3. Completar importe, medio y referencia. El formulario genera un UUID de solicitud.
4. Confirmar. El servidor recalcula la deuda; no confía en el máximo ni en el saldo mostrado por el navegador.
5. Consultar deuda restante, historial y el movimiento de caja resultante.

Dentro de una transacción se bloquea el cliente, se valida la deuda, se comprueba la solicitud, se bloquea la caja abierta que usa G2 y se registra:

- Un ingreso real mediante el servicio de Caja de G2.
- Un movimiento positivo de pago en `cuenta_corriente_movimientos`.
- Un registro inmutable de aplicación en `cuenta_corriente_pagos`, con ambas referencias, actor y saldos anterior/restante.
- Un evento obligatorio `pago_aplicado` en `cuenta_corriente_pago_eventos`.

Si falla un paso, se revierte todo. La prueba de fallo provocado después de la escritura en caja comprobó que no quedaran movimientos ni auditorías parciales.

El importe debe ser positivo, tener hasta dos decimales, no exceder $99.999.999,99 ni superar la deuda. No se acepta registrar por este formulario anticipos o saldo a favor. Se rechazan clientes inactivos y usuarios sin caja abierta.

Una repetición con el mismo `request_key`, actor, cliente, importe, medio, referencia y aviso devuelve el pago existente (`200`); la primera aplicación devuelve `201`. Reutilizar la clave con otros datos produce `409`. La tabla impone unicidad, además de las comprobaciones del servicio.

La escritura web se habilita cuando el origen de saldos es `internal`. Si se conecta un origen financiero HTTP independiente, el formulario queda deshabilitado: no sería correcto consultar una deuda remota y escribir el pago en una cuenta local distinta. No se inventó una delegación remota de escritura.

## 6. Transferencias: aviso, archivo y revisión

Se consultaron en GitHub la [HU #120](https://github.com/rapiriz/ERP-Juan23/issues/120) y la [HU #119](https://github.com/rapiriz/ERP-Juan23/issues/119). La rama remota histórica `feature/------AXEL-G1` también contenía vistas y controlador de reportes; se aprovecharon como referencia funcional. Se desarrolló la implementación modular actual en lugar de copiar consultas directas antiguas a tablas de otros dominios.

El aviso exige cliente, fecha no futura, importe, referencia y comprobante. Se aceptan PDF, JPEG o PNG, comprobando tipo real y extensión, con máximo de **5.120 KiB (5 MiB)**. Se rechazan contenido incompatible, archivo ausente, importe inválido y fecha futura.

Los comprobantes se guardan con nombre generado en el disco privado `local`, actualmente `storage/app/private/transferencias`. No se publican en `public/storage`. Se conserva nombre original, tamaño, MIME y SHA-256 para trazabilidad. El JSON público de este módulo no expone la ruta interna ni la huella.

La descarga exige autenticación y permiso, se entrega como adjunto y lleva `no-store`, `nosniff` y política de contenido restrictiva. Si falla la transacción de alta, se elimina el archivo nuevo creado por ese intento.

Estados: `pendiente` → `validada` o `rechazada`. Se exige nota de revisión y se registra actor y evento. Una repetición del mismo estado no genera otro evento; intentar cambiar un estado terminal por otro produce conflicto.

**Ni registrar ni validar el aviso acredita dinero.** Una vez validado, el administrativo abre la ficha y aplica el pago expresamente. El servicio comprueba que cliente e importe coincidan, que el medio sea transferencia y que el aviso no tenga ya otro pago. `transferencia_id` es único en los pagos para impedir una segunda aplicación con una clave de solicitud diferente.

El archivo es evidencia aportada, no una confirmación bancaria automática. No se agregó conciliación automática, validación contra un banco, antivirus externo ni un acceso de cliente final que no exista en el modelo de usuarios actual.

## 7. Reportes: fuente y significado

| Reporte | Fuente | Reglas |
| --- | --- | --- |
| Ventas diarias | G4, `GET /api/ventas`, todas sus páginas con `desde`/`hasta` | Fecha argentina; confirmadas y posteriores; pendientes/canceladas excluidas; operaciones, total y subtotal en estado facturada. Este estado no certifica emisión fiscal. |
| Caja diaria | G2, `GET /api/v1/caja/{fecha}` | Usa su respuesta real sin envoltorio `data`; comprueba fecha, cantidad de movimientos y sumas en centavos. Neto = ingresos − egresos, sin sumar aperturas de otros días. |
| Stock bajo | G3, lista pública configurada en `services.catalog.products_path` | Sólo activos, disponibilidad ≤ mínimo; agotado si disponibilidad = 0; los datos ausentes no se convierten en cero. |
| Vencimientos | G4, `GET /api/v1/finanzas/facturas/pendientes` | Ventanas inclusivas de 7/15/30 días desde la fecha seleccionada, más vencidas y fechas desconocidas identificadas. |
| Deudores | G4, API financiera de deudores | Deuda positiva y clientes presentes en G1; orden mayor/menor. |
| Ranking comercial | Fuente de ventas de G4 o ranking dedicado configurado | Cantidades e importe neto del criterio acordado; período conservado. Se completan todas las páginas también si hay un endpoint dedicado. |
| Clientes por producto | Productos G3 + ventas/compradores G4 | Compradores identificados, denominador de clientes de G1 y cobertura global del producto. |

Vencimientos muestra **pendientes actuales**, no una reconstrucción de deuda a una fecha histórica. No se inventan fechas para ventas antiguas. El alta de venta permite indicar vencimiento; el servidor existente lo valida y conserva.

Los reportes vacíos muestran una explicación. Si el origen falla, la vista muestra “Sin datos disponibles” y reintento; no se reemplaza el error por importes o cantidades cero. Las APIs y exportaciones conservan el error `502/503` correspondiente.

El esquema visual usa el layout compartido y componentes de G1, con menú lateral, encabezado, filtros, tarjetas, tablas y mensajes. Se revisó el render HTML, una captura de la pantalla de transferencias y páginas PDF de stock/vencimientos. La captura es una comprobación visual; no equivale a una prueba manual de todos los navegadores o tamaños.

## 8. APIs y permisos incorporados

Base local: `http://127.0.0.1:8000`. Todas las rutas de esta tabla requieren autenticación; el catálogo público conserva su régimen anterior.

| Método | Ruta | Consulta/cuerpo | Permisos |
| --- | --- | --- | --- |
| GET | `/api/v1/reportes/ventas-diarias` | `fecha=YYYY-MM-DD` | Administrativo, contador o token técnico autorizado. |
| GET | `/api/v1/reportes/caja-diaria` | `fecha=YYYY-MM-DD` | Igual. |
| GET | `/api/v1/reportes/stock-bajo` | `orden=asc` o `desc` | Igual. |
| GET | `/api/v1/reportes/vencimientos` | `fecha=YYYY-MM-DD&dias=7`, `15` o `30` | Igual. |
| GET | `/api/v1/transferencias` | `estado=pendiente/validada/rechazada/todas`, `cliente_id`, `page`, `per_page` | Administrativo, contador o token técnico autorizado. |
| GET | `/api/v1/transferencias/{id}` | — | Igual. |
| GET | `/api/v1/transferencias/{id}/comprobante` | Descarga privada | Igual. |
| POST | `/api/v1/transferencias` | Multipart: `request_key`, `cliente_id`, `fecha`, `monto`, `referencia`, archivo `comprobante` | Usuario administrativo activo. |
| PATCH | `/api/v1/transferencias/{id}/revision` | JSON: `estado=validada/rechazada`, `nota` | Usuario administrativo activo. |
| POST | `/api/v1/finanzas/clientes/{id}/pagos` | JSON: `request_key`, `monto`, `medio_pago`, `comprobante` opcional, `transferencia_id` opcional | Usuario administrativo activo. |

El token técnico de servicio no habilita las escrituras nuevas. Un contador puede consultar, pero no registrar pagos, cargar avisos ni revisarlos. La web usa sesión vigente, autorización y CSRF; las APIs mantienen los middleware de sesión/Bearer del proyecto. Hay límites de solicitudes en las rutas nuevas.

Ejemplo de pago, después de obtener un Bearer de usuario administrativo válido y abrir su caja:

```http
POST /api/v1/finanzas/clientes/1/pagos
Authorization: Bearer <token_de_usuario>
Accept: application/json
Content-Type: application/json

{
  "request_key": "b13a1ea3-7953-4faa-a774-dfe44cad453f",
  "monto": "50.00",
  "medio_pago": "efectivo",
  "comprobante": "Recibo de prueba"
}
```

Usar un cliente con deuda real suficiente. Cada operación nueva debe usar otro UUID. Para comprobar idempotencia, repetir exactamente el mismo cuerpo con la misma clave. Esto registra dinero real en la base seleccionada; no es una simulación.

## 9. Exportaciones

Ruta web autenticada:

```text
/reportes/exportar/{reporte}/{formato}
```

Reportes: `daily-sales`, `daily-cash`, `low-stock`, `due-invoices`, `debtors`, `product-ranking`, `client-product-reach`. Formatos: `pdf`, `xlsx`. Se mantienen las descargas existentes de historial del cliente.

Los enlaces de cada pantalla conservan los filtros relevantes. La paginación visual no limita el archivo exportado. Se incluyen resúmenes en los reportes que los tienen: el PDF los muestra antes del detalle y el XLSX debajo de éste. La cobertura del producto es un porcentaje global, no un importe monetario por cliente.

Los nombres, documentos, títulos y otros textos se escriben como texto explícito en Excel. Una cadena externa como `=HYPERLINK(...)` se conserva como texto y no se ejecuta como fórmula. Los valores numéricos reales permanecen numéricos.

## 10. Migraciones y efecto de compartir el código

| Migración nueva | Tablas creadas |
| --- | --- |
| `app/Modules/CuentaCorriente/database/migrations/2026_11_09_000001_create_client_payment_records.php` | `cuenta_corriente_pagos`, `cuenta_corriente_pago_eventos`. |
| `app/Modules/NotificacionesTransferencia/database/migrations/2026_11_09_000002_create_transfer_notifications.php` | `g1_transferencias`, `g1_transferencia_eventos`. |

Ambas comprueban si las tablas existen antes de crearlas. No eliminan ni recrean tablas anteriores, no actualizan datos históricos y no insertan demostraciones. Sus `down()` son conservadores: no destruyen registros financieros, auditorías ni avisos. Para revertir esquema y datos se usa el respaldo coherente.

No se agregaron claves foráneas entre dominios en estas tablas nuevas para evitar imponer tipos incompatibles a identidades existentes. Las relaciones y permisos se verifican en el servicio y las referencias de aplicación tienen índices/únicos propios. Esto no reemplaza una futura política integral de integridad referencial para todo el ERP.

Un commit/push comparte estos archivos si se incluyen. No transporta la base MySQL local. Al bajar los cambios y ejecutar `migrate` o `erp-install.php`, otros equipos **sí crearán estas cuatro tablas** en la base configurada y `erp:configure` ajustará la configuración local de integración. Debe revisarse y respaldarse esa base antes. Esta entrega no debe describirse como “sin efecto posible sobre otros grupos”.

## 11. Pruebas y evidencias

### 11.1 Pruebas automatizadas

| Comprobación | Resultado final |
| --- | --- |
| Pruebas funcionales seleccionadas de esta entrega y sus contratos/regresiones | **75 pruebas, 661 aserciones, todas correctas**. |
| Pruebas nuevas dentro de ese conjunto | **23**: 11 de reportes, 8 de pagos/transferencias y 4 de historial/recomendaciones. |
| Unitarias | **101 pruebas, 289 aserciones, todas correctas**. |
| Frontend Node | **34 pruebas, todas correctas**. |
| Revisión general con el mismo perímetro seguro de la etapa anterior | **451 pruebas, 2.274 aserciones; 4 fallos heredados y 1 advertencia de prueba riesgosa**. No es una suite completamente verde. |
| PHP de esta etapa | Sintaxis de 36 archivos sin errores; formato Pint aplicado. |
| Instalación desde base vacía MariaDB | Migraciones completas correctas en `erp_g1_instalacion_limpia`, en el servidor aislado. |
| Actualización local existente | Dos migraciones aplicadas, clave de aplicación conservada y 94 tablas anteriores verificadas sin diferencias. |
| Instalación repetida | `Nothing to migrate`; conserva la misma cuenta técnica existente. |
| Estado de tablas MariaDB | `mysqlcheck` de las dos bases correcto, salida 0. |
| Dependencias | `composer audit --locked`: sin avisos ni paquetes abandonados en la comprobación de cierre. |

Los 75 tests incluyen paginación completa, estados de pago, importes, fechas inválidas, reportes vacíos, respuestas incompletas, totales de caja inconsistentes, permisos, PDF/XLSX, fórmula inyectada, pago parcial/completo, repetición de claves, aviso privado, MIME/tamaño, revisión y doble aplicación de una transferencia. También se verifica reversión total cuando se fuerza un fallo después del movimiento de caja.

Los cuatro fallos generales ya estaban en el corte anterior:

- Tres pruebas de `G4DependencyRegistrationTest`: expectativas antiguas de vistas o mocks de Query Builder que no corresponden a las consultas actuales.
- Una de `G4SalesSchemaTest`: espera el nombre `ventas` cuando la vista modular vigente es `ventas.ventas`.
- La advertencia de prueba riesgosa acompaña a una de esas pruebas por manejadores de errores/excepciones no retirados.

Se mantuvieron las siete exclusiones anteriores de pruebas heredadas que pueden acceder directamente a MySQL o requieren entornos propios: `ConteoFisicoTest`, `G3IntegracionGruposApiTest`, `G3ViewsTest`, `HistorialPrecioProveedorTest`, `ProductoCostoRecargoTest`, `UserStoriesFrontendTest`, `VentasApiTest`. No se ejecutaron indiscriminadamente contra la base de trabajo. El archivo de configuración seguro y los logs están en la carpeta de evidencias.

### 11.2 Circuito con MariaDB real, sin mocks

En la copia aislada se ejecutaron **24 solicitudes de integración mediante el kernel real de Laravel**, con las rutas, middleware, APIs y servicios del proyecto:

1. Alta de categoría, marca, cliente y producto por sus APIs; producto con 20 unidades y mínimo 25.
2. Venta de 2 unidades, precio $100, con vencimiento: stock real restante **18**, deuda **$200**.
3. Consulta y transición de estado desde el cliente interno de G2 hacia Ventas.
4. Apertura de caja mediante el servicio real de G2.
5. Pago desde la ruta de la ficha de G1 de **$50**: deuda **$150**.
6. Registro de aviso de transferencia de **$20**, con un PDF privado, usando Bearer de usuario.
7. Revisión a validada: deuda se conserva en **$150**.
8. Aplicación explícita del pago asociado: deuda **$130**.
9. Repetición de la solicitud: respuesta `200`, sin duplicar caja, cuenta ni auditoría.
10. Consulta de saldo, deudores, pendientes, pagos, stock, catálogo y los cuatro reportes nuevos.

Los datos del circuito se revirtieron mediante transacción y el comprobante de prueba se retiró del almacenamiento privado. La comparación posterior de las 94 tablas originales no encontró diferencias.

### 11.3 Consultas sobre la base de trabajo

Tras la actualización se verificaron ocho pantallas autenticadas y ocho consultas de servicio, todas con `200`. Las cuatro pantallas operativas indicaron fuente disponible. Tres consultas privadas sin identidad devolvieron `401`: clientes, caja diaria y transferencias. Salud y clientes por lista/ID siguieron respondiendo con identidad válida.

Las siete exportaciones se pidieron en PDF y XLSX contra la base local: **14 respuestas `200` con archivos generados**. Los PDFs de stock y vencimientos se renderizaron para comprobar encabezados, resúmenes, acentos y tablas. Los pendientes actuales pueden no caer dentro de 7 días aunque existan comprobantes: se verificó también la ventana de 30 días.

Archivos de evidencia: `circuito-real.json`, `pantallas-y-apis-base-local.json`, `exportaciones-base-local.json`, `preservacion-base-local.json`, `regresiones-funcionales-final.txt`, `pruebas-unitarias-final.txt`, `pruebas-frontend-final.txt`, `pruebas-generales-final.txt` y logs de instalación.

## 12. Límites y trabajo que no debe darse por cerrado

- La consistencia probada corresponde al **nuevo recorrido G1 → ClientPaymentService**. La ruta antigua de movimientos de Cuenta Corriente de G4 sigue existiendo y no fue sustituida por este caso de uso; no debe suponerse que cualquier ruta histórica tiene la misma auditoría o vinculación con caja.
- El nuevo pago bloquea cliente y caja y evita duplicaciones de sus propias solicitudes. No se certificó concurrencia frente a todos los escritores externos del ERP. En particular, los cierres de caja y otros circuitos antiguos de G2 requieren una revisión conjunta de bloqueos, reintentos e idempotencia en su etapa correspondiente.
- Un aviso validado no prueba liquidación bancaria. La conciliación y confirmación de fondos siguen siendo procesos separados.
- Los vencimientos son los informados por G4. Los históricos sin fecha permanecen identificados como desconocidos; no se infieren plazos comerciales.
- La fecha de un reporte de vencimientos delimita la ventana, pero el importe es el pendiente actual, no el de una foto histórica de deuda.
- El estado `facturada` de Ventas no demuestra emisión fiscal. No se incorporó un proveedor fiscal.
- No se implementó escritura financiera remota en la ficha cuando el origen de deuda es HTTP; requiere un contrato de escritura y autenticación acordado.
- No se agregaron edición/borrado de pagos aplicados ni eliminación de auditorías. Una futura corrección financiera debe usar un circuito explícito de reversión, no borrar registros.
- Exportaciones y consultas completas tienen el límite de paginación indicado y se construyen en memoria. Grandes volúmenes pueden requerir tareas en segundo plano y exportación por lotes.
- Continúan los cuatro fallos heredados de pruebas y las exclusiones señaladas. La recuperación previa de MySQL tampoco permite certificar transacciones que ya estuvieran incompletas antes de recuperarlo.

## 13. Cómo probarlo manualmente

1. Iniciar MySQL en XAMPP y la web con `C:\xampp\php\php.exe -d extension=gd -d extension=zip artisan serve`. No hace falta Apache para los consumidores locales `internal`.
2. Como administrativo, abrir **Reportes y Finanzas** y consultar fecha, orden y ventanas de los reportes. Descargar PDF y Excel de cada uno.
3. Abrir una caja del usuario y un cliente con deuda. Registrar un pago menor que la deuda. Verificar saldo restante, historial y caja. Esto modifica datos reales.
4. Abrir `/transferencias`, registrar un archivo permitido y comprobar que no baja la deuda. Validarlo con nota y volver a comprobar. Abrir la ficha desde el aviso y registrar el pago asociado; ahí sí debe bajar la deuda.
5. Como contador, comprobar consulta/descarga y ausencia de controles de escritura. En Thunder Client, probar las rutas de la tabla con Bearer de usuario; sin identidad deben rechazar acceso privado.
6. Para una venta nueva, informar vencimiento opcional y consultar la ventana correspondiente. Los comprobantes sin fecha deben continuar diferenciados.
7. Abrir un producto agotado en el catálogo público: si no hay alternativas disponibles debe explicarlo; si falla la fuente debe informar el error en lugar de afirmar que no hay alternativas.

## 14. Cómo revertir esta etapa

La reversión debe hacerse con la aplicación detenida y respaldando antes cualquier operación que se haya registrado después de la entrega. Restaurar el SQL anterior descarta operaciones posteriores; no debe aplicarse sin evaluar esa pérdida. Las copias son locales y privadas.

### A. Revertir código conservando la etapa anterior

1. Consultar `manifest-reversion.json` y `plan-reversion.txt` en la carpeta externa de esta etapa.
2. Para archivos marcados `restore`, copiar exclusivamente la versión correspondiente de `codigo-antes/` a su ruta en el proyecto.
3. Para archivos marcados `new_file_review`, retirar sólo el archivo nuevo indicado si su huella coincide con la registrada. Si cambió después, revisarlo primero y conservar copia.
4. Restaurar `.env` desde `env-antes.privado` sólo si se necesita volver también a esa configuración. Limpiar configuración con `C:\xampp\php\php.exe artisan config:clear`.
5. No usar `git reset --hard`, `git clean` ni una copia completa de una rama anterior: podrían eliminar cambios locales que preceden a esta entrega.

El manifiesto se calcula comparando contra el inicio de esta etapa, no contra HEAD, para conservar el trabajo de bloqueos generales y seguridad que ya existía.

### B. Revertir base y archivos privados

1. Conservar un respaldo nuevo de las dos bases y de `storage/app/private/transferencias` antes de retroceder. No publicar los comprobantes.
2. Si no hubo operaciones posteriores que deban conservarse, restaurar `proyecto-antes.sql` mediante la herramienta de MySQL/phpMyAdmin utilizada para los respaldos, verificando antes el servidor y los nombres de base. El archivo contiene las dos bases del proyecto; no importarlo por error sobre otro entorno.
3. Si hubo pagos, avisos u otras operaciones nuevas, no importar ese SQL de forma directa. Preparar una recuperación selectiva que preserve asientos financieros, auditorías y comprobantes vinculados.
4. Tras restaurar un corte anterior sin avisos, los archivos nuevos que queden en almacenamiento privado deben archivarse o retirarse individualmente después de verificar que ya no estén referenciados. No borrar toda la carpeta privada del sistema.
5. Ejecutar sólo lecturas de comprobación: acceso, clientes, catálogo y reportes del corte anterior. Confirmar conteos y datos contra el respaldo.

`migrate:rollback` no es el mecanismo de reversión destructiva de esta entrega: los `down()` preservan las tablas con dinero y auditoría. Tampoco se debe ejecutar `migrate:fresh` ni borrar filas financieras a mano para simular una reversión.

### C. Servidores y cierre

Los servidores utilizados para la validación se detienen al cerrar la tarea. No se deja un `artisan serve` de prueba funcionando. Para continuar, el usuario inicia MySQL y el servidor web como de costumbre. No se enviaron cambios al repositorio remoto.
