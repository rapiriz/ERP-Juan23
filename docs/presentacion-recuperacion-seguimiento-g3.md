# Presentación de Recuperación: Seguimiento y Entrega del Proyecto (Grupo 3)
## Distribuidora PyB — ERP Juan23

* **Materia:** Práctica Profesionalizante 3 (PP3) / Proyecto Final
* **Grupo:** Grupo 3 (Módulo Productos, Stock, Proveedores y Compras)
* **Integrantes que presentan:**
  - Santiago Gomiz
  - Tomás Giraudo
  - Francisco Calvo
* **Líderes de Asistencia:** Darío Tarnoski, Gino Martellini
* **Estructura:** 14 Diapositivas (Límite: entre 10 y 15 diapositivas)

---

## Índice de Diapositivas y Distribución de Roles

| # | Diapositiva | Tema del Requerimiento Cátedra | Expositor Sugerido |
| :-: | :--- | :--- | :--- |
| **01** | Carátula y Presentación del Equipo | Tema 1: Formación del equipo | Tomás Giraudo |
| **02** | Qué problema resuelve el ERP y el Grupo 3 | Tema 10: Funcionalidades y problema | Francisco Calvo |
| **03** | Organización del Equipo y Roles | Tema 1 y 6: Organización y tareas | Tomás Giraudo |
| **04** | Canales y Metodología de Comunicación | Tema 2: Canales de comunicación | Francisco Calvo |
| **05** | Stack Tecnológico y Herramientas | Tema 9: Lenguajes y tecnologías | Santiago Gomiz |
| **06** | Arquitectura del Sistema (Monolito Modular) | Tema 3: Arquitectura | Santiago Gomiz |
| **07** | Diseño de Base de Datos y Modelo Kardex | Tema 8: Base de datos | Santiago Gomiz |
| **08** | Gestión y Flujo de Trabajo en Git | Temas 4 y 5: Gestión y tareas en Git | Tomás Giraudo |
| **09** | Integración Inter-Grupos y Contratos de API | Tema 7: Integración con G1, G2 y G4 | Santiago Gomiz |
| **10** | Funcionalidades Principales Implementadas | Tema 10: Funcionalidades en detalle | Francisco Calvo |
| **11** | Registro de Trabajo Individual: Santiago Gomiz | Registro de trabajo / Aportes | Santiago Gomiz |
| **12** | Registro de Trabajo Individual: Francisco Calvo | Registro de trabajo / Aportes | Francisco Calvo |
| **13** | Registro de Trabajo Individual: Tomás Giraudo | Registro de trabajo / Aportes | Tomás Giraudo |
| **14** | Métricas del Proyecto, Horas y Conclusiones | Registro de trabajo / Cierre | Los 3 integrantes |

---

## Diapositiva 01: Carátula y Presentación
* **Título:** Sistema de Gestión ERP Juan23 — Módulo Distribuidora PyB
* **Subtítulo:** Recuperación de Seguimiento y Entrega del Proyecto
* **Integrantes Expositores:** Francisco Calvo, Tomás Giraudo, Santiago Gomiz
* **Grupo:** Grupo 3 (Productos, Stock, Inventario, Proveedores y Compras)
* **Contenido Visual:**
  - Logo institucional / ERP Juan23.
  - Identificación de los 3 integrantes expositores.
  - Nombre del cliente simulado: *Distribuidora PyB (Mayorista de Alimentos y Bebidas)*.
* **Guión / Speech (Tomás Giraudo):**
  > "Buenas tardes profesores. Somos Tomás Giraudo, Francisco Calvo y Santiago Gomiz, integrantes del Grupo 3 del ERP Juan23. Hoy vamos a presentar el informe integral de seguimiento, organización del equipo, arquitectura técnica y las soluciones que desarrollamos para resolver la operativa comercial de la distribuidora PyB."

---

