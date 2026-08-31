# Arquitectura Técnica — ERP Distribuidora

> Última actualización: 12/08/2026
> Responsable: Estefania Gianovich — Grupo 2

## Arquitectura general

**Tipo:** Monolito modular con enfoque de microservicios (no microservicios puros). El sistema se organiza en módulos separados por responsabilidad de dominio (clientes, productos, stock, ventas, facturación, entregas/logística, reportes, usuarios), cada uno con bajo acoplamiento y comunicándose mediante contratos de API REST definidos. La diferencia con microservicios puros: no hay una base de datos por servicio, se usa una única base de datos compartida — decisión tomada por alcance del proyecto e indicación del profesor. Cada módulo no debería acceder directamente a la lógica de otro; la comunicación se hace mediante los endpoints/contratos acordados, aunque físicamente todos los datos vivan en la misma base.

**Organización del código:** Un solo repositorio (ERP-Juan23). Estructura de ramas: main (código testeado y finalizado) → develop (integración de todos los grupos) → develop-g1/develop-g2/develop-g3/develop-g4 (una por grupo) → feature/gX-nombre-apellido (una por integrante). El Grupo 2 tiene 4 integrantes: Nicolás, Sofía, Estefanía (yo) y Tomás.

**Propiedad de datos:** Como es monolito modular con base compartida, la "propiedad de datos" no es física (todas las tablas están en la misma BD) sino lógica/de responsabilidad: cada módulo es responsable de su dominio y los demás módulos no deberían tocar directamente sus tablas, sino consultarlo vía su API interna. Ejemplo confirmado: el módulo de Ventas no maneja la lógica de clientes, productos o stock — los consulta a los módulos correspondientes cuando necesita esa info (valida cliente activo, verifica stock disponible, se comunica con facturación para generar el comprobante).

**Asignación de módulos dentro del Grupo 2:** se define sobre la marcha, no hay asignación fija por integrante todavía (ver `01-negocio-y-alcance.md`).

## Comunicación entre módulos

**Mecanismo:** API REST interna, con contratos formales por interacción (define qué envía un servicio, qué recibe como respuesta, y qué errores puede devolver).

**Formato de respuesta estándar:** JSON. En errores: `{ "error": true/false, "codigo": N, "mensaje": "..." }`. En éxito: el recurso solicitado directamente.

**Versionado:** por URL (`/api/v1/...`). Se crea `/api/v2` solo cuando un cambio rompe compatibilidad (elimina/renombra campos, cambia estructura de respuesta, cambia forma de autenticar, etc.)

### Endpoints de otros módulos que consumimos (Grupo 2: Entregas, Facturación, Caja, Cobros, Conciliación, Rendiciones)

| Módulo dueño | Endpoint | Método | Para qué lo usamos |
|---|---|---|---|
| Clientes (G1) | /clientes/{id} | GET | Validar cliente / obtener datos para entrega (dirección, nombre) |
| Auth (G1) | /auth/login | POST | Autenticación — devuelve token_sesion, permisos (evidencia de sesión de servidor, no JWT) |
| Auth (G1) | /auth/logout | POST | Cerrar sesión |
| Usuarios (G1) | /usuarios/{id}/permisos | GET | Verificar permisos del usuario logueado |
| Ventas (G4) | /ventas/{id} | GET | Obtener datos completos de una venta para generar entrega/factura |
| Ventas (G4) | /ventas | GET | Listar ventas con filtros (por estado de facturación, cliente) |
| Ventas (G4) | /ventas/{id}/estado | PATCH | Actualizar estado de venta (ej. a "facturada") tras facturar/entregar |
| Stock (G3) | /stock/descuentos | POST | Se descuenta stock al confirmarse una venta (lo llama el módulo Ventas, pero afecta la disponibilidad que nuestro módulo de Entregas necesita conocer) |
| Stock (G3) | /stock/devoluciones | POST | Explícitamente marcado como "llamado por Entregas" — registrar devolución de mercadería |
| Saldos (G4) | /saldos/clientes/{id} | GET | Estado de cuenta corriente del cliente — relevante para Cobros |
| Saldos (G4) | /saldos/pagos | POST | Registrar pago que impacta la cuenta corriente — relevante para Cobros |
| Promociones (G4) | /promociones/validar | POST | Si Facturación necesita validar descuentos aplicados a una venta |

🚩 Bandera roja: no se encontraron endpoints propios de Facturación (ni en G2 ni en G4) — está mencionada como concepto (factura_id aparece en Ventas, Cobros, Entregas, Rendiciones) pero nadie definió formalmente su CRUD todavía. Confirmar con el grupo quién la desarrolla y cuáles son sus endpoints.

### Endpoints que exponemos nosotros (Grupo 2)

**Entregas:**
| Método | Endpoint | Descripción |
|---|---|---|
| GET | /entregas | Listar entregas/remitos con filtros (repartidor, fecha, estado) |
| GET | /entregas/{id} | Detalle completo de entrega/remito |
| POST | /entregas | Crear entrega/remito (con factura o manual) |
| PATCH | /entregas/{id}/actualizar-estado | Actualizar estado (en_transito/entregada/no_entregada/rechazada) |
| PATCH | /entregas/{id}/registrar-firma | Registrar firma/confirmación de entrega |
| PATCH | /entregas/{id}/rechazar | Rechazar entrega |
| GET | /entregas?repartidor_id={id}&fecha={fecha} | Entregas del día para un repartidor (insumo para Rendición) |

**Cobros:**
| Método | Endpoint | Descripción |
|---|---|---|
| GET | /cobros | Listar cobros con filtros |
| GET | /cobros/{id} | Detalle completo de cobro |
| POST | /cobros | Registrar cobro (vinculado a factura o cuenta) |
| PATCH | /cobros/{id} | Modificar cobro — solo admin, validar |
| DELETE | /cobros/{id} | Rechazar/anular cobro (solo si está pendiente de validación) |

**Caja:**
| Método | Endpoint | Descripción |
|---|---|---|
| GET | /caja/{fecha} | Movimientos de caja de un día |
| POST | /caja/movimiento | Registrar movimiento manual (ingreso/egreso) |
| GET | /caja/movimiento/{id} | Detalle de un movimiento |
| PATCH | /caja/movimiento/{id} | Modificar movimiento manual (solo admin) |

**Conciliación Bancaria:**
| Método | Endpoint | Descripción |
|---|---|---|
| GET | /conciliacion | Listar períodos de conciliación |
| GET | /conciliacion/{id} | Detalle de conciliación |
| POST | /conciliacion | Crear período de conciliación |
| PATCH | /conciliacion/{id}/conciliar | Conciliar movimiento específico con banco |
| PATCH | /conciliacion/{id}/cerrar | Cerrar período de conciliación |

**Rendiciones:**
| Método | Endpoint | Descripción |
|---|---|---|
| GET | /rendiciones | Listar rendiciones con filtros |
| GET | /rendiciones/{id} | Detalle completo de rendición |
| PATCH | /rendiciones/{id}/actualizar-factura | Actualizar estado de factura en rendición |
| PATCH | /rendiciones/{id}/cerrar | Repartidor cierra su jornada |
| PATCH | /rendiciones/{id}/revisar | Admin/gestora contable revisa y valida |

**Facturación:** sin endpoints definidos todavía (ver bandera roja arriba).

## Base de datos

**Estrategia:** una única base de datos compartida para todo el ERP. Aún no existe físicamente ninguna base de datos creada — el proyecto está en etapa de diseño del modelo.

**Motor:** MySQL. Confirmado por un compañero del equipo.

**Modelo de datos (diagrama entidad-relación en dbdiagram.io, transcripto):**

```
ZONA
- id_zona (PK), nombre, descripcion

CLIENTE
- id_cliente (PK), nombre, razon_social, CUIL (unique), email, telefono, direccion, localidad
- id_zona (FK → ZONA)
- tipo_cliente: mayorista | minorista | consumidor_final
- condicion_iva
- estado: activo | inactivo

USUARIO
- id_usuario (PK, y a la vez FK → CLIENTE.id_cliente) — CONFIRMADO: USUARIO hereda de CLIENTE (defendido explícitamente por el equipo de base de datos en la presentación). CLIENTE es la clase principal/base; USUARIO obtiene los atributos de CLIENTE más los específicos de USUARIO (user, password, rol, estado). Descarta la otra versión que aparecía en el diagrama-imagen original (donde CLIENTE parecía subtipo de USUARIO).
- user (unique), password
- rol: admin | vendedor | repartidor
- estado: activo | bloqueado

CATEGORIA — id_categoria (PK), nombre
MARCA — id_marca (PK), nombre

PRODUCTO
- id_producto (PK), codigo (unique), nombre, descripcion
- precioMay, precioMin, imagen, stock, stock_minimo
- estado: activo | inactivo
- id_categoria (FK), id_marca (FK), fecha_alta

UNIDAD_MEDIDA — id_unidad (PK), id_producto (FK), nombre_unidad, equivalencia_base, descripcion
LOTE — id_lote (PK), id_producto (FK), nro_lote, cantidad, fecha_vencimiento, estado: vigente|vencido|consumido

PROVEEDOR
- id_proveedor (PK), razon_social, CUIT (unique), telefono, email, direccion
- estado: activo | inactivo, plazo_entrega_dias, fecha_alta

PRODUCTO_PROVEEDOR (N:N) — id_producto_proveedor (PK), id_producto (FK), id_proveedor (FK), es_proveedor_principal, precio_acordado, activo, fecha_asociacion

COMPRA — id_compra (PK), numero_comprobante, id_proveedor (FK), importe_total, estado: pendiente|completada|cancelada, fecha_compra, id_usuario (FK)
DETALLE_COMPRA — id_detalle_compra (PK), id_compra (FK), id_producto (FK), id_unidad (FK), cantidad, precio_unitario, subtotal

VENTA
- id_venta (PK), id_cliente (FK), fecha, total, pagado (boolean), numFactura
- estado: pendiente | confirmada | facturada | cancelada
- observaciones, id_usuario (FK)

DETALLE_VENTA — id_detalle_venta (PK), id_venta (FK), id_producto (FK), id_promocion (FK, nullable), cantidad, precio_unitario, descuento, subtotal

PROMOCION — id_promocion (PK), nombre, tipo_descuento: porcentaje|monto_fijo, valor, vigencia_desde, vigencia_hasta, condiciones, estado: activa|pausada|baja
PROMOCION_PRODUCTO (N:N) — id_promocion (FK), id_producto (FK) — PK compuesta

ENTREGA
- id_entrega (PK), id_repartidor (FK → USUARIO), fecha
- estado: pendiente | en_transito | entregada | no_entregada
- observaciones

ENTREGA_PEDIDO (N:N) — id_entrega (FK), id_venta (FK) — PK compuesta
```

## Stack técnico

**Backend:** PHP orientado a objetos + Laravel, combinados por capas (NO es una contradicción — aclarado en clase). El patrón es: la lógica de negocio pura (cálculos, reglas, validaciones que no dependen de cómo llega la petición HTTP) se programa en clases de PHP OOP plano, independientes del framework. Laravel se usa como capa externa — recibe la petición HTTP, valida superficialmente, y delega el trabajo real a esas clases puras, sin meter Eloquent ni nada específico de Laravel dentro de ellas.

Ejemplo de la separación:
```
// Clase de dominio, PHP puro, sin imports de Laravel
namespace Dominio\Ventas;
class CalculadoraDeVenta {
    public function calcularTotal(array $items, ?Cliente $cliente): float { ... }
}

// Controller de Laravel, delega el cálculo real a la clase de dominio
namespace App\Http\Controllers;
use Dominio\Ventas\CalculadoraDeVenta;
class VentaController extends Controller {
    public function store(Request $request) {
        $calculadora = new CalculadoraDeVenta();
        $total = $calculadora->calcularTotal($request->items, $cliente);
        // Laravel se encarga de guardar con Eloquent, responder JSON, etc.
    }
}
```

Ventaja de este patrón: la lógica de negocio queda testeable sin necesitar que Laravel esté corriendo, portable a otro proyecto, y resistente a un futuro cambio de framework.

🚩 Bandera roja pendiente de precisar (prioridad alta, aclarar con el profesor o releyendo el material de esa clase): esto explica cómo pueden convivir Laravel y PHP puro *dentro* de un mismo módulo, pero no confirma si la intención es "todos los módulos usan Laravel como capa externa + PHP puro adentro" o si cada módulo puede elegir su stack libremente (lo cual sí volvería a ser incompatible *entre* módulos, como se documentó originalmente). Mientras no se confirme, se sigue avanzando con PDO plano por ser lo más simple y no depender de instalar/configurar Laravel todavía.

**Autenticación:** sesiones de servidor (no JWT), como apuesta informada. 🚩 A diferencia de Laravel/PHP (que son compatibles porque resuelven cosas distintas y se combinan por capas), JWT y sesiones compiten por resolver lo mismo — "¿cómo sabe el servidor si el usuario sigue logueado?" — con una decisión de fondo mutuamente excluyente: sesiones implican que el servidor consulta una tabla en cada request; JWT puro/stateless implica que el servidor no consulta nada, solo verifica la firma del token. Existe un patrón híbrido real (JWT + tabla de blacklist/whitelist para poder invalidar tokens), pero le saca la ventaja principal a JWT y probablemente no aplique acá. Se elige sesiones porque el endpoint real definido por Grupo 1 (POST /auth/login) devuelve token_sesion y permisos en la respuesta — patrón típico de sesiones, no de JWT. Además coincide con el contexto (usuarios internos, no app pública masiva). No hay apuro por resolver esto — el equipo no lo va a decidir formalmente hasta que se llegue a programar la autenticación. Si se programa con capas separadas (Controllers/Services/Repositories), el cambio a JWT más adelante debería afectar solo la capa de autenticación, no la lógica de negocio.

**Frontend:** [pendiente confirmación formal del equipo — no está definido si es HTML/JS por módulo o frontend único. Recomendación propia: frontend único que consuma las 4 APIs de los grupos, no uno separado por módulo. Razón: el backend ya está pensado como monolito modular con enfoque de microservicios (módulos separados pero funcionando como un solo sistema); un frontend fragmentado por grupo obligaría al usuario final (Diego, Alma, Matías) a saltar entre pantallas sueltas para completar un flujo simple como venta→entrega→cobro, reproduciendo justo la desconexión operativa que el cliente pidió resolver en la entrevista inicial. Cada grupo puede seguir armando y probando sus propias pantallas sueltas durante el desarrollo, pero el objetivo final debería ser un único punto de entrada visual.]

**Otras herramientas/librerías acordadas:**
- **Testing:** PHPUnit — estándar para testear PHP, independiente de si se usa framework o no. Relevante porque el criterio de "Done" del equipo exige código testeado antes de integrar (ver `03-convenciones-y-estado.md`).
- **Documentación/prueba de API (equipo):** Postman o Swagger/OpenAPI, para tener una vista compartida y presentable de los endpoints entre los 4 grupos.
- **Prueba de API (uso personal, Estefania):** REST Client (extensión de VS Code) — peticiones HTTP en archivos `.http`, versionables en Git junto con el código. No reemplaza a Postman como documentación de equipo, pero sirve perfecto para el testeo individual del día a día.
- [resto pendiente — librerías específicas de negocio (ej: generación de PDFs para reportes, manejo de fechas/vencimientos) se definen cuando se resuelva la lógica puntual de cada módulo, no antes]

