-- ====================================================================
-- ESQUEMA DE BASE DE DATOS — ERP DISTRIBUIDORA JUAN XXIII
-- GRUPO 2: Módulos de Entregas (Logística), Rendiciones y Caja
-- ====================================================================
-- NOTA DE ARQUITECTURA:
-- Este script define EXCLUSIVAMENTE las tablas propias y bajo la
-- responsabilidad del Grupo 2.
-- Las referencias a entidades externas (Ventas del G4, Clientes,
-- Productos/Stock y Usuarios) se gestionan mediante claves foráneas
-- lógicas (id_venta, id_cliente, id_producto, id_usuario) integradas
-- por API REST o servicios compartidos, sin acoplamiento rígido de BD.
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `erp_distribuidora` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `erp_distribuidora`;

-- Desactivar chequeo de claves foráneas para recreación idempotente
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `CAJA_MOVIMIENTO`;

DROP TABLE IF EXISTS `CAJA`;

DROP TABLE IF EXISTS `RENDICION_COBRO`;

DROP TABLE IF EXISTS `RENDICION`;

DROP TABLE IF EXISTS `detalle_remito`;

DROP TABLE IF EXISTS `remito`;

DROP TABLE IF EXISTS `entrega_pedido`;

DROP TABLE IF EXISTS `entrega`;

DROP TABLE IF EXISTS `zona`;

SET FOREIGN_KEY_CHECKS = 1;

-- ====================================================================
-- 1. MÓDULO ENTREGAS / LOGÍSTICA (G2)
-- ====================================================================

