# Estructura del proyecto ERP-JUAN23 y convenciones de modularización

> Objetivo: que cualquiera del equipo (back o front) sepa **qué hay en cada carpeta,
> de cuál depende, y dónde poner cada cosa nueva**.

---

## 1. Cómo viaja un pedido por las carpetas

```
Navegador  ── GET /ventas ──►  public/index.php        (única puerta de entrada)
                                   │
                                   ▼
                              bootstrap/app.php         (arranca Laravel, registra rutas y middleware)
                                   │
                                   ▼
                              routes/web.php  ──require──►  routes/ventas.php   (¿qué controller atiende esta URL?)
                                   │
                                   ▼
                    app/Http/Controllers/Ventas/*       (valida lo que llega)
                                   │
                                   ▼
                    app/Services/Ventas/*               (reglas de negocio)
                                   │
                                   ▼
                    app/Models/*  ────────►  MySQL (.env → config/database.php)
                                   │
              ┌────────────────────┴───────────────────┐
              ▼                                        ▼
   resources/views/*.blade.php (HTML)          respuesta JSON (los fetch del front)
   (+ CSS/JS compilados por Vite → public/build)
```

`config/*` y `.env` son consultados por TODAS las capas. `storage/` guarda lo que el sistema escribe (sesiones, logs).

---

## 2. Carpeta por carpeta

### Carpetas que SE EDITAN (nuestro trabajo)