**Estructura de carpetas del backend:** 🚩 Decisión de emergencia (temporal, no acordada formalmente por el equipo): organización por módulo primero, capa después — cada módulo (Entregas, Facturación, Caja, Cobros, ConciliacionBancaria, Rendiciones) tiene sus propias subcarpetas Controllers/Services/Repositories/Models, más una carpeta Shared para lo transversal (conexión PDO, router, formato de respuesta estándar, validación de sesión).

```
/src
  /Entregas
    /Controllers
    /Services
    /Repositories
    /Models

  /Facturacion
    /Controllers
    /Services
    /Repositories
    /Models

  /Caja
    ...
  /Cobros
    ...
  /ConciliacionBancaria
    ...
  /Rendiciones
    ...

  /Shared
    /Database
      Conexion.php          ← configuración PDO, un solo lugar
    /Http
      Router.php             ← enrutamiento simple hecho a mano
      Response.php            ← helper para responder JSON estándar {data, error, mensaje}
    /Auth
      SesionMiddleware.php    ← validación de sesión, reutilizable por todos los módulos

/public
  index.php                  ← punto de entrada único

/config
  database.php                ← credenciales, separado del código
```

Razón de la elección: coherente con la arquitectura "monolito modular con enfoque de microservicios" — cada carpeta de módulo funciona como un microservicio en potencia (bajo acoplamiento, fácil de separar a futuro), en vez de mezclar los 6 módulos dentro de las mismas carpetas de Controllers/Services/etc. Si se confirma el uso de Laravel más adelante, esta estructura queda obsoleta (el framework impone la suya propia).

## Notas y dudas pendientes

**Sobre arquitectura y autenticación:**
- Laravel y PHP OOP plano NO son incompatibles entre sí (aclarado en clase, ver detalle en "Stack técnico" → Backend) — pero falta confirmar si TODOS los módulos deben seguir ese patrón de capas (Laravel externo + PHP puro adentro) o si cada módulo elige libremente, lo cual sí volvería a generar incompatibilidad *entre* módulos. Mientras tanto, se sigue avanzando con PDO plano.
- ¿Autenticación vía JWT o sesiones de servidor? A diferencia de Laravel/PHP, esto sí es una decisión de fondo mutuamente excluyente (ver detalle en "Stack técnico" → Autenticación). Se decidió avanzar con sesiones como apuesta informada. Sin apuro — el equipo no lo definiría formalmente hasta llegar a programar la autenticación.
- Estructura de carpetas del backend (módulo primero, capa después) — no está acordada formalmente por el equipo, es solo una decisión temporal para no frenar el desarrollo. Confirmar con los 4 grupos, ya que afecta la integración en develop. Si se confirma Laravel, esta estructura queda obsoleta.

**Sobre base de datos:**
- No hay tablas modeladas para Facturación, Caja, Cobros, Conciliación Bancaria y Rendiciones — solo Entregas está modelada hasta ahora, a pesar de que sí existen endpoints definidos para esos módulos. Hay que diseñar esas tablas.
- VENTA.numFactura y VENTA.pagado están como campos sueltos en Venta — definir si Factura debería ser una entidad/tabla propia en cambio (tiene sentido que lo sea, dado que Cobros, Rendiciones y Entregas referencian constantemente un factura_id).
- ENTREGA no tiene campo ni relación directa con una dirección de destino. La única forma de llegar a una dirección es la cadena ENTREGA → ENTREGA_PEDIDO → VENTA → CLIENTE.direccion. Confirmar si esto es intencional (se asume dirección única del cliente) o si falta agregar un campo propio en ENTREGA — importante si en algún momento un cliente puede tener más de una dirección posible de entrega.

**Sobre endpoints:**
- No existen endpoints definidos para el módulo de Facturación — confirmar quién lo desarrolla y su diseño.

**Sobre stack técnico:**
- Confirmar integración de frontend: ¿HTML/JS por módulo, o un frontend único consumiendo todas las APIs?
- Confirmar librerías/herramientas específicas acordadas por el equipo (más allá del lenguaje base).
