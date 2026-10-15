-- =============================================================================
-- ESQUEMA DE BASE DE DATOS PARA EL MÓDULO DE COBROS Y SUS DEPENDENCIAS
-- Proyecto: ERP Distribuidora Pigüé (ERP-Juan23)
-- Respetando las convenciones de /context:
--   - Nombres de tabla: SINGULAR Y MAYÚSCULAS (ZONA, CLIENTE, USUARIO, VENTA, COBRO, etc.)
--   - Claves primarias y foráneas: prefijo id_* en snake_case
--   - Campos de estado y atributos: snake_case
-- =============================================================================

CREATE DATABASE IF NOT EXISTS erp_distribuidora CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE erp_distribuidora;

-- 1. ZONA
CREATE TABLE IF NOT EXISTS ZONA (
    id_zona INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. CLIENTE
CREATE TABLE IF NOT EXISTS CLIENTE (
    id_cliente INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    razon_social VARCHAR(150) NULL,
    cuil VARCHAR(20) UNIQUE NULL,
    email VARCHAR(100) NULL,
    telefono VARCHAR(50) NULL,
    direccion VARCHAR(255) NOT NULL,
    localidad VARCHAR(100) NOT NULL DEFAULT 'Pigüé',
    id_zona INT NULL,
    tipo_cliente ENUM('mayorista', 'minorista', 'consumidor_final') DEFAULT 'mayorista',
    condicion_iva VARCHAR(50) DEFAULT 'Responsable Inscripto',
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    CONSTRAINT fk_cliente_zona FOREIGN KEY (id_zona) REFERENCES ZONA(id_zona) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. USUARIO (Hereda atributos de CLIENTE según arquitectura acordada)
CREATE TABLE IF NOT EXISTS USUARIO (
    id_usuario INT PRIMARY KEY,
    user VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'vendedor', 'repartidor') NOT NULL DEFAULT 'vendedor',
    estado ENUM('activo', 'bloqueado') NOT NULL DEFAULT 'activo',
    CONSTRAINT fk_usuario_cliente FOREIGN KEY (id_usuario) REFERENCES CLIENTE(id_cliente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. VENTA (Representa ventas/facturas emitidas pendientes o cobradas)
CREATE TABLE IF NOT EXISTS VENTA (
    id_venta INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL,
    pagado BOOLEAN NOT NULL DEFAULT FALSE,
    numFactura VARCHAR(50) NULL,
    estado ENUM('pendiente', 'confirmada', 'facturada', 'cancelada') DEFAULT 'confirmada',
    observaciones TEXT NULL,
    id_usuario INT NOT NULL,
    CONSTRAINT fk_venta_cliente FOREIGN KEY (id_cliente) REFERENCES CLIENTE(id_cliente),
    CONSTRAINT fk_venta_usuario FOREIGN KEY (id_usuario) REFERENCES USUARIO(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. COBRO (Módulo del Grupo 2 - Tabla principal de cobros)
CREATE TABLE IF NOT EXISTS COBRO (
    id_cobro INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    id_usuario INT NOT NULL, -- Usuario administrativo/repartidor que registra el cobro
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    monto_total DECIMAL(10,2) NOT NULL,
    medio_pago ENUM('efectivo', 'transferencia', 'cheque') NOT NULL DEFAULT 'efectivo',
    comprobante_nro VARCHAR(100) NULL, -- Nro de comprobante, transferencia o cheque
    estado ENUM('registrado', 'anulado') NOT NULL DEFAULT 'registrado',
    observaciones TEXT NULL,
    motivo_anulacion TEXT NULL,
    fecha_anulacion DATETIME NULL,
    id_usuario_anulacion INT NULL,
    CONSTRAINT fk_cobro_cliente FOREIGN KEY (id_cliente) REFERENCES CLIENTE(id_cliente),
    CONSTRAINT fk_cobro_usuario FOREIGN KEY (id_usuario) REFERENCES USUARIO(id_usuario),
    CONSTRAINT fk_cobro_usuario_anulacion FOREIGN KEY (id_usuario_anulacion) REFERENCES USUARIO(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. COBRO_VENTA (Relación M:N entre Cobros y Ventas/Facturas saldadas)
CREATE TABLE IF NOT EXISTS COBRO_VENTA (
    id_cobro INT NOT NULL,
    id_venta INT NOT NULL,
    monto_aplicado DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id_cobro, id_venta),
    CONSTRAINT fk_cv_cobro FOREIGN KEY (id_cobro) REFERENCES COBRO(id_cobro) ON DELETE CASCADE,
    CONSTRAINT fk_cv_venta FOREIGN KEY (id_venta) REFERENCES VENTA(id_venta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. CAJA_MOVIMIENTO (Movimientos de caja asociados a cobros en efectivo)
CREATE TABLE IF NOT EXISTS CAJA_MOVIMIENTO (
    id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo ENUM('ingreso', 'egreso') NOT NULL DEFAULT 'ingreso',
    concepto VARCHAR(255) NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    medio_pago ENUM('efectivo', 'transferencia', 'cheque') NOT NULL DEFAULT 'efectivo',
    id_cobro INT NULL,
    id_usuario INT NOT NULL,
    estado ENUM('activo', 'anulado') NOT NULL DEFAULT 'activo',
    CONSTRAINT fk_caja_cobro FOREIGN KEY (id_cobro) REFERENCES COBRO(id_cobro) ON DELETE SET NULL,
    CONSTRAINT fk_caja_usuario FOREIGN KEY (id_usuario) REFERENCES USUARIO(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
