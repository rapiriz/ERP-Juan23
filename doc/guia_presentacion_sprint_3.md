# 🚀 Plan de Presentación y Validación — Sprint 3 (Grupo 2)

**Integrantes:** Estefanía Gianovich, Nicolás, Sofía Pizzano, Tomás Guanes  
**Fecha:** Octubre 2026  
**Módulos:** Entregas, Facturación (planificada), Cobros, Rendiciones, Caja, Conciliación Bancaria  

---

## 🧭 1. Flujo Integrado de Negocio del Grupo 2

El siguiente diagrama ilustra cómo colaboran e interactúan los módulos desarrollados por cada integrante para cumplir el circuito operativo de la distribuidora:

```mermaid
sequenceDiagram
    autonumber
    actor Repartidor as 🚚 Repartidor / Chofer
    actor Admin as 👤 Administrativo / Cajero
    participant Entregas as 📦 Entregas (Estefanía)
    participant Cobros as 💵 Cobros (Tomás)
    participant Rendiciones as 📋 Rendiciones (Nicolás)
    participant Caja as 🏦 Caja (Sofía)
    participant Conciliacion as 🏛️ Conciliación (Sofía)

    %% Inicio del Día
    Admin->>Caja: 1. Apertura de Caja diaria (CAJ-01: saldo inicial)
    Repartidor->>Entregas: 2. Consulta pedidos confirmados (HU #1)
    Repartidor->>Entregas: 3. Arma hoja de ruta y emite remitos numerados R-XXXX (HU #2)
    
    %% Recorrido y Cobranza
    Repartidor->>Repartidor: Realiza entregas físicas a clientes
    Admin->>Cobros: 4. Registra cobro con imputación FIFO a facturas (COB-01)
    opt Cobro en Efectivo
        Cobros->>Caja: Impacta movimiento de ingreso automático en CAJA_MOVIMIENTO
    end

    %% Cierre del Reparto
    Repartidor->>Rendiciones: 5. Genera rendición de jornada (reparto + cobros + devoluciones)
    Admin->>Rendiciones: 6. Revisa y aprueba rendición (control de diferencias)
    
    %% Cierre de Jornada y Banco
    Admin->>Caja: 7. Realiza arqueo y cierre de caja diaria (control faltante/sobrante)
    Admin->>Conciliacion: 8. Ejecuta cruce automático de extracto bancario (margen 48hs, monto exacto)
```

---

## 🏛️ 2. Arquitectura Técnica del Sistema

