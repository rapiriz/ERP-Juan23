# Historias de Usuario — ERP Distribuidora Pigüé

> Última actualización: 12/08/2026
> Responsable: Estefania Gianovich — Grupo 2
> Fuente: GitHub Issues del repositorio ERP-Juan23

⚠️ **Aviso importante:** estas historias fueron redactadas con ayuda de una IA y funcionan como guía orientativa, no como verdad definitiva ni cerrada. Están sujetas a cambios del equipo — criterios de aceptación, complejidad, dependencias y hasta el alcance de cada una pueden ajustarse a medida que se avance. No tomar ningún detalle acá como decisión final e inamovible sin confirmarlo primero con el grupo.

## Plazo general

**Fecha objetivo:** fin de octubre 2026 — se espera tener programado (código testeado e integrado, según criterio de "Done" ya documentado en `03-convenciones-y-estado.md`) lo que estas historias de usuario cubren.

## Metodología de estimación observada

Cada historia trae: **Prioridad** (MVP / Importante / Mejora), **Complejidad** (escala tipo Fibonacci: 3, 5, 8, 13...) y **Dependencias** explícitas con otros módulos/grupos. Las historias de Entregas además traen un desglose detallado del cálculo de complejidad (Cantidad de trabajo, Esfuerzo, Complejidad técnica, Riesgo, Incertidumbre, Dependencias, cada uno puntuado) que se traduce a Story Points sugeridos — no todos los módulos vinieron con ese nivel de detalle, pero es un método replicable si hace falta re-estimar algo.

**Convención de nomenclatura de historias, deducida:** `[G2]-MODULO-NN` (ej: `[G2]-CAJ-01`, `[G2]-COB-01`) — vale la pena confirmarla como estándar formal, ya que hasta ahora no existía documentación de convenciones de nombres de historias/issues.

---

## CAJA (3 H.U.)

### [G2]-CAJ-01 — Apertura de caja
**Como** administrativo, **quiero** registrar la apertura de caja al inicio del turno, **para** establecer el saldo inicial y habilitar el registro de movimientos del día.

- **Prioridad:** Importante | **Complejidad:** 3 | **Dependencias:** Login/Roles (G1)
- **Criterios de aceptación:** solo se puede abrir una caja si no hay otra ya abierta; se registra monto inicial, fecha, hora y usuario que abre; el estado cambia a "abierta" al confirmar; el sistema muestra confirmación con los datos ingresados.
- **Alcance:** registro del monto inicial, fecha, hora y usuario; activación del estado "caja abierta". No incluye gestión de múltiples cajas simultáneas.
- **Entradas:** monto inicial en efectivo, usuario logueado.
- **Salidas:** registro de apertura de caja, estado de caja: abierta.

### [G2]-CAJ-02 — Consultar movimientos de caja del día
**Como** administrativo, **quiero** consultar todos los movimientos de caja de una jornada, **para** auditar lo que entró y salió y detectar inconsistencias antes del cierre.

- **Prioridad:** Importante | **Complejidad:** 3 | **Dependencias:** [G2]-CAJ-01, [G2]-CAJ-03, [G2]-COB-01, Login/Roles (G1)
- **Criterios de aceptación:** se listan todos los movimientos del día con tipo, concepto, monto, usuario y hora; se puede filtrar por tipo (cobro, ingreso manual, egreso, extracción); se muestra saldo acumulado; se puede seleccionar otra fecha para días anteriores.
- **Alcance:** listado de movimientos con tipo, concepto, monto, usuario, saldo acumulado; filtro por tipo. No incluye edición o anulación de movimientos desde esta vista.
- **Entradas:** fecha (default: hoy), filtro de tipo (opcional).
- **Salidas:** listado de movimientos del día, saldo acumulado.

### [G2]-CAJ-03 — Consultar historial de cierres de caja
**Como** administrativo, **quiero** consultar el historial de cierres de días anteriores, **para** hacer seguimiento de la operatoria y detectar diferencias recurrentes.

