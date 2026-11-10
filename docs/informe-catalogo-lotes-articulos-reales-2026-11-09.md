# Catálogo, artículos reales, lotes y preparación fiscal

Fecha: 09/11/2026. Alcance de esta etapa: ampliar los cinco puntos solicitados y cargar artículos reales para pruebas locales. No equivale a certificar todo el ERP ni sustituye la auditoría general. El usuario autorizó cambios entre módulos y confirmó que por ahora sólo existe entorno local y comprobantes internos.

## Resultado y alcance

| Punto solicitado | Implementación | Límite que se conserva |
|---|---|---|
| Artículos e imágenes | 15 artículos comerciales Marolio insertados en la BD, con fotos oficiales normalizadas y movimientos reales de ingreso. | Los precios, cantidades, códigos internos, lotes y fechas son datos de prueba, no inventario físico certificado. |
| Lotes y vencimientos | Registro, asignación de stock existente, historial, recepción por lote, salida FEFO y restauración vinculada a venta. | Los lotes históricos quedan pendientes de revisión; no se supone que su información sea fiable. |
| Catálogo | Subcategorías, carrito anónimo, solicitudes persistentes, constancia y revisión/conversión administrativa. | Una solicitud pública no es venta confirmada, reserva ni documento fiscal. |
| Estadísticas de proveedores | Cumplimiento, retrasos y pendientes según compra, fecha prevista y recepciones reales. | Sin fechas históricas verificables se informa no evaluable; no se inventan estadísticas. |
| Fiscal y producción | Contrato de emisor, implementación que rechaza emisión, diagnóstico y configuración de homologación preparada. Se conserva la guía de despliegue existente. | No hay adaptador ARCA, CAE, servidor productivo, SMTP real ni validación HTTPS de un dominio. |

## Qué faltaba y qué se corrigió

### Productos y fotografías

La carga necesitaba validar también el alias de archivo `imagen`, evitar referencias a fotos locales inexistentes y retirar el archivo nuevo si fallaba el guardado de BD. Se agregó `Productos/Services/ProductImageStorage.php` y se integró con `ProductoController`.

Admite JPEG, PNG y WebP auténticos, hasta 4 MB y 4096 × 4096 píxeles. Decodifica y vuelve a codificar con GD a WebP con nombre UUID, quitando metadatos y sin sobreescribir nombres del usuario. Los errores de validación devuelven 422. Se conserva la foto previa cuando no se carga otra; no se borra automáticamente un archivo anterior que podría tener otras referencias. Las imágenes quedan en `public/assets/catalogo`, por lo que deben incluirse en los respaldos junto con la BD.

Se usó el propio controlador de productos para la carga y el servicio de lotes para generar el ingreso; no se publicaron mocks ni se fijó el stock únicamente en HTML. Se verificaron las 15 imágenes desde el gateway real del catálogo. El formulario de Inventario conserva la carga y previsualización, con los límites indicados.

### Lotes y vencimientos

`Stock/Services/LotService`, `LotStockAllocator`, `Models/Lote` y `Controllers/LoteController` implementan trazabilidad sin sumar dos veces el stock. Registrar un ingreso aumenta el inventario; asignar existencias ya cargadas sólo las distribuye en lotes. La cantidad queda protegida con transacciones y bloqueo del producto.

FEFO consume primero lo que vence antes entre lotes controlados. Los lotes vencidos no se venden. La disponibilidad comercial los descuenta del stock físico, que sigue existiendo hasta su operación de disposición. Los movimientos vinculados a una venta permiten restaurar sus cantidades en los lotes correspondientes, sin escoger un lote arbitrario. El stock histórico sin lote no se elimina ni se convierte automáticamente en trazable.

Los lotes antiguos se marcan `pendiente_revision`; la adopción exige comprobar cantidades e integridad y rechaza cantidades fraccionarias incompatibles. La recepción de compra admite número de lote y vencimiento por detalle; recepciones parciales del mismo lote coherente reutilizan su registro. Datos contradictorios se rechazan de forma atómica.

