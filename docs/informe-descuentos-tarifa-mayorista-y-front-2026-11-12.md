# Descuentos, tarifa mayorista y presentación compartida

Fecha: 12/11/2026.

## Versiones y alcance

Se comparó `develop` en `227cf184dcc36313ebd968841410f1d13506d7f1` con el estado inicial de `Branch-G1` en `fecf67fe895acc6d94755a7121b9a57d5e908b06`. **Las modificaciones de esta intervención se realizaron sólo en la copia local de `Branch-G1`.** No se hizo merge, commit ni push y no se modificó la copia de `develop` utilizada para el análisis.

El usuario definió las reglas durante la intervención:

1. Tarifa minorista predeterminada. Para un cliente mayorista, permitir elegir expresamente la tarifa mayorista con un checkbox, sin agregar mínimos de cantidad inventados.
2. Ante una bonificación del 100%, mostrar una advertencia grande preguntando si se quieren regalar los productos. Permitir continuar después de una confirmación expresa.
3. Unificar el frontend con la referencia suministrada: fondo celeste, superficies blancas, bordes suaves, botones azules y acciones destructivas diferenciadas.

La revisión anterior era un inventario de hallazgos respaldados, no una certificación de ausencia de otros errores. Estos casos amplían ese inventario.

## Qué se encontró en ambas ramas

| Caso | `develop`, versión revisada | `Branch-G1`, antes de esta intervención |
|---|---|---|
| Una unidad de Coca-Cola 1,5 L, Fideos 500 g y Yerba Mate 1 kg | El catálogo de prueba produce $7.650 en minorista y $6.950 en mayorista, con descuento por línea igual a cero. | Mismo resultado con esos precios de prueba. |
| Elección de tarifa | Los botones permiten elegir la lista; no hay una regla de mínimo de cantidad. No se exige que esa elección corresponda al tipo de cliente registrado. | Misma ausencia de aceptación explícita y validación del tipo de cliente. |
| 100% global en pantalla | La interfaz permite ingresarlo y previsualizar cero. El alta y la calculadora rechazan la venta con total cero. | La interfaz también permite ingresarlo, pero la calculadora del servidor rechaza guardar el total cero. |
| 100% de una línea, con otras líneas cobradas | Puede aceptarse sin una advertencia específica de regalo. | También se acepta sin confirmación explícita. |
| Descuento fijo superior al subtotal | Se recorta al subtotal; si el total queda en cero se rechaza por ese resultado, no por un mensaje específico de exceso del descuento global. | Igual comportamiento en la calculadora. |
| Porcentaje de línea al cambiar cantidad o tarifa | El monto guardado en el carrito puede quedar desactualizado respecto del porcentaje elegido. | Mismo patrón en el frontend. |

**El importe menor de la captura no provenía de un descuento oculto:** era la sustitución de `precioMin` por `precioMay`. Para ese ejemplo, $2.500/$2.200, $950/$850 y $4.200/$3.900 explican exactamente la diferencia. La nueva regla evita seleccionar esa tarifa automáticamente o usarla para un cliente que no corresponde.

No se incorporó ningún umbral de tres productos ni una cantidad mínima por artículo: el usuario eligió una aceptación explícita mediante checkbox.

## Limitación adicional de la fuente real de G3

En las versiones revisadas, `app/Modules/Productos/Models/Producto.php` expone tanto `precio_minorista` como `precio_mayorista` a partir de `precio_unitario` cuando está presente. `ProductoController` publica esos valores también como `precioMin` y `precioMay`.

Por ello, **el catálogo real de G3 puede entregar el mismo importe para las dos tarifas**. Los precios diferenciados del ejemplo anterior están en el catálogo de prueba de Ventas. El checkbox nuevo elige el valor que ofrece la fuente; no genera una rebaja artificial.

No se modificó ese modelo, el controlador de productos ni el esquema de G3. Si se necesitan dos tarifas reales persistidas, queda pendiente acordar su origen y almacenamiento y hacer que G3 las publique diferenciadas. No se puede presentar la demostración con mocks como prueba de esa capacidad del catálogo real.

## Cambios concretos en `Branch-G1`

### Elección de tarifa

