# Integracion entre grupos

Contrato detallado de campos, respuestas JSON y estados HTTP de cada ruta publicada: [contrato-api-g1.md](contrato-api-g1.md).

El Grupo 1 publica JSON en `/api/v1`. Las rutas de `routes/web.php` son pantallas HTML y no forman parte de este contrato. Los IDs son enteros positivos. Fechas y horas se expresan en ISO 8601 con zona horaria; importes en ARS usan cadenas decimales con dos cifras, sin separadores de miles. Las respuestas de listas usan `data` y, cuando hay paginacion, `meta`.

## Autenticacion

La API acepta dos tipos de token en `Authorization: Bearer ...`:

- **Token interno de servicio** (`INTERNAL_API_TOKEN`): para consultas y reclamos entre servicios. No autoriza altas ni cambios de clientes.
- **Token de usuario**: lo entrega `POST /api/v1/auth/login`. Administrativo y repartidor pueden consultar clientes, zonas y reclamos; solo administrativo puede consultar usuarios o crear y actualizar clientes. `POST /api/v1/auth/logout` lo revoca. Es valido durante 120 minutos desde su emision, o el valor de `SESSION_LIFETIME` si se configura otro.

Cada llamada autenticada debe incluir:

```http
Authorization: Bearer <TOKEN_INTERNO_O_DE_USUARIO>
Accept: application/json
```

El token interno se configura en `INTERNAL_API_TOKEN` y se comparte solo entre servidores de confianza. Nunca se envia un token en la URL. Sin token valido, las rutas protegidas devuelven `401`; un token de servicio o un usuario sin permiso para modificar clientes recibe `403`. Los errores de validacion devuelven `422` con `message` y `errors`; un recurso inexistente devuelve `404`. El login admite cinco peticiones por minuto por origen y reutiliza el bloqueo de cuenta despues de tres contrasenas incorrectas. Las otras rutas se limitan a 60 peticiones por minuto.

Para probar localmente, generar un valor con `C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`, asignarlo a `INTERNAL_API_TOKEN` en `.env` y ejecutar `C:\xampp\php\php.exe artisan config:clear`. Con el servidor levantado, por ejemplo:

```bash
curl -H "Authorization: Bearer TU_TOKEN" -H "Accept: application/json" http://localhost:8000/api/v1/clientes
```

## Endpoints publicados por G1

| Metodo | Ruta | Parametros / cuerpo | Respuesta |
| --- | --- | --- | --- |
| GET | `/api/v1/health` | Ninguno | `{ "status": "ok" }` |
| GET | `/api/v1/clientes` | `buscar`, `estado`, `tipo`, `zona_id`, `page`, `per_page` | Clientes y `meta` |
| POST | `/api/v1/clientes` | Ficha nueva completa; token de administrador | Cliente creado (`201`) y cabecera `Location` |
| GET | `/api/v1/clientes/{id}` | Ninguno | Ficha de cliente |
| PUT | `/api/v1/clientes/{id}` | Ficha editable completa; token de administrador | Cliente reemplazado (`200`) |
| PATCH | `/api/v1/clientes/{id}` | Uno o mas campos editables; token de administrador | Cliente modificado (`200`) |
| GET | `/api/v1/clientes/{id}/saldo` | Ninguno | Saldo y moneda |
| GET | `/api/v1/clientes/{id}/cobros` | `page`, `per_page` | Cobros confirmados, del mas reciente al mas antiguo |
| GET | `/api/v1/zonas` | Ninguno | Zonas ordenadas por nombre |
| GET | `/api/v1/reclamos` | `cliente_id`, `estado`, `page`, `per_page` | Reclamos y `meta` |
| GET | `/api/v1/reclamos/{id}` | Ninguno | Reclamo |
| POST | `/api/v1/reclamos` | JSON de alta | Reclamo creado (`201`) |
| PATCH | `/api/v1/reclamos/{id}/estado` | `{ "estado": "en_proceso" }` | Reclamo actualizado |
| GET | `/api/v1/usuarios/{id}` | Ninguno | Solo ID, nombre, rol y estado |
| POST | `/api/v1/auth/login` | `{ "usuario": "admin", "password": "..." }`; sin token previo | Token de usuario y vencimiento (`200`) |
| POST | `/api/v1/auth/logout` | Token de usuario activo | Confirma cierre de sesion (`200`) |

### Login y logout JSON

```http
POST /api/v1/auth/login
Content-Type: application/json
Accept: application/json

{"usuario":"admin","password":"tu_contrasena"}
```

Respuesta de ejemplo:

```json
{
  "data": {
    "token_type": "Bearer",
    "access_token": "TOKEN_OPACO",
    "expires_at": "2026-10-06T16:00:00-03:00",
    "usuario": { "id": 1, "nombre": "Administrador", "rol": "administrativo" }
  }
}
```

Enviar `access_token` como Bearer en las siguientes llamadas. Un nuevo login de la misma cuenta invalida el token anterior y cualquier sesion web anterior. El login web tambien invalida el token API anterior. Para cerrar sesion:

```http
POST /api/v1/auth/logout
Authorization: Bearer TOKEN_OPACO
Accept: application/json
```

El token deja de funcionar inmediatamente despues del logout o al vencer. No se entrega un token interno de servicio mediante login.

### Alta y cambios de clientes

`POST /api/v1/clientes` requiere `nombre`, `apellido_razon_social`, `dni_cuit`, `telefono`, `email`, `direccion` y `tipo_cliente` (`minorista` o `mayorista`). `localidad`, `zona_id` y `condicion_iva` son opcionales. El cliente se crea `activo` y `creado_por` se toma del token administrativo.

`PUT /api/v1/clientes/{id}` requiere los siete campos anteriores, `estado` (`activo` o `inactivo`) y la presencia explicita de `localidad`, `zona_id` y `condicion_iva` (pueden valer `null`). `PATCH /api/v1/clientes/{id}` requiere al menos un campo editable y conserva todos los demas. DNI/CUIT y email deben ser unicos; `zona_id` debe existir cuando se especifica.

Ejemplo de alta:

```json
{
  "nombre": "Ana",
  "apellido_razon_social": "Comercial Norte",
  "dni_cuit": "20-12345678-9",
  "telefono": "11-4444-5555",
  "email": "ana@example.com",
  "direccion": "Calle Principal 123",
  "tipo_cliente": "mayorista",
  "localidad": "San Isidro",
  "zona_id": 3,
  "condicion_iva": "Responsable Inscripto"
}
```

Ejemplo de cambio parcial:

```http
PATCH /api/v1/clientes/12
Authorization: Bearer TOKEN_DE_ADMINISTRADOR
Content-Type: application/json

{"telefono":"11-5555-6666","localidad":null}
```

`saldo` y `creado_por` no son campos editables por estos endpoints: el saldo se reduce mediante cobros y el creador se fija en el alta. La comunicacion de cargos desde Ventas sigue pendiente de acuerdo entre grupos. Si se envian esos campos, la API responde `422`.

Ejemplo de cliente:

```json
{
  "data": {
    "id": 12,
    "nombre": "Ana",
    "apellido_razon_social": "Comercial Norte",
    "dni_cuit": "20-12345678-9",
    "telefono": "11-4444-5555",
    "email": "ana@example.com",
    "direccion": "Calle Principal 123",
    "localidad": "San Isidro",
    "zona_id": 3,
    "condicion_iva": "Responsable Inscripto",
    "tipo_cliente": "mayorista",
    "estado": "activo",
    "saldo": "12500.00"
  }
}
```

`GET /clientes/{id}/saldo` devuelve `data: {"cliente_id": 12, "saldo": "12500.00", "moneda": "ARS"}`. Los cobros incluyen `id`, `cliente_id`, `fecha`, `monto_total`, `medio_pago`, `comprobante_nro`, `observaciones`, `usuario_id` y `usuario_nombre`.

Para crear un reclamo:

```json
{
  "cliente_id": 12,
  "usuario_id": 4,
  "asunto": "Mercaderia incompleta",
  "descripcion": "Faltaron dos productos del pedido",
  "prioridad": "alta"
}
```

El cliente debe estar activo y el usuario debe ser administrativo o repartidor activo. El reclamo nace en `abierto`. Prioridades: `baja`, `media`, `alta`. Estados: `abierto`, `en_proceso`, `cerrado`. El cliente y usuario que se envian quedan registrados como autor y asociado del reclamo; cada consumidor debe enviar IDs verificables.

## JSON requerido de Ventas

El Grupo 1 consulta `GET /api/v1/ventas?fecha=YYYY-MM-DD` para ventas diarias y `GET /api/v1/ventas?cliente_id=12&desde=YYYY-MM-DD&hasta=YYYY-MM-DD` para el historial. Los filtros `desde` y `hasta` son opcionales. Ambas consultas responden `{ "data": [...] }` sin paginacion; el resultado debe contener **todas** las operaciones del filtro. Se espera orden ascendente por fecha en ventas diarias y descendente en historial.

Cada elemento debe tener `fecha` (ISO 8601), `numero_factura` (cadena o `null`), `total` (decimal), `estado` (`pendiente`, `confirmada`, `pagada`, `facturada` o `cancelada`), `cliente` con `nombre` y `apellido_razon_social`, y `detalles` como lista de `{ cantidad, subtotal, producto: { nombre } }`. Para ventas diarias se muestran solamente `confirmada`, `pagada` y `facturada`; el proveedor debe aplicar ese filtro. Para historial deben venir todos los estados.

Ejemplo listo para usar: [ventas.json](contracts/ventas.json).

## JSON requerido de Inventario

El Grupo 1 consulta `GET /api/v1/productos/stock-bajo?orden=asc` o `orden=desc`. La respuesta `{ "data": [...] }` debe incluir todos los productos con `stock < stock_minimo`, ordenados por `stock`. Cada producto tiene `codigo`, `nombre`, `stock`, `stock_minimo`, `categoria` y `marca`; las dos ultimas pueden ser `null` o `{ "nombre": "..." }`.

Ejemplo listo para usar: [stock-bajo.json](contracts/stock-bajo.json).

## Configuracion local y remota

```dotenv
INTERNAL_API_TOKEN=valor_aleatorio_largo
SALES_SERVICE_DRIVER=local
SALES_SERVICE_URL=http://ventas:8000
SALES_SERVICE_TOKEN=token_del_servicio_ventas
INVENTORY_SERVICE_DRIVER=local
INVENTORY_SERVICE_URL=http://inventario:8000
INVENTORY_SERVICE_TOKEN=token_del_servicio_inventario
```

Con `local`, reportes y exportaciones leen las tablas actuales `ventas` y `productos`. Con `http`, consultan exclusivamente los endpoints JSON especificados. Tras cambiar `.env`, ejecutar `php artisan config:clear`. No se crea un registro local alternativo si el servicio remoto falla: se devuelve `502` o `503` para que el error sea visible.

Los cobros y el saldo pertenecen a G1. En modo local un cobro tambien se distribuye entre ventas locales en `cobro_ventas`. En modo HTTP el cobro se registra en la cuenta del cliente, sin escribir en la base de ventas externa. La conciliacion a nivel de venta entre servicios requiere acordar un endpoint de aplicacion de cobros antes de mover definitivamente la propiedad de las ventas.

Ademas, el alta de una venta externa debe comunicar el cargo al servicio G1 para incrementar `clientes.saldo`. Ese mensaje y su mecanismo de idempotencia todavia no forman parte del contrato y deben acordarse con el grupo de Ventas antes de operar con ventas reales en bases separadas. Los reportes de lectura ya pueden cambiarse al modo HTTP de forma independiente.
