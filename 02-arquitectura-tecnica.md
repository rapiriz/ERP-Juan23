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

✅ **Decisión tomada (nivel negocio e implementación):** el disparo de facturación será **"Facturación inmediata al confirmar venta"** — la factura se genera apenas Ventas confirma el pedido, desacoplada del remito/entrega. Ver detalle y análisis de riesgo en "Notas y dudas pendientes" más abajo. Sujeto a revisión futura si el equipo lo considera necesario. **A cargo de Estefania — no se descarta ni se posterga, contrario a lo sugerido en la revisión de arquitectura de Sofía.**

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

✅ **Bandera roja resuelta (confirmado por el equipo):** se usa **Laravel de punta a punta**, no PDO plano. El patrón de capas descripto arriba (Controller de Laravel liviano delegando a clases de dominio en PHP puro) sigue siendo válido como buena práctica dentro de Laravel — lo que se descartó fue la alternativa de escribir router y conexión a BD a mano en vez de usar lo que Laravel ya trae resuelto. Ver detalle completo en "Estructura de carpetas del backend" más abajo.

**Autenticación:** sesiones de servidor (no JWT), como apuesta informada. 🚩 A diferencia de Laravel/PHP (que son compatibles porque resuelven cosas distintas y se combinan por capas), JWT y sesiones compiten por resolver lo mismo — "¿cómo sabe el servidor si el usuario sigue logueado?" — con una decisión de fondo mutuamente excluyente: sesiones implican que el servidor consulta una tabla en cada request; JWT puro/stateless implica que el servidor no consulta nada, solo verifica la firma del token. Existe un patrón híbrido real (JWT + tabla de blacklist/whitelist para poder invalidar tokens), pero le saca la ventaja principal a JWT y probablemente no aplique acá. Se elige sesiones porque el endpoint real definido por Grupo 1 (POST /auth/login) devuelve token_sesion y permisos en la respuesta — patrón típico de sesiones, no de JWT. Además coincide con el contexto (usuarios internos, no app pública masiva). No hay apuro por resolver esto — el equipo no lo va a decidir formalmente hasta que se llegue a programar la autenticación. Si se programa con capas separadas (Controllers/Services/Repositories), el cambio a JWT más adelante debería afectar solo la capa de autenticación, no la lógica de negocio.

**Frontend:** [pendiente confirmación formal del equipo — no está definido si es HTML/JS por módulo o frontend único. Recomendación propia: frontend único que consuma las 4 APIs de los grupos, no uno separado por módulo. Razón: el backend ya está pensado como monolito modular con enfoque de microservicios (módulos separados pero funcionando como un solo sistema); un frontend fragmentado por grupo obligaría al usuario final (Diego, Alma, Matías) a saltar entre pantallas sueltas para completar un flujo simple como venta→entrega→cobro, reproduciendo justo la desconexión operativa que el cliente pidió resolver en la entrevista inicial. Cada grupo puede seguir armando y probando sus propias pantallas sueltas durante el desarrollo, pero el objetivo final debería ser un único punto de entrada visual.]

**Otras herramientas/librerías acordadas:**
- **Testing:** PHPUnit — estándar para testear PHP, independiente de si se usa framework o no. Relevante porque el criterio de "Done" del equipo exige código testeado antes de integrar (ver `03-convenciones-y-estado.md`).
- **Documentación/prueba de API (equipo):** Postman o Swagger/OpenAPI, para tener una vista compartida y presentable de los endpoints entre los 4 grupos.
- **Prueba de API (uso personal, Estefania):** REST Client (extensión de VS Code) — peticiones HTTP en archivos `.http`, versionables en Git junto con el código. No reemplaza a Postman como documentación de equipo, pero sirve perfecto para el testeo individual del día a día.
- [resto pendiente — librerías específicas de negocio (ej: generación de PDFs para reportes, manejo de fechas/vencimientos) se definen cuando se resuelva la lógica puntual de cada módulo, no antes]

