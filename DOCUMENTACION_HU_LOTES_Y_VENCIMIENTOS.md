# Documentación de Issue: Gestión de Lotes y Vencimientos — Stock

**Materia:** Prácticas Profesionalizantes 3 (PPS 3) — Instituto Juan XXIII  
**Proyecto:** ERP Distribuidora Pigüé  
**Grupo Asignado:** Grupo 3 (Productos, Stock, Proveedores, Compras, Pedidos de Compra)  
**Módulo:** Gestión de Stock  
**Rama:** `feature/g3-martellini-giraudo`  
**Prioridad:** Importante | **Complejidad:** 5 SP | **Dependencias:** P01 (Producto), S03 (Ingreso de Mercadería)  
**Estado:** Finalizada / Verificada  

---

## 1. Definición de la Historia de Usuario

### Historia de Usuario
> **Como** administrador  
> **quiero** registrar la fecha de vencimiento y el número de lote al recibir mercadería  
> **para** mantener la trazabilidad y controlar la rotación de los productos.

### Descripción
Permite registrar la información de lotes y fechas de vencimiento para cada ingreso de mercadería, asegurando el seguimiento de cada producto desde su recepción hasta su salida.

### Criterios de Aceptación
- [x] **Debe permitir registrar lotes por producto.**
- [x] **Debe registrar fecha de vencimiento.**
- [x] **Debe asociar lotes a ingresos de mercadería.**
- [x] **Debe permitir consultar lotes registrados.**

### Alcance
* **Incluido:**
  * Registro y consulta de lotes y vencimientos de productos.
  * Asociación automática a movimientos de ingreso de mercadería (`MOVIMIENTO_STOCK`).
  * Consultas generales con filtros (por producto, estado, búsqueda por lote, próximos a vencer).
  * Historial cronológico consultable por producto específico.
  * Cálculo dinámico de días para vencer y alertas de semáforo (vigente, próximo, urgente, vencido).
  * Integración en el prototipo frontend (`stock.html`) con modal de gestión e ingreso de partidas.
* **Excluido:**
  * No incluye generación de reportes avanzados ni eliminación física de lotes (integridad referencial).

---

## 2. Modelo de Base de Datos

En estricta conformidad con el esquema unificado `BDDistribuidoraFinal.sql`:

```sql
CREATE TABLE `LOTE` (
  `id_lote` integer PRIMARY KEY AUTO_INCREMENT,
  `id_producto` integer NOT NULL,
  `id_movimiento` integer NULL,
  `id_unidad` integer NULL,
  `nro_lote` string NOT NULL,
  `cantidad` integer NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `estado` string COMMENT 'vigente | vencido | consumido'
);

ALTER TABLE `LOTE` ADD FOREIGN KEY (`id_producto`) REFERENCES `PRODUCTO` (`id_producto`);
ALTER TABLE `LOTE` ADD FOREIGN KEY (`id_movimiento`) REFERENCES `MOVIMIENTO_STOCK` (`id_movimiento`);
ALTER TABLE `LOTE` ADD FOREIGN KEY (`id_unidad`) REFERENCES `UNIDAD_MEDIDA` (`id_unidad`);
```

---

## 3. Componentes Backend Desarrollados

### 3.1. Modelo Eloquent: `app/Modules/Stock/Models/Lote.php`
* **Tabla:** `LOTE` (clave primaria `id_lote`, timestamps inactivos).
* **Atributos asignables:** `id_producto`, `id_movimiento`, `id_unidad`, `nro_lote`, `cantidad`, `fecha_vencimiento`, `estado`.
* **Relaciones:**
  * `producto()`: `BelongsTo` con `Producto`.
  * `movimiento()`: `BelongsTo` con `MovimientoStock`.
  * `unidad()`: `BelongsTo` con `UnidadMedida`.
* **Cálculo dinámico:**
  * `dias_para_vencer`: Devuelve la diferencia de días entre la fecha actual y la de vencimiento (positivo: vigente; negativo: vencido).
