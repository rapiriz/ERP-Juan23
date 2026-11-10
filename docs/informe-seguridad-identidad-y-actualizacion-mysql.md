# Seguridad, identidad y recuperación de MySQL

## 1. Fecha y alcance

Revisión del 8 de noviembre de 2026 sobre `develop`, con commit base `060cb9badcb2ff5bc9301975edf038f27fd1091d`.

Se implementó la etapa de seguridad, identidad y actualización controlada de la base acordada después de actualizar el proyecto. Los cambios de código alcanzan componentes compartidos y puntos de entrada de G1, G2, G3 y G4, dentro de la autorización ampliada del usuario. Esta entrega conserva pendientes funcionales identificados en la sección 9.

Los cambios de esta etapa permanecen en el árbol de trabajo. Los respaldos y las evidencias de recuperación están en `../Entregables/Seguridad_MySQL_2026-11-08`, fuera del repositorio `ERP-Juan23`.

## 2. Hallazgos corregidos

| Prioridad | Problema encontrado | Corrección aplicada | Ubicación principal |
|---|---|---|---|
| Crítica | MySQL no podía arrancar por daño del redo de InnoDB y de tablas internas. | Recuperación sobre una copia, exportación de las bases del proyecto, restauración en un servidor sano, comparación de datos y sustitución autorizada del directorio original. | Entorno local de XAMPP y respaldos externos al repositorio. |
| Crítica | Había operaciones internas de G3 cuyo único control era un resolvedor de identidad que podía aceptar encabezados o recurrir a un usuario predeterminado. | Autenticación y permisos antes del controlador. El actor sale del usuario autenticado o de una cuenta técnica configurada expresamente. | `app/Modules/Productos/routes/web.php`, `app/Http/Middleware/IdentidadUsuario.php`, `app/Support/UsuarioActual.php`. |
| Alta | Los contratos externos de escritura de stock necesitaban autenticación efectiva y una identidad auditable para llamadas de servicio. | Sesión vigente con CSRF o Bearer válido. Las llamadas de servicio necesitan un usuario de auditoría existente y activo. | `app/Modules/Productos/routes/api.php`, `config/services.php`. |
| Alta | `interno=1` ampliaba la respuesta comercial de productos con información de costos sin un permiso específico. | Ese parámetro exige un usuario administrativo autenticado. El contrato comercial público conserva su acceso. | `app/Modules/Productos/Middleware/AuthorizeProductInternals.php`. |
| Alta | Las nuevas consultas de cobros y rendiciones estaban fuera del grupo autenticado de la API principal. | Se incorporaron al grupo de lectura autenticada y se limitaron por audiencia. | `routes/api.php`. |
| Alta | Las nuevas consultas bancarias y la importación de extractos necesitaban controles de acceso explícitos. | Consultas para administrativo, contador o servicio válido. La importación exige un usuario administrativo o contador. | `app/Modules/ConciliacionBancaria/routes/web.php`. |
| Alta | Ventas, Promociones y Cuenta Corriente necesitaban distinguir consulta y escritura por rol. | Reutilización de autenticación común y permisos de escritura específicos. | Rutas de esos tres módulos. |
| Alta | Había interpolación de datos en HTML dinámico y argumentos de manejadores de Inventario y de la pantalla bancaria. | Escape de texto y atributos, serialización de argumentos y validación de URL de imágenes en los puntos revisados. | `public/assets/js/safe-html.js`, vistas de Inventario y Conciliación. |
| Media | Una escritura del frontend de Proveedores no enviaba el token CSRF. | Se agregó `X-CSRF-TOKEN` a la sincronización de asociaciones. | `resources/views/proveedores/index.blade.php`. |
| Media | La existencia de una clase de cobros no garantizaba que el esquema requerido por la conciliación automática estuviera disponible. | Comprobación de tabla y columnas requeridas y respuesta controlada `503` cuando falta la integración. | `app/Modules/Shared/Http/Middleware/G2SchemaReady.php`. |

