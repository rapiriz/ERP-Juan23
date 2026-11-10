# G2: correcciones de finanzas, rendiciones y entregas

Fecha: 09/11/2026. Rama local: `develop`, conservando los cambios previos de instalación, seguridad y funcionalidades de G1.

## 1. Alcance y resultado

Se trabajó sobre los trece incisos del apartado **G2 — Finanzas, rendiciones y entregas**, con autorización para modificar los grupos necesarios. Se implementaron y verificaron los recorridos nuevos sobre las APIs, servicios y base integrada existentes. No se hizo commit, push ni reemplazo de la rama por otra versión del repositorio.

Las operaciones nuevas quedan funcionales en el entorno local integrado. Los históricos que no permiten demostrar qué dinero se aplicó se conservan y se bloquean para revisión; no se fabricó una aplicación ni una deuda para dar esos casos por resueltos. Este informe separa esas restricciones de los errores corregidos. No certifica que todos los apartados del ERP estén terminados.

| Prioridad original | Inciso | Error o faltante encontrado | Corrección y comprobación |
| --- | --- | --- | --- |
| Alta | Esquema bancario | Convivían `cobro` y `cobros`; el modelo bancario apuntaba al origen equivocado. Otras consultas usaban `id`, `monto` o `fecha_cobro` como columnas que no existían en el origen real. | Se usa `cobro`, PK `id_cobro`, `monto_total`, `fecha`; se conservan alias JSON para la interfaz. Consultas autenticadas de banca y cobranzas devolvieron `200` sobre MariaDB. |
| Alta | Importación bancaria | El cast directo convertía `1.250,75` en `1,25`. Faltaban controles de fecha/estructura y se podían omitir errores de inserción o de archivo. | Analizador de importes en centavos y formatos explícitos; fechas reales, cabeceras XLS y registros TXT validados; importación completa o rechazo completo, archivo privado y deduplicación por hash/fila. |
| Alta | Conciliación automática | Orígenes, referencias y tipos de Caja eran incompatibles; se buscaba `ingreso` en lugar de los tipos reales. Cheque no tenía relación con Cobro. | Normalización de orígenes reales y relación Cheque–Cobro. Coincidencia exacta de importe/signo, margen absoluto de dos días e identificación por referencia o documento; las ambigüedades quedan para revisión. |
| Alta | Conciliación manual | Faltaban controles de existencia, disponibilidad, suma, estado, período y transacción; sólo admitía un origen sin validación completa. | Grupos de uno o varios movimientos/orígenes, importes exactos, reservas únicas, asignación de importes entre detalles, auditoría, idempotencia y reversión de grupos abiertos. |
| Alta | Gastos, disputas y períodos | Había clasificaciones sin un recorrido operativo completo ni protección uniforme de períodos cerrados. | Registro de ajuste de gasto/impuesto, disputa/resolución con nota, asociación de movimientos sin período y cierre condicionado a movimientos resueltos. Mutaciones y reversiones bloqueadas en períodos cerrados. |
| Alta | Cheques rechazados | Cambiar el cheque a rechazado no restablecía la deuda y podía borrar una conciliación sin compensación financiera. | Relación verificable con el cobro/pago. Se revierte primero el grupo bancario abierto, luego caja y cuenta mediante movimientos compensatorios; se conserva auditoría y se marca el cheque rechazado. |
| Alta | Aprobación de rendiciones | Podía guardar aprobada aunque fallara Caja; transferencia/cheque sólo dejaban mensajes en el log; se intentaba marcar ventas pagadas sin comprobar pago total. | Servicio transaccional de revisión y aplicación: caja real, libro financiero, cobro, vínculo a venta, cheque cuando corresponde y auditoría obligatoria. No fuerza una venta a pagada por un cobro parcial. |
| Alta | Cobros individuales | La selección parcial no quedaba persistida y el estado de la rendición se confundía con la confirmación de cada cobro. | Estado individual persistente, actor y fecha de revisión, ID del cobro aplicado y exposición en las APIs. La cabecera permanece pendiente mientras queden cobros sin revisar. |
| Alta | Pendientes y anulación | Faltaba una pantalla para aplicar cada pendiente o anular sus efectos coherentemente. | `/cobros`, aplicación individual administrativa y anulación compensatoria. Repetir una anulación no resta dos veces ni borra el pago original. |
| Alta | Entregas y zonas | Ventas no exponía la zona que Entregas necesitaba; además G2 enviaba `en_preparacion` cuando el estado de G4 es `preparada`. | El adaptador de Cliente conserva `zona_id`; VentaResource expone `id_zona`; planificación usa transiciones acotadas del propietario de Ventas. |
| Alta | Listado de entregas | La agrupación por un solo campo no cubría las columnas seleccionadas con `ONLY_FULL_GROUP_BY`; también había totales inventados. | Agrupaciones completas y totales de bultos desde remitos, importes desde Ventas. Se comprobó el listado con el modo SQL estricto activo. |
| Media | Modificar pedidos, HU #125 | No existía el recorrido completo de agregar/quitar pedidos de una entrega en preparación. | Editor en la hoja de ruta y `PUT` dedicado, misma zona, control de propietario, reservas, remitos, estados y auditoría en una transacción. |
| Media | Interfaz bancaria | Organizar selección y varias pestañas sólo mostraban avisos o descripciones. | Interfaz de G1 con operaciones reales: selección y comparación, confirmación, propuestas, revisión, historial/reversión, cheques, importación, períodos y notas de operación. |

## 2. Protección del estado anterior

La carpeta de respaldo y evidencias está **fuera del proyecto Git**:

```text
C:\Users\juanf\OneDrive\Escritorio\Repositorio\Entregables\G2_Finanzas_2026-11-09
```

Se guardaron antes de editar:

