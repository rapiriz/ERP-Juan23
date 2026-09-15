# ERP Juan23 — S11 + PC07

Paquete con la estructura de carpetas de Laravel ya armada, lista para copiar
dentro de tu proyecto y abrir en VS Code.

## Cómo instalarlo

1. Descomprimí el zip.
2. Copiá cada carpeta (`app`, `database`, `resources`, `public`) dentro de la
   raíz de tu proyecto Laravel, fusionando con las que ya existen (no
   reemplaces tu `app/Providers` completo, solo agregá los archivos nuevos).
3. Estos archivos son **snippets**, no reemplazan tus archivos existentes:
   - `routes/api_v1_snippet.php` → copiá el bloque `Route::middleware(...)`
     dentro de tu `routes/api.php` real.
   - `routes/web_snippet.php` → copiá el bloque `Route::middleware('auth')`
     dentro de tu `routes/web.php` real (o fusioná con tu grupo `auth`
     existente).
   - `config/app_providers_snippet.php` → agregá esas 2 líneas al array
     `providers` de tu `config/app.php` real.
4. Corré las migraciones:
   ```bash
   php artisan migrate
   ```
5. Asegurate de tener los modelos `Producto`, `Proveedor` y `Stock` (no
   incluidos acá, se asumen ya existentes en tu proyecto) con las relaciones
   `detalleVentas()`, `proveedores()` y `stock()` usadas en
   `GeneradorSugerenciasReposicionService`.
6. Publicá el asset de Chart.js ya está enlazado por CDN en
   `dashboard-reposicion.blade.php`, no requiere instalación local.

## Novedades de esta tanda (Web CRUD + Dashboard)

- **CRUD de lotes (web):** `app/Http/Controllers/ProductoLoteController.php`
  con vistas en `resources/views/lotes/` (`index`, `create`, `edit`). Rutas
  con nombre `lotes.index`, `lotes.create`, `lotes.store`, `lotes.edit`,
  `lotes.update`, `lotes.destroy`.
- **Dashboard con gráficos:** `resources/views/dashboard-reposicion.blade.php`
  + `public/js/dashboard-reposicion.js`, usando Chart.js vía CDN. Consume 4
  endpoints nuevos bajo `/api/v1/dashboard-reposicion/*`.
- **Acciones en lote:** checkboxes en la tabla de sugerencias + botón
  "Procesar Seleccionadas", que pega a `POST /api/v1/sugerencias-reposicion/procesar-lote`.
- **Rechazo de sugerencias:** `POST /api/v1/sugerencias-reposicion/{id}/rechazar`,
  disponible también desde el modal de detalle.
- **Modal de detalle funcional:** click en el nombre del producto abre el
  modal con los datos reales de esa sugerencia (antes era estático).
- **Filtros avanzados de vencimientos:** producto y ubicación, ya conectados
  de punta a punta (vista → JS → controller → service → repository).

No se generó un "Web Controller" separado para sugerencias de compra: las
sugerencias no se crean a mano (se generan automáticamente), así que las
acciones de procesar/rechazar/procesar-en-lote se resolvieron ampliando el
controller de API que ya existía y consumiendo esos mismos endpoints desde
el JS de la vista — evita duplicar lógica de autorización entre una versión
web y una de API para las mismas acciones.

## Estructura

```
app/
  Models/ProductoLote.php
  Models/SugerenciaCompra.php
  Repositories/ProductoLoteRepository.php
  Repositories/SugerenciaCompraRepository.php
  Services/ConsultaVencimientosService.php
  Services/GeneradorSugerenciasReposicionService.php
  Http/Controllers/Api/V1/ConsultaVencimientosController.php
  Http/Controllers/Api/V1/SugerenciasReposicionController.php
  Providers/RepositoryServiceProvider.php
  Providers/VencimientosServiceProvider.php
database/migrations/
  2026_09_15_000001_create_producto_lotes_table.php
  2026_09_15_000002_create_sugerencias_compra_table.php
routes/
  api_v1_snippet.php   (snippet a fusionar)
config/
  app_providers_snippet.php   (snippet a fusionar)
resources/views/
  sugerencias-reposicion.blade.php
  vencimientos.blade.php
public/
  css/claymorfismo.css
  js/sugerencias-reposicion.js
  js/vencimientos.js
```