El escape aplicado cubre los renderizadores revisados. Queda pendiente una validación completa del frontend en navegador y del resto de los puntos que construyen HTML dinámico.

## 3. Autenticación y permisos

### 3.1. Autenticación común

Se agregó `AuthenticateApiSessionRequest` en el módulo Login.

- Un encabezado `Authorization` explícito se valida mediante el autenticador Bearer existente.
- Un Bearer inválido obtiene `401`, aunque el navegador tenga una sesión válida.
- Sin encabezado Bearer, se utilizan las cookies de sesión y se verifica actividad, estado del usuario y reemplazo de la sesión.
- Las escrituras autenticadas por sesión requieren CSRF. Las escrituras con Bearer utilizan la validación del token.
- Las rutas que ya procesaron cookies y sesión reutilizan ese contexto.
- Las respuestas procesadas por este autenticador incluyen `Cache-Control` con `no-store`.

`AutenticarApiVentas` conserva su clase pública y pasa a reutilizar este comportamiento. Se agregó `EnsureApiWriteUser`, registrado como `api.write.user`, para separar consultas y escrituras. `EnsureApiUser` admite varios roles explícitos.

### 3.2. Matriz aplicada

| Área | Lectura | Escritura |
|---|---|---|
| Productos y stock comerciales externos | Pública según el contrato existente. | Stock externo requiere administrativo, repartidor o servicio válido con cuenta técnica de auditoría. |
| Productos con `interno=1` | Usuario administrativo. | Se mantienen los controles de las rutas operativas. |
| APIs operativas internas de G3 | Administrativo o repartidor. | Administrativo. |
| Cobros y rendiciones de `/api/v1` | Administrativo, contador o servicio válido. | Los endpoints de esta etapa son de consulta. |
| Nuevas consultas bancarias | Administrativo, contador o servicio válido. | Importación de extractos para administrativo o contador. |
| Ventas | Administrativo, repartidor, contador o servicio válido. | Alta, edición, anulación y restauración para administrativo o repartidor. Cambio de estado también admite servicio con identidad de auditoría configurada. |
| Promociones | Administrativo, repartidor, contador o servicio válido. | Administrativo. |
| Cuenta Corriente en la web | Administrativo o contador. | Administrativo. |
| API de escritura de Cuenta Corriente | Según la autorización de la ruta. | Administrativo o servicio válido con identidad de auditoría configurada. |

La operación bancaria del contador se conserva porque forma parte de los permisos de negocio existentes de G2. En Cuenta Corriente se ocultó el bloque de gestión de movimientos a los usuarios que carecen del permiso de escritura y se adaptó el manejador JavaScript para tolerar su ausencia.

### 3.3. Identidad de auditoría

`UsuarioActual` dejó de crear o sincronizar usuarios durante una solicitud. También dejó de seleccionar un administrador predeterminado y de aceptar identidades suministradas por encabezados o atributos arbitrarios.

Para una solicitud de usuario, el identificador procede del modelo autenticado `App\Modules\Login\Models\User`, con estado activo. Para una solicitud de servicio, procede de `services.internal_api.user_id`, configurado mediante `INTERNAL_API_USER_ID`. Si falta esa configuración o el usuario está inactivo, las operaciones que necesitan actor responden `503`.

En esta copia local se creó la cuenta `servicio.integracion`, con ID `22`, estado activo y rol `contador`. Se eligió un rol con menos privilegios interactivos que administrativo. La contraseña se generó aleatoriamente y se descartó. La autenticación de servicio utiliza el token interno configurado en el `.env` local.

Esta cuenta y su configuración fueron una decisión de implementación para evitar atribuir acciones automáticas a un administrador humano. La cuenta pertenece a `usuarios`, la tabla canónica de identidad. Las claves foráneas revisadas de la base restaurada apuntan a `usuarios(id)`.

El token interno y la configuración local contienen secretos y deben mantenerse privados. Cada instalación necesita preparar su propia cuenta de auditoría y sus credenciales. Los consumidores que tengan variables de token independientes todavía requieren configuración y prueba de su circuito completo.

## 4. Recuperación autorizada de MySQL

### 4.1. Daño encontrado

El primer arranque de una copia física falló con `Missing MLOG_CHECKPOINT`. También se detectaron daños de Aria en tablas internas de permisos y tablas de phpMyAdmin sin su espacio de tablas disponible.

Se archivó una copia física completa mientras el servidor original estaba detenido. La recuperación se realizó sobre otra copia, con `innodb_force_recovery=6`, y se exportaron únicamente las dos bases del proyecto y las cuentas con sus permisos recuperables.

La recuperación necesitó omitir el redo dañado. Se verificaron todos los registros legibles recuperados, pero ese procedimiento no permite certificar transacciones incompletas que dependieran de ese redo. Esta limitación se comunicó antes de la autorización de reconstrucción.

### 4.2. Validación antes de sustituir XAMPP

| Base | Tablas recuperadas | Registros anteriores a las actualizaciones |
|---|---|---|
| `sistema_gestion` | 78 | 1.394 |
| `sistema_gestion_g1_g3_20261026` | 17 | 30 |
| **Total** | **95** | **1.424** |

Se restauró el volcado completo en un servidor de prueba sano construido desde el respaldo estándar de XAMPP. Se comparó el contenido de cada tabla mediante cantidad de filas y SHA-256 de sus filas serializadas y ordenadas. Las 95 tablas coincidieron. `mysqlcheck` aprobó sus comprobaciones.

También se recuperaron y restauraron las cuentas MySQL y sus permisos. Las nueve sentencias de creación y concesión recuperadas coinciden con las obtenidas del servidor original después de reconstruirlo.

### 4.3. Sustitución del entorno original

Después de la autorización expresa del usuario se detuvieron los servidores de prueba. El proceso de la copia dañada no terminó después de solicitar un apagado normal y se finalizó verificando previamente que pertenecía a esa copia de recuperación.

Se movió el directorio original a `C:/xampp/mysql/data-anterior-20261108` y se instaló la copia sana validada como `C:/xampp/mysql/data`. Se inició el MySQL original con `C:/xampp/mysql/bin/my.ini`, en el puerto `3306`, con recuperación forzada desactivada.

Las tablas internas de MySQL y phpMyAdmin se reconstruyeron desde el respaldo estándar y se restauraron las cuentas y permisos recuperados. Esta operación afecta la instalación local de XAMPP además de las bases del ERP. El directorio original dañado queda archivado como evidencia de recuperación.

## 5. Actualización controlada del esquema

Se ejecutaron por ruta individual tres migraciones existentes en esta rama, primero en la copia de validación.

| Migración | Efecto aplicado |
|---|---|
| `app/Modules/Productos/database/migrations/2026_11_03_000001_add_costo_y_recargo_to_producto_table.php` | Agrega costo y recargo de productos, obtiene el costo del proveedor principal activo cuando corresponde y amplía el campo de origen del historial de precios. |
| `app/Modules/Productos/database/migrations/2026_11_03_000002_widen_origen_in_historial_precio_table.php` | Asegura el ancho del origen del historial de precios requerido por los nuevos valores. |
| `database/migrations/2026_11_04_000001_create_archivo_importado_log_table.php` | Crea la tabla de registro de archivos importados. |

Entre las tablas preexistentes, los únicos cambios de contenido detectados después de las migraciones fueron el registro de tres migraciones y los campos nuevos de `producto`. Se conservaron sus 20 productos y las filas de las demás tablas. La tabla de archivos importados quedó vacía.

Después de las migraciones y de crear la cuenta técnica, la base principal tiene 79 tablas y 1.398 registros. La segunda base conserva sus 17 tablas y 30 registros. El total final es de **96 tablas y 1.428 registros**.

