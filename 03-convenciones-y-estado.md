# Convenciones de Equipo y Estado Actual — ERP Distribuidora

> Última actualización: 12/08/2026
> Responsable: Estefania Gianovich — Grupo 2

## Convenciones del equipo

**Patrón de capas:** ✅ Resuelto (ver `02-arquitectura-tecnica.md`): el equipo confirmó Laravel de punta a punta. "Módulo primero, capa después" (Controllers/Services/Repositories/Models por módulo) se mantiene, pero ahora dentro del scaffold real de Laravel, no como decisión temporal aislada. Confirmado en el Grupo 2 con el scaffold instalado en `feature/g2-sofia-laravel-scaffold`.

**Nomenclatura de tablas:** No está escrita como regla, pero se puede inferir un patrón consistente del diagrama de dbdiagram.io: nombres de tabla en singular y mayúsculas (CLIENTE, PRODUCTO, VENTA), campos en snake_case con prefijo `id_` para claves (id_cliente, id_producto), y campos de estado como string con valores tipo enum documentados en notas ('activo | inactivo'). Es un patrón observado, no un acuerdo explícito — confirmar como estándar antes de que cada grupo diverja.

**Nomenclatura de endpoints:** También inferido, no declarado: sustantivos en plural y minúscula (/clientes, /productos, /entregas), kebab-case para rutas compuestas (/aumentos-masivos, /historial-precios, /actualizar-estado), acciones específicas como sub-recurso (/entregas/{id}/registrar-firma en vez de un verbo suelto). Consistente entre los 4 grupos según lo revisado.

