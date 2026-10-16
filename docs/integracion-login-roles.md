# Integración de login y roles

Esta rama (`feature/login-roles`) contiene el flujo común de autenticación que deben utilizar los demás grupos para probar sus pantallas.

## Puesta en marcha local

Desde la raíz del proyecto:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Abrir `http://localhost:8000/login`.

El seeder crea estos usuarios de desarrollo:

| Usuario | Contraseña | Rol |
| --- | --- | --- |
| `admin` | `Admin1234` | `administrativo` |
| `repartidor` | `Repartidor1234` | `repartidor` |
| `contador` | `Contador1234` | `contador` |

Estas credenciales son únicamente para desarrollo local y no deben utilizarse en producción.

## Acceso por rol

| Pantalla o módulo | Administrativo | Repartidor | Contador |
| --- | --- | --- | --- |
| Panel principal | Sí | Sí | Sí |
| Clientes: listar y consultar | Sí | Sí | No |
| Clientes: crear y editar | Sí | No | No |
| Reclamos | Sí | Sí | No |
| Reportes | Sí | No | Sí |
| Ventas y stock | Sí | Sí | No |
| Usuarios y emails | Sí | No | No |

La interfaz oculta los accesos no permitidos, pero la autorización también se aplica en las rutas mediante el middleware `role`. No debe confiarse únicamente en la visibilidad del menú.

## Flujo de autenticación web

- `GET /login` muestra el formulario.
- `POST /login` autentica al usuario y redirige al panel.
- `POST /logout` cierra la sesión y regenera el token CSRF.
- Tres contraseñas incorrectas bloquean la cuenta durante 15 minutos.
- Cada usuario mantiene una única sesión activa.
- La sesión vence después de 120 minutos sin actividad.

## Login para consumidores API

Los grupos que consuman la API pueden obtener un token con:

```http
POST /api/v1/auth/login
Accept: application/json
Content-Type: application/json

{
  "usuario": "admin",
  "password": "Admin1234"
}
```

La respuesta contiene `data.access_token`, `data.expires_at` y el rol en `data.usuario.rol`. Las solicitudes protegidas deben enviar:

```http
Authorization: Bearer <access_token>
```

El token no debe guardarse en el repositorio ni compartirse fuera del entorno local.

## Verificación antes de integrar

```powershell
php artisan test
php artisan route:list --except-vendor
vendor/bin/pint --test
```

La integración debe conservar las migraciones, el seeder, los middleware, las rutas protegidas y las pruebas de `tests/Feature/AuthenticationTest.php`, `tests/Feature/SessionSecurityTest.php` y `tests/Feature/LoginRoleVisibilityTest.php`.