Las migraciones originales de la rama conservan su contenido. Se aplicaron al entorno local con sus archivos existentes.

### 5.1. Migraciones que continúan pendientes

| Archivo | Motivo |
|---|---|
| `app/Modules/Productos/database/migrations/2026_10_28_000001_remove_lotes_vencimientos_and_legacy_mock_tables.php` | Elimina tablas y columnas, incluidas estructuras históricas con datos. Requiere resolver el destino de esos datos y el alcance de lotes y vencimientos. |
| `database/migrations/2026_11_04_000002_create_movimiento_bancario_table.php` | Intenta crear una tabla bancaria que ya existe con otro esquema. Requiere una transformación que preserve los movimientos existentes y unifique los consumidores. |

La actualización actual utiliza rutas individuales de migración. Una ejecución general de `php artisan migrate` todavía alcanzaría estas migraciones pendientes y necesita una corrección previa del plan de instalación.

## 6. Archivos agregados y modificados

| Archivos | Motivo |
|---|---|
| `app/Modules/Login/Middleware/AuthenticateApiSessionRequest.php` | Nuevo autenticador común de sesión y Bearer para APIs operativas. |
| `app/Modules/Login/Middleware/EnsureApiWriteUser.php` | Nuevo control de rol limitado a escrituras. |
| `app/Modules/Login/Middleware/EnsureApiUser.php`, `bootstrap/app.php` | Soporte de varios roles y registro del alias nuevo. |
| `app/Http/Middleware/IdentidadUsuario.php`, `app/Support/UsuarioActual.php` | Resolución de identidad autenticada y eliminación del aprovisionamiento implícito. |
| `config/services.php`, `.env.example` | Configuración explícita del usuario técnico de auditoría. |
| `routes/api.php` | Protección de las consultas de cobros y rendiciones. |
| `app/Modules/Productos/routes/api.php`, `app/Modules/Productos/routes/web.php` | Controles de lectura, escritura e integración de G3. |
| `app/Modules/Productos/Middleware/AuthorizeProductInternals.php` | Nuevo permiso para datos internos de productos. |
| `app/Modules/Ventas/Middleware/AutenticarApiVentas.php`, `app/Modules/Ventas/routes/api.php` | Reutilización de autenticación y separación de permisos por operación. |
| `app/Modules/Promociones/routes/api.php` | Autenticación y permisos de escritura. |
| `app/Modules/CuentaCorriente/routes/api.php`, `app/Modules/CuentaCorriente/routes/web.php` | Permisos de escritura de cuenta corriente. |
| `app/Modules/ConciliacionBancaria/routes/web.php` | Protección de las consultas bancarias y de importación. |
| `app/Modules/Shared/Http/Middleware/G2SchemaReady.php` | Verificación de la estructura necesaria para conciliación automática. |
| `public/assets/js/safe-html.js` | Utilidad nueva de escape y validación de URL de imágenes. |
| `resources/views/layouts/app.blade.php`, `resources/views/layouts/g3.blade.php` | Carga de la utilidad y escape del mensaje de toast de G3. |
| `resources/views/inventario/index.blade.php` | Escape de datos dinámicos, argumentos de eventos e imágenes. Consulta de costos internos limitada a administrativo. |
| `resources/views/conciliacion/split-screen.blade.php` | Escape de los textos y atributos dinámicos revisados. |
| `resources/views/proveedores/index.blade.php` | Encabezado CSRF faltante en la sincronización de asociaciones. |
| `resources/views/saldos/saldo.blade.php` | Visibilidad de gestión de movimientos según permiso y adaptación de su listener. |
| `tests/Feature/ModuleApiSecurityTest.php` | Nuevas pruebas de acceso, identidad, roles, Bearer, sesión y CSRF. |
| `tests/Feature/G1CatalogApiTest.php` | Actualización de la comprobación del middleware del contrato de productos. |
| `tests/Frontend/SafeHtml.test.js` | Pruebas del escape, argumentos de eventos y URLs de imágenes. |