- Se reemplazaron los dos botones del POS por **Aplicar precio mayorista**.
- La casilla aparece desmarcada inicialmente y al cambiar de cliente. Para clientes minoristas queda deshabilitada.
- Sin marcarla se utiliza la tarifa minorista, aunque el cliente sea mayorista.
- Se agregó `SalePricePolicy`, compartida por alta y modificación. El servidor exige `aplicar_precio_mayorista=true` si se envía `lista=mayorista` y comprueba que el cliente sea mayorista. El control no depende solamente del HTML.
- `HttpClienteApi` conserva ahora `tipo_cliente`, que antes se perdía durante la adaptación de la respuesta de Clientes. Para contratos anteriores sin ese campo, la política puede consultar la ficha local de G1; un tipo desconocido no habilita la tarifa mayorista.
- El editor del historial tiene el mismo checkbox y valida con las mismas reglas.
- La conversión de solicitudes del catálogo comunica la elección explícita de tarifa a Ventas. Su formulario queda inicialmente en minorista y conserva la confirmación de revisión existente.
- Se actualiza el precio mostrado en las sugerencias del buscador según la tarifa elegida. Al recuperar un carrito se toman los precios vigentes del catálogo, en vez de conservar importes viejos de otra tarifa.

### Bonificaciones del 100%

- Se agregó `sale-confirmation.js`, utilizado por POS y editor. Muestra un diálogo grande con el texto **¿Estás seguro de que querés regalar los productos?** y explica que la mercadería igualmente sale del stock.
- La opción enfocada inicialmente es **Volver y revisar**. Cancelar o pulsar Escape no aplica la bonificación ni guarda la venta. El foco se devuelve al control anterior.
- La advertencia cubre el regalo de una línea y el del pedido completo. Se verifica también al registrar para cubrir carritos recuperados y cambios posteriores.
- El servidor requiere `confirmar_bonificacion=true` cuando el total o alguna línea quedan en cero. No basta con manipular la interfaz o enviar un descuento por API.
- Con confirmación, una venta puede tener total cero. El movimiento de cuenta corriente conserva importe cero, sin generar deuda económica, y el stock sigue su circuito real y durable.
- En el alta se agrega una entrada `bonificacion` a `venta_historial` con usuario, motivo, total y tarifa. En una modificación se registra la confirmación dentro de los datos del evento de modificación existente.
- Se mantienen la protección de reintentos y la idempotencia: una respuesta repetida no crea una segunda venta, un segundo evento de bonificación ni otra operación de stock.
- Los porcentajes superiores al 100%, descuentos negativos y montos superiores al importe al que se aplican siguen rechazándose. Un monto superior al subtotal ahora informa el exceso directamente; no se recorta silenciosamente como forma de admitirlo.

El booleano de confirmación representa la decisión de un operador ya autenticado y autorizado para vender. No crea un acceso público nuevo ni un privilegio de usuario adicional.

### Cálculos de la vista previa

- Los descuentos porcentuales por línea se recalculan al cambiar cantidad o tarifa.
- Se redondea el descuento por unidad a centavos y se usa ese mismo valor en la vista previa y en el cuerpo enviado al servidor.
- Cuando el monto fijo de una línea no puede dividirse exactamente entre sus unidades, se redondea hacia abajo por unidad para no conceder más que lo ingresado. Por ejemplo, $100 entre seis unidades permite $16,66 por unidad ($99,96 en total), sin una bonificación adicional oculta por redondeo.
- El descuento global se redondea antes de restarlo, igual que en la calculadora del backend. También se detecta como regalo un porcentaje que, por redondeo, termina bonificando el importe completo.

Ejemplo comprobado: tres unidades de $100,75 con 10% de descuento por unidad implican $10,08 de descuento cada una, $30,24 en total. No se muestra un resultado para enviar luego otro al backend.

### Estética compartida