## Diapositiva 02: El Problema de Negocio y Nuestra Solución (Tema 10)
* **Título:** Problemática de la Distribuidora y Solución ERP
* **Puntos Clave:**
  - **El Problema Real:**
    - Descontrol de stock físico vs. teórico por ventas sin validación previa.
    - Pérdidas por mercadería vencida (falta de trazabilidad por lote).
    - Desfase de precios ante inflación y falta de actualización de cotizaciones de proveedores.
    - Dificultad para coordinar compras con la demanda real.
  - **La Solución del Grupo 3:**
    - Catálogo centralizado con cálculo dinámico de costo de reposición y margen (markup).
    - Kardex inmutable con control estricto de existencias y regla FEFO (*First Expired, First Out*).
    - Módulo de compras con recepciones totales y parciales y cálculo exacto de deudas.
    - APIs estandarizadas en `/api/v1/` para alimentar en tiempo real a Ventas (G4), Clientes/Catálogo (G1) y Entregas/Caja (G2).
* **Guión / Speech (Francisco Calvo):**
  > "PyB es una distribuidora mayorista. Su mayor dolor era la falta de sincronización entre el stock del depósito y las ventas en mostrador o reparto. Desarrollamos un módulo que asegura que ningún producto se venda sin stock real, controla vencimientos por lote y recalcula automáticamente los precios de venta cuando el proveedor aumenta los costos."

---

## Diapositiva 03: Organización del Equipo y Distribución de Tareas (Temas 1 y 6)
* **Título:** Organización Interna y Roles en el Grupo 3
* **Puntos Clave:**
  - **Metodología Ágil:** Trabajo por Sprints (Sprint 1 a Sprint 5).
  - **División de Responsabilidades:**
    - **Santiago Gomiz:** Arquitectura de contratos de API inter-grupos, especificación OpenAPI 3.0, Diccionario de Datos, Matriz RTM, Manual de Usuario y ADRs.
    - **Francisco Calvo:** Pruebas funcionales de endpoints con Postman, validación de hardening de APIs, suites de pruebas de integración y verificación de respuestas HTTP.
    - **Tomás Giraudo:** Lógica de abastecimiento, sugerencias de reposición, seguimiento de órdenes de compra, pruebas de casos de uso y testing funcional de inventario.
  - **Coordinación con Líderes:** Asistencia general de Darío Tarnoski y Gino Martellini para consolidación de ramas principales y acuerdos inter-módulos.
* **Guión / Speech (Tomás Giraudo):**
  > "Nos organizamos bajo un esquema ágil por sprints. Dividimos el trabajo de acuerdo a fortalezas: contratos de integración y documentación técnica formal a cargo de Santiago; verificación y pruebas rigurosas de APIs en Postman con Francisco; y reglas de negocio de compras, reposición y stock a mi cargo."

---

## Diapositiva 04: Métodos y Canales de Comunicación (Tema 2)
* **Título:** Canales de Comunicación y Colaboración
* **Puntos Clave:**
  - **Canal Diario Operativo:** Grupo de WhatsApp del Grupo 3 y grupo general de líderes/recuperación para alertas de integración y bloqueos.
  - **Reuniones Sincrónicas:**
    - Días fijos de sincronización semanal y sesiones extraordinarias de cierre de sprint.
    - Demostraciones internas de código y revisión conjunta de PRs antes del merge a `develop`.
  - **Trazabilidad en Git:** Uso de issues, commits semánticos (`feat`, `fix`, `docs`, `test`) y comentarios en pull requests.
  - **Planilla de Control Compartida:** Registro diario de horas en Google Sheets con detalle de tarea, horarios y estado de cumplimiento.
* **Guión / Speech (Francisco Calvo):**
  > "Para evitar malos entendidos entre grupos, mantuvimos comunicación activa por WhatsApp y llamadas de Discord/Meet. Cada requerimiento o corrección se documentó con mensajes claros y se registró en la planilla oficial de horas para que cualquier miembro o docente pueda auditar qué se hizo, cuándo y cuánto tiempo insumió."

---