El `.env` local se actualizó para la identidad del servicio. La cuenta técnica y la recuperación de MySQL son cambios del entorno local. Los archivos de código y `.env.example` enumerados sí integrarían un commit si se seleccionan. Un tercero que descargue esos archivos recibirá los cambios de permisos y necesitará configurar su servicio de forma explícita.

## 7. Pruebas y resultados

### 7.1. Pruebas automatizadas

| Comprobación | Resultado |
|---|---|
| Selección de regresión de seguridad, APIs de G1 e integración de G2 | 126 pruebas y 463 aserciones aprobadas. |
| Todas las pruebas de `tests/Unit` | 101 pruebas y 289 aserciones aprobadas. |
| Utilidad de HTML seguro | 3 pruebas JavaScript aprobadas. |
| Sintaxis PHP de archivos de código agregados o modificados | 20 archivos aprobados. |
| Sintaxis de scripts de Inventario, Cuenta Corriente y fragmento bancario renderizados | 5 scripts aprobados. |
| Revisión general segura de Feature y Caja | 418 pruebas, 2.042 aserciones, 4 fallos y 1 prueba marcada como riesgosa. |

Los conjuntos se superponen, por lo que sus cantidades no se suman como pruebas distintas. La revisión general usó SQLite en memoria. Excluyó seis archivos que utilizan `UsesG3Database`, porque ese trait fuerza una base MySQL concreta, y `VentasApiTest`, cuyo `setUp` vuelve a crear tablas ya instaladas por las migraciones de prueba.

Persisten tres fallos de `G4DependencyRegistrationTest`, relacionados con expectativas de controladores, catálogos o consultas simuladas que quedaron desactualizadas, y uno de `G4SalesSchemaTest`, que espera la vista `ventas` mientras el controlador utiliza `ventas.ventas`. La marca de prueba riesgosa corresponde al caso de consultas simuladas de cuenta corriente. Estos resultados impiden certificar la suite general completa.

La prueba nueva de seguridad cubre 26 endpoints privados frente a un visitante con encabezados de identidad falsificados, además de los casos de roles, sesión expirada o reemplazada, Bearer inválido, cuenta técnica, CSRF e identidad de auditoría.

### 7.2. Solicitudes sobre el MySQL restaurado

Se despacharon solicitudes mediante el kernel real de Laravel con los controladores y la base restaurada. Los resultados se guardan en la carpeta de evidencias local. Las comprobaciones de escritura de seguridad utilizaron la base aislada de pruebas.

| Solicitud | Identidad | Resultado |
|---|---|---|
| `GET /api/v1/health` | Visitante / servicio | `401` / `200`. |
| `GET /api/v1/clientes` | Visitante / servicio | `401` / `200`, con 20 elementos en la primera página. |
| `GET /api/v1/cobros?cliente_id=1` | Visitante / servicio | `401` / `200`, lista vacía para ese cliente. Esto verifica la consulta y su autorización, no una cobranza completa. |
| `GET /api/v1/rendiciones` | Servicio | `200`, con 15 elementos. |
| `GET /api/v1/caja/2026-11-08` | Servicio | `200`. |
| `GET /api/v1/productos` | Visitante | `200`, con 20 productos en la respuesta. |
| `GET /api/v1/catalogo` | Visitante | `200`, con 12 elementos en la página devuelta. |
| `GET /api/productos?interno=1` | Visitante / servicio / Bearer inválido | `401` / `403` / `401`. |
| `GET /api/productos?interno=1` | Administrativo con sesión válida | `200`. |
| `GET /inventario`, `GET /saldo` | Administrativo con sesión válida | Ambas pantallas respondieron `200`. |
| `GET /api/movimientos-bancarios` | Administrativo con sesión válida | Continúa en `500` por incompatibilidad entre esquema y modelo. Pendiente de la sección 9. |

