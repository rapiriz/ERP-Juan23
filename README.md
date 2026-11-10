# ERP Juan23

## Entorno integrado (09/11/2026)

Se completó la etapa de bloqueos generales e integración: migraciones que conservan datos, instalación desde cero y actualización, transporte interno entre módulos, fuentes financieras y correcciones de escape de HTML. Los cambios y sus límites están en [el informe de esta etapa](docs/informe-bloqueos-generales-e-integracion-2026-11-09.md).

Para trabajar, iniciar **MySQL** desde XAMPP y ejecutar desde la raíz del proyecto:

```powershell
C:\xampp\php\php.exe -d extension=gd -d extension=zip artisan serve
```

Abrir `http://127.0.0.1:8000`. El catálogo público está en `/catalogo`; su JSON está en `/api/v1/catalogo` y `/api/v1/catalogo/{id}`. Apache es opcional: los consumidores locales configurados con `internal` llaman las rutas registradas dentro de Laravel, conservando sus middleware y evitando HTTP hacia el mismo servidor de desarrollo.

Para detener la web, pulsar **Ctrl+C**. La base existente conserva sus datos. Los informes de octubre y la recuperación de MySQL del 08/11 son antecedentes históricos; la secuencia de instalación vigente es la siguiente.

## Funcionalidades de G1 completadas (09/11/2026)

En **Reportes y Finanzas** se agregaron ventas diarias, caja diaria, stock bajo y vencimientos a 7, 15 y 30 días. Los siete reportes de consulta tienen descarga PDF/Excel; se mantiene la exportación del historial de cada cliente. Los filtros se conservan al exportar y se incluyen todos los resultados consultados, independientemente de la página de pantalla.

La ficha de cliente distingue pagos aplicados de cobros declarados o rendiciones aprobadas. Un administrativo puede registrar un pago parcial si el cliente tiene deuda y el usuario tiene una caja abierta. El registro vincula caja, cuenta corriente y auditoría en una transacción; una repetición con la misma clave de solicitud no duplica el pago.

En `/transferencias` se pueden registrar avisos con comprobante privado PDF/JPEG/PNG de hasta 5 MiB. Un administrativo los valida o rechaza con una nota. **Validar el aviso no acredita el pago**: después se abre la ficha del cliente y se registra expresamente el pago asociado. El contador puede consultar y descargar comprobantes; no registrar ni validar operaciones.

Las nuevas consultas JSON protegidas son `GET /api/v1/reportes/ventas-diarias`, `/caja-diaria`, `/stock-bajo` y `/vencimientos`, bajo el mismo prefijo `/api/v1/reportes`. Los avisos usan `/api/v1/transferencias` y el pago explícito usa `POST /api/v1/finanzas/clientes/{id}/pagos`. Las escrituras requieren un usuario administrativo autenticado; el token técnico de integración no habilita estas escrituras.

La actualización aplica dos migraciones modulares nuevas y crea cuatro tablas de pagos, avisos y auditoría. Para instalaciones existentes, ejecutar `C:\xampp\php\php.exe erp-install.php` después del respaldo; no usar `migrate:fresh` sobre datos de trabajo. Cambios, contratos, pruebas, límites y reversión: [informe de funcionalidades de G1](docs/informe-funcionalidades-g1-2026-11-09.md).

## Finanzas, rendiciones y entregas de G2 (09/11/2026)

Se completó el recorrido integrado de revisión individual de cobros, aplicación a caja/cuenta corriente, anulación compensatoria y rechazo de cheques. Los cobros pendientes y sus operaciones están en `/cobros`, accesible desde Rendiciones. Un administrativo necesita su caja abierta para aplicar o revertir dinero. Los históricos sin un vínculo financiero verificable se bloquean para revisión: no se vuelven a liquidar automáticamente.

`/conciliacion` permite importar XLS/TXT, consultar propuestas, conciliar uno o varios movimientos, registrar gastos bancarios, gestionar disputas, revisar cheques y cerrar períodos. Las conciliaciones validan sumas, signo, disponibilidad y período; el historial permite revertir grupos mientras el período esté abierto. Una conciliación bancaria no vuelve a acreditar un cobro ya aplicado.

En la hoja de ruta de una entrega en preparación aparece **Modificar pedidos**. Se pueden quitar o incorporar pedidos de la misma zona; al quitar, el pedido vuelve a confirmado y se conserva el remito anterior como anulado. Este recorrido atómico usa la integración local `internal`.

La migración modular `2026_11_09_000003_complete_g2_operations` agrega cinco tablas de vínculos, reservas y auditoría, y amplía Rendición Cobro, Entrega y Conciliación Detalle. No elimina datos históricos. Para actualizar otras instalaciones, respaldar su base y ejecutar `C:\xampp\php\php.exe erp-install.php`.