La pantalla `/inventario/lotes` incluye búsqueda, estado, ventanas de vencimiento e historial. El acceso antiguo `/vencimientos` dirige a esa pantalla. No se creó tarea programada ni automatización nueva: las consultas calculan el estado al abrirlas.

### Subcategorías y selección pública

`Productos/Services/CategoryHierarchy` evita padres inexistentes, ciclos y jerarquías de más de ocho niveles. `CategoriaController`, `Categoria`, la proyección comercial de productos y el DTO del catálogo incorporan la ruta jerárquica. No se permite borrar categorías con hijos o productos. Las categorías padre incluyen los productos de sus descendientes; filtros, cantidades y navegación reflejan esa jerarquía.

`CatalogOrderService`, `CatalogOrderController`, sus rutas, vistas y `catalog-cart.js` agregan la selección de hasta 50 artículos, con cantidades de 1 a 999. Sólo identificadores y cantidades se guardan en localStorage. El servidor vuelve a consultar productos y precios, valida contacto, consentimiento, CSRF y frecuencia. Una repetición idéntica no crea otra solicitud; cambiar el contenido bajo la misma clave devuelve conflicto.

La constancia usa un enlace privado aleatorio cuyo hash se guarda en BD, sin mostrar email o teléfono, con cabeceras que impiden caché e indexación. El administrador verifica al solicitante, selecciona un cliente existente y lista de precios, y puede revisar, rechazar o convertir. La conversión llama internamente al controlador existente de Ventas bajo la identidad administrativa y reutiliza su circuito de deuda, stock e idempotencia. No se modificó el backend de G4 para esta etapa. Cada revisión deja evento persistente.

### Cumplimiento de proveedores

`Proveedores/Services/SupplierPerformance` y su controlador calculan resultados filtrados por proveedor y fecha de compra. Se añadió `fecha_entrega_prevista` a Compras: puede indicarse expresamente o tomar al crear la compra el plazo que ya informa el proveedor. Las compras anteriores quedan con fecha desconocida.

Una compra es evaluable para cumplimiento cuando tiene fecha prevista y recepción completa comprobable. Se usa la última recepción que completa la compra; se muestran porcentaje a tiempo, retraso medio, abiertas vencidas y casos sin información suficiente. Los cancelados se excluyen. El formulario de Compras incorpora la fecha prevista. No se cargaron compras o pagos ficticios en la base habitual para mejorar estos indicadores.

### Fiscal y producción

Se creó `Modules/Facturacion` con contrato `FiscalIssuer`, implementación `InternalDocumentsOnly`, `FiscalReadiness`, controlador, rutas y vista. `AppServiceProvider` registra la implementación que rechaza emitir. `config/fiscal.php` y las variables vacías de `.env.example` preparan la configuración sin activar emisión ni exponer secretos.

