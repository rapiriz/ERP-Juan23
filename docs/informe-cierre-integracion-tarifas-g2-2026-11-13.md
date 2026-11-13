# Cierre de integración de tarifas, ventas y entregas

## Versión revisada

Trabajo realizado sobre Branch-G1 el 13/11/2026, tomando como punto de partida el commit `9a492f3fe289346d8404b147a9a332d575bdea02`.

Se contrastaron las novedades de develop hasta `a69baf0d58e2e786537b81c78b89ef0ee732ef9b`. La incorporación de G2 se hizo adaptando sus recorridos al libro financiero y los servicios transaccionales de esta rama.

## Problemas y correcciones

| Problema encontrado | Corrección realizada | Archivos principales |
| --- | --- | --- |
| La casilla mayorista seleccionaba una tarifa, pero ambos precios provenían del mismo valor base | Se agregaron dos precios opcionales persistidos por producto, sus campos de formulario, salida de API, historial y tratamiento en aumentos masivos | `app/Modules/Productos/Models/Producto.php`, `Controllers/ProductoController.php`, `resources/views/inventario/index.blade.php` |
| La tarifa de una venta se intentaba inferir comparando precios actuales | Las altas y modificaciones guardan `venta.lista`; el JSON la devuelve y el editor la recupera | `app/Modules/Ventas/Controllers/VentaController.php`, `Services/VentaService.php`, `Resources/VentaResource.php`, `resources/views/historial-ventas/historial-ventas.blade.php` |
| Un cliente dado de baja podía llegar al cálculo de una venta | Se valida su estado al registrar o modificar, dentro del recorrido transaccional | `app/Modules/Ventas/Services/SalePricePolicy.php` |
| Un producto inactivo podía venderse o interrumpir la consulta completa de stock del POS/editor | El cálculo rechaza productos inactivos y los tres recorridos de carga de productos los filtran antes de consultar stock | `CalculadoraVenta.php`, `VentaController.php`, `VentaApiController.php` |
| El alias `stock` evitaba las validaciones de `stock_disponible` | Se normalizan ambos campos antes de guardar, validando enteros, límites y coincidencia cuando se envían los dos | `app/Modules/Productos/Controllers/ProductoController.php` |
| Las siguientes páginas de cobros podían perder `cliente_id` y responder 400 | Los enlaces agregan los filtros ya validados, incluyendo cliente, estado y tamaño de página | `app/Modules/Rendiciones/Controllers/Api/V1/CobroApiController.php`, `RendicionApiController.php` |
| Entregas recibía datos incompletos de dirección, zona y cantidades | Se amplió la proyección de Clientes/Ventas y el consumidor de G2; los bultos utilizan la presentación real del producto | `ClientResource.php`, `HttpClienteApi.php`, `HttpProductoApi.php`, `VentaResource.php`, `VentasApiClient.php`, `EntregaRepository.php`, `RemitoRepository.php` |
| La confirmación de entrega declaraba el importe completo del pedido | Se incorporaron importe cobrado y medio de pago, con entrega sin cobro y cobro parcial | `EntregaController.php`, `EntregaService.php`, `DeliveryPlanningService.php`, `public/js/g2-entregas.js`, `resources/views/entregas/index.blade.php` |
| Una rendición posterior podía omitir el saldo de un pedido parcialmente pagado | La preparación consulta la deuda efectiva, descuenta declaraciones pendientes y permite rendiciones sucesivas con importes editables | `app/Modules/Rendiciones/Services/RendicionService.php`, `public/js/g2-rendiciones.js` |
| La precarga podía sobrescribir información revisada o volver a declarar el mismo pedido | La precarga conserva las filas existentes y comprueba si el pedido ya fue declarado para esa entrega, incluso si después fue rechazado o anulado | `RendicionService.php` |
| Faltaba impresión de rendiciones con nombres reales | Se incorporó el comprobante imprimible de G2 adaptado, enriquecimiento de clientes/repartidor y ruta protegida por rol y propiedad | `RendicionController.php`, `routes/web.php`, `resources/views/rendiciones/imprimir.blade.php` |
| La revisión móvil encontró desbordamiento en indicadores y pestañas de rendiciones | Se permite contraer las tarjetas y desplazar horizontalmente las pestañas dentro de su contenedor | `public/css/g2.css`, `resources/views/rendiciones/index.blade.php` |

Las rutas de archivos abreviadas en la tabla corresponden al módulo indicado en esa fila.

## Precios comerciales