## Diapositiva 05: Lenguajes, Tecnologías y Entorno (Tema 9)
* **Título:** Stack Tecnológico y Entorno de Desarrollo
* **Puntos Clave:**
  - **Backend:** PHP 8.3 + Laravel 12 (última versión del framework).
  - **Base de Datos:** MySQL 8.0 (entorno productivo/XAMPP) y SQLite `:memory:` para ejecución ultra rápida de tests automatizados.
  - **Frontend:** Laravel Blade, JavaScript nativo modular y Tailwind CSS con diseño claymorfismo.
  - **Herramientas de Integración y Testing:**
    - Postman (colecciones `G3.postman_collection.json` y `ERP-Juan23-APIs.postman_collection.json`).
    - PHPUnit para tests de Feature y Unitarios.
    - OpenAPI 3.0 / Swagger YAML para especificación de contratos.
* **Guión / Speech (Santiago Gomiz):**
  > "Elegimos un stack moderno y robusto: PHP 8.3 sobre Laravel 12. Garantizamos que el sistema corra tanto en servidores convencionales con XAMPP como en tests automatizados desacoplados con SQLite en memoria, utilizando herramientas profesionales de la industria como Postman y OpenAPI 3.0."

---

## Diapositiva 06: Arquitectura del Sistema (Tema 3)
* **Título:** Arquitectura Monolito Modular Desacoplado
* **Puntos Clave:**
  - **Patrón:** Monolito Modular organizado por dominios en `app/Modules/`:
    - `Productos`: Catálogo, categorías, marcas, precios y costos.
    - `Stock`: Existencias, movimientos Kardex, lotes y conteos.
    - `Proveedores`: Padrón CUIT con Módulo 11, cotizaciones y plazos.
    - `Compras` y `PedidosDeCompra`: Órdenes, recepciones y pagos.
  - **Separación de Tráfico:**
    - Rutas Web (`routes/web.php`): Interfaz operativa interna de G3 con Eloquent y transacciones atómicas.
    - Rutas API (`routes/api.php`): Endpoints limpios de integración hacia G1, G2 y G4 con middleware de identidad desacoplado (`IdentidadUsuario`).
  - **ADRs (Architecture Decision Records):** 7 decisiones documentadas formalmente (`ADR-001` a `ADR-007`).
* **Guión / Speech (Santiago Gomiz):**
  > "Diseñamos un monolito modular. Esto nos da lo mejor de dos mundos: la simplicidad de despliegue de una sola base de código Laravel, pero con módulos completamente aislados por dominio. Separamos drásticamente las rutas web operativas internas de las APIs públicas consumidas por los demás grupos."

---

## Diapositiva 07: Diseño y Modelo de Base de Datos (Tema 8)
* **Título:** Modelo Relacional y Trazabilidad Inmutable
* **Puntos Clave:**
  - **Tablas Principales:** `producto`, `categoria`, `marca`, `movimiento_stock`, `lote`, `proveedor`, `producto_proveedor`, `orden_compra`, `compra`, `pago_proveedor`.
  - **Kardex Inmutable (`movimiento_stock`):**
    - Las existencias nunca se modifican directamente a mano; todo cambio genera un registro auditado (`stock_anterior`, `cantidad`, `stock_posterior`, motivo, lote y usuario).
  - **Trazabilidad de Precios y Costos:**
    - Adición de `costo` de reposición y `recargo` (% markup) en `producto`.
    - Historial con origen (`manual`, `aumento_masivo`, `costo_reposicion`).
  - **Migraciones no destructivas:** Scripts con soporte para tablas en minúsculas y mayúsculas, garantizando compatibilidad entre entornos Windows y Linux.
* **Guión / Speech (Santiago Gomiz):**
  > "El corazón del depósito es el Kardex inmutable. No permitimos 'hacks' en base de datos: cada venta de G4 o recepción de G3 crea un movimiento con foto del stock antes y después. Además, normalizamos la relación producto-proveedor para que las compras recalculen automáticamente el costo de reposición."

---

## Diapositiva 08: Registro y Gestión en Git (Temas 4 y 5)
* **Título:** Flujo de Trabajo en Git y Trazabilidad del Código
* **Puntos Clave:**
  - **Estrategia de Ramas:**
    - `main`: Código productivo validado.
    - `develop`: Rama general de integración de todos los grupos.
    - `develop-g3`: Rama oficial de trabajo del Grupo 3.
  - **Convención de Commits:** Commits semánticos (`feat:`, `fix:`, `docs:`, `test:`, `refactor:`) para facilitar auditorías y changelogs.
  - **Resolución de Conflictos:** Sincronización continua (`git fetch --all`, `git pull --ff-only`, rebases limpios) evitando 'merge bombs'.
  - **Evidencia:** Registro de decenas de commits propios demostrando trabajo progresivo a lo largo de los sprints.