La comprobación de formato de CUIT/punto de venta y presencia de archivos privados es orientativa: no valida autorización fiscal. Aun completándolos, la emisión permanece deshabilitada. Se necesitarán adaptador WSAA/WSFE, persistencia de autorizaciones, recuperación idempotente ante respuestas inciertas y homologación real antes de habilitarla. Fuentes oficiales: [WSFE](https://www.arca.gob.ar/ws/documentacion/ws-factura-electronica.asp), [WSAA](https://arca.gob.ar/ws/documentacion/wsaa.asp), [homologación](https://www.arca.gob.ar/ws/documentacion/homologacion-externa.asp).

No se modificó `.env`, no se publicaron credenciales ni se recreó la tarea de Windows eliminada por el usuario. SMTP real, HTTPS y restauración integral en destino siguen pendientes de un entorno de producción. La guía `validacion-y-despliegue.md` conserva los pasos y añade el respaldo de las fotos públicas.

## Cambios de base de datos

Se aplicaron únicamente cuatro migraciones nuevas, primero en base vacía de QA, después sobre copia restaurada y finalmente en `sistema_gestion`:

| Archivo | Cambio |
|---|---|
| `Stock/database/migrations/2026_11_09_000008_restore_lot_traceability.php` | Conserva/crea `lote`, agrega estado de trazabilidad e incorpora `stock_lote_movimientos`. La FK se adapta al tipo firmado/tamaño del identificador existente. |
| `Productos/database/migrations/2026_11_09_000009_add_category_hierarchy.php` | Agrega padre opcional e índice a `categoria`; no altera productos históricos. |
| `Catalogo/database/migrations/2026_11_09_000010_create_catalog_order_requests.php` | Crea solicitudes, artículos y eventos; no duplica el maestro comercial. |
| `Compras/database/migrations/2026_11_09_000011_add_expected_supplier_delivery_date.php` | Fecha prevista nullable, sin atribuir compromisos a compras anteriores. |

Sus rutas completas comienzan en `app/Modules/`. Los `down()` son conservadores: **no borran datos ni deshacen el esquema**. `migrate:rollback` no constituye un mecanismo de reversión de esta entrega. No se borraron ni reemplazaron migraciones antiguas en esta etapa.

Se respaldó la BD antes de aplicar cambios. La comparación de las columnas y filas originales mostró cero registros anteriores alterados o desaparecidos en las 97 tablas anteriores de `sistema_gestion` (1.343 registros considerados, excluido el registro de migraciones). La comprobación ampliada de otras bases también conservó sus registros. La tabla de migraciones sí registra las cuatro aplicaciones nuevas. La verificación del ensayo se corrigió para comparar sólo el esquema clonado, sin intentar consultar phpMyAdmin en el servidor de QA.

## Qué grupos y archivos se tocaron

- G1: módulo Catálogo, nuevas solicitudes/vistas/carrito, DTO/proyección, configuración, pruebas y documentación.
- G3: Productos, categorías, almacenamiento de imágenes; Stock, lotes, movimientos y disponibilidad; Compras/Recepción y fecha prevista; Proveedores y estadísticas. Son cambios autorizados, no sólo consumo de API.
- G2: únicamente en esta etapa se agregó una etiqueta accesible a las casillas de selección de pedidos en `public/js/g2-entregas.js`, detectada al probar una copia con datos. No se alteró backend ni esquema de G2 en esta etapa.
- G4: se reutiliza su alta de venta al convertir solicitudes; no hubo cambio de sus archivos de backend en esta etapa.
- Compartidos: registro del emisor en `AppServiceProvider`, nuevas rutas GET permitidas en `InternalApiClient`, menú lateral, estilos, `.env.example` y documentación.

El repositorio ya contenía muchos cambios de etapas anteriores. El inventario externo `archivos-etapa.json` distingue los de esta etapa frente a `codigo-antes.json`; `git diff` frente a HEAD también incluye trabajos previos y no debe atribuirse entero a esta ampliación. No se hizo commit ni push.

## Datos comerciales cargados

Fuente: [catálogo oficial de Marolio](https://productos.marolio.com.ar/productos). Las fotos se descargaron del fabricante y se normalizaron localmente; no son imágenes generadas. Antes de una publicación comercial verificar permisos de uso y mantener la atribución.

Los códigos `MAR-REAL-*` son identificadores internos de esta carga, no códigos de barras del fabricante. Los nombres corresponden a artículos reales; los precios, cantidades, lotes `PRUEBA-*` y vencimientos son deliberadamente de prueba. No representan una cotización ni vencimientos de envases físicos. El entorno local muestra un aviso al respecto.

| Código | Artículo | Precio de prueba ARS | Unidades de prueba | Vencimiento de prueba |
|---|---|---:|---:|---|
| MAR-REAL-001 | Aceto Balsámico x 250ml Marolio | 2150 | 24 | 2026-11-16 |
| MAR-REAL-002 | Vinagre de Alcohol Marolio | 850 | 60 | 2026-11-24 |
| MAR-REAL-003 | Vinagre de Vino Marolio | 900 | 48 | 2026-12-09 |
| MAR-REAL-004 | Spaghetti Marolio | 1200 | 120 | 2027-02-07 |
| MAR-REAL-005 | Tallarines Marolio | 1250 | 80 | 2027-03-09 |
| MAR-REAL-006 | Tirabuzón Marolio | 1300 | 50 | 2026-11-16 |
| MAR-REAL-007 | Mostachol Rayado Marolio | 1250 | 36 | 2026-11-24 |
| MAR-REAL-008 | Arroz Doble Marolio | 1800 | 60 | 2026-12-09 |
| MAR-REAL-009 | Puré de Tomates Marolio | 950 | 24 | 2027-02-07 |
| MAR-REAL-010 | Tomates Peritas Marolio | 1750 | 48 | 2027-03-09 |
| MAR-REAL-011 | Arvejas Marolio | 1100 | 96 | 2026-11-16 |
| MAR-REAL-012 | Garbanzos Marolio | 1250 | 36 | 2026-11-24 |
| MAR-REAL-013 | Duraznos Marolio | 2200 | 24 | 2026-12-09 |
| MAR-REAL-014 | Mermelada de Frutilla Marolio | 1800 | 20 | 2027-02-07 |
| MAR-REAL-015 | Arroz Fino Marolio | 1350 | 100 | 2027-03-09 |

La carga creó 15 productos nuevos (IDs 21 a 35), preservando los 20 existentes: total 35 productos al terminar. Se crearon 15 lotes controlados y sus ingresos, y se conservaron los 15 lotes históricos. Repetir la carga omitió los 15 códigos existentes y no duplicó sus existencias. Los manifiestos externos conservan ID, foto, lote, precio, cantidad y URL de origen de cada artículo.

## Nuevos accesos y APIs

| Método | Ruta | Uso y acceso |
|---|---|---|
| GET | `/inventario/lotes` | Pantalla para administrativo, contador y repartidor. |
| GET | `/api/v1/lotes` | Listado autenticado, filtros y vencimientos. |
| GET | `/api/vencimientos/proximos` | Consulta autenticada de próximos vencimientos. |
| POST | `/api/v1/lotes` | Registro administrativo, con identidad de usuario; un token técnico de lectura no habilita escritura. |
| GET | `/proveedores/estadisticas` | Pantalla administrativa/contador. |
| GET | `/api/v1/reportes/proveedores-cumplimiento` | Estadísticas con autenticación de lectura. |
| GET | `/operaciones/fiscal` | Diagnóstico administrativo/contador. |
| GET | `/api/v1/fiscal/estado` | Estado fiscal, nunca emisión. |
| GET | `/catalogo/carrito` | Selección pública. |
| POST | `/catalogo/pedidos` | Solicitud pública, sesión/CSRF y límite de frecuencia. |
| GET | `/catalogo/pedido/{token}` | Constancia privada mediante enlace secreto. |
| GET/POST | `/pedidos-catalogo` y detalle/revisión | Gestión administrativa, según las rutas documentadas en el módulo. |

El inventario `api-rutas-registradas.md` incluye las rutas actuales y sus middleware; estar registrada no elimina sus permisos ni demuestra cobertura de todas las variantes.

## Verificaciones y correcciones encontradas durante el desarrollo

Se ensayaron instalación vacía y actualización sobre respaldo, conservación de registros, carga y repetición idempotente, lectura de fotos, jerarquías, stock y reversión por lote, recepción parcial, permisos, solicitud pública y conversión sin duplicar ventas. Las pruebas automatizadas utilizan bases exclusivas de QA y transacciones; no escriben fixtures sobre la base habitual.

La inspección visual también detectó un error 500 en la consulta de lotes: Carbon recibía los días como texto desde la URL. Se normalizó el entero después de validarlo y se agregó una regresión con las URLs del navegador para 7, 15 y 30 días. El recorrido de navegador ahora comprueba también respuestas asíncronas y filas cargadas. Se corrigió además la rejilla de las fotos, cuya altura intrínseca recortaba el producto: la imagen completa queda contenida en su ficha.

Se corrigieron durante las pruebas: la migración de jerarquía frente a fixtures SQLite sin tabla `categoria`; expectativas antiguas que redirigían vencimientos fuera de la función nueva; una prueba que referenciaba una foto local inexistente en lugar de subir un archivo; formato de recepción opcional que rompía el contrato previo del frontend; desbordamiento móvil del consentimiento; contraste de la constancia; y etiquetas accesibles de selección de Entregas con datos existentes. Un ensayo de despacho fiscal recibió 401 porque la prueba no pasaba el token: se corrigió el consumidor de prueba, sin quitar la autenticación.

**Resultado final:** 701 pruebas de backend y 4.308 aserciones; 48 pruebas de frontend; 29 comprobaciones generales y 12 ampliadas en navegador, sin errores de ejecución ni infracciones de accesibilidad detectadas en esos recorridos. Se verificó la sintaxis de 49 archivos PHP/JS de esta etapa, 61 migraciones sin duplicados y 310 rutas API registradas. Instalación vacía y actualización en copia completadas. Los registros originales permanecen intactos y las 15 imágenes de la base habitual son legibles.

Los resultados finales y comandos quedan en `resultados-verificacion.json` dentro del directorio externo de esta etapa. Las capturas cubren catálogo, foto real, carrito móvil, solicitud, revisión, conversión, lotes, estadísticas y diagnóstico fiscal. Las comprobaciones automáticas de accesibilidad no sustituyen una revisión humana integral.

## Cómo probar manualmente

1. Iniciar MySQL en XAMPP y, en el proyecto, ejecutar `C:\xampp\php\php.exe -d extension=gd -d extension=zip artisan serve`.
2. Sin sesión abrir `/catalogo`: buscar `MAR-REAL`, combinar categoría/marca y abrir el detalle con foto. Los productos nuevos se insertaron en la BD, no requieren ejecutar un seeder.
3. Agregar artículos al carrito, ajustar cantidades y enviar una solicitud con contacto y consentimiento. Guardar su enlace de constancia; tratarlo como privado.
4. Como administrativo abrir `/pedidos-catalogo`, revisar, verificar al cliente y elegir la lista. Convertir sólo cuando se desee crear una venta real de prueba: esa acción sí usa stock y cuenta corriente.
5. En `/inventario/lotes` revisar filtros de 7, 15 y 30 días e historial. Para asignar stock ya existente elegir esa operación; no registrar otro ingreso por error. Los lotes vencidos quedan fuera de disponibilidad comercial.
6. En Inventario crear/editar un producto y subir JPEG/PNG/WebP dentro de los límites. Verificar la foto en su detalle de catálogo. Usar movimientos de stock para existencias; la cantidad por bulto no es el stock disponible.
7. Consultar estadísticas y diagnóstico fiscal. Con fechas de compras históricas desconocidas es correcto ver “no evaluable”; en fiscal debe verse emisión deshabilitada.

## Respaldos y cómo revertir

Todo lo siguiente es una guía; no se ejecutó reversión. Los respaldos contienen datos y `.env`, por lo que deben mantenerse privados y fuera del repositorio.

Directorio externo: `Entregables/Ampliacion_Catalogo_Lotes_2026-11-09`, hermano de `ERP-Juan23`.

- `codigo-antes.zip` y `codigo-antes.json`: estado de archivos inmediatamente anterior a esta etapa, incluyendo cambios locales anteriores.
- `respaldo-privado/erp-20261109-220816-u1RcHP.sql` y manifiesto: respaldo SQL anterior a la ampliación.
- `archivos-etapa.json`: archivos nuevos/modificados y huellas, para revertir exclusivamente esta etapa.
- Manifiesto `carga-sistema_gestion-20261109-223559.json`: identifica exactamente los 15 productos/fotos/lotes cargados.

Para retirar solamente artículos de prueba, preferir desactivarlos mediante la operación existente, identificados por el manifiesto. No borrar productos, lotes o movimientos que ya tengan ventas o recepciones. Desactivar no anula deuda, solicitudes ni movimientos previos.

Para una reversión completa: detener escrituras; respaldar nuevamente la BD y los archivos actuales; restaurar el SQL anterior **en otra base** y comprobar tablas, filas y relaciones; recuperar del ZIP sólo los archivos modificados de esta etapa respetando cambios posteriores; retirar sólo archivos nuevos enumerados en el manifiesto y sin referencias; conservar las fotos requeridas por el respaldo que vaya a utilizarse; verificar la aplicación en copia antes de seleccionar esa base como destino habitual. La instantánea anterior contiene secretos: no publicarla. No ejecutar `migrate:fresh`, `db:wipe` ni `git reset --hard` sobre el entorno habitual.

Los `down()` conservadores no revierten el esquema; no usar `migrate:rollback` esperando retirar estas tablas/columnas. Eliminar archivos de migración tampoco elimina su efecto ya aplicado y perjudica instalaciones futuras. Si hay datos creados después del respaldo, restaurarlo implica perderlos: primero acordar recuperación/conciliación, no sobreescribir por defecto.

Los procesos de QA y la instancia MySQL iniciados para esta etapa se detienen al finalizar. No se configura un servicio ni tarea de reemplazo. El usuario seguirá iniciando su entorno manualmente.

## Pendientes fuera de lo validado

Quedan la autorización fiscal real y su adaptador, SMTP/HTTPS en destino, verificación integral de restauración productiva, revisión comercial de datos antes de publicar y los hallazgos de la auditoría general ajenos a esta etapa. El maestro histórico de lotes debe revisarse antes de adoptarlo. Esta entrega no certifica ausencia absoluta de errores en todo el proyecto.

## Inventario de archivos de esta etapa

Este listado excluye la caché de PHPUnit y las 15 imágenes UUID, identificadas en el manifiesto de carga. Los archivos previos se modificaron respecto de la instantánea local, conservando sus cambios anteriores. `config/schema_tables.php` incorpora el modelo Lote al control de nombres de tablas.

| Estado | Archivo |
|---|---|
| Modificado | `.env.example` |
| Modificado | `README.md` |
| Nuevo | `app/Modules/Catalogo/Controllers/CatalogOrderController.php` |
| Modificado | `app/Modules/Catalogo/Controllers/CatalogoApiController.php` |
| Modificado | `app/Modules/Catalogo/Data/ProductoCatalogo.php` |
| Modificado | `app/Modules/Catalogo/README.md` |
| Nuevo | `app/Modules/Catalogo/Services/CatalogOrderService.php` |
| Modificado | `app/Modules/Catalogo/Services/CatalogoService.php` |
| Nuevo | `app/Modules/Catalogo/database/migrations/2026_11_09_000010_create_catalog_order_requests.php` |
| Nuevo | `app/Modules/Catalogo/routes/web.php` |
| Modificado | `app/Modules/Compras/Controllers/CompraController.php` |
| Modificado | `app/Modules/Compras/Controllers/RecepcionController.php` |
| Modificado | `app/Modules/Compras/Models/Compra.php` |
| Nuevo | `app/Modules/Compras/database/migrations/2026_11_09_000011_add_expected_supplier_delivery_date.php` |
| Nuevo | `app/Modules/Facturacion/Contracts/FiscalIssuer.php` |
| Nuevo | `app/Modules/Facturacion/Controllers/FiscalStatusController.php` |
| Nuevo | `app/Modules/Facturacion/README.md` |
| Nuevo | `app/Modules/Facturacion/Services/FiscalReadiness.php` |
| Nuevo | `app/Modules/Facturacion/Services/InternalDocumentsOnly.php` |
| Nuevo | `app/Modules/Facturacion/routes/api.php` |
| Nuevo | `app/Modules/Facturacion/routes/web.php` |
| Modificado | `app/Modules/Productos/Controllers/CategoriaController.php` |
| Modificado | `app/Modules/Productos/Controllers/ProductoController.php` |
| Modificado | `app/Modules/Productos/Models/Categoria.php` |
| Modificado | `app/Modules/Productos/Models/Producto.php` |
| Nuevo | `app/Modules/Productos/Services/CategoryHierarchy.php` |
| Nuevo | `app/Modules/Productos/Services/ProductImageStorage.php` |
| Nuevo | `app/Modules/Productos/database/migrations/2026_11_09_000009_add_category_hierarchy.php` |
| Modificado | `app/Modules/Productos/routes/web.php` |
| Nuevo | `app/Modules/Proveedores/Controllers/SupplierPerformanceController.php` |
| Nuevo | `app/Modules/Proveedores/Services/SupplierPerformance.php` |
| Nuevo | `app/Modules/Proveedores/routes/api.php` |
| Nuevo | `app/Modules/Proveedores/routes/web.php` |
| Nuevo | `app/Modules/Stock/Controllers/LoteController.php` |
| Nuevo | `app/Modules/Stock/Models/Lote.php` |
| Nuevo | `app/Modules/Stock/Services/LotService.php` |
| Nuevo | `app/Modules/Stock/Services/LotStockAllocator.php` |
| Modificado | `app/Modules/Stock/Services/StockService.php` |
| Nuevo | `app/Modules/Stock/database/migrations/2026_11_09_000008_restore_lot_traceability.php` |
| Nuevo | `app/Modules/Stock/routes/api.php` |
| Nuevo | `app/Modules/Stock/routes/web.php` |
| Modificado | `app/Providers/AppServiceProvider.php` |
| Modificado | `app/Services/Integrations/InternalApiClient.php` |
| Modificado | `config/catalogo.php` |
| Nuevo | `config/fiscal.php` |
| Modificado | `config/schema_tables.php` |
| Modificado | `docs/api-rutas-registradas.md` |
| Modificado | `docs/indice-informes.md` |
| Nuevo | `docs/informe-catalogo-lotes-articulos-reales-2026-11-09.md` |
| Modificado | `docs/validacion-y-despliegue.md` |
| Modificado | `public/assets/css/catalogo.css` |
| Nuevo | `public/assets/js/catalog-cart.js` |
| Modificado | `public/assets/js/catalogo.js` |
| Nuevo | `public/assets/js/lotes.js` |
| Modificado | `public/js/g2-entregas.js` |
| Nuevo | `resources/views/catalogo/cart.blade.php` |
| Modificado | `resources/views/catalogo/index.blade.php` |
| Modificado | `resources/views/catalogo/layout.blade.php` |
| Nuevo | `resources/views/catalogo/order.blade.php` |
| Nuevo | `resources/views/catalogo/orders.blade.php` |
| Modificado | `resources/views/catalogo/partials/card.blade.php` |
| Nuevo | `resources/views/catalogo/receipt.blade.php` |
| Modificado | `resources/views/catalogo/show.blade.php` |
| Modificado | `resources/views/compras/index.blade.php` |
| Modificado | `resources/views/inventario/index.blade.php` |
| Nuevo | `resources/views/inventario/lotes.blade.php` |
| Nuevo | `resources/views/operaciones/fiscal.blade.php` |
| Modificado | `resources/views/partials/sidebar-links.blade.php` |
| Nuevo | `resources/views/proveedores/performance.blade.php` |
| Nuevo | `tests/Feature/CatalogLotsAndOrdersMySqlTest.php` |
| Nuevo | `tests/Feature/SupplierPerformanceAndFiscalMySqlTest.php` |
| Modificado | `tests/Feature/UserStoriesFrontendTest.php` |
