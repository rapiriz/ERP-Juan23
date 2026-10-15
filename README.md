# ERP Distribuidora Pigüé — Rama `sofi-grupo2`

Sistema de administración de ventas para una distribuidora de alimentos y bebidas, desarrollado como proyecto de la materia Prácticas profesionalizantes 3. Este mismo se divide en 4 grupos, cada uno responsable de un conjunto de módulos que comparten la misma base de datos, el mismo repositorio y las mismas convenciones de arquitectura.

Esta rama corresponde al trabajo individual de **Sofía** dentro del **Grupo 2 — Logística y Análisis**.

## Contexto del proyecto

El sistema es un ERP modular pensado para una distribuidora, que opera bajo un modelo **B2B por rutas y relación comercial directa** — no un e-commerce de carrito tipo B2C. El 90% de las ventas son a comercios mayoristas, organizadas por zonas con rutas que se repiten cada ~15 días.

### División de grupos

| Grupo | Tema | Módulos |
|---|---|---|
| 1 | Núcleo Comercial | Clientes, Catálogo, Login/Roles, Reportes |
| **2** | **Logística y Análisis** | **Entregas, Cobros, Rendiciones, Caja, Conciliación Bancaria, Facturación** |
| 3 | Inventario y Compras | Productos, Stock, Proveedores, Compras |
| 4 | Ventas y Cobranzas | Ventas, Saldos, Promociones |

Todos los grupos trabajan sobre el mismo modelo de base de datos y el mismo repositorio, integrando su trabajo a través de ramas `develop-gX` por grupo.

## Stack técnico

- **Backend:** Laravel 12 (PHP 8.2), arquitectura organizada en capas por módulo: `Model → Repository → Service → Controller`.
- **Base de datos:** MySQL. Convención de nombres heredada del modelo entidad-relación original: tablas en `MAYÚSCULAS` singular, claves con prefijo `id_`.
- **Autoload:** PSR-4, con el namespace `App\` mapeado tanto a `app/` (requerido internamente por Laravel) como a `src/` (donde vive el código real de cada módulo).
- **Frontend:** vistas Blade server-side, con estilo propio **Claymorfismo** (paleta celeste/azul, cards con sombra doble y bordes redondeados, tipografía Inter). CSS plano en archivos separados — sin estilos inline en el HTML.
- **Autenticación:** Laravel Sanctum para los endpoints de API que lo requieren (en desarrollo — el login definitivo lo entrega el Grupo 1).

## Alcance de esta rama

Esta rama contiene el desarrollo de los siguientes módulos del Grupo 2:

### Conciliación Bancaria
- Gestión de períodos de conciliación (apertura, cierre).
- Motor de **conciliación automática**: cruza movimientos bancarios contra cobros, movimientos de caja, ajustes bancarios y cheques, con las siguientes reglas de negocio:
  - Margen de fecha: hasta 48hs de diferencia.
  - Coincidencia de monto exacta, sin tolerancia.
  - Relación siempre 1 a 1 (un movimiento ↔ un único origen).
  - Ante ambigüedad (más de un candidato posible), el movimiento queda pendiente de revisión manual.
- Conciliación manual de movimientos puntuales.
- Gestión de cheques (alta, marcar depositado, marcar rechazado — con reversión automática de la conciliación si el cheque rechazado ya estaba conciliado).
- API REST (`/api/v1/conciliacion/...`, `/api/v1/cheques/...`) y vistas web (`/conciliacion/...`).

### Caja
- Apertura y cierre de caja diaria por usuario (regla: una caja por usuario por día, cada usuario gestiona únicamente la propia).
- Registro de movimientos manuales (ingreso, egreso, extracción) y automáticos (cobro).
- Historial de cierres, con cálculo de diferencia entre lo esperado y lo contado.
- API REST protegida con Sanctum (`/api/v1/caja/...`) y vistas web (`/caja/...`).

### Placeholders de otros módulos
Para poder resolver relaciones (claves foráneas) desde Conciliación Bancaria, existen versiones mínimas de dos Models que pertenecen a otros compañeros del grupo:
- `App\Cobros\Models\Cobro` — el diseño completo de Cobros no es responsabilidad de esta rama.
- `App\Caja\Models\CajaMovimiento` — completado como parte del desarrollo real de Caja en esta rama.

## Estructura de carpetas relevante

```
src/
  ConciliacionBancaria/
    Models/          → PeriodoConciliacion, MovimientoBancario, AjusteBancario, Cheque, ConciliacionDetalle
    Repositories/     → ConciliacionRepository
    Services/         → ConciliacionService
    Controllers/      → ConciliacionController (API), ConciliacionWebController (vistas), ChequeController (API)
  Caja/
    Models/           → Caja, CajaMovimiento
    Repositories/      → CajaRepository
    Services/          → CajaService
    Controllers/       → CajaController (API), CajaWebController (vistas)
  Cobros/
    Models/           → Cobro (placeholder mínimo)
  Shared/
    Http/             → Response (formato de respuesta JSON estándar), Controller base