- `codigo-antes/`: los 592 archivos versionados o no ignorados que existían al comenzar, incluido el trabajo local anterior.
- `estado-inicial.json`: huellas SHA-256 de ese corte. El manifiesto final distingue 70 archivos de esta etapa: 50 versiones anteriores para restaurar y 20 nuevos para revisar antes de retirar.
- `cambios-previos.patch`: diferencias previas respecto de Git.
- `env-antes.privado`: configuración anterior, con secretos; no compartir ni subir.
- `mysql-prueba-data/`: copia física tomada con MySQL detenido, utilizada exclusivamente como instancia aislada en el puerto **3315**.
- `proyecto-antes.sql`: respaldo lógico de las dos bases del proyecto, obtenido de esa copia coherente.
- `columnas-y-datos-previos.privado.json`: huellas de las columnas y registros anteriores.

La copia contenía 100 tablas entre `sistema_gestion` y `sistema_gestion_g1_g3_20261026`. Se verificaron **98 tablas de datos** después de las pruebas y después de actualizar el MySQL original: mismos registros y mismos valores en todas las columnas anteriores. Las dos tablas `migrations` se excluyen de esa igualdad porque registran la migración nueva.

La instancia original, puerto **3306**, se actualizó sólo después de las pruebas de la copia. `erp-install.php` aplicó la nueva migración, conservó `APP_KEY` y reutilizó la cuenta técnica existente #22. No se cargaron demostraciones ni se aprobaron/anularon cobros de trabajo: las cinco tablas nuevas quedaron vacías en la base local.

La base secundaria también se conservó. Las pruebas de escritura usaron transacciones revertidas en la copia. La prueba de concurrencia utilizó una tercera base independiente, `erp_g2_instalacion_limpia`, dentro de la instancia aislada. Sus datos sintéticos no se copiaron al MySQL original.

## 3. Archivos agregados y por qué

| Archivo nuevo | Responsabilidad |
| --- | --- |
| `app/Modules/ConciliacionBancaria/Services/BankMoney.php` | Interpretar importes sin truncar texto ni aceptar prefijos numéricos parciales; trabajar en centavos. |
| `.../Services/BankPeriodService.php` | Apertura/asignación de períodos, superposición, permisos y bloqueo de períodos cerrados. |
| `.../Services/BankReconciliationService.php` | Único caso de uso para conciliación manual/automática, disponibilidad, grupos, reservas, gastos, disputas, cierre y reversión. |
| `.../Controllers/BankOperationsController.php` | Validación HTTP y endpoints operativos de la interfaz bancaria. |
| `app/Modules/Rendiciones/Services/CollectionSettlementService.php` | Aplicar cobros a Caja y Cuenta Corriente, crear comprobante/vínculos y realizar anulaciones compensatorias. |
| `.../Services/RenditionReviewService.php` | Revisión parcial o completa, persistencia de estados individuales y cabecera coherente. |
| `.../Controllers/CollectionOperationsController.php` | Pantalla de pendientes, datos de cheques, aplicación y anulación administrativa. |
| `.../database/migrations/2026_11_09_000003_complete_g2_operations.php` | Ampliación conservadora de esquema, índices/vínculos de aplicación y auditoría. |
| `app/Modules/Shared/Services/G2OperationAudit.php` | Diario obligatorio de operaciones nuevas; un fallo impide confirmar la transacción. |
| `app/Modules/Entregas/Services/DeliveryPlanningService.php` | Armado/edición/confirmación atómicos, zona, reservas, remitos y auditoría. |
| `app/Modules/Ventas/Services/DeliverySaleService.php` | Transiciones del dominio propietario de Ventas para reservar, liberar y entregar, con historial. |
| `resources/views/rendiciones/cobros.blade.php` | Listado operativo y formularios de cobros usando el layout compartido de G1. |
| `public/js/g2-banking.js` | Operaciones reales de las pestañas y selección bancaria, mensajes y control de solicitudes. |
| `public/js/g2-delivery-editor.js` | Editor de pedidos de la hoja de ruta, carga de disponibles y guardado del listado final. |
| `public/assets/css/g2-operations.css` | Estilos acotados a estas interfaces: tablas, selección, pestaña activa, editor y elementos ocultos. |
| `tests/Feature/G2FinancialOperationsTest.php` | Diez pruebas de revisión, pago, anulación, cheque, permisos, protección y reversión por fallo. |
| `tests/Feature/G2BankReconciliationTest.php` | Diez pruebas de importación, formatos, grupos, ambiguos, períodos, gastos y disputas. |
| `tests/Feature/G2DeliveryPlanningTest.php` | Cinco pruebas de armado/edición, zona, propiedad, confirmación y rendiciones por tramos. |
| `tests/Frontend/g2-banking.test.cjs` | Centavos del resumen de selección y escape de contenido externo. |
| Este informe | Hallazgos, cambios, uso, evidencia, límites y reversión de esta etapa. |

En las filas abreviadas con `...`, la ruta continúa dentro del módulo indicado en la fila anterior del mismo bloque.

## 4. Archivos existentes modificados

### Banca de G2