### Persistencia y compatibilidad

`producto.precio_unitario` conserva el precio base. Las columnas nuevas `precio_minorista` y `precio_mayorista` admiten `NULL`. Un campo vacío utiliza el precio base vigente. Los productos existentes conservan esa configuración inicial hasta que un administrativo cargue importes diferenciados.

Esta elección conserva los importes previos. La migración carece de un porcentaje comercial para generar una tarifa mayorista automáticamente. El responsable comercial puede completar cada precio desde Inventario.

La API entrega los precios efectivos en `precio_minorista`, `precio_mayorista`, `precioMin` y `precioMay`. El formulario interno recibe también `precio_minorista_configurado` y `precio_mayorista_configurado`, que permiten distinguir un valor guardado de la opción de seguir el precio base.

Alta y edición aceptan los alias `precioMin` y `precioMay`. Si se envían junto con el campo canónico, sus valores deben coincidir. Se admiten importes desde cero hasta `9999999999.99`, con dos decimales como máximo. El catálogo público utiliza el precio minorista efectivo.

### Aumentos e historial

Los aumentos sobre precio de venta respetan `tipo_precio`.

- `mayorista` modifica la tarifa mayorista.
- `minorista` modifica la tarifa minorista.
- `ambos` modifica el precio base y las tarifas explícitas. Las tarifas vacías continúan siguiendo el base.

Los cambios de costo/recargo conservan las tarifas explícitas. El precio base continúa su circuito previo de formación de precio. Cada tarifa explícita puede ajustarse por separado.

Las altas, ediciones y aumentos registran sus cambios en `historial_precio` con el tipo correspondiente. El historial por producto calcula variaciones dentro de cada tipo, evitando comparar una tarifa minorista con una mayorista. La auditoría del aumento incluye las tarifas anteriores y posteriores.

### Tarifa utilizada en ventas

Las nuevas ventas guardan `lista` con `minorista` o `mayorista`. La casilla mayorista exige un cliente mayorista y una decisión expresa del operador. Los importes se calculan con los precios del servidor.

Las ventas anteriores quedan con `lista = NULL`. El editor conserva sus precios originales mientras se consulta y exige elegir una tarifa antes de recalcular y guardar. Si el tipo actual del cliente impide usar la tarifa guardada, solicita una elección vigente. La comparación de precios deja de utilizarse para reconstruir esa decisión histórica.

La confirmación grande de bonificación completa incorporada en la etapa anterior sigue formando parte del alta y de la modificación.

## Estado de clientes, productos y stock inicial

El estado de cliente se verifica en la política de venta, junto al bloqueo del cliente usado por el recorrido financiero. También se utiliza su tipo actual para habilitar la tarifa mayorista.

La calculadora rechaza líneas con productos inactivos. Si un artículo histórico ya está fuera del catálogo activo, el editor conserva su precio e identificación originales al mostrarlo y solicita quitarlo antes de guardar nuevos cambios. El POS y el listado de productos para modificar ventas los excluyen antes de consultar disponibilidad, evitando convertir la baja de un artículo en la caída completa de la pantalla.

En el alta de producto, `stock` y `stock_disponible` se normalizan a un único valor. Se exige un entero entre cero y `2147483647`. Letras, negativos, fracciones y valores superiores se rechazan. Dos alias con valores diferentes también se rechazan. La creación de producto y su movimiento inicial continúan dentro de la transacción existente.

## Integración selectiva de G2

### Datos para entregas y remitos

La API de Clientes agrega `zona_nombre`. El consumidor de Ventas conserva dirección, calle, altura, localidad, teléfono y nombre de zona. La respuesta de Ventas entrega estos datos dentro de `cliente`.

Cada detalle incluye `unidades_por_bulto`, `bultos` y `sueltos`. G2 obtiene `total_articulos`, `total_bultos` y `total_sueltos` sumando las líneas. Una presentación de seis unidades permite expresar nueve unidades como un bulto y tres sueltas. La cantidad proviene del pedido.

Los datos de dirección o zona ausentes se muestran como pendientes de registrar. Se retiraron los textos fijos que atribuían a cualquier cliente Pigüé o Ruta Saavedra. El remito obtiene el DNI/CUIT real y el código comercial de cada artículo cuando están disponibles.

### Confirmación con cobro parcial

La hoja de ruta abre un formulario con importe y medio de pago. El endpoint conserva su URL.

```http
PATCH /g2/api/entregas/15/pedidos/42/entregado
Accept: application/json
Content-Type: application/json
```