**Nomenclatura de clases:** ✅ Ya hay primera evidencia real (Grupo 2, tras instalar Laravel): namespace `App\{Módulo}\{Capa}\NombreClase` en PascalCase (ej. `App\ConciliacionBancaria\Controllers\ConciliacionController`), coherente con el autoload psr-4 `App\` → `src/` y con la estructura de carpetas ya documentada en `02-arquitectura-tecnica.md`. Sigue sin ser un acuerdo formal de los 4 grupos — confirmar antes de que cada uno programe con estilos distintos, pero ya no es un hueco total como antes.

**Documento de estándares acordado por escrito:** No existe todavía. Acuerdo verbal del equipo: se va a usar la sección Wiki de GitHub del repositorio como lugar para documentar convenciones a futuro. Sin fecha ni contenido cargado aún.

## Flujo de trabajo Git (referencia)

- `main` → código testeado y finalizado
- `develop` → integración de todos los grupos
- `develop-g1`, `develop-g2`, `develop-g3`, `develop-g4` → una por grupo
- `feature/gX-nombre-apellido` → una por integrante, nace de su `develop-gX`

## Criterio de Documentación para GitHub Issues (Regla Clave)
- La documentación debe enfocarse en **decisiones de alto nivel y reglas de negocio**, no en jerga técnica profunda.
- El contenido debe estar redactado de forma clara y funcional para ser volcado directamente como descripción o comentarios en las **Issues de GitHub**.
- Si un aspecto es extenso, se divide creando nuevas Issues para no saturar una sola.
- **Definición de "Integración"**: Código probado localmente, con pruebas unitarias e integración en navegador satisfactorias, subido (`git push`) en la rama correspondiente y preparado para mergear a `develop-g2`.

🚩 **Problema encontrado y resuelto puntualmente (Grupo 2):** la rama `sofi-grupo2` resultó tener un historial de commits completamente aislado — sin ningún commit ancestro en común con `develop-g2`/`main` (confirmado con `git merge-base`, que no devolvió resultado). Esto impide abrir Pull Requests normales desde esa rama (GitHub responde "entirely different commit histories"). Solución aplicada: crear una rama nueva (`feature/g2-sofia-laravel-scaffold`) partiendo del estado real de `develop-g2`, en vez de forzar un merge de historiales no relacionados. `sofi-grupo2` queda sin tocar. **Pendiente:** confirmar con el resto del equipo si esto le pasó a alguien más, y si conviene revisar cómo se están creando las ramas nuevas para evitar que se repita (posible causa: rama creada sin partir de un checkout de `origin/main` o `origin/develop-gX`, sino con historial propio desde cero).

## Estado actual del proyecto (al retomar)

| Módulo | Grupo | Estado | Notas |
|---|---|---|---|
| Entregas / Facturación / Caja / Cobros / Conciliación Bancaria / Rendiciones | 2 (nosotros) | ✅ Laravel instalado y funcionando (scaffold completo, migrado de PDO plano) — PR abierto hacia `develop-g2`. Código de negocio de cada módulo (Models/Services/Repositories) aún no escrito | Sin asignación fija por integrante, se define sobre la marcha |
| Clientes / Login / Reportes / Catálogo | 1 | Diseño — épicas y endpoints definidos con detalle | Reportes sin prioridad definida aún |
| Productos / Stock / Proveedores / Compras / Pedidos de compra | 3 | Diseño — endpoints muy detallados, el grupo con más granularidad hasta ahora | — |
| Ventas / Saldos / Promociones | 4 | Diseño — endpoints definidos | — |

En general: el resto del proyecto sigue en etapa de diseño (contratos de API y modelo de datos), sin base de datos física creada aún. El Grupo 2 es, hasta ahora, el único con código de infraestructura real corriendo (Laravel instalado, sin lógica de negocio todavía).

**Dependencias reales del Grupo 2:** el grupo depende principalmente del módulo de **Ventas (G4)** y en general de los módulos de **G4** (Saldos, Promociones) — no de los 4 grupos por igual. La dependencia con G1 (Clientes/Auth) y G3 (Stock) existe pero es menor. Esto permite avanzar en paralelo sin bloquearse mientras G4 progresa, usando mocks solo puntualmente para probar algo de G1/G3 si hiciera falta.

**Criterio de "Done":** una épica se considera terminada cuando su código está testeado, integrado a GitHub y documentado. Esto no impide avanzar en paralelo: se puede programar y probar un módulo mockeando datos de otro módulo que todavía no esté listo, sin necesidad de esperar una prioridad/orden estricto entre épicas de distintos grupos.

## Plazos

**Fecha objetivo:** fin de octubre 2026 — plazo esperado para tener programado (código testeado, integrado a GitHub y documentado, según el criterio de "Done" ya definido arriba) lo cubierto por las historias de usuario existentes hasta ahora (ver `05-historias-usuario.md`). Dato surgido de la revisión de historias de usuario, no estaba documentado en ningún lugar anterior.

## Cambios respecto al plan original

**Confirmado — stack de backend (Grupo 2):** se resolvió la bandera roja pendiente sobre Laravel vs. PDO plano. El equipo decidió **Laravel de punta a punta**. En consecuencia:
- Se instaló el scaffold completo de Laravel en el repositorio (rama `feature/g2-sofia-laravel-scaffold`, PR hacia `develop-g2`).
- Se eliminó el código PDO plano que ya se había empezado a escribir (`config/database.php` manual, `public/index.php` con router propio, `src/Shared/Http/Router.php` y `src/Shared/Auth/SesionMiddleware.php` — estos dos últimos sin implementación real, solo `TODO`).
- Quedan pendientes de limpieza: `src/Shared/Database/Conexion.php` (sin uso tras la migración) y el namespace de `src/Shared/Http/Response.php` (hoy `Shared\Http`, debería ser `App\Shared\Http`). Ver detalle completo en `02-arquitectura-tecnica.md`.

## Notas y dudas pendientes

- **Wiki de GitHub vs. documentación en `.md` del Project de Claude:** no es una contradicción técnica, pero sí una duda operativa. ¿La Wiki de GitHub va a ser la fuente "oficial" para todo el equipo, y estos `.md` quedan como documentación personal de repaso? ¿O se van a subir estos mismos documentos a la Wiki cuando estén más maduros? Definir para no terminar manteniendo dos fuentes de verdad desactualizadas entre sí.
- **Ramas con historial aislado:** confirmar con el equipo si el problema de `sofi-grupo2` (ver "Flujo de trabajo Git" arriba) es un caso único o si conviene revisar el resto de las ramas activas por las dudas.
- (Espacio para sumar dudas nuevas a medida que surjan.)
