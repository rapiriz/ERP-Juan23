# Bloqueos generales e integración: correcciones y verificación

Fecha de ejecución: 08–09/11/2026. Proyecto: ERP Juan23, rama local `develop`.

## 1. Alcance y resultado

Se trabajó sobre los siete incisos del apartado **Bloqueos generales e integración**, con autorización para modificar todos los grupos. Se conservaron los cambios locales anteriores. No se hizo commit ni push.

Esta etapa permite instalar el esquema integrado, actualizar la base local sin la limpieza destructiva pendiente, consumir las APIs locales sin HTTP hacia el mismo proceso de PHP y consultar fuentes financieras reales. No equivale a certificar todos los circuitos del ERP: al final se registran los límites y los trabajos de las siguientes etapas.

| Prioridad original | Inciso | Problema encontrado | Corrección aplicada | Verificación |
| --- | --- | --- | --- | --- |
| Crítica | BD compartida | Una migración de G3 eliminaba lotes y estructuras históricas, alteraba identificadores y datos de integración. | Se conservó su nombre e historial, reemplazando la limpieza por una ampliación que sólo agrega `producto.imagen` si falta. | Prueba de preservación, actualización de copia y comparación de columnas originales en la base local. |
| Alta | Instalación | El esquema no era reproducible; una migración bancaria intentaba crear una tabla existente y faltaban esquemas usados por las pantallas. | Migraciones compatibles, cuatro ampliaciones modulares y un instalador común para alta y actualización. | Migración desde base vacía en MariaDB, instalación repetida y `composer setup` sobre la base existente. |
| Alta | HTML | Proveedores y otras pantallas interpolaban datos externos en `innerHTML` y manejadores de botones. | Escape de texto y atributos, serialización segura de argumentos y conversión numérica de IDs. | Pruebas con HTML malicioso, nombres con comillas y los renderizadores reales. |
| Alta | Dependencias | La auditoría previa registraba diez avisos en seis paquetes. | Actualización compatible de las dependencias afectadas y transitivas, conservando PHP 8.2 y Laravel 12. | `composer validate` válido y `composer audit --locked` sin avisos ni paquetes abandonados al cierre. |
| Alta | Ventas | `internal` caía en la rama de mocks del proveedor. | Registro explícito de `internal`, `http` y `mock`. Configuraciones desconocidas fallan en lugar de seleccionar mocks. | Venta real en MySQL de prueba usando clientes y productos de las APIs, con descuento de stock. |
| Alta | Comunicación | HTTP hacia el mismo `artisan serve` podía bloquear la solicitud; faltaban configuración e identidad de servicio. | Despacho interno a rutas registradas y comando de configuración local. | Consumidores reales con prohibición de tráfico HTTP en las pruebas; conservación de identidad y sesión exterior. |
| Alta | Contratos financieros | No había una fuente coherente para saldo, deudores, pagos aplicados y comprobantes pendientes. | APIs de lectura en Cuenta Corriente de G4, adaptadas a los consumidores de G1. | Pruebas de signos, importes con centavos, paginación, ventas históricas, vencimientos desconocidos y autenticación. |

## 2. Respaldo y tratamiento de la base

Los respaldos y evidencias se guardaron **fuera del repositorio Git**, en:

```text
C:\Users\juanf\OneDrive\Escritorio\Repositorio\Entregables\Bloqueos_Integracion_2026-11-08
```

Elementos principales:

- `proyecto-antes.sql`: respaldo lógico de `sistema_gestion` y `sistema_gestion_g1_g3_20261026`, previo a esta actualización.
- `proyecto-despues.sql` y `mysql-data-despues`: respaldo lógico posterior y copia física en frío del estado actualizado, tomada después del apagado normal de MySQL.
- `env-antes.privado`: configuración local anterior. Contiene secretos y no debe compartirse.
- `codigo-antes`, `migrations-antes` y `cambios-previos.patch`: estado del código anterior a esta etapa, incluidos cambios locales que ya existían.
- `columnas-y-datos-previos.privado.json`: columnas originales y huellas de registros para la comparación.
- `reversion-codigo`, `manifest-reversion.json` y `plan-reversion.txt`: copias previas y plan de reversión selectiva de esta etapa, sin descartar el trabajo local previo. El manifiesto distingue 39 archivos con versión anterior y 12 archivos nuevos, e incluye huellas SHA-256.
- Logs de instalación, pruebas, consultas y auditoría de dependencias. Los archivos SQL y respaldos privados no deben subirse a Git.

