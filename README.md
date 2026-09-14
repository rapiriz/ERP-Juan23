# ERP Juan 23 — Grupo 2

Sistema ERP Distribuidora Juan XXIII — Módulos del Grupo 2: **Entregas (Logística), Facturación, Caja, Cobros, Conciliación Bancaria y Rendiciones**.

---

## 👥 Integrantes y Responsabilidades

* **Estefanía Gianovich:** Entregas (Logística y Despacho) y Facturación
* **Nicolás:** Rendiciones y Cobros
* **Tomás:** Cobros y Caja
* **Sofía:** Conciliación Bancaria y Arquitectura Laravel

---

## 🏗️ Arquitectura del Sistema

* **Monolito Modular** en PHP / Laravel bajo estándar PSR-4 (`App\` $\rightarrow$ `src/`).
* **Base de Datos:** MySQL compartida (`erp_distribuidora`).
* **Enrutamiento:** API REST versionada (`/api/v1/...`).

---

## 🚀 Cómo Ejecutar el Proyecto Localmente

1. Iniciar el servidor local:
   ```bash
   php -c php.ini -S localhost:8000 -t public
   ```
2. **Paneles Web de Demostración:**
   * Logística y Entregas: [http://localhost:8000/entregas.html](http://localhost:8000/entregas.html)
   * Rendiciones de Reparto: [http://localhost:8000/rendiciones.php](http://localhost:8000/rendiciones.php)

---

## 🧪 Pruebas Automatizadas

```bash
php -c php.ini tests/EntregasTest.php
```
