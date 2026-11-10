# Validación, operación local y preparación de despliegue

Actualizado: 10/11/2026. Guía vigente para ejecutar las comprobaciones y operar lo agregado en esta etapa. Los informes anteriores son registros históricos, no instrucciones actuales de instalación. El usuario confirmó que por ahora sólo existe entorno local.

## Pruebas aisladas

La ejecución predeterminada usa SQLite `:memory:`. No necesita MySQL ni accede a la base habitual:

```powershell
C:\xampp\php\php.exe vendor\bin\phpunit --fail-on-risky
C:\nodejs\node.exe --test tests/Frontend/*.cjs tests/Frontend/*.js
```

En ese modo se omiten explícitamente las pruebas que requieren MySQL. No están excluidas ni ignoradas en silencio: se ejecutan habilitando una base QA. La suite incluye Feature y todas las carpetas de Unit; antes sólo incluía Unit/Caja.

Para ejecutar **todas** las pruebas se necesita un MySQL/MariaDB exclusivo de QA en `127.0.0.1:3315`. Nunca apuntar ese servicio al datadir de la base habitual ni reutilizar datos de producción. Elegir un nombre nuevo por corrida:

```powershell
$env:ERP_G3_G4_TEST_DATABASE='erp_qa_mi_validacion_01'
# Si el servidor de prueba tiene contraseña:
# $env:ERP_TEST_DB_USERNAME='root'
# $env:ERP_TEST_DB_PASSWORD='configurar-localmente'
C:\xampp\php\php.exe scripts/prepare-test-database.php
C:\xampp\php\php.exe vendor\bin\phpunit --fail-on-risky --fail-on-skipped
```

`prepare-test-database.php` crea únicamente una base nueva con prefijo `erp_qa_` o `erp_test_` en 3315. Rechaza un destino existente y no usa la conexión habitual del `.env` para decidir el destino. No ejecuta `migrate:fresh` sobre una base existente. Los helpers de pruebas insertan fixtures dentro de transacciones y los revierten.

La guarda de pruebas rechaza SQL contra bases no autorizadas antes de ejecutar las consultas. El bootstrap fuerza configuración de prueba y evita cargar un archivo de configuración cacheada de producción. Las pruebas no requieren apagar la base habitual para ser seguras; durante esta entrega se mantuvo apagada salvo su auditoría de sólo lectura.

## Navegador y accesibilidad

```powershell
C:\nodejs\npm.cmd ci --prefix tests/Browser
C:\xampp\php\php.exe tests/Browser/seed.php
$env:ERP_PHP='C:\xampp\php\php.exe'
python scripts/start-browser-qa.py
# Usar Chrome ya instalado, o instalar Chromium con Playwright:
$env:ERP_BROWSER_EXECUTABLE='C:\Program Files\Google\Chrome\Application\chrome.exe'
C:\nodejs\npm.cmd test --prefix tests/Browser
```

Requiere conservar `ERP_G3_G4_TEST_DATABASE` definido con la base QA. El seeder de navegador sólo admite esa base en 3315 y genera credenciales aleatorias privadas. No imprime claves ni las guarda en Git. Rechaza volver a crear los mismos fixtures; usar una base nueva para otro ciclo completo. No ejecutar esos fixtures en una instalación real.

El ayudante genera una APP_KEY efímera para el proceso QA si no se suministró una, sin modificar `.env` ni imprimirla. Rechaza un puerto 8015 ocupado. El servidor temporal usa exclusivamente 8015. Su PID queda en `storage/app/private/qa-browser/server.pid`; detener únicamente ese proceso al terminar. No inicia ni cambia los servidores habituales 8000/8001. En Linux los mismos scripts usan `php` del PATH y Chromium instalado por Playwright.

Los resultados y capturas van a `tests/Browser/results/`, ignorado por Git; durante esta entrega se guardaron fuera del repo en Entregables. El recorrido revisa pantallas autenticadas, catálogo público, cuatro modales representativos, vista móvil, menú, errores JavaScript y reglas de accesibilidad WCAG mediante axe. Incluye denegación de APIs a visitantes y permisos de lectura del contador. No certifica por sí solo cumplimiento integral de WCAG ni todos los navegadores y combinaciones de datos.