- Se amplió `erp-theme.css` sobre las clases ya utilizadas por G1, G2, G3 y G4. Se unificaron fondos, superficies, formularios, tablas, botones, modales, estados y tipografía.
- Se agregó el tema al dashboard, que antes no lo cargaba, y al catálogo y pantallas de acceso.
- Se retiraron fondos crema de estilos compartidos y catálogo. Las superficies de datos son blancas y el fondo general celeste.
- Se conservaron el menú lateral plegable, los controles de navegación y los colores semánticos de error, advertencia y éxito.
- Se corrigieron separación de acciones del historial, encabezados de tabla partidos, anchos de tablas con muchas columnas y la superposición del icono del buscador de inventario.
- Se quitaron importaciones de Google Fonts en las hojas de G2/G3 que las utilizaban. La tipografía compartida tiene respaldo local.
- No se reemplazó HTML funcional ni se cambiaron los mecanismos de apertura de modales existentes para imponer colores. Los documentos de impresión y PDF mantienen su propósito específico.

## Archivos y dominios afectados

| Dominio | Agregado/modificado | Motivo |
|---|---|---|
| G4 — Ventas | `Services/SalePricePolicy.php` nuevo | Validar aceptación de tarifa y tipo de cliente en servidor. |
| G4 — Ventas | `VentaController`, `VentaService`, `CalculadoraVenta`, `ModificarVentaRequest` | Aplicar las reglas tanto en alta como modificación y auditar regalos. |
| G4 — Integración y JSON | `HttpClienteApi`, `VentaResource` | Conservar/publicar el tipo de cliente para elegir la tarifa correctamente. |
| G1 — Catálogo | `CatalogOrderService`, `catalogo/order.blade.php` | Mantener compatible la conversión a venta con el nuevo control de tarifa y elegir minorista inicialmente. |
| Frontend de Ventas | `ventas/ventas.blade.php`, `historial-ventas/historial-ventas.blade.php` | Checkbox, confirmaciones, cálculos y edición coherentes. |
| JavaScript compartido | `public/assets/js/sale-confirmation.js` nuevo | Diálogo accesible de confirmación. |
| Tema compartido | `public/assets/css/erp-theme.css`, `styles.css`, `catalogo.css` | Paleta y componentes comunes. |
| Layouts | Dashboard, layout del catálogo y cuatro vistas de autenticación | Cargar el tema en todas esas experiencias. |
| CSS de módulos | `public/css/g2.css`, `public/css/pigue-claymorphism.css` | Retirar importación externa de fuentes. |
| Pruebas | `VentasApiTest`, `CalculadoraVentaTest`, fixture de `CatalogLotsAndOrdersMySqlTest`; nuevo `Frontend/sales-discounts.test.cjs` | Verificar los nuevos contratos, regalos, reintentos y cálculos. |

**Sí se modificó backend de Ventas, que pertenece a G4**, en la copia local de `Branch-G1`, para implementar las reglas autorizadas. También se ajustó el consumidor de G1 y la presentación compartida. **No se modificó lógica de negocio de G2/G3, migraciones, `.env` ni la base habitual de ningún grupo.** En G2/G3 únicamente se retiraron importaciones de fuentes de las hojas indicadas y se aplicó el tema común por los layouts existentes.

No se agregaron nuevas migraciones: la auditoría de bonificaciones utiliza `venta_historial`, ya existente.

## Contrato para consumidores de Ventas

Se conservan las URLs existentes. Cambia la validación comercial de `POST /api/ventas`, el alta web `/ventas` y `PUT /api/ventas/{id}`:

```json
{
  "cliente_id": 123,
  "lista": "mayorista",
  "aplicar_precio_mayorista": true,
  "items": [{"id": 1, "cantidad": 1, "descuento": 0}]
}
```

El cliente 123 debe ser realmente mayorista. En la modificación no se envía `cliente_id`, porque el cliente de la venta no se cambia.

Para una bonificación completa se agrega, después de la confirmación del operador:

```json
{
  "confirmar_bonificacion": true,
  "descuento_global": {"modo": "porcentaje", "valor": 100}
}
```

Sin confirmar, el servidor devuelve 422 y el error bajo `confirmar_bonificacion`. `lista=mayorista` sin aceptación explícita devuelve 422 bajo `aplicar_precio_mayorista`; aplicarla a un minorista se rechaza bajo `lista`. Los consumidores externos que enviaban mayorista sin confirmación deben adaptar ese cuerpo. Las solicitudes minoristas normales no necesitan los campos nuevos.

## Verificación y evidencia

