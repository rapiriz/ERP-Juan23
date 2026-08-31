# Prototipo Frontend — ERP Distribuidora Pigüé

> Última actualización: 12/08/2026
> Responsable: Estefania Gianovich — Grupo 2
> Fuente: repositorio `PrototipoPPS3`, compartido por compañeros del equipo (React + TypeScript + Vite + Tailwind, armado con Google AI Studio)

## Propósito de este documento

Este prototipo NO es fiel a los datos ni a la lógica de negocio real del proyecto (ver `01` y `02` para eso). Su valor es puramente de **referencia visual y de maquetación**: estilo, paleta, componentes, y qué pantallas se imaginaron para cada módulo. No usar como fuente para modelo de datos, reglas de negocio ni arquitectura backend — el prototipo guarda todo en `localStorage` del navegador, no tiene backend real conectado.

**Nota importante sobre portabilidad:** el prototipo está en React + Tailwind; el backend real del proyecto es PHP + PDO plano con HTML/CSS. La paleta de colores, tipografía, efectos visuales (sombras, bordes) y criterios de accesibilidad SÍ se pueden replicar exactamente, porque son reglas de CSS puro. Los componentes reutilizables de React (botones, cards, etc.) NO se heredan automáticamente — hay que recrearlos a mano como fragmentos PHP o clases CSS reutilizables, usando este documento como referencia de a qué deben parecerse.

## Sistema de diseño

**Estilo general:** Claymorfismo minimalista. Efecto visual de "relieve" tipo plastilina/arcilla: cards con bordes muy redondeados (~28px) y sombras dobles (una clara hacia adentro simulando luz, otra oscura sutil) que dan sensación de profundidad suave, sin ser un diseño duro o plano.

**Paleta de colores:**
- Fondo general: celeste pastel muy suave — `#EBF4FC`
- Sidebar: degradado azul oscuro — `#1E40AF` → `#1D4ED8` → `#1E3A8A` (135deg)
- Botón primario: azul — `#0D6EFD` (hover: `#0b5ed7`)
- Texto principal: gris oscuro azulado, alto contraste — `#1E293B`
- Cards: blanco semitransparente con blur — `rgba(255, 255, 255, 0.85)` + `backdrop-filter: blur(16px)`

**Tipografía:** Inter (Google Fonts), pesos 400/500/600/700/800. Tamaño base **1.125rem** (18px) — más grande que el estándar web (16px), decisión deliberada de legibilidad.

**Accesibilidad — criterio de diseño explícito, no solo estético:**
El CSS trae comentarios directos como *"Foco ultra-visible para accesibilidad de adultos mayores"* y *"Asegurar targets interactivos de mínimo 48px"*. El footer del sistema dice literalmente "Modo Accesible Activado". Esto confirma que el equipo asumió usuarios con poca familiaridad tecnológica o adultos mayores — coherente con el perfil de Diego relevado en la entrevista (resistencia tecnológica, prioriza lo presencial). Reglas concretas a mantener en cualquier pantalla nueva:
- Todo elemento interactivo (input, botón, select) con mínimo 48px de alto.
- Foco de teclado ultra-visible: outline de 4px, color `#0D6EFD`, con offset de 2px.
- Contraste alto en todos los textos.

**Clases reutilizables identificadas (nombres tal cual en el CSS del prototipo):**
- `.clay-card` / `.clay-card-hover` — tarjetas base con efecto claymorfismo
- `.clay-sidebar` — barra lateral con degradado azul
- `.clay-btn-primary` — botón de acción principal
- `.clay-btn-secondary` — botón secundario/alternativo
- `.clay-input` — campos de formulario con estilo consistente

**Stack técnico del prototipo:** React + TypeScript, Tailwind CSS, Vite. Persistencia en `localStorage` (sin backend real).

## Pantallas identificadas