| Carpeta                           | Para qué sirve                                                                                                               | Qué va ahí / vinculación                                                                                                                   |
| --------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| **`app/`**                        | Todo el código PHP propio de la aplicación. Se autocarga bajo el namespace `App\` (la ruta de carpeta = el namespace).       | Depende de: `config/` (valores), `database` (vía modelos). La usan: `routes/` (apuntan a controllers) y `resources/views` (reciben datos). |
| `app/Http/Controllers/<Modulo>/`  | Reciben el pedido HTTP, **validan** y llaman al Service. Cero reglas de negocio.                                             | Llaman a → `Services`. Los llama ← `routes/`.                                                                                              |
| `app/Services/<Modulo>/`          | **Lógica de negocio** (precios, stock, cobro). Carpeta creada por nosotros.                                                  | Usan → `Models`, `config`. Los llaman ← `Controllers` y otros Services.                                                                    |
| `app/Models/`                     | Un archivo por tabla de MySQL. Define tabla, clave primaria, relaciones, scopes.                                             | Hablan con → MySQL. Los usan ← `Services`/`Controllers`.                                                                                   |
| `app/Exceptions/`                 | Errores propios (ej: `VentaException`).                                                                                      | Lanzadas por → `Services`.                                                                                                                 |
| `app/Support/`                    | Ayudantes chicos transversales (ej: `UsuarioActual`).                                                                        | Usados por → `Controllers`.                                                                                                                |
| **`routes/`**                     | Tabla "URL → controller". `web.php` (con sesión y CSRF) y un archivo por módulo (`ventas.php`) que se enchufa con `require`. | Apuntan a → `Controllers`. Cargadas por ← `bootstrap/app.php`.                                                                             |
| **`resources/`**                  | Lo que ve el usuario.                                                                                                        |                                                                                                                                            |
| `resources/views/`                | Plantillas Blade (`.blade.php`). `layouts/app.blade.php` es el marco común; cada pantalla lo extiende con `@extends`.        | Reciben datos de ← `Controllers` (`view('ventas', [...])`). Usan → `public/build` (vía `@vite`).                                           |
| `resources/css/`, `resources/js/` | CÓDIGO FUENTE de estilos y scripts. Vite los compila.                                                                        | Compilados por → `vite.config.js` → salen en `public/build/`.                                                                              |
| **`config/`**                     | Archivos de configuración (`database.php`, `session.php`, y el nuestro `ventas.php`). Leen valores del `.env`.               | Leídos por ← todas las capas con `config('archivo.clave')`. **Regla:** `env()` solo se usa dentro de `config/`.                            |
| **`database/`**                   | Migraciones, seeders y factories.                                                                                            | **En este proyecto la base nace del script `distribuidora_mysql.sql`** (phpMyAdmin), no de migraciones. Ver advertencia abajo.             |
| **`tests/`**                      | Pruebas automáticas (`Feature/` prueba URLs completas, `Unit/` prueba clases sueltas). Se ejecutan con `php artisan test`.   | Prueban → `Services` y `Controllers`. Configuradas por `phpunit.xml`.                                                                      |

> ⚠️ **`php artisan migrate`:** sobre la base compartida `distribuidora` crearía tablas estándar de Laravel
> (`users`, `sessions`, `cache`, `jobs`…). No lo corras sin avisar al equipo. Por eso usamos `SESSION_DRIVER=file`.

### Carpetas GENERADAS o de FRAMEWORK (no se editan a mano)

| Carpeta             | Qué es                                                                                                                                                                                           | Notas                                                                                                                                          |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| **`vendor/`**       | Librerías PHP que instala Composer (incluye Laravel entero).                                                                                                                                     | Se crea con `composer install`. **Nunca editar ni subir a git.** Su `autoload.php` es lo que permite que `App\Services\...` se encuentre solo. |
| **`node_modules/`** | Librerías JavaScript que instala npm (Vite, etc.).                                                                                                                                               | Se crea con `npm install`. No editar ni subir a git.                                                                                           |
| **`bootstrap/`**    | Arranque del framework. `app.php` registra rutas, middleware y manejo de errores. `cache/` guarda cachés internas.                                                                               | Casi nunca se toca. Solo si hay que registrar un middleware o cambiar cómo se cargan las rutas.                                                |
| **`public/`**       | **Única carpeta accesible desde el navegador.** Contiene `index.php` (entrada), imágenes públicas y `build/` (CSS/JS compilados por Vite).                                                       | El servidor web (Apache de XAMPP o `php artisan serve`) debe apuntar acá. Todo lo demás queda fuera del alcance web, por seguridad.            |
| **`storage/`**      | Lo que el sistema escribe: `logs/laravel.log` (**ahí están los errores**), `framework/sessions` (donde vive el carrito con `SESSION_DRIVER=file`), `framework/cache`, `app/` (archivos subidos). | Debe tener permisos de escritura. No subir a git.                                                                                              |

### Archivos de la raíz

| Archivo                                  | Función                                                                                                                                                |
| ---------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **`.env`**                               | Configuración **de tu máquina** (base de datos, claves, timezone). No se sube a git. Cada integrante tiene el suyo.                                    |
| **`.env.example`**                       | Plantilla del `.env` que sí se comparte. Si agregamos una variable nueva, se agrega acá también. (El nombre correcto es `.env.example`.)               |
| **`artisan`**                            | Consola de Laravel: `php artisan route:list`, `optimize:clear`, `test`, etc.                                                                           |
| **`composer.json` / `composer.lock`**    | Qué librerías PHP necesita el proyecto y las versiones exactas. El `lock` se sube a git para que todos tengan lo mismo.                                |
| **`package.json` / `package-lock.json`** | Ídem para librerías JavaScript (Vite).                                                                                                                 |
| **`vite.config.js`**                     | Qué archivos de `resources/` compila Vite (`resources/css/app.css`, `resources/js/app.js`). Es lo que pide el `@vite(...)` de `layouts/app.blade.php`. |
| **`phpunit.xml`**                        | Configuración de los tests.                                                                                                                            |
| **`.gitignore` / `.gitattributes`**      | Qué NO se versiona (`vendor`, `node_modules`, `.env`, `storage`…) y reglas de fin de línea.                                                            |
| **`.editorconfig`**                      | Estilo de código común (indentación, saltos de línea) para todos los editores del equipo.                                                              |

---

## 3. Matriz de dependencias (quién depende de quién)

```
resources/views ──► (recibe datos de) Controllers ──► Services ──► Models ──► MySQL
       │                    ▲                            │
       │                    │                            │
       └─ @vite ─► public/build ◄─ vite.config.js ◄─ resources/css, js

