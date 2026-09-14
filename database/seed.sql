-- ====================================================================
-- DATOS INICIALES DE PRUEBA (SEED) — ERP DISTRIBUIDORA JUAN XXIII
-- ====================================================================

USE `erp_distribuidora`;

-- Limpiar tablas si tienen datos anteriores
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `detalle_remito`;
TRUNCATE TABLE `remito`;
TRUNCATE TABLE `entrega_pedido`;
TRUNCATE TABLE `entrega`;
TRUNCATE TABLE `detalle_venta`;
TRUNCATE TABLE `venta`;
TRUNCATE TABLE `producto`;
TRUNCATE TABLE `usuario`;
TRUNCATE TABLE `cliente`;
TRUNCATE TABLE `zona`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. ZONAS DE REPARTO
INSERT INTO `zona` (`id_zona`, `nombre`, `descripcion`) VALUES
(1, 'Ruta Saavedra', 'Localidades de Saavedra y Goyena'),
(2, 'Ruta Tornquist', 'Tornquist, Sierra de la Ventana y Villa Ventana'),
(3, 'Pigüé Urbano', 'Casco céntrico y comercios locales'),
(4, 'Ruta Puan', 'Puan y zona rural aledaña');

-- 2. CLIENTES (Almacenes y comercios mayoristas)
INSERT INTO `cliente` (`id_cliente`, `nombre`, `razon_social`, `cuit`, `direccion`, `localidad`, `telefono`, `id_zona`, `estado`) VALUES
(1, 'Almacén Don Juan', 'Juan Pérez S.R.L.', '30-22334455-8', 'Av. San Martín 450', 'Saavedra', '02923-451122', 1, 'activo'),
(2, 'Autoservicio Las Sierras', 'Las Sierras Distribuidora', '30-44556677-4', 'Belgrano 120', 'Tornquist', '0291-4940112', 2, 'activo'),
(3, 'Despensa El Sol', 'María Gómez', '27-33445566-3', 'Rivadavia 890', 'Pigüé', '02923-472233', 3, 'activo'),
(4, 'Minimercado Goyena', 'Carlos Rossi', '20-18223344-9', 'Mitre 230', 'Goyena', '02923-498877', 1, 'activo'),
(5, 'Supermercado Central Puan', 'Puan Alimentos S.A.', '30-66778899-1', 'Avenida de Mayo 65', 'Puan', '02923-481100', 4, 'activo');

-- 3. USUARIOS
INSERT INTO `usuario` (`id_usuario`, `nombre`, `user`, `password`, `rol`, `estado`) VALUES
(1, 'Diego (Dueño / Vendedor)', 'diego', 'admin123', 'admin', 'activo'),
(2, 'Alma (Administración)', 'alma', 'alma123', 'admin', 'activo'),
(3, 'Matías (Repartidor Oficial)', 'matias', 'matias123', 'repartidor', 'activo');

-- 4. PRODUCTOS
INSERT INTO `producto` (`id_producto`, `codigo`, `nombre`, `descripcion`, `precio_unitario`, `bultos_por_unidad`, `stock`, `estado`) VALUES
(1, 'ACE-GIR-01', 'Aceite Girasol 1.5L (Caja x 12)', 'Caja con 12 botellas de 1.5L', 18500.00, 1, 150, 'activo'),
(2, 'HAR-000-01', 'Harina 000 1kg (Fardo x 10)', 'Fardo de 10 paquetes de 1kg', 9800.00, 1, 300, 'activo'),
(3, 'AZU-LED-01', 'Azúcar Ledesma 1kg (Fardo x 10)', 'Fardo de 10 paquetes de 1kg', 12500.00, 1, 200, 'activo'),
(4, 'FID-LUC-01', 'Fideos Tallarín Lucchetti 500g (Caja x 15)', 'Caja con 15 paquetes de 500g', 14200.00, 1, 180, 'activo'),
(5, 'ARR-LUC-01', 'Arroz Lucchetti 1kg (Caja x 10)', 'Caja con 10 paquetes de 1kg', 16300.00, 1, 120, 'activo');

-- 5. VENTAS CONFIRMADAS CON BULTOS Y ARTÍCULOS SUELTOS
-- Pedido 1 (Saavedra): Don Juan -> 15 bultos + 4 artículos sueltos
INSERT INTO `venta` (`id_venta`, `id_cliente`, `id_usuario`, `fecha`, `total`, `total_bultos`, `total_sueltos`, `estado`, `observaciones`) VALUES
(1, 1, 1, NOW() - INTERVAL 2 HOUR, 224500.00, 15, 4, 'confirmada', 'Entregar por la mañana antes de las 11:00 hs. Estacionar sobre lateral.');

INSERT INTO `detalle_venta` (`id_venta`, `id_producto`, `cantidad`, `precio_unitario`, `subtotal`, `bultos`, `unidades_sueltas`) VALUES
(1, 1, 5, 18500.00, 92500.00, 5, 0), -- 5 cajas cerradas de aceite
(1, 2, 6, 9800.00, 58800.00, 6, 0), -- 6 fardos cerrados de harina
(1, 3, 4, 12500.00, 50000.00, 4, 0), -- 4 fardos cerrados de azúcar
(1, 4, 4, 14200.00, 23200.00, 0, 4); -- 4 paquetes sueltos de fideos

-- Pedido 2 (Saavedra / Goyena): Minimercado Goyena -> 12 bultos + 2 sueltos
INSERT INTO `venta` (`id_venta`, `id_cliente`, `id_usuario`, `fecha`, `total`, `total_bultos`, `total_sueltos`, `estado`, `observaciones`) VALUES
(2, 4, 1, NOW() - INTERVAL 1 HOUR, 161200.00, 12, 2, 'confirmada', 'Cobro contra entrega en efectivo. Tocar timbre portón.');

INSERT INTO `detalle_venta` (`id_venta`, `id_producto`, `cantidad`, `precio_unitario`, `subtotal`, `bultos`, `unidades_sueltas`) VALUES
(2, 1, 4, 18500.00, 74000.00, 4, 0),
(2, 4, 5, 14200.00, 71000.00, 5, 0),
(2, 5, 3, 16300.00, 48900.00, 3, 2);

-- Pedido 3 (Tornquist): Autoservicio Las Sierras -> 20 bultos + 0 sueltos
INSERT INTO `venta` (`id_venta`, `id_cliente`, `id_usuario`, `fecha`, `total`, `total_bultos`, `total_sueltos`, `estado`, `observaciones`) VALUES
(3, 2, 1, NOW() - INTERVAL 3 HOUR, 298000.00, 20, 0, 'confirmada', 'Descarga por portón trasero. Pedido de pallet completo.');

INSERT INTO `detalle_venta` (`id_venta`, `id_producto`, `cantidad`, `precio_unitario`, `subtotal`, `bultos`, `unidades_sueltas`) VALUES
(3, 1, 8, 18500.00, 148000.00, 8, 0),
(3, 2, 7, 9800.00, 68600.00, 7, 0),
(3, 3, 5, 12500.00, 62500.00, 5, 0);

-- Pedido 4 (Pigüé Urbano): Despensa El Sol -> 8 bultos + 6 artículos sueltos
INSERT INTO `venta` (`id_venta`, `id_cliente`, `id_usuario`, `fecha`, `total`, `total_bultos`, `total_sueltos`, `estado`, `observaciones`) VALUES
(4, 3, 1, NOW() - INTERVAL 30 MINUTE, 118400.00, 8, 6, 'confirmada', 'Local céntrico frente a la plaza. Horario comercial.');

INSERT INTO `detalle_venta` (`id_venta`, `id_producto`, `cantidad`, `precio_unitario`, `subtotal`, `bultos`, `unidades_sueltas`) VALUES
(4, 4, 4, 14200.00, 56800.00, 4, 3),
(4, 5, 4, 16300.00, 65200.00, 4, 3);

-- Pedido 5 (Puan): Supermercado Central Puan -> 18 bultos + 0 sueltos
INSERT INTO `venta` (`id_venta`, `id_cliente`, `id_usuario`, `fecha`, `total`, `total_bultos`, `total_sueltos`, `estado`, `observaciones`) VALUES
(5, 5, 1, NOW() - INTERVAL 4 HOUR, 267000.00, 18, 0, 'confirmada', 'Abonará con cheque a 30 días al recibir.');

INSERT INTO `detalle_venta` (`id_venta`, `id_producto`, `cantidad`, `precio_unitario`, `subtotal`, `bultos`, `unidades_sueltas`) VALUES
(5, 1, 6, 18500.00, 111000.00, 6, 0),
(5, 2, 6, 9800.00, 58800.00, 6, 0),
(5, 4, 6, 14200.00, 85200.00, 6, 0);