La comprobación final de contenido, después de las solicitudes, identificó únicamente la cuenta técnica nueva respecto de la copia posterior a las tres migraciones. `mysqlcheck` aprobó la revisión final de las dos bases y el volcado final terminó correctamente.

## 8. Respaldos y arranque

Los respaldos se encuentran en `../Entregables/Seguridad_MySQL_2026-11-08`.

| Elemento | Uso |
|---|---|
| `proyecto-recuperado.sql` | Respaldo lógico completo anterior a las tres migraciones y a la cuenta técnica. Fue restaurado y comparado. |
| `proyecto-despues-verificado.sql` | Respaldo lógico final de las dos bases. |
| `mysql-data-antes` | Copia física del directorio original dañado. |
| `mysql-data-restaurado-verificado` | Copia física del estado sano tras el apagado final. |
| `env-antes.privado` | Configuración local anterior. Contiene secretos. |
| `cuentas-permisos-recuperados.privado.sql`, `cuentas-finales.privado.sql` | Cuentas MySQL y permisos, comparados y coincidentes. Contienen secretos. |
| `datos-*.json`, `mysqlcheck-*.txt`, `smoke-mysql.jsonl`, `phpunit-*.txt` | Evidencia de integridad, solicitudes y pruebas. |
| `LEER-RESPALDOS.md` | Identificación de los respaldos completos y de los intentos de volcado incompletos. |

Los archivos `mysql-recuperado-antes.sql` y `bases-aplicacion-recuperadas.sql` son intentos incompletos afectados por daños en tablas internas. Para una restauración anterior se utiliza `proyecto-recuperado.sql`.

Al finalizar se detuvo el MySQL iniciado para las verificaciones y se conservaron apagados los servidores de prueba. Para trabajar localmente se inicia MySQL desde XAMPP y se ejecuta, dentro de `ERP-Juan23`, el comando habitual.

```powershell
C:\xampp\php\php.exe artisan serve
```

Los cambios del autenticador se aplican en el mismo servidor Laravel. Los consumidores que todavía utilizan HTTP hacia el propio servidor requieren resolver su transporte como parte del trabajo pendiente.

## 9. Trabajo pendiente para cerrar el sistema

Esta tabla reúne los pendientes del relevamiento previo que esta etapa conserva y los bloqueos comprobados durante la recuperación. Las reglas de negocio abiertas requieren confirmación de su equipo responsable antes del cierre funcional.

La tabla agrupa trabajo y combina defectos confirmados con verificaciones todavía pendientes. Su cobertura debe contrastarse con cada historia de usuario y con recorridos completos de negocio. La revisión adicional del mismo día confirmó los controles incompletos de cierre de caja en Ventas y de organización de selecciones en Conciliación, detallados a continuación.