```json
{
  "monto_cobrado": 3000.50,
  "medio_pago": "efectivo"
}
```

Se admiten `efectivo`, `transferencia` y `cheque`. Cero confirma la entrega física sin crear un nuevo cobro. Si una integración anterior omite el importe, se conserva la compatibilidad declarando el saldo disponible. La pantalla actual envía siempre el importe elegido.

La validación compara centavos contra la deuda efectiva de la factura y resta los cobros pendientes ya declarados. Un importe superior, negativo, inválido o con más de dos decimales produce 422. El rechazo conserva el estado del pedido y los registros anteriores.

La confirmación guarda importe y medio en `entrega_pedido`, cambia el estado logístico, actualiza el remito, precarga la declaración y registra auditoría en la misma transacción. Un reenvío con los mismos valores devuelve la operación existente. Un reenvío con un cobro diferente devuelve 409 y remite a registrar el saldo desde Rendiciones.

### Declaración, aplicación y saldo posterior

La preparación de rendición devuelve el total del pedido, el saldo disponible y el importe sugerido. Las filas editables permiten declarar importes parciales. La asociación se valida contra el pedido entregado, su cliente y la entrega seleccionada.

La primera precarga de una entrega conserva el importe informado por el chofer. Su repetición conserva la declaración original. Un cobro posterior del saldo se declara expresamente desde una nueva rendición o mediante el agregado de cobros a una pendiente.

La deuda restante utiliza `FinancialReadService`, que contempla pagos efectivos, reversos y créditos del circuito existente. Para las decisiones transaccionales se incorporó una lectura actual con bloqueos, evitando utilizar una fotografía anterior al bloqueo del cliente en MySQL. La validación de declaraciones pendientes también utiliza filas bloqueadas.

La aprobación individual continúa a cargo de `RenditionReviewService` y `CollectionSettlementService`. El circuito registra cobro, caja, cuenta corriente, vínculos de aplicación y auditoría. Un cheque exige completar sus datos desde Cobros antes de aplicarse. El historial mantiene diferenciados los estados pendiente, aplicado, rechazado y anulado.

Se conservaron los controles de usuario activo, rol, propiedad de la entrega/rendición, transacciones, incidencias, aprobación individual y prevención de duplicados. El transporte local integrado utiliza las APIs internas autenticadas de Laravel.

### Impresión

`GET /rendiciones/{id}/imprimir` requiere sesión vigente y acceso a la rendición. El botón se encuentra en su detalle. El comprobante incluye chofer, cliente, DNI/CUIT, factura, medio de pago, importe y estado de revisión. Sus totales se identifican como valores declarados y se explica cuándo se acreditan en el libro financiero.

### Decisiones al contrastar develop

Se aprovecharon la propuesta de campos de cobro en entregas, impresión, datos de clientes, cantidades y preparación de rendiciones por tramos. La adaptación conserva el libro financiero de Branch-G1 como fuente del saldo. También se mantuvieron los renderizadores con escape de HTML y la revisión individual existente.

La consulta diaria de movimientos de Caja ya utilizaba `fecha` en Branch-G1, coincidiendo con la corrección más reciente de develop. La búsqueda de clientes de rendiciones se amplió a dirección e identificador y conserva el filtro de clientes activos.

La documentación de G3 se contrastó con `docs/contrato-api-g3.md` de ese commit. Se actualizaron en el contrato local las tarifas diferenciadas y los ejemplos de consumo compatibles con autenticación, transporte interno, imágenes y stock de esta rama.

## Migraciones y actualización local

Se agregaron tres archivos en `database/migrations`.

| Archivo | Cambio |
| --- | --- |
| `2026_11_13_000001_add_commercial_prices_to_products.php` | Precios minorista y mayorista opcionales en producto |
| `2026_11_13_000002_add_price_list_to_sales.php` | Lista comercial opcional en venta |
| `2026_11_13_000003_add_collected_amount_to_delivery_orders.php` | Importe cobrado y medio de pago opcionales en entrega_pedido |

Los archivos verifican si la tabla y cada columna existen. Sus métodos `down` conservan las columnas y sus valores para permitir retroceder el código preservando los registros comerciales.

Se aplicaron estas tres migraciones a la base local configurada después de generar un respaldo SQL completo. La comparación de las columnas originales verificó **100 tablas, 1.441 registros y cero diferencias en los datos anteriores**. Los nuevos campos históricos quedaron vacíos según su significado. El respaldo privado y el registro de verificación quedaron fuera del repositorio ERP, en `Entregables/Branch_G1_Cierre_2026-11-13`.