Primero se restauró el respaldo en un servidor de prueba independiente, puerto 3313. Allí se aplicaron las migraciones y se ejecutaron escrituras de prueba. Después se actualizó el MySQL local, puerto 3306, utilizando el instalador del proyecto.

La comparación verificó **94 tablas de datos** entre las dos bases: mismos registros y mismos valores de las columnas que existían antes de la actualización. Se excluyeron de esa igualdad las dos tablas `migrations`, porque su función es registrar las migraciones aplicadas. El respaldo previo contiene 96 tablas y 1.428 registros, incluyendo esas tablas de control.

No se cargaron productos, clientes ni ventas de demostración en la base de trabajo durante esta etapa. Las operaciones de prueba de venta, pago y stock se hicieron en la instancia aislada y se revirtieron mediante transacciones. La base secundaria se conservó.

Esta etapa no reconstruye las transacciones incompletas que pudieron perderse antes de la recuperación de MySQL del 08/11. Esa limitación histórica sigue documentada en `informe-seguridad-identidad-y-actualizacion-mysql.md`.

## 3. Migraciones: qué cambió y por qué

No se borraron todas las migraciones. Conservar sus nombres mantiene la relación con el registro `migrations` de instalaciones existentes.

### 3.1 Archivos existentes corregidos

| Archivo | Cambio |
| --- | --- |
| `app/Modules/Productos/database/migrations/2026_10_28_000001_remove_lotes_vencimientos_and_legacy_mock_tables.php` | Su `up()` conserva tablas, lotes, columnas, IDs y datos históricos. Sólo agrega `imagen` a Producto cuando falta. `down()` no borra imágenes ni estructuras. |
| `app/Modules/Productos/database/migrations/2026_10_24_000001_seed_g3_catalog_for_integration.php` | Se conserva como paso histórico sin insertar datos de demostración durante `migrate`. |
| `app/Modules/Productos/database/migrations/2026_10_26_000001_migrate_g3_foreign_keys_to_usuarios_table.php` | Antes de alinear las referencias con `usuarios`, comprueba que existan los usuarios referenciados. Si hay huérfanos, se detiene; ya no reasigna sus operaciones a otro administrador. |
| `database/migrations/2026_11_04_000001_create_archivo_importado_log_table.php` | Comprueba la existencia de la tabla antes de crearla. |
| `database/migrations/2026_11_04_000002_create_movimiento_bancario_table.php` | Amplía la tabla bancaria existente en vez de recrearla. Conserva los campos históricos y agrega los que espera el módulo nuevo. Permite período nulo para importaciones aún no asignadas. |

La compatibilidad bancaria copia la fecha, descripción, signo y estado a los campos nuevos cuando falta la fecha nueva. También completa los campos históricos cuando la instalación proviene exclusivamente del esquema nuevo. Por ejemplo, `conciliado` se representa como `matcheado` en el contrato nuevo, sin cambiar el valor histórico original.

Se conservan índices, claves y valores preexistentes; no se impuso una nueva unicidad sobre movimientos históricos que pudieran estar duplicados. Esta corrección no fusiona filas ni decide automáticamente qué movimiento conciliar.

### 3.2 Migraciones nuevas

Todas están dentro de `app/Modules/{Modulo}/database/migrations` y son descubiertas por el proveedor modular existente.

| Módulo | Archivo | Función |
| --- | --- | --- |
| Promociones | `2026_11_08_000001_complete_promociones_schema.php` | Crea las tablas de promociones y su relación con productos si faltan; agrega `tipo_descuento` y `valor_descuento` cuando no existen. No reemplaza promociones registradas. |
| Ventas | `2026_11_08_000002_add_optional_due_date_to_venta.php` | Agrega `fecha_vencimiento` opcional. No inventa vencimientos para ventas históricas. |
| ConciliacionBancaria | `2026_11_08_000003_complete_banking_schema.php` | Ejecuta la ampliación bancaria de forma repetible y crea, si faltan, período, cheques, ajustes y detalle de conciliación. Permite reparar instalaciones que ya habían registrado una versión anterior de la migración raíz. |
| Rendiciones | `2026_11_08_000004_complete_legacy_collection_schema.php` | Crea `cobro` y `cobro_venta` si faltan, porque el historial de ventas y las referencias bancarias todavía utilizan ese contrato histórico. No copia ni convierte cobros de rendiciones en pagos confirmados. |