* **Scopes de consulta:**
  * `scopePorProducto($idProducto)`: Filtra lotes del producto indicado.
  * `scopeVigentes()`: Filtra lotes con estado vigente y fecha de vencimiento no expirada.
  * `scopePorVencer($dias = 30)`: Filtra lotes vigentes que vencen dentro de los próximos N días.
  * `scopeSearch($term)`: Búsqueda flexible por número de lote o código/descripción del producto.

### 3.2. Controlador: `app/Modules/Stock/Controllers/LoteController.php`
* **`index(Request $request)`:** Listado con filtros opcionales (`id_producto`, `estado`, `search`, `por_vencer`, `dias`).
* **`store(Request $request)`:** Registro de lote validando producto activo, obligatoriedad de número de lote y fecha de vencimiento válida. Si `asociar_ingreso` es `true` (default), invoca a `StockService::registrarMovimiento` tipo `'ingreso'`, incrementa el inventario y asocia el `id_movimiento` creado al lote.
* **`show(int $id)`:** Detalle individual de lote con relaciones cargadas.
* **`lotesPorProducto(int $idProducto)`:** Historial de lotes ordenados por fecha de vencimiento para un producto determinado.
* **`porVencer(Request $request)`:** Endpoint para alimentar tableros de alertas de vencimientos próximos.

### 3.3. Modificaciones en Servicios y Controladores Existentes
* **`app/Modules/Stock/Services/StockService.php`:**
  * `registrarMovimiento`: Ahora devuelve la instancia `MovimientoStock` persistida para permitir la asociación de claves foráneas.
* **`app/Modules/Stock/Controllers/IngresoMercaderiaController.php`:**
  * Se agregaron campos opcionales `nro_lote` y `fecha_vencimiento` por ítem recibido en `POST /api/stock/ingresos`.
  * Al ingresar mercadería, si se suministran dichos datos, se crea automáticamente el lote correspondiente asociado al movimiento.
* **`app/Modules/Productos/Models/Producto.php`:**
  * Se agregó la relación `lotes(): HasMany` vinculada a `Lote::class`.
* **`app/Modules/Stock/Models/MovimientoStock.php`:**
  * Se agregó la relación `lotes(): HasMany` vinculada a `Lote::class`.

---

## 4. Contrato de la API REST

### 4.1. Listar Lotes
* **Endpoint:** `GET /api/stock/lotes`
* **Query Params (opcionales):**
  * `id_producto`: integer (ej: `1`)
  * `estado`: string (`vigente`, `vencido`, `consumido`, `todos`)
  * `search`: string (búsqueda por número de lote o nombre de producto)
  * `por_vencer`: boolean (`true` / `false`)
  * `dias`: integer (default: `30`)
* **Respuesta Exitosa (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "id_lote": 1,
      "nro_lote": "LOT-2026-A1",
      "id_producto": 1,
      "codigo_producto": "HAR-001",
      "descripcion_producto": "Harina 000 1kg",
      "cantidad": 50,
      "id_unidad": null,
      "nombre_unidad": null,
      "fecha_vencimiento": "2026-10-15",
      "dias_para_vencer": 31,
      "estado": "vigente",
      "urgente": false,
      "id_movimiento": 12,
      "fecha_ingreso": "2026-09-14"
    }
  ]
}
```

### 4.2. Registrar Lote Directo
* **Endpoint:** `POST /api/stock/lotes`
* **Body (JSON):**
```json
{
  "id_producto": 1,
  "nro_lote": "LOT-2026-B2",
  "fecha_vencimiento": "2026-11-30",
  "cantidad": 20,
  "id_unidad": null,
  "asociar_ingreso": true,
  "motivo": "Ingreso mercadería lote LOT-2026-B2"
}
```
* **Respuesta Exitosa (201 Created):**
```json
{
  "status": "success",
  "message": "Lote registrado exitosamente.",
  "data": {
    "lote": {
      "id_lote": 2,
      "nro_lote": "LOT-2026-B2",
      "id_producto": 1,
      "codigo_producto": "HAR-001",
      "descripcion_producto": "Harina 000 1kg",
      "cantidad": 20,
      "fecha_vencimiento": "2026-11-30",
      "dias_para_vencer": 77,
      "estado": "vigente",
      "urgente": false,
      "id_movimiento": 13,
      "fecha_ingreso": "2026-09-14"
    },
    "stock_disponible": 70,
    "estado_alerta": "normal"
  }
}
```

### 4.3. Ingreso de Mercadería con Lote (S03 Integrado)
* **Endpoint:** `POST /api/stock/ingresos`
* **Body (JSON):**
```json
{
  "id_proveedor": 1,
  "fecha": "2026-09-14",
  "items": [
    {
      "id_producto": 1,
      "cantidad": 30,
      "nro_lote": "LOT-PROV-99",
      "fecha_vencimiento": "2026-12-31",
      "motivo": "Recepción remito proveedor"
    }
  ]
}
```

### 4.4. Historial de Lotes por Producto
* **Endpoint:** `GET /api/stock/productos/{id}/lotes`
* **Respuesta Exitosa (200 OK):** Devuelve el listado completo de lotes ingresados para el producto indicado con el total y existencias actuales.

### 4.5. Lotes Próximos a Vencer
* **Endpoint:** `GET /api/stock/lotes-por-vencer?dias=30`
* **Respuesta Exitosa (200 OK):** Lista de lotes con caducidad inminente (utilizado por el dashboard de `index.html`).

---

## 5. Implementación en la Interfaz de Usuario (Frontend)

Archivo: `public/Interfaz/stock.html`

1. **Botón Principal:** Incorporación del botón **"📦 Lotes y Vencimientos"** en la botonera superior para abrir la consola de gestión de lotes.
2. **Acción en Tabla de Stock:** Cada fila de producto incluye el botón **"📦 Lotes"** que abre el modal filtrando de forma inmediata las partidas de ese producto en particular.
3. **Modal de Gestión (`#modalLotes`):**
   * Panel desplegable para registrar nuevos lotes con actualización directa de stock.
   * Filtros combinados por producto y estado (Todos, Vigentes, Próximos a vencer, Vencidos).
   * Semáforo de alerta en tabla:
     * **Rojo (`tag crit`):** Lotes vencidos o urgentes con vencimiento ≤ 15 días.
     * **Amarillo (`tag warn`):** Lotes con vencimiento próximo entre 16 y 30 días.
     * **Verde (`tag active`):** Lotes vigentes (> 30 días).
4. **Modal de Ingreso de Mercadería:** Inclusión de campos `N° Lote` y `Vence` en la grilla dinámica de recepción de artículos.

---

## 6. Verificación y Pruebas Realizadas

1. **Integridad Transaccional:** Al dar de alta un lote con `asociar_ingreso = true`, se crean de manera atómica el movimiento en `MOVIMIENTO_STOCK`, el lote en `LOTE` y se actualiza `PRODUCTO.stock`.
2. **Validaciones de Reglas de Negocio:**
   * Rechazo ante producto inexistente o en estado inactivo.
   * Rechazo ante cantidad menor o igual a cero.
   * Rechazo ante formato de fecha inválido.
   * Asignación automática del estado `'vencido'` si la fecha ingresada es anterior a la fecha actual.
3. **Persistencia Local:** Todos los cambios se encuentran integrados localmente en la rama `feature/g3-martellini-giraudo`, sin realizar push al repositorio remoto según las directivas establecidas.
4. **Correcciones Frontend y Entorno Local:** Se solucionó un problema de anidamiento HTML en el modal de lotes y se añadieron mensajes de error amigables ("base de datos no configurada") cuando se testea con SQLite vacío. Se configuró PHP 8.4 y Composer localmente para ejecutar pruebas mediante `php artisan serve`.