Detalle de errores, archivos, contratos, pruebas reales, restricciones y reversión: [informe de G2](docs/informe-g2-finanzas-rendiciones-entregas-2026-11-09.md).

## Catálogo público de G1

Consulta sin iniciar sesión en `/catalogo`, con búsqueda, filtros, disponibilidad y detalle. Consume las APIs de Productos de G3 sin agregar tablas ni modificar datos de otros grupos. Si el proveedor no está conectado, muestra su estado de indisponibilidad.

Configuración, alcance, pruebas y reversión: [informe de implementación del Catálogo](docs/informe-implementacion-catalogo-g1.md).

## Actualización de Clientes de G1 (27/10/2026)

El alta y la edición incluyen selector DNI/CUIT, localidad y zona (existente o nueva), calle y altura separadas, y validaciones en navegador y servidor. Se retiró el archivo de migración Sprint 3 sin ejecutar rollback ni borrar tablas. Su parte de Clientes/Zonas de G1 y los nuevos campos quedaron en `2026_10_27_000001_complete_g1_clientes_schema.php`: agrega solo lo que falte y conserva los datos existentes. Detalles, ejecución limitada a G1, pruebas y reversión: [informe de Clientes](docs/informe-clientes-campos-validacion-g1.md).

## Requisitos

- PHP 8.2 o superior.
- Laravel 12.x.
- Composer 2.x.
- MySQL 8 o MariaDB 10.4 o superior.
- Extensiones PHP: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `tokenizer`, `xml` y `zip`.

El proyecto fue verificado con PHP 8.2.12, Laravel 12.69.2 y MariaDB 10.4.32 de XAMPP.

En XAMPP, habilitar `extension=gd` y `extension=zip` en `C:\xampp\php\php.ini`. Como alternativa, los comandos de este README que generan PDF o Excel incluyen ambas extensiones mediante `-d`.

## Instalación y actualización

1. Instalar las dependencias con `composer install`.
2. En una instalación nueva, copiar `.env.example` a `.env`. En una existente, conservar `.env` y `APP_KEY`.
3. Crear una base MySQL/MariaDB vacía para una instalación nueva, o respaldar la base existente. Configurar `DB_CONNECTION=mysql`, host, puerto, base, usuario y contraseña en `.env`.
4. Iniciar MySQL y ejecutar:

   ```powershell
   composer setup
   ```

   Si Composer no está en PATH, en este equipo:

   ```powershell
   C:\xampp\php\php.exe C:\ProgramData\ComposerSetup\bin\composer.phar setup
   ```

El instalador conserva una `APP_KEY` existente, ejecuta las migraciones pendientes y configura una identidad técnica y los transportes internos. Crea la clave sólo si falta. No ejecuta semillas ni importa SQL históricos. Las migraciones de limpieza de G3 conservarán lotes, identificadores y tablas históricas.

Para crear el primer administrador en una base nueva:

```powershell
C:\xampp\php\php.exe artisan erp:configure --admin
```

Pide usuario y contraseña de al menos 12 caracteres. No modifica las credenciales de usuarios existentes. La cuenta `servicio.integracion` sirve para las llamadas entre módulos; su contraseña aleatoria no se entrega y su token permanece en `.env`.

Si las dependencias ya están instaladas, `C:\xampp\php\php.exe erp-install.php` realiza la actualización y configuración. El arranque diario requiere solamente MySQL y `artisan serve`.

No usar `migrate:fresh`, `migrate:refresh`, rollback general ni semillas sobre una base con datos que deban conservarse. Los SQL históricos no son el instalador vigente. Ante referencias huérfanas de usuarios, la migración se detiene para revisar la identidad original, sin asignar registros a otro usuario.

## Integración local y remota

`erp:configure` establece `VENTAS_INTEGRACION=internal`, `CATALOG_TRANSPORT=internal` y los transportes locales de reportes, facturación, saldos, rendiciones y Entregas/Ventas. Ventas toma clientes reales de G1, productos y movimientos de stock de G3. `mock` requiere una selección explícita; un valor desconocido produce un error de configuración.

Las fuentes financieras de Cuenta Corriente de G4 son:

| Método | Ruta | Datos |
| --- | --- | --- |
| GET | `/api/v1/finanzas/clientes/{id}/saldo` | Deuda, saldo a favor y saldo contable |
| GET | `/api/v1/finanzas/clientes/deudores` | Clientes con deuda |
| GET | `/api/v1/finanzas/facturas/pendientes` | Ventas internas con importe pendiente y vencimiento opcional |
| GET | `/api/v1/finanzas/cobros?cliente_id={id}` | Pagos efectivamente aplicados al libro |