| Archivo dentro de `app/Modules/ConciliacionBancaria/` | Modificación |
| --- | --- |
| `Services/BankColumnMapper.php` | Se reescribió el mapeo: columnas por cabecera, importe localizado o numérico real, fechas estrictas, serial de fecha Excel y TXT posicional validado. |
| `Services/BankStatementParserService.php` | El signo es la fuente de crédito/débito. Un importe absoluto positivo con signo egreso ya no se clasifica como crédito. |
| `Services/BankStatementImportService.php` | Valida todo antes de persistir, usa almacenamiento privado con resultado comprobado, transacción completa, deduplicación, período y auditoría. Retira el archivo nuevo si falla. |
| `Services/ConciliacionService.php` | Conserva las entradas y campos de detalle usados por los controladores anteriores, agrega el grupo y delega las mutaciones al caso de uso único. |
| `Services/ChequeService.php` | Depósito/rechazo con validación, relación con cobro, compensación financiera y auditoría. |
| `Repositories/Cobro.php` | Mapeo a `cobro`/`id_cobro`, sin timestamps inexistentes; alias de ID/cliente/usuario para compatibilidad JSON. |
| `Repositories/ConciliacionRepository.php` | Se retienen las lecturas. Se retiró el motor de candidatos/mutación duplicado que usaba columnas y tipos incorrectos. |
| `Repositories/ChequeRepository.php` | Se retiró el borrado aislado de conciliación que no conservaba un recorrido trazable. |
| `Models/Cheque.php` | Relación `cobro()` con el origen real. |
| `Models/ConciliacionDetalle.php` | Acepta grupo e importe aplicado para representar relaciones múltiples sin duplicar el total. |
| `Models/MovimientoBancario.php` | Campos de compatibilidad, período y relación; conserva el actor explícito y sincroniza columnas nuevas/históricas para escrituras nuevas. |
| `Controllers/CobranzaErpController.php` | Filtros y orden usan columnas reales; compatibilidad de `confirmado` con el estado `registrado` del origen; validación de consulta/paginación. |
| `Controllers/MovimientoBancarioController.php` | Valida filtros; conserva errores de validación y conflictos sin convertirlos en una falsa importación exitosa. |
| `Controllers/ConciliacionWebController.php` | La entrada principal muestra la interfaz operativa; el GET no crea períodos ni modifica datos. |
| `Controllers/ChequeController.php` | El rechazo exige motivo y lo entrega al servicio. |
| `routes/web.php` | Registro de endpoints de grupos, períodos, clasificación, asignación e importación con sesión, permisos y CSRF. |

### Caja, rendiciones y entregas de G2

- `app/Modules/Caja/Services/CajaService.php`: transacciones en apertura, cierre, cierre automático, conteo, alta/reversión y edición de movimientos. La edición de un movimiento que pertenece a un pago/cobro o a una conciliación se rechaza: debe usarse la reversión del origen.
- `app/Modules/Caja/Repositories/CajaRepository.php`: lecturas con bloqueo durante transacciones, serialización de apertura por usuario y detección de movimientos protegidos.
- `app/Modules/Rendiciones/Services/RendicionService.php`: delega revisión al nuevo servicio, conserva datos del medio de pago y hace atómico agregar un cobro. La precarga ya no borra cobros revisados ni sustituye registros financieros. Si una entrega se rinde por tramos, conserva el lote revisado y crea otro pendiente para los nuevos pedidos entregados.
- `app/Modules/Rendiciones/Repositories/RendicionRepository.php`: conserva metadatos y bloquea la cabecera al consultar dentro de una transacción, para serializar agregados y revisión.
- `app/Modules/Rendiciones/Models/RendicionCobro.php`: estados individuales, vínculo aplicado, revisión y metadatos de cheque/transferencia.
- `app/Modules/Rendiciones/Controllers/RendicionController.php`: recibe selección parcial y datos del medio; exige importes con centavos válidos. Conserva `422/409` de dominio y evita exponer SQL en errores inesperados.
- `app/Modules/Rendiciones/Controllers/Api/V1/{CobroApiController,RendicionApiController}.php`: campos individuales de aplicación/revisión. La certificación corresponde al libro financiero, no a liquidación bancaria.
- `app/Modules/Rendiciones/routes/web.php`: pantalla `/cobros`, datos de cheque, aplicar individual y anular.
- `app/Modules/Entregas/Services/EntregaService.php`: delega armado y confirmación al planificador transaccional; se elimina el éxito aparente después de fallar Ventas o la precarga de rendición.
- `app/Modules/Entregas/Repositories/EntregaRepository.php`: agrupación SQL compatible, remitos anulados excluidos y totales reales; no se conserva el cálculo ficticio de dos bultos por pedido ni el importe fijo cero.
- `app/Modules/Entregas/Controllers/EntregaController.php`, `routes/web.php`: guardado del listado final de pedidos con autorización de propietario y estado.
- `app/Modules/Shared/Http/Middleware/G2SchemaReady.php`: comprobación de las columnas reales del origen de cobros para la conciliación anterior.
- `public/js/g2-rendiciones.js`: selección de cobros pendientes y mensaje de revisión parcial; la aprobación rápida carga el detalle correcto antes de enviar IDs.
- `public/js/g2-entregas.js`: comunica los datos de la hoja de ruta al editor.
- `resources/views/conciliacion/show.blade.php`: mensajes de coincidencias ambiguas/sin candidato ajustados al criterio de identificación.
- `resources/views/conciliacion/split-screen.blade.php`: se reemplazan avisos/pestañas descriptivas por formularios, tablas y acciones reales.
- `resources/views/entregas/index.blade.php`: acceso y panel para modificar pedidos en preparación.
- `resources/views/rendiciones/index.blade.php`: acceso administrativo a cobros pendientes y anulaciones.

### Cambios necesarios en G1 y G4

Sí se modificaron otros grupos dentro de la autorización actual:

- **G4**, `app/Modules/CuentaCorriente/Services/FinancialReadService.php`: reconoce las reversiones vinculadas a los pagos nuevos. El pago revertido deja de consumirse como crédito aplicado de la factura; la deuda y los pendientes se restablecen sin borrar sus asientos.
- **G4**, `app/Modules/Ventas/Services/DeliverySaleService.php`: servicio nuevo del propietario de la venta. La liberación logística `preparada → confirmada` es acotada y auditada; no se abrió una regresión arbitraria de todos los estados del módulo.
- **G4**, `app/Modules/Ventas/Integraciones/HttpClienteApi.php`: conserva `zona_id` recibida desde la API de G1.
- **G4**, `app/Modules/Ventas/Resources/VentaResource.php`: expone `id_zona` y la zona dentro de cliente para que G2 pueda filtrar y validar.
- **G1**, `app/Services/Gateways/RendicionesGateway.php`: conserva el estado individual de G2 y no inventa confirmación para históricos sin esa información.
- **G1**, `app/Modules/Clientes/Controllers/ClienteController.php`: distingue rechazado/anulado individual aunque el estado de cabecera sea diferente.
- `README.md`: actualización de uso e instalación.

