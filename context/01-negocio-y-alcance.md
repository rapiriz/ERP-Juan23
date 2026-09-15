# Negocio y Alcance — ERP Distribuidora

> Última actualización: 12/08/2026
> Responsable: Estefania Gianovich — Grupo 2

## Módulos del proyecto

### Grupo 1 — Gestión de Clientes, Login, Reportes, Catálogo

**Épica 1: Gestión de Clientes**
- Primera etapa: alta, baja, modificación y filtrado de clientes (operaciones básicas).
- Etapas futuras: etiquetas, historial de pagos, pagos parciales, generación de reclamos (tanto del admin hacia proveedores como de clientes hacia el admin por problemas con productos).

**Épica 2: Login**
- Primera etapa: inicio de sesión simple, redirección según permisos, bloqueo tras X intentos fallidos.
- Etapas futuras: expiración de sesión por inactividad (seguridad). Contexto relevante: la distribuidora tiene solo 3 personas por ahora, por lo tanto solo 3 tipos de usuario, una acreditación por cada uno.

**Épica 3: Reportes**
- Sin prioridad definida aún (sujeto a cambios). Debe incluir: ranking comercial (productos más/menos vendidos), stock bajo, registro detallado de pagos (recibidos y realizados), historial por cliente, ventas diarias/semanales/mensuales, vencimiento de facturas, notificaciones de transferencias (comprobantes y chequeo de que lleguen).
- Todos los reportes exportables en PDF, DOCX o XLSX.

**Épica 4: Catálogo**
- Actualización automática según stock.
- Desarrollo de una "web" tipo catálogo visible para el cliente.
- Recomendación de productos según parámetros preestablecidos (ej: sección de más vendidos).

### Grupo 2 (nuestro grupo)
Entregas, Facturación, Caja, Cobros, Conciliación Bancaria, Rendiciones.
*(Sin asignación fija de quién hace qué módulo todavía — se va definiendo sobre la marcha.)*

### Grupo 3 — Productos, Stock, Proveedores, Compras

- **Épica 1 — Productos:** administrar productos, categorías y marcas (registro, búsqueda, mantenimiento).
- **Épica 2 — Stock:** control de inventario, ingresos, egresos y ajustes, disponibilidad de info actualizada.
- **Épica 3 — Proveedores:** registro, consulta y administración de proveedores, asociación a productos, seguimiento de info relevante para compras.
- **Épica 4 — Compras:** registro y administración de compras de mercadería, control de proveedores, recepciones, pagos y deudas asociadas.
- **Épica 5 — Pedidos de compra:** generación, consulta, modificación y control de órdenes de compra, planificación de reposiciones, seguimiento hasta recepción.

### Grupo 4 — Ventas, Saldos, Promociones

- **Gestión de ventas:** registro, modificación, baja e historial de ventas.
- **Gestión de saldos:** cuentas corrientes, pagos, devoluciones y notas de crédito.
- **Gestión de promociones:** descuentos, promociones y combos.

## Entidades centrales del negocio

Según el diagrama entidad-relación provisto por el grupo (ver `diagrama-entidad-relacion.png` en el Project):

**Entidades identificadas:**
- CLIENTE (subtipo de USUARIO — ver nota de herencia abajo)
- USUARIO
- VENTA
- PRODUCTO
- PROMOCION
- ENTREGA
- PROVEEDOR
- COMPRA

**Relación de herencia:**
CONFIRMADO (ver `02-arquitectura-tecnica.md`): es al revés de lo que sugería este primer diagrama-imagen. USUARIO hereda de CLIENTE, no al contrario — defendido explícitamente por el equipo de base de datos en la presentación. CLIENTE es la clase principal; USUARIO agrega los atributos propios (user, password, rol, estado) sobre la base de CLIENTE. Sin confirmar aún si existen roles adicionales de USUARIO no representados (ej: variantes de vendedor/admin más allá de admin|vendedor|repartidor) — relevante para el módulo de Login del Grupo 1.

**Relaciones confirmadas:**
- CLIENTE → REALIZA (1:N) → VENTA
- VENTA → TIENE (N:N) → PRODUCTO
- PRODUCTO → TIENE (N:N) → PROMOCION
- PROVEEDOR → ASIGNA (N:N) → PRODUCTO
- USUARIO → Asigna (N:1) → ENTREGA
- USUARIO → Realiza (1:N) → COMPRA
- COMPRA → Hacia (N:N) → PROVEEDOR

**Relaciones ambiguas (pendiente de confirmar con el grupo):**
El sector del diagrama entre VENTA, un diamante "TIENE (N:1)", "APLICA (N:N)", PROMOCION y ENTREGA tiene líneas cruzadas que no permiten determinar con certeza si VENTA se relaciona directamente con ENTREGA, o si es una relación compuesta distinta. No dar por definitivo hasta confirmar directamente con quien dibujó el diagrama.