Las tablas auxiliares nuevas conservan referencias lógicas mediante sus IDs; no se añadieron restricciones foráneas indiscriminadas contra esquemas históricos de tipos distintos. Esto permite la instalación, pero no sustituye la revisión funcional de integridad y conciliación que sigue pendiente.

En promociones históricas sin los campos de descuento, se agregan el tipo `monto_fijo` y valor cero como valores iniciales neutrales. El `total` histórico se conserva. No se intenta deducir un descuento a partir de ese total y precios actuales; esas promociones deben revisarse antes de editar sus condiciones o validar su importe comercial en la siguiente etapa de Ventas.

Las migraciones de ampliación tienen una reversión conservadora: **`down()` no elimina datos potencialmente reales**. Para volver exactamente al esquema anterior se utiliza el respaldo, según la sección final.

### 3.3 Compatibilidad del modelo bancario

`app/Modules/ConciliacionBancaria/Models/MovimientoBancario.php` sincroniza los campos nuevos con sus equivalentes históricos al guardar. Una importación nueva informa fecha, descripción, signo, estado y usuario de la cabecera de importación, de modo que las columnas históricas obligatorias no bloqueen la escritura.

Se comprobó una inserción real en la copia MariaDB del esquema existente, con período nulo, usuario válido y ambos juegos de columnas consistentes. Se revirtió la transacción de prueba.

## 4. Instalador y configuración

Archivos principales:

- `composer.json`: `setup` instala dependencias, crea `.env` si falta y ejecuta `erp-install.php`. La creación de proyecto ya no intenta instalar automáticamente una base SQLite como si fuera el esquema integrado.
- `erp-install.php`: conserva una `APP_KEY` existente; genera una únicamente si falta. Ejecuta las migraciones pendientes y luego `erp:configure`. Se detiene ante un error.
- `app/Console/Commands/ConfigureIntegration.php`: crea o reutiliza `servicio.integracion`, guarda la configuración local y permite crear un administrador mediante `--admin`.
- `.env.example`, `config/ventas.php`, `config/services.php`, `config/g2.php` y `README.md`: documentan y resuelven los transportes y contratos.
- `database/seeders/DatabaseSeeder.php`: conserva contraseñas, rol y estado de usuarios existentes mediante `firstOrCreate`; las semillas siguen siendo una acción explícita.

La cuenta técnica usa rol `contador` y estado activo. Su contraseña se genera aleatoriamente y no se entrega. El token se guarda en el `.env` local y no se imprime. Si existe una cuenta con ese nombre y otro rol o estado, el comando se detiene para revisarla.

El modo local configura:

```dotenv
ERP_API_TRANSPORT=internal
VENTAS_INTEGRACION=internal
CATALOG_TRANSPORT=internal
SALES_TRANSPORT=internal
BILLING_TRANSPORT=internal
BALANCE_TRANSPORT=internal
G1_RENDICIONES_TRANSPORT=internal
G2_API_TRANSPORT=internal
BILLING_PAYMENTS_PATH=/api/v1/finanzas/cobros
BILLING_INVOICES_PATH=/api/v1/finanzas/facturas/pendientes
BALANCE_CLIENT_PATH=/api/v1/finanzas/clientes/{id}/saldo
BALANCE_DEBTORS_PATH=/api/v1/finanzas/clientes/deudores
```

El comando es deliberadamente una configuración de **instalación local integrada**: cambia también los transportes individuales para no dejar un consumidor usando HTTP por una variable anterior. No debe ejecutarse sin revisar la configuración de una instalación distribuida que utilice servicios remotos.

No sustituye automáticamente tokens externos ya configurados: para el transporte interno se usa el token específico si existe y, en su ausencia, el token interno. Un token específico inválido seguirá siendo rechazado y debe corregirse en ese entorno.

### Instalación nueva

1. Crear una base MySQL/MariaDB vacía y configurar sus credenciales en `.env`, partiendo de `.env.example`.
2. Con MySQL iniciado, ejecutar `composer setup`.
3. Ejecutar `php artisan erp:configure --admin` para crear el primer administrativo con credenciales propias.
4. Ejecutar `php artisan serve` para iniciar la web.

El instalador no ejecuta `db:seed`. Una instalación vacía tendrá la cuenta técnica y las estructuras, pero requiere cargar usuarios, categorías, marcas y datos comerciales por sus operaciones correspondientes.

### Base existente

Respaldar datos y `.env`, instalar dependencias y ejecutar `composer setup`, o `php erp-install.php` si las dependencias ya están instaladas. No generar otra `APP_KEY`. No utilizar `migrate:fresh`, `refresh` ni rollback general.

En esta base local se aplicaron seis migraciones pendientes: limpieza conservadora, ampliación bancaria y las cuatro migraciones nuevas. La segunda ejecución de `migrate` informó que no había pendientes.

## 5. Comunicación real entre los módulos

`app/Services/Integrations/InternalApiClient.php` despacha solicitudes a las rutas API ya registradas en Laravel. No consulta directamente las tablas de otros módulos para reemplazar sus APIs.

La lista de contratos permitidos está definida por método y acción del controlador. Incluye lecturas de clientes, productos, stock, ventas, rendiciones, cobros, caja y fuentes financieras; las escrituras internas habilitadas son el lote de movimientos de stock y el cambio de estado de Ventas. Una ruta fuera de esa lista es rechazada.

En cada llamada se conservan los middleware de autorización, los validadores y el controlador real. Se aíslan la petición, autenticación y sesión internas; las cookies y la identidad del navegador no se transmiten. Al finalizar, incluso ante un error, se restaura el contexto exterior. El catálogo conserva su acceso al contrato público de Productos, sin convertir la visita anónima en una sesión administrativa.

Esto evita que una solicitud a `artisan serve` espere otra conexión al mismo proceso, que no puede atenderla hasta terminar la primera. No hace falta iniciar Apache para este circuito local.

Adaptaciones concretas:

| Consumidor | Archivos | Resultado |
| --- | --- | --- |
| Catálogo G1 | `app/Modules/Catalogo/Services/InternalCatalogApi.php` | Adaptador limitado a la API de Productos; conserva las rutas autorizadas y el contexto exterior. |
| Reportes y consultas G1 | `app/Services/Gateways/ExternalJsonClient.php` | Selección de transporte por servicio; lectura de todas las páginas y tratamiento de errores sin mostrar una falla como cero registros. |
| Ventas G4 | `HttpClienteApi.php`, `HttpProductoApi.php`, `HttpStockApi.php` y `AppServiceProvider.php` | En `internal`, consume las APIs reales de G1 y G3. `http` sigue disponible para proveedores separados y `mock` queda explícito. |
| Entregas G2 | `app/Modules/Entregas/Integraciones/VentasApiClient.php` | Listado, detalle y actualización de estado de Ventas mediante el mismo transporte interno. |

Para servicios separados se conserva `http`, con URL base, token, rutas y tiempos de espera. No se debe apuntar ese modo al mismo proceso único de `artisan serve`.

## 6. Fuentes financieras: dueño, signos, estados y campos

El responsable del cálculo es **Cuenta Corriente de G4**, mediante:

- `app/Modules/CuentaCorriente/Services/FinancialReadService.php`.
- `app/Modules/CuentaCorriente/Controllers/Api/V1/FinancialReadController.php`.
- `app/Modules/CuentaCorriente/routes/api.php`.

G1 consume estas fuentes mediante `app/Services/Gateways/ExternalFinanceGateway.php`. Las rutas públicas existentes de G1 mantienen su lugar como consumidores; las nuevas rutas de origen no se llaman recursivamente a través de esos consumidores.