En esta etapa no se modificaron archivos ni esquemas de G3. Las pruebas integradas sí crearon temporalmente productos mediante sus APIs en la copia aislada y luego revirtieron esas escrituras.

## 5. Esquema nuevo y efecto de ejecutar migraciones

Se agregó **una migración modular**, sin borrar las anteriores:

```text
app/Modules/Rendiciones/database/migrations/
2026_11_09_000003_complete_g2_operations.php
```

| Tabla nueva | Para qué se necesita |
| --- | --- |
| `g2_cobro_aplicaciones` | Enlaza el cobro de G2 con el pago aplicado de G4 y sus movimientos compensatorios posteriores. Evita adivinar qué asiento corresponde a una anulación. |
| `g2_operacion_eventos` | Auditoría obligatoria de las operaciones nuevas, con actor, evento, registro, datos y fecha. |
| `g2_conciliacion_grupos` | Identidad del grupo, clave idempotente, selección, importe, método, período y estado activo/revertido. |
| `g2_conciliacion_reservas` | Reserva única de cada recurso monetario y sus alias para impedir doble uso concurrente. |
| `g2_entrega_reservas` | Reserva de venta en la planificación para impedir asignaciones activas duplicadas. |

Ampliaciones:

- `RENDICION_COBRO`: `datos_medio_pago` si faltaba, `estado_revision`, `id_cobro_aplicado`, `revisado_por`, `fecha_revision`, `nota_revision`.
- `CONCILIACION_DETALLE`: `id_grupo`, `monto_aplicado`.
- `entrega`: `id_zona`, `fecha_modificacion`, `id_usuario_modificacion`, cuando no existen.

Se detectó que la copia local ya tenía `datos_medio_pago`, pero el esquema reproducible desde migraciones no siempre lo creaba. La migración nueva lo completa; no depende de que ese cambio se haya hecho a mano en otra computadora.

Las columnas previas y sus valores se preservan. No se copian filas de `cobros` a `cobro`, no se vacían tablas, no se reasignan clientes ni se certifican pagos históricos por el solo hecho de que la cabecera diga aprobada. Las reservas bancarias se complementan con la comprobación de los detalles históricos existentes.

`down()` conserva datos financieros y auditoría. La reversión destructiva se realiza desde el respaldo coherente, después de evaluar las operaciones posteriores.

**Un push no transporta la base MySQL, pero sí comparte esta migración si se incluye en el commit.** Otro equipo que baje el código y ejecute `migrate`/`erp-install.php` ampliará su base configurada. Debe respaldarla y revisar el corte antes. No se debe afirmar que estos archivos no tienen efecto sobre otros grupos.

## 6. Importación: formatos y garantías

Se aceptan XLS binario real y TXT posicional del formato implementado. No se interpreta un CSV, XLSX o HTML renombrado como si fuera XLS.

Importes:

| Entrada | Centavos | Importe |
| --- | ---: | ---: |
| `1.250,75` | 125075 | 1250,75 |
| `1250.75` | 125075 | 1250,75 |
| `-1.250,75` | -125075 | -1250,75 |
| `1.250` | 125000 | 1250,00, agrupación argentina de miles |
| `0,75` | 75 | 0,75 |

Se rechazan prefijos como `12oops`, separadores incompatibles, más decimales de los permitidos, importes cero y valores fuera de rango. Los egresos se guardan como importe absoluto y signo separado, sin reclasificarlos como créditos.

XLS identifica las columnas por cabecera Fecha/Descripción/Importe y Saldo opcional. Acepta fechas de calendario y serial Excel; no depende de una columna inicial vacía. TXT valida tipo/longitud de registro, fecha de calendario, signo y posiciones de centavos; una fecha como 31 de febrero no se normaliza a otro día. Las descripciones TXT no UTF-8 se decodifican como Windows-1252 después de cortar las posiciones de bytes.

El archivo completo se analiza antes de insertar. Si hay errores de filas, se devuelve `422` y no se importa un subconjunto como si hubiera sido exitoso. Los saldos de cabecera se informan sólo cuando hay valores utilizables y secuencia cronológica; no se inventan cuando faltan. No se certifica una conciliación del saldo contable completo del banco por importar sus filas.

El archivo se guarda con nombre generado en el disco privado `local`, bajo `storage/app/private/extractos_bancarios`. Se comprueba el resultado de almacenamiento y se conserva hash SHA-256 en el log. Un fallo de transacción elimina el archivo nuevo del intento. Se rechaza un hash de archivo repetido y se omiten filas ya presentes según fecha, importe, signo, referencia y descripción normalizada.

El límite de validación de la aplicación es 20 MiB; los límites `upload_max_filesize` y `post_max_size` de PHP también deben permitir el tamaño que se quiera cargar. No se alteró globalmente la configuración PHP/XAMPP para aumentar esos límites.

## 7. Conciliación y períodos

### Manual y múltiple

La selección admite de uno a cien movimientos y de uno a cien orígenes. Todos los movimientos deben pertenecer al mismo período abierto y signo. Los orígenes deben existir, estar disponibles y tener signo compatible. La suma debe coincidir exactamente en centavos; no se aplican tolerancias silenciosas.

El grupo distribuye el importe entre detalles. Por ejemplo, banco $60 + $40 contra un cobro $100 crea dos detalles aplicados de $60 y $40, no dos cobros de $100. También se admite un movimiento contra varios orígenes y grupos de varios a varios con sumas exactas.