Referencias de las herramientas: [Playwright](https://playwright.dev/docs/test-cli), [axe para Playwright](https://github.com/dequelabs/axe-core-npm/tree/develop/packages/playwright).

## Programador: retirado por decisión del usuario

La tarea Windows `ERP-Juan23-26A606BC25` fue eliminada y permanece ausente. Los scripts y comandos del ERP se conservan, pero declarar `Schedule` no los ejecuta. No hay automatización de reemplazo instalada y esta etapa no la crea.

Por lo tanto, el cierre de cajas vencidas, la recuperación periódica de stock y el pulso no se ejecutan automáticamente en este entorno. Mientras no se autorice otro mecanismo, un responsable puede revisar las pantallas y ejecutar manualmente, con MySQL activo:

```powershell
C:\xampp\php\php.exe artisan caja:cerrar-vencidas
C:\xampp\php\php.exe artisan ventas:reenviar-stock --limit=100
```

Son operaciones de negocio: revisar los registros y permisos antes de ejecutarlas. `schedule:list` sólo lista la programación declarada; no prueba que haya una tarea activa. Los archivos de instalación Windows/Linux se conservan como herramientas opcionales para un despliegue futuro, sin instruir su ejecución como parte del arranque habitual.

## Stock pendiente

Administrativo puede abrir `/operaciones/stock`, filtrar pendientes/aplicadas, ver venta, clave en el registro persistido, motivo, intentos, cambios y diagnóstico escapado. El botón reintenta una operación específica con CSRF. No permite editar cantidades ni inventar movimientos desde el monitor. Una operación ya aplicada no se aplica nuevamente.

El monitor no elimina operaciones fallidas: conservar la evidencia y corregir conectividad/stock/contrato antes de reintentar. El comando de reenvío existente sirve para recuperación por lotes.

## Revisión financiera histórica e inventario

```powershell
C:\xampp\php\php.exe artisan erp:audit-finanzas --json
C:\xampp\php\php.exe artisan erp:inventario-esquema --json
```

El primer comando y `/operaciones/historicas` son de sólo lectura. Detectan asientos positivos sin pago canónico, cobros sin aplicación financiera y pagos con respaldo inconsistente. El comando devuelve código 1 si hay casos para revisar; no significa que los haya corregido o que todos sean pagos inválidos. La pantalla muestra hasta 100 por categoría.

No relacionar filas sólo porque coinciden fecha, importe o nombre. Verificar cliente, venta, comprobante, caja, cobro, eventual cheque, reversión y duplicaciones. Registrar una decisión por caso antes de preparar cualquier regularización transaccional. Las categorías pueden solaparse.

El inventario devuelve nombres, cantidades de registros, columnas y claves foráneas; no modifica tablas ni filas. Antes de retirar estructuras históricas comparar ese inventario, las migraciones y los consumidores del código. Un nombre parecido o una tabla vacía no prueban que sea seguro borrarla. No se borró ninguna tabla en esta etapa.

## Instalación y actualización

Respaldar antes de actualizar. Mantener el `.env` y la clave de una instalación existente. El punto de entrada sigue siendo `composer setup` o sus pasos explícitos `composer install` y `php erp-install.php`.

Se ensayó la instalación completa en una copia física independiente con su propio vendor, `.env` y base QA vacía. Se repitió el instalador: no quedaron migraciones pendientes y se conservó la cuenta técnica existente.

Se normalizaron los identificadores SQL para coincidir con los nombres de las tablas creadas por las migraciones: por ejemplo `producto`, `proveedor` y `unidad_medida`, conservando los nombres canónicos mayúsculos que utilizan las tablas de G2. No se renombraron tablas de la base local ni campos JSON.

`SchemaCaseGuard` detiene el instalador si un servidor con `lower_case_table_names=0` tiene nombres incompatibles. No intenta resolverlos creando o borrando tablas. Respaldar y analizar una conversión explícita si aparece un conflicto. Las migraciones anteriores conservan sus nombres. El cierre del 10/11 agrega dos migraciones aditivas y deja 63 archivos en total; ver el informe final antes de actualizar.

No hay Linux/WSL/Docker operativo en esta PC. Se agregó CI de Ubuntu/MariaDB para ejecutar la instalación y suite en un sistema sensible a mayúsculas. Su primera ejecución remota sigue pendiente de publicar/revisar los cambios; no se presenta como una prueba Linux ya realizada.

## Respaldos y ensayo de restauración

```powershell
C:\xampp\php\php.exe artisan erp:backup C:\RespaldosPrivados\ERP
```

El comando usa la conexión configurada, exige MySQL/MariaDB y rechaza guardar dentro de `public`. Genera SQL con transacción consistente, rutinas y triggers, más archivo de huella SHA-256. No expone la contraseña en los argumentos del proceso. Se puede indicar `ERP_MYSQLDUMP` si el ejecutable no está en el PATH o en XAMPP.

El SQL contiene información privada: restringir permisos, cifrar, definir retención, conservar copia fuera de la PC y probar restauraciones. Además del SQL, respaldar `.env`/APP_KEY y `storage/app/private` para conservar credenciales de integración y comprobantes. No publicarlos en Git. No basta con conservar un PDF del informe.

Para ensayar sin sobrescribir una base existente:

```powershell
$env:ERP_RESTORE_TEST_DATABASE='erp_qa_restauracion_nueva'
C:\xampp\php\php.exe scripts/restore-test-backup.php C:\RespaldosPrivados\ERP\respaldo.sql
```

El ensayo sólo admite una base nueva con prefijo de prueba en 3315. Rechaza dumps que seleccionan/crean/eliminan bases y rechaza destinos existentes. Comparar todas las tablas, filas y huellas antes de considerar válido el respaldo. Se verificó esa comparación en esta entrega. Esto no es un comando para restaurar automáticamente producción ni garantiza reconstruir transacciones que nunca llegaron a guardarse.

## Producción futura

El usuario confirmó que sólo hay entorno local. Se conserva su configuración de desarrollo; no se cambia `.env` a producción ni se inventan credenciales SMTP o un certificado.

Preparar en el servidor de destino, sin copiar secretos al repo:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.su-dominio.com
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_LIFETIME=30
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.su-proveedor.com
MAIL_PORT=465
MAIL_USERNAME=CONFIGURAR_EN_SERVIDOR
MAIL_PASSWORD=CONFIGURAR_EN_SERVIDOR
MAIL_FROM_ADDRESS=erp@su-dominio.com
```

`deploy/nginx-erp.conf.example` publica exclusivamente `public/`, redirige HTTP y bloquea rutas sensibles. Ajustar dominio, certificado, permisos y socket PHP. No habilitar `APP_DEBUG` públicamente. Verificar cookies del navegador, renovación del certificado, HTTPS detrás del proxy y descarga de comprobantes autenticada.

```powershell
C:\xampp\php\php.exe artisan erp:doctor --production --database --json
```

El doctor no imprime secretos ni envía correo; comprueba configuración y pulso. Que diga OK no certifica una entrega real de SMTP ni un certificado. Cuando exista servidor, comprobar entrega a un correo propio, recuperación de contraseña, TLS y un ensayo completo de restauración de SQL + archivos privados.

## Integración continua y documentación

`.github/workflows/validation.yml` prepara PHP, MariaDB de prueba en 3315 y Node en Ubuntu; instala desde lock, audita dependencias, crea base nueva, corre PHPUnit sin permitir riesgos/omisiones, ejecuta pruebas JS, revisión PSR-4/migraciones y navegador. Sube únicamente resultados de navegador; no `.env`, credenciales QA ni dumps.

El flujo no se ejecutó en GitHub porque no se hizo commit/push. La implementación local y sus resultados están en [el informe de validación](informe-validacion-preparacion-y-repaso-2026-11-09.md). El [índice de informes](indice-informes.md) concentra los documentos actuales e históricos.

## Imágenes y datos de catálogo

Incluir `public/assets/catalogo` en el respaldo coordinado con SQL, archivos privados y configuración secreta. Los UUID de foto se guardan en Productos: restaurar sólo SQL puede dejar imágenes ausentes. Ensayar también su recuperación y permisos de lectura. Las fotos oficiales cargadas en esta etapa y sus fuentes están identificadas en el [informe de ampliación](informe-catalogo-lotes-articulos-reales-2026-11-09.md). Las fechas, stock y precios de los 15 artículos son de prueba. No se registró una nueva tarea programada.

## Frontend reproducible y paquete para compartir

El frontend interno usa recursos locales fijados por `tools/frontend/package-lock.json`: Tailwind compilado, Lucide, Bootstrap, Chart.js y Font Awesome. No requiere un CDN ni Node para operar. Para reconstruirlos usar `npm ci --prefix tools/frontend` y `npm run build --prefix tools/frontend`; `public/assets/vendor/manifest.json` conserva versiones/huellas y `licenses/` conserva licencias.

`tests/Browser/completion.cjs` añade el circuito de catálogo/solicitud/conversión, lotes, proveedores, estado fiscal, móvil y texto comercial hostil. Usa fixtures exclusivos QA, incluida una imagen de prueba generada por el seeder. No depende de cargar los artículos comerciales en CI. La imagen QA no se incluye en el paquete de entrega.

Preparar un ZIP nuevo fuera del proyecto con `python scripts/package-release.py --output ../Entregables/ERP-fuentes.zip`. El ayudante no publica ni altera fuentes. Excluye entornos reales, dumps SQL/SQLite, runtime privado, Git, dependencias instaladas y respaldos; incluye migraciones PHP, locks, assets compilados/licencias y `.env.example` sin credenciales. Comprueba los valores secretos largos de `.env` contra los archivos incluidos y genera/verifica un manifiesto SHA-256. Esto no sustituye revisión humana de datos sensibles en documentación histórica.

El workflow local incluye reconstrucción de assets, auditorías, instalación, regresiones, restauración/actualización QA y navegador en Ubuntu. Su ejecución real en GitHub sigue pendiente hasta publicar la versión revisada y ejecutar Actions; no confundir el YAML local ni las pruebas Windows con un resultado remoto exitoso.

## Cierre del 10/11 y fuentes para compartir

Resultados actuales, cambios de compras/pagos, dos migraciones, revisión histórica y reversión: [informe final](informe-cierre-pendientes-y-revision-2026-11-10.md). Trazabilidad: [issues y ramas](revision-issues-y-ramas-2026-11-10.md). Para revisar únicamente lo tocado en esa etapa: [inventario](inventario-cambios-cierre-2026-11-10.md).

Para preparar un ZIP **nuevo** fuera del proyecto: `python scripts/package-release.py --output C:/Entregas/ERP-fuentes.zip`. Excluye entornos reales, SQL/SQLite, runtime, Git y dependencias instaladas; incluye locks, migraciones PHP, assets compilados y licencias. No sustituye un respaldo privado de BD, APP_KEY o comprobantes.