Para otra instalación, realizar el respaldo y ejecutar el instalador habitual desde la raíz del ERP.

```powershell
C:\xampp\php\php.exe erp-install.php
```

La actualización requiere publicar código y migraciones juntos. Las columnas nuevas deben estar disponibles antes de utilizar las nuevas pantallas y escrituras.

## Verificación

| Comprobación | Resultado |
| --- | --- |
| Instalación sobre base MySQL QA vacía | `erp-install.php` completó 66 migraciones y configuró la cuenta técnica en una copia de código con dependencias reutilizadas |
| Actualización de copia QA anterior con datos | 84 tablas y 53 registros anteriores conservados, con huellas idénticas |
| Segunda ejecución del instalador limpio y de la actualización QA | Sin migraciones pendientes y cuenta técnica conservada |
| Actualización de la base local configurada | Tres migraciones nuevas aplicadas, 100 tablas y 1.441 registros anteriores conservados |
| Suite completa PHP con MySQL QA habilitado | 737 pruebas y 4.680 aserciones aprobadas |
| Pruebas JavaScript | 52 pruebas aprobadas, incluyendo archivos `.cjs` y `.js` |
| Consultas en la base local actualizada | Clientes, Productos y Ventas respondieron 200 mediante el transporte interno autenticado |
| Sintaxis PHP de archivos modificados y nuevos | 33 archivos verificados |
| Navegador Chrome en QA | 18 pantallas internas/públicas con HTTP 200, cinco recorridos funcionales y revisión móvil de cuatro pantallas |
| Tarifas en el mismo carrito | 7.650 minorista y 6.950 mayorista con selección expresa |
| Entrega con cobro parcial | Declaración de 3.000,50 sobre una venta de 7.500, nombres reales e impresión pendiente |

La suite combina pruebas SQLite en memoria y pruebas MySQL optativas habilitadas contra una base QA exclusiva.

Las nuevas regresiones cubren precios persistidos y alias, aumento por tarifa, baja de cliente/producto, stock inicial inválido, venta con tarifa guardada, entrega sin cobro, cobro parcial, reenvío idéntico, conflicto de reenvío, saldo posterior, rendición sucesiva, duplicación de declaración, repetición de precarga después de un rechazo, impresión con permisos y enlaces de paginación.

Durante la verificación se corrigieron una consulta adicional de stock del editor y el desbordamiento móvil de Rendiciones. Se separaron la base de la suite y la base de navegador para que sus fixtures permanezcan independientes.

Los resultados técnicos, capturas y comprobante de impresión de QA están en `Entregables/Branch_G1_Cierre_2026-11-13`. Los respaldos SQL y credenciales de QA son privados. Las pruebas de navegador bloquearon recursos externos y utilizaron los archivos locales del proyecto.

La validación corresponde al entorno local. SMTP, dominio/HTTPS e integración fiscal requieren sus respectivos entornos y accesos para una verificación real, según el alcance acordado previamente.

## Cómo comprobarlo manualmente

1. Iniciar MySQL desde XAMPP y levantar Laravel con el comando habitual del README.
2. En Inventario, editar un producto y cargar precios minorista y mayorista diferentes. Guardar y volver a abrir su ficha.
3. En Ventas, seleccionar un mayorista y agregar un producto. Comparar el total antes y después de marcar la casilla. Repetir con un minorista.
4. Abrir la modificación de una venta nueva y comprobar la tarifa recuperada. Una venta histórica solicita elegirla antes de guardar.
5. Preparar una entrega con un pedido confirmado y cliente con zona. Revisar dirección, cantidades y remito.
6. Confirmar un pedido informando un importe inferior a su deuda. Consultar su declaración en Rendiciones.
7. Abrir caja y aprobar el cobro individual. Consultar la deuda restante del cliente y preparar una nueva rendición por el saldo cuando se cobre.
8. Abrir el botón Imprimir del detalle de rendición y comprobar cliente, importe y revisión.

## Inventario de archivos de esta etapa

El informe se agrega junto con los siguientes archivos nuevos o modificados. Los archivos de pruebas actualizados validan los campos y argumentos ampliados, además de las nuevas regresiones.