Cobro, ingreso en Caja y cheque pueden representar el mismo dinero. Se reservan sus alias y se impide elegirlos de nuevo, tanto en la misma selección como en otro grupo. No se ofrece como origen separado el movimiento de Caja que ya pertenece al cobro; el cheque debe estar depositado para conciliarse.

La confirmación guarda grupo, reservas, detalles, estado bancario y auditoría en una transacción. La clave de solicitud permite repetir la misma operación sin duplicarla. Reutilizarla para otra selección o para un grupo ya revertido produce conflicto.

### Automática

La propuesta exige importe y signo exactos, fecha dentro de dos días en valor absoluto y coincidencia de referencia o documento. Importe/fecha solos no se consideran prueba suficiente. Si un banco tiene varios candidatos, o dos bancos compiten por el mismo único candidato, quedan para revisión; no se elige uno por el orden de la consulta.

Se vuelve a comprobar disponibilidad al aplicar y no se acreditan pagos por conciliar: el movimiento de cobro ya existía. Un contracargo, gasto o disputa no se convierte en cobro sólo porque su importe sea positivo en la columna absoluta.

### Gastos, disputas y cierre

- Un gasto/impuesto bancario de débito se registra como ajuste bancario trazable y se concilia con ese ajuste. No se inventa un egreso físico de efectivo en Caja por una comisión del banco.
- Disputar o resolver requiere nota. Una disputa impide conciliar y cerrar el período mientras siga pendiente.
- Un movimiento histórico sin período puede asignarse al período abierto que contenga su fecha; no se mueve un registro que ya pertenece a otro período.
- No se admiten períodos superpuestos. La creación automática de un mes evita invadir períodos manuales existentes.
- El cierre exige que no queden pendientes, revisiones ni disputas. Los estados `matcheado` y `no_aplica` se consideran resueltos para este control. `no_aplica` es una clasificación, no prueba de que se haya registrado un gasto en un libro fiscal.
- Revertir un grupo requiere motivo y período abierto. Se conservan el grupo en estado revertido y la auditoría con sus detalles; se liberan reservas y se retiran únicamente los enlaces activos. No se borran extractos ni movimientos financieros.

La interfaz ofrece Manual, Alta confianza, Para revisar, Historial y Cheques. “Revisar selección” abre un resumen real y la confirmación compara importes; no muestra un aviso de una funcionalidad futura. Los errores se muestran en la pantalla sin convertirlos en tablas vacías exitosas.

## 8. Rendición y aplicación de cobros

La revisión administrativa bloquea la cabecera y sus cobros. Agregar un cobro usa el mismo bloqueo de cabecera, para no insertarlo después de una aprobación simultánea. La selección se valida contra esa rendición.

Cada cobro aplicado crea o vincula, dentro de la misma transacción:

1. El pago mediante `ClientPaymentService` del propietario financiero de G4.
2. Un ingreso real con el medio correspondiente en la caja abierta del administrativo que revisa.
3. El movimiento positivo en Cuenta Corriente y el registro de pago de la etapa anterior.
4. El comprobante de G2 en `cobro`, la asociación a `cobro_venta` y el vínculo verificable en `g2_cobro_aplicaciones`.
5. El cheque en cartera si corresponde, con número, banco y fecha exigidos.
6. El estado individual aplicado, actor, fecha y auditoría obligatoria.

Se comprueba cliente, venta, estado cobrable y pendiente de ese comprobante. No se acepta un pago superior al pendiente. Un pago parcial no cambia indiscriminadamente el estado logístico de Ventas a pagada. Efectivo, transferencia y cheque dejan registros reales, no sólo un mensaje en el log.

Se mantienen diferenciados **aplicación al libro** y **liquidación bancaria**: un cheque recibido y aplicado puede rechazarse después. La conciliación documenta el cruce bancario; la simple aprobación de rendición no prueba la acreditación del banco.

Si falla Caja, una fila posterior o la auditoría obligatoria, se revierte todo el lote de revisión. Una prueba forzó un error después de escribir el pago y verificó ausencia de cambios parciales en Caja, Cuenta Corriente, comprobante y estado.

### Estados individuales y cabecera

Los cobros nuevos tienen `pendiente`, `aplicado`, `rechazado` o `anulado`. Se conservan revisor, fecha, nota e ID del cobro aplicado. Mientras queden pendientes, la rendición continúa pendiente y devuelve `revision_parcial: true`. Al terminar, queda aprobada si tiene aplicaciones, o rechazada si no las tiene y se rechazó el conjunto.

Un rechazo posterior de los pendientes de una rendición parcialmente aplicada no revierte los que ya se aplicaron. Para esos cobros se usa la anulación explícita con su compensación. Repetir la aprobación de un cobro ya aplicado no genera otro ingreso.

Los importes de cabecera representan lo declarado, no se presentan como una prueba de liquidación de todas sus filas. Las APIs exponen los estados individuales y G1 conserva su separación entre declarado, aplicado y anulado.

## 9. Anulación y rechazo de cheque

Anular exige administrativo activo, motivo y una caja abierta del actor. Si hay una conciliación activa del cobro, de su ingreso en Caja o de su cheque, primero se debe revertir ese grupo.

La anulación genera un egreso de reversión en la caja actual del actor y un ajuste negativo compensatorio de Cuenta Corriente. No se reabre ni modifica una caja histórica ya cerrada. Se guardan los IDs compensatorios, motivo y actor; se marca el cobro/estado individual anulado y, cuando corresponde, el cheque anulado. No se elimina el pago original ni su auditoría.

El lector financiero reconoce el par pago/reversión: restablece deuda y pendiente del comprobante y deja de devolverlo como pago efectivo vigente. Una repetición devuelve el resultado ya anulado sin una segunda compensación.

Al rechazar un cheque se revierte primero su conciliación de grupo abierto, se anula su cobro por el mismo servicio y luego se marca rechazado con fecha y auditoría. Si algo falla, incluido que el período esté cerrado o falte caja, no queda un rechazo parcial.