Exigen autenticación y permisos. Un cobro declarado en una rendición no se presenta como pago confirmado. El informe especifica signos, estados, asignación de pagos y límites de estos contratos.

Para servicios separados, elegir `http` en el transporte correspondiente y configurar URL, token y rutas del proveedor. No apuntar esos consumidores HTTP al mismo proceso único de `artisan serve`. Los tokens son privados y no deben subirse a Git.

## Datos de demostración

`composer setup` no carga productos, clientes ni contraseñas de prueba. `artisan db:seed` queda como acción explícita de desarrollo y puede agregar datos de demostración; el seeder de usuarios conserva las credenciales de cuentas ya existentes. Revisar las semillas antes de ejecutarlas sobre una base compartida.

## Permisos

Los reportes consumen las APIs locales mediante transporte interno. La liquidación completa de cobros y la conciliación bancaria siguen requiriendo las validaciones funcionales detalladas en el informe de esta etapa.

| Funcionalidad | Administrativo | Repartidor | Contador |
| --- | --- | --- | --- |
| Panel propio | Sí | Sí | Sí |
| Listar y buscar clientes | Sí | Sí | No |
| Crear y editar clientes | Sí | No | No |
| Crear y listar reclamos | Sí | Sí | No |
| Ver historial de clientes | Sí | Sí | No |
| Consultar cobros y saldos de otros módulos | Sí | Sí | No |
| Reportes de clientes y ranking comercial | Sí | No | Sí |
| Exportar historial de clientes a PDF y Excel | Sí | No | Sí |
| Secciones de ventas y stock sin consultas de G1 | Sí | Sí | No |
| Configurar emails de usuarios | Sí | No | No |

## Seguridad de acceso

- Tres contraseñas incorrectas bloquean la cuenta durante 15 minutos.
- Cada cuenta admite una sola sesión activa.
- La sesión expira después de 120 minutos sin actividad.
- El logout utiliza POST con protección CSRF.
- Las contraseñas usan hashing seguro de Laravel.
- Los identificadores de sesión guardados en la base están hasheados.
- Los permisos se verifican en middleware, Policies y Form Requests.
- La recuperación usa códigos numéricos hasheados que vencen en 15 minutos y admiten hasta 5 intentos.

## Recuperación de contraseña por email

En desarrollo, `MAIL_MAILER=log` guarda el mensaje y su código en `storage/logs/laravel.log`, pero no envía un correo real. Para usar un servidor SMTP, configure en `.env`:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_SCHEME=null
MAIL_USERNAME=usuario_smtp
MAIL_PASSWORD=contrasena_smtp
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="Varela ERP"
```

Después de modificar `.env`, limpiar la configuración:

```powershell
C:\xampp\php\php.exe artisan config:clear
```

## Pruebas y comprobaciones

Ejecutar la suite automatizada:

```powershell
C:\xampp\php\php.exe -d extension=gd -d extension=zip artisan test
```

La instalación integrada se valida sobre una base **MySQL/MariaDB separada y vacía**. SQLite en memoria se usa en pruebas unitarias y de contratos; no representa todo el esquema de G3. Algunas pruebas heredadas usan MySQL directamente: revisar `tests/UsesG3Database.php` antes de ejecutar toda la suite. El informe registra las exclusiones y los fallos previos que permanecen.

Revisar rutas y formato:

```powershell
C:\xampp\php\php.exe artisan route:list --except-vendor
C:\xampp\php\php.exe vendor\bin\pint --test
```

La suite incluye pruebas de seguridad, clientes, reclamos, integración de cobros y saldos, reportes y exportaciones.

## Estructura principal

```text
app/
  Http/
    Controllers/  Controlador base y panel principal compartido
  Modules/
    Login/        Controllers, Models, Requests, Services, Enums, Middleware y Mail
    Clientes/     Controllers, Models, Requests, Enums, Policies y Resources
    Reclamos/     Controllers, Models, Requests, Enums y Policies
    Reportes/     Controllers, Requests y Services
    Productos/    Models y Enums del esquema histórico
    Ventas/       Models y Enums del esquema histórico
  Contracts/      Interfaces compartidas entre módulos
  Providers/      Registro de servicios y políticas
  Services/
    Gateways/     Clientes HTTP compartidos para APIs de otros grupos
database/
  factories/      Datos para pruebas
  migrations/     Esquema de base de datos
  seeders/        Usuarios de desarrollo