- **Prioridad:** Importante | **Complejidad:** 3 | **Dependencias:** [G2]-CAJ-02
- **Criterios de aceptación:** cierres listados por fecha descendente; cada ítem muestra fecha, saldo inicial, saldo final, diferencia, usuario que cerró; acceso al detalle de movimientos de cada cierre; filtro por rango de fechas.
- **Alcance:** listado de cierres, vista de detalle. No incluye reapertura de cierres anteriores ni modificación de datos históricos.
- **Entradas:** filtro de rango de fechas (opcional).
- **Salidas:** listado de cierres históricos, detalle de movimientos por cierre.

---

## COBROS (4 H.U.)

### [G2]-COB-01 — Registrar cobro a cliente
**Como** administrativo, **quiero** registrar el cobro de una deuda pendiente de un cliente, **para** mantener actualizada su cuenta corriente y dejar constancia del pago recibido.

- **Prioridad:** MVP | **Complejidad:** 5 | **Dependencias:** Clientes (G1), Ventas (G4), Login/Roles (G1)
- **Criterios de aceptación:** buscar/seleccionar cliente activo; ver saldo pendiente antes de cobrar; ingresar monto, fecha y medio de pago (efectivo/transferencia/cheque); actualización automática del saldo al confirmar; genera comprobante con número, fecha, monto y medio de pago; registra usuario; no permite monto 0 o negativo.
- **Alcance:** selección de cliente, visualización de deuda, ingreso de monto, selección de medio de pago, generación de comprobante. No incluye conciliación bancaria, emisión de facturas ni rendiciones (van en sus módulos correspondientes).
- **Entradas:** cliente, monto, medio de pago, fecha.
- **Salidas:** saldo actualizado, comprobante de cobro, movimiento en caja (si el medio es efectivo).

### [G2]-COB-02 — Consultar cuenta corriente de un cliente
**Como** administrativo, **quiero** consultar el estado de cuenta de un cliente, **para** conocer su deuda actual, historial de pagos y facturas pendientes.

- **Prioridad:** MVP | **Complejidad:** 3 | **Dependencias:** Clientes (G1), Ventas (G4), Login/Roles (G1)
- **Criterios de aceptación:** búsqueda por nombre/código/CUIT; saldo total destacado; listado de facturas/remitos pendientes con fecha y monto; historial de cobros con fecha, monto, medio de pago; filtro por rango de fechas; saldo refleja cobros en tiempo real.
- **Alcance:** saldo actual, listado de pendientes, historial, filtro por fechas. No incluye edición manual del saldo, ni emisión de facturas/promociones.
- **Entradas:** cliente, filtro de fechas (opcional).
- **Salidas:** vista de saldo, listado de comprobantes pendientes, historial de cobros.

### [G2]-COB-03 — Anular un cobro registrado
**Como** administrativo, **quiero** anular un cobro registrado por error, **para** corregir el saldo del cliente y mantener la integridad de los registros.

- **Prioridad:** Importante | **Complejidad:** 5 | **Dependencias:** Login/Roles (G1), [G2]-COB-01, [G2]-CAJ-01
- **Criterios de aceptación:** solo admin/superior puede anular; motivo obligatorio; saldo se revierte; cobro queda marcado "anulado" visible en historial; si era efectivo, genera reversión en caja; registra usuario, fecha y hora de anulación.
- **Alcance:** anulación con motivo, reversión del saldo, registro de auditoría. No incluye eliminación física del cobro ni devolución de dinero (eso es nota de crédito, módulo Ventas).
- **Entradas:** cobro a anular, motivo.
- **Salidas:** cobro marcado anulado, saldo revertido, reversión en caja (si aplica).

### [G2]-COB-04 — Listar cobros pendientes del día
**Como** repartidor, **quiero** ver la lista de clientes con cobros pendientes para la jornada, **para** organizar mis cobranzas durante el reparto.