Se usaron exclusivamente MySQL de QA en 3315 y SQLite en memoria. Las bases nuevas fueron `erp_qa_sales_theme_20261112` para navegador y `erp_qa_sales_regression_20261112` para regresión. No se compartió la base con ventas de navegador con las pruebas que requieren tablas vacías.

| Verificación final | Resultado |
|---|---|
| Suite completa con MySQL exclusivo habilitado | **731 pruebas, 4.591 aserciones, sin fallos, errores ni omisiones.** |
| Pruebas de JavaScript | **52 correctas**, incluidas regresiones de porcentaje, cantidades y redondeo. |
| Recorrido Chrome de QA | 17 pantallas principales: HTTP 200, tema compartido cargado, sin errores de JavaScript ni respuestas fallidas de API observadas. |
| Navegador móvil | Dashboard, historial, inventario y catálogo a 390 px, sin desbordamiento horizontal del documento. Las tablas anchas conservan desplazamiento dentro de su contenedor. |
| Confirmación de regalo | Cancelación, aceptación y enfoque seguro comprobados. Venta de total cero registrada realmente en MySQL de QA con movimiento de stock aplicado. |
| Casos de servidor | Mayorista sin aceptar, minorista intentando tarifa mayorista, regalo de una línea sin confirmar, regalo completo confirmado, modificación y reintento sin duplicación. |

Se conservaron diagnósticos de intentos iniciales: una aserción esperaba el catálogo sin enriquecimiento y se corrigió conservando el catálogo original; otro intento de regresión utilizó por error la base con fixtures del navegador y produjo colisiones/contadores no vacíos. Se creó una base de regresión separada y las pruebas finales pasaron. No se interpretaron esas colisiones como defectos del ERP ni se borraron datos habituales para resolverlas.

Al finalizar se apagaron los servidores temporales de QA. Se verificaron cerrados los puertos 3306, 3315, 8000, 8015 y 8025. No se instaló ninguna tarea programada ni automatización de reemplazo.

Evidencias fuera del repositorio del ERP: `Entregables/Revision_Descuentos_Front_2026-11-12/`, con reproducciones previas de ambas ramas, resultados de PHPUnit/Node, resultados y capturas de navegador. Sus credenciales efímeras de QA no deben incluirse en una entrega ni en Git.

Estos resultados certifican los casos ejecutados, no todas las reglas comerciales imaginables ni el cierre de los demás pendientes de la auditoría de `develop`.

## Pendientes que no se presentan como resueltos

1. Fuente real de precios diferenciados de G3, explicada antes. No hay descuento inventado para compensarla.
2. El esquema histórico de Ventas no guarda una columna de lista de precios. El editor anterior la deduce comparando la primera línea con el catálogo actual; si cambian los precios, esa deducción puede ser ambigua. Esta intervención aplica la aceptación explícita y el recálculo vigente, sin inventar una lista histórica ni migrar operaciones anteriores.
3. Los restantes problemas del informe de auditoría de `develop`, que no fueron incorporados en bloque a ninguna rama durante este trabajo.
4. La uniformidad visual se revisó en las pantallas enumeradas; no equivale a probar todas las combinaciones de datos, roles, documentos de impresión y tamaños posibles.

## Cómo revertir estos cambios

No se ejecutó ninguna reversión. Para revertir después de crear un commit dedicado, usar **`git revert <hash-del-commit>`** y revisar el resultado. Esto genera un commit inverso y conserva el historial.

Si todavía no se creó ese commit, se pueden restaurar **únicamente los archivos enumerados** desde `fecf67fe895acc6d94755a7121b9a57d5e908b06`, revisando primero que no tengan trabajo posterior que se quiera conservar. Retirar sólo los archivos nuevos de esta intervención: `SalePricePolicy.php`, `sale-confirmation.js`, `sales-discounts.test.cjs` y este informe. No ejecutar una limpieza global del árbol ni borrar migraciones.

No hay cambio de esquema que revertir. Las ventas que alguien llegue a registrar después de desplegar esta versión son operaciones de negocio: no deben borrarse para revertir código. Cualquier devolución/anulación debe hacerse por su circuito y conservar auditoría. Volver a la versión anterior implica recuperar también sus límites: total cero rechazado y líneas gratuitas sin confirmación.