public/assets/    CSS y JavaScript conservados
resources/views/ Vistas Blade
routes/web.php    Rutas web con nombre
routes/api.php    Rutas JSON de integración
tests/Feature/    Pruebas automatizadas
```

Todos los módulos, incluido `Login`, siguen el mismo criterio: las clases PHP se agrupan en `app/Modules/{Modulo}/`, y las rutas, vistas, migraciones, factories, seeders y assets permanecen en sus carpetas generales. Cada módulo contiene las subcarpetas necesarias para su funcionalidad. Ver [estructura-modular.md](docs/estructura-modular.md) para la distribución y el alcance.

## Configuración de producción

Configurar credenciales reales en `.env`, utilizar `APP_DEBUG=false`, habilitar HTTPS y definir `SESSION_SECURE_COOKIE=true`. Nunca subir `.env` al repositorio.

El detalle completo de la migración está en [MIGRATION.md](MIGRATION.md).

## Integración entre grupos

La API JSON de G1 y los contratos HTTP esperados de G2, G3 y G4 se documentan en [docs/integracion-microservicios.md](docs/integracion-microservicios.md). El modo `internal` consume las rutas API registradas de los módulos de esta misma aplicación; `http` permite proveedores separados. El login API es publico; las demas rutas aceptan un token de usuario o, segun sus permisos, `INTERNAL_API_TOKEN`. Ningun token debe incluirse en el repositorio.


### Compras, stock y ventas: correcciones del 09/11/2026

- Informe de cambios, pruebas y reversión: [G3/G4 — Compras, stock y ventas](docs/informe-g3-g4-compras-stock-ventas-2026-11-09.md).
- Después de actualizar el código, respaldar la base y ejecutar `C:\xampp\php\php.exe artisan migrate`. La nueva migración agrega las tablas de seguimiento `g4_stock_operations` y `g3_stock_requests`.
- Con `VENTAS_INTEGRACION=internal` se mantiene el uso de `artisan serve` y MySQL; no se necesita otro servidor HTTP para Stock.
- Recuperación de ajustes pendientes: `C:\xampp\php\php.exe artisan ventas:reenviar-stock --limit=100`. Su programación está declarada, pero la tarea local fue retirada por pedido del usuario; actualmente el reenvío se ejecuta manualmente.
- PDF de saldos y pagos: botón de descarga en Cuentas corrientes (`/saldo`), con los filtros activos y acceso autenticado.


### Crédito, devoluciones y compras: cierre funcional del 09/11/2026

- [Informe detallado, APIs, pruebas y reversión](docs/informe-cierre-funcional-g1-g3-g4-2026-11-09.md).
- La migración `2026_11_09_000007_complete_commercial_workflows.php` agrega límite opcional de crédito y registros de devoluciones/notas internas. Respaldar la base antes de migrar; no borra datos históricos.
- Devoluciones y comprobantes internos: `/devoluciones`. Las notas no son comprobantes fiscales. Sólo mercadería recibida como apta aumenta el stock disponible.
- Las entregas seleccionan pedidos confirmados. Confirmación y observaciones están disponibles desde Historial de ventas; la confirmación vuelve a validar el crédito en el servidor.
- Inactividad máxima de sesión: 30 minutos. No publicar `.env` ni respaldos de la base.


### Validación y operación local (09/11/2026)

- [Guía vigente: pruebas aisladas, programador, monitor, respaldo y despliegue](docs/validacion-y-despliegue.md).
- [Informe de correcciones y revisión del repositorio](docs/informe-validacion-preparacion-y-repaso-2026-11-09.md) e [índice de informes](docs/indice-informes.md).
- Administrativo: `/operaciones/stock` y `/operaciones/historicas`. Reintentos de stock conservan su clave; la revisión financiera nunca aplica pagos automáticamente.
- La tarea programada local permanece eliminada; iniciar MySQL y Artisan no instala ni activa un programador. Ver la guía para operación manual.
- Inventario de APIs: [rutas registradas](docs/api-rutas-registradas.md). Ejemplos en la colección Postman existente.
- No ejecutar pruebas contra la base habitual: el modo MySQL usa una base exclusiva `erp_qa_*` en 3315. La guía explica cómo habilitar todas las pruebas sin omisiones.

## Ampliación del 09/11/2026

[Catálogo, artículos reales, lotes, proveedores y preparación fiscal](docs/informe-catalogo-lotes-articulos-reales-2026-11-09.md).

### Cierre y revisión del 10/11/2026

- [Correcciones, migraciones aditivas, pruebas y reversión](docs/informe-cierre-pendientes-y-revision-2026-11-10.md).
- [Revisión de issues, PR y ramas](docs/revision-issues-y-ramas-2026-11-10.md).
- [Inventario de archivos de esta etapa](docs/inventario-cambios-cierre-2026-11-10.md) y [APIs registradas](docs/api-rutas-registradas.md).
- Assets locales: `npm ci --prefix tools/frontend` y `npm run build --prefix tools/frontend`.
- La tarea de Windows permanece retirada. No hay ejecución automática instalada; ver los comandos manuales en la guía vigente.