- **Prioridad:** Importante | **Complejidad:** 5 | **Dependencias:** Clientes (G1), Entregas (G2 — dependencia interna), [G2]-COB-01
- **Criterios de aceptación:** listado de clientes con deuda activa asignada al repartidor del día; cada ítem muestra nombre, dirección, monto pendiente, indicación de entrega asociada; filtro por zona/ruta; marcar cobro como realizado desde la vista.
- **Alcance:** listado con monto, dirección, vínculo a entrega del día. No incluye cobros de otros días no asignados, ni gestión de rutas (eso es Entregas).
- **Entradas:** usuario repartidor logueado, fecha del día.
- **Salidas:** lista de cobros pendientes de la jornada.

---

## ENTREGAS (4 H.U.)

### HU #1 — Visualización de pedidos pendientes de despacho
**Como** repartidor, **quiero** visualizar el listado de pedidos confirmados por Ventas, **para** identificar qué mercadería está lista para despachar.

- **Prioridad:** ALTA | **Complejidad:** 8 → 3 SP sugeridos | **Dependencias:** Grupo 4 (Ventas), expone pedidos confirmados
- **Criterios de aceptación:** pantalla exclusiva con órdenes en estado "Confirmado" o "Pendiente de despacho"; cada ítem muestra ID, cliente, zona/dirección, cantidad de bultos/artículos; filtro por zona geográfica.
- **Desglose de complejidad:** Cantidad de trabajo 1, Esfuerzo 1, Complejidad técnica 2 (parsear API externa), Riesgo 1 (solo lectura), Incertidumbre 1 (depende de qué tan rápido G4 defina su JSON), Dependencias 2 (endpoint externo de Ventas). Total: 8.

### HU #2 — Armado de entrega y emisión de remito
**Como** repartidor, **quiero** agrupar uno o varios pedidos pendientes en una hoja de ruta y generar sus remitos, **para** organizar el viaje y documentar la salida de mercadería.

- **Prioridad:** ALTA | **Complejidad:** 10 → 5 SP sugeridos | **Dependencias:** ninguna con otros grupos
- **Criterios de aceptación:** selección múltiple de pedidos pendientes, asociación a un repartidor; al confirmar, cambia estado de pedidos a "En preparación" o "En Camino"; genera un Remito por cada pedido con número único y detalle de artículos.
- **Desglose de complejidad:** Cantidad de trabajo 2, Esfuerzo 2, Complejidad técnica 2 (transacciones múltiples), Riesgo 2 (mercadería puede quedar duplicada/perdida si falla), Incertidumbre 1, Dependencias 1. Total: 10.

### HU #3 — Notificación de pedido entregado
**Como** repartidor, **quiero** marcar un pedido como entregado al completar la entrega física, **para** notificar al depósito en tiempo real.

- **Prioridad:** ALTA | **Complejidad:** 2 → 1 SP sugerido | **Dependencias:** ninguna con otros grupos
- **Criterios de aceptación:** repartidor ve su hoja de ruta con pedidos del día; botón/acción para cambiar estado a "Entregado"; registro automático de fecha, hora exacta y usuario (auditoría).
- **Desglose de complejidad:** Cantidad de trabajo 0, Esfuerzo 0, Complejidad técnica 1 (PUT simple + campos de auditoría), Riesgo 1, Incertidumbre 0, Dependencias 0. Total: 2.

### HU #4 (issues #122/#123 relacionadas) — Modificar lista de pedidos asignados a una entrega
**Como** repartidor, **quiero** modificar los pedidos incluidos en una entrega en preparación, **para** quitar o añadir órdenes antes de iniciar el recorrido.

