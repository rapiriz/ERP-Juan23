-- =============================================================================
-- DATOS DE PRUEBA (SEMILLAS) PARA EL MÓDULO DE COBROS
-- Proyecto: ERP Distribuidora Pigüé (ERP-Juan23)
-- =============================================================================

USE erp_distribuidora;

-- 1. ZONAS
INSERT INTO ZONA (id_zona, nombre, descripcion) VALUES
(1, 'Zona Centro - Pigüé', 'Ruta céntrica urbana de Pigüé'),
(2, 'Zona Saavedra', 'Ruta interurbana hacia localidad de Saavedra');

-- 2. CLIENTES
INSERT INTO CLIENTE (id_cliente, nombre, razon_social, cuil, email, telefono, direccion, localidad, id_zona, tipo_cliente, condicion_iva, estado) VALUES
(1, 'Supermercado Central', 'Supermercado Central S.R.L.', '30-71123456-8', 'central@pigue.com', '2923-401122', 'Av. Casey 450', 'Pigüé', 1, 'mayorista', 'Responsable Inscripto', 'activo'),
(2, 'Almacén Don Pedro', 'Pedro Gomez', '20-25987654-3', 'donpedro@gmail.com', '2923-458899', 'Rivadavia 120', 'Pigüé', 1, 'minorista', 'Monotributo', 'activo'),
(3, 'Autoservicio El Sol', 'El Sol S.A.', '30-65432109-7', 'contacto@elsol.com', '2923-490011', 'San Martin 890', 'Saavedra', 2, 'mayorista', 'Responsable Inscripto', 'activo');

-- 3. USUARIOS (Alma = Admin, Matias = Repartidor, Diego = Vendedor)
-- Nota: id_usuario hereda de id_cliente en este modelo
INSERT INTO USUARIO (id_usuario, user, password, rol, estado) VALUES
(1, 'alma_admin', '$2y$10$eImiTXuWVxfM37uY4JANjOL.81F8RzW6d1gJ3W.zR.1a1A7eNf9dG', 'admin', 'activo'),
(2, 'matias_repartidor', '$2y$10$eImiTXuWVxfM37uY4JANjOL.81F8RzW6d1gJ3W.zR.1a1A7eNf9dG', 'repartidor', 'activo'),
(3, 'diego_vendedor', '$2y$10$eImiTXuWVxfM37uY4JANjOL.81F8RzW6d1gJ3W.zR.1a1A7eNf9dG', 'vendedor', 'activo');

-- 4. VENTAS PENDIENTES DE PAGO (Deuda para probar el módulo de Cobros)
INSERT INTO VENTA (id_venta, id_cliente, fecha, total, pagado, numFactura, estado, observaciones, id_usuario) VALUES
(101, 1, NOW() - INTERVAL 5 DAY, 45000.00, FALSE, 'FC-A-0001-00000101', 'confirmada', 'Pedido semanal bultos pesados', 3),
(102, 1, NOW() - INTERVAL 2 DAY, 18500.50, FALSE, 'FC-A-0001-00000105', 'facturada', 'Reposición lácteos', 3),
(103, 2, NOW() - INTERVAL 3 DAY, 25000.00, FALSE, 'FC-B-0001-00000201', 'confirmada', 'Entrega bebidas', 3),
(104, 3, NOW() - INTERVAL 1 DAY, 89000.00, FALSE, 'FC-A-0001-00000301', 'confirmada', 'Pedido mayorista quincenal', 3);

-- 5. COBRO DE EJEMPLO PRE-REGISTRADO (Opcional, para pruebas de consulta)
INSERT INTO COBRO (id_cobro, id_cliente, id_usuario, fecha, monto_total, medio_pago, comprobante_nro, estado, observaciones) VALUES
(1, 1, 1, NOW() - INTERVAL 1 DAY, 10000.00, 'transferencia', 'TR-99887766', 'registrado', 'Pago parcial anticipado');

INSERT INTO COBRO_VENTA (id_cobro, id_venta, monto_aplicado) VALUES
(1, 101, 10000.00);
