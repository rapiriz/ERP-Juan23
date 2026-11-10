# Catálogo público y solicitudes — G1

El visitante consulta `/catalogo` y el detalle sin iniciar sesión. El maestro de productos sigue perteneciendo a Productos: G1 proyecta sus datos mediante `CatalogoGateway`, con transporte HTTP o despacho interno autorizado según `services.catalog.transport`. El transporte interno evita que `artisan serve` se bloquee intentando atender una segunda llamada HTTP a sí mismo. No duplica el maestro ni consulta directamente sus tablas desde el catálogo.

Categorías jerárquicas, filtros combinados, fotos, disponibilidad, paginación y alternativas usan la información comercial de G3. La disponibilidad descuenta los lotes controlados vencidos; la ausencia de información no se convierte en cero.

## Solicitudes

- `/catalogo/carrito`: selección guardada en el navegador, sólo identificadores y cantidades.
- `POST /catalogo/pedidos`: solicitud persistente con contacto, consentimiento, CSRF, límite de frecuencia e idempotencia. Los precios se recalculan en servidor y son de referencia.
- `/catalogo/pedido/{token}`: constancia mediante enlace privado aleatorio, sin datos de contacto, caché ni indexación.
- `/pedidos-catalogo`: revisión administrativa. El administrador verifica identidad, elige el cliente y la lista de precios y convierte la solicitud en una venta mediante el flujo existente de Ventas. La selección pública por sí sola no crea deuda ni reserva stock.

`g1_catalog_orders`, `g1_catalog_order_items` y `g1_catalog_order_events` conservan solicitud, detalle y revisión. La conversión tiene una clave privada distinta de la clave pública para evitar reutilizarla desde el formulario externo.

Las fotos se cargan desde Inventario; `ProductImageStorage` valida y convierte a WebP. El catálogo no administra archivos maestros por su cuenta. El aviso local de datos de prueba depende de `catalogo.testing_notice`; nunca certifica precios ni existencias comerciales reales.

Ver [informe vigente y reversión](../../../docs/informe-catalogo-lotes-articulos-reales-2026-11-09.md). Los informes anteriores son históricos.