- `README.md`
- `app/Modules/Catalogo/Data/ProductoCatalogo.php`
- `app/Modules/Clientes/Resources/ClientResource.php`
- `app/Modules/CuentaCorriente/Services/FinancialReadService.php`
- `app/Modules/Entregas/Controllers/EntregaController.php`
- `app/Modules/Entregas/Integraciones/VentasApiClient.php`
- `app/Modules/Entregas/Repositories/EntregaRepository.php`
- `app/Modules/Entregas/Repositories/RemitoRepository.php`
- `app/Modules/Entregas/Services/DeliveryPlanningService.php`
- `app/Modules/Entregas/Services/EntregaService.php`
- `app/Modules/Productos/Controllers/ProductoController.php`
- `app/Modules/Productos/Models/Producto.php`
- `app/Modules/Rendiciones/Controllers/Api/V1/CobroApiController.php`
- `app/Modules/Rendiciones/Controllers/Api/V1/RendicionApiController.php`
- `app/Modules/Rendiciones/Controllers/RendicionController.php`
- `app/Modules/Rendiciones/Services/RendicionService.php`
- `app/Modules/Rendiciones/routes/web.php`
- `app/Modules/Ventas/Controllers/VentaApiController.php`
- `app/Modules/Ventas/Controllers/VentaController.php`
- `app/Modules/Ventas/Integraciones/HttpClienteApi.php`
- `app/Modules/Ventas/Integraciones/HttpProductoApi.php`
- `app/Modules/Ventas/Resources/VentaResource.php`
- `app/Modules/Ventas/Services/CalculadoraVenta.php`
- `app/Modules/Ventas/Services/SalePricePolicy.php`
- `app/Modules/Ventas/Services/VentaService.php`
- `database/migrations/2026_11_13_000001_add_commercial_prices_to_products.php`
- `database/migrations/2026_11_13_000002_add_price_list_to_sales.php`
- `database/migrations/2026_11_13_000003_add_collected_amount_to_delivery_orders.php`
- `docs/contrato-api-g1.md`
- `docs/contrato-api-g3.md`
- `public/css/g2.css`
- `public/js/g2-entregas.js`
- `public/js/g2-rendiciones.js`
- `resources/views/entregas/index.blade.php`
- `resources/views/historial-ventas/historial-ventas.blade.php`
- `resources/views/inventario/index.blade.php`
- `resources/views/rendiciones/imprimir.blade.php`
- `resources/views/rendiciones/index.blade.php`
- `tests/Feature/CatalogoPublicoTest.php`
- `tests/Feature/CommercialCompletionMySqlTest.php`
- `tests/Feature/G1ReadApiTest.php`
- `tests/Feature/G2DeliveryPlanningTest.php`
- `tests/Feature/G2IntegrationTest.php`
- `tests/Unit/Rendiciones/RendicionServiceTest.php`

## Reversión

### Conservar datos y volver al código anterior

El punto de partida de esta etapa es `9a492f3fe289346d8404b147a9a332d575bdea02`. Existe un archivo con el código versionado anterior en `Entregables/Branch_G1_Cierre_2026-11-13_codigo_antes.zip`.

Antes de revertir, detener la web y generar un respaldo actualizado de la base y de los archivos modificados después de esta entrega. Restaurar únicamente los archivos de código de esta etapa desde ese commit o desde el archivo anterior, revisando el diff. Las columnas aditivas pueden conservarse y permiten retomar esta versión posteriormente.

Las escrituras de esta etapa alcanzan G1, G2, G3 y G4 según los archivos descritos. La restauración selectiva debe incluir consumidores y pantallas correspondientes para mantener sus contratos alineados. El informe, los contratos y las instrucciones del README permiten identificar el conjunto de cambios.

### Volver también a la fotografía anterior de la base

El respaldo `base-local-antes.privado.sql`, fuera del repositorio ERP, contiene la base previa a estas tres migraciones. Restaurarlo requiere revisar primero las operaciones realizadas desde su generación. La restauración completa recupera esa fotografía y reemplaza las operaciones posteriores de esa base.

Para una reversión verificada, restaurar primero en una base de recuperación, comprobar los registros y decidir qué operaciones posteriores deben conservarse. Luego coordinar el reemplazo con la web detenida. La reversión automática de estas migraciones conserva sus datos comerciales.

### Arranque posterior

Con código y esquema compatibles, iniciar MySQL y Laravel según el README. Verificar inicio de sesión, Inventario, Ventas, consulta del cliente, entregas y rendiciones. Las tareas programadas de Windows conservan la decisión de administración tomada en su etapa correspondiente.