| Método y ruta de origen | Respuesta y permisos |
| --- | --- |
| `GET /api/v1/finanzas/clientes/{id}/saldo` | `data` con `cliente_id`, `saldo`, `saldo_a_favor`, `saldo_contable`, `moneda` y `origen`. Usuario autorizado o servicio; administrativo, contador y repartidor según el middleware existente. |
| `GET /api/v1/finanzas/clientes/deudores` | Lista de clientes con `saldo > 0`, ordenada por deuda. Administrativo/contador o servicio autorizado. |
| `GET /api/v1/finanzas/facturas/pendientes` | Comprobantes de venta interna con importe pendiente. Incluye `id`, `cliente_id`, `numero`, `tipo_comprobante`, `fecha`, `vencimiento` e `importe_pendiente`. Administrativo/contador o servicio autorizado. |
| `GET /api/v1/finanzas/cobros?cliente_id={id}` | Pagos aplicados: `id`, `cliente_id`, `fecha`, `monto_total`, `medio_pago`, `observaciones`, `confirmacion_individual` y `origen`. Usuario o servicio autorizado. |

Las listas devuelven `data` y `meta` con página actual, última página, cantidad por página y total. Aceptan `page` y `per_page`, máximo 100 por página. Los clientes inexistentes devuelven 404; parámetros inválidos, 422; solicitudes privadas sin autenticar, 401. Las respuestas llevan `Cache-Control: no-store` y límite de solicitudes.

### Reglas implementadas

1. El libro `cuenta_corriente_movimientos` conserva su signo: una venta es un importe negativo y un pago/crédito aplicado es positivo. `saldo_contable` conserva ese signo. La API expone **`saldo` positivo para deuda** y **`saldo_a_favor` positivo para crédito**, en ARS.
2. Los importes se calculan en centavos durante la acumulación y asignación, para evitar residuos de operaciones decimales en esos cálculos.
3. Un registro de `RENDICION_COBRO` por sí solo no es prueba de pago aplicado. La lista de cobros se obtiene de movimientos positivos del libro de tipo `pago` o `cobro`. No incluye notas de crédito como si fueran dinero cobrado.
4. Las referencias `rendicion_cobro:{id}` permiten asignar un pago aplicado a su venta. Los pagos y créditos sin comprobante explícito se imputan por antigüedad a las ventas pendientes, primero las más antiguas. Esta regla **FIFO** es una decisión de integración de esta etapa.
5. Las ventas históricas que no tienen movimiento en el libro se reconocen virtualmente durante la lectura, sin insertar filas nuevas. Se excluyen las canceladas/anuladas. Si la venta ya tiene movimiento se toma el asiento existente y no se vuelve a sumar su total.
6. Se conserva el criterio de registro actual de Ventas: al crear una venta ya se genera deuda aunque su estado inicial sea `pendiente`. Esta etapa no cambia ese ciclo de negocio. Los rankings de ventas confirmadas continúan con su criterio independiente.
7. `pagada` o `facturada` no sustituye un asiento de pago faltante. El saldo procede del libro, no de asumir que cambiar el estado acredita dinero. La consistencia completa entre estados, caja y cobros corresponde a la siguiente etapa funcional.
8. `numFactura` es la referencia interna de la venta; `tipo_comprobante=venta_interna` lo explicita. Estas respuestas no certifican emisión fiscal.
9. Si no se informó un vencimiento, se devuelve `null`; el frontend muestra **Vencimiento sin informar**. Se agregó `fecha_vencimiento` opcional al alta de Ventas, sin asignar fechas ficticias a registros anteriores.
10. El saldo incluye el libro completo; los comprobantes pendientes corresponden a ventas identificables. Ajustes o débitos sin comprobante pueden hacer que la suma de comprobantes no sea igual al saldo. No se crean facturas artificiales para cubrir esa diferencia.

`CuentaCorrienteController.php` utiliza las mismas lecturas históricas en la pantalla real y al calcular el saldo disponible para un movimiento. `resources/views/reports/debtors.blade.php` muestra vencimientos desconocidos correctamente. No se reescribieron masivamente los movimientos históricos.

## 7. Correcciones de presentación y seguridad HTML

Se utilizaron el helper común `public/assets/js/safe-html.js` y las funciones de escape existentes donde correspondía. Ambos layouts cargan el helper antes de los scripts de pantalla.

