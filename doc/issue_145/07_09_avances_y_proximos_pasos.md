# Documentación Oficial — Issue #145: Módulo de Cobros (Grupo 2)

> **Repositorio:** `rapiriz/ERP-Juan23`  
> **Rama de trabajo:** `feature/g2-tomas-guanes`  
> **Responsable:** Tomás Guanes  
> **Fecha de corte:** 07 de Septiembre de 2026  
> **Estado general:** MVP Backend 100% Funcional e Integrado en Laravel 11  

---

## 1. Resumen Ejecutivo y Alcance de la Issue #145

La **Issue #145 (`[G2] - COBROS`)** constituye la épica contenedora en GitHub para el desarrollo integral del **Módulo de Cobros** del ERP, asignado al **Grupo 2** (junto con Entregas, Rendiciones, Caja, Facturación y Conciliación Bancaria).

El objetivo primordial del módulo es gestionar el ingreso de fondos por cancelación total o parcial de deudas de clientes, garantizando:
1. La **trazabilidad contable** del pago frente a las ventas emitidas.
2. La **actualización automática del saldo** de cuenta corriente.
3. La **emisión del comprobante** de cobro (recibo).
4. La **afectación directa en Caja** física cuando el cobro se realiza en efectivo.
5. El soporte de medios de pago alternativos (transferencias bancarias y cheques) para su posterior conciliación bancaria.

---

## 2. Matriz de Estado de las Sub-Issues de #145

A continuación se detalla el estado actual de cada sub-issue oficial vinculada a la Issue #145 en el backlog del repositorio:

| Sub-Issue | Título Oficial / Historia de Usuario | Prioridad / Milestone | Estado Actual | Entregables Clave |
| :--- | :--- | :--- | :--- | :--- |
| **#1** | **[G2] - Registrar cobro a cliente - COBROS** (`[G2]-COB-01`) | MVP / Sprint 1 | ✅ **Finalizado (Backend)** | Controller, Service, Repository, Migraciones, Validaciones, FIFO y Tests |
| **#2** | **[G2] - Consultar cuenta corriente de un cliente - COBROS** (`[G2]-COB-02`) | MVP / Sprint 1 | 🟡 **Implementado Parcial** | Endpoint y cálculo de saldo pendiente en tiempo real activo; pendiente vista de histórico detallado |
| **#3** | **[G2] - Cobro parcial de deuda - COBROS** | Importante | ✅ **Finalizado (Lógica)** | Algoritmo de imputación progresiva en Service (amortiza facturas y deja saldo remanente) |
| **#4** | **[G2] - Anular un cobro registrado - COBROS** (`[G2]-COB-03`) | Importante / Sprint 4 | 🟠 **Estructura Lista** | Modelo relacional y campos de auditoría (`motivo_anulacion`, estados) listos para la lógica de reversión |
| **#5** | **[G2] - Listar cobros pendientes del día - COBROS** (`[G2]-COB-04`) | Importante / Sprint 3 | ⚪ **Planificado** | Depende del acoplamiento con la hoja de ruta de repartidores (Módulo Entregas) |

---

## 3. Arquitectura Técnica Implementada

Siguiendo el estándar de arquitectura modular y por capas establecido por el Grupo 2 tras la adopción de Laravel 11 (`App\{Módulo}\{Capa}`):

```
ERP-Juan23/
├── database/migrations/
│   ├── 2026_09_03_233537_create_cobro_table.php       # Tabla COBRO
│   └── 2026_09_07_233538_create_cobro_venta_table.php # Tabla intermedia COBRO_VENTA
├── routes/
│   └── api.php                                         # Endpoints v1 del módulo
├── src/Cobros/
│   ├── Controllers/
│   │   └── CobroController.php                         # Control HTTP, validación y formateo
│   ├── Models/
│   │   ├── Cobro.php                                   # Entidad Eloquent con constantes de dominio
│   │   └── CobroVenta.php                              # Entidad de relación N:N cobro-venta
│   ├── Repositories/
│   │   └── CobroRepository.php                         # Abstracción y persistencia de datos
│   └── Services/
│       └── CobroService.php                            # Reglas de negocio y transaccionalidad
├── tests/Unit/
│   └── CobroServiceTest.php                            # Pruebas unitarias automatizadas
└── cobros.http                                         # Suite de pruebas de integración HTTP
```

### Flujo de Ejecución (Patrón de Capas):
1. **HTTP Request** ingresa por `routes/api.php` bajo el prefijo `/api/v1/cobros`.
2. **`CobroController`**: Valida los tipos de datos primarios de la solicitud y delega la ejecución al servicio.
3. **`CobroService`**:
   - Valida reglas de dominio (cliente existente y activo, monto estrictamente positivo, medio de pago admitido).
   - Genera número de comprobante único si no fue suministrado (`REC-YYYYMMDD-XXXX`).
   - Envuelve la persistencia en una **transacción atómica (`DB::transaction`)**:
     - Registra la cabecera del cobro en `COBRO`.
     - Obtiene las ventas pendientes del cliente y las cancela/amortiza por orden de antigüedad (**Estrategia FIFO**), registrando la imputación en `COBRO_VENTA`.
     - Si el medio de pago es `efectivo`, impacta automáticamente un movimiento de ingreso en `CAJA_MOVIMIENTO`.
4. **`CobroRepository`**: Centraliza todas las sentencias SQL y operaciones Eloquent/Query Builder hacia las tablas `COBRO`, `COBRO_VENTA`, `VENTA`, `CLIENTE` y `CAJA_MOVIMIENTO`.
5. **Response unificado**: Retorna la estructura JSON estandarizada del equipo mediante `App\Shared\Http\Response`.

---

## 4. Esquema Relacional de Base de Datos

### A. Tabla `COBRO`
Almacena el evento del cobro percibido:
- `id_cobro` (INT, PK, Auto-incremental)
- `id_cliente` (INT, FK a `CLIENTE`)
- `id_usuario` (INT, FK a `USUARIO` / Responsable de la carga)
- `fecha` (DATETIME)
- `monto_total` (DECIMAL(12, 2))
- `medio_pago` (ENUM: `'efectivo'`, `'transferencia'`, `'cheque'`)
- `comprobante_nro` (VARCHAR(100), opcional o autogenerado)
- `estado` (VARCHAR(20), default: `'registrado'`)
- `observaciones` (TEXT, nullable)
- *Campos de auditoría para anulación (Sub-issue #4)*: `motivo_anulacion`, `fecha_anulacion`, `id_usuario_anulacion`.

### B. Tabla Intermedia `COBRO_VENTA`
Garantiza trazabilidad cuando un cobro salda una o más ventas, o cuando un pago parcial amortiza una fracción de la venta:
- `id_cobro` (INT, FK a `COBRO`)
- `id_venta` (INT, FK a `VENTA`)
- `monto_aplicado` (DECIMAL(12, 2))
- *Clave primaria compuesta:* `(id_cobro, id_venta)`

### C. Integraciones entre Tablas de Otros Módulos:
- **Caja (`CAJA_MOVIMIENTO`):** Todo cobro en efectivo genera un registro con `tipo = 'ingreso'`, asociando concepto, monto y usuario.
- **Ventas (`VENTA` - Grupo 4):** Se consultan ventas pendientes (`pagado = false`) y se marcan como saldadas (`pagado = true`) una vez cubiertas.
- **Clientes (`CLIENTE` - Grupo 1):** Se valida el estado del cliente (`estado = 'activo'`).

---

## 5. Especificación de Endpoints REST (API v1)

### 1. Registrar Cobro a Cliente
- **Método:** `POST`
- **Ruta:** `/api/v1/cobros`
- **Headers:** `Content-Type: application/json`, `Accept: application/json`

**Cuerpo de la petición (JSON):**
```json
{
  "id_cliente": 1,
  "id_usuario": 1,
  "monto_total": 5000.00,
  "medio_pago": "efectivo",
  "comprobante_nro": "REC-20260907-001",
  "observaciones": "Cobro de factura en efectivo"
}
```

**Respuesta Exitosa (`201 Created`):**
```json
{
  "data": {
    "id_cobro": 14,
    "id_cliente": 1,
    "id_usuario": 1,
    "fecha": "2026-09-07T16:30:00.000000Z",
    "monto_total": 5000,
    "medio_pago": "efectivo",
    "comprobante_nro": "REC-20260907-001",
    "estado": "registrado",
    "observaciones": "Cobro de factura en efectivo",
    "ventas_asociadas": [
      {
        "id_venta": 101,
        "monto_aplicado": 5000
      }
    ],
    "movimiento_caja": 52
  },
  "error": null,
  "mensaje": "Operación exitosa"
}
```

**Respuestas de Error:**
- `422 Unprocessable Content`: Validación fallida (monto `<= 0`, medio de pago no soportado, cliente inexistente o inactivo).
- `500 Internal Server Error`: Fallo interno o de conexión con reversión automática de la transacción.

---

### 2. Consultar Saldo Pendiente del Cliente
- **Método:** `GET`
- **Ruta:** `/api/v1/cobros/clientes/{idCliente}/saldo`
- **Headers:** `Accept: application/json`

**Respuesta Exitosa (`200 OK`):**
```json
{
  "data": {
    "id_cliente": 1,
    "saldo_pendiente": 17000.00
  },
  "error": null,
  "mensaje": "Operación exitosa"
}
```

---

## 6. Verificación y Testing Realizados

1. **Pruebas Unitarias Automatizadas (`tests/Unit/CobroServiceTest.php`):**
   - Validación de excepción ante montos menores o iguales a cero.
   - Validación de cliente inactivo o inexistente mediante Mock de Repository.
   - Rechazo de medios de pago no tipificados (e.g. criptomonedas, métodos inválidos).

2. **Suite de Pruebas de Integración (`cobros.http`):**
   - Escenario 1: Consulta de saldo previo.
   - Escenario 2: Cobro exitoso en efectivo con alta de movimiento en Caja y amortización de deuda.
   - Escenario 3: Cobro con transferencia bancaria (sin impacto en caja física).
   - Escenarios 4, 5 y 6: Casos de validación de negocio (monto cero, cliente 99999, medio inválido).
   - Escenario 7: Cobro documentado con cheque bancario.
   - Escenario 8: Verificación de reducción del saldo en tiempo real.
   - Escenario 9: Pago que extingue el 100% de la deuda pendiente del cliente.

---

## 7. Próximos Pasos y Coordinación con el Grupo

1. **Interfaz Gráfica / Prototipo:**
   - Construir el panel visual interactivo de Cobros (`public/cobros.html` o vista Blade) análogo al desarrollado en Entregas y Rendiciones para demostraciones en clase.
2. **Sub-Issue #4 (Anulación de Cobro):**
   - Desarrollar el endpoint `POST /api/v1/cobros/{id}/anular` que implemente la reversión de caja y reapertura de la venta/factura.
3. **Sub-Issue #5 (Cobros del Repartidor en Hoja de Ruta):**
   - Integrar con el módulo de Entregas (Estefanía) para listar cobros pendientes asignados a la ruta del día.
4. **Integración con Conciliación Bancaria (Sofía):**
   - Asegurar que los cobros registrados con `medio_pago = 'transferencia'` o `'cheque'` sean consumibles por el motor de conciliación automática.