| Pantalla (archivo) | Propósito deducido |
|---|---|
| `LoginView` | Inicio de sesión |
| `RecoveryView` | Recuperación de contraseña |
| `DashboardLayout` | Layout general — sidebar + navegación, envuelve todas las vistas internas |
| `DashboardMainView` | Pantalla principal/resumen tras loguearse |
| `UserProfileView` | Perfil del usuario logueado |
| `UsersManagementView` | ABM de usuarios (rol admin) |
| `RolesManagementView` | Matriz de permisos por rol |
| `SalesView` | Módulo de Ventas — vista contenedora |
| `SalesView/SalesPOS` | Punto de venta |
| `SalesView/SalesCurrentAccount` | Cuenta corriente dentro de Ventas |
| `SalesView/SalesProductAdmin` | Administración de productos desde Ventas |
| `SalesView/SalesPromotions` | Gestión de promociones |
| `InventoryView` | Stock/inventario |
| `CustomersView` | Clientes (y aparentemente también proveedores/compras, según referencias en `App.tsx`) |
| `LogisticsView` | **Módulo de Entregas (nuestro grupo)** — incluye vehículos y rutas de reparto |
| `FinancesView` | **Módulos de Caja/Cobros/Facturación (nuestro grupo)** — movimientos de caja, cuentas corrientes, facturación electrónica |

**Dato relevante para nuestro grupo:** en este prototipo, `LogisticsView` y `FinancesView` agrupan varios de nuestros módulos en dos pantallas grandes (en vez de una pantalla separada por cada uno de los 6 módulos: Entregas, Facturación, Caja, Cobros, Conciliación, Rendiciones). No es una decisión que haya que copiar necesariamente, pero es una referencia de cómo alguien del equipo imaginó agrupar visualmente esta área.

## Navegación y arquitectura de la app (prototipo)

- Un componente raíz (`App.tsx`) controla qué vista se muestra mediante un estado tipo switch (`currentView`: LOGIN, DASHBOARD, SALES, INVENTORY, CUSTOMERS, LOGISTICS, FINANCES, etc.)
- Sin sesión iniciada: se muestra `LoginView` o `RecoveryView` solas, sin sidebar.
- Con sesión iniciada: todo se envuelve en `DashboardLayout` (sidebar + navegación), y adentro se renderiza la vista activa según el rol.
- El acceso a cada vista está condicionado por una matriz de permisos por rol (`RolePermissions`) — mismo código de pantallas para todos los usuarios, pero la navegación/sidebar se adapta según el rol logueado.
- Roles usados en el prototipo (no coincide exactamente con los roles confirmados en la base de datos — ver bandera roja abajo): Administrador, Repartidor, Preventista, Administrativo.

## Banderas rojas / diferencias con lo ya documentado en `01` y `02`

- **Roles:** el prototipo usa Administrador/Repartidor/Preventista/Administrativo. La base de datos real (confirmado en `02`) usa admin/vendedor/repartidor. No coinciden exactamente (¿Preventista = vendedor? ¿de dónde sale Administrativo?) — confirmar con el equipo si esto es relevante para Login (Grupo 1).
- **Vehículos y rutas de entrega:** el prototipo modela `Vehicle` (patente, modelo, conductor, capacidad, estado) y `DeliveryRoute` (agrupa varios pedidos en una ruta, con vehículo y zona asignados) — conceptos que NO existen en el modelo de base de datos actual (`02-arquitectura-tecnica.md`), donde `ENTREGA` solo vincula un repartidor a una entrega individual, sin noción de vehículo ni de ruta que agrupe varias entregas. Relevante para nuestro módulo de Entregas — vale la pena evaluar si conviene incorporarlo al modelo real.
- **Facturación electrónica (AFIP):** el prototipo trae campos de integración fiscal real (CAE, punto de venta, tipo de comprobante A/B/C) que no aparecen en ningún documento previo. Confirma que el sistema final probablemente necesite integrarse con AFIP — dato grande para nuestro módulo de Facturación, hoy sin tablas ni endpoints definidos.
- **Nombre "Distribuidora Pigüé":** es un placeholder del equipo de prototipo, no el nombre comercial real de la distribuidora del cliente (que se desconoce). Pigüé es la localidad donde opera.

## Notas y dudas pendientes

- Confirmar si el criterio de accesibilidad (targets de 48px, foco ultra-visible, tipografía grande) es un acuerdo real del equipo o una decisión unilateral de quien armó el prototipo — de ser un acuerdo real, aplicaría a cualquier pantalla que programemos en nuestros módulos.
- Confirmar si se espera que Entregas incorpore el concepto de Vehículo/Ruta que trae el prototipo, o si el modelo actual (repartidor + entrega individual) es el definitivo.
- (Espacio para sumar dudas nuevas a medida que surjan.)
