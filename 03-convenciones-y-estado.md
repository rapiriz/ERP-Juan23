# Convenciones de Equipo y Estado Actual — ERP Distribuidora

> Última actualización: 12/08/2026
> Responsable: Estefania Gianovich — Grupo 2

## Convenciones del equipo

**Patrón de capas:** No hay confirmación formal del equipo. Se definió "módulo primero, capa después" (Controllers/Services/Repositories/Models por módulo) como decisión temporal propia — ver bandera roja en `02-arquitectura-tecnica.md`.

**Nomenclatura de tablas:** No está escrita como regla, pero se puede inferir un patrón consistente del diagrama de dbdiagram.io: nombres de tabla en singular y mayúsculas (CLIENTE, PRODUCTO, VENTA), campos en snake_case con prefijo `id_` para claves (id_cliente, id_producto), y campos de estado como string con valores tipo enum documentados en notas ('activo | inactivo'). Es un patrón observado, no un acuerdo explícito — confirmar como estándar antes de que cada grupo diverja.

**Nomenclatura de endpoints:** También inferido, no declarado: sustantivos en plural y minúscula (/clientes, /productos, /entregas), kebab-case para rutas compuestas (/aumentos-masivos, /historial-precios, /actualizar-estado), acciones específicas como sub-recurso (/entregas/{id}/registrar-firma en vez de un verbo suelto). Consistente entre los 4 grupos según lo revisado.

**Nomenclatura de clases:** Sin ningún tipo de evidencia ni acuerdo — no hay código real todavía, solo diseño de API y BD. Hueco total, no discutido en absoluto por el equipo.

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
- **Definición de "Integración"**: Código probado localmente, con pruebas satisfactorias, subido (`git push`) en la rama correspondiente y preparado para mergear a `develop-g2`.

## Estado actual del proyecto (al retomar)

| Módulo | Grupo | Estado | Notas |
|---|---|---|---|
| Entregas / Facturación / Caja / Cobros / Conciliación Bancaria / Rendiciones | 2 (nosotros) | Diseño — endpoints y BD parcialmente definidos, sin código ni BD real | Sin asignación fija por integrante, se define sobre la marcha |
| Clientes / Login / Reportes / Catálogo | 1 | Diseño — épicas y endpoints definidos con detalle | Reportes sin prioridad definida aún |
| Productos / Stock / Proveedores / Compras / Pedidos de compra | 3 | Diseño — endpoints muy detallados, el grupo con más granularidad hasta ahora | — |
| Ventas / Saldos / Promociones | 4 | Diseño — endpoints definidos | — |

En general: todo el proyecto está en etapa de diseño (contratos de API y modelo de datos), sin base de datos física creada aún y sin código implementado en ningún módulo, según toda la evidencia recolectada hasta ahora.

**Dependencias reales del Grupo 2:** el grupo depende principalmente del módulo de **Ventas (G4)** y en general de los módulos de **G4** (Saldos, Promociones) — no de los 4 grupos por igual. La dependencia con G1 (Clientes/Auth) y G3 (Stock) existe pero es menor. Esto permite avanzar en paralelo sin bloquearse mientras G4 progresa, usando mocks solo puntualmente para probar algo de G1/G3 si hiciera falta.

**Criterio de "Done":** una épica se considera terminada cuando su código está testeado, integrado a GitHub y documentado. Esto no impide avanzar en paralelo: se puede programar y probar un módulo mockeando datos de otro módulo que todavía no esté listo, sin necesidad de esperar una prioridad/orden estricto entre épicas de distintos grupos.

## Plazos

**Fecha objetivo:** fin de octubre 2026 — plazo esperado para tener programado (código testeado, integrado a GitHub y documentado, según el criterio de "Done" ya definido arriba) lo cubierto por las historias de usuario existentes hasta ahora (ver `05-historias-usuario.md`). Dato surgido de la revisión de historias de usuario, no estaba documentado en ningún lugar anterior.

## Cambios respecto al plan original

Sin cambios detectados hasta el momento, ni mencionados explícitamente en ningún documento revisado. Es esperable que surjan cambios una vez que se retome la cursada — este espacio queda para registrarlos cuando aparezcan.

## Notas y dudas pendientes

- **Nomenclatura de clases:** no discutida en absoluto por el equipo — definir antes de que cada grupo empiece a programar con estilos distintos.
- **Wiki de GitHub vs. documentación en `.md` del Project de Claude:** no es una contradicción técnica, pero sí una duda operativa. ¿La Wiki de GitHub va a ser la fuente "oficial" para todo el equipo, y estos `.md` quedan como documentación personal de repaso? ¿O se van a subir estos mismos documentos a la Wiki cuando estén más maduros? Definir para no terminar manteniendo dos fuentes de verdad desactualizadas entre sí.
- (Espacio para sumar dudas nuevas a medida que surjan.)