| Archivo | Datos protegidos y corrección |
| --- | --- |
| `resources/views/proveedores/index.blade.php` | Razón social, contactos, correo, dirección e historial. Los nombres que pasan a un botón se serializan con `JSON.stringify` y después se escapan para el atributo HTML. Se conserva el nombre original al ejecutar la acción. |
| `resources/views/compras/index.blade.php` | Proveedores, productos, comprobantes, observaciones, estados y textos de detalle. |
| `resources/views/inventario/index.blade.php` | Interpolaciones restantes en detalles e historiales, manteniendo los escapes existentes. |
| `resources/views/ventas/ventas.blade.php` | Textos de clientes y productos insertados por JavaScript. |
| `public/js/g2-entregas.js` | Clientes, domicilios, zonas, artículos, observaciones y mensajes. Los IDs usados en manejadores se convierten a números. |
| `public/js/g2-rendiciones.js` | Repartidor, observaciones tanto en texto como en `title`, remitos, motivo de rechazo y mensajes. |
| `public/js/sugerencias-reposicion.js` | Producto, código, proveedor, observaciones, rechazo, estado y fecha; IDs y paginación numéricos. |
| `public/js/promociones.js` | Interpolaciones restantes de código, fechas, valor y acciones, preservando los escapes de nombre y descripción ya existentes. |
| `public/assets/js/g1-rendiciones.js` | Delega el escape al renderizador compartido seguro. Evita escapar dos veces y mostrar entidades HTML al usuario. Conserva normalización de IDs, filtros y bloqueo por indisponibilidad. |

Se mantuvieron `textContent` y asignaciones de `value` con texto original donde ya eran seguras. No se escapa indiscriminadamente el HTML estructural generado por la aplicación.

Estas pruebas cubren los puntos de inserción modificados y los revisados en esta etapa. No equivalen a una certificación general de ausencia de vulnerabilidades ni a una prueba de penetración de todo el sistema. El archivo compilado histórico `public/js/promociones-BIlSLq2E.js` no está referenciado por las vistas actuales; el script utilizado y revisado es `public/js/promociones.js`.

## 8. Dependencias y pruebas

La actualización de `composer.lock` registra 18 actualizaciones compatibles. Entre ellas: CommonMark 2.10.1 → 2.10.7, Symfony HTTP Foundation y HTTP Kernel 7.4.19 → 7.4.23, Routing 7.4.18 → 7.4.23, Mime 7.4.19 → 7.4.22 y YAML 7.4.18 → 7.4.23, junto con dependencias transitivas. `vendor` se actualizó mediante Composer, sin editar sus archivos manualmente.

| Comprobación | Resultado |
| --- | --- |
| `composer validate --no-check-publish` | Válido. |
| `composer audit --locked --format=json` | Cero avisos y cero paquetes abandonados al cierre. |
| Instalación completa de migraciones en MariaDB vacía | Correcta. Las migraciones de G3 se prueban en MariaDB, no se dan por verificadas sólo por SQLite. |
| `erp-install.php` repetido en la base aislada | Dos ejecuciones correctas; clave generada inicialmente y conservada en la segunda; sin semillas. |
| `composer setup` sobre la base local existente | Correcto; seis migraciones aplicadas, cuenta técnica reutilizada y `APP_KEY` conservada. |
| Nueva ejecución de `migrate --force` | Sin migraciones pendientes. |
| Preservación de las columnas originales | 94 tablas comprobadas, sin diferencias de datos. |
| `mysqlcheck` en la instancia de prueba | Tablas comprobadas correctamente. |
| Reinicio de la instancia de prueba | Revalidación de datos y circuito de venta/pago correcta. Los procesos de prueba usan salidas de consola redirigidas para independizarlos del terminal. |
| Pruebas seleccionadas de contratos, seguridad, catálogo, gateways y reportes | 65 pruebas, 219 aserciones, correctas. |
| Pruebas unitarias | 101 pruebas, 289 aserciones, correctas. |
| Frontend Node, todos los archivos `.test.js` y `.test.cjs` | 34 pruebas correctas, incluidas cargas HTML maliciosas y regresiones de Rendiciones. |
| Consultas sobre la base local mediante el kernel real | 18 comprobaciones con los estados esperados: API pública, rechazo anónimo de datos privados, fuentes autenticadas, consulta bancaria y siete pantallas internas. |
| Sintaxis de scripts Blade renderizados | Scripts extraídos de las pantallas revisadas y comprobados con Node. No sustituye una revisión visual completa en navegador. |