* **Guión / Speech (Tomás Giraudo):**
  > "Adoptamos una disciplina estricta de Git. Trabajamos en develop-g3, sincronizando periódicamente con develop para no tener sorpresas al integrar. Aplicamos commits convencionales bien descriptivos, lo que permite auditar en el historial de Git exactamente qué aportó cada integrante."

---

## Diapositiva 09: Integración Inter-Grupos (Tema 7)
* **Título:** Integración con los Grupos 1, 2 y 4
* **Puntos Clave:**
  - **Doble Prefijo Soportado:** Soporte simultáneo en `/api/` y `/api/v1/` para no romper dependencias históricas de otros grupos.
  - **Contratos Específicos por Grupo:**
    - **Grupo 1 (Catálogo y Clientes):** `GET /api/v1/productos` (listado público filtrable) y `GET /api/v1/productos/{id}`.
    - **Grupo 4 (Ventas y Promociones):** `GET /api/productos/codigo/{sku}` (lector de barras), `GET /api/stock/{id}` y `POST /api/stock/movimientos` (descuento atómico en lote con rollback).
    - **Grupo 2 (Entregas, Remitos y Rendiciones):** Reingreso por devoluciones en reparto con reposición a lote original.
  - **Confidencialidad Comercial:** Las APIs externas ocultan deliberadamente costos de compra, recargos internos y proveedores (`ADR-007`).
* **Guión / Speech (Santiago Gomiz):**
  > "La integración inter-grupos fue el mayor desafío del proyecto. Lo resolvimos publicando un contrato OpenAPI 3.0 claro y una suite Postman con 16 pruebas automatizadas. Por ejemplo, cuando G4 confirma una venta con 5 artículos, invoca un solo endpoint en lote que descuenta el stock de forma atómica; si un ítem falla, no descuenta nada."

---

## Diapositiva 10: Funcionalidades Principales Desarrolladas (Tema 10)
* **Título:** Demostración de Funcionalidades Principales
* **Puntos Clave:**
  - **Gestión Avanzada de Precios:**
    - Aumento masivo porcentual por Proveedor, Categoría o Marca.
    - Impacto sobre precio de venta (sube margen) o sobre costo (mantiene markup).
    - Reglas de redondeo comercial y precios psicológicos (.90 y .99).
  - **Control de Inventario Físico:**
    - Conteo físico a ciegas (el operario cuenta sin ver el saldo teórico).
    - Semáforo de stock (Normal, Bajo, Sin Stock) y control FEFO de vencimientos.
  - **Gestión Integral de Compras:**
    - Órdenes de compra con cálculo automático de sugerencias por stock mínimo.
    - Recepciones flexibles por ítem y cierres parciales con recálculo de deuda y bloqueo por pagos en exceso.
* **Guión / Speech (Francisco Calvo):**
  > "El sistema no es un simple ABM. Implementamos lógica de negocio real para distribuidores: aumentos masivos con redondeo, conteos físicos a ciegas para evitar trampas en inventario, y cierres de compra inteligentes que no te dejan cerrar una compra si pagaste más de lo que el proveedor te entregó."

---

## Diapositiva 11: Registro Individual: Santiago Gomiz
* **Título:** Aportes Individuales — Santiago Gomiz
* **Puntos Clave:**
  - **Horas Dedicadas:** **35.07 horas registradas** en planilla oficial (Sprint 5).
  - **Responsabilidades Asumidas:**
    - Diseño y actualización de contratos de integración (`docs/contrato-api-g3.md` y `docs/openapi.yaml`).
    - Matriz de Trazabilidad de Requisitos (RTM) vinculando P01-P08, S01-S16, PV01-PV08 y C01-C09 con tests automatizados.
    - Redacción formal de Decisiones de Arquitectura (`ADR-001` a `ADR-007`).
    - Actualización del Diccionario de Datos con campos de costos, recargos y cierres parciales.
    - Elaboración del Manual de Usuario paso a paso y Guía de Resolución de Problemas (Troubleshooting).
  - **Evidencia en Git:** Commits semánticos en `develop-g3` (`489a397`, `99277dd`, `360c905`).
