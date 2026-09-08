# Sistema web de gestion

Sistema web de gestion con login seguro, gestion de clientes y funcionalidades de Sprint 2.

## Requisitos

- PHP 8.2 o superior. Desarrollado y revisado localmente con PHP 8.5.7.
- MySQL 8 o MariaDB compatible.
- Apache, XAMPP, WAMP o el servidor embebido de PHP para desarrollo local.

## Instalacion

### Opcion A: XAMPP/WAMP en Windows

1. Iniciar Apache.
2. Iniciar MySQL.
3. Abrir phpMyAdmin.
4. Ir a la pestana Importar.
5. Seleccionar `database/database.sql`.
6. Ejecutar la importacion.
7. Si la base ya habia sido importada antes de Sprint 1, ejecutar tambien `database/migracion_sprint1.sql` para agregar los campos de bloqueo al login.
8. Si la base ya habia sido importada antes de Sprint 2, ejecutar tambien `database/migracion_sprint2.sql` para agregar sesion unica y reclamos.
9. Revisar `config/database.php`:

   ```text
   host: localhost
   puerto: 3306
   base: sistema_gestion
   usuario: root
   contrasena: vacia por defecto en XAMPP
   ```

10. Si tu MySQL tiene contrasena para `root`, crear una copia local de configuracion:

   ```bash
   copy config\database.local.example.php config\database.local.php
   ```

   Luego editar `config/database.local.php` y colocar la contrasena real en `DB_PASSWORD`.

11. Abrir el proyecto desde Apache o levantarlo con el servidor embebido de PHP.

### Opcion B: PHP + MySQL por terminal

1. Confirmar que PHP tenga habilitado `pdo_mysql`:

   ```bash
   php -m
   ```

   Debe aparecer `pdo_mysql`. En Windows, si falta, habilitar esta linea en `php.ini` y reiniciar Apache o la terminal:

   ```ini
   extension=pdo_mysql
   ```

2. Crear la base e importar las tablas:

   ```bash
   mysql -u root -p < database/database.sql
   ```

3. Configurar credenciales MySQL mediante una de estas opciones:

   Crear `config/database.local.php` a partir de `config/database.local.example.php`:

   ```bash
   copy config\database.local.example.php config\database.local.php
   ```

   O usar variables de entorno:

   ```text
   DB_HOST
   DB_PORT
   DB_NAME
   DB_USER
   DB_PASSWORD
   ```

   Las variables de entorno tienen prioridad sobre `config/database.local.php`.

4. Levantar el sistema desde la carpeta del proyecto:

   ```bash
   php -S localhost:8000
   ```

5. Abrir la URL inicial:

   ```text
   http://localhost:8000/
   ```

## Diagnostico de instalacion

Para revisar el entorno local durante desarrollo:

```bash
php scripts/check_database.php
```

El script comprueba PHP, PDO, `pdo_mysql`, conexion MySQL, base `sistema_gestion`, tablas principales y usuarios de prueba. No debe publicarse ni exponerse como herramienta web en produccion.

Si el diagnostico muestra:

```text
ERROR Conexion con MySQL - SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: NO)
```

significa que MySQL esta funcionando, pero el usuario configurado requiere contrasena. Configurar `DB_PASSWORD` en `config/database.local.php` o mediante variable de entorno.

## Usuarios de prueba

Estas credenciales son solo para desarrollo local.

| Usuario | Contrasena | Rol |
| --- | --- | --- |
| admin | Admin1234 | administrativo |
| vendedor | Vendedor1234 | vendedor |
| inactivo | Inactivo1234 | vendedor inactivo |

## Funcionalidades incluidas

- Login con usuario y contrasena.
- Validacion de usuarios activos contra MySQL.
- Bloqueo temporal de usuario por 15 minutos luego de 3 intentos fallidos.
- Hash de contrasenas con `password_hash()` y verificacion con `password_verify()`.
- Sesiones PHP con regeneracion de ID luego del login.
- Registro de inicio de sesion exitoso en `login_logs`.
- Dashboard con nombre de usuario, rol, accesos de clientes y cierre de sesion.
- Registro de clientes para roles `administrativo` y `vendedor`.
- Listado y busqueda de clientes para rol `administrativo`.
- Edicion de datos y estado de clientes para rol `administrativo`.
- Etiquetas visuales para diferenciar clientes `mayorista` y `minorista`.
- Creacion de reclamos desde el panel del vendedor y listado inicial para seguimiento.
- Sesion unica por cuenta: un nuevo login invalida la sesion anterior del mismo usuario.
- Expiracion de sesion por inactividad.
- Redireccion al panel cuando un usuario intenta acceder a una pantalla sin permisos.
- Validaciones backend y frontend basicas.
- Proteccion CSRF simple en formularios POST.
- Restricciones UNIQUE para DNI/CUIT y email.

## Estructura principal

```text
/
├── index.php
├── login.php
├── logout.php
├── dashboard.php
├── config/database.php
├── auth/session.php
├── auth/require_auth.php
├── auth/authenticate.php
├── clientes/index.php
├── clientes/nuevo.php
├── clientes/guardar.php
├── clientes/editar.php
├── clientes/actualizar.php
├── reclamos/index.php
├── reclamos/nuevo.php
├── reclamos/guardar.php
├── ventas/index.php
├── stock/index.php
├── assets/css/styles.css
├── assets/js/app.js
├── database/database.sql
├── database/migracion_sprint1.sql
├── database/migracion_sprint2.sql
└── README.md
```

## Pruebas manuales sugeridas

Login:

- Usuario correcto y contrasena correcta: acceso permitido.
- Usuario correcto y contrasena incorrecta: acceso rechazado.
- Usuario inexistente: acceso rechazado.
- Usuario inactivo: acceso rechazado.
- Login correcto: se inserta un registro en `login_logs`.
- Tres contrasenas incorrectas para un usuario activo: usuario bloqueado 15 minutos.
- Usuario bloqueado: acceso rechazado aunque la contrasena sea correcta hasta que venza el bloqueo.
- Login correcto luego del vencimiento: se reinician los intentos fallidos.

Clientes:

- Todos los campos validos: cliente registrado.
- Campo obligatorio vacio: cliente no registrado y se muestra error.
- DNI/CUIT repetido: cliente no registrado y se muestra error.
- Email repetido: cliente no registrado y se muestra error.
- Email invalido: cliente no registrado y se muestra error.
- Tipo de cliente invalido por POST manual: cliente no registrado.
- Acceso directo a `clientes/nuevo.php` sin sesion: redireccion al login.
- Usuario administrativo: puede entrar a `clientes/index.php`, buscar clientes y abrir `editar.php`.
- Usuario vendedor: no puede entrar a listado ni edicion administrativa de clientes.
- Edicion con DNI/CUIT o email usado por otro cliente: cliente no actualizado.
- Los clientes mayoristas y minoristas muestran etiquetas diferenciadas en listado y panel.
- Usuario administrativo: puede crear y listar reclamos.
- Usuario vendedor: ve accesos a clientes, reclamos, ventas y stock desde su panel.
- Usuario vendedor: puede crear reclamos asociados a clientes registrados.
- Iniciar sesion con el mismo usuario en dos navegadores: la sesion anterior queda invalidada.
- Dejar la sesion inactiva mas de 30 minutos: el sistema pide iniciar sesion nuevamente.
- Usuario sin permisos en una pantalla administrativa: vuelve al panel con mensaje de permiso.
