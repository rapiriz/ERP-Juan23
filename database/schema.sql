-- ====================================================================
-- ESQUEMA DE BASE DE DATOS — ERP DISTRIBUIDORA JUAN XXIII
-- Módulo: Entregas (Logística) y Soporte de Ventas/Clientes
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `erp_distribuidora` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `erp_distribuidora`;

-- Desactivar chequeo de claves foráneas para recrear tablas limpias
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `detalle_remito`;
DROP TABLE IF EXISTS `remito`;
DROP TABLE IF EXISTS `entrega_pedido`;
DROP TABLE IF EXISTS `entrega`;
DROP TABLE IF EXISTS `detalle_venta`;
DROP TABLE IF EXISTS `venta`;
DROP TABLE IF EXISTS `producto`;
DROP TABLE IF EXISTS `usuario`;
DROP TABLE IF EXISTS `cliente`;
DROP TABLE IF EXISTS `zona`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. ZONAS / RUTAS DE REPARTO
CREATE TABLE `zona` (
    `id_zona` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL
) ENGINE=InnoDB;

-- 2. CLIENTES
CREATE TABLE `cliente` (
    `id_cliente` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(150) NOT NULL,
    `razon_social` VARCHAR(150) NULL,
    `cuit` VARCHAR(20) NULL,
    `direccion` VARCHAR(255) NOT NULL,
    `localidad` VARCHAR(100) NOT NULL,
    `telefono` VARCHAR(50) NULL,
    `id_zona` INT NOT NULL,
    `estado` ENUM('activo', 'inactivo') DEFAULT 'activo',
    CONSTRAINT `fk_cliente_zona` FOREIGN KEY (`id_zona`) REFERENCES `zona` (`id_zona`)
) ENGINE=InnoDB;

-- 3. USUARIOS (Repartidores, Vendedores, Admin)
CREATE TABLE `usuario` (
    `id_usuario` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(150) NOT NULL,
    `user` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `rol` ENUM('admin', 'vendedor', 'repartidor') NOT NULL,
    `estado` ENUM('activo', 'bloqueado') DEFAULT 'activo'
) ENGINE=InnoDB;

-- 4. PRODUCTOS
CREATE TABLE `producto` (
    `id_producto` INT AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL UNIQUE,
    `nombre` VARCHAR(200) NOT NULL,
    `descripcion` TEXT NULL,
    `precio_unitario` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `bultos_por_unidad` INT NOT NULL DEFAULT 1,
    `stock` INT NOT NULL DEFAULT 0,
    `estado` ENUM('activo', 'inactivo') DEFAULT 'activo'
) ENGINE=InnoDB;

-- 5. VENTAS (Módulo Ventas / G4)
CREATE TABLE `venta` (
    `id_venta` INT AUTO_INCREMENT PRIMARY KEY,
    `id_cliente` INT NOT NULL,
    `id_usuario` INT NOT NULL,
    `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_bultos` INT NOT NULL DEFAULT 0,
    `total_sueltos` INT NOT NULL DEFAULT 0,
    `estado` ENUM('pendiente', 'confirmada', 'en_preparacion', 'en_camino', 'entregada', 'cancelada') DEFAULT 'confirmada',
    `observaciones` TEXT NULL,
    CONSTRAINT `fk_venta_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
    CONSTRAINT `fk_venta_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB;

-- 6. DETALLE DE VENTAS
CREATE TABLE `detalle_venta` (
    `id_detalle_venta` INT AUTO_INCREMENT PRIMARY KEY,
    `id_venta` INT NOT NULL,
    `id_producto` INT NOT NULL,
    `cantidad` INT NOT NULL,
    `precio_unitario` DECIMAL(12,2) NOT NULL,
    `subtotal` DECIMAL(12,2) NOT NULL,
    `bultos` INT NOT NULL DEFAULT 1,
    `unidades_sueltas` INT NOT NULL DEFAULT 0,
    CONSTRAINT `fk_dv_venta` FOREIGN KEY (`id_venta`) REFERENCES `venta` (`id_venta`) ON DELETE CASCADE,
    CONSTRAINT `fk_dv_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`)
) ENGINE=InnoDB;

-- 7. ENTREGAS (Viajes / Hojas de Ruta — Módulo Entregas / G2)
CREATE TABLE `entrega` (
    `id_entrega` INT AUTO_INCREMENT PRIMARY KEY,
    `id_repartidor` INT NOT NULL,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_salida` DATE NOT NULL,
    `estado` ENUM('en_preparacion', 'en_camino', 'finalizada', 'cancelada') NOT NULL DEFAULT 'en_preparacion',
    `observaciones` TEXT NULL,
    `id_usuario_creacion` INT NOT NULL,
    CONSTRAINT `fk_entrega_repartidor` FOREIGN KEY (`id_repartidor`) REFERENCES `usuario` (`id_usuario`),
    CONSTRAINT `fk_entrega_creador` FOREIGN KEY (`id_usuario_creacion`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB;

-- 8. ENTREGA_PEDIDO (Relación Entrega - Ventas)
CREATE TABLE `entrega_pedido` (
    `id_entrega_pedido` INT AUTO_INCREMENT PRIMARY KEY,
    `id_entrega` INT NOT NULL,
    `id_venta` INT NOT NULL,
    `estado_pedido_entrega` ENUM('en_preparacion', 'en_camino', 'entregado', 'no_entregado', 'rechazado') DEFAULT 'en_preparacion',
    `motivo_rechazo` TEXT NULL,
    `fecha_actualizacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ep_entrega` FOREIGN KEY (`id_entrega`) REFERENCES `entrega` (`id_entrega`) ON DELETE CASCADE,
    CONSTRAINT `fk_ep_venta` FOREIGN KEY (`id_venta`) REFERENCES `venta` (`id_venta`)
) ENGINE=InnoDB;

-- 9. REMITOS (Comprobante legal de traslado — HU #2)
CREATE TABLE `remito` (
    `id_remito` INT AUTO_INCREMENT PRIMARY KEY,
    `numero_remito` VARCHAR(30) NOT NULL UNIQUE,
    `id_entrega` INT NOT NULL,
    `id_venta` INT NOT NULL,
    `fecha_emision` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `estado` ENUM('emitido', 'entregado_conforme', 'anulado') NOT NULL DEFAULT 'emitido',
    `observaciones` TEXT NULL,
    CONSTRAINT `fk_remito_entrega` FOREIGN KEY (`id_entrega`) REFERENCES `entrega` (`id_entrega`) ON DELETE CASCADE,
    CONSTRAINT `fk_remito_venta` FOREIGN KEY (`id_venta`) REFERENCES `venta` (`id_venta`)
) ENGINE=InnoDB;

-- 10. DETALLE DE REMITO (Productos y bultos físicos que viajan)
CREATE TABLE `detalle_remito` (
    `id_detalle_remito` INT AUTO_INCREMENT PRIMARY KEY,
    `id_remito` INT NOT NULL,
    `id_producto` INT NOT NULL,
    `descripcion` VARCHAR(255) NOT NULL,
    `cantidad` INT NOT NULL,
    `bultos` INT NOT NULL DEFAULT 1,
    CONSTRAINT `fk_dr_remito` FOREIGN KEY (`id_remito`) REFERENCES `remito` (`id_remito`) ON DELETE CASCADE,
    CONSTRAINT `fk_dr_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`)
) ENGINE=InnoDB;