El Grupo 2 opera bajo un **Monolito Modular** en Laravel 12 con bajo acoplamiento, convención **PSR-4** (`App\` $\rightarrow$ `src/`) y separación de responsabilidades en 4 capas:

```mermaid
graph TD
    subgraph Cliente ["🖥️ Clientes / Frontend"]
        UI_Blade["Vistas Blade (Claymorfismo)<br/>/caja · /conciliacion"]
        UI_Static["Paneles Web HTML5/JS<br/>/entregas.html · /rendiciones.html"]
        HTTP_Client["Herramientas API REST<br/>cobros.http · Postman"]
    end

    subgraph API_Gateway ["🚪 Enrutamiento Central (routes/api.php)"]
        R_Entregas["/api/v1/entregas"]
        R_Cobros["/api/v1/cobros"]
        R_Rendiciones["/api/v1/rendiciones"]
        R_Caja["/api/v1/caja"]
        R_Conciliacion["/api/v1/conciliacion"]
    end

    subgraph Modulos ["📦 Módulos de Dominio (src/)"]
        subgraph Mod_Entregas ["Entregas"]
            EC["EntregaController"] --> ES["EntregaService"] --> ER["EntregaRepository"] --> EM["Entrega / Remito"]
        end
        subgraph Mod_Cobros ["Cobros"]
            CC["CobroController"] --> CS["CobroService (FIFO)"] --> CR["CobroRepository"] --> CM["Cobro / CobroVenta"]
        end
        subgraph Mod_Rendiciones ["Rendiciones"]
            RC["RendicionController"] --> RS["RendicionService"] --> RR["RendicionRepository"] --> RM["Rendicion / RendicionCobro"]
        end
        subgraph Mod_Caja ["Caja"]
            CaC["CajaController"] --> CaS["CajaService"] --> CaR["CajaRepository"] --> CaM["Caja / Movimiento"]
        end
        subgraph Mod_Conciliacion ["Conciliación Bancaria"]
            CoC["ConciliacionController"] --> CoS["ConciliacionService"] --> CoR["ConciliacionRepository"] --> CoM["Periodo / Movimiento"]
        end
    end

    subgraph BD ["🗄️ Base de Datos Compartida (MySQL)"]
        T_Entregas[("ENTREGA<br/>REMITO")]
        T_Cobros[("COBRO<br/>COBRO_VENTA")]
        T_Rendiciones[("RENDICION<br/>RENDICION_COBRO")]
        T_Caja[("CAJA<br/>CAJA_MOVIMIENTO")]
        T_Conciliacion[("PERIODO_CONCILIACION<br/>MOVIMIENTO_BANCARIO<br/>CHEQUE")]
    end

    UI_Blade --> API_Gateway
    UI_Static --> API_Gateway
    HTTP_Client --> API_Gateway

    R_Entregas --> EC
    R_Cobros --> CC
    R_Rendiciones --> RC
    R_Caja --> CaC
    R_Conciliacion --> CoC

    EM --> T_Entregas
    CM --> T_Cobros
    RM --> T_Rendiciones
    CaM --> T_Caja
    CoM --> T_Conciliacion

    CS -.->|"Impacto Automático en Efectivo"| T_Caja
```

---

## 🎤 3. Guion de Exposición para la Presentación (10 a 15 min)

### Parte 1: Introducción y Arquitectura General (2 min) — *Todo el equipo / Portavoz*
* **Qué destacar:**
  1. Adopción exitosa de **Laravel 12** con estándar PSR-4 sin código procedural legacy.
  2. Arquitectura **Monolito Modular** en 4 capas (`Controller` $\rightarrow$ `Service` $\rightarrow$ `Repository` $\rightarrow$ `Model`).
  3. Contratos de **API REST versionados (`/api/v1`)** y respuestas unificadas en formato JSON.
  4. Respeto estricto de la **propiedad de datos**: cada módulo gestiona sus tablas sin romper los dominios de otros grupos.

---

### Parte 2: Demostración Módulo por Módulo (8-10 min)

#### 🚚 1. Entregas y Logística (Estefanía Gianovich)
* **Qué mostrar en vivo:**
  * Abrir en el navegador: `http://localhost:8000/entregas.html`.
  * Mostrar cómo se listan los pedidos pendientes filtrados por zona (HU #1).
  * Seleccionar pedidos y generar una entrega: mostrar la creación automática del **Remito con numeración formal `R-XXXX-XXXXXXXX`** (HU #2).
  * Mostrar cómo los pedidos cambian de estado y quedan asociados al repartidor.

#### 💵 2. Cobros y Cuenta Corriente (Tomás Guanes)
* **Qué mostrar en vivo:**
  * Mostrar el archivo `cobros.http` o ejecutar mediante Postman/curl un registro de cobro (`POST /api/v1/cobros`).
  * Destacar el **Algoritmo FIFO de Imputación Progresiva**: cómo un pago parcial o total salda primero las facturas más antiguas registradas en `COBRO_VENTA`.
  * **Punto fuerte de integración:** Mostrar que si el medio de pago es `efectivo`, automáticamente se registra un movimiento de entrada en `CAJA_MOVIMIENTO` dentro de la misma transacción atómica (`DB::transaction`).
  * Consultar el saldo del cliente en tiempo real (`GET /api/v1/cobros/clientes/{id}/saldo`).

#### 📋 3. Rendiciones de Reparto (Nicolás)
* **Qué mostrar en vivo:**
  * Abrir en el navegador: `http://localhost:8000/rendiciones.php` (o `rendiciones.html`).
  * Mostrar el panel visual con diseño Claymórfico y tabla sin scroll horizontal.
  * Cargar una rendición con cobros desglosados por medio de pago (efectivo, transferencias, cheques).
  * Registrar una devolución o registrar una diferencia (sobrante/faltante).
  * Cerrar y revisar la rendición cambiando su estado a aprobada.

#### 🏦 4. Caja y Conciliación Bancaria (Sofía Pizzano)
* **Qué mostrar en vivo:**
  * **Caja:**
    * Abrir `http://localhost:8000/caja`.
    * Mostrar la **Apertura de caja** (`CAJ-01`) con validación de no permitir duplicados por usuario/día.
    * Ver los movimientos del día (`CAJ-02`), incluyendo el cobro en efectivo generado en el módulo de Tomás.
    * Mostrar el **Historial de cierres** (`CAJ-03` / `CAJ-04`) con filtro de fechas y arqueo de diferencias.
  * **Conciliación Bancaria:**
    * Abrir `http://localhost:8000/conciliacion`.
    * Mostrar la pantalla de períodos de conciliación.
    * Explicar el **Motor de Conciliación Automática**: cruce con regla de tolerancia de 48hs, monto exacto y descarte por ambigüedad.
    * Mostrar el módulo de **Cheques**: alta, depósito y rechazo con reversión contable automática.

---

### Parte 3: Calidad y Pruebas Automatizadas (2 min) — *Tomás / Sofía*
* **Qué mostrar en la consola:**
  * Ejecutar el comando de tests unitarios:
    ```bash
    vendor/bin/phpunit tests/Unit/CobroServiceTest.php
    vendor/bin/phpunit tests/Unit/RendicionServiceTest.php
    ```
  * Mostrar que los tests de negocio pasan al **100% de efectividad**.

---

### Parte 4: Cierre y Próximos Pasos para el Sprint 4 (1 min)
* **Puntos a mencionar:**
  1. **Integración Cruzada Entregas-Cobros (`[G2]-COB-04`):** Conectar los cobros pendientes directamente en la hoja de ruta del repartidor en `entregas.html`.
  2. **Anulación de Cobros (`[G2]-COB-03`):** La estructura relacional y campos de auditoría ya están en la base de datos; se habilitará la reversión en el Sprint 4.
  3. **Facturación:** Alinear con el Grupo 4 (Ventas) una vez definidos sus contratos para conectar la emisión de facturas.

---

## 📋 4. Checklist Técnico de Verificación Previa

Antes de conectar el proyector o compartir pantalla:

- [ ] **Base de Datos MySQL:** Servidor MySQL corriendo y accesible.
- [ ] **Variables de entorno:** Archivo `.env` configurado con credenciales de base de datos.
- [ ] **Migraciones aplicadas:** Ejecutar `php artisan migrate` para garantizar que las tablas de los 4 integrantes existan en la base local.
- [ ] **Servidor Web activo:** Servidor corriendo mediante `php artisan serve` o `php -c php.ini -S localhost:8000 -t public`.
- [ ] **Pestañas del navegador listas:**
  1. `http://localhost:8000/entregas.html` (Entregas)
  2. `http://localhost:8000/rendiciones.php` (Rendiciones)
  3. `http://localhost:8000/caja` (Caja)
  4. `http://localhost:8000/conciliacion` (Conciliación)
- [ ] **Cliente HTTP listo:** Archivo `cobros.http` abierto en VS Code con REST Client o colección de Postman preparada.
- [ ] **Consola limpia:** Terminal abierta en la raíz con el comando `vendor/bin/phpunit` listo para ejecutar.
