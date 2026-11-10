# Cierre funcional: clientes, compras, crédito y devoluciones

Fecha: 9 de noviembre de 2026. Etapa correspondiente a los once puntos de la última tabla solicitada. Este informe complementa los informes anteriores; no los reemplaza ni declara terminado todo el ERP.

## 1. Alcance y estado final

Se modificaron G1, G3 y G4 y sus puntos de comunicación con G2, con la autorización expresa de intervenir en cualquier grupo. Se conservaron los cambios que ya estaban en el directorio. No se hizo commit, push, reset ni limpieza del trabajo previo.

| Prioridad | Punto solicitado | Problema y consecuencia | Resultado de esta etapa |
|---|---|---|---|
| Alta | Recepción de compras | El producto no identifica una línea cuando se compra en varias presentaciones. Se podía imputar la recepción a un detalle equivocado. | La consulta y el formulario identifican detalle, producto, unidad y equivalencia; el POST manda el identificador exacto. |
| Alta | Integración con develop | El consumidor de Entregas había vuelto a pedir ventas `pendiente`, mientras la planificación y el remito exigen ventas confirmadas. | Se comprobó el cambio remoto y se conserva `confirmada` como condición compartida, con prueba contra la API real interna. El historial ofrece confirmar pedidos. |
| Alta | Concurrencia de G3 | Revisar estados o calcular precios antes del bloqueo permite actualizaciones perdidas o acciones incompatibles. | Transacciones, lectura bloqueada y validación posterior al bloqueo en edición/envío/cancelación de órdenes y modificaciones de precios; pruebas con procesos independientes. |
| Alta | Límite de crédito | No había límite por cliente ni comprobación al confirmar. | Campo opcional, consulta de exposición y disponible, vista previa y validación obligatoria en servidor. También se impide eludirla aumentando o restaurando ventas confirmadas. |
| Alta | Devoluciones comerciales | Faltaba un circuito asociado a la venta y a la recepción física. | Pantalla, API, cantidades parciales, motivo, condición y movimientos de stock para mercadería apta, con reintentos identificados. |
| Alta | Notas de crédito | Faltaba vincular devolución, crédito y cuenta corriente sin duplicar efectos. | Una nota interna por devolución, movimiento vinculado e importes netos derivados de la venta original. Descarga PDF. |
| Media | Relaciones antiguas | Cliente/User apuntaban a claves o al modelo Cobro incorrectos. | Relaciones de ventas y cobros corregidas y comprobadas con registros reales de prueba. |
| Media | Permiso del contador | Podía ver deudores pero no abrir la ficha enlazada. | Acceso de lectura a la ficha, conservando la prohibición de alta, edición, pagos y recepción de devoluciones. |
| Media | Sesiones | Configuración antigua de 120 minutos frente al requisito de 30. | Valor predeterminado y `.env` local en 30; el middleware limita la inactividad a 30 incluso con configuración antigua de 120. |
| Media | Resumen de saldos | Un cambio neto del período no equivale al total pagado ni a la deuda al cierre. | Separación de pagos netos del período, deuda al corte y deuda actual, incluyendo saldo inicial y reversión de pagos. Mismo cálculo conceptual en pantalla y PDF. |
| Media | Observaciones de ventas | Para editar comentarios se dependía del circuito que cambia productos e importes. | PATCH separado, disponible después del pago, con auditoría del texto anterior/nuevo y sin modificar cuenta corriente, stock o estado. |

## 2. Información recuperada del repositorio

Se consultó `origin/develop` mediante fetch, sin mezclar automáticamente todo el historial con el trabajo local:

- Referencia remota examinada: [`ae9fba1d294dc153249d1d2658d75c426a39a1cd`](https://github.com/rapiriz/ERP-Juan23/commit/ae9fba1d294dc153249d1d2658d75c426a39a1cd).
- Su padre [`efbbe2a130867bf6cabd741f3366cf9cc15f7427`](https://github.com/rapiriz/ERP-Juan23/commit/efbbe2a130867bf6cabd741f3366cf9cc15f7427) cambia el filtro de Entregas de `confirmada` a `pendiente`. No se copió ese retroceso contractual. La implementación local consulta confirmadas y el servicio de planificación sigue exigiéndolas.
- Se recuperó el trabajo de G3 sobre costo de reposición, proveedor principal, recargo e incrementos por proveedor. Los parches de Producto y su prueba se aplicaron; Compras, Recepción e Inventario requirieron integración manual para preservar las correcciones locales anteriores.
- Se revisaron las historias [#157](https://github.com/rapiriz/ERP-Juan23/issues/157), [#160](https://github.com/rapiriz/ERP-Juan23/issues/160), [#161](https://github.com/rapiriz/ERP-Juan23/issues/161) y [#97](https://github.com/rapiriz/ERP-Juan23/issues/97) como respaldo de límites de crédito, devoluciones, notas y comentarios. Que una historia esté cerrada no se tomó como prueba de que el circuito local funcionara.

La rama local conserva su HEAD anterior: esto es una adaptación de código, no un merge completo de develop. Antes de publicar habrá que resolver la integración del historial Git con los cambios locales; no usar un push forzado para saltear ese paso.

## 3. Compras, presentaciones y costos

### Identificación de la recepción

`CompraController::show` expone `id_detalle`/`id_detalle_compra`, `id_producto`, `id_unidad`, denominación y `equivalencia_base`. `DetalleCompra::unidad` permite leer la presentación real. El formulario muestra esa información y envía cada recepción con su identificador de línea.

Ejemplo: comprar el mismo producto en unidades y cajas de 12 genera dos detalles distintos. Recibir una caja afecta su detalle y suma 12 unidades base; no se busca arbitrariamente la primera línea del producto.

### Estado y cantidades de órdenes

`PurchaseOrderProgress` convierte cantidades a unidad base y suma todas las compras activas asociadas a la orden. Rechaza cantidades o productos que exceden lo comprometido. El estado recibido parcial/completo depende del total físicamente recibido de todas las compras, no únicamente de la última recepción.

Una compra asociada exige orden enviada o parcialmente recibida y proveedor coincidente. El cambio manual de estado no puede declarar una recepción que los registros no respaldan.

### Bloqueos y precios

Las órdenes bloquean su cabecera antes de verificar si son editables, enviables o cancelables. Se bloquean productos en orden estable y se vuelven a validar las unidades dentro de la transacción. Envío y cancelación dejan auditoría transaccional: un fallo no deja el estado cambiado sin su registro.

Las modificaciones de producto y los aumentos masivos calculan sobre valores leídos después de `lockForUpdate`. El costo de reposición se sincroniza bajo bloqueo. Dos aumentos consecutivos de 10% sobre 150 deben terminar en 181,50, no en 165 por una lectura simultánea antigua.

El costo pactado se normaliza a la unidad individual: una caja de 12 a 120 equivale a 10 por unidad base. El historial del proveedor conserva, en cambio, 120 con la unidad caja correspondiente. Se quitó el respaldo por una unidad base global ajena al producto. La interfaz permite elegir base de incremento y proveedor según el trabajo recuperado de G3.

## 4. Crédito por cliente

### Configuración y permisos

Se agregó `clientes.limite_credito`, decimal con dos lugares y nullable, al formulario de clientes, validadores, escritura y recurso JSON. `null` significa sin límite configurado; `0` impide nueva exposición de crédito y no equivale a ilimitado. No se asignaron límites nuevos a clientes existentes.

El administrador puede configurarlo. Administrador, repartidor y contador pueden consultar el estado conforme a las rutas autenticadas de Ventas. Cambiar el límite deja evento con valor anterior/nuevo y actor.

### Cálculo y aplicación

`CreditLimitService` usa el libro de cuenta corriente y los pagos de `FinancialReadService`. Para liberar crédito no toma como efectivo un cheque aún no conciliado ni un pago histórico positivo sin enlace al cobro canónico. Un saldo a favor legítimo sí amplía el importe disponible.

La exposición sigue el libro actual: las ventas pendientes ya registradas también generan deuda en este ERP. No se cambió retroactivamente ese criterio contable; es una política conservadora y debe tenerse presente al interpretar el disponible. Reducir el límite no borra ni cancela ventas anteriores.

El POS presenta disponible y advertencia si el carrito lo supera. Esa advertencia no sustituye al control: el servidor bloquea y recalcula bajo el bloqueo del cliente al confirmar o al salir de pendiente hacia otra fase. La edición que incrementa una venta confirmada y la restauración a una fase confirmada también verifican el límite. Una reducción de importe puede corregir exposición sin quedar bloqueada por la deuda anterior.

Un rechazo devuelve 422 y revierte la operación completa. El intento se audita después de deshacer la transacción rechazada, para conservar la evidencia del bloqueo. La comprobación concurrente con un pago toma el saldo posterior al pago, no una fotografía anterior a la espera por el bloqueo.

## 5. Devoluciones comerciales y notas

### Entrada y reglas

Nueva pantalla `/devoluciones`, integrada al menú y estilo compartidos. El administrador consulta una venta, selecciona sus renglones, cantidades recibidas y condición; el contador puede consultar el historial y comprobantes.

Se requiere venta confirmada o posterior, no anulada, sin ajustes de stock pendientes y sin una entrega aún sin resolver. Los estados logísticos admitidos para una entrega asociada son `entregado` o `no_entregada`; una entrega en preparación/camino debe resolverse primero.

Las condiciones son `apto`, `danado` y `vencido`. Sólo lo apto vuelve al stock disponible. Lo dañado o vencido queda registrado como recepción física con crédito, sin aumentar existencias vendibles. El circuito es distinto a la devolución informativa de una rendición.

Se valida pertenencia de cada detalle a la venta, cantidad entera positiva y acumulado aún no devuelto. El bloqueo de cliente, venta y detalles evita acreditar dos veces la última unidad disponible cuando llegan solicitudes simultáneas.

### Importes y trazabilidad

Se usa el precio original y el subtotal de cada línea. El descuento global se reparte proporcionalmente en centavos con aritmética entera y distribución de restos. En devoluciones sucesivas se calcula la diferencia acumulada, para que devolver todas las unidades recupere exactamente el neto original, incluidos centavos indivisibles.

Si una venta histórica tiene total/detalles/descuento inconsistentes, se rechaza con 409: no se inventa un crédito. Cambiar el precio actual del catálogo no altera la base de devolución.

Por cada recepción se guardan devolución, detalles y condición, nota de crédito, movimiento de cuenta corriente e historial de la venta. La nota se aplica primero a la venta original; el excedente queda como crédito utilizable. En una venta ya pagada produce saldo a favor. No genera una devolución de efectivo ni una salida de caja automática.

La nota descargada es un **comprobante interno sin validez fiscal**; esta etapa no implementa emisión fiscal ni comunicación con ARCA/AFIP.

### Reintentos y stock

La solicitud exige UUID `request_key`. Misma clave, contenido y actor recuperan la operación previa sin repetir crédito ni stock; contenido o actor incompatible se rechaza. El navegador conserva el payload pendiente por usuario/venta en sessionStorage para permitir reintento si se pierde la respuesta.

El stock usa la bandeja duradera de la etapa anterior. Si un servicio remoto falla puede quedar `stock_estado: pendiente`, con recuperación por `ventas:reenviar-stock`; no se presenta como aplicada una operación pendiente. La integración interna local fue probada con stock real de QA.

Después de una devolución se bloquean modificaciones de productos, anulaciones y restauraciones ordinarias de esa venta para proteger sus asociaciones e importes. El comentario aislado sigue editable. Las devoluciones y sus notas son inmutables en esta etapa: no se agregó una anulación de devolución ni un reintegro monetario automático. Una corrección posterior requiere un circuito compensatorio auditable; nunca eliminar filas financieras manualmente.

## 6. Relaciones, lectura del contador y sesión

- `Cliente::ventas` usa `id_cliente`; `User::ventas`, `id_usuario`.
- Ambos modelos enlazan `cobros` al modelo real de Conciliación, con `id_cliente` e `id_usuario` respectivamente.
- La política de detalle de cliente admite contador y la ruta de detalle sale del grupo operativo que lo bloqueaba. No se amplían los permisos de creación, edición o pago. Se verificaron las denegaciones.
- `.env.example` y `config/session.php` usan 30 minutos. En el `.env` local sólo se cambió `SESSION_LIFETIME` a 30; sus credenciales no se publican. `EnsureCurrentSession` aplica máximo de 30 minutos de inactividad aunque otra instalación conserve 120. El tiempo se renueva con actividad válida; no es un cierre cada 30 minutos desde el login.

## 7. Resumen de saldos y observaciones

`AccountSummary` en PHP y `account-summary.js` separan:

1. Pagos netos dentro del período seleccionado: pagos/cobros menos sus reversiones. Una nota de crédito no se cuenta como pago recibido.
2. Deuda al corte: saldo inicial más todos los movimientos hasta la fecha final.
3. Deuda actual: todos los movimientos, aun posteriores al período consultado.
4. Saldo a favor al corte, cuando corresponda.

La tabla arrastra el saldo inicial previo al filtro. Los totales generales abarcan los clientes visibles según los filtros; no confunden el incremento de deuda del período con el saldo final. El PDF utiliza el mismo criterio de corte.

El nuevo PATCH de observaciones sólo acepta texto de hasta 150 caracteres y permite vaciarlo. Conserva estado, importes, productos, stock y movimientos. Intenta enviar campos financieros expresamente prohibidos produce validación; otros campos no se utilizan para modificar la venta. El historial conserva texto anterior y nuevo, actor y fecha; repetir el mismo texto no agrega un cambio artificial.

## 8. Rutas nuevas y uso básico

Todas las rutas de esta tabla requieren autenticación y autorización. No se publicó la información financiera al catálogo público.

| Método | Ruta | Uso / permiso |
|---|---|---|
| GET | `/api/ventas/clientes/{id}/credito` | Estado de crédito: administrativo/repartidor/contador. |
| PUT | `/api/ventas/clientes/{id}/credito` | Configurar límite: administrativo. |
| GET | `/api/ventas/devoluciones?cliente_id=&venta_id=&page=` | Historial paginado de 20 registros. |
| GET | `/api/ventas/devoluciones/{id}` | Detalles, nota y saldo del cliente. |
| POST | `/api/ventas/{id}/devoluciones` | Recepción y crédito: administrativo. |
| PATCH | `/api/ventas/{id}/observaciones` | Comentario aislado: administrativo/repartidor. |
| GET | `/devoluciones` | Pantalla: administrativo/contador. |
| GET | `/devoluciones/{id}/nota` | Descarga del comprobante interno: administrativo/contador. |

En Thunder Client, usar `Accept: application/json`, `Content-Type: application/json` y Bearer de usuario obtenido por el login POST existente. Un token de servicio de lectura no habilita estas escrituras. Desde el frontend se conserva el mecanismo de sesión/CSRF de la aplicación.

Ejemplo de límite (PUT):

```json
{"limite_credito": 150000.50}
```

Para quitar el límite enviar `{"limite_credito": null}`; para crédito cero enviar `{"limite_credito": 0}`.

Ejemplo de devolución (POST): obtener antes `id_detalle` y `cantidad_devuelta` desde GET `/api/ventas/{id}`. Reemplazar los identificadores por los de esa venta y generar un UUID nuevo para una nueva operación; conservarlo para reintentar la misma.

```json
{
  "request_key": "8f4e741c-95c8-4cb9-a72b-03ac6965ea64",
  "motivo": "Recepción física de una unidad devuelta",
  "items": [{"detalle_venta_id": 123, "cantidad": 1, "condicion": "apto"}]
}
```

Ejemplo de comentario (PATCH): `{"observaciones":"Cliente solicita entrega por la mañana"}`. Enviar cadena vacía para quitarlo.

Confirmación desde Historial: el botón consulta crédito y ejecuta PATCH `/api/ventas/{id}/estado` con `{"estado":"confirmada"}`; el servidor verifica nuevamente. Esto permite entregar únicamente pedidos confirmados sin volver a habilitar transiciones logísticas arbitrarias desde la pantalla.

## 9. Base de datos y preservación

Nueva migración modular: `app/Modules/Ventas/database/migrations/2026_11_09_000007_complete_commercial_workflows.php`.

Agrega el campo nullable `clientes.limite_credito` y cuatro tablas: `g4_credit_events`, `g4_devoluciones`, `g4_devolucion_detalles`, `g4_notas_credito`. No elimina tablas, lotes, columnas históricas ni registros. No recrea la base, no importa SQL histórico y no vuelve a ejecutar seeders.

Se probó primero en el MySQL de QA aislado 3315. Después se respaldó la base habitual con mysqldump y se ejecutó exclusivamente esa migración en `sistema_gestion`, MySQL local 3306.

Resultado verificado:

| Comprobación | Resultado |
|---|---|
| Tablas anteriores / posteriores | 93 / 97 |
| Registros de negocio anteriores, sin contar historial de migraciones | 1.343 |
| Tablas anteriores cuyos datos o columnas anteriores cambiaron | Ninguna |
| Columna nueva en clientes | `limite_credito`, sin imponer un límite a clientes existentes |
| Registros iniciales en las cuatro tablas nuevas | 0 |

La comparación usa huellas SHA-256 de todas las filas y columnas preexistentes, ordenadas para evitar falsos cambios por orden de lectura. En clientes compara las columnas anteriores: añadir un campo no debe confundirse con alterar los valores de nombre, documento, dirección, etc. El historial `migrations` agrega el registro normal de la ejecución y se excluye de esa comparación de datos de negocio.

Otra instalación debe respaldar su propia base y ejecutar la migración para usar las funciones nuevas. Commit/push no modifica por sí solo una BD remota, pero ejecutar esta migración sí agrega estas estructuras. No subir los volcados privados ni el `.env`.

## 10. Verificaciones y resultados

Las pruebas de esta etapa no cargaron ventas, pagos o devoluciones ficticias en la base habitual. Las de MySQL usan una base `erp_qa_*` en 3315, con transacciones revertidas; las carreras usan un clon separado que se retiró al terminar.

| Verificación | Evidencia / resultado |
|---|---|
| Integración MySQL actual + dos etapas anteriores | 58 pruebas, 433 aserciones, correctas. |
| Frontend Node | 46 pruebas correctas. Incluyen contrato exacto de recepción, resumen de saldo y advertencia de crédito. |
| Pantallas | Carga HTTP 200 de Devoluciones, Historial de ventas, Ventas, Saldos, Inventario y Compras; 12 scripts inline renderizados con sintaxis JavaScript válida. |
| PHP | Pint en los archivos PHP de esta etapa y comprobación de sintaxis. |
| Procesos simultáneos | Cinco escenarios reales con conexiones independientes, detallados debajo. |
| Regresión general aislada | 532 pruebas, 2.447 aserciones, 56 omitidas; persisten cuatro fallos y una prueba riesgosa ya registrados previamente. Ejecutada antes de los dos últimos casos adicionales de crédito. |
| Regresión relacionada final | 190 pruebas, 1.085 aserciones, correctas. |

Casos concurrentes documentados en `concurrencia-resultados.json`:

- Cancelar/enviar la misma orden: una acción prospera y la incompatible se rechaza, sin estado doble.
- Enviar/editar la misma orden: enviada queda protegida frente a la edición simultánea.
- Dos aumentos del 10%: precio 150 → 181,50 y dos entradas de historial.
- Dos devoluciones de la única unidad vendida: una respuesta 201 y otra 422; una sola nota y una sola reposición de stock.
- Pago de 100 simultáneo con confirmación de venta de 150 y límite 50: deuda final 50, pago y confirmación válidos, sin lectura antigua del saldo.

Las pruebas nuevas también cubren caja/pago real, cheque pendiente que no libera crédito, pago histórico sin respaldo, saldo a favor por devolución de venta pagada, límite ilimitado/cero, rechazo que no cambia total/stock, retorno dañado, devolución excesiva o de detalle ajeno, roles, PDF, redondeo y notas aplicadas a su venta original. Incluyen agregado de varias compras de una orden, relación de modelos y sesión antigua de 120 minutos limitada a 30.

Los cuatro fallos de la regresión general corresponden a tres métodos de `G4DependencyRegistrationTest` con mocks/expectativas antiguos y un método de `G4SalesSchemaTest` que espera la vista `ventas` cuando se utiliza `ventas.ventas`. No se presentan como resueltos por esta entrega ni se cambiaron para ocultar fallos. La prueba riesgosa pertenece a ese mismo fixture antiguo. Siete clases antiguas con acceso inseguro/fijo al MySQL habitual se excluyen mediante la configuración externa de QA; la base habitual se mantuvo apagada durante las pruebas que requieren aislamiento. La prueba de costo de Producto recuperada del remoto está entre ellas y no se presenta como ejecutada sobre la base del usuario.

La validación de pantallas es de renderizado y scripts/pruebas funcionales; no equivale a una revisión visual manual de cada resolución y navegador.

## 11. Limitaciones y puesta en marcha

- Se completan los once puntos descritos con el alcance indicado. No significa que no existan otros errores del proyecto ni reemplaza una auditoría completa nueva.
- No hay emisión fiscal de notas ni reintegro de dinero automático. Las correcciones de devoluciones persistidas requieren compensación financiera/stock explícita.
- Los datos históricos inconsistentes se rechazan antes de acreditar; esta etapa no reescribe esas ventas.
- `VENTAS_INTEGRACION=internal` permite consumir las APIs de la misma instancia sin exigir un segundo servidor HTTP. Para servicios externos deben configurarse transporte, URL y tokens. Los ajustes de stock pendientes requieren el programador o ejecución del comando de reenvío existente.
- Para usar el sistema iniciar MySQL desde XAMPP y Laravel con el comando habitual `C:\xampp\php\php.exe artisan serve`. No se dejó un servidor Laravel iniciado por esta tarea.
- No se hizo commit ni push. Los cambios previos del directorio siguen presentes y no todos pertenecen a esta etapa.

## 12. Inventario exacto de esta etapa

Este inventario se calcula contra el respaldo tomado al comenzar esta etapa, no contra HEAD, porque ya había trabajo local anterior. `Modificado` conserva el archivo previo en el ZIP; `Nuevo` no existía en ese respaldo. `.env` no se incluye en Git: únicamente su tiempo de sesión local cambió a 30 minutos.

| Estado | Archivo |
|---|---|
| Modificado | `.env.example` |
| Modificado | `README.md` |
| Modificado | `app/Modules/Clientes/Models/Cliente.php` |
| Modificado | `app/Modules/Clientes/Policies/ClientePolicy.php` |
| Modificado | `app/Modules/Clientes/Requests/Api/ClientWriteRequest.php` |
| Modificado | `app/Modules/Clientes/Resources/ClientResource.php` |
| Modificado | `app/Modules/Clientes/Services/ClientFields.php` |
| Modificado | `app/Modules/Clientes/Services/ClientWriter.php` |
| Modificado | `app/Modules/Compras/Controllers/CompraController.php` |
| Modificado | `app/Modules/Compras/Controllers/RecepcionController.php` |
| Modificado | `app/Modules/Compras/Models/DetalleCompra.php` |
| Modificado | `app/Modules/CuentaCorriente/Controllers/CuentaCorrienteController.php` |
| Nuevo | `app/Modules/CuentaCorriente/Services/AccountSummary.php` |
| Modificado | `app/Modules/CuentaCorriente/Services/FinancialReadService.php` |
| Modificado | `app/Modules/CuentaCorriente/Services/SalePaymentEvidence.php` |
| Modificado | `app/Modules/Login/Middleware/EnsureCurrentSession.php` |
| Modificado | `app/Modules/Login/Models/User.php` |
| Modificado | `app/Modules/PedidosDeCompra/Controllers/OrdenCompraController.php` |
| Nuevo | `app/Modules/PedidosDeCompra/Services/PurchaseOrderProgress.php` |
| Modificado | `app/Modules/Productos/Controllers/ProductoController.php` |
| Modificado | `app/Modules/Productos/Models/Producto.php` |
| Modificado | `app/Modules/Proveedores/Services/HistorialPrecioService.php` |
| Nuevo | `app/Modules/Ventas/Controllers/CommercialController.php` |
| Modificado | `app/Modules/Ventas/Controllers/VentaController.php` |
| Nuevo | `app/Modules/Ventas/Exceptions/CreditLimitExceeded.php` |
| Modificado | `app/Modules/Ventas/Resources/VentaResource.php` |
| Nuevo | `app/Modules/Ventas/Services/CommercialReturnService.php` |
| Nuevo | `app/Modules/Ventas/Services/CreditLimitService.php` |
| Modificado | `app/Modules/Ventas/Services/VentaService.php` |
| Nuevo | `app/Modules/Ventas/database/migrations/2026_11_09_000007_complete_commercial_workflows.php` |
| Modificado | `app/Modules/Ventas/routes/api.php` |
| Modificado | `config/session.php` |
| Nuevo | `docs/informe-cierre-funcional-g1-g3-g4-2026-11-09.md` |
| Nuevo | `public/assets/js/account-summary.js` |
| Nuevo | `public/assets/js/commercial-returns.js` |
| Modificado | `resources/views/clientes/form.blade.php` |
| Modificado | `resources/views/compras/index.blade.php` |
| Nuevo | `resources/views/devoluciones/index.blade.php` |
| Nuevo | `resources/views/devoluciones/nota.blade.php` |
| Modificado | `resources/views/historial-ventas/historial-ventas.blade.php` |
| Modificado | `resources/views/inventario/index.blade.php` |
| Modificado | `resources/views/partials/sidebar-links.blade.php` |
| Modificado | `resources/views/saldos/saldo.blade.php` |
| Modificado | `resources/views/ventas/ventas.blade.php` |
| Modificado | `routes/web.php` |
| Nuevo | `tests/Feature/CommercialCompletionMySqlTest.php` |
| Modificado | `tests/Feature/G1ReadApiTest.php` |
| Modificado | `tests/Feature/ProductoCostoRecargoTest.php` |
| Nuevo | `tests/Frontend/commercial-completion.test.cjs` |

## 13. Respaldo y cómo revertir esta etapa

El directorio externo `Entregables/Cierre_Funcional_2026-11-09/` contiene:

- `codigo-antes.zip` y `codigo-antes.json`: versión y huellas de 631 archivos al inicio, incluyendo el trabajo anterior.
- `cambios-etapa.json`: inventario con estado y huella final de cada archivo modificado/nuevo.
- `env-antes.privado`: copia privada del entorno anterior; no publicarla.
- `base-antes.sql` y `base-antes.json`: respaldo previo a esta migración y huellas de datos originales.
- `verificacion-base-local.json`, resultados de pruebas y concurrencia.
- `revertir-codigo.py`: restauración selectiva y comprobable del código de esta etapa, sin tocar MySQL.

### Reversión de código

1. Apagar Laravel y hacer un respaldo actual de la base y de cualquier trabajo posterior.
2. Ejecutar primero, desde la raíz `Repositorio`, la comprobación sin aplicar:

```powershell
python .\Entregables\Cierre_Funcional_2026-11-09\revertir-codigo.py
```

3. Si las huellas coinciden y se quiere revertir, ejecutar el mismo comando agregando `--aplicar`. Restaura sólo los archivos que existían antes y retira sólo los nuevos de esta etapa. Rechaza archivos alterados posteriormente y valida que sus rutas permanezcan dentro del proyecto. No usar `git reset --hard`, `git clean` ni restaurar todo desde HEAD: perderían las etapas anteriores.
4. El script no modifica `.env`. Si se necesita restaurar el tiempo anterior, cambiar únicamente `SESSION_LIFETIME` al valor de `env-antes.privado`, sin sobrescribir nuevas credenciales o tokens. El requisito funcional vigente sigue siendo de 30 minutos.
5. Limpiar la configuración y vistas compiladas con `C:\xampp\php\php.exe artisan config:clear` y `C:\xampp\php\php.exe artisan view:clear` antes de volver a arrancar.

### Reversión de base y operaciones

El `down()` es conservador y no elimina créditos, devoluciones ni auditoría. `migrate:rollback` no es un método para deshacer devoluciones y podría retirar el registro de migración sin quitar las estructuras; no usarlo como reversión de negocio.

Si sólo se revierte código, dejar las tablas y la columna nuevas no altera los valores originales. Si hubo devoluciones/pagos/ventas posteriores, **no importar el SQL anterior encima de la base actual**: eliminaría operaciones posteriores. Primero respaldar y revisar cada crédito y ajuste para preparar movimientos compensatorios.

Sólo en un entorno desechable o cuando se confirme que no existe ninguna operación posterior, puede restaurarse `base-antes.sql` en una base vacía separada y verificarse antes de cambiar la conexión de la aplicación. No importar sobre la base compartida ni borrar tablas de otros grupos. El respaldo antes de la migración permite una restauración completa del estado anterior, pero no conserva operaciones realizadas después de ese respaldo.

La restauración de código y la reversión contable son procedimientos diferentes: esta entrega conserva la trazabilidad para evitar que una reversión técnica borre evidencia financiera.
