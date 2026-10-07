# Mapa de APIs entre grupos - Sprints 3 y 4

Relevamiento al 6 de octubre de 2026 para responder a la [issue #190 (APIs)](https://github.com/rapiriz/ERP-Juan23/issues/190). La issue pide que cada grupo especifique que datos y operaciones necesita de Ventas, Saldos y Promociones. Este documento cruza las historias de los milestones Sprint 3 y Sprint 4, el codigo local de G1 y las rutas visibles en las ramas `develop-g2`, `develop-g3` y `develop-g4`. No se pudo consultar el tablero visual de GitHub Projects con una sesion autenticada; las historias se relevaron a traves de sus issues y milestones publicos.

**Estados usados aqui:** `implementado en G1` significa que la ruta existe en el proyecto local; `ruta visible en rama` significa que figura en `routes/api.php` del otro grupo, sin afirmar que la integracion funcione de extremo a extremo; `propuesto` significa que todavia hay que acordar e implementar el contrato. Las URLs propuestas son nombres de trabajo, no compromisos de los otros grupos.

Los cuerpos, permisos y errores de las rutas de G1 estan en [contrato-api-g1.md](contrato-api-g1.md). La configuracion de los consumidores HTTP actuales esta en [integracion-microservicios.md](integracion-microservicios.md).

## APIs que G1 ya publica

Todas estas rutas usan el prefijo `/api/v1`. Salvo el login, requieren `Authorization: Bearer <token>`. El token interno de servicio permite consultas y operaciones de reclamos; las escrituras de clientes requieren token de usuario administrativo.

| Metodo | Ruta | Funcion | Consumidor probable |
| --- | --- | --- | --- |
| POST | `/auth/login` | Autenticar usuario y entregar token opaco | Interfaces de todos los grupos, sujeto a acordar validacion entre servicios |
| POST | `/auth/logout` | Revocar el token de usuario | Interfaces de todos los grupos |
| GET | `/health` | Confirmar que el proceso API responde; requiere token | Monitoreo interno |
| GET | `/clientes` | Buscar, filtrar y paginar clientes | G2 Cobros/Entregas, G4 Ventas |
| POST | `/clientes` | Crear cliente activo | G1, administrativo |
| GET | `/clientes/{id}` | Obtener ficha y estado del cliente | G2, G4 |
| PUT | `/clientes/{id}` | Reemplazar ficha editable completa | G1, administrativo |
| PATCH | `/clientes/{id}` | Actualizar parcialmente la ficha | G1, administrativo |
| GET | `/clientes/{id}/saldo` | Obtener saldo actual en ARS | G2, G4 mientras G1 conserve la propiedad del saldo |
| GET | `/clientes/{id}/cobros` | Listar cobros confirmados | G2, G4 mientras G1 conserve la propiedad de cobros |
| GET | `/zonas` | Listar zonas | G2 Entregas, G4 filtros |
| GET | `/reclamos` | Listar y filtrar reclamos | G1 |
| GET | `/reclamos/{id}` | Consultar un reclamo | G1 |
| POST | `/reclamos` | Crear reclamo | G1 |
| PATCH | `/reclamos/{id}/estado` | Cambiar su estado | G1 |
| GET | `/usuarios/{id}` | Obtener ID, nombre, rol y estado | Servicios que deban identificar usuarios |

Estas son las 16 rutas definidas en `routes/api.php`. El token de G1 es **opaco y cifrado**, no un JWT. Compartirlo con otro servicio no le permite verificarlo localmente sin un mecanismo de introspeccion. El token interno (`INTERNAL_API_TOKEN`) es otra credencial, configurada entre servidores de confianza.

## APIs que G1 podria publicar

Son propuestas derivadas de las dependencias entre grupos; ninguna esta implementada en G1. Hay que decidir primero la propiedad de saldos y cobros antes de implementar las operaciones monetarias.

| Ruta propuesta | Para que se necesita | Grupo consumidor |
| --- | --- | --- |
| `GET /api/v1/auth/me` | Consultar identidad y rol del usuario autenticado | G2, G3, G4 |
| `POST /api/v1/auth/introspect` | Validar un token de usuario desde otro servicio sin compartir claves de cifrado | G2, G3, G4 |
| `GET /api/v1/usuarios?rol=repartidor&estado=activo` | Listar repartidores elegibles | G2 Entregas/Rendiciones |
| `POST /api/v1/clientes/{id}/cargos` | Registrar el cargo de una venta confirmada en la cuenta del cliente | G4, **solo si G1 sigue siendo dueno del saldo** |
| `POST /api/v1/clientes/{id}/cobros` | Registrar cobro parcial o total mediante API | G2/G4, **solo si G1 sigue siendo dueno de cobros** |
| `POST /api/v1/notificaciones-transferencia` | Recibir aviso y comprobante de una transferencia | G1, historia [#120](https://github.com/rapiriz/ERP-Juan23/issues/120) |
| `GET /api/v1/notificaciones-transferencia?estado=pendiente` | Consultar avisos por validar | G1/G2 |
| `PATCH /api/v1/notificaciones-transferencia/{id}/estado` | Aprobar o rechazar un aviso tras comprobacion | G1/G2, a definir |

Para un eventual cargo desde Ventas, acordar `venta_id`, `cliente_id`, fecha, monto y una clave de idempotencia. Reintentar la misma venta no debe duplicar deuda. Tambien hay que definir anulaciones y notas de credito antes de usarlo con datos reales.

## Lo que G1 necesita de G2

G2 trabaja en Cobros, Caja, Rendiciones, Entregas, Conciliacion y analiza Facturacion. La [rama develop-g2](https://github.com/rapiriz/ERP-Juan23/blob/develop-g2/routes/api.php) contiene las rutas indicadas como visibles.

| Ruta | Estado | Uso en G1 |
| --- | --- | --- |
| `GET /api/v1/caja/{fecha}` | Ruta visible en rama | Reporte de caja diaria, Sprint 4 [#117](https://github.com/rapiriz/ERP-Juan23/issues/117) |
| `GET /api/v1/caja/{fecha}/{id_usuario}` | Ruta visible en rama | Detalle de caja por usuario |
| `GET /api/v1/caja/cierres` | Ruta visible en rama | Historial de cierres |
| `POST /api/v1/cobros` | Ruta visible en rama | Registrar cobros, si G2 queda como dueno de esa operacion |
| `GET /api/v1/cobros/clientes/{idCliente}/saldo` | Ruta visible en rama | Consultar saldo; se superpone con `GET /clientes/{id}/saldo` de G1 |
| `GET /api/v1/cobros/clientes/{id}/historial?desde=...&hasta=...` | Propuesto | Historial de pagos de Sprint 3 [#104](https://github.com/rapiriz/ERP-Juan23/issues/104) |
| `GET /api/v1/cobros/pendientes?fecha=...&zona_id=...` | Propuesto | Clientes deudores y cobros pendientes, Sprint 4 [#114](https://github.com/rapiriz/ERP-Juan23/issues/114) |
| `GET /api/v1/facturas?cliente_id=...&estado=pendiente` | Propuesto | Facturas que integran la deuda de un cliente, [#114](https://github.com/rapiriz/ERP-Juan23/issues/114) |
| `GET /api/v1/facturas/proximas-vencer?dias=7` | Propuesto | Reporte de vencimientos, Sprint 4 [#116](https://github.com/rapiriz/ERP-Juan23/issues/116) |
| `GET /api/v1/transferencias?estado=pendiente` | Propuesto | Cruzar avisos de G1 con conciliacion bancaria; propiedad a definir |

El historial de cobros que G1 ya publica puede cubrir [#104](https://github.com/rapiriz/ERP-Juan23/issues/104) por ahora. La historia de facturacion de G2 [#188](https://github.com/rapiriz/ERP-Juan23/issues/188) es un analisis de backlog; no hay que tratar sus endpoints sugeridos como disponibles. G2 tambien tiene historias de anulacion y pago parcial en Sprint 4 ([#3](https://github.com/rapiriz/ERP-Juan23/issues/3), [#4](https://github.com/rapiriz/ERP-Juan23/issues/4)), que necesitan una sola fuente de saldo.

## Lo que G1 necesita de G3

G3 gestiona Productos y Stock. La [rama develop-g3](https://github.com/rapiriz/ERP-Juan23/blob/develop-g3/routes/api.php) expone rutas `/api/...`, sin el prefijo `/v1` que G1 espera hoy.

| Ruta visible en G3 | Uso en G1 |
| --- | --- |
| `GET /api/stock/alertas` | Reporte de stock bajo, Sprint 3 [#115](https://github.com/rapiriz/ERP-Juan23/issues/115) |
| `GET /api/stock` | Consulta general de inventario |
| `GET /api/stock/{id}/disponibilidad` | Disponibilidad y precio de un producto |
| `GET /api/productos/{id}` | Ficha de producto |
| `GET /api/stock/{id}/historial-movimientos` | Historial de entradas, salidas y ajustes |
| `GET /api/stock/lotes-por-vencer` | Lotes proximos al vencimiento |

**Desacuerdo concreto:** G1 consume actualmente `GET /api/v1/productos/stock-bajo?orden=asc|desc` cuando `INVENTORY_SERVICE_DRIVER=http`; G3 ofrece `GET /api/stock/alertas`. La forma JSON tampoco coincide: G1 espera `nombre`, `stock`, `categoria` y `marca`; la alerta de G3 devuelve `descripcion`, `stock_disponible`, `categoria_nombre` y `marca_nombre`, dentro de `{"status":"success","data":[...]}`. Hace falta adaptar el consumidor de G1 o acordar un endpoint compatible. El estado de GitHub de la historia [#115](https://github.com/rapiriz/ERP-Juan23/issues/115) no prueba que este intercambio HTTP ya funcione.

## Lo que G1 necesita de G4

G4 tiene historias de Ventas, Saldos y Promociones. En la [rama develop-g4](https://github.com/rapiriz/ERP-Juan23/tree/develop-g4) revisada no habia `routes/api.php`; la pantalla de ventas usaba productos de ejemplo y las promociones eran un placeholder. Las siguientes rutas son **propuestas**, no APIs disponibles.

| Ruta propuesta | Necesidad de G1 |
| --- | --- |
| `GET /api/v1/ventas?fecha=YYYY-MM-DD` | Ventas diarias, Sprint 3 [#119](https://github.com/rapiriz/ERP-Juan23/issues/119) |
| `GET /api/v1/ventas?cliente_id=12&desde=...&hasta=...` | Historial del cliente, Sprint 3 [#118](https://github.com/rapiriz/ERP-Juan23/issues/118) |
| `GET /api/v1/ventas/{id}` | Detalle completo de una operacion y sus productos |
| `GET /api/v1/ventas/ranking-productos?desde=...&hasta=...` | Ranking comercial, Sprint 4 [#113](https://github.com/rapiriz/ERP-Juan23/issues/113) |
| `GET /api/v1/promociones` | Listado de promociones de G4 |
| `GET /api/v1/promociones/activas?fecha=...` | Promociones vigentes |
| `GET /api/v1/promociones/aplicables?cliente_id=12&producto_id=8` | Promociones para cliente/producto, pensando en Catalogo/Recomendaciones de Sprint 5 |

Para [#119](https://github.com/rapiriz/ERP-Juan23/issues/119) y [#118](https://github.com/rapiriz/ERP-Juan23/issues/118), G1 ya preparo un consumidor HTTP que espera `GET /api/v1/ventas` con `{"data":[...]}` e informacion de fecha, estado, total, cliente y detalles con productos. El contrato preciso esta en [integracion-microservicios.md](integracion-microservicios.md#json-requerido-de-ventas). El modo local lee las tablas de este proyecto; ese fallback no confirma integracion con la base de G4. Para ranking, el servicio de G4 podria entregar ventas detalladas completas y G1 calcular el ranking, o publicar un agregado: conviene elegir un solo contrato y asegurar que no haya paginacion incompleta.

Las promociones de G4 ([#162](https://github.com/rapiriz/ERP-Juan23/issues/162) a [#165](https://github.com/rapiriz/ERP-Juan23/issues/165)) no son requisito directo de los Sprint 3 y 4 de G1, pero pueden alimentar las historias futuras de Catalogo y Recomendaciones. Las historias de G4 [#153](https://github.com/rapiriz/ERP-Juan23/issues/153), [#154](https://github.com/rapiriz/ERP-Juan23/issues/154) y [#156](https://github.com/rapiriz/ERP-Juan23/issues/156) tambien hablan de deuda y pagos; no deben crear un tercer saldo independiente.

## Decisiones que hay que cerrar con los grupos

1. **Propiedad de saldo y cobros.** Hoy G1 guarda `clientes.saldo` y `cobros`; G2 tiene su propio modulo Cobros y G4 historias de Saldos. Como objetivo de microservicios se puede asignar Clientes/Usuarios/Reclamos a G1, Cobros/Cuenta corriente/Caja a G2, Productos/Stock a G3 y Ventas/Promociones a G4. Esa distribucion es una **recomendacion**, no un acuerdo confirmado. Hasta migrar, G1 debe seguir siendo la fuente de verdad de los saldos que expone.
2. **Eventos monetarios.** Acordar quien registra el cargo de una venta, quien registra o anula un cobro, como se relaciona con una factura y como se evitan duplicados al reintentar peticiones. Nunca actualizar el mismo saldo por separado en G1, G2 y G4.
3. **Autenticacion entre servicios.** La documentacion general de arquitectura menciona JWT, mientras G1 entrega un token opaco. Acordar introspeccion o un estandar comun y definir roles, vencimiento y credenciales de servicio.
4. **Version y forma de los JSON.** G1 usa `/api/v1` y respuestas con `data`; G3 publica `/api` y agrega `status`. G2 mezcla respuestas propias. Antes de conectar `SALES_SERVICE_DRIVER=http` o `INVENTORY_SERVICE_DRIVER=http`, acordar URL, campos, errores, paginacion, moneda y zona horaria.
5. **Facturacion.** Definir quien publica facturas pendientes y vencimientos, y si G2 asume la titularidad despues de su analisis [#188](https://github.com/rapiriz/ERP-Juan23/issues/188).

## Fuentes

- [Issue #190: APIs](https://github.com/rapiriz/ERP-Juan23/issues/190).
- [Historias de G1 en Sprint 3](https://github.com/rapiriz/ERP-Juan23/issues?q=is%3Aissue+milestone%3A%22Sprint+3%22+%22%5BG1%5D%22) y [Sprint 4](https://github.com/rapiriz/ERP-Juan23/issues?q=is%3Aissue+milestone%3A%22Sprint+4%22+%22%5BG1%5D%22).
- [Rutas de G2](https://github.com/rapiriz/ERP-Juan23/blob/develop-g2/routes/api.php), [rutas de G3](https://github.com/rapiriz/ERP-Juan23/blob/develop-g3/routes/api.php) y [rama de G4](https://github.com/rapiriz/ERP-Juan23/tree/develop-g4).
- Codigo local de G1: `routes/api.php`, `app/Services/Gateways/HttpSalesGateway.php` y `app/Services/Gateways/HttpInventoryGateway.php`.