| Prioridad | Área o grupo | Pendiente | Criterio de cierre |
|---|---|---|---|
| Alta | G2, instalación bancaria | El modelo nuevo usa `fecha_operacion`, `signo_monto` y `estado_conciliacion`, mientras la tabla existente tiene el contrato anterior. La migración nueva intenta crear la misma tabla. | Transformación compatible con datos existentes, mapeo de campos, consultas e importación bancaria probadas. |
| Alta | G3 y esquema compartido | Migración destructiva de lotes, vencimientos, claves y tablas históricas pendiente. | Decidir qué información se conserva y preparar una actualización recuperable antes de ejecutarla. |
| Alta | Todos, instalación | Faltan completar y verificar los caminos de instalación desde cero, incluida la cobertura de Promociones y Conciliación. | Instalación limpia reproducible y actualización de una copia con datos, con el mismo resultado funcional. |
| Alta | G2, cobros y rendiciones | Distinguir aprobación de rendición, confirmación del cobro y aplicación en caja y cuenta corriente. Revisar fallos parciales y reintentos. | Un cobro produce los movimientos financieros correspondientes una sola vez y los errores conservan un estado consistente. |
| Alta | G2, conciliación | El origen de cobros y el modelo del repositorio no satisfacen todavía la estructura requerida por la conciliación automática. | Contrato real de cobros integrado y conciliación automática probada con su esquema. |
| Alta | G2, circuito bancario | Conciliación manual, asociaciones múltiples, disputas, gastos, cheques, anulaciones e inmutabilidad necesitan completar reglas y pruebas. | Casos positivos, rechazo de asociaciones incompatibles y reversión financiera verificados. |
| Alta | G2, importación | Revisar lectura de importes y formatos del extracto y completar las acciones pendientes de la interfaz dividida. | Archivos reales con decimales, miles, duplicados y errores de formato producen resultados correctos y trazables. |
| Alta | G2, interfaz de conciliación | `organizarSeleccion()` muestra un aviso y las pestañas generan textos descriptivos. La organización de movimientos y cobranzas requiere implementar el flujo operativo. Comprobado en `resources/views/conciliacion/split-screen.blade.php`. | La selección permite ejecutar y consultar operaciones reales, con validación de importes, permisos y persistencia. |
| Alta | G4 y G1 | Alinear saldo y deudores con los endpoints consumidos por G1 y completar facturas pendientes con cliente, comprobante, vencimiento e importe pendiente. | Respuestas reales con deuda y pagos aplicados, paginación y filtros acordados, y pruebas de vencimientos. |
| Alta | G1, consumo de G2 | Completar el uso del historial paginado de pagos confirmados y de las consultas de caja en reportes. | G1 distingue datos completos, respuesta vacía y servicio fallido y refleja únicamente cobros válidos según el contrato acordado. |
| Alta | Integración G1–G4 | El valor de integración `internal` no tiene una implementación equivalente en todos los proveedores de Ventas. Revisar mocks, URLs y tokens independientes. | Todas las pantallas operativas utilizan sus fuentes reales acordadas y se verifica la configuración por instalación. |
| Alta | Transporte entre módulos | Quedan consumidores con HTTP hacia la misma instancia de `artisan serve`, con riesgo de espera circular. | Transporte interno seguro o despliegue con concurrencia verificado para cada consumidor. |
| Alta | G4, Ventas y Promociones | Revisar precisión monetaria, recálculo de precios y descuentos en servidor y condiciones de las promociones. | Importes con centavos y descuentos límite conservan los totales correctos y rechazan datos manipulados. |
| Alta | G4–G2, cierre de caja desde Ventas | El botón `btnCerrarCaja` ejecuta únicamente el aviso de función pendiente. Comprobado en `resources/views/ventas/ventas.blade.php`. | Conectar el botón con el circuito de cierre existente, validar permisos y condiciones y comprobar el resultado en Caja. |
| Alta | G4–G3, stock | Las notificaciones de stock necesitan garantizar idempotencia, reintentos y trazabilidad de fallos. | La venta y sus cambios impactan una sola vez y los fallos de stock son recuperables y visibles. |
| Alta | G3, stock y compras | Validación de unidades contra su producto y concurrencia de movimientos, recepciones y pagos. | Solicitudes simultáneas y reintentadas conservan stock, cantidades e importes consistentes. |
| Alta | G2–G4, entregas | Contrato de zona, asignaciones y consultas agrupadas necesitan compatibilidad entre módulos y con `ONLY_FULL_GROUP_BY`. | Planificación, edición, despacho y rendición probados con pedidos y zonas reales. |
| Alta | Dependencias | El relevamiento previo registró diez avisos de seguridad en seis paquetes. | Actualizar versiones compatibles, repetir la auditoría y ejecutar regresiones. El cierre depende del resultado de esa comprobación actualizada. |
| Media | Frontend transversal | Continúan diferencias de estilos, conflictos de visibilidad de modales y otros renderizadores HTML por revisar. | Navegación, diálogos, formularios, permisos visibles y estados de error probados en navegador para los cuatro grupos. |
| Media | G1 y reportes | Completar las exportaciones y reportes de caja, ventas diarias y stock según las historias acordadas. | Descargas y cifras comparadas con datos reales y con los filtros de pantalla. |
| Media | Pruebas | Cuatro fallos de G4 persisten, `VentasApiTest` duplica el esquema y seis archivos fuerzan otra base MySQL. El ejecutor principal omite algunas pruebas unitarias. | Suite aislada y repetible que cubra todos los módulos sin depender de bases personales. |
| Media | Datos de demostración | Revisar el seeder que restablece contraseñas de usuarios existentes. | La carga de demostración respeta las cuentas existentes y su uso queda acotado al entorno de prueba. |
| Media | Alcance funcional G3/G4 | Confirmar el alcance vigente de lotes, vencimientos, límites de crédito, notas de crédito y funciones anunciadas pendientes. | Historias, implementación, interfaz y base representan el mismo alcance aprobado. |
| Media | Documentación y repositorio | Actualizar contratos y enlaces, incorporar CI y coordinar la limpieza de estructuras o ramas históricas. | Documentación contrastada con rutas reales, validación automática y retiro de artefactos acordado por sus responsables. |