Un cobro histórico sin vínculo financiero verificable, o una conciliación histórica que no pertenece a un grupo reversible, se bloquea con explicación. No se inventa una deuda ni se borra el detalle anterior para simular éxito. Esos casos requieren reconstrucción documental del origen.

## 10. Entregas, zonas y HU #125

Se consultó la [HU #125](https://github.com/rapiriz/ERP-Juan23/issues/125). Exige modificar sólo en preparación, quitar/devolver a despacho, incorporar pedidos de la misma zona y actualizar auditoría. No se importó otra rama completa ni se alteró el estado remoto del issue.

El armado crea la entrega, reserva pedidos confirmados de una misma zona y emite sus remitos en una transacción. Se usa el estado `preparada` de G4. No se acepta un pedido sin zona, de otra zona o asignado a otra entrega activa.

El editor de la hoja de ruta permite enviar el listado final. Puede vaciarse temporalmente una entrega en preparación. Al quitar un pedido:

- Se devuelve `preparada → confirmada` mediante el servicio acotado del dueño de Ventas.
- Se retira la asignación activa y se libera la reserva.
- El remito previo queda anulado y conservado.
- Se registra actor, fecha, pedidos agregados/quitados y zona.

Al agregar, se comprueba disponibilidad otra vez, se reserva, se genera un remito nuevo con sus artículos reales y se guarda el historial de Ventas. Una repetición del listado sin cambios no genera otro remito. Se bloquea la edición en camino o finalizada y se valida que un repartidor sólo cambie su entrega.

La confirmación de entrega y la precarga de rendición se realizan también en una transacción. Ya no se confirma éxito si falló la fase de Ventas o la precarga. Si el viaje se rinde por tramos, el tramo posterior no reemplaza cobros revisados; crea un nuevo lote pendiente cuando corresponde.

Los listados se probaron en MariaDB con `ONLY_FULL_GROUP_BY` activado. Bultos provienen de detalles de remitos vigentes e importes de las ventas reales. No se conserva el cálculo inventado de dos bultos por pedido.

Esta atomicidad utiliza **la integración local `internal` y una misma conexión de base**. Si G4 se convierte en un servicio remoto independiente, se bloquean estas mutaciones antes de enviar cambios parciales. Hace falta un contrato de reserva/compensación distribuida para trasladar esta garantía a varios servidores.

## 11. Rutas y seguridad

Las rutas `/g2/api/...` de esta tabla son endpoints de la interfaz con **sesión web vigente y CSRF en escritura**. No equivalen a una API externa habilitada con cualquier token técnico. Se conserva el esquema de autenticación previo de `/api/...` y `/api/v1/...`.

| Método | Ruta | Función |
| --- | --- | --- |
| GET | `/conciliacion`, `/conciliacion/split` | Interfaz bancaria operativa. |
| GET | `/g2/api/bancos?periodo_id={id}` | Períodos, movimientos, orígenes disponibles, propuestas e historial. |
| POST | `/g2/api/bancos/extractos` | Multipart `archivo`, validación/importación completa. |
| POST | `/g2/api/bancos/grupos` | `request_key` UUID, `movimientos` IDs, `origenes` con `origen`/`id`, `nota` opcional. |
| POST | `/g2/api/bancos/grupos/{uuid}/revertir` | `motivo` obligatorio. |
| POST | `/g2/api/bancos/movimientos/{id}/clasificar` | `accion`: `disputar`, `resolver`, `gasto`; `nota`. |
| POST | `/g2/api/bancos/movimientos/{id}/periodo` | Asignación de registro sin período, `periodo_id`. |
| POST | `/g2/api/bancos/periodos` | `fecha_desde`, `fecha_hasta`. |
| POST | `/g2/api/bancos/periodos/{id}/automatico` | Aplicación de coincidencias no ambiguas. |
| POST | `/g2/api/bancos/periodos/{id}/cerrar` | Cierre con validación de pendientes. |
| GET/PATCH | `/g2/api/cheques`, `/{id}/depositar`, `/{id}/rechazar` | Consulta, depósito y rechazo con motivo. |
| GET | `/cobros?estado=pendiente` | Listado administrativo; también aplicado, rechazado, anulado o todos. |
| POST | `/cobros/declarados/{id}/cheque` | Completar número, banco y fecha antes de aplicar. |
| POST | `/cobros/declarados/{id}/aplicar` | Aplicar un pendiente y recomputar estado de rendición. |
| POST | `/cobros/{id}/anular`, `/g2/api/cobros/{id}/anular` | Anulación compensatoria, `motivo`. Aquí `{id}` es el ID de `cobro`, no el ID de la fila declarada. |
| PATCH | `/g2/api/rendiciones/{id}/revisar` | `accion`, `cobros_aprobados` opcional, motivo al rechazar. |
| PUT | `/g2/api/entregas/{id}/pedidos` | `pedidos_ids`: listado final, con autorización de propietario y estado. |

Permanecen las rutas anteriores de armado, confirmación, períodos, cobros de rendición y APIs públicas de integración. Las respuestas de `/api/cobranzas-erp` usan el origen real y conservan alias de lectura; `confirmado` se interpreta como `registrado` en ese origen.

Las escrituras financieras de aplicación/anulación exigen administrativo activo; los servicios lo comprueban además de los middleware. El contador puede operar el área bancaria y consultar, pero no se le habilita una caja ni la anulación financiera de un cheque por ser contador. Los repartidores tienen acceso a sus entregas/rendiciones; no a la liquidación administrativa.

No se exponen archivos privados por una ruta pública. El JavaScript escapa referencias y descripciones; el editor de entrega usa nodos de texto para los nombres. Las validaciones del navegador sólo orientan: importes, disponibilidad, permisos y estados se vuelven a comprobar en el servidor.

## 12. Verificación realizada

### Automatizada

| Comprobación | Resultado |
| --- | --- |
| Pruebas nuevas de esta etapa | **25 pruebas, 172 aserciones, correctas**. |
| Selección de pruebas G2 y regresiones de G1/integración | **79 pruebas, 509 aserciones, correctas**. |
| Unitarias | **97 pruebas, 275 aserciones, correctas**. |
| Frontend Node | **36 pruebas, correctas**, incluidas las dos nuevas de banca. |
| Revisión general con el perímetro seguro anterior | **476 pruebas, 2.446 aserciones; cuatro fallos heredados de G4 y una advertencia de prueba riesgosa**. |
| Sintaxis PHP de la etapa | 57 archivos comprobados sin errores; formato Pint aplicado. |
| JavaScript nuevo y modificado | Comprobación de sintaxis; prueba en Chrome de selección, comparación, pestaña de historial y envío del grupo. |
| Instalación MariaDB desde cero | Correcta en `erp_g2_instalacion_limpia`, incluyendo el campo de medio de pago que faltaba en un esquema vacío. |
| Actualización local | Una migración aplicada, sin diferencias en las 98 tablas anteriores ni cambio de `APP_KEY`. |
| Integridad MySQL | `mysqlcheck` de ambas bases, salida 0. |

Los cuatro fallos generales siguen siendo tres de `G4DependencyRegistrationTest` y uno de `G4SalesSchemaTest`: expectativas antiguas de vistas/consultas; la advertencia de manejadores pertenece a una de esas pruebas. No se afirmó que toda la suite estuviera verde.

Se mantuvieron las siete exclusiones seguras anteriores de pruebas heredadas que usan directamente MySQL o requieren su entorno propio: `ConteoFisicoTest`, `G3IntegracionGruposApiTest`, `G3ViewsTest`, `HistorialPrecioProveedorTest`, `ProductoCostoRecargoTest`, `UserStoriesFrontendTest`, `VentasApiTest`.

Se ajustaron fixtures Mockery de seis pruebas de Caja para los métodos nuevos de bloqueo/protección del repositorio, conservando sus aserciones. Se retiraron cuatro casos unitarios antiguos de revisión basados en mocks; uno exigía expresamente continuar la aprobación aunque fallara la integración. Esa expectativa contradice la corrección solicitada. El recorrido se prueba ahora con persistencia real en las diez pruebas financieras nuevas, incluidos fallos y reversión total. No se deshabilitó la autorización para hacer pasar los casos.

### MariaDB real y APIs reales

`circuito-g2-real.php` ejecutó **39 solicitudes** por el kernel real de Laravel, usando usuarios/sesión/CSRF y Bearer cuando corresponde, sin mocks de APIs ni de base:

1. Alta temporal de categoría, marca, cliente con zona y producto mediante sus APIs; dos ventas de $100 confirmadas por G4.
2. Armado de entrega y retiro de un pedido. Se verificó que el retirado volviera a despacho por su zona.
3. Listado de entregas y pendientes con `ONLY_FULL_GROUP_BY` activado.
4. Confirmación del pedido restante y precarga de rendición real.
5. Intento de revisión sin caja: `422` sin liquidación parcial.
6. Apertura de caja, aprobación parcial, repetición de esa aprobación y aplicación del segundo cobro. Deuda restante **$80**.
7. Importación XLS real y conciliación de dos movimientos de $60/$40 contra un cobro de $100. Repetición sin duplicación y reversión del grupo.
8. Recepción/aplicación de cheque de $40, depósito, conciliación y rechazo. Se revirtió su grupo y su pago: deuda nuevamente **$80**.
9. Anulación del cobro de transferencia de $20 y repetición: deuda **$100**, sin segunda compensación.
10. Cierre bancario con pendientes rechazado con `409`; consultas bancarias, cobranzas, cobros por cliente y rendiciones correctas.

Todas las escrituras de ese circuito se revirtieron en la copia y se retiró su extracto privado. La comparación de los datos previos quedó sin diferencias.

### Concurrencia con procesos independientes

En la base de instalación limpia, aislada del proyecto de trabajo:

- Dos procesos intentaron conciliar bancos distintos contra el mismo origen de $100. Uno completó; el otro recibió `409`. Quedó un grupo y un detalle, no dos usos del dinero.
- Un cierre tomó el bloqueo antes que un pago: el cierre completó y el pago tardío recibió `422` por falta de caja abierta, sin aplicación parcial.
- Un pago tomó el bloqueo antes que el cierre: se aplicaron $25,25 y el cierre posterior incluyó exactamente **$25,25** como monto esperado. La deuda quedó en **$75,25**.

Estas pruebas demuestran los escenarios ejecutados. No equivalen a una prueba de carga exhaustiva de todos los escritores del ERP. Un error de base o interbloqueo no debe interpretarse como permiso para repetir operaciones sin sus controles; las transacciones conservan la atomicidad y los recorridos nuevos detectan repeticiones.

### Pantallas y comprobación visual

Tras actualizar el MySQL original, se verificaron **18 consultas con `200`**: las pantallas de conciliación, cobros, rendiciones, entregas y caja; sus lecturas operativas; movimientos bancarios/cobranzas/rendiciones API; salud, clientes lista/ID y catálogo.

Se renderizó la interfaz bancaria en Chrome con una respuesta de prueba controlada para comprobar su comportamiento y diseño: selección visible, centavos equilibrados, historial y POST de grupo. Esos datos de comprobación visual no se guardaron en la base. La captura mantuvo el layout/menú de G1; se ajustaron controles y pestaña activa. La prueba de APIs reales es el circuito anterior, no esa respuesta visual controlada.

## 13. Límites y casos que requieren revisión

- Una rendición antigua aprobada sin certificación individual no se vuelve a liquidar. También se bloquea un cobro de cabecera pendiente si ya tiene un asiento histórico identificable en Cuenta Corriente o Caja. Se devuelve conflicto y debe verificarse su documentación y sus asientos antes de reconstruir vínculos.
- No se anula automáticamente un cobro histórico que no pueda vincularse a un pago y a caja. Generar una deuda por el importe de un registro sin esa prueba podría duplicarla.
- Las conciliaciones históricas sin grupo nuevo se respetan como reservas existentes. Para revertirlas financieramente o rechazar un cheque vinculado a ellas hace falta revisión y reconstrucción trazable; no se aplica el antiguo borrado aislado.
- Un período cerrado no se reabre automáticamente por un rechazo o disputa posterior. Se bloquea la operación que lo alteraría. Un ajuste posterior requiere un circuito contable acordado, no reescribir el corte cerrado.
- No se agregó una conexión directa con el banco ni una certificación fiscal. El extracto y los comprobantes son fuentes aportadas, y la conciliación es un cruce registrado por el usuario/sistema.
- No se suman saldos de apertura de caja a una comisión bancaria ni se confunde el ingreso de un cheque con fondos bancarios definitivamente liquidados.
- Planificación/edición/confirmación atómicas de entregas requieren `internal`. Un despliegue con bases separadas necesita reserva distribuida, reintentos y compensaciones explícitos.
- Los formatos de importación tienen un contrato concreto. Si el banco entrega otra disposición de columnas/registros o una estructura no reconocida, se rechaza para adaptar el formato; no se adivinan importes.
- La interfaz bancaria limita la carga masiva de movimientos a 2.000 y pide acotar por período cuando se supera. No se certificó rendimiento con grandes volúmenes ni todos los navegadores/tamaños de pantalla.
- Persisten los cuatro fallos heredados de pruebas de G4 y la limitación histórica de transacciones incompletas previa a la recuperación de MySQL del 08/11.
- Este trabajo no cierra por sí solo todos los apartados de compras, promociones, stock, devoluciones u otras historias no incluidas entre estos trece incisos. La nueva revisión integral debe evaluar su estado actual por separado.

## 14. Cómo usarlo para comprobar los cambios

1. Iniciar MySQL en XAMPP y ejecutar `C:\xampp\php\php.exe -d extension=gd -d extension=zip artisan serve` desde el proyecto. No hace falta Apache con los consumidores locales `internal`.
2. Como administrativo, abrir su caja y crear/consultar una rendición pendiente. Seleccionar cobros individuales en el detalle o entrar a **Cobros pendientes y anulaciones**. Comprobar deuda, caja y estados después de aplicar.
3. Para cheques, completar número, banco y fecha antes de aplicar. Consultar Cheques en Conciliación, depositar y, si corresponde, rechazar con motivo. Esto mueve dinero y deuda reales; usar datos de prueba conscientes.
4. Abrir Conciliación, crear/elegir período e importar un extracto válido. Revisar propuestas o seleccionar movimientos/orígenes, comparar sus sumas y confirmar. Verificar Historial y la reversión mientras el período siga abierto.
5. Probar gasto o disputa con nota. Intentar cerrar un período pendiente debe fallar; cerrar uno resuelto impide nuevas alteraciones.
6. Abrir una hoja de ruta en preparación y usar **Modificar pedidos**. Quitar un pedido y comprobar que vuelva a despacho; agregar otro de la misma zona. Confirmar una entrega y comprobar que la modificación posterior quede bloqueada.

## 15. Cómo revertir esta etapa

La reversión debe preservar las etapas anteriores y cualquier operación posterior que ya se haya registrado. No usar un reset global ni restaurar toda una rama antigua.

### Código

1. Detener la aplicación y guardar un respaldo nuevo del código, `.env`, bases y archivos privados.
2. Consultar `manifest-reversion.json` y `plan-reversion.txt` de la carpeta externa de esta etapa.
3. Restaurar sólo los archivos `restore` desde su ruta equivalente en `codigo-antes/`.
4. Retirar sólo los `new_file_review` después de comparar la huella registrada. Si cambiaron después de esta entrega, revisar/conservar esa modificación antes de retirarlos.
5. `.env` tiene copia separada en `env-antes.privado`. Restaurarla sólo si también se quiere volver a ese corte de configuración. Ejecutar `artisan config:clear`.

El manifiesto compara contra el inicio de G2, no contra HEAD. Así conserva las correcciones anteriores de G1 y bloqueos generales que ya existían sin commit.

### Base y archivos

1. Respaldar las dos bases actuales antes de retroceder.
2. Si no hubo operaciones posteriores necesarias, restaurar `proyecto-antes.sql` del respaldo de esta etapa, verificando primero servidor, puerto y nombres de base. El archivo contiene ambas bases del proyecto.
3. Si hubo cobros, conciliaciones, rechazos o entregas nuevos, no importar ciegamente ese corte: descartaría esas operaciones. Preparar recuperación selectiva preservando pagos, movimientos compensatorios, remitos, vínculos y auditoría.
4. Conservar copia privada de `storage/app/private/extractos_bancarios`. Después de restaurar un corte anterior, archivar o retirar sólo los archivos nuevos que se hayan comprobado sin referencias. No borrar toda la carpeta privada ni los comprobantes de transferencias de G1.
5. Las reservas y enlaces se liberan mediante sus recorridos, no borrando pagos/ledger a mano. `migrate:rollback` no elimina esta auditoría: el `down()` es conservador. No usar `migrate:fresh` sobre la base de trabajo.
6. Verificar clientes, catálogo, saldo, listados y conteos del corte restaurado antes de volver a operar.

### Evidencias y cierre

En la carpeta externa quedan los respaldos anterior/posterior y logs de instalación, preservación, pruebas, circuito real, concurrencia y captura visual. Contienen información privada y no deben incorporarse al commit.

Se detienen las instancias de MySQL utilizadas para el trabajo y no se deja un servidor web de prueba levantado. El usuario inicia MySQL y Artisan cuando quiera continuar. No se enviaron cambios al remoto.