* **Guión / Speech (Santiago Gomiz):**
  > "Mi foco estuvo en garantizar la integridad técnica y documental del sistema. Llevo más de 35 horas registradas en este sprint. Diseñé los contratos de integración para que los otros tres grupos supieran exactamente cómo comunicarse con nosotros, redacté la matriz de trazabilidad con las pruebas automáticas y formalicé los ADRs que justifican cada decisión técnica."

---

## Diapositiva 12: Registro Individual: Francisco Calvo
* **Título:** Aportes Individuales — Francisco Calvo
* **Puntos Clave:**
  - **Horas Dedicadas:** **17.77 horas registradas** en planilla oficial.
  - **Responsabilidades Asumidas:**
    - Pruebas exhaustivas y hardening de APIs en Postman (`G3-068` y `G3-069`).
    - Verificación del comportamiento de rutas públicas y códigos de error HTTP (200, 201, 400, 404).
    - Comprobación de que las pruebas de movimientos de stock descontaran y reincorporaran unidades sin alterar el stock neto final.
    - Validación de integración de ventas de G4 y rendiciones de reparto de G2.
* **Guión / Speech (Francisco Calvo):**
  > "Mi responsabilidad fue probar y asegurar la robustez de las APIs. En Postman configuré y corrí las pruebas de integración servidor a servidor, verificando que los errores de stock insuficiente devuelvan 400, que los IDs inexistentes den 404 y que las ventas y devoluciones mantengan intactas las existencias netas al finalizar los ciclos de testeo."

---

## Diapositiva 13: Registro Individual: Tomás Giraudo
* **Título:** Aportes Individuales — Tomás Giraudo
* **Puntos Clave:**
  - **Horas Dedicadas:** **18.14 horas registradas** en planilla oficial.
  - **Responsabilidades Asumidas:**
    - Lógica de negocio de pedidos de compra y sugerencias de reposición por stock mínimo (`PC01` a `PC07`).
    - Pruebas funcionales de cancelación de órdenes de compra y cierres parciales.
    - Verificación de interfaz de inventario y filtros por proveedor en aumentos masivos.
    - Colaboración en el testeo de casos borde en base de datos.
* **Guión / Speech (Tomás Giraudo):**
  > "Me concentré en el circuito de abastecimiento y la experiencia operativa del encargado de compras. Participé en la validación de las sugerencias automáticas de compra según stock crítico, el comportamiento de las cancelaciones de órdenes y la verificación funcional de las pantallas de gestión."

---

## Diapositiva 14: Resumen de Métricas, Compromiso y Conclusiones
* **Título:** Métricas Globales del Grupo 3 y Conclusiones
* **Puntos Clave:**
  - **Dedicación Total del Grupo 3:** **159.47 horas hombre** registradas y auditables en el spreadsheet del equipo.
  - **Calidad de Software:**
    - Suites de pruebas automáticas (`G3IntegracionGruposApiTest`, `ProductoCostoRecargoTest`, `CorreccionesLogicaComprasDistribuidoraTest`).
    - 0 advertencias de contrato de API rotas con los demás grupos.
  - **Lecciones Aprendidas:**
    - La importancia de contratos tempranos (OpenAPI) para evitar bloqueos entre equipos.
    - El valor del Kardex inmutable para garantizar consistencia contable y auditoría.
    - La colaboración interdisciplinaria y el compromiso en la etapa final de entrega.
  - **¡Muchas gracias! Preguntas y Demostración en Vivo.**
* **Guión / Speech (Cierre conjunto):**
  > "El Grupo 3 acumuló casi 160 horas de desarrollo, pruebas y documentación. Entregamos un sistema que no solo cumple todos los requisitos solicitados, sino que está preparado para operar en un entorno real con altos estándares de calidad de software. Quedamos a disposición para las preguntas y para mostrar el sistema en vivo."