resources/views/
  layouts/app.blade.php      → layout compartido (sidebar + estilo Claymorfismo)
  conciliacion/              → listado, alta, detalle de períodos
  caja/                      → apertura, día actual, historial de cierres, detalle de cierre

public/css/
  clay-conciliacion.css      → estilos compartidos entre todos los módulos con vista web

database/migrations/         → tablas propias: PERIODO_CONCILIACION, MOVIMIENTO_BANCARIO,
                                AJUSTE_BANCARIO, CHEQUE, CONCILIACION_DETALLE, COBRO, CAJA_MOVIMIENTO
```

## Cómo levantar el proyecto

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configurar credenciales de MySQL en .env (DB_DATABASE, DB_USERNAME, DB_PASSWORD)
php artisan migrate
php artisan serve
```

Vistas disponibles una vez levantado:
- `http://127.0.0.1:8000/conciliacion` — Conciliación Bancaria
- `http://127.0.0.1:8000/caja` — Caja

> **Nota:** el login real todavía no está integrado a las vistas web. Ambos Web Controllers usan un `id_usuario` fijo de forma provisoria (marcado con `TODO` en el código), a reemplazar cuando el módulo de Login (Grupo 1) esté disponible.

## Decisiones de arquitectura documentadas

- **Manejo de errores sin excepciones propias:** los Services devuelven `array` (éxito), `null` (registro no encontrado) o `['error' => true, 'mensaje' => '...']` (regla de negocio violada), en vez de lanzar excepciones. Elegido por simplicidad, dado el tamaño del equipo (~19 personas) y la falta de un patrón de excepciones ya acordado.
- **Un Controller por módulo**, no uno por tabla — se mantiene alineado a los endpoints ya documentados como contrato de API.
- **Repository sin interfaz de PHP** (clase concreta directa) — no es requisito de la cátedra, se prioriza simplicidad.
- **CSS plano, no Tailwind** — el scaffold de Laravel trae Tailwind v4 preconfigurado en `resources/css/app.css`, pero el pipeline de Vite nunca se terminó de instalar en el proyecto. Se optó por CSS plano en archivos separados (cumpliendo igualmente la exigencia de la cátedra de no usar estilos inline).

# ERP Juan 23 — Grupo 2

Sistema ERP Distribuidora Juan XXIII — Módulos del Grupo 2: **Entregas (Logística), Facturación, Caja, Cobros, Conciliación Bancaria y Rendiciones**.

---

## 👥 Integrantes y Responsabilidades

* **Estefanía Gianovich:** Entregas (Logística y Despacho) y Facturación
* **Nicolás:** Rendiciones y Cobros
* **Tomás:** Cobros y Caja
* **Sofía:** Conciliación Bancaria y Arquitectura Laravel

---

## 🏗️ Arquitectura del Sistema

* **Monolito Modular** en PHP / Laravel bajo estándar PSR-4 (`App\` $\rightarrow$ `src/`).
* **Base de Datos:** MySQL compartida (`erp_distribuidora`).
* **Enrutamiento:** API REST versionada (`/api/v1/...`).

---

## 🚀 Cómo Ejecutar el Proyecto Localmente

1. Iniciar el servidor local:
   ```bash
   php -c php.ini -S localhost:8000 -t public
   ```
2. **Paneles Web de Demostración:**
   * Logística y Entregas: [http://localhost:8000/entregas.html](http://localhost:8000/entregas.html)
   * Rendiciones de Reparto: [http://localhost:8000/rendiciones.php](http://localhost:8000/rendiciones.php)

---

## 🧪 Pruebas Automatizadas

```bash
php -c php.ini tests/EntregasTest.php
```