**Depósito / Sucursal:**
No existe como entidad separada en el diagrama ni fue mencionado como tal por el grupo. Según lo conversado, la distribuidora opera con depósito y sucursal como el mismo lugar físico (no hay múltiples depósitos/sucursales separados). Esto simplifica el modelo de stock para el módulo de Entregas: no habría que manejar "stock por depósito", sino un stock único. Confirmar esto explícitamente al retomar clases, ya que afecta directamente el diseño de tu módulo.

## Reglas de negocio definidas

**Stock:**
Depósito único (no hay múltiples sucursales — confirma lo anotado arriba). El sistema actual ya maneja el stock de forma virtual/digital. Regla clave detectada en entrevista con el cliente: un pedido cancelado devuelve stock automáticamente (actualmente vía movimientos negativos, probablemente equivalente a una nota de crédito o ajuste de stock ligado a la cancelación) — relevante para el módulo de Entregas si se manejan cancelaciones o devoluciones.

**Crédito de clientes:**
Manejo informal — Diego (el cliente que encargó el sistema) fía "de palabra", sin documentación legal formal detectada hasta ahora. Regla de negocio explícita: un cliente puede comprar aunque tenga deuda pendiente (no hay bloqueo automático por saldo). Sin información aún sobre límites de crédito o topes — pendiente de profundizar cuando se toque el módulo de Cuentas Corrientes (Grupo 4).

**Comprobantes:**
Confirmado: remitos, facturas, notas de crédito, cheques, transferencias bancarias. Diego no restringe el método de pago/comprobante para facilitar la venta. Particularidad importante sobre transferencias: requieren validación manual — actualmente el cliente manda el comprobante por WhatsApp y Alma lo confirma a mano revisando el banco. Punto de mejora potencial a proponer si se toca Cobros o Conciliación Bancaria.

**Otras reglas relevantes:**
- El historial de operaciones se guarda por cliente.
- Los descuentos son personalizados y manuales por cliente (no hay tabla de descuentos automática por categoría).
- Los pedidos están ligados a localidades/rutas — la distribuidora trabaja por zonas, con rutas que se repiten cada ~15 días.
- Los repartidores registran el estado de pago al momento de entregar — directamente relevante para el módulo de Entregas: en el flujo real actual, entrega y registro de cobro están acoplados.
- Diego quiere poder vender y facturar casi al mismo tiempo, para que la preparación del pedido arranque antes — afecta el diseño del flujo Venta → Entrega si se busca reducir esa demora.

## Actores y roles reales

Basado en entrevista con el cliente (Diego), dueño de la distribuidora.

**Actores actuales del negocio (personas reales, no roles de sistema todavía):**
- **Diego:** vendedor, administrador, maneja cuentas corrientes, facturación, control de pagos, supervisión logística. Trabaja muy presencialmente, prioriza relaciones humanas, desconfía de automatizaciones financieras.
- **Alma:** administración general, manejo de precios, validación de transferencias, puente con la contadora externa, acceso completo al sistema.
- **Matías:** preparación de pedidos, reparto, registro manual de cobros, cierre de caja. Actualmente sin restricciones técnicas de permisos.

**Roles de sistema sugeridos (a confirmar con Grupo 1, que maneja Login):** Administrador, Vendedor, Repartidor, Cliente. El tipo de cliente (mayorista/minorista) sería un atributo, no un rol separado.

## Perfil del negocio

- 90% de las ventas son a comercios mayoristas; minoristas no son prioridad.
- Venta organizada por zonas/pueblos, con rutas que se repiten cada ~15 días.
- Volumen alto: pedidos de aproximadamente 250 bultos diarios.
- Descuentos personalizados por cliente (manuales, no automáticos por categoría).

**Nota de diseño importante:** el sistema NO debe pensarse como un e-commerce tipo carrito de supermercado (modelo B2C). Es un negocio B2B por rutas y relación comercial directa — el error común es diseñar "Mercado Libre chiquito" cuando el flujo real es otro.

**Ubicación:** la distribuidora del cliente está en Pigüé (Buenos Aires). No se conoce el nombre comercial exacto de la distribuidora — el equipo del prototipo la referencia como "Distribuidora Pigüé" a modo de placeholder, usando la localidad como identificador provisorio.

**Funcionalidad futura — Inteligencia Artificial:**
Mencionado por el equipo como incorporación futura al sistema. Posibles casos de uso sugeridos (sin definir cuál se implementará): chat de ayuda/atención al cliente, o alertas automáticas de faltantes de stock. Proveedor/tecnología de IA aún sin elegir — cualquier mención a una API específica sería prematura por ahora.

## Notas y dudas pendientes

- No queda claro cómo funciona el módulo de Facturación en la práctica (relación con Ventas, con qué dispara la generación de un comprobante).
- No queda claro cómo funciona el módulo de Caja (qué registra exactamente, cómo se relaciona con Cobros y Conciliación Bancaria).
- (Espacio para sumar dudas nuevas a medida que surjan.)