# Informe de Testeo · Historia de Usuario C01: Registrar Compras de Mercadería

> **Proyecto:** ERP Distribuidora Pigüé (Grupo 3)  
> **Módulo:** Gestión de Compras  
> **Prioridad:** MVP | **Complejidad:** 8 SP  
> **Fecha de Ejecución:** 18 de Octubre de 2026  
> **Estado:** **APROBADO (100% de criterios y pruebas superadas)**  

---

## 1. Especificación de la Historia de Usuario

### 1.1 Enunciado
> **Como** administrador  
> **quiero** registrar compras de mercadería  
> **para** gestionar el abastecimiento de productos.

### 1.2 Descripción
Permite registrar compras de mercadería realizadas a proveedores para abastecer el inventario de la empresa. Esta funcionalidad centraliza la información de las adquisiciones, incluyendo productos, cantidades y precios, generando un registro formal que podrá ser utilizado posteriormente para la recepción de mercadería, control de pagos y análisis de costos.

### 1.3 Criterios de Aceptación
1. **Debe permitir seleccionar un proveedor:** Validación de existencia y estado activo del proveedor.
2. **Debe permitir agregar uno o varios productos a la compra:** Soporte de ítems múltiples con validación de estado activo en catálogo.
3. **Debe permitir registrar cantidades y precios por producto:** Cantidad entera mayor a cero y precio unitario decimal mayor o igual a cero.
4. **Debe calcular automáticamente el importe total de la compra:** Subtotales por renglón (`cantidad * precio_unitario`) y total general consolidado.
5. **Debe registrar la fecha de la compra:** Fecha válida del sistema o ingresada por el usuario (no posterior al día de la fecha).
6. **Debe generar un identificador único para la compra:** Formato estándar formal correlativo `NCC-AAAA-XXXXXX` y clave primaria relacional `id_compra`.

### 1.4 Dependencias
* **PV01** - Registrar Proveedores (proveedor emisor debe existir y estar activo).
* **PV05** - Asociar Productos a Proveedores (vinculación automática en `PRODUCTO_PROVEEDOR` con precio acordado al registrar compra).

### 1.5 Alcance y Exclusiones (Fuera de Alcance)
* **Dentro de alcance:**
  * Registro formal de la compra asociada al proveedor.
  * Inclusión de 1 a $N$ productos.
  * Cantidades y precios unitarios por producto.
  * Cálculo de subtotales e importe total.
  * Asignación de identificador único.
  * Registro de fecha de creación y vencimiento estimada.
  * Establecimiento del estado inicial como **`"pendiente"`**.
  * Respuestas estructuradas de confirmación o mensajes de error detallados.
* **Fuera de alcance (respetado estrictamente):**
  * ❌ No incluye recepción de mercadería.
  * ❌ No incluye actualización de stock ni movimientos en Kardex (`MOVIMIENTO_STOCK`).
  * ❌ No incluye registro de pagos (`PAGO_COMPRA` o `PAGO_PROVEEDOR`).
  * ❌ No incluye cancelación o modificación posterior de la compra.

---

## 2. Entradas y Salidas Verificadas

### 2.1 Entradas
| Parámetro | Tipo | Requerido | Validación |
| :--- | :---: | :---: | :--- |
| `id_proveedor` | Integer | Sí | Debe existir en `PROVEEDOR` y tener `estado = 'activo'`. |
| `numero_comprobante` | String | No | Máx. 50 caracteres (ej. `FC-A-0001-00001234`). |
| `fecha_compra` | Date | No | Formato `YYYY-MM-DD`. Debe ser `<= today`. Por defecto hoy. |
| `fecha_vencimiento` | Date | No | Formato `YYYY-MM-DD`. Por defecto hoy + 30 días. |
| `items` | Array | Sí | Mínimo 1 elemento. |
| `items[].id_producto` | Integer | Sí | Debe existir en `PRODUCTO` y tener `estado = 'activo'`. |
| `items[].cantidad` | Integer | Sí | Entero $> 0$. |
| `items[].precio_unitario`| Decimal | Sí | Flotante $\ge 0.00$. |

### 2.2 Salidas
* **Código HTTP 201 Created** al registrar exitosamente.
* Objeto JSON con:
  * `status`: `"success"`
  * `message`: `"Compra registrada exitosamente."`
  * `data.id_compra`: Identificador relacional.
  * `data.numero_compra`: Identificador único formal (ej. `NCC-2026-089197`).
  * `data.importe_total`: Total calculado con 2 decimales.
  * `data.saldo_pendiente`: Igual al importe total (sin pagos iniciales).
  * `data.estado`: `"pendiente"`.
  * `data.total_productos`: Cantidad acumulada de unidades.

---

## 3. Adecuación de Base de Datos Realizada

Durante la fase de auditoría previa al testeo, se detectó que el esquema legacy de MySQL tenía tipos `DECIMAL(10,0)` que truncaban centavos. Se implementó y ejecutó la migración:

* **Archivo:** [`backend/database/migrations/2026_10_18_000001_fix_monetary_decimal_precision.php`](file:///c:/Users/franc/Desktop/rama_Tarnosky-Ortelli/ERP-Juan23/backend/database/migrations/2026_10_18_000001_fix_monetary_decimal_precision.php)
* **Tablas y Columnas Actualizadas a `DECIMAL(12,2)`:**
  * `COMPRA.importe_total`
  * `COMPRA.saldo_pendiente`
  * `DETALLE_COMPRA.precio_unitario`
  * `DETALLE_COMPRA.subtotal`
  * `PRODUCTO_PROVEEDOR.precio_acordado`
  * `PRODUCTO.precio_unitario` (agregada y sincronizada)
  * `HISTORIAL_PRECIO.precio`

---

## 4. Batería de Pruebas Automatizadas (PHPUnit)

Se diseñó la clase de prueba unitaria y de integración [`backend/tests/Feature/RegistrarCompraC01Test.php`](file:///c:/Users/franc/Desktop/rama_Tarnosky-Ortelli/ERP-Juan23/backend/tests/Feature/RegistrarCompraC01Test.php).

### 4.1 Casos de Prueba Implementados

```
✓ test_criterio_registrar_compra_con_multiples_productos_exitosamente
✓ test_dependencia_pv05_asocia_producto_al_proveedor_al_comprar
✓ test_alcance_no_modifica_stock_ni_genera_pagos
✓ test_validaciones_de_entradas_y_mensajes_de_error
✓ test_interfaz_blade_elementos_c01_presentes
```

### 4.2 Resultado de la Ejecución

```bash
php artisan test tests/Feature/RegistrarCompraC01Test.php
```

```text
   PASS  Tests\Feature\RegistrarCompraC01Test
  ✓ criterio registrar compra con multiples productos exitosamente               0.60s  
  ✓ dependencia pv05 asocia producto al proveedor al comprar                     0.07s  
  ✓ alcance no modifica stock ni genera pagos                                    0.17s  
  ✓ validaciones de entradas y mensajes de error                                 0.07s  
  ✓ interfaz blade elementos c01 presentes                                       0.07s  

  Tests:    5 passed (60 assertions)
  Duration: 1.36s
```

---

## 5. Prueba de Integración en Tiempo Real (Live API)

Con el servidor Laravel en ejecución en `http://127.0.0.1:8000`, se ejecutó una llamada HTTP real:

### 5.1 Petición Enviada (POST `/api/compras`)
```json
{
  "id_proveedor": 3,
  "numero_comprobante": "FAC-TEST-LIVE-001",
  "fecha_compra": "2026-10-18",
  "items": [
    {
      "id_producto": 1,
      "cantidad": 10,
      "precio_unitario": 1500.00
    },
    {
      "id_producto": 14,
      "cantidad": 5,
      "precio_unitario": 800.00
    }
  ]
}
```

### 5.2 Respuesta Obtenida (HTTP 201 Created)
```json
{
  "status": "success",
  "message": "Compra registrada exitosamente.",
  "data": {
    "id_compra": 39,
    "numero_compra": "NCC-2026-089197",
    "numero_comprobante": "FAC-TEST-LIVE-001",
    "id_proveedor": 3,
    "proveedor": "distribuidora pepito",
    "importe_total": 19000,
    "saldo_pendiente": 19000,
    "estado": "pendiente",
    "fecha_compra": "2026-10-18",
    "fecha_vencimiento": "2026-11-17",
    "fecha_cancelacion": null,
    "total_productos": "15"
  }
}
```

### 5.3 Consulta de Detalle Posterior (GET `/api/compras/39`)
Verificó que:
* `total_comprado`: 15
* `total_recibido`: 0 (confirmando que no se recibió mercadería)
* `total_pendiente`: 15
* `pagos.total_pagado`: 0 (confirmando que no se registraron pagos)
* `saldo_a_pagar`: 19000
* Renglones de detalle asociados correctamente a los productos 1 y 14 con sus respectivos subtotales.

---

## 6. Verificación en Interfaz de Usuario (Frontend Blade)

* **Ruta de acceso:** `http://127.0.0.1:8000/compras`
* **Componente:** Vista [`backend/resources/views/compras/index.blade.php`](file:///c:/Users/franc/Desktop/rama_Tarnosky-Ortelli/ERP-Juan23/backend/resources/views/compras/index.blade.php)
* **Elementos interactivos comprobados:**
  1. Botón **"Nueva Compra (C01)"** (`abrirModalNuevaCompra()`).
  2. Modal de captura `#modalNuevaCompra`.
  3. Selector de proveedor activo `#compraProveedor`.
  4. Selector de productos con buscador, campo de cantidad y precio unitario con autocalculado de subtotales.
  5. Grilla dinámica `#detalleCompraItems` con opción de eliminar ítems antes de guardar.
  6. Indicador de total en tiempo real `#compraTotalDisplay`.
  7. Envío asíncrono con token CSRF y refresco automático de las tablas de compras, órdenes y deudas.

---

## 7. Dictamen Final

La funcionalidad **C01: Registrar compras de mercadería** cumple de forma íntegra con todos los requisitos funcionales, criterios de aceptación, reglas de negocio y restricciones de alcance estipulados en la especificación.