## 10. Cómo revertir esta etapa

### 10.1. Código y configuración

Primero conservar una copia del trabajo actual y separar cambios posteriores. El inventario de archivos de la sección 6 identifica el perímetro de esta etapa. Para los archivos previamente existentes se puede recuperar su contenido desde el commit base `060cb9badcb2ff5bc9301975edf038f27fd1091d`. Los archivos nuevos de esta etapa se retiran según ese inventario. Una restauración global del repositorio puede descartar otros trabajos y requiere revisar previamente el diff.

La configuración local anterior está en `env-antes.privado`. Restaurar o quitar específicamente `INTERNAL_API_TOKEN` e `INTERNAL_API_USER_ID` según el estado previo y refrescar la caché de configuración si estuviera activa. Revertir el código de seguridad vuelve a habilitar los defectos descritos en este informe.

### 10.2. Bases del proyecto

Antes de una reversión realizar un nuevo respaldo de las bases actuales y detener los escritores. El archivo `proyecto-recuperado.sql` contiene los datos completos anteriores a las tres migraciones y a la cuenta técnica. Para obtener exactamente ese esquema anterior, restaurarlo primero en otra instancia sana y comparar. Un importado sobre una base que ya tenga tablas nuevas puede conservar estructuras adicionales que el volcado anterior desconoce, por lo que requiere revisar la lista de tablas.

La reversión individual de migraciones tiene límites. El `down()` de costos elimina sus columnas y pierde los valores añadidos. El `down()` del historial intenta volver a un enum más corto y puede ser incompatible con valores nuevos. La reversión del registro de archivos elimina esa tabla. Para recuperar el estado exacto anterior se utiliza el respaldo verificado, conservando previamente cualquier dato nuevo de negocio.

Si solo se retira la cuenta técnica, comprobar antes que ninguna operación posterior la referencia. Las acciones automáticas ya auditadas con ese usuario deben conservar su trazabilidad. Quitar la configuración de auditoría provoca respuestas `503` en las escrituras de servicio que necesitan actor.

### 10.3. Instalación MySQL de XAMPP

Con MySQL detenido, el respaldo físico `mysql-data-restaurado-verificado` permite recuperar la instalación sana documentada al cierre de esta etapa. Conservar previamente el directorio actual y comprobar rutas y permisos antes de sustituirlo.

`C:/xampp/mysql/data-anterior-20261108` y `mysql-data-antes` contienen la instalación original dañada. Sirven para conservar evidencia y volver a intentar una recuperación especializada. Reinstalarlos directamente como directorio activo reintroduce el daño que impedía arrancar MySQL.

Las cuentas MySQL y sus permisos están respaldados aparte. Una restauración de las tablas internas desde el respaldo estándar debe volver a aplicar esos permisos para reproducir el acceso recuperado.