**Estructura de carpetas del backend:** ✅ Ya no es una decisión temporal — Laravel está instalado y funcionando en el repo (rama `feature/g2-sofia-laravel-scaffold`, basada en `develop-g2`). Se mantuvo el espíritu de "módulo primero, capa después" que el equipo ya había definido, pero ahora dentro del scaffold real de Laravel — no reemplaza la organización modular, la combina con ella.

```
/artisan                     ← CLI de Laravel
/bootstrap
  app.php                     ← arranque de la aplicación
  providers.php                ← registro de Service Providers
  /cache                       ← archivos de caché compilados (no se versionan)

/config                      ← configuración estándar de Laravel (app, database, session, etc.)
  database.php                 ← conexión a MySQL vía variables de entorno (.env), ya NO es un array hardcodeado

/database
  /migrations                  ← acá van a vivir las migraciones de las tablas de cada módulo
  /factories
  /seeders

/public
  index.php                    ← punto de entrada único, ahora es el de Laravel (reemplaza al router manual)

/resources
  /views                       ← vistas Blade (uso mínimo, el backend es principalmente API)

/routes
  web.php
  console.php

/storage                     ← logs, cache de vistas, sesiones (si se usan)

/tests

/src                          ← el código de dominio del equipo, con el autoload psr-4 App\ → src/
  /Entregas
    /Controllers
    /Services
    /Repositories
    /Models

  /Facturacion
    ...
  /Caja
    ...
  /Cobros
    ...
  /ConciliacionBancaria
    ...
  /Rendiciones
    ...

  /Providers
    AppServiceProvider.php     ← única clase de arranque de Laravel que vive en src/, no en app/

  /Shared
    /Database
      Conexion.php             ← 🚩 quedó sin uso tras la migración a Laravel, pendiente de eliminar en un PR aparte
    /Http
      Response.php              ← 🚩 formato de respuesta JSON acordado {data, error, mensaje}; se conserva como referencia, pendiente de que su namespace pase de Shared\Http a App\Shared\Http para que autoload-ee bien
      /Controllers
        Controller.php          ← clase base de la que heredan todos los Controllers del proyecto
    /Auth
      (SesionMiddleware.php eliminado — estaba sin implementar, Laravel resuelve sesión/auth de forma nativa)
```

Qué se eliminó en la migración (ambos sin implementación real, solo un `TODO` sin código): `src/Shared/Http/Router.php` (Laravel trae su propio sistema de rutas) y `src/Shared/Auth/SesionMiddleware.php` (Laravel resuelve sesión de forma nativa).

**Nota sobre el historial de la rama:** al intentar abrir el Pull Request de este cambio desde la rama `sofi-grupo2`, GitHub devolvió error de "historiales no relacionados" — se confirmó con `git merge-base` que esa rama nunca compartió un commit ancestro con `develop-g2`/`main`. El trabajo se rehizo en una rama nueva (`feature/g2-sofia-laravel-scaffold`) partiendo del estado real de `develop-g2`, para que el PR pudiera mergearse sin forzar un merge de historiales no relacionados. `sofi-grupo2` queda sin tocar, pendiente de que el equipo decida qué hacer con ella.

## Notas y dudas pendientes

**Sobre arquitectura y autenticación:**
- ✅ Resuelto: el equipo confirmó Laravel de punta a punta (no PDO plano). Scaffold instalado y funcionando en `feature/g2-sofia-laravel-scaffold` (PR abierto hacia `develop-g2`).
- 🚩 Nuevo pendiente: `src/Shared/Database/Conexion.php` quedó sin uso tras la migración — eliminar en un PR aparte una vez confirmado que ningún otro grupo la referencia.
- 🚩 Nuevo pendiente: `src/Shared/Http/Response.php` conserva el namespace viejo (`Shared\Http` en vez de `App\Shared\Http`) — ajustar antes de usarla desde un Controller de Laravel, o no va a autoload-ear.
- ¿Autenticación vía JWT o sesiones de servidor? A diferencia de Laravel/PHP, esto sí es una decisión de fondo mutuamente excluyente (ver detalle en "Stack técnico" → Autenticación). Se decidió avanzar con sesiones como apuesta informada. Sin apuro — el equipo no lo definiría formalmente hasta llegar a programar la autenticación.
- ✅ Resuelto: estructura de carpetas del backend, ahora sobre el scaffold real de Laravel (ver detalle en "Stack técnico" → Backend).

**Sobre base de datos:**
- No hay tablas modeladas para Facturación, Caja, Cobros, Conciliación Bancaria y Rendiciones — solo Entregas está modelada hasta ahora, a pesar de que sí existen endpoints definidos para esos módulos. Hay que diseñar esas tablas.
- VENTA.numFactura y VENTA.pagado están como campos sueltos en Venta — definir si Factura debería ser una entidad/tabla propia en cambio (tiene sentido que lo sea, dado que Cobros, Rendiciones y Entregas referencian constantemente un factura_id).
- ENTREGA no tiene campo ni relación directa con una dirección de destino. La única forma de llegar a una dirección es la cadena ENTREGA → ENTREGA_PEDIDO → VENTA → CLIENTE.direccion. Confirmar si esto es intencional (se asume dirección única del cliente) o si falta agregar un campo propio en ENTREGA — importante si en algún momento un cliente puede tener más de una dirección posible de entrega.

**Sobre endpoints:**
- No existen endpoints definidos para el módulo de Facturación — confirmar quién lo desarrolla y su diseño. **Aclaración de fusión de documentos:** este módulo sigue en pie, a cargo de Estefania — la revisión de arquitectura de Sofía (enfocada en la migración a Laravel) no incluyó este punto en su versión del documento, pero eso no significa que se haya decidido descartarlo o posponerlo.

**Sobre Facturación — decisión de disparo (nivel negocio e implementación):**
- **Decisión:** Facturación inmediata al confirmar venta. La factura se genera apenas Ventas confirma el pedido, sin esperar a la entrega ni a un cierre de período. Queda totalmente desacoplada del Remito (documento de Entregas).
- **Motivo:** alineado con el pedido explícito de Diego en la entrevista de vender y facturar casi al mismo tiempo, para que la preparación del pedido arranque antes. Pendiente reconfirmar textualmente con el audio de la entrevista.
- **Riesgos aceptados conscientemente (ver análisis de riesgo completo aparte):**
  - Descalce de IVA: el IVA se declara ante ARCA por lo devengado (fecha de factura), no por lo percibido (fecha de cobro). Como la distribuidora fía "de palabra" sin bloqueo por deuda, puede haber meses donde se declara/paga IVA sobre ventas todavía no cobradas. Mitigación propuesta: reporte de "IVA devengado pendiente de cobro" para la contadora.
  - Todo cambio de pedido o error de carga posterior a la venta requiere nota de crédito (no hay ventana de corrección previa a la emisión, a diferencia de un esquema diferido). Mitigación: resolver bien el flujo de nota de crédito desde el MVP, y agregar pantalla de revisión previa a confirmar la venta.
  - Mayor volumen de comprobantes individuales ante ARCA (uno por venta). Mitigación: manejo asíncrono/cola de reintentos en la integración, y que la venta se confirme igual si ARCA falla puntualmente, dejando la factura en estado "pendiente de CAE".
- **Nota de organismo:** a partir de 2024/2025 el ente fiscal es ARCA (Agencia de Recaudación y Control Aduanero), que reemplazó a AFIP — mismo CUIT, Clave Fiscal, CAE y webservices, solo cambió el nombre y el dominio del portal. Usar "ARCA" en toda la documentación de acá en adelante, no "AFIP".
- **Aplicación práctica dentro del scaffold Laravel (ver "Estructura de carpetas del backend" arriba):** este módulo vive en `src/Facturacion/` con sus propias `Controllers/Services/Repositories/Models`, igual que el resto — la migración a Laravel no cambia nada de esta decisión de negocio, solo la forma en que se implementa técnicamente.

**Sobre stack técnico:**
- Confirmar integración de frontend: ¿HTML/JS por módulo, o un frontend único consumiendo todas las APIs?
- Confirmar librerías/herramientas específicas acordadas por el equipo (más allá del lenguaje base).