routes/*.php ───► Controllers          config/*.php ◄── leído por TODOS     .env ──► config/
Controllers ──► Services ──► Models    Services ──► app/Exceptions
```

Regla de oro (**las flechas solo van hacia abajo**):

- Un **Controller** puede usar Services, nunca Models directamente para lógica de negocio (consultas simples de lectura, como los buscadores, son la excepción).
- Un **Service** NUNCA usa `Request` ni devuelve respuestas HTTP: recibe datos simples y devuelve arrays o lanza `VentaException`.
- Una **vista** NUNCA consulta la base: solo muestra lo que el controller le pasa o lo que el JS le pide a un endpoint.
- Un **Model** no contiene reglas de negocio, solo describe la tabla.

Así, si algo falla, se sabe en qué capa mirar:

| Síntoma                                | Mirar en                   |
| -------------------------------------- | -------------------------- |
| 404 / "Route not defined"              | `routes/`                  |
| 422 con campos mal enviados            | validación del Controller  |
| Número mal calculado, regla de negocio | `Services/`                |
| Error SQL / columna inexistente        | `Models/` y la base        |
| Pantalla mal armada                    | `resources/views`          |
| Error 500 sin explicación              | `storage/logs/laravel.log` |

---

## 4. Cómo sumar un módulo nuevo (receta)

Ejemplo: módulo **Cobranzas**.

1. `routes/cobranzas.php` + una línea `require __DIR__.'/cobranzas.php';` en `routes/web.php`
2. `app/Http/Controllers/Cobranzas/…Controller.php`
3. `app/Services/Cobranzas/…Service.php`
4. `app/Models/` → solo los modelos de tablas que todavía no existan (**los modelos se comparten entre módulos**; antes de crear uno, revisar si ya está).
5. `config/cobranzas.php` si hay valores configurables
6. `resources/views/cobranzas/…blade.php` (y JS en `resources/js/cobranzas/`)
7. `docs/COBRANZAS_BACKEND.md`

Nombres: Controllers y Services en **singular + sufijo** (`CarritoService`), rutas en minúscula (`ventas/carrito`), nombres de ruta con puntos (`ventas.carrito.mostrar`).

---

## 5. Estándar de comentarios (el que usamos en todo el módulo)

Cada archivo empieza con un bloque que responde siempre lo mismo:

```php
/**
 * NombreDeLaClase  —  una línea que dice QUÉ ES.
 *
 * ¿QUÉ HACE?     explicación breve, en palabras del negocio.
 * REGLAS         las reglas de negocio que implementa (si las hay).
 * ENDPOINTS      (solo controllers) método + URL + body + respuesta.
 * DEPENDE DE     qué clases/tablas/config necesita para funcionar.
 * LO USA         quién la llama (para saber qué se rompe si se cambia).
 * LIMITACIONES   supuestos o cosas que no cubre.
 */
```

Y cada método público lleva su comentario con parámetros y qué devuelve. Los comentarios explican el **por qué**, no repiten el código.

---

## 6. Guía rápida para el front

1. **Layout** (`resources/views/layouts/app.blade.php`): agregar en `<head>` el `<meta name="csrf-token" content="{{ csrf_token() }}">` y antes de `</body>` un `@stack('scripts')`.
2. **Vista** `ventas.blade.php`: `@extends('layouts.app')` y pasar el estado inicial:
    ```blade
    @push('scripts')
    <script>window.POS = @json(['carrito' => $carrito, 'config' => $config]);</script>
    @endpush
    ```
3. **JS** en `resources/js/ventas/` (compilado por Vite): una función `render(carrito)` que dibuja toda la pantalla y un helper `api(método, url, body)`.
4. Cada botón: llama a `api(...)` → recibe `carrito` → `render(carrito)`. **No se calcula nada en JS.**
5. Contratos exactos de cada endpoint: `documentacion-guia/VENTAS_BACKEND.md` sección 4.
6. Cómo probar sin front: con la pantalla `/ventas` abierta, usar la consola del navegador (F12) y ejecutar `fetch('/ventas/carrito',{headers:{Accept:'application/json'}}).then(r=>r.json()).then(console.log)`.
