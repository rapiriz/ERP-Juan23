# Informe de validación, preparación y repaso del repositorio

> Registro histórico: la tarea Windows descrita más abajo fue retirada posteriormente por pedido del usuario. Permanece ausente; no ejecutar su instalación para seguir este informe. Ver [guía vigente](validacion-y-despliegue.md#programador-retirado-por-decisión-del-usuario) y [cierre del 10/11](informe-cierre-pendientes-y-revision-2026-11-10.md).

Fecha: 09/11/2026. Alcance: los nueve puntos de validación y preparación solicitados y una nueva revisión cruzada del estado local con GitHub. El usuario confirmó que sólo se utiliza entorno local.

## 1. Resultado y límites de esta entrega

Se resolvieron los cuatro fallos antiguos y el caso riesgoso; las pruebas que estaban excluidas se recuperaron y ejecutaron. Se quitaron conexiones fijas a la base habitual, se configuró el programador local, se agregaron monitores de stock/finanzas, controles de despliegue, respaldo/restauración y CI. Se revisaron pantallas reales y se corrigieron errores detectados de JavaScript, contraste, controles sin nombre y navegación móvil.

La suite completa terminó con **683 pruebas y 4.177 aserciones**, sin errores, fallos, riesgos ni omisiones, usando SQLite y MySQL de prueba. También pasaron 48 pruebas JavaScript. Se completaron 29 recorridos de navegador con cero errores de ejecución, cero infracciones detectadas por axe en las auditorías realizadas y sin desbordes en las cuatro vistas móviles comprobadas. Las comprobaciones automáticas no certifican todos los navegadores, todos los casos manuales ni cumplimiento integral de WCAG.

Se conservó el trabajo previo. No hubo commit, push, merge de ramas ni modificación remota de issues/comentarios. `fetch --prune` sólo actualizó referencias de Git. Tampoco se crearon/eliminaron migraciones ni tablas de negocio en la base habitual.

## 2. Los nueve puntos solicitados

| Prioridad | Área | Qué estaba mal/faltaba | Qué se hizo | Estado y evidencia |
|---|---|---|---|---|
| Alta | Pruebas | Cuatro expectativas antiguas, mocks de consultas desactualizados y un test riesgoso. Clases excluidas nunca comprobadas en conjunto. | Fixtures SQLite reales, nombres actuales de vistas, autenticación de prueba y circuito de recepciones real; inclusión de toda la carpeta Unit. | Suite completa sin exclusiones especiales: 683/4.177, sin omisiones al habilitar QA. |
| Alta | Aislamiento | `UsesG3Database` conectaba directamente a `distribuidorapyb:3306`. La configuración cacheada podía apuntar al entorno habitual. | Base explícita `erp_qa_*`/`erp_test_*` en 3315, guarda previa al SQL y bootstrap que no carga caché de producción. | Se probó el rechazo de una conexión habitual antes de ejecutar SQL. |
| Alta | Tareas programadas | Declarar Schedule no iniciaba un programador del sistema operativo. | Tarea Windows cada minuto, ejecución oculta y limitada, pulso privado y templates systemd/cron. | Tarea registrada y pulsos automáticos observados. Cierre/reenvío ejecutados en pruebas aisladas. |
| Media | Operaciones históricas | No había listado para detectar vínculos faltantes sin modificar datos. | Servicio de sólo lectura, CLI y pantalla administrativa con casos para revisar. | En la base habitual: 40 asientos sin pago canónico y 38 cobros sin aplicación. La regularización requiere evidencia; no se inventaron vínculos. |
| Media | Stock pendiente | Sólo existía el comando técnico, sin consulta operativa ni reintento individual. | `/operaciones/stock`, filtros, intentos, diagnóstico escapado, pulso y POST protegido para reintentar. | Se verificó que repetir una operación aplicada no duplica stock ni incrementa intentos. |
| Media | Frontend | Un listener de Ventas apuntaba a un botón inexistente; había colores de bajo contraste, filtros sin nombre y una vista móvil desbordada. | Botón real de caja, CSS de accesibilidad, etiquetas, foco/diálogos, menú móvil y mensajes de validación accesibles. | Navegador real, cuatro modales representativos, móvil, permisos de visitante/contador. |
| Media | Instalación | Faltaba repetir la instalación completa y controlar nombres SQL sensibles a mayúsculas. | Copia física con vendor y `.env` propios, instalación vacía, actualización repetida, identificadores canónicos y `SchemaCaseGuard`. | Local validado. CI Ubuntu/MariaDB preparado; su ejecución Linux remota sigue pendiente de publicación. |
| Media | Producción | Faltaban controles verificables y ejemplos de despliegue/respaldo. | `erp:doctor`, protección de errores JSON, Nginx/systemd de ejemplo y herramientas de respaldo/restauración QA. | Preparación local completada. SMTP/HTTPS reales no aplican todavía: no hay servidor de producción. |
| Media | Documentación/automatización | Informes históricos dispersos, ejemplos incompletos y ausencia de CI. | Guía vigente, índice, inventario de 305 rutas API, colección Postman actualizada, workflow y herramientas de inventario. | Archivos listos; workflow no publicado ni ejecutado en GitHub. |

## 3. Corrección de pruebas: qué se cambió y por qué

### Fallos antiguos

`G4DependencyRegistrationTest` usaba nombres de vistas anteriores (`ventas`, `historial-ventas`, `saldo`) y mocks encadenados de Query Builder que ya no representaban las consultas actuales. Una de sus excepciones dejaba handlers registrados y PHPUnit lo marcaba riesgoso.

Se reemplazaron los mocks de BD por registros reales en SQLite de prueba y se mantuvieron las comprobaciones de dependencias mock/HTTP. Las vistas actuales son `ventas.ventas`, `historial-ventas.historial-ventas` y `saldos.saldo`. Se comprueba que los GET no agreguen operaciones.

`G4SalesSchemaTest` mantiene sus pruebas de instalación y reversión conservadora, pero su prueba de pantallas instala el conjunto de dependencias que esas pantallas realmente necesitan. No se modificaron controladores para satisfacer un fixture obsoleto.

### Pruebas que estaban excluidas

Las seis clases de G3 que usaban el helper antiguo ya se ejecutan contra QA. Sus fixtures se crean dentro de transacciones; no dependen de las categorías, usuarios, productos o proveedores del desarrollador. Se agregó autenticación real de prueba donde se pretendía probar escrituras autorizadas. No se deshabilitaron middleware ni permisos para conseguir respuestas 200.

En `UserStoriesFrontendTest`, el estado recibido de una orden se comprueba después de registrar compra y recepción; ya no se declara una orden completada sin recibir mercadería. También se comprueba el rechazo de esa declaración prematura.

`VentasApiTest` ya no crea por segunda vez tablas que `RefreshDatabase` instaló. Crea clientes correspondientes al catálogo de prueba, inicia sesión, solicita paginación explícita y comprueba el usuario autenticado, en lugar del ID fijo de una configuración antigua.

La suite Unit ahora incluye todas sus carpetas, no sólo Caja. La prueba de calculadora se movió de `tests/Unit/CalculadoraVentaTest.php` a `tests/Unit/Ventas/CalculadoraVentaTest.php` para coincidir con su namespace PSR-4. No se eliminó su cobertura.

## 4. Aislamiento que evita dañar la base habitual

Archivos centrales: `tests/bootstrap.php`, `tests/TestCase.php`, `tests/TestDatabaseSafety.php`, `tests/UsesG3Database.php`, `phpunit.xml` y `scripts/prepare-test-database.php`.

- Por defecto se utiliza SQLite `:memory:` con correo/sesión/caché de prueba.
- Para MySQL se exige un nombre QA explícito y puerto 3315. El helper ya no usa `distribuidorapyb` ni 3306.
- La guarda se registra sobre conexiones Laravel y comprueba el destino antes del SQL.
- El bootstrap impide reutilizar configuración cacheada de la aplicación habitual.
- El preparador exige una base nueva y nunca hace `migrate:fresh` sobre una existente.
- Las credenciales de QA pueden definirse por entorno; no se agregaron contraseñas reales al repositorio.

La suite predeterminada sin servidor QA informa sus omisiones de MySQL. La entrega se verificó también en modo completo con QA y `--fail-on-risky --fail-on-skipped`, sin omisiones. Eso reemplaza el uso de una configuración externa que excluía clases enteras.

## 5. Programador automático

Se instaló la tarea Windows `ERP-Juan23-26A606BC25` para esta ruta. La cuenta es la del usuario actual, con privilegios limitados, sin ventanas visibles. Corre cada minuto mientras esa cuenta tenga sesión iniciada y no sobrescribe tareas ya existentes.

El wrapper ejecuta `artisan schedule:run` desde la raíz del proyecto y conserva la salida en `storage/logs/scheduler.log`. Se observaron ejecuciones automáticas y actualización de `storage/app/private/operations/scheduler.json`, con resultado de tarea 0 en las verificaciones realizadas.

Laravel conserva el cierre de cajas vencidas de repartidor cada 15 minutos y el reenvío de stock cada minuto, ambos sin solapamiento. Se agregó un pulso cada minuto. El pulso no implica que cada operación de negocio haya sido exitosa: deben revisarse sus resultados y logs. La tarea no inicia MySQL.

Las pruebas ejecutan los comandos contra registros aislados: cierre automático de una caja vencida y aplicación de una operación de stock pendiente. No se usaron cajas reales para provocar vencimientos artificiales.

Para producción se prepararon unidad/timer de systemd y alternativa cron; no se instalaron en Linux. Esta tarea local interactiva no reemplaza una cuenta de servicio que deba funcionar sin sesión de usuario.

## 6. Pantallas de operaciones

### Stock

`OperationsController` y `operaciones.stock` permiten al administrativo consultar 25 operaciones por página, filtrar estado, ver venta, motivo, clave original, cambios, intentos, último error y última actualización. El diagnóstico se escapa, evitando ejecutar HTML de errores.

El POST `/operaciones/stock/{id}/reintentar` exige sesión actual, rol administrativo y CSRF. Reutiliza `StockDeliveryService`, no modifica el payload ni crea un UUID nuevo. Se registra actor, operación y resultado en el log. Si continúa fallando, permanece pendiente; si ya estaba aplicada, no vuelve a generar el ajuste.

### Revisión financiera

`FinancialIntegrityReview`, el comando `erp:audit-finanzas` y `/operaciones/historicas` leen sin escribir. Revisan:

1. Asientos positivos de pago/cobro sin registro de pago canónico.
2. Cobros registrados sin aplicación financiera.
3. Pagos cuyo asiento, caja, cliente, importe o vínculo de aplicación no coinciden o faltan.

Se muestran hasta 100 casos por grupo y su total. No se suman las categorías como si fueran operaciones únicas: pueden solaparse. El comando devuelve 1 cuando hay revisión pendiente; no significa que haya reparado los registros.

## 7. Frontend: correcciones concretas

### Error real de Ventas

La página registraba `addEventListener` sobre `btnCerrarCaja`, pero ese elemento no existía. El error interrumpía el resto del bloque JavaScript, incluso aunque la pantalla pareciera cargada. Se agregó el botón real **Caja y cierre**, conectado a la ruta de Caja existente. La prueba de navegador comprueba que ya no aparezca esa excepción.

### Contraste y controles

`accessibility.css` unifica colores secundarios legibles sobre fondos claros sin alterar importes, estados ni reglas de negocio. Se corrigieron contraste de indicadores, texto auxiliar del catálogo, saldos y botones de G2. Se agregaron nombres accesibles a filtros y campos de producto/compra, y a botones de cierre que sólo mostraban iconos. Los botones con texto Cancelar conservan ese nombre visible, sin una etiqueta contradictoria.

### Móvil y teclado

El menú empieza cerrado en móvil cuando no hay preferencia guardada. Puede abrirse/cerrarse con el control hamburguesa y mantiene la elección del usuario. En pantallas pequeñas se superpone y no reduce permanentemente el área de trabajo a una columna estrecha. Se agregaron salto al contenido, foco visible, nombres de diálogo y manejo del foco en modales.

La validación de login y formularios comunes relaciona error/campo mediante `aria-describedby`, marca `aria-invalid`, anuncia el mensaje y enfoca el primer campo inválido. El login conserva mensajes genéricos y su política de intentos; no se debilitó autenticación.

Se comprobaron 21 pantallas incluyendo login, cuatro modales y cuatro vistas móviles: 29 recorridos, además de comprobaciones de APIs a visitante y navegación del contador. Las pantallas revisadas abarcan G1, G2, G3, G4 y catálogo público. Se conservaron capturas y resultados fuera del repo.

## 8. Instalación, nombres SQL y Linux

Las migraciones base de G3 crean tablas como `producto`, `proveedor` y `unidad_medida`, pero muchos modelos/consultas usaban nombres mayúsculos. Windows ocultaba ese desajuste. Se normalizaron los identificadores SQL para coincidir con las tablas canónicas; se conservaron mayúsculas donde la migración oficial de G2 las usa. También se revisaron listas de tablas e inspecciones de índices.

No se renombraron tablas existentes ni campos JSON. Se mantuvieron los fallbacks explícitos de lectura de esquemas antiguos cuando correspondían. `SchemaCaseGuard` comprueba conflictos de nombres antes de migrar, tanto desde `erp-install.php` como por el evento de migraciones de Laravel. Si hay un conflicto en un servidor sensible a mayúsculas, se detiene antes de inventar una tabla paralela. Una conversión de nombres en un servidor existente requiere respaldo y revisión explícita.

Se ejecutaron Composer y el instalador en una copia física independiente, con dependencias, `.env` y base QA propios. La primera instalación creó el esquema; al repetirla no quedaron migraciones pendientes y se reutilizó la cuenta técnica. Los 57 nombres de archivos de migraciones se conservaron: **ninguna migración nueva ni retirada en esta etapa**.

Esta PC no tiene Linux/WSL/Docker operativo. Se preparó CI de Ubuntu/MariaDB, pero todavía no se ejecutó ese pipeline remoto. Por lo tanto no se certifica aquí una instalación Linux real; su verificación queda identificada antes de desplegar allí.

## 9. Preparación para producción, sin cambiar el entorno local

El usuario confirmó que no hay servidor de producción. Se dejaron guía SMTP, cookies seguras, configuración Nginx y tareas systemd/cron. No se cambiaron sus credenciales ni `.env` a producción y no se envió correo a terceros.

`erp:doctor --production --database --json` comprueba entorno, depuración, URL HTTPS, cookies, configuración SMTP, pulso y compatibilidad del esquema. No imprime contraseñas ni envía mensajes. En el entorno actual detecta correctamente HTTP/local/debug/SMTP sin configurar: son valores de desarrollo, no una prueba de fallo de producción.

Se agregó `RedactServerErrors`: con depuración desactivada, errores JSON 5xx de controladores no exponen mensajes SQL, stack o detalles internos; conservan el código HTTP y producen un identificador de seguimiento. Los errores de validación/autorización no se convierten en 500 ni pierden su explicación. Se probó expresamente la protección de un diagnóstico SQL devuelto por un controlador.

Cuando exista servidor, comprobar entrega SMTP real a un correo propio, TLS/certificado/proxy, cookies en navegador, restauración de SQL y archivos privados, permisos y retención de respaldos. Configuración validada no equivale a entrega de correo o HTTPS certificados.

## 10. Respaldo y restauración verificados

`erp:backup` genera SQL y huella SHA-256 fuera de public, con contraseña transmitida por entorno al proceso, no por argumentos. La guía indica respaldar además APP_KEY/.env y comprobantes privados, cifrar y guardar una copia externa.

`restore-test-backup.php` sólo restaura en una base nueva de prueba en 3315 y rechaza dumps con instrucciones de cambio de base. No sobrescribe destinos existentes.

El ensayo de esta entrega restauró **80 tablas y 69 registros** de la base QA en otra base QA. Se compararon filas y huellas de todas las tablas: coincidieron. No se restauró un dump encima de la base habitual.

La base habitual fue auditada en lectura, suspendiendo temporalmente la tarea programada para evitar operaciones automáticas durante esa comprobación; después se reactivó. Conserva **97 tablas y 1.343 registros de negocio**. La comparación con las huellas anteriores no detectó alteraciones en los datos preexistentes.

## 11. Inventario histórico: no borrar sin revisar

La instalación fresca tiene 80 tablas; la base habitual conserva 17 estructuras adicionales de versiones anteriores. No todas están vacías, ni todas son reemplazables por una tabla de nombre parecido.

| Tabla histórica | Registros observados | Tratamiento de esta entrega |
|---|---:|---|
| `categorias` | 0 | Preservada; inventariada, sin modificación. |
| `cliente_credito_config` | 15 | Preservada; inventariada, sin modificación. |
| `cobro_ventas` | 0 | Preservada; inventariada, sin modificación. |
| `cobros` | 0 | Preservada; inventariada, sin modificación. |
| `credito_venta` | 15 | Preservada; inventariada, sin modificación. |
| `detalle_ventas` | 0 | Preservada; inventariada, sin modificación. |
| `devolucion_venta` | 15 | Preservada; inventariada, sin modificación. |
| `document_sequences` | 1 | Preservada; inventariada, sin modificación. |
| `importacion_bancaria` | 1 | Preservada; inventariada, sin modificación. |
| `integration_operations` | 15 | Preservada; inventariada, sin modificación. |
| `lote` | 15 | Preservada; inventariada, sin modificación. |
| `marcas` | 0 | Preservada; inventariada, sin modificación. |
| `nota_credito` | 15 | Preservada; inventariada, sin modificación. |
| `productos` | 0 | Preservada; inventariada, sin modificación. |
| `rendicion_devolucion` | 0 | Preservada; inventariada, sin modificación. |
| `rendicion_diferencia` | 15 | Preservada; inventariada, sin modificación. |
| `ventas` | 0 | Preservada; inventariada, sin modificación. |

`cliente_credito_config`, `devolucion_venta`, `nota_credito` y `credito_venta` representan un circuito anterior al nuevo `clientes.limite_credito` y tablas `g4_*`. Cada una conserva datos que deben revisarse antes de sincronizar o retirar estructuras. Moverlos directamente puede duplicar crédito o stock; ignorarlos sin revisar puede dejar fuera límites y devoluciones previas. La mera coincidencia de IDs/importes no basta para certificar equivalencia.

`lote` conserva 15 registros aunque el circuito activo de G3 ya no permite gestionarlos. Preservarlos no significa que exista una gestión de lotes completa. `integration_operations`, secuencias e importaciones pueden contener evidencia de idempotencia o documentos: no se retiraron.

## 12. Revisión cruzada con GitHub

Se consultó GitHub y se actualizaron referencias sin alterar el árbol local:

- **23 ramas remotas activas**; se compararon commits y listas de archivos con develop.
- **191 issues y 12 PR**, incluyendo abiertos y cerrados.
- **65 comentarios de conversación en 29 hilos** y los comentarios inline de los 12 PR; no se encontraron inline en esos PR.
- Se consultaron comentarios de commits del repositorio; la respuesta fue vacía.
- Se contrastaron requisitos, rutas registradas, código, migraciones, pruebas y evidencias locales.

El remoto develop examinado permanece en [`ae9fba1`](https://github.com/rapiriz/ERP-Juan23/commit/ae9fba1d294dc153249d1d2658d75c426a39a1cd); el HEAD local permanece en `060cb9b`. Parte del código remoto ya fue adaptada manualmente en etapas anteriores. Un número de commits exclusivos por rama no demuestra que falten esas funcionalidades: varias ramas tienen historias de merges distintas y propuestas antiguas.

En la rama de Axel hay una propuesta de SQL/DBML; no se importó por encima de la base. No se trajeron ramas completas que reintroducirían esquemas o contratos antiguos. Los estados abierto/cerrado de las issues no se tomaron como prueba de funcionamiento.

No se modificaron issues, comentarios, asignaciones, PR ni proyectos privados. La revisión abarca los recursos accesibles del repositorio y los archivos locales; no certifica documentos privados fuera de ese acceso.

## 13. Qué sigue pendiente después de este repaso

| Prioridad | Área | Pendiente concreto | Evidencia y decisión necesaria |
|---|---|---|---|
| Alta | Datos financieros históricos | Revisar y regularizar vínculos antes de aplicar/revertir automáticamente operaciones antiguas. | 40 asientos sin pago canónico y 38 cobros sin aplicación; categorías no equivalen a 78 operaciones únicas. Pedir comprobantes y decidir por registro. |
| Alta | Esquemas comerciales anteriores | Conciliar los límites, devoluciones y notas históricas con el circuito actual, sin duplicar efectos. | Tablas antiguas con 15 registros por varias categorías y nombres distintos de `g4_*`. No se migraron por conjeturas. |
| Alta antes de publicar | Git | Integrar el historial local/remoto, revisar el diff completo y ejecutar CI sobre lo que se vaya a publicar. | HEAD local y origin/develop distintos; no hubo commit/push/merge en esta entrega. |
| Media antes de Linux | Compatibilidad y CI | Ejecutar el workflow de Ubuntu/MariaDB y atender diferencias que aparezcan en ese entorno. | Preparado y comprobaciones locales correctas; no hay Linux disponible en esta PC ni corrida remota del YAML aún. |
| Media / alcance | Lotes y vencimientos de mercadería | Acordar si se reintegra ese circuito o se documenta formalmente su exclusión. | [HU #73](https://github.com/rapiriz/ERP-Juan23/issues/73), [#74](https://github.com/rapiriz/ERP-Juan23/issues/74), [#75](https://github.com/rapiriz/ERP-Juan23/issues/75) describen lotes, pero `/vencimientos` redirige a Inventario y no hay recorrido activo de lotes. |
| Media / documentación | DER y estructuras históricas | Actualizar diagramas al esquema vigente y decidir por tabla qué conservar, transformar o retirar. | Base habitual 97 tablas frente a 80 de instalación fresca; inventario CLI disponible. No ejecutar SQL de propuestas sin revisión. |
| Futuro, según alcance | Facturación fiscal | Definir requisitos y proveedor si se exige emisión fiscal real. | [Spike #188](https://github.com/rapiriz/ERP-Juan23/issues/188); las ventas/notas actuales son documentos internos y no certifican emisión ARCA/AFIP. |
| Futuro, no bloqueo técnico actual | Estadísticas de cumplimiento | Completar porcentaje en término/demoras/comparativa por período si se incluye esa HU. | [#80](https://github.com/rapiriz/ERP-Juan23/issues/80) se define como prioridad Futuro; el historial de plazos no cubre todos sus indicadores. |
| Por confirmar | Catálogo | Confirmar si sigue requerida jerarquía de subcategorías del sitio de referencia. | El contrato actual de G3 ofrece categorías planas, sin relación padre/hija. No se inventó una jerarquía comercial. |
| Limpieza posterior | Código/archivos antiguos | Retirar únicamente duplicados o código sin consumidores una vez demostrado su desuso. | Hay bundles antiguos y procesadores históricos; por ejemplo `ProcesadorDeHistorialRendicion` conserva mocks pero el listado activo usa el repositorio. No se clasificó como fallo operativo sólo por existir ese archivo. |
| Cuando exista producción | Operación | Verificar SMTP y HTTPS reales, cuenta de servicio, retención, monitoreo y restauración integral. | Usuario confirmó entorno local; no es una configuración faltante para desarrollar en localhost. |

La tabla contiene pendientes comprobados y decisiones de alcance; no vuelve a listar como rotos los flujos que pasaron las regresiones. Tampoco garantiza que no pueda aparecer otro problema con datos o integraciones reales distintos.

## 14. Documentación y CI incorporados

- [Guía vigente](validacion-y-despliegue.md): comandos reproducibles, tarea local, monitor, respaldo y producción futura.
- [Inventario de 305 rutas API](api-rutas-registradas.md): métodos, acciones y middleware registrados, sin afirmar que todas tengan idéntico contrato.
- [Contrato G1 actualizado](contrato-api-g1.md) y colección Postman existente con crédito, devoluciones y comentarios.
- [Índice de informes](indice-informes.md): conserva la evidencia anterior y distingue instrucciones vigentes.
- `.github/workflows/validation.yml`: Ubuntu/MariaDB/PHP/Node, instalación desde lock, auditorías, todas las pruebas sin permitir omisiones, PSR-4 y navegador. No publica dumps ni credenciales.

Composer validó el manifiesto; su auditoría no devolvió avisos ni paquetes abandonados. npm audit tampoco detectó vulnerabilidades en las dependencias del recorrido de navegador, en la fecha de la comprobación.

## 15. Resultados guardados y archivos de esta etapa

El directorio externo `Entregables/Validacion_Preparacion_2026-11-09/` conserva respaldo de código anterior, entorno privado, resultados, capturas, inventario, referencias GitHub, comparación de ramas, copia de instalación y ensayo de restauración. Los archivos privados y bases QA no forman parte del commit.

Pruebas y evidencias principales: `suite-final.txt`, `frontend-final.txt`, `browser/results.json`, `restauracion-verificada.json`, `auditoria-local-solo-lectura.privado.json`, `instalacion-completa.txt`, `actualizacion-repetida.txt`, `composer-audit.json`, `npm-audit.json`, `github-issues.json`, `github-comments.json`, `ramas-comparadas.json`.

El inventario siguiente se compara contra el respaldo al comenzar esta etapa, no contra HEAD, porque ya había trabajo local anterior. La normalización SQL afecta modelos, repositorios, controladores y migraciones; las pruebas verifican que los contratos sigan funcionando. Se aplicó Pint a los PHP intervenidos y se restauraron archivos que sólo habían recibido cambios equivalentes de formato para reducir ruido.

| Estado | Archivo |
|---|---|
| Nuevo | `.github/workflows/validation.yml` |
| Modificado | `.gitignore` |
| Modificado | `README.md` |
| Nuevo | `app/Console/Commands/AuditFinancialHistory.php` |
| Nuevo | `app/Console/Commands/BackupDatabase.php` |
| Nuevo | `app/Console/Commands/DeploymentDoctor.php` |
| Nuevo | `app/Console/Commands/SchedulerHeartbeat.php` |
| Nuevo | `app/Console/Commands/SchemaInventory.php` |
| Modificado | `app/Contracts/SalesGateway.php` |
| Modificado | `app/Http/Controllers/Controller.php` |
| Modificado | `app/Http/Middleware/IdentidadUsuario.php` |
| Modificado | `app/Http/Middleware/PresentG2Rendiciones.php` |
| Nuevo | `app/Http/Middleware/RedactServerErrors.php` |
| Modificado | `app/Modules/Catalogo/Services/InternalCatalogApi.php` |
| Modificado | `app/Modules/Compras/Models/Compra.php` |
| Modificado | `app/Modules/Compras/Models/DetalleCompra.php` |
| Modificado | `app/Modules/Compras/Models/DetalleRecepcion.php` |
| Modificado | `app/Modules/Compras/Models/PagoProveedor.php` |
| Modificado | `app/Modules/Compras/Models/Recepcion.php` |
| Modificado | `app/Modules/Entregas/Repositories/EntregaRepository.php` |
| Modificado | `app/Modules/Entregas/Repositories/RemitoRepository.php` |
| Modificado | `app/Modules/PedidosDeCompra/Controllers/DashboardReposicionController.php` |
| Modificado | `app/Modules/PedidosDeCompra/Controllers/SugerenciasReposicionController.php` |
| Modificado | `app/Modules/PedidosDeCompra/Models/DetalleOrden.php` |
| Modificado | `app/Modules/PedidosDeCompra/Models/OrdenCompra.php` |
| Modificado | `app/Modules/PedidosDeCompra/Models/SugerenciaCompra.php` |
| Modificado | `app/Modules/PedidosDeCompra/Services/PurchaseOrderProgress.php` |
| Modificado | `app/Modules/Productos/Controllers/CategoriaController.php` |
| Modificado | `app/Modules/Productos/Controllers/MarcaController.php` |
| Modificado | `app/Modules/Productos/Models/Categoria.php` |
| Modificado | `app/Modules/Productos/Models/HistorialPrecio.php` |
| Modificado | `app/Modules/Productos/Models/Marca.php` |
| Modificado | `app/Modules/Productos/Models/Producto.php` |
| Modificado | `app/Modules/Productos/database/migrations/2026_09_30_000000_create_g3_base_tables.php` |
| Modificado | `app/Modules/Productos/database/migrations/2026_10_01_000001_create_sugerencia_compra_table.php` |
| Modificado | `app/Modules/Productos/database/migrations/2026_10_02_000001_add_motivo_rechazo_to_sugerencia_compra_table.php` |
| Modificado | `app/Modules/Productos/database/migrations/2026_10_03_000001_create_pago_proveedor_table.php` |
| Modificado | `app/Modules/Productos/database/migrations/2026_10_03_000002_create_historial_plazo_proveedor_table.php` |
| Modificado | `app/Modules/Productos/database/migrations/2026_10_03_000003_add_id_orden_compra_to_sugerencia_compra_table.php` |
| Modificado | `app/Modules/Productos/database/migrations/2026_10_10_000001_add_contacto_to_proveedor_table.php` |
| Modificado | `app/Modules/Productos/database/migrations/2026_10_11_000001_add_observaciones_to_historial_precio_proveedor_table.php` |
| Modificado | `app/Modules/Productos/database/migrations/2026_10_12_000001_align_stock_tables_with_inventory_flow.php` |
| Modificado | `app/Modules/Productos/database/migrations/2026_10_12_000002_add_base_flag_to_legacy_units.php` |
| Modificado | `app/Modules/Productos/database/migrations/2026_10_13_000001_align_purchase_table_with_purchase_flow.php` |
| Modificado | `app/Modules/Productos/database/migrations/2026_10_13_000002_add_unit_to_supplier_price_history.php` |
| Modificado | `app/Modules/Productos/database/migrations/2026_10_16_000001_add_presentacion_bulto_to_producto_table.php` |
| Modificado | `app/Modules/Productos/database/migrations/2026_10_24_000001_seed_g3_catalog_for_integration.php` |
| Modificado | `app/Modules/Productos/database/migrations/2026_10_26_000001_migrate_g3_foreign_keys_to_usuarios_table.php` |
| Modificado | `app/Modules/Productos/database/migrations/2026_10_28_000001_remove_lotes_vencimientos_and_legacy_mock_tables.php` |
| Modificado | `app/Modules/Productos/database/migrations/2026_11_03_000001_add_costo_y_recargo_to_producto_table.php` |
| Modificado | `app/Modules/Productos/database/migrations/2026_11_03_000002_widen_origen_in_historial_precio_table.php` |
| Modificado | `app/Modules/Promociones/routes/api.php` |
| Modificado | `app/Modules/Proveedores/Controllers/PlazoEntregaController.php` |
| Modificado | `app/Modules/Proveedores/Controllers/ProveedorController.php` |
| Modificado | `app/Modules/Proveedores/Models/HistorialPlazoProveedor.php` |
| Modificado | `app/Modules/Proveedores/Models/HistorialPrecioProveedor.php` |
| Modificado | `app/Modules/Proveedores/Models/ProductoProveedor.php` |
| Modificado | `app/Modules/Proveedores/Models/Proveedor.php` |
| Modificado | `app/Modules/Rendiciones/Repositories/RendicionRepository.php` |
| Modificado | `app/Modules/Rendiciones/Services/ProcesadorDeCobroRendicion.php` |
| Modificado | `app/Modules/Rendiciones/Services/ProcesadorDeHistorialRendicion.php` |
| Modificado | `app/Modules/Rendiciones/Services/ProcesadorDeRendicionReparto.php` |
| Modificado | `app/Modules/Rendiciones/Services/ProcesadorDeValidacionRendicion.php` |
| Modificado | `app/Modules/Reportes/Services/ReportService.php` |
| Nuevo | `app/Modules/Shared/Controllers/OperationsController.php` |
| Modificado | `app/Modules/Shared/Http/Response.php` |
| Nuevo | `app/Modules/Shared/Services/FinancialIntegrityReview.php` |
| Modificado | `app/Modules/Stock/Controllers/ConteoFisicoController.php` |
| Modificado | `app/Modules/Stock/Controllers/UnidadController.php` |
| Modificado | `app/Modules/Stock/Models/MovimientoStock.php` |
| Modificado | `app/Modules/Stock/Models/SesionConteo.php` |
| Modificado | `app/Modules/Stock/Models/UnidadMedida.php` |
| Modificado | `app/Modules/Stock/Requests/RegistrarItemConteoRequest.php` |
| Modificado | `app/Modules/Stock/Services/StockService.php` |
| Modificado | `app/Modules/Ventas/Logging/VentaLogger.php` |
| Modificado | `app/Modules/Ventas/Middleware/AutenticarApiVentas.php` |
| Modificado | `app/Modules/Ventas/Requests/ListarVentasRequest.php` |
| Modificado | `app/Modules/Ventas/routes/web.php` |
| Modificado | `app/Providers/AppServiceProvider.php` |
| Nuevo | `app/Services/DeploymentReadiness.php` |
| Nuevo | `app/Services/SchemaCaseGuard.php` |
| Modificado | `app/Support/UsuarioActual.php` |
| Modificado | `bootstrap/app.php` |
| Nuevo | `config/schema_tables.php` |
| Modificado | `database/migrations/0001_01_01_000000_create_erp_tables.php` |
| Modificado | `database/migrations/2026_09_16_000001_add_password_recovery.php` |
| Modificado | `database/seeders/ProductoSeeder.php` |
| Modificado | `database/seeders/RendicionesEjemploSeeder.php` |
| Nuevo | `deploy/erp-scheduler.service` |
| Nuevo | `deploy/erp-scheduler.timer` |
| Nuevo | `deploy/nginx-erp.conf.example` |
| Nuevo | `docs/api-rutas-registradas.md` |
| Modificado | `docs/contrato-api-g1.md` |
| Nuevo | `docs/indice-informes.md` |
| Nuevo | `docs/informe-validacion-preparacion-y-repaso-2026-11-09.md` |
| Modificado | `docs/postman/ERP-Juan23-APIs.postman_collection.json` |
| Nuevo | `docs/validacion-y-despliegue.md` |
| Modificado | `erp-install.php` |
| Modificado | `phpunit.xml` |
| Nuevo | `public/assets/css/accessibility.css` |
| Nuevo | `public/assets/js/accessibility.js` |
| Modificado | `public/assets/js/app.js` |
| Modificado | `public/assets/js/navigation.js` |
| Modificado | `resources/views/auth/login.blade.php` |
| Modificado | `resources/views/catalogo/layout.blade.php` |
| Modificado | `resources/views/compras/index.blade.php` |
| Modificado | `resources/views/inventario/index.blade.php` |
| Modificado | `resources/views/layouts/app.blade.php` |
| Modificado | `resources/views/layouts/g3.blade.php` |
| Nuevo | `resources/views/operaciones/historicas.blade.php` |
| Nuevo | `resources/views/operaciones/stock.blade.php` |
| Modificado | `resources/views/partials/sidebar-links.blade.php` |
| Modificado | `resources/views/proveedores/index.blade.php` |
| Modificado | `resources/views/ventas/ventas.blade.php` |
| Modificado | `routes/console.php` |
| Modificado | `routes/web.php` |
| Nuevo | `scripts/audit-source.php` |
| Nuevo | `scripts/install-scheduler.ps1` |
| Nuevo | `scripts/prepare-test-database.php` |
| Nuevo | `scripts/restore-test-backup.php` |
| Nuevo | `scripts/scheduler-run.ps1` |
| Nuevo | `scripts/start-browser-qa.py` |
| Nuevo | `tests/Browser/package-lock.json` |
| Nuevo | `tests/Browser/package.json` |
| Nuevo | `tests/Browser/seed.php` |
| Nuevo | `tests/Browser/smoke.cjs` |
| Modificado | `tests/Feature/CatalogoPublicoTest.php` |
| Modificado | `tests/Feature/CommercialCompletionMySqlTest.php` |
| Modificado | `tests/Feature/ConteoFisicoTest.php` |
| Modificado | `tests/Feature/G1CatalogApiTest.php` |
| Modificado | `tests/Feature/G1PartnerApiConsumptionTest.php` |
| Modificado | `tests/Feature/G1RendicionesConsumptionTest.php` |
| Modificado | `tests/Feature/G3G4OperationsMySqlTest.php` |
| Modificado | `tests/Feature/G4DependencyRegistrationTest.php` |
| Modificado | `tests/Feature/G4SalesSchemaTest.php` |
| Modificado | `tests/Feature/HistorialPrecioProveedorTest.php` |
| Nuevo | `tests/Feature/OperationalReadinessTest.php` |
| Modificado | `tests/Feature/OperationsConsistencyMySqlTest.php` |
| Modificado | `tests/Feature/SessionSecurityTest.php` |
| Modificado | `tests/Feature/UserStoriesFrontendTest.php` |
| Modificado | `tests/Feature/VentasApiTest.php` |
| Modificado | `tests/Frontend/navigation.test.cjs` |
| Modificado | `tests/TestCase.php` |
| Nuevo | `tests/TestDatabaseSafety.php` |
| Nuevo | `tests/Unit/Ventas/CalculadoraVentaTest.php` |
| Modificado | `tests/UsesG3Database.php` |
| Modificado | `tests/bootstrap.php` |
| Retirado de su ubicación anterior | `tests/Unit/CalculadoraVentaTest.php` |

## 16. Cómo revertir esta etapa

### Código

1. Detener los servidores del proyecto y respaldar cualquier cambio posterior.
2. Desde `Repositorio`, comprobar sin aplicar:

```powershell
python .\Entregables\Validacion_Preparacion_2026-11-09\revertir-codigo.py
```

3. Si las huellas coinciden y se decide revertir, repetir con `--aplicar`. El script restaura sólo los archivos modificados/retirados en esta etapa y elimina sólo los nuevos de esta etapa, comprobando rutas dentro del proyecto y rechazando cambios posteriores. Restaura también la ubicación anterior de la prueba movida. No toca MySQL.
4. Limpiar caché de configuración/vistas antes de iniciar otra vez. No usar `git reset --hard` ni `git clean`: eliminarían trabajo previo que esta entrega conserva.

### Tarea programada

Antes de retirar los scripts nuevos, ejecutar desde ERP-Juan23:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/install-scheduler.ps1 -Remove
```

Se retira únicamente la tarea cuyo nombre corresponde a esta ruta. No se cambian servicios MySQL/XAMPP ni tareas de otros proyectos.

### Base y entorno

Esta etapa no cambió tablas/filas de la base habitual, por lo que **no requiere un rollback de migraciones** para deshacer el código. No eliminar las tablas históricas inventariadas ni importar un dump encima de la base actual para revertir pantallas o tests. Las bases `erp_qa_*` del ensayo están en el servidor aislado 3315, fuera del repo; son diferentes de `sistema_gestion`.

El `.env` habitual no se cambió en esta etapa. `env-antes.privado` queda como evidencia, no para sobrescribir credenciales que se modifiquen después. No subirlo ni subir dumps SQL a Git.

El programador local queda configurado; los servidores utilizados para las pruebas se detienen al finalizar. Las operaciones financieras históricas siguen pendientes de decisión y no se aplican automáticamente por esta revisión.