Las nuevas pruebas son `tests/Feature/LocalIntegrationContractsTest.php`, `tests/Feature/PreservingUpgradeTest.php` y ampliaciones de `tests/Frontend/SafeHtml.test.js`. Se ajustaron fixtures de paginación en `Sprint4ReportsTest.php` y la carga del helper real en `g1-rendiciones.test.cjs`. `phpunit.xml` fija los transportes HTTP/mock usados por las pruebas para que el `.env` local integrado no altere sus fixtures; los tests de transporte interno lo activan explícitamente.

### Circuito real de escritura en MySQL aislado

Se creó por las APIs una categoría, marca, cliente y producto de prueba con 20 unidades y precio 100. Se registró una venta de dos unidades mediante la API de Ventas y sus consumidores internos reales. El stock pasó a **18** y la deuda a **200**. Un pago de **50**, con sesión y CSRF válidos en la operación web de Cuenta Corriente, dejó la deuda en **150**. G2 confirmó la venta y leyó su detalle mediante su cliente API interno, sin HTTP. Se consultaron saldo, deudores, comprobantes pendientes, pagos, stock y catálogo. Se revirtió la transacción de prueba.

En las lecturas finales de la base local, los consumidores devolvieron 27 clientes activos, 20 productos, 15 deudores, 29 comprobantes pendientes y 26 ventas confirmadas pendientes de despacho. Son resultados del estado de datos al cierre, no cantidades sembradas por esta etapa. Las consultas del consumidor se ejecutaron impidiendo solicitudes HTTP externas.

### Resultado general y limitaciones heredadas

La ejecución general aislada registró **428 pruebas y 2.087 aserciones, con cuatro fallos previos y una advertencia de prueba riesgosa**, los mismos problemas de fixtures y vistas que existían al comenzar:

- `G4DependencyRegistrationTest`: espera nombres antiguos de vistas y fixtures de consulta que no representan los controladores actuales, incluida la consulta real de usuarios y los cobros pendientes.
- `G4SalesSchemaTest`: espera `ventas` donde la vista actual es `ventas.ventas`.

No se ocultaron esos fallos para presentar la suite completa como aprobada. Los GET correspondientes sí devolvieron 200 contra el esquema MySQL real actualizado.

Para no usar bases de trabajo durante la prueba general, se excluyeron seis clases que usan `tests/UsesG3Database.php`, el cual fija MySQL `distribuidorapyb` directamente, y `VentasApiTest`, cuyo fixture intenta volver a crear tablas que ya crean las migraciones. La configuración de esa ejecución queda en el respaldo de evidencias, `phpunit-seguro.xml`. Corregir el aislamiento y los fixtures restantes sigue siendo trabajo de pruebas del proyecto.

Composer también advierte una ubicación PSR-4 previa en `Tests\Unit\Ventas\CalculadoraVentaTest`, situado en `tests/Unit/CalculadoraVentaTest.php`. No es un aviso de vulnerabilidad ni impidió la instalación, pero corresponde normalizar esa ubicación en la etapa de pruebas.

## 9. Qué cambió para los demás equipos

Esta etapa sí modifica código compartido, consumidores de G2, migraciones de G3, fuentes financieras de G4, vistas y dependencias. Fue autorizada expresamente para resolver los bloqueos de integración.

Un futuro commit/push compartiría esos archivos; **no compartiría automáticamente la base local, el `.env`, los tokens ni los respaldos externos**. Otro equipo que actualice el código y ejecute `composer setup` o `migrate` recibirá las ampliaciones de esquema y el comportamiento conservador de las migraciones corregidas. `erp:configure` cambia la configuración de su entorno si lo ejecuta.

Por ello deben revisar el informe y respaldar sus propias bases antes de actualizar. Una instalación que ya hubiera ejecutado la antigua limpieza no recuperará automáticamente los datos que esa versión hubiera borrado: para eso necesita su respaldo original. Esta etapa evita que una ejecución pendiente vuelva a borrarlos.

