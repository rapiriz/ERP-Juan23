# ERP Juan 23 - Módulo de Rendiciones y Cobros (Grupo 2)

Bienvenido a la documentación oficial del **Módulo de Rendiciones de Reparto** del sistema ERP Juan 23. Este módulo se encarga de gestionar y controlar todo el dinero, los cobros y los productos devueltos por los repartidores al finalizar sus entregas del día.

---

## 🎯 Objetivos del Módulo

Cuando un repartidor sale a entregar pedidos, lleva mercadería y cobra a los clientes en efectivo o transferencia. Al volver a la empresa, debe **rendir** lo que entregó y el dinero que cobró. 

Este módulo permite:
1. **Registrar lo que rinde el repartidor** (remitos entregados y dinero cobrado).
2. **Registrar cobros individuales** realizados a facturas específicas.
3. **Detectar diferencias** (si falta o sobra dinero en caja respecto a lo esperado).
4. **Registrar devoluciones de productos** (si un cliente no estaba o rechazó el pedido).
5. **Aprobar o Rechazar la rendición** por parte de un supervisor o cajero.
6. **Consultar el historial** de todas las rendiciones realizadas con filtros.

---

## 📋 Historias de Usuario Desarrolladas (Issues Oficiales)

A continuación se explica de forma sencilla qué hace cada funcionalidad implementada:

### 1. Registrar Rendición de Reparto completo (`Issue #14`)
- **¿Qué hace?**: Es el formulario o proceso principal cuando el repartidor vuelve de su ruta. Indica qué reparto hizo, qué repartidor fue, observaciones, la lista de remitos entregados y los cobros acumulados.
- **Endpoint API**: `POST /api/v1/rendiciones`
- **Servicio**: [`src/Rendiciones/Services/ProcesadorDeRendicionReparto.php`](file:///c:/ProyectoPPS/src/Rendiciones/Services/ProcesadorDeRendicionReparto.php)

---

### 2. Registrar Cobro Individual dentro de Rendición (`Issue #13`)
- **¿Qué hace?**: Permite asentar el pago de una factura en particular indicando el cliente, el número de factura, el monto abonado y el medio de pago (Efectivo o Transferencia).
- **Endpoint API**: `POST /api/v1/rendiciones/cobros`
- **Servicio**: [`src/Rendiciones/Services/ProcesadorDeCobroRendicion.php`](file:///c:/ProyectoPPS/src/Rendiciones/Services/ProcesadorDeCobroRendicion.php)

---

### 3. Registrar Diferencias en Rendición (`Issue #12`)
- **¿Qué hace?**: Si el dinero en efectivo que entrega el repartidor no coincide exactamente con el total esperado, se calcula la diferencia (faltante o sobrante) y se guarda el motivo y observaciones para auditoría.
- **Endpoint API**: `POST /api/v1/rendiciones/diferencias`
- **Servicio**: [`src/Rendiciones/Services/ProcesadorDeDiferenciaRendicion.php`](file:///c:/ProyectoPPS/src/Rendiciones/Services/ProcesadorDeDiferenciaRendicion.php)

---

### 4. Aprobar o Rechazar Rendición (`Issue #11`)
- **¿Qué hace?**: El encargado o supervisor revisa la rendición presentada. Puede marcarla como **Aprobada** (cerrando el proceso) o **Rechazada** (ingresando un motivo obligatorio, por ejemplo: "faltan comprobantes físicos").
- **Endpoint API**: `POST /api/v1/rendiciones/validar`
- **Servicio**: [`src/Rendiciones/Services/ProcesadorDeValidacionRendicion.php`](file:///c:/ProyectoPPS/src/Rendiciones/Services/ProcesadorDeValidacionRendicion.php)

---

### 5. Consultar Historial de Rendiciones (`Issue #10`)
- **¿Qué hace?**: Permite consultar la lista de todas las rendiciones registradas en el sistema. Soporta filtros por estado (*Aprobada, Pendiente, Rechazada*) y por ID de repartidor.
- **Endpoint API**: `GET /api/v1/rendiciones/historial`
- **Servicio**: [`src/Rendiciones/Services/ProcesadorDeHistorialRendicion.php`](file:///c:/ProyectoPPS/src/Rendiciones/Services/ProcesadorDeHistorialRendicion.php)

---

### 6. Registrar Devoluciones en Rendición (`Issue #8`)
- **¿Qué hace?**: Si un pedido no pudo entregarse (ejemplo: el negocio estaba cerrado), se registra como devolución asociada a la rendición indicando el pedido, cliente y el motivo.
- **Endpoint API**: `POST /api/v1/rendiciones/devoluciones`
- **Servicio**: [`src/Rendiciones/Services/ProcesadorDeDevolucionRendicion.php`](file:///c:/ProyectoPPS/src/Rendiciones/Services/ProcesadorDeDevolucionRendicion.php)

---

## 📁 Estructura del Código

```text
ProyectoPPS/
├── config/                  # Configuraciones del sistema
├── context/                 # Notas de contexto local (ignorado por Git)
├── public/                  # Punto de entrada público del servidor web
│   ├── index.php            # Enrutador principal de la API REST
│   └── rendiciones.php      # Panel visual interactivo de demostración
├── src/                     # Código fuente del sistema
│   └── Rendiciones/         # Módulo de Rendiciones
│       ├── Controllers/     # Controladores (si aplica)
│       ├── Models/          # Modelos de datos
│       ├── Repositories/    # Acceso a datos / Persistencia
│       └── Services/        # Lógica de negocio (Procesadores)
├── peticiones.http          # Suite de pruebas HTTP para VS Code / Postman
├── .gitignore               # Archivos ignorados por Git
└── README.md                # Esta documentación
```

---

## 🚀 Cómo Ejecutar y Probar el Proyecto

### 1. Iniciar el servidor local
Abre una terminal en la carpeta raíz del proyecto y ejecuta:

```bash
php -S localhost:8000 -t public
```

### 2. Formas de probar el sistema:

#### A) Desde el Panel Web Interactivo (Interfaz Gráfica)
Abre tu navegador e ingresa a:
👉 `http://localhost:8000/rendiciones.php`

Allí verás tarjetas interactivas para probar los 6 flujos en tiempo real con respuestas claras en pantalla.

#### B) Mediante el archivo `peticiones.http` (API Client)
Puedes abrir el archivo [`peticiones.http`](file:///c:/ProyectoPPS/peticiones.http) en tu editor (con extensiones como REST Client o Postman) y ejecutar directamente las peticiones HTTP preconfiguradas.

---

## 🛠️ Ejemplos de uso de la API REST

### Ejemplo 1: Registrar una Rendición de Reparto (POST)
**URL**: `http://localhost:8000/api/v1/rendiciones`  
**Body (JSON)**:
```json
{
  "id_reparto": 45,
  "id_repartidor": 12,
  "observaciones": "Entrega completada sin inconvenientes.",
  "remitos": [101, 102, 103],
  "cobros": [
    { "id_factura": 201, "monto": 15000.00, "medio_pago": "efectivo" },
    { "id_factura": 202, "monto": 8500.50, "medio_pago": "transferencia" }
  ]
}
```
**Respuesta (HTTP 201 Created)**:
```json
{
  "error": false,
  "codigo": 201,
  "mensaje": "Rendición de reparto #45 registrada exitosamente.",
  "datos": { ... }
}
```

---

### Ejemplo 2: Validar (Aprobar / Rechazar) una Rendición (POST)
**URL**: `http://localhost:8000/api/v1/rendiciones/validar`  
**Body (JSON)**:
```json
{
  "id_rendicion": 15,
  "accion": "aprobada",
  "id_usuario_validador": 3,
  "observaciones": "Todo verificado correctamente contra caja."
}
```

---

## 🤝 Autor y Licencia

Desarrollado para la materia PPS - Proyecto ERP Juan 23.
