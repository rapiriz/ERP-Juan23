# Cierre de pendientes y revisión integral — 10 de noviembre de 2026

Este informe corresponde a la última tabla de pendientes solicitada, continuada el 10/11. Complementa los informes anteriores. Describe cambios comprobados, no una garantía de ausencia de cualquier falla futura. Se intervinieron G1, G2, G3, G4 y recursos compartidos con la autorización expresa del usuario. No se hizo commit, push, reset ni limpieza del trabajo previo.

## 1. Resultado por punto solicitado

| Prioridad | Punto / causa encontrada | Corrección concreta y ubicación | Estado y comprobación |
|---|---|---|---|
| Alta | Unidades: dos GET apuntaban a `show` inexistente. | `Stock/Controllers/UnidadController.php`: detalle acotado a producto y unidad; `Productos/routes/web.php`: nombre `idUnidad` y parámetros numéricos. | Ambos prefijos responden; unidad ajena/inexistente o ID no numérico devuelve 404. Pruebas de rutas y de operaciones. |
| Alta | Costos: la diferencia binaria de un centavo podía quedar debajo del umbral `0.01`. | `Productos/Models/Producto.php`: comparación de centavos redondeados; actualización consistente de costo, recargo, precio e historial. | Cambio 100,01 → 100,02 y reenvío sin historial duplicado comprobados. |
| Alta | Primer proveedor: sin principal explícito podía no existir fuente para sincronizar el costo. | `Proveedores/Controllers/ProveedorController.php`, `HistorialPrecioController.php`, modelo Producto: elección del primero, respaldo único válido y bloqueos ordenados. | Pruebas recuperadas de develop y regresiones locales aprobadas. No se asignan costos arbitrarios entre varios proveedores. |
| Alta antes de Linux | Validaciones G3 consultaban tablas en mayúsculas diferentes de las migraciones. | 26 reglas restantes en 9 controladores, 3 clases de pruebas y control de nombres canónicos en `scripts/audit-source.php`. | Auditoría sin errores. Las 2 reglas de Categoría de la tabla anterior ya estaban corregidas. No se renombraron tablas; se conservaron los nombres canónicos mayúsculos de G2. Linux real pendiente de CI. |
| Alta | Compras confundía mercadería pendiente con dinero adeudado. | Controladores Compra/Recepción, modelos Compra/OrdenCompra, servicios `PurchaseLifecycle` y `PurchaseOrderProgress`, vista `compras/index.blade.php`. | Se separan `saldo_pendiente`, `total_pagado`, `saldo_a_pagar` y `estado_pago`; pago posible aunque la recepción esté completa. Recepción calcula valor por línea y precio, no por proporción de unidades heterogéneas. |
| Alta | Crédito aceptaba notas históricas sin documento verificable; otra comprobación podía aceptar sólo un `venta_id`. | Nuevo `CuentaCorriente/Services/CreditNoteEvidence.php`, `SalePaymentEvidence.php` y `Ventas/Services/CreditLimitService.php`. | Una misma evidencia canónica para liberar crédito y acreditar pago. Las notas sin respaldo siguen visibles pero no reducen exposición ni pagan ventas. |
| Alta | Develop incorporó reglas nuevas de compras y pagos; una copia completa quitaba protecciones locales. | Integración selectiva de compras, órdenes, sugerencias, costo, proveedor y anulación de pagos. Dos migraciones aditivas. | Código contrastado hasta `7d521adc`. Se retuvo selección de ventas confirmadas para entregas y conservación de documentos anulados. No es un merge del historial Git. |
| Alta | Finanzas históricas: faltaban vínculos verificables. | Ampliación de `Shared/Services/FinancialIntegrityReview.php` y pantalla `/operaciones/historicas`. | Relectura real: 40 asientos sin pago canónico, 38 cobros sin aplicación y 15 notas de crédito sin respaldo; 0 pagos con respaldo inconsistente. Son categorías solapables, no 93 casos distintos. Requieren revisión documental. |
| Alta | Tablas históricas adicionales sin equivalencia confirmada. | Nuevo `Shared/Services/HistoricalSchemaReview.php`, comando `SchemaInventory --historicas` y pantalla administrativa. | 16 tablas adicionales y 92 filas inventariadas. Se preservaron todas. El lote recuperado en la etapa anterior explica pasar de 17 a 16 extras. No se copiaron ni borraron por parecido de nombre. |
| Media | Compra inválida devolvía 500 en una regla de negocio. | `CompraController.php`: validación financiera 422 y conflictos de estado/proveedor 409 dentro de transacción. | Pruebas confirman respuesta, conservación de datos y reversión integral. El 500 queda para fallos inesperados. |
| Media | APIs anunciadas, fotos y programador no coincidían con el código/entorno. | Alias de stock, inventario de rutas, contratos G1/G3, README y guía de validación. | 484 rutas verificadas; 15 imágenes reales válidas. La tarea Windows sigue retirada: no hay ejecución automática local. |
| Antes de publicar | CI/Linux no tenía ejecución remota. | `.github/workflows/validation.yml`: instalación, suites, auditorías, restauración/actualización y navegador sobre Ubuntu/MariaDB. | Workflow preparado; GitHub devolvió 0 ejecuciones. No se afirma que Linux ya pasó. Publicación y ejecución pendientes. |
| Media | Estilos/dependencias diferentes y CDN sin versión fija. | Compilación en `tools/frontend`; assets y licencias en `public/assets/vendor`; `erp-theme.css` compartido por ambos layouts. | Tailwind/Lucide/Bootstrap/Chart/Font Awesome servidos localmente con versiones y huellas; pruebas de modales, móvil, contraste y navegación. No equivale a certificación WCAG completa. |
| Baja | Cierres mostraban IDs; QA exigía APP_KEY manual. | `CajaService.php`, vistas de cierres/PDF; `scripts/start-browser-qa.py`. | Nombres históricos con respaldo ID/Sistema; QA genera clave efímera sólo para su proceso, sin editar `.env`. |
| Antes de compartir | Entrega podía incluir entornos, dumps, comprobantes y dependencias instaladas. | Nuevo `scripts/package-release.py`: lista permitida, exclusiones, revisión de secretos de entorno y manifiesto SHA-256. | ZIP de fuentes separado del proyecto. No se borraron archivos históricos para producirlo ni se incluyeron respaldos privados. |