No se modificaron las tablas internas de cuentas o permisos de MySQL durante esta etapa. La reparación de XAMPP del informe anterior es un antecedente separado.

## 10. Pendientes de las próximas etapas

Los siete bloqueos generales se corrigieron y verificaron dentro del entorno y alcance indicados. Continúan fuera de esta etapa:

- Consistencia transaccional completa entre aprobación de rendición, liquidación individual, caja, cuenta corriente y reintentos.
- Reglas funcionales de conciliación bancaria, importes regionales del parser, asignación de período y revisión de conciliaciones/cheques/ajustes.
- Precisión monetaria en todos los caminos de Ventas, descuentos, concurrencia, idempotencia y recuperación frente a fallos de stock. La prueba de venta realizada valida el circuito descrito, no todos esos escenarios.
- Corrección de los cuatro fallos de fixtures/vistas, aislamiento de las pruebas MySQL heredadas y normalización PSR-4 mencionada.
- Revisión visual completa de todas las pantallas y diálogos, uniformidad del frontend y las historias de usuario de las demás secciones de la auditoría.
- Validación específica en otros servidores: la instalación se verificó con PHP 8.2.12 y MariaDB 10.4.32 de XAMPP en Windows. No se certificó aquí una instalación en Linux con diferencias de mayúsculas/minúsculas en nombres de tablas.
- Acuerdo funcional entre equipos si se decide cambiar FIFO, el momento en que una venta genera deuda o la definición fiscal del comprobante. Las reglas aplicadas quedaron explicitadas en la sección 6.

## 11. Cómo revertir esta etapa

La reversión exacta requiere **código, dependencias, configuración y base compatibles entre sí**. No ejecutar un rollback global ni importar un SQL histórico elegido por nombre.

1. Detener la web y los trabajadores que puedan escribir en la base. Hacer un respaldo nuevo del estado actual para conservar cualquier operación realizada después de esta corrección.
2. Revisar `plan-reversion.txt` y `manifest-reversion.json`, fuera de Git, en la carpeta de evidencias indicada en la sección 2. Comparar las huellas SHA-256 con los archivos actuales: si cambiaron posteriormente, revisar esas diferencias antes de reemplazarlos.
3. Para las entradas `restore`, copiar su versión previa desde `reversion-codigo`, conservando la misma ruta relativa dentro del proyecto. Para las entradas `new_file_review`, revisar y retirar únicamente esos archivos nuevos si ya no deben formar parte de la versión anterior. No borrar carpetas completas ni usar `git reset --hard`: los cambios locales anteriores a esta etapa deben conservarse. El plan queda para revisión manual y no ejecuta borrados automáticos.
4. Restaurar `.env` desde `env-antes.privado`, después de revisar si hubo cambios legítimos posteriores en credenciales o configuración. No publicar ni pegar su contenido en el chat.
5. Restaurar `proyecto-antes.sql` en un MySQL compatible, primero en una base/instancia de comprobación. Contiene las dos bases y sus tablas `migrations`. Su importación reemplaza el estado de esas bases por el del respaldo; por eso debe conservarse el respaldo más reciente del paso 1 y decidir cómo recuperar las operaciones posteriores.
6. Con el `composer.lock` anterior restaurado, ejecutar `composer install`, **no `composer update`**. No ejecutar `composer setup` durante la reversión: el código anterior incluye la migración destructiva pendiente que se corrigió aquí.
7. Ejecutar `php artisan config:clear` y `php artisan view:clear`, revisar configuración y tablas, comprobar login y lecturas privadas y recién entonces volver a iniciar la web.

Los `down()` conservadores de las ampliaciones no deshacen automáticamente columnas que ya podrían tener datos reales. Quitar sólo un archivo de migración tampoco revierte lo aplicado en MySQL. La copia SQL es la referencia para volver exactamente al estado anterior.

Los respaldos y evidencias permanecen fuera del repositorio. Al cerrar esta tarea se detienen los servidores de prueba y el MySQL iniciado para la verificación; el arranque manual continúa siendo MySQL desde XAMPP y `artisan serve`.