- **Prioridad:** IMPORTANTE | **Complejidad:** 10 → 5 SP sugeridos | **Dependencias:** HU #122, HU #123
- **Criterios de aceptación:** solo permite modificar entregas en estado "En Preparación" (bloqueado si ya está "En Camino"); ver pedidos asignados con opción "Quitar"; al quitar, el pedido vuelve al listado de pendientes (HU #1) con estado "Confirmado"; permite "Incluir" nuevos pedidos pendientes de la misma zona; actualiza campos de auditoría (fecha_modificacion, usuario_modificacion) en cada cambio.
- **Desglose de complejidad:** Cantidad de trabajo 2, Esfuerzo 2, Complejidad técnica 2 (transaccionalidad, evitar registros huérfanos), Riesgo 2 (pedidos pueden quedar en limbo de estado), Incertidumbre 1, Dependencias 1. Total: 10.

🚩 Nota de nomenclatura de estados detectada: estas historias usan "En preparación/En Camino/Entregado/Confirmado" (mayúsculas variables), mientras que el modelo de base de datos ya documentado en `02-arquitectura-tecnica.md` define `ENTREGA.estado` como `pendiente | en_transito | entregada | no_entregada` (minúsculas, snake_case, sin "en preparación" como estado propio). Hay que unificar esto antes de programar — ni los nombres ni la cantidad de estados coinciden exactamente.

---

## RENDICIONES (6 H.U.)

### Registrar cobros dentro de una rendición
**Como** administrador, **quiero** registrar los cobros realizados durante un reparto, **para** informar qué clientes pagaron y cuánto.

- **Prioridad:** MVP | **Complejidad:** 5 | **Dependencias:** Ventas, Clientes, Gestión de Cobros, Login
- **Criterios de aceptación:** seleccionar cliente y factura asociada; registrar monto abonado; permitir múltiples medios de pago; validar montos inválidos; asociar el cobro a una rendición existente.
- **Alcance:** pagos parciales o completos, asociación a clientes/facturas, registro de medio de pago. No incluye generación de facturas.
- **Notas técnicas:** validar existencia de factura pendiente; permitir pagos parciales; el administrativo no cobra, registra en el sistema los cobros ocurridos durante ese reparto.

### Registrar rendición de reparto
**Como** repartidor, **quiero** registrar una rendición de reparto, **para** informar cobros, entregas y movimientos del recorrido.

- **Prioridad:** MVP | **Complejidad:** 5 | **Dependencias:** Login, Gestión de Entregas, Gestión de Cobros
- **Criterios de aceptación:** seleccionar el reparto realizado; registrar fecha y usuario; ingresar múltiples cobros; calcular total rendido; guardar observaciones.
- **Alcance:** datos generales de la rendición, pedidos/facturas entregadas, montos cobrados, observaciones/diferencias. No incluye conciliación bancaria automática.

### Aprobar o rechazar rendiciones
**Como** administrativo, **quiero** modificar el estado de las rendiciones, **para** validar que la información cargada sea correcta.

- **Prioridad:** MVP | **Complejidad:** 5 | **Dependencias:** Login, Gestión de Cobros (si aprobar confirma cobros), Caja (si aprobar genera ingresos), Entregas/Repartos
- **Criterios de aceptación:** cambiar estado; registrar usuario aprobador y fecha de validación; permitir motivo de rechazo.
- **Estados sugeridos:** Pendiente, Aprobada, Rechazada.
- **Alcance:** aprobar, rechazar, registrar motivo. No incluye edición posterior automática.

### Consultar historial de rendiciones
**Como** administrativo, **quiero** consultar el historial de rendiciones, **para** revisar operaciones anteriores.

- **Prioridad:** Importante | **Complejidad:** 3 | **Dependencias:** Login
- **Criterios de aceptación:** búsqueda por fecha y por repartidor; muestra estado; visualiza detalle completo.
- **Alcance:** buscar, filtrar, ver detalle. No incluye edición masiva.

### Registrar diferencias en una rendición
**Como** administrativo, **quiero** registrar diferencias detectadas en una rendición, **para** controlar inconsistencias entre lo esperado y lo rendido.

- **Prioridad:** MVP | **Complejidad:** 3 | **Dependencias:** Gestión de Cobros, Caja, Login
- **Criterios de aceptación:** calcular diferencia automáticamente; permitir registrar motivo y observaciones; marcar rendición con diferencia pendiente.
- **Alcance:** registrar diferencias monetarias, motivo, observaciones. No incluye resolución automática.

### Registrar devoluciones dentro de una rendición
**Como** repartidor, **quiero** registrar devoluciones realizadas durante un reparto, **para** informar productos/pedidos que no pudieron entregarse.

- **Prioridad:** Importante | **Complejidad:** 5 | **Dependencias:** Entregas, Ventas, Stock
- **Criterios de aceptación:** seleccionar pedido; ingresar motivo; registrar observaciones; asociar devolución a la rendición.
- **Alcance:** registrar devoluciones, motivo, cliente/pedido asociado. No incluye actualización automática de stock físico.
- **Notas técnicas:** validar existencia del pedido.

---

## CONCILIACIÓN BANCARIA (5 H.U.)

### Importación y Mapeo de Extractos Bancarios
**Como** administrativo, **quiero** importar extractos bancarios digitales en formatos estándar, **para** cargar masivamente los movimientos oficiales sin digitación manual.

- **Prioridad:** MVP | **Complejidad:** 8 | **Dependencias:** Login/Roles (G1), estructura base de BD
- **Criterios de aceptación:** subir archivos .csv/.xls/.xlsx; pantalla para mapear columnas (fecha, descripción, monto) — agnóstico del banco de origen; validación de fechas/montos antes de guardar; evita duplicar filas ya subidas (mismo día, descripción, monto).
- **Alcance:** subir/leer archivos, pantalla de mapeo, guardar con estado "pendiente". No incluye cruzar con ventas/cobros del sistema (otra historia) ni conexión directa al banco por internet.
- **Salidas:** movimientos guardados con estado "pendiente"; resumen de filas guardadas/salteadas/con error.

### Conciliación Automática de Movimientos
**Como** administrativo, **quiero** ejecutar un cruce automático entre los movimientos del ERP y el extracto bancario, **para** identificar coincidencias sin trabajo manual.

- **Prioridad:** MVP | **Complejidad:** 13 (la más alta de todas las historias) | **Dependencias:** Importación de Extractos, Módulo de Caja, Gestión de Cobros
- **Criterios de aceptación:** botón de inicio del cruce; busca coincidencias exactas de monto + fecha (con margen configurable) + tipo de movimiento; al coincidir, marca ambos registros "conciliado" automáticamente; muestra resumen de cruces exitosos y sin pareja.
- **Alcance:** motor de comparación en servidor, ventana de tiempo configurable (ej. 2-3 días), actualización de estado. No incluye resolver a mano diferencias (otra historia).

### Conciliación Manual de Transacciones
**Como** administrativo, **quiero** asociar manualmente movimientos del banco con registros del sistema, **para** resolver pagos agrupados o parciales que el cruce automático no pudo unir.

- **Prioridad:** MVP | **Complejidad:** 8 | **Dependencias:** Conciliación Automática
- **Criterios de aceptación:** pantalla de dos columnas (banco pendiente / ERP pendiente); selección de una fila del banco + uno o varios movimientos del sistema; valida que la suma coincida exactamente antes de guardar; al confirmar, marca todo como "conciliado".
- **Alcance:** interfaz de selección manual, relaciones uno-a-muchos, bloqueo si no da diferencia cero. No incluye crear nuevos cobros/ventas si falta plata.

### Registro de Ajustes y Gastos Bancarios
**Como** administrativo, **quiero** registrar gastos y comisiones desde el módulo de conciliación, **para** dar de alta movimientos del banco sin documento previo en el sistema.

- **Prioridad:** MVP | **Complejidad:** 3 | **Dependencias:** Importación de Extractos
- **Criterios de aceptación:** seleccionar fila de banco pendiente que sea gasto/comisión; formulario de categorización (Comisión, Impuesto al Cheque, Mantenimiento) + observación; al confirmar, registra el egreso y lo asocia a la fila; marca la fila como "conciliado" automáticamente.
- **Alcance:** formulario de alta rápida, categorización básica, conciliación automática del movimiento creado. No incluye carga de facturas de proveedores (eso es Compras).

### Control y Reversión de Cheques Rechazados
**Como** administrativo, **quiero** registrar el rechazo de un cheque, **para** corregir el saldo del banco y devolverle la deuda al cliente automáticamente.

- **Prioridad:** Importante | **Complejidad:** 13 (la más alta junto con Conciliación Automática) | **Dependencias:** Conciliación Manual, Gestión de Saldos/Cuentas Corrientes (G4)
- **Criterios de aceptación:** buscar cheque depositado y conciliado previamente; marcar como "Rechazado"; al confirmar, resta automáticamente el monto del saldo bancario; se comunica con Cuentas Corrientes para reactivar la deuda del cliente.
- **Alcance:** búsqueda de cheques, cambio de estado conciliado→rechazado, descuento del saldo bancario. No incluye gestión legal o cobro de multas al cliente.

---

## VENTAS (8 H.U.) — Módulo del Grupo 4, referencia por dependencia directa

No es nuestro módulo — se documenta acá porque es la dependencia externa más fuerte que tiene el Grupo 2 (Entregas y Cobros dependen directamente de Ventas). Sujeto a cambios que no controlamos.

| Historia | Prioridad | Complejidad | Notas relevantes para nosotros |
|---|---|---|---|
| Registrar venta | MVP | 13 | Genera el comprobante y actualiza stock — punto de partida de facturación, entrega y cobro |
| Modificar venta | Importante | 8 | Reemplaza el comprobante anterior — si una entrega ya fue armada sobre una venta que después se modifica, hay que definir qué pasa |
| Eliminar venta | Importante | 5 | Solo si no está "en proceso de envío" — coincide con la restricción de estado que ya vimos en Entregas |
| Crear carrito | Importante | 5 | Etapa previa a confirmar venta, no genera datos persistentes hasta confirmar |
| Agregar comentarios | Mejora | 3 | Menor prioridad, no crítico para nuestra integración |
| Resumen de venta (PDF) | Importante | 5 | Comprobante descargable — capaz reutilizable como referencia para nuestro propio comprobante de Entrega/Remito |
| Historial de ventas | Importante | 3 | — |
| Filtros de historial | Importante | 5 | — |

---

## Facturación — sin historias de usuario todavía

Confirmado por el propio equipo: el módulo de Facturación no tiene historias de usuario creadas aún. Sigue siendo la bandera roja más importante pendiente (ver `02-arquitectura-tecnica.md`) — sin historias, sin tablas, sin endpoints propios definidos formalmente hasta ahora.

## Notas y dudas pendientes (nuevas, de esta tanda)

- Nomenclatura de estados de Entrega inconsistente: las historias de usuario usan "En preparación/En Camino/Entregado/Confirmado", el modelo de BD ya documentado usa `pendiente/en_transito/entregada/no_entregada`. Unificar antes de programar.
- Dependencia de Cobros con Entregas confirmada como interna al Grupo 2 (HU de "Listar cobros pendientes del día" depende directamente de Entregas) — coordinar bien el orden de desarrollo entre ambos módulos dentro del propio grupo.
- Cheques rechazados (Conciliación) depende de Cuentas Corrientes de G4 — otra dependencia externa además de Ventas, no habíamos anotado esta.
- Convención `[G2]-MODULO-NN` para nombrar historias — usada en Caja y Cobros, no en Entregas/Rendiciones/Conciliación (estas últimas usan otro formato o ninguno). Confirmar si vale la pena unificar.