-- 1.1 ZONAS / RUTAS DE REPARTO
CREATE TABLE `zona` (
    `id_zona` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 1.2 ENTREGAS (Viajes / Hojas de Ruta del Repartidor)
CREATE TABLE `entrega` (
    `id_entrega` INT AUTO_INCREMENT PRIMARY KEY,
    `id_repartidor` INT NOT NULL COMMENT 'ID lógico del usuario repartidor',
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_salida` DATE NULL,
    `estado` ENUM(
        'en_preparacion',
        'en_camino',
        'finalizada',
        'cancelada'
    ) NOT NULL DEFAULT 'en_preparacion',
    `observaciones` TEXT NULL,
    `id_usuario_creacion` INT NOT NULL DEFAULT 1 COMMENT 'ID lógico del usuario que planificó la entrega',
    INDEX `idx_entrega_repartidor` (`id_repartidor`),
    INDEX `idx_entrega_estado` (`estado`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 1.3 ENTREGA_PEDIDO (Relación de Entregas con Pedidos/Ventas)
CREATE TABLE `entrega_pedido` (
    `id_entrega_pedido` INT AUTO_INCREMENT PRIMARY KEY,
    `id_entrega` INT NOT NULL,
    `id_venta` INT NOT NULL COMMENT 'ID de la venta provista por API Ventas (G4)',
    `estado_pedido_entrega` ENUM(
        'en_preparacion',
        'en_camino',
        'entregado',
        'no_entregado',
        'rechazado'
    ) NOT NULL DEFAULT 'en_preparacion',
    `motivo_rechazo` TEXT NULL,
    `fecha_actualizacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_ep_venta` (`id_venta`),
    CONSTRAINT `fk_ep_entrega` FOREIGN KEY (`id_entrega`) REFERENCES `entrega` (`id_entrega`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 1.4 REMITOS (Comprobante legal de traslado y entrega)
CREATE TABLE `remito` (
    `id_remito` INT AUTO_INCREMENT PRIMARY KEY,
    `numero_remito` VARCHAR(30) NOT NULL UNIQUE,
    `id_entrega` INT NOT NULL,
    `id_venta` INT NOT NULL COMMENT 'ID de la venta asociada',
    `fecha_emision` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `estado` ENUM(
        'emitido',
        'entregado_conforme',
        'anulado'
    ) NOT NULL DEFAULT 'emitido',
    `observaciones` TEXT NULL,
    INDEX `idx_remito_venta` (`id_venta`),
    CONSTRAINT `fk_remito_entrega` FOREIGN KEY (`id_entrega`) REFERENCES `entrega` (`id_entrega`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 1.5 DETALLE DE REMITO (Productos y bultos físicos cargados en el camión)
CREATE TABLE `detalle_remito` (
    `id_detalle_remito` INT AUTO_INCREMENT PRIMARY KEY,
    `id_remito` INT NOT NULL,
    `id_producto` INT NULL COMMENT 'ID de producto en catálogo/stock',
    `descripcion` VARCHAR(255) NOT NULL,
    `cantidad` INT NOT NULL,
    `bultos` INT NOT NULL DEFAULT 1,
    CONSTRAINT `fk_dr_remito` FOREIGN KEY (`id_remito`) REFERENCES `remito` (`id_remito`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ====================================================================
-- 2. MÓDULO RENDICIONES (G2)
-- ====================================================================

-- 2.1 RENDICION (Liquidación de jornada del repartidor ligada al reparto)
CREATE TABLE `RENDICION` (
    `id_rendicion` INT AUTO_INCREMENT PRIMARY KEY,
    `id_repartidor` INT NOT NULL COMMENT 'ID lógico del repartidor',
    `id_entrega` INT NULL COMMENT 'ID de la entrega/hoja de ruta asociada',
    `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `total_rendido` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `estado` ENUM(
        'pendiente',
        'aprobada',
        'rechazada'
    ) NOT NULL DEFAULT 'pendiente',
    `observaciones` TEXT NULL,
    `id_usuario_validador` INT NULL COMMENT 'ID lógico del administrador que valida',
    `fecha_validacion` DATETIME NULL,
    `motivo_rechazo` TEXT NULL,
    INDEX `idx_rendicion_repartidor` (`id_repartidor`),
    INDEX `idx_rendicion_estado` (`estado`),
    CONSTRAINT `fk_rendicion_entrega` FOREIGN KEY (`id_entrega`) REFERENCES `entrega` (`id_entrega`) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 2.2 RENDICION_COBRO (Desglose de cobranzas por pedido y medio de pago)
CREATE TABLE `RENDICION_COBRO` (
    `id_rendicion_cobro` INT AUTO_INCREMENT PRIMARY KEY,
    `id_rendicion` INT NOT NULL,
    `id_cliente` INT NOT NULL COMMENT 'ID lógico del cliente',
    `id_factura` INT NOT NULL COMMENT 'ID de la factura/venta saldada (G4)',
    `monto` DECIMAL(12, 2) NOT NULL,
    `medio_pago` ENUM(
        'efectivo',
        'transferencia',
        'cheque'
    ) NOT NULL DEFAULT 'efectivo',
    `fecha_registro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_rc_rendicion` (`id_rendicion`),
    INDEX `idx_rc_factura` (`id_factura`),
    CONSTRAINT `fk_rc_rendicion` FOREIGN KEY (`id_rendicion`) REFERENCES `RENDICION` (`id_rendicion`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ====================================================================
-- 3. MÓDULO CAJA (G2)
-- ====================================================================

-- 3.1 CAJA (Apertura, arqueo y cierre diario de caja)
CREATE TABLE `CAJA` (
    `id_caja` INT AUTO_INCREMENT PRIMARY KEY,
    `id_usuario` INT NOT NULL COMMENT 'ID lógico del responsable/cajero',
    `fecha` DATE NOT NULL,
    `monto_inicial` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `monto_final` DECIMAL(12, 2) NULL,
    `monto_esperado` DECIMAL(12, 2) NULL,
    `diferencia` DECIMAL(12, 2) NULL,
    `estado` ENUM('abierta', 'cerrada') NOT NULL DEFAULT 'abierta',
    `id_usuario_cierre` INT NULL,
    `fecha_hora_apertura` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_hora_cierre` DATETIME NULL,
    `fecha_vencimiento` DATE NULL,
    `observaciones` VARCHAR(500) NULL,
    INDEX `idx_caja_usuario_estado` (`id_usuario`, `estado`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 3.2 CAJA_MOVIMIENTO (Movimientos de efectivo, ingresos por rendición y auditoría)
CREATE TABLE `CAJA_MOVIMIENTO` (
    `id_movimiento_caja` INT AUTO_INCREMENT PRIMARY KEY,
    `id_caja` INT NULL,
    `fecha` DATE NOT NULL,
    `fecha_hora_carga` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `monto` DECIMAL(12, 2) NOT NULL,
    `tipo` VARCHAR(20) NOT NULL COMMENT 'cobro | ingreso | egreso',
    `concepto` VARCHAR(255) NOT NULL,
    `id_usuario` INT NOT NULL COMMENT 'ID lógico del usuario que operó',
    `id_cobro` INT NULL COMMENT 'ID de cobro asociado si aplica',
    `monto_anterior` DECIMAL(12, 2) NULL COMMENT 'Auditoría de modificaciones',
    `id_usuario_modificacion` INT NULL COMMENT 'Auditoría',
    `fecha_hora_modificacion` DATETIME NULL COMMENT 'Auditoría',
    INDEX `idx_cm_caja` (`id_caja`),
    INDEX `idx_cm_tipo` (`tipo`),
    CONSTRAINT `fk_cm_caja` FOREIGN KEY (`id_caja`) REFERENCES `CAJA` (`id_caja`) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;