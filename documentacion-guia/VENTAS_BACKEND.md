# Backend del módulo de VENTAS — Punto de Venta (POS)

> Documento para el equipo de **front** y para quien mantenga el back.
> Cubre la pantalla "Punto de Venta" del prototipo de Figma: cliente, listas de
> precios, buscador, carrito, total y botones COBRAR / LIMPIAR / DESCUENTOS / CERRAR CAJA.

---

## 1. Arquitectura en 30 segundos

```
 Navegador (front)                         Servidor Laravel
 ─────────────────      HTTP + JSON       ───────────────────────────────────────────────
  ventas.blade.php  ───────────────────►  routes/ventas.php        (a qué controller va cada URL)
  (botones, tabla)                              │
                                                ▼
                                         Controllers/Ventas/*       (reciben, VALIDAN, responden)
                                                │
                                                ▼
                                         Services/Ventas/*          (LÓGICA DE NEGOCIO: precios,
                                                │                    promos, stock, cobro, caja)
                                                ▼
                                         Models/*  →  MySQL (base `distribuidora`)
```

**Regla de oro:** el front NO calcula nada. Cada acción devuelve el carrito completo
ya calculado (precios, descuentos, subtotales, total, si se puede cobrar) y el front solo lo dibuja.

**El carrito vive en la sesión de Laravel**, pero solo guarda _ids y cantidades_.
Precios, promociones y stock se leen de la base en cada respuesta, así nunca hay datos viejos.

---

## 2. Dónde va cada archivo y por qué

Todo lo entregado respeta la estructura estándar de Laravel; se copian las carpetas
dentro de `ERP-JUAN23/` respetando las rutas.

| Archivo                                | Carpeta estándar de Laravel  | Por qué va ahí                                                                                                                                                                   |
| -------------------------------------- | ---------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `routes/ventas.php`                    | `routes/`                    | Laravel carga las rutas desde esa carpeta. Archivo aparte para no generar conflictos en `web.php` con el resto del equipo.                                                       |
| `app/Http/Controllers/Ventas/*.php`    | `app/Http/Controllers/`      | Es donde Laravel busca controllers. La subcarpeta `Ventas` agrupa los del módulo. **Son finos**: reciben, validan y delegan.                                                     |
| `app/Services/Ventas/*.php`            | `app/Services/` (la creamos) | Laravel no trae esta carpeta, pero `app/` se autocarga entero (namespace `App\`). Acá vive **toda la lógica de negocio**, separada de HTTP, para poder reutilizarla y testearla. |
| `app/Models/*.php`                     | `app/Models/`                | Un modelo por tabla que usa el POS (puerta de entrada PHP ⇄ MySQL).                                                                                                              |
| `app/Exceptions/VentaException.php`    | `app/Exceptions/`            | Error de negocio que se convierte solo en respuesta JSON 422.                                                                                                                    |
| `app/Support/UsuarioActual.php`        | `app/Support/` (la creamos)  | Helper chico: "¿qué usuario está operando?". Aislado para cambiarlo fácil cuando exista el login.                                                                                |
| `config/ventas.php`                    | `config/`                    | Valores de negocio configurables (Consumidor Final, medios de pago, reglas de promos).                                                                                           |
| `documentacion-guia/VENTAS_BACKEND.md` | `documentacion-guia/`        | Contratos y reglas del módulo.                                                                                                                                                   |

### ¿Por qué separar Controller y Service?

Si la lógica de cobro estuviera dentro del controller, solo se podría usar desde una URL.
Separada en un Service, la usan el POS hoy y mañana (por ejemplo) un pedido telefónico o un test automático, sin copiar código.

### Qué hace cada pieza

| Pieza                 | Responsabilidad                                                                                   | Depende de                                                                           |
| --------------------- | ------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| `CarritoService`      | Estado del carrito, lista de precios, descuentos, validación de stock, totales                    | `PromocionService`, `Producto`, `Cliente`, `Promocion`, `config/ventas.php`          |
| `PromocionService`    | Elegir la promo que corresponde a cada producto (vigencia + reglas + la de mayor %)               | `Promocion`, `config/ventas.php`                                                     |
| `VentaService`        | COBRAR: transacción que crea venta, detalle, descuenta stock/lotes, registra cobros, caja y deuda | `CarritoService`, 7 modelos + tablas `cobro_venta`, `unidad_medida`, `log_auditoria` |
| `CajaService`         | Arqueo de CERRAR CAJA                                                                             | `caja_movimiento`, `cobro`, `venta`                                                  |
| `VentaController`     | Mostrar pantalla + COBRAR                                                                         | `CarritoService`, `VentaService`                                                     |
| `CarritoController`   | Todas las acciones sobre el carrito                                                               | `CarritoService`                                                                     |
| `ProductoController`  | Buscador de productos                                                                             | `Producto`, `CarritoService` (lista activa)                                          |
| `ClienteController`   | Buscar cliente + alta rápida (F1)                                                                 | `Cliente`, `CarritoService`                                                          |
| `PromocionController` | Promos vigentes para el botón DESCUENTOS                                                          | `Promocion`, `PromocionService`, `CarritoService`                                    |
| `CajaController`      | Resumen/arqueo de caja                                                                            | `CajaService`, `UsuarioActual`                                                       |

---

## 3. Puesta en marcha (una sola vez)

### 3.1 Copiar archivos

Copiar el contenido de la carpeta entregada dentro de `ERP-JUAN23/` (mismas rutas).

### 3.2 Rutas

`routes/web.php` ya carga `routes/ventas.php`, y la ruta `ventas` apunta a `VentaController@index`. No agregues otro `require` ni una ruta alternativa para `/ventas`, porque duplicaría URL/nombre.

### 3.3 `.env`

```dotenv
APP_TIMEZONE=America/Argentina/Buenos_Aires   # fechas de ventas/cobros en hora argentina
APP_LOCALE=es

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=distribuidora                     # nombre de la base del script SQL
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=file      # <-- MUY IMPORTANTE (ver abajo)
CACHE_STORE=file         # (en Laravel 10: CACHE_DRIVER=file)
QUEUE_CONNECTION=sync
```

> **Por qué `SESSION_DRIVER=file`:** Laravel 11+ viene con `SESSION_DRIVER=database`, que necesita
> una tabla `sessions` que **no existe** en `distribuidora_mysql.sql`. El carrito vive en la sesión,
> así que sin este cambio la app falla con "Table 'distribuidora.sessions' doesn't exist".
> Lo mismo con `CACHE_STORE` (tabla `cache`).

Luego: `php artisan config:clear`

### 3.4 Importar la base

Importar `distribuidora_mysql.sql` en phpMyAdmin (¡ojo! el script hace `DROP DATABASE distribuidora`).

### 3.5 Para el FRONT: token CSRF

En `resources/views/layouts/app.blade.php`, dentro de `<head>`, agregar:

```html
<meta name="csrf-token" content="{{ csrf_token() }}" />
```

Sin eso, todos los POST/PUT/PATCH/DELETE devuelven **419 Page Expired**.

### 3.6 Verificar

```
php artisan route:list --path=ventas
```

Debe listar las 17 rutas de `routes/ventas.php`.

---

## 4. Referencia de endpoints

Prefijo común: `/ventas`. Todas las llamadas **JSON** deben enviar:

```
Accept: application/json
Content-Type: application/json
X-CSRF-TOKEN: <valor del meta csrf-token>
```

### 4.1 Mapa Pantalla → Endpoint

| Elemento de la pantalla                             | Método y URL                                                                 | Body                                                                             |
| --------------------------------------------------- | ---------------------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| Al cargar / recargar                                | `GET /ventas/carrito` (o usar la variable `$carrito` que ya recibe la vista) | —                                                                                |
| Buscador "Buscar producto por nombre o código…"     | `GET /ventas/productos/buscar?q=texto`                                       | —                                                                                |
| Elegir un producto del buscador                     | `POST /ventas/carrito/productos`                                             | `{"id_producto":8,"cantidad":1}`                                                 |
| Columna CANTIDAD (editar)                           | `PATCH /ventas/carrito/productos/{id}`                                       | `{"cantidad":12}`                                                                |
| Quitar una fila                                     | `DELETE /ventas/carrito/productos/{id}`                                      | —                                                                                |
| Botón **Lista 1 — Minorista / Lista 2 — Mayorista** | `PUT /ventas/carrito/lista`                                                  | `{"lista":"mayorista"}`                                                          |
| Botón **BUSCAR CLIENTE** (lista de resultados)      | `GET /ventas/clientes/buscar?q=texto`                                        | —                                                                                |
| Elegir cliente / volver a Consumidor Final          | `PUT /ventas/carrito/cliente`                                                | `{"id_cliente":3}` o `{"id_cliente":null}`                                       |
| Botón **NUEVO CLIENTE (F1)**                        | `POST /ventas/clientes`                                                      | ver 4.3                                                                          |
| Botón **DESCUENTOS** → ver promos                   | `GET /ventas/promociones/activas`                                            | —                                                                                |
| Botón **DESCUENTOS** → agregar combo                | `POST /ventas/carrito/promociones/{id}`                                      | —                                                                                |
| Botón **DESCUENTOS** → descuento a una fila         | `PATCH /ventas/carrito/productos/{id}/descuento`                             | `{"porcentaje":15}` (`0` = sin descuento, `null` = volver a la promo automática) |
| Botón **LIMPIAR**                                   | `DELETE /ventas/carrito`                                                     | —                                                                                |
| Botón **COBRAR**                                    | `POST /ventas/cobrar`                                                        | ver 4.4                                                                          |
| Botón **CERRAR CAJA**                               | `GET /ventas/caja/resumen` (vista previa) y `POST /ventas/caja/cerrar`       | `{"efectivo_contado":19800}` (opcional)                                          |

### 4.2 El objeto `carrito` (lo devuelven TODOS los endpoints de carrito)

```json
{
    "ok": true,
    "carrito": {
        "cliente": {
            "id": null,
            "nombre": "Consumidor Final",
            "cuit": "11.111.111-1",
            "condicion_iva": "Consumidor Final",
            "domicilio": "",
            "tipo_cliente": null,
            "saldo": 0,
            "es_consumidor_final": true
        },
        "lista": "minorista",
        "lineas": [
            {
                "id_producto": 8,
                "codigo": "GOL-001",
                "descripcion": "Alfajor triple x unidad",
                "cantidad": 100,
                "precio_unitario": 700,
                "bruto": 70000,
                "descuento_pct": 5,
                "descuento": 3500,
                "origen_descuento": "promo",
                "promocion": {
                    "id": 1,
                    "nombre": "Alfajores 5% OFF (100+ u.)"
                },
                "descuento_manual": null,
                "subtotal": 66500,
                "stock_disponible": 300,
                "problema": null
            }
        ],
        "totales": {
            "articulos": 1,
            "unidades": 100,
            "subtotal_bruto": 70000,
            "descuento_total": 3500,
            "total": 66500
        },
        "puede_cobrar": true,
        "advertencias": []
    }
}
```

**Cómo se corresponde con la pantalla**

| Pantalla                                                              | Campo                                                                                                         |
| --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| "EMITIR FACTURA A: Consumidor Final"                                  | `cliente.nombre`                                                                                              |
| "CUIT: 11.111.111-1" / texto azul                                     | `cliente.cuit` / `cliente.condicion_iva`                                                                      |
| "Domicilio/Localidad"                                                 | `cliente.domicilio` (vacío en Consumidor Final: mostrar el placeholder)                                       |
| Botón de lista resaltado                                              | `lista`                                                                                                       |
| Columnas CÓD. / DESCRIPCIÓN / CANTIDAD / PRECIO U. / DESC. / SUBTOTAL | `codigo` / `descripcion` / `cantidad` / `precio_unitario` / `descuento` (en $) o `descuento_pct` / `subtotal` |
| "Carrito vacío — busque un producto"                                  | `lineas.length === 0`                                                                                         |
| "0 art. · 0 unid."                                                    | `totales.articulos` · `totales.unidades`                                                                      |
| "Total $ 0,00"                                                        | `totales.total`                                                                                               |
| Botón COBRAR deshabilitado (el celeste apagado)                       | `puede_cobrar === false`                                                                                      |
| Fila en rojo                                                          | `lineas[i].problema` distinto de `null` (stock insuficiente / producto inactivo)                              |

> Los importes llegan como **números** (no texto). El formato `$ 1.234,56` lo da el front con
> `new Intl.NumberFormat('es-AR', {style:'currency', currency:'ARS'})`.

### 4.3 Buscadores y alta de cliente

**`GET /ventas/productos/buscar?q=agua`**

```json
{
    "ok": true,
    "lista": "minorista",
    "productos": [
        {
            "id_producto": 1,
            "codigo": "BEB-001",
            "nombre": "Agua mineral 2L",
            "precio": 1200,
            "stock": 250,
            "sin_stock": false,
            "coincidencia_exacta": false
        }
    ]
}
```

`coincidencia_exacta: true` ⇒ el texto coincide exacto con el código (lector de código de barras): el front puede agregar directo.

**`GET /ventas/clientes/buscar?q=estrella`**

```json
{
    "ok": true,
    "clientes": [
        {
            "id_cliente": 2,
            "nombre": "Marcela Supermercado La Estrella SRL",
            "dni_cuit": "30-70123456-9",
            "condicion_iva": "Responsable Inscripto",
            "tipo_cliente": "mayorista",
            "domicilio": "Av. Colón 2500, Bahía Blanca",
            "saldo": 0
        }
    ]
}
```

**`POST /ventas/clientes`** (alta rápida). Requeridos: `nombre`, `apellido_razon_social`, `dni_cuit` (único),
`telefono`, `email` (único), `direccion`, `tipo_cliente`. Opcionales: `localidad`, `condicion_iva`, `id_zona`, `seleccionar` (default `true`).
Responde **201** con `cliente` y el `carrito` ya con ese cliente elegido.
Errores de validación: **422** con formato estándar de Laravel `{"message":"...","errors":{"campo":["..."]}}`.

> El atajo de teclado **F1** lo maneja el front (`keydown`); el back solo expone el endpoint.

### 4.4 COBRAR

`POST /ventas/cobrar`

```json
{
    "pagos": [
        { "medio_pago": "efectivo", "monto": 5000 },
        { "medio_pago": "tarjeta", "monto": 3000.5 }
    ],
    "observaciones": "texto opcional"
}
```

- `medio_pago` ∈ `efectivo | transferencia | cheque | tarjeta`.
- `pagos` vacío o ausente ⇒ **todo a cuenta corriente** (solo con cliente registrado).

Respuesta **200**:

```json
{
    "ok": true,
    "venta": {
        "id_venta": 6,
        "fecha": "2026-09-30",
        "estado": "pagada",
        "total": 23300,
        "pagado": 23300,
        "vuelto": 1700,
        "pendiente": 0,
        "comprobantes": ["REC-0004"]
    }
}
```

- `vuelto`: efectivo a devolver al cliente en mano.
- `pendiente > 0`: quedó deuda en `cliente.saldo` y la venta queda `confirmada`.
- Después de cobrar el carrito queda **vacío** en el servidor: el front debe pedir/redibujar `GET /ventas/carrito`.

### 4.5 Promociones activas (`GET /ventas/promociones/activas`)

Devuelve cada promo con `porcentaje`, vigencia, `reglas` (condiciones), `productos` (con `precio_lista` y
`precio_con_descuento` según la lista activa) y `total_combo`. Detalle completo en el comentario de `PromocionController`.

### 4.6 Caja (`GET /ventas/caja/resumen`, `POST /ventas/caja/cerrar`)

Query/body opcionales: `fecha` (`YYYY-MM-DD`), `efectivo_contado`.

```json
{
    "ok": true,
    "caja": {
        "fecha": "2026-09-30",
        "id_usuario": 1,
        "ventas": { "cantidad": 3, "canceladas": 0, "total": 45000 },
        "ingresos_por_medio": { "efectivo": 20000, "tarjeta": 25000 },
        "total_ingresos": 45000,
        "total_egresos": 0,
        "efectivo_esperado": 20000,
        "efectivo_contado": 19800,
        "diferencia": -200,
        "tipo_diferencia": "faltante"
    }
}
```

### 4.7 Errores (formato único)

| Situación                                                  | HTTP | Cuerpo                                                          |
| ---------------------------------------------------------- | ---- | --------------------------------------------------------------- |
| Regla de negocio (stock, carrito vacío, cliente inactivo…) | 422  | `{"ok":false,"mensaje":"...","errores":{}}` → mostrar `mensaje` |
| Producto/cliente/promo inexistente                         | 404  | idem                                                            |
| Datos mal enviados (validación de campos)                  | 422  | `{"message":"...","errors":{"campo":["..."]}}`                  |
| Falta token CSRF                                           | 419  | —                                                               |

### 4.8 Ejemplo de helper `fetch` para el front

```js
const token = document.querySelector('meta[name="csrf-token"]').content;

async function api(method, url, body) {
    const res = await fetch(url, {
        method,
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": token,
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.mensaje || data.message || "Error");
    return data;
}

// Ejemplos
const { carrito } = await api("POST", "/ventas/carrito/productos", {
    id_producto: 8,
    cantidad: 1,
});
render(carrito); // el front solo redibuja con lo que devuelve el back
```

---

## 5. Reglas de negocio implementadas

**Precios**

- Lista 1 Minorista → `producto.precioMin`. Lista 2 Mayorista → `producto.precioMay`.
- Al elegir un cliente, la lista pasa a su `tipo_cliente`. Consumidor Final ⇒ minorista. Se puede cambiar a mano.

**Descuentos (por línea)** — prioridad:

1. Manual del cajero (incluso `0`, que anula la promo).
2. Mejor promoción vigente (estado activo + dentro de fechas + producto incluido + reglas de `config/ventas.php`). Nunca se acumulan promos.
3. Ninguno.

`descuento ($) = round(precio × cantidad × % / 100, 2)` — coincide con los datos de prueba (ej: 100 alfajores × $700 con 5% = $3.500 de descuento, subtotal $66.500).

**Stock**

- No se puede agregar más que el stock disponible. Se re-verifica (con bloqueo de filas) al cobrar.
- Al cobrar: `producto.stock` baja, se crea `movimiento_stock` (tipo `venta`) y se descuentan los `lote` por vencimiento más próximo (FEFO).

**Cobro**

- Consumidor Final paga el total completo. Cliente registrado puede pagar parcial o nada (queda en `cliente.saldo`).
- Varios medios de pago en una misma venta. Exceso = vuelto, solo cubierto por la parte en efectivo.
- Venta resultante: `pagada` (cobrada completa) o `confirmada` (con deuda).
- Se generan: `venta`, `detalle_venta`, `cobro` (+`cobro_venta`), `caja_movimiento` (ingreso) y `log_auditoria`.

---

## 6. Supuestos y limitaciones (leer antes de la demo)

1. **Probado solo a nivel de sintaxis (`php -l`).** No se ejecutó contra su Laravel + MySQL; hay que correr la lista de la sección 7.
2. **Promociones — condiciones:** la tabla `promocion` no guarda "cantidad mínima" ni "solo mayoristas" (están en la descripción de los datos demo). Se cargaron en `config/ventas.php → reglas_promocion` (promo 1: mínimo 100 u.; promo 2: lista mayorista). Si se agregan promos nuevas con condiciones, hay que sumarlas ahí. Mejor solución a futuro: agregar columnas a `promocion` y coordinar con el equipo dueño de esa tabla.
3. **Columna `promocion.total`:** se interpreta como **porcentaje** (así lo indica el script SQL).
4. **Consumidor Final** se guarda con `venta.id_cliente = NULL`.
5. **Facturación:** `venta.numFactura` queda `NULL` (no hay integración AFIP/ARCA). El estado `facturada` no se usa desde el POS.
6. **Cerrar caja = arqueo de consulta.** El esquema no tiene tabla de cierres, así que no queda registro de "caja cerrada" ni se bloquean ventas posteriores. Los egresos no tienen medio de pago: se asumen en efectivo. Si se necesita historial de cierres, proponer una tabla `cierre_caja`.
7. **Usuario/login:** se usa `session('usuario_id')` si existe; si no, el usuario demo `1`. Ver `App\Support\UsuarioActual`.
8. **Unidades:** se vende siempre en unidad base. Vender por Pack/Caja (`unidad_medida`) sería una ampliación.
9. **No incluido (no figura en la pantalla):** historial de ventas, anulación/devolución de ventas, límite de crédito del cliente, stock mínimo/alertas.

---

## 7. Pruebas rápidas con los datos de ejemplo

Usar Thunder Client / Postman / consola del navegador (con cookie de sesión y CSRF).
Los números salen de `distribuidora_mysql.sql`.

| #   | Acción                                                            | Resultado esperado                                                     |
| --- | ----------------------------------------------------------------- | ---------------------------------------------------------------------- |
| 1   | Agregar prod. `2` ×6 y prod. `8` ×10 (Consumidor Final)           | `total = 23300` (13.800 + 9.500), sin descuentos                       |
| 2   | Cobrar `efectivo 25000`                                           | `estado: pagada`, `vuelto: 1700`; `producto 2` stock −6                |
| 3   | `PUT cliente {id_cliente:2}` (mayorista) + agregar prod. `8` ×100 | `lista: mayorista`, `descuento_pct: 5`, `subtotal = 66500`             |
| 4   | Bajar a prod. `8` ×99                                             | descuento desaparece (promo 1 exige 100)                               |
| 5   | Cliente `3` + `POST carrito/promociones/2`                        | Lavandina + Detergente con 10%: total `2250`                           |
| 6   | Cobrar sin `pagos` con cliente `3`                                | `estado: confirmada`, `pendiente: 2250`; `cliente.saldo` 85280 → 87530 |
| 7   | Consumidor Final + cobrar pagando de menos                        | 422 "Consumidor Final debe abonar el total…"                           |
| 8   | Agregar prod. `10` ×9 (stock 8)                                   | 422 "Stock insuficiente…"                                              |
| 9   | `PATCH …/descuento {porcentaje: 0}` en una línea con promo        | el descuento de esa línea pasa a 0                                     |
| 10  | `GET caja/resumen` tras las ventas                                | `ingresos_por_medio` y `efectivo_esperado` coherentes                  |

Para dejar la base como al inicio: volver a importar `distribuidora_mysql.sql`.

---

## 8. Mapa de dependencias (¿qué se rompe si cambio X?)

- Cambia el nombre de una **columna/tabla** → revisar el **Model** correspondiente y los `DB::table(...)` de `VentaService`/`CajaService`.
- Cambia la **forma del JSON del carrito** → afecta a todo el front. Se define en un solo lugar: `CarritoService::estado()`.
- Cambia una **regla de descuento** → `CarritoService::estado()` (prioridades) o `PromocionService` (qué promo gana).
- Cambia una **condición de promo** → `config/ventas.php`.
- Llega el **login** → solo `App\Support\UsuarioActual`.
- Se integra **facturación electrónica** → `VentaService::cobrar()` (completar `numFactura`).
- Se agrega un **medio de pago** → ENUM `cobro.medio_pago` en MySQL **y** `config/ventas.php → medios_pago`.