Las rutas de archivos abreviadas de esta tabla están bajo `app/Modules/`, salvo las indicadas desde la raíz. El [inventario de archivos de esta etapa](inventario-cambios-cierre-2026-11-10.md) distingue lo nuevo y lo modificado respecto de la copia tomada al comenzar, sin atribuirle los cientos de cambios previos.

## 2. Integración con GitHub y criterios conservados

Se consultaron las [ramas](https://github.com/rapiriz/ERP-Juan23/branches), las 191 issues, 12 PR y 65 comentarios disponibles. Se compararon las puntas con el relevamiento anterior: cambiaron develop, develop-g3 y la rama personal de rendiciones/cobros de G2; las otras 20 conservaron su punta. Durante el trabajo develop avanzó desde `c59cb99` a [`7d521adcaafc83d7134c7e59e1177da614ca614a`](https://github.com/rapiriz/ERP-Juan23/commit/7d521adcaafc83d7134c7e59e1177da614ca614a), que agrega anulación de pagos de compras. Se incorporó su funcionalidad con conservación del registro y auditoría, en lugar de eliminación física. La consulta final del 10/11 no encontró nuevas actualizaciones de issues desde las 00:00 UTC ni ejecuciones de Actions.

La [matriz de issues y PR](revision-issues-y-ramas-2026-11-10.md) enumera los elementos revisados, su estado remoto y el tratamiento local. Una issue abierta puede duplicar una historia ya implementada; una cerrada no demuestra que todas sus condiciones funcionen. No se cambiaron estados remotos ni se publicaron comentarios.

La selección de pedidos para entregas conserva `confirmada`. El cambio remoto a `pendiente` eludía la confirmación comercial y el control de crédito. Se mantuvieron las pruebas de integración que requieren confirmación antes de planificar. Tampoco se adoptó la eliminación de recepciones al cancelar una compra: los documentos históricos se conservan y el stock se revierte sólo cuando su origen se puede demostrar.

El HEAD local permanece en `060cb9badcb2ff5bc9301975edf038f27fd1091d`. Se adaptaron fuentes, no se hizo un merge completo ni se cambió la historia de la rama. Antes de publicar hay que revisar el conjunto local, integrar el historial remoto y ejecutar CI; no usar push forzado para saltear esa revisión.

## 3. Compras, órdenes y pagos: comportamiento resultante

### Importes y recepciones

Recibir mercadería y pagar una compra son hechos diferentes. `saldo_pendiente` representa mercadería aún no recibida; `saldo_a_pagar` es importe total menos pagos activos. Una compra recibida puede seguir impaga. La recepción suma el valor pendiente de cada detalle según su precio; no usa una fracción de cantidades que mezcle unidades y cajas o productos con precios diferentes.

El detalle de una orden conserva presentaciones originales e informa cantidades pendientes en unidad base, identificador de unidad de compra y precio base estimado para generar compras sin confundir formatos. Los cálculos de progreso convierten a unidad base y conservan compras vinculadas históricas.

Una orden pendiente puede generar una compra: bajo bloqueo se valida proveedor/estado, se guarda y se avanza a enviada sólo cuando todo termina bien. `recibir_ahora` usa el controlador interno de recepción dentro de la misma transacción, con identidad, detalle, presentación, lote y vencimiento. No se llama al propio servidor por HTTP. Cualquier recepción inválida revierte también la compra y el avance de la orden.

El cierre parcial requiere recepción comprobada y que lo pagado no supere el valor recibido. Ajusta pendientes y total, registra observaciones y auditoría; no inventa una devolución de dinero. Una orden sólo se da por cerrada parcialmente cuando sus compras activas justifican esa situación y el usuario confirma la decisión.

Al cancelar, se bloquean orden/compra/productos, se comprueban pagos activos y stock originado en sus recepciones, y se conservan documentos. Con lotes controlados se revierte el lote realmente recibido; no se toma otro lote para compensar. Un origen histórico ambiguo o mercadería ya consumida provoca conflicto, con reversión completa de la operación fallida.

Las sugerencias agrupadas usan necesidad máxima por producto, unidades base, proveedor válido y bloqueos ordenados. Se evita sumar dos veces la misma necesidad o incorporar sugerencias a una orden que ya tiene compras. Se conservaron auditoría y reglas de concurrencia anteriores.

### Anulación trazable de pagos a proveedores

La última función recuperada de develop se expone como `DELETE /api/compras/{compra}/pagos/{pago}`, con alias `/api/v1/...`. Requiere usuario administrativo activo; por sesión se conserva CSRF. El cuerpo puede incluir `motivo`; si se envía debe contener entre 3 y 1000 caracteres. Si se omite se registra un motivo administrativo predeterminado. No acepta pago de otra compra, ni reabre una compra cancelada.

Se conserva el pago original, con `anulado_en`, `anulado_por` y `motivo_anulacion`. Los totales y deudas usan pagos activos; `lista` conserva el historial completo y `pagos` sólo los activos. Un reenvío de la misma anulación devuelve el resultado sin repetir efecto ni auditoría. Si falla la auditoría, se revierte también la marca de anulación. La interfaz confirma, pide motivo, escapa su contenido y actualiza detalle, deuda e historial.

Esta operación revierte el registro de pago de G3. No afirma que el banco devolvió dinero ni crea un movimiento de Caja a partir de un vínculo que no existe. Ante un pago real con efectos externos, verificar el comprobante y gestionar su regularización financiera con el responsable.

## 4. Base de datos y preservación

Se conservaron los archivos anteriores de migraciones; no se eliminaron ni se reescribieron todos. Se agregaron:

| Migración | Cambio | Por qué se necesita |
|---|---|---|
| `app/Modules/Productos/database/migrations/2026_11_10_000001_add_cierre_parcial_to_compra_y_orden_table.php` | `cierre_parcial` y `observaciones_cierre` en compra y orden_compra, sólo si faltan. | Persistir el cierre parcial de develop sin perder estado/observaciones en instalaciones existentes. |
| `app/Modules/Compras/database/migrations/2026_11_10_000002_preserve_supplier_payment_reversals.php` | Datos de anulación y un índice para pagos activos en pago_proveedor. | Conservar quién/cuándo/por qué anuló, historial y deuda consistente. |

Ambas se ensayaron y aplicaron a la base local principal. La primera agrega banderas con valor inicial falso y observaciones opcionales; la segunda agrega campos nulos. No se cargaron nuevos artículos/clientes en esta etapa. Las imágenes y productos reales incorporados en la etapa anterior siguen disponibles. Los nuevos ejemplos financieros y compras de navegador están exclusivamente en bases QA.

El registro contiene ahora 63 archivos de migración. Los `down()` de estas dos migraciones son deliberadamente conservadores: no borran columnas ni historial. Un rollback genérico no elimina esos campos ni constituye una restauración completa.

Antes de migrar se obtuvo un respaldo SQL y una copia privada de las fuentes. Se repitió el respaldo antes de la segunda migración. Se probó:

1. Instalación de las 63 migraciones en una base nueva de QA.
2. Restauración del respaldo real anterior en otra base nueva y aplicación de las dos migraciones.
3. Comparación de las columnas y huellas originales de las 101 tablas de la base del proyecto: **0 filas anteriores alteradas o perdidas**.
4. Comprobación adicional de las 137 tablas incluidas en el inventario inicial del servidor local, que también abarcaba otra base y phpMyAdmin: **0 filas anteriores alteradas o perdidas**. No son 137 tablas del ERP principal.

No se reparó ni reconstruyó MySQL en esta etapa. Al reanudar, un primer arranque no pudo escribir en XAMPP por restricciones del entorno de ejecución; el arranque autorizado funcionó. Eso no se tomó como evidencia de nueva corrupción.

Las 16 tablas históricas adicionales y sus cantidades se enumeran en la sección siguiente. Conservación no significa que sus datos sean válidos para aplicar automáticamente.

## 5. Datos históricos que siguen pendientes

| Estructura adicional | Filas | Tratamiento |
| `categorias` | 0 | Conservar; equivalencia y respaldo por verificar. |
| `cliente_credito_config` | 15 | Conservar; equivalencia y respaldo por verificar. |
| `cobro_ventas` | 0 | Conservar; equivalencia y respaldo por verificar. |
| `cobros` | 0 | Conservar; equivalencia y respaldo por verificar. |
| `credito_venta` | 15 | Conservar; equivalencia y respaldo por verificar. |
| `detalle_ventas` | 0 | Conservar; equivalencia y respaldo por verificar. |
| `devolucion_venta` | 15 | Conservar; equivalencia y respaldo por verificar. |
| `document_sequences` | 1 | Conservar; equivalencia y respaldo por verificar. |
| `importacion_bancaria` | 1 | Conservar; equivalencia y respaldo por verificar. |
| `integration_operations` | 15 | Conservar; equivalencia y respaldo por verificar. |
| `marcas` | 0 | Conservar; equivalencia y respaldo por verificar. |
| `nota_credito` | 15 | Conservar; equivalencia y respaldo por verificar. |
| `productos` | 0 | Conservar; equivalencia y respaldo por verificar. |
| `rendicion_devolucion` | 0 | Conservar; equivalencia y respaldo por verificar. |
| `rendicion_diferencia` | 15 | Conservar; equivalencia y respaldo por verificar. |
| `ventas` | 0 | Conservar; equivalencia y respaldo por verificar. |

Los 40 asientos sin pago canónico, 38 cobros sin aplicación y 15 notas sin respaldo deben contrastarse con cliente, venta, comprobante, caja, cheque y eventual reversión. No se vinculan por mera coincidencia de importe/fecha. No se borran ni se aplica su efecto dos veces. La pantalla administrativa y los comandos permiten listarlos, pero la decisión por registro requiere evidencia que el código no puede inventar.

La revisión de esquema se limita a la base configurada. Se corrigió el alcance del inventario: no presenta tablas de otras bases del servidor como si fueran propias de este ERP.

## 6. Hallazgos adicionales solucionados durante las pruebas

- **Modales de G3 abiertos al entrar:** el CSS compilado y las reglas de `clay-modal-backdrop` dejaban visible un modal con clase `hidden`. Se agregó la regla específica del modal, sin forzar globalmente `.hidden` y romper variantes responsive. Se probaron apertura, cierre y Escape.
- **Disponibilidad distinta entre listado y detalle:** el detalle mostraba stock físico como disponible, incluso cuando había lotes controlados vencidos. Ahora `disponible`, `stock_disponible` y equivalencias disponibles usan la disponibilidad derivada; `stock` conserva su significado físico.
- **Consulta de catálogo podía consumir la cuota del POST de pedidos:** los límites numéricos de rutas distintas compartían un contador. Se separaron prefijos de throttling de navegación, pedidos, comprobante, login y recuperación, manteniendo sus límites. La prueba recorre 8 consultas y luego permite 5 pedidos, rechazando el sexto con 429; el login tiene su propio contador.
- **Una combinación automática duplicó un método del modelo Producto:** aunque no existía conflicto textual, el lint detectó dos `scopeFilterProveedor`. Se resolvió la duplicación y se verificó toda la integración antes de continuar.
- **Selector de orden sin nombre accesible:** se agregó etiqueta accesible al nuevo campo de compra. Se comprobaron datos hostiles en Proveedores y en motivos de anulación de pagos: se muestran como texto y no ejecutan HTML.
- **Fixtures de navegador incompletas:** se agregaron producto con imagen y compra propios de QA. Las pruebas no dependen de que una instalación nueva contenga productos personales ni compras reales del entorno habitual.

## 7. Frontend y dependencias locales

Los layouts `app` y `g3` comparten los tokens visuales de `public/assets/css/erp-theme.css`: fondo claro, azul, tipografía, campos, tarjetas y botones. No se quitan diferencias semánticas de alerta/éxito/peligro. Las pantallas conservan su lógica de negocio.

La compilación usa versiones exactas en `tools/frontend/package-lock.json`: Tailwind 3.4.19, Lucide 1.70.0, Bootstrap 5.3.3, Chart.js 4.4.1 y Font Awesome 6.5.1. Se publican archivos locales, fuentes, licencias y manifiesto SHA-256 en `public/assets/vendor`. Para reconstruir: `npm ci --prefix tools/frontend` y `npm run build --prefix tools/frontend`. No se necesita CDN de Tailwind, `lucide@latest` ni Google Fonts en esos layouts.

El aviso informativo de Browserslist sobre antigüedad de su base de compatibilidad no fue tratado como vulnerabilidad ni justificó una actualización indiscriminada. Las auditorías de npm y Composer no reportaron vulnerabilidades en las versiones bloqueadas consultadas el 10/11.

## 8. Pruebas y límites de la comprobación

| Verificación ejecutada | Resultado |
|---|---|
| PHPUnit completo, con fallos ante pruebas riesgosas u omitidas | **726 pruebas, 4546 aserciones, aprobadas**. |
| Pruebas Node de frontend | **48 aprobadas**, 0 omitidas. |
| Navegador Playwright/axe: smoke | **29 pantallas**, 0 errores de ejecución, 0 infracciones detectadas, sin desbordes móviles de la muestra. |
| Navegador: recorridos adicionales | **14 comprobaciones**, 0 errores de ejecución e infracciones detectadas. Incluye solicitud pública, comprobante, revisión/conversión a venta y anulación de pago con texto hostil. |
| Auditoría de controladores de rutas | **484 rutas**, 0 acciones inexistentes. |
| Auditoría de nombres canónicos/migraciones | **63 migraciones**, 0 errores. |
| Sintaxis de PHP/JS intervenido | Sin errores; detalle en inventario/evidencias de la etapa. |
| Composer validate --strict / composer audit --locked | Manifiesto válido; sin avisos de vulnerabilidad. |
| npm audit: herramientas de assets y navegador | Sin vulnerabilidades reportadas. |
| Imágenes locales de productos reales de la etapa anterior | **15 consultadas, 15 válidas**. |
| Instalación nueva y actualización restaurada | Ambas correctas; 0 filas originales cambiadas en las 101 tablas comprobadas. |

Todas las escrituras de prueba funcional se ejecutaron en bases con prefijo QA en el servidor independiente 3315 o SQLite en memoria. El servidor de navegador usa 8015 y una clave temporal. No se sembraron operaciones de prueba en la base habitual. Los datos agregados por pruebas no son mocks de la base real: son registros de pruebas aisladas que no deben entregarse como datos productivos.

Las pruebas locales no certifican todos los navegadores, accesibilidad integral, concurrencia bajo cualquier carga ni Linux real. La matriz de GitHub es trazabilidad, no una ejecución individual de cada criterio de aceptación. No se realizó un pentest ni una homologación fiscal.

## 9. APIs, operación y programador

El [inventario de APIs](api-rutas-registradas.md) se generó nuevamente desde las rutas registradas. Los contratos G1/G3 explican autenticación, presentaciones, imágenes, cierre parcial y anulación trazable. Un GET en el navegador no reemplaza un POST de login. No se preparan tokens fijos dentro del código ni se usa `X-User-Id` como autenticación.

`GET /api/stock/disponibilidad` y su alias v1 resuelven el anuncio de stock de la issue #190. `/api/ventas/demanda` continúa siendo una propuesta histórica sin ruta: el servicio actual de reposición usa los movimientos de salida de G3, por lo que no bloquea ese circuito. Si otro equipo requiere esa API, debe acordar período, estados, cantidades y formato; no se anuncia como implementada.

La tarea Windows `ERP-Juan23-26A606BC25` permanece eliminada. No se instaló reemplazo ni se borraron scripts. Por eso los cierres de cajas vencidas, reintentos periódicos de stock y pulso **no se ejecutan automáticamente** en este entorno. La guía vigente informa los comandos manuales y la futura necesidad de un programador autorizado en producción. Una entrada de `schedule:list` no demuestra que exista una tarea activa.

Los servidores usados exclusivamente para esta comprobación se apagan al finalizar. El usuario puede iniciar MySQL desde XAMPP y la aplicación como siempre con `C:\xampp\php\php.exe artisan serve`. No es necesario Apache para el transporte interno entre módulos.

## 10. Pendientes reales después de esta entrega

| Prioridad | Pendiente | Qué falta para cerrarlo |
|---|---|---|
| Alta, antes de regularizar saldos antiguos | Finanzas históricas | Revisar comprobantes de las categorías 40/38/15, detectar solapamientos y documentar una decisión por registro. No aplicar ni revertir automáticamente. |
| Alta, antes de retirar tablas | 16 estructuras adicionales / 92 filas | Confirmar equivalencias, origen, relaciones y ausencia de duplicación; ensayar un plan explícito antes de copiar/borrar. Se conserva todo. |
| Antes de publicar / Linux | Integración del historial y CI | Revisar el conjunto de cambios locales, integrar Git sin perder protecciones, publicar con autorización y ejecutar el workflow Ubuntu/MariaDB. Actualmente hay 0 ejecuciones remotas. |
| Antes de producción | SMTP, HTTPS, cookies seguras, respaldos externos y restauración de archivos privados | Sólo existe entorno local. Probar infraestructura real cuando haya dominio/servidor/credenciales; no alcanza con la restauración SQL aislada. |
| Antes de emitir comprobantes fiscales | ARCA | Configuración/homologación, certificados, CUIT/punto de venta y validación de emisión. El alcance confirmado sigue siendo comprobantes internos con integración preparada. |
| Por decisión operativa | Ejecución automática de tareas | Sigue deshabilitada por pedido expreso. Si se desea automatizar en otro entorno, autorizar y verificar un mecanismo allí; esta etapa no lo instala. |
| Si lo requiere un consumidor | Demanda de ventas y cierre administrativo de issues duplicadas | Acordar el contrato faltante y validar su consumidor; actualizar estados del backlog con responsables después de revisar evidencia. No confundirlo con una dependencia actual de reposición. |

No apareció un nuevo fallo reproducido del conjunto solicitado tras la suite final. Eso no convierte los pendientes de datos, publicación e infraestructura en resueltos ni garantiza que no surjan errores con otros datos o condiciones.

## 11. Archivos, entrega y cómo revertir

Se conserva fuera del proyecto `Entregables/Cierre_Pendientes_2026-11-09/`, con respaldo previo SQL, respaldo previo a la segunda migración, copia `codigo-antes.privado.zip`, huellas y registros de pruebas. Son privados: no subirlos a Git ni compartirlos como ZIP de fuentes. La copia anterior incluye la configuración real que tenía este entorno y exige protección.

El ZIP de fuentes final excluye `.env` real, dumps SQL/SQLite, claves/certificados, Git, dependencias instaladas, logs, comprobantes y runtime privado. Incluye migraciones PHP, locks, assets compilados/licencias y documentación. No es un respaldo restaurable de los datos ni contiene una base cargada. No se borraron archivos del repositorio por mera antigüedad.

Para revertir esta etapa sin destruir el trabajo anterior:

1. Detener las escrituras y guardar un respaldo nuevo de la situación actual, incluida configuración y archivos privados. Si hubo operaciones nuevas, no restaurar un respaldo antiguo encima de ellas.
2. Usar el inventario de esta etapa y `codigo-antes.privado.zip` para restaurar **solamente los archivos modificados en esta etapa**. Los archivos marcados como nuevos pueden retirarse sólo tras comprobar sus referencias. No usar `git reset --hard`: había cientos de cambios locales previos. La copia previa, no HEAD, representa ese trabajo anterior.
3. Volver al código previo normalmente permite conservar las columnas aditivas, porque no se borró el esquema anterior. Si ya se anularon pagos, restaurar código que sume todos los registros volvería a contarlos: antes de retroceder ese servicio revisar las anulaciones con su auditoría. No borrar pagos ni sus campos para ocultar ese problema.
4. Para revertir también la BD, restaurar el SQL anterior **primero en una base nueva aislada**, comparar datos, y preparar una promoción controlada con la aplicación detenida y autorización específica. El respaldo anterior a la segunda migración revierte sólo esa fase; el anterior al inicio permite ensayar toda la etapa. No ejecutar una restauración automática en la base habitual.
5. Los `down()` conservadores no eliminan las columnas. Si se exige un retiro de campos, hace falta inventario de datos y una migración posterior expresamente revisada; no modificar migraciones aplicadas para simular una reversión.
6. Reinstalar dependencias según los locks de la versión elegida y reconstruir sus assets si corresponde; limpiar las cachés Laravel y repetir las pruebas. Nunca regenerar APP_KEY de una instalación existente como parte de esta reversión.

Ni el cierre ni la reversión requieren reinstalar la tarea Windows eliminada. Este informe no autoriza modificar permisos internos de XAMPP ni borrar sus archivos.
