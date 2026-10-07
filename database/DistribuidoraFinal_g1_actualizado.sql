CREATE TABLE `ZONA` (
  `id_zona` integer PRIMARY KEY AUTO_INCREMENT,
  `nombre` varchar(120) UNIQUE NOT NULL,
  `descripcion` varchar(255)
);

CREATE TABLE `USUARIO` (
  `id_usuario` integer PRIMARY KEY AUTO_INCREMENT,
  `usuario` varchar(50) UNIQUE NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `email` varchar(160) UNIQUE,
  `rol` ENUM ('administrativo', 'repartidor', 'contador') NOT NULL,
  `estado` ENUM ('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  `intentos_fallidos` tinyint NOT NULL DEFAULT 0,
  `bloqueado_hasta` datetime,
  `ultimo_intento_fallido` datetime,
  `current_session_id` varchar(128),
  `created_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

CREATE TABLE `PASSWORD_RESET_CODE` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `usuario_id` integer UNIQUE NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `intentos` tinyint NOT NULL DEFAULT 0,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp,
  `updated_at` timestamp
);

CREATE TABLE `CLIENTE` (
  `id_cliente` integer PRIMARY KEY AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `apellido_razon_social` varchar(160) NOT NULL,
  `dni` varchar(8) UNIQUE,
  `cuit` char(11) UNIQUE,
  `telefono` varchar(30) NOT NULL,
  `email` varchar(160) UNIQUE NOT NULL,
  `direccion` varchar(255) NOT NULL,
  `localidad` varchar(120),
  `id_zona` integer,
  `tipo_cliente` ENUM ('minorista', 'mayorista') NOT NULL,
  `condicion_iva` ENUM ('responsable_inscripto', 'sujeto_exento', 'consumidor_final', 'responsable_monotributo', 'sujeto_no_categorizado', 'proveedor_del_exterior', 'cliente_del_exterior', 'iva_liberado_ley_19640', 'monotributista_social', 'iva_no_alcanzado', 'monotributo_trabajador_independiente_promovido'),
  `estado` ENUM ('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  `saldo` decimal(12,2) DEFAULT 0,
  `creado_por` integer NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  CONSTRAINT `chk_cliente_documento` CHECK (`dni` IS NOT NULL OR `cuit` IS NOT NULL)
);

CREATE TABLE `LOGIN_LOG` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `usuario_id` integer NOT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `ip` varchar(45)
);

CREATE TABLE `RECLAMO` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `cliente_id` integer NOT NULL,
  `usuario_id` integer NOT NULL,
  `asunto` varchar(160) NOT NULL,
  `descripcion` text NOT NULL,
  `prioridad` ENUM ('baja', 'media', 'alta') NOT NULL DEFAULT 'media',
  `estado` ENUM ('abierto', 'en_proceso', 'cerrado') NOT NULL DEFAULT 'abierto',
  `created_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

CREATE TABLE `CATEGORIA` (
  `id_categoria` integer PRIMARY KEY AUTO_INCREMENT,
  `nombre` varchar(100) UNIQUE NOT NULL,
  `fecha_creacion` date NOT NULL,
  `fecha_modificacion` date
);

CREATE TABLE `MARCA` (
  `id_marca` integer PRIMARY KEY AUTO_INCREMENT,
  `nombre` varchar(100) UNIQUE NOT NULL,
  `fecha_creacion` date NOT NULL,
  `fecha_modificacion` date
);

CREATE TABLE `PRODUCTO` (
  `id_producto` integer PRIMARY KEY AUTO_INCREMENT,
  `codigo` varchar(50) UNIQUE NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` varchar(255),
  `precio_unitario` decimal(12,2) NOT NULL,
  `imagen` varchar(255),
  `stock` integer NOT NULL DEFAULT 0 COMMENT 'En MySQL: CHECK (stock >= 0)',
  `stock_minimo` integer NOT NULL DEFAULT 0,
  `dias_alerta_vencimiento` integer NOT NULL DEFAULT 30,
  `estado` ENUM ('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  `fecha_alta` date NOT NULL,
  `fecha_modificacion` date,
  `fecha_desactivacion` date,
  `id_categoria` integer NOT NULL,
  `id_marca` integer NOT NULL,
  `id_usuario_carga` integer NOT NULL,
  `id_usuario_modificacion` integer
);

CREATE TABLE `HISTORIAL_PRECIO` (
  `id_historial` integer PRIMARY KEY AUTO_INCREMENT,
  `id_producto` integer NOT NULL,
  `tipo_precio` ENUM ('general', 'mayorista', 'minorista') NOT NULL DEFAULT 'general',
  `precio` decimal(12,2) NOT NULL,
  `porcentaje_aumento` decimal(6,2),
  `regla_redondeo` varchar(50) DEFAULT 'sin_redondeo',
  `origen` ENUM ('manual', 'aumento_masivo') NOT NULL DEFAULT 'manual',
  `fecha_cambio` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `id_usuario` integer NOT NULL
);

CREATE TABLE `UNIDAD_MEDIDA` (
  `id_unidad` integer PRIMARY KEY AUTO_INCREMENT,
  `id_producto` integer NOT NULL,
  `nombre_unidad` varchar(50) NOT NULL,
  `equivalencia_base` integer NOT NULL DEFAULT 1,
  `es_base` boolean NOT NULL DEFAULT false,
  `descripcion` varchar(255)
);

CREATE TABLE `MOVIMIENTO_STOCK` (
  `id_movimiento` integer PRIMARY KEY AUTO_INCREMENT,
  `id_producto` integer NOT NULL,
  `id_unidad` integer,
  `tipo` ENUM ('ingreso', 'venta', 'devolucion', 'ajuste') NOT NULL,
  `cantidad` integer NOT NULL,
  `cantidad_base` integer,
  `fecha` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `motivo` varchar(255),
  `id_usuario` integer NOT NULL,
  `id_venta` integer,
  `id_entrega` integer,
  `id_recepcion` integer,
  `id_sesion_conteo` integer,
  `id_lote` integer
);

CREATE TABLE `LOTE` (
  `id_lote` integer PRIMARY KEY AUTO_INCREMENT,
  `id_producto` integer NOT NULL,
  `nro_lote` varchar(50) NOT NULL,
  `cantidad_inicial` integer NOT NULL,
  `cantidad_actual` integer NOT NULL,
  `fecha_vencimiento` date NOT NULL
);

CREATE TABLE `UBICACION` (
  `id_ubicacion` integer PRIMARY KEY AUTO_INCREMENT,
  `descripcion` varchar(100) UNIQUE NOT NULL COMMENT 'estante / pasillo / gondola',
  `estado` ENUM ('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  `fecha_creacion` date
);

CREATE TABLE `PRODUCTO_UBICACION` (
  `id_producto` integer NOT NULL,
  `id_ubicacion` integer NOT NULL,
  `fecha_asignacion` date,
  `id_usuario` integer,
  PRIMARY KEY (`id_producto`, `id_ubicacion`)
);

CREATE TABLE `SESION_CONTEO` (
  `id_sesion` integer PRIMARY KEY AUTO_INCREMENT,
  `estado` ENUM ('en_proceso', 'finalizado') NOT NULL DEFAULT 'en_proceso',
  `fecha_conteo` date,
  `observaciones` text,
  `total_productos` integer DEFAULT 0,
  `total_diferencias` integer DEFAULT 0,
  `id_usuario` integer NOT NULL
);

CREATE TABLE `DETALLE_CONTEO` (
  `id_detalle_conteo` integer PRIMARY KEY AUTO_INCREMENT,
  `id_sesion` integer NOT NULL,
  `id_producto` integer NOT NULL,
  `cantidad_fisica` integer,
  `stock_sistema` integer,
  `diferencia` integer,
  `observaciones` varchar(255)
);

CREATE TABLE `PROVEEDOR` (
  `id_proveedor` integer PRIMARY KEY AUTO_INCREMENT,
  `razon_social` varchar(160) NOT NULL,
  `CUIT` varchar(20) UNIQUE NOT NULL,
  `telefono` varchar(30),
  `email` varchar(160),
  `direccion` varchar(255),
  `estado` ENUM ('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  `plazo_entrega_dias` integer,
  `fecha_alta` date NOT NULL,
  `fecha_modificacion` date,
  `fecha_desactivacion` date,
  `id_usuario_carga` integer NOT NULL,
  `id_usuario_modificacion` integer
);

CREATE TABLE `PRODUCTO_PROVEEDOR` (
  `id_producto_proveedor` integer PRIMARY KEY AUTO_INCREMENT,
  `id_producto` integer NOT NULL,
  `id_proveedor` integer NOT NULL,
  `es_proveedor_principal` boolean NOT NULL DEFAULT false,
  `precio_acordado` decimal(12,2),
  `id_unidad` integer,
  `activo` boolean NOT NULL DEFAULT true,
  `fecha_asociacion` date,
  `fecha_desasociacion` date,
  `id_usuario` integer
);

CREATE TABLE `ORDEN_COMPRA` (
  `id_orden` integer PRIMARY KEY AUTO_INCREMENT,
  `numero_orden` varchar(30) UNIQUE NOT NULL,
  `id_proveedor` integer NOT NULL,
  `total_estimado` decimal(12,2),
  `estado` ENUM ('pendiente', 'enviada', 'recibida_parcialmente', 'completada', 'cancelada') NOT NULL DEFAULT 'pendiente',
  `fecha_creacion` date NOT NULL,
  `fecha_modificacion` date,
  `fecha_envio` date,
  `fecha_entrega_estimada` date,
  `fecha_cancelacion` date,
  `id_usuario` integer NOT NULL
);

CREATE TABLE `DETALLE_ORDEN` (
  `id_detalle_orden` integer PRIMARY KEY AUTO_INCREMENT,
  `id_orden` integer NOT NULL,
  `id_producto` integer NOT NULL,
  `id_unidad` integer NOT NULL,
  `cantidad_solicitada` integer NOT NULL,
  `cantidad_sugerida` integer,
  `origen` ENUM ('manual', 'sugerencia') NOT NULL DEFAULT 'manual',
  `precio_estimado` decimal(12,2),
  `subtotal` decimal(12,2)
);

CREATE TABLE `COMPRA` (
  `id_compra` integer PRIMARY KEY AUTO_INCREMENT,
  `numero_compra` varchar(30) UNIQUE NOT NULL,
  `numero_comprobante` varchar(40),
  `id_proveedor` integer NOT NULL,
  `id_orden` integer,
  `importe_total` decimal(12,2),
  `saldo_pendiente` decimal(12,2),
  `estado` ENUM ('pendiente', 'parcialmente_recibida', 'completada', 'cancelada') NOT NULL DEFAULT 'pendiente',
  `fecha_compra` date NOT NULL,
  `fecha_vencimiento` date,
  `fecha_cancelacion` date,
  `fecha_modificacion` date,
  `id_usuario` integer NOT NULL,
  `id_usuario_modificacion` integer
);

CREATE TABLE `DETALLE_COMPRA` (
  `id_detalle_compra` integer PRIMARY KEY AUTO_INCREMENT,
  `id_compra` integer NOT NULL,
  `id_producto` integer NOT NULL,
  `id_unidad` integer NOT NULL,
  `cantidad` integer NOT NULL,
  `cantidad_recibida` integer NOT NULL DEFAULT 0,
  `precio_unitario` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2)
);

CREATE TABLE `RECEPCION` (
  `id_recepcion` integer PRIMARY KEY AUTO_INCREMENT,
  `id_compra` integer,
  `id_proveedor` integer NOT NULL,
  `fecha_recepcion` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `id_usuario` integer NOT NULL
);

CREATE TABLE `DETALLE_RECEPCION` (
  `id_det_rec` integer PRIMARY KEY AUTO_INCREMENT,
  `id_recepcion` integer NOT NULL,
  `id_detalle_compra` integer,
  `id_producto` integer NOT NULL,
  `id_lote` integer,
  `id_unidad` integer NOT NULL,
  `cantidad_recibida` integer NOT NULL
);

CREATE TABLE `PAGO_PROVEEDOR` (
  `id_pago` integer PRIMARY KEY AUTO_INCREMENT,
  `id_compra` integer NOT NULL,
  `metodo_pago` ENUM ('efectivo', 'transferencia', 'cheque', 'echeq', 'tarjeta') NOT NULL,
  `importe` decimal(12,2) NOT NULL,
  `fecha_pago` date NOT NULL,
  `id_usuario` integer NOT NULL
);

CREATE TABLE `HISTORIAL_PRECIO_PROVEEDOR` (
  `id_historial_prov` integer PRIMARY KEY AUTO_INCREMENT,
  `id_producto_proveedor` integer NOT NULL,
  `id_proveedor` integer NOT NULL,
  `id_producto` integer NOT NULL,
  `id_compra` integer,
  `id_unidad` integer,
  `precio_acordado` decimal(12,2) NOT NULL,
  `fecha_actualizacion` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `id_usuario` integer
);

CREATE TABLE `LOG_AUDITORIA` (
  `id_auditoria` integer PRIMARY KEY AUTO_INCREMENT,
  `tabla_afectada` string NOT NULL,
  `id_registro` integer NOT NULL,
  `accion` string NOT NULL COMMENT 'INSERT | UPDATE | DELETE',
  `valores_anteriores` json,
  `valores_nuevos` json,
  `id_usuario` integer,
  `fecha_hora` timestamp
);

CREATE TABLE `PROMOCION` (
  `id_promocion` integer PRIMARY KEY AUTO_INCREMENT,
  `nombre` string,
  `total` decimal,
  `vigencia_desde` date,
  `vigencia_hasta` date,
  `descripcion` string,
  `estado` boolean
);

CREATE TABLE `VENTA` (
  `id_venta` integer PRIMARY KEY AUTO_INCREMENT,
  `id_cliente` integer,
  `fecha` date,
  `total` decimal,
  `numFactura` string,
  `estado` ENUM ('pendiente', 'confirmada', 'pagada', 'facturada', 'cancelada'),
  `observaciones` string,
  `id_usuario` integer
);

CREATE TABLE `DETALLE_VENTA` (
  `id_detalle_venta` integer PRIMARY KEY AUTO_INCREMENT,
  `id_venta` integer,
  `id_producto` integer,
  `id_promocion` integer,
  `cantidad` integer,
  `precio_unitario` decimal,
  `descuento` decimal,
  `subtotal` decimal
);

CREATE TABLE `PROMOCION_PRODUCTO` (
  `id_promocion` integer,
  `id_producto` integer,
  `cantidad` integer,
  PRIMARY KEY (`id_promocion`, `id_producto`)
);

CREATE TABLE `ENTREGA` (
  `id_entrega` integer PRIMARY KEY AUTO_INCREMENT,
  `id_repartidor` integer,
  `fecha_creacion` date,
  `fecha_salida` date,
  `estado` string COMMENT 'pendiente | en_transito | entregada | no_entregada',
  `observaciones` string,
  `id_usuario_creacion` integer
);

CREATE TABLE `ENTREGA_PEDIDO` (
  `id_entrega` integer,
  `id_venta` integer,
  `estado_pedido_entega` string COMMENT 'pendiente | en_transito | entregada | no_entregada',
  PRIMARY KEY (`id_entrega`, `id_venta`)
);

CREATE TABLE `REMITO` (
  `id_remito` integer PRIMARY KEY AUTO_INCREMENT,
  `numero_remito` string UNIQUE,
  `id_entrega` integer,
  `id_venta` integer,
  `fecha_emision` datetime,
  `estado` string COMMENT 'enum',
  `observaciones` text
);

CREATE TABLE `DETALLE_REMITO` (
  `id_detalle_remito` integer PRIMARY KEY AUTO_INCREMENT,
  `id_remito` integer,
  `id_producto` integer,
  `descripcion` string,
  `cantidad` integer,
  `bultos` integer
);

CREATE TABLE `COBRO` (
  `id_cobro` integer PRIMARY KEY AUTO_INCREMENT,
  `id_cliente` integer,
  `id_usuario` integer,
  `fecha` datetime,
  `monto_total` decimal,
  `medio_pago` string COMMENT 'enum',
  `comprobante_nro` string,
  `estado` string COMMENT 'enum',
  `observaciones` text,
  `motivo_anulacion` text,
  `fecha_anulacion` datetime,
  `id_usuario_anulacion` integer
);

CREATE TABLE `COBRO_VENTA` (
  `id_cobro` integer,
  `id_venta` integer,
  `monto_aplicado` decimal,
  PRIMARY KEY (`id_cobro`, `id_venta`)
);

CREATE TABLE `CAJA_MOVIMIENTO` (
  `id_movimiento_caja` integer PRIMARY KEY AUTO_INCREMENT,
  `fecha` date,
  `monto` decimal,
  `tipo` string,
  `concepto` string,
  `id_usuario` integer,
  `id_cobro` integer COMMENT 'Propuesto en esquema integral'
);

CREATE TABLE `RENDICION` (
  `id_rendicion` integer PRIMARY KEY AUTO_INCREMENT,
  `id_reparto` integer,
  `id_repartidor` integer,
  `fecha_rendicion` datetime,
  `total_rendido` decimal,
  `estado` string COMMENT 'enum',
  `id_usuario_validador` integer,
  `fecha_validacion` datetime,
  `motivo_rechazo` text,
  `observaciones` text
);

CREATE TABLE `RENDICION_COBRO` (
  `id_rendicion_cobro` integer PRIMARY KEY AUTO_INCREMENT,
  `id_rendicion` integer,
  `id_cliente` integer,
  `id_factura` integer,
  `monto` decimal,
  `medio_pago` string COMMENT 'enum',
  `fecha_registro` datetime
);

CREATE TABLE `RENDICION_REMITO` (
  `id_rendicion` integer,
  `id_remito` integer,
  PRIMARY KEY (`id_rendicion`, `id_remito`)
);

CREATE TABLE `RENDICION_DIFERENCIA` (
  `id_diferencia` integer PRIMARY KEY AUTO_INCREMENT,
  `id_rendicion` integer,
  `total_esperado` decimal,
  `total_rendido` decimal,
  `diferencia` decimal,
  `tipo_diferencia` string COMMENT 'enum',
  `motivo` text,
  `observaciones` text,
  `diferencia_pendiente` boolean,
  `fecha_auditoria` datetime
);

CREATE TABLE `RENDICION_DEVOLUCION` (
  `id_devolucion` integer PRIMARY KEY AUTO_INCREMENT,
  `id_rendicion` integer,
  `id_pedido` integer,
  `motivo` string,
  `observaciones` text,
  `estado_entrega` string,
  `fecha_registro` datetime
);

CREATE INDEX `idx_cliente_creado_por` ON `CLIENTE` (`creado_por`);

CREATE INDEX `idx_cliente_id_zona` ON `CLIENTE` (`id_zona`);

CREATE INDEX `idx_login_log_usuario_id` ON `LOGIN_LOG` (`usuario_id`);

CREATE INDEX `idx_reclamo_cliente_id` ON `RECLAMO` (`cliente_id`);

CREATE INDEX `idx_reclamo_usuario_id` ON `RECLAMO` (`usuario_id`);

CREATE INDEX `idx_reclamo_estado` ON `RECLAMO` (`estado`);

CREATE INDEX `idx_producto_categoria` ON `PRODUCTO` (`id_categoria`);

CREATE INDEX `idx_producto_marca` ON `PRODUCTO` (`id_marca`);

CREATE INDEX `idx_producto_nombre` ON `PRODUCTO` (`nombre`);

CREATE INDEX `idx_historial_prod_fecha` ON `HISTORIAL_PRECIO` (`id_producto`, `fecha_cambio`);

CREATE INDEX `idx_historial_usuario` ON `HISTORIAL_PRECIO` (`id_usuario`);

CREATE UNIQUE INDEX `uq_unidad_producto_nombre` ON `UNIDAD_MEDIDA` (`id_producto`, `nombre_unidad`);

CREATE INDEX `idx_unidad_producto` ON `UNIDAD_MEDIDA` (`id_producto`);

CREATE INDEX `idx_mov_stock_producto_fecha` ON `MOVIMIENTO_STOCK` (`id_producto`, `fecha`);

CREATE INDEX `idx_mov_stock_tipo` ON `MOVIMIENTO_STOCK` (`tipo`);

CREATE INDEX `idx_mov_stock_lote` ON `MOVIMIENTO_STOCK` (`id_lote`);

CREATE INDEX `idx_mov_stock_recepcion` ON `MOVIMIENTO_STOCK` (`id_recepcion`);

CREATE UNIQUE INDEX `uq_lote_producto_nro` ON `LOTE` (`id_producto`, `nro_lote`);

CREATE INDEX `idx_lote_producto_vencimiento` ON `LOTE` (`id_producto`, `fecha_vencimiento`);

CREATE INDEX `idx_prod_ubic_ubicacion` ON `PRODUCTO_UBICACION` (`id_ubicacion`);

CREATE INDEX `idx_sesion_conteo_usuario` ON `SESION_CONTEO` (`id_usuario`);

CREATE UNIQUE INDEX `uq_conteo_sesion_prod` ON `DETALLE_CONTEO` (`id_sesion`, `id_producto`);

CREATE INDEX `idx_detalle_conteo_prod` ON `DETALLE_CONTEO` (`id_producto`);

CREATE INDEX `idx_proveedor_razon` ON `PROVEEDOR` (`razon_social`);

CREATE INDEX `idx_prod_prov_producto` ON `PRODUCTO_PROVEEDOR` (`id_producto`);

CREATE INDEX `idx_prod_prov_proveedor` ON `PRODUCTO_PROVEEDOR` (`id_proveedor`);

CREATE INDEX `idx_orden_proveedor` ON `ORDEN_COMPRA` (`id_proveedor`);

CREATE INDEX `idx_orden_estado` ON `ORDEN_COMPRA` (`estado`);

CREATE UNIQUE INDEX `uq_detalle_orden_prod_unidad` ON `DETALLE_ORDEN` (`id_orden`, `id_producto`, `id_unidad`);

CREATE INDEX `idx_det_orden_producto` ON `DETALLE_ORDEN` (`id_producto`);

CREATE UNIQUE INDEX `uq_compra_prov_comprobante` ON `COMPRA` (`id_proveedor`, `numero_comprobante`);

CREATE INDEX `idx_compra_proveedor_fecha` ON `COMPRA` (`id_proveedor`, `fecha_compra`);

CREATE INDEX `idx_compra_orden` ON `COMPRA` (`id_orden`);

CREATE UNIQUE INDEX `uq_det_compra_prod_unidad` ON `DETALLE_COMPRA` (`id_compra`, `id_producto`, `id_unidad`);

CREATE INDEX `idx_det_compra_producto` ON `DETALLE_COMPRA` (`id_producto`);

CREATE INDEX `idx_recepcion_compra` ON `RECEPCION` (`id_compra`);

CREATE INDEX `idx_recepcion_proveedor` ON `RECEPCION` (`id_proveedor`);

CREATE INDEX `idx_det_rec_recepcion` ON `DETALLE_RECEPCION` (`id_recepcion`);

CREATE INDEX `idx_det_rec_det_compra` ON `DETALLE_RECEPCION` (`id_detalle_compra`);

CREATE INDEX `idx_det_rec_producto` ON `DETALLE_RECEPCION` (`id_producto`);

CREATE INDEX `idx_det_rec_lote` ON `DETALLE_RECEPCION` (`id_lote`);

CREATE INDEX `idx_pago_compra` ON `PAGO_PROVEEDOR` (`id_compra`);

CREATE INDEX `idx_pago_usuario` ON `PAGO_PROVEEDOR` (`id_usuario`);

CREATE INDEX `idx_hist_prov_prod_prov` ON `HISTORIAL_PRECIO_PROVEEDOR` (`id_producto_proveedor`);

CREATE INDEX `idx_hist_prov_fecha` ON `HISTORIAL_PRECIO_PROVEEDOR` (`fecha_actualizacion`);

ALTER TABLE `ZONA` COMMENT = 'Entidad del diagrama general. La asociacion con CLIENTE queda disponible para la integracion con zonas.';

ALTER TABLE `USUARIO` COMMENT = 'Usuarios internos del ERP. No es una subtabla de CLIENTE. Incluye bloqueo de acceso y sesion unica.';

ALTER TABLE `PASSWORD_RESET_CODE` COMMENT = 'Codigo temporal hasheado para recuperar la contrasena. Cada usuario puede tener un solo codigo vigente.';

ALTER TABLE `CLIENTE` COMMENT = 'Propuesta G1: DNI y CUIT separados; al menos uno informado. Condicion IVA nullable segun catalogo ARCA. Pendiente de implementar en MySQL y API.';

ALTER TABLE `LOGIN_LOG` COMMENT = 'Historial de inicios de sesion exitosos.';

ALTER TABLE `RECLAMO` COMMENT = 'Reclamos cargados por un repartidor o un administrador y asociados a un cliente.';

ALTER TABLE `PRODUCTO` COMMENT = 'Catalogo maestro de articulos. Precio unitario de lista base y stock embebido. En MySQL tiene CONSTRAINT chk_producto_stock_no_negativo CHECK (stock >= 0).';

ALTER TABLE `HISTORIAL_PRECIO` COMMENT = 'Trazabilidad de precios. Guarda unicamente precio resultante (OB1)';

ALTER TABLE `UNIDAD_MEDIDA` COMMENT = 'Unidades y equivalencias relativas a la unidad base (S08)';

ALTER TABLE `MOVIMIENTO_STOCK` COMMENT = 'Libro kardex de auditoria de inventario (S12)';

ALTER TABLE `LOTE` COMMENT = 'Lotes con vencimiento en unidad base. Rompe referencia circular (S09)';

ALTER TABLE `UBICACION` COMMENT = 'Sectores/pasillos del deposito fisico (S14)';

ALTER TABLE `COMPRA` COMMENT = 'Compras a proveedores. numero_compra es correlativo interno; numero_comprobante es del proveedor';

ALTER TABLE `RECEPCION` COMMENT = 'Recepcion de mercaderia. id_compra nullable permite ingreso directo sin compra previa (S03)';

ALTER TABLE `CLIENTE` ADD FOREIGN KEY (`id_zona`) REFERENCES `ZONA` (`id_zona`);

ALTER TABLE `CLIENTE` ADD FOREIGN KEY (`creado_por`) REFERENCES `USUARIO` (`id_usuario`);

ALTER TABLE `PASSWORD_RESET_CODE` ADD FOREIGN KEY (`usuario_id`) REFERENCES `USUARIO` (`id_usuario`) ON UPDATE CASCADE ON DELETE CASCADE;

ALTER TABLE `LOGIN_LOG` ADD FOREIGN KEY (`usuario_id`) REFERENCES `USUARIO` (`id_usuario`);

ALTER TABLE `RECLAMO` ADD FOREIGN KEY (`cliente_id`) REFERENCES `CLIENTE` (`id_cliente`);

ALTER TABLE `RECLAMO` ADD FOREIGN KEY (`usuario_id`) REFERENCES `USUARIO` (`id_usuario`);

ALTER TABLE `PRODUCTO` ADD CONSTRAINT `fk_producto_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `CATEGORIA` (`id_categoria`) ON DELETE RESTRICT;

ALTER TABLE `PRODUCTO` ADD CONSTRAINT `fk_producto_marca` FOREIGN KEY (`id_marca`) REFERENCES `MARCA` (`id_marca`) ON DELETE RESTRICT;

ALTER TABLE `PRODUCTO` ADD CONSTRAINT `fk_producto_usuario_carga` FOREIGN KEY (`id_usuario_carga`) REFERENCES `USUARIO` (`id_usuario`) ON DELETE RESTRICT;

ALTER TABLE `PRODUCTO` ADD CONSTRAINT `fk_producto_usuario_mod` FOREIGN KEY (`id_usuario_modificacion`) REFERENCES `USUARIO` (`id_usuario`) ON DELETE SET NULL;

ALTER TABLE `HISTORIAL_PRECIO` ADD CONSTRAINT `fk_hist_precio_producto` FOREIGN KEY (`id_producto`) REFERENCES `PRODUCTO` (`id_producto`) ON DELETE CASCADE;

ALTER TABLE `HISTORIAL_PRECIO` ADD CONSTRAINT `fk_hist_precio_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `USUARIO` (`id_usuario`) ON DELETE RESTRICT;

ALTER TABLE `UNIDAD_MEDIDA` ADD CONSTRAINT `fk_unidad_producto` FOREIGN KEY (`id_producto`) REFERENCES `PRODUCTO` (`id_producto`) ON DELETE CASCADE;

ALTER TABLE `LOTE` ADD CONSTRAINT `fk_lote_producto` FOREIGN KEY (`id_producto`) REFERENCES `PRODUCTO` (`id_producto`) ON DELETE RESTRICT;

ALTER TABLE `PRODUCTO_UBICACION` ADD CONSTRAINT `fk_prod_ubic_producto` FOREIGN KEY (`id_producto`) REFERENCES `PRODUCTO` (`id_producto`) ON DELETE CASCADE;

ALTER TABLE `PRODUCTO_UBICACION` ADD CONSTRAINT `fk_prod_ubic_ubicacion` FOREIGN KEY (`id_ubicacion`) REFERENCES `UBICACION` (`id_ubicacion`) ON DELETE CASCADE;

ALTER TABLE `PRODUCTO_UBICACION` ADD CONSTRAINT `fk_prod_ubic_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `USUARIO` (`id_usuario`) ON DELETE SET NULL;

ALTER TABLE `SESION_CONTEO` ADD CONSTRAINT `fk_conteo_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `USUARIO` (`id_usuario`) ON DELETE RESTRICT;

ALTER TABLE `DETALLE_CONTEO` ADD CONSTRAINT `fk_det_conteo_sesion` FOREIGN KEY (`id_sesion`) REFERENCES `SESION_CONTEO` (`id_sesion`) ON DELETE CASCADE;

ALTER TABLE `DETALLE_CONTEO` ADD CONSTRAINT `fk_det_conteo_producto` FOREIGN KEY (`id_producto`) REFERENCES `PRODUCTO` (`id_producto`) ON DELETE RESTRICT;

ALTER TABLE `PROVEEDOR` ADD CONSTRAINT `fk_proveedor_usuario_carga` FOREIGN KEY (`id_usuario_carga`) REFERENCES `USUARIO` (`id_usuario`) ON DELETE RESTRICT;

ALTER TABLE `PROVEEDOR` ADD CONSTRAINT `fk_proveedor_usuario_mod` FOREIGN KEY (`id_usuario_modificacion`) REFERENCES `USUARIO` (`id_usuario`) ON DELETE SET NULL;

ALTER TABLE `PRODUCTO_PROVEEDOR` ADD CONSTRAINT `fk_prod_prov_producto` FOREIGN KEY (`id_producto`) REFERENCES `PRODUCTO` (`id_producto`) ON DELETE CASCADE;

ALTER TABLE `PRODUCTO_PROVEEDOR` ADD CONSTRAINT `fk_prod_prov_proveedor` FOREIGN KEY (`id_proveedor`) REFERENCES `PROVEEDOR` (`id_proveedor`) ON DELETE CASCADE;

ALTER TABLE `PRODUCTO_PROVEEDOR` ADD CONSTRAINT `fk_prod_prov_unidad` FOREIGN KEY (`id_unidad`) REFERENCES `UNIDAD_MEDIDA` (`id_unidad`) ON DELETE SET NULL;

ALTER TABLE `PRODUCTO_PROVEEDOR` ADD CONSTRAINT `fk_prod_prov_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `USUARIO` (`id_usuario`) ON DELETE SET NULL;

ALTER TABLE `HISTORIAL_PRECIO_PROVEEDOR` ADD CONSTRAINT `fk_hist_prov_prod_prov` FOREIGN KEY (`id_producto_proveedor`) REFERENCES `PRODUCTO_PROVEEDOR` (`id_producto_proveedor`) ON DELETE CASCADE;

ALTER TABLE `HISTORIAL_PRECIO_PROVEEDOR` ADD CONSTRAINT `fk_hist_prov_proveedor` FOREIGN KEY (`id_proveedor`) REFERENCES `PROVEEDOR` (`id_proveedor`) ON DELETE RESTRICT;

ALTER TABLE `HISTORIAL_PRECIO_PROVEEDOR` ADD CONSTRAINT `fk_hist_prov_producto` FOREIGN KEY (`id_producto`) REFERENCES `PRODUCTO` (`id_producto`) ON DELETE RESTRICT;

ALTER TABLE `HISTORIAL_PRECIO_PROVEEDOR` ADD CONSTRAINT `fk_hist_prov_compra` FOREIGN KEY (`id_compra`) REFERENCES `COMPRA` (`id_compra`) ON DELETE SET NULL;

ALTER TABLE `HISTORIAL_PRECIO_PROVEEDOR` ADD CONSTRAINT `fk_hist_prov_unidad` FOREIGN KEY (`id_unidad`) REFERENCES `UNIDAD_MEDIDA` (`id_unidad`) ON DELETE SET NULL;

ALTER TABLE `HISTORIAL_PRECIO_PROVEEDOR` ADD CONSTRAINT `fk_hist_prov_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `USUARIO` (`id_usuario`) ON DELETE SET NULL;

ALTER TABLE `ORDEN_COMPRA` ADD CONSTRAINT `fk_orden_proveedor` FOREIGN KEY (`id_proveedor`) REFERENCES `PROVEEDOR` (`id_proveedor`) ON DELETE RESTRICT;

ALTER TABLE `ORDEN_COMPRA` ADD CONSTRAINT `fk_orden_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `USUARIO` (`id_usuario`) ON DELETE RESTRICT;

ALTER TABLE `DETALLE_ORDEN` ADD CONSTRAINT `fk_det_orden_orden` FOREIGN KEY (`id_orden`) REFERENCES `ORDEN_COMPRA` (`id_orden`) ON DELETE CASCADE;

ALTER TABLE `DETALLE_ORDEN` ADD CONSTRAINT `fk_det_orden_producto` FOREIGN KEY (`id_producto`) REFERENCES `PRODUCTO` (`id_producto`) ON DELETE RESTRICT;

ALTER TABLE `DETALLE_ORDEN` ADD CONSTRAINT `fk_det_orden_unidad` FOREIGN KEY (`id_unidad`) REFERENCES `UNIDAD_MEDIDA` (`id_unidad`) ON DELETE RESTRICT;

ALTER TABLE `COMPRA` ADD CONSTRAINT `fk_compra_proveedor` FOREIGN KEY (`id_proveedor`) REFERENCES `PROVEEDOR` (`id_proveedor`) ON DELETE RESTRICT;

ALTER TABLE `COMPRA` ADD CONSTRAINT `fk_compra_orden` FOREIGN KEY (`id_orden`) REFERENCES `ORDEN_COMPRA` (`id_orden`) ON DELETE SET NULL;

ALTER TABLE `COMPRA` ADD CONSTRAINT `fk_compra_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `USUARIO` (`id_usuario`) ON DELETE RESTRICT;

ALTER TABLE `COMPRA` ADD CONSTRAINT `fk_compra_usuario_mod` FOREIGN KEY (`id_usuario_modificacion`) REFERENCES `USUARIO` (`id_usuario`) ON DELETE SET NULL;

ALTER TABLE `DETALLE_COMPRA` ADD CONSTRAINT `fk_det_compra_compra` FOREIGN KEY (`id_compra`) REFERENCES `COMPRA` (`id_compra`) ON DELETE CASCADE;

ALTER TABLE `DETALLE_COMPRA` ADD CONSTRAINT `fk_det_compra_producto` FOREIGN KEY (`id_producto`) REFERENCES `PRODUCTO` (`id_producto`) ON DELETE RESTRICT;

ALTER TABLE `DETALLE_COMPRA` ADD CONSTRAINT `fk_det_compra_unidad` FOREIGN KEY (`id_unidad`) REFERENCES `UNIDAD_MEDIDA` (`id_unidad`) ON DELETE RESTRICT;

ALTER TABLE `RECEPCION` ADD CONSTRAINT `fk_recepcion_compra` FOREIGN KEY (`id_compra`) REFERENCES `COMPRA` (`id_compra`) ON DELETE SET NULL;

ALTER TABLE `RECEPCION` ADD CONSTRAINT `fk_recepcion_proveedor` FOREIGN KEY (`id_proveedor`) REFERENCES `PROVEEDOR` (`id_proveedor`) ON DELETE RESTRICT;

ALTER TABLE `RECEPCION` ADD CONSTRAINT `fk_recepcion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `USUARIO` (`id_usuario`) ON DELETE RESTRICT;

ALTER TABLE `DETALLE_RECEPCION` ADD CONSTRAINT `fk_det_rec_recepcion` FOREIGN KEY (`id_recepcion`) REFERENCES `RECEPCION` (`id_recepcion`) ON DELETE CASCADE;

ALTER TABLE `DETALLE_RECEPCION` ADD CONSTRAINT `fk_det_rec_det_compra` FOREIGN KEY (`id_detalle_compra`) REFERENCES `DETALLE_COMPRA` (`id_detalle_compra`) ON DELETE SET NULL;

ALTER TABLE `DETALLE_RECEPCION` ADD CONSTRAINT `fk_det_rec_producto` FOREIGN KEY (`id_producto`) REFERENCES `PRODUCTO` (`id_producto`) ON DELETE RESTRICT;

ALTER TABLE `DETALLE_RECEPCION` ADD CONSTRAINT `fk_det_rec_lote` FOREIGN KEY (`id_lote`) REFERENCES `LOTE` (`id_lote`) ON DELETE SET NULL;

ALTER TABLE `DETALLE_RECEPCION` ADD CONSTRAINT `fk_det_rec_unidad` FOREIGN KEY (`id_unidad`) REFERENCES `UNIDAD_MEDIDA` (`id_unidad`) ON DELETE RESTRICT;

ALTER TABLE `PAGO_PROVEEDOR` ADD CONSTRAINT `fk_pago_compra` FOREIGN KEY (`id_compra`) REFERENCES `COMPRA` (`id_compra`) ON DELETE CASCADE;

ALTER TABLE `PAGO_PROVEEDOR` ADD CONSTRAINT `fk_pago_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `USUARIO` (`id_usuario`) ON DELETE RESTRICT;

ALTER TABLE `MOVIMIENTO_STOCK` ADD CONSTRAINT `fk_mov_stock_producto` FOREIGN KEY (`id_producto`) REFERENCES `PRODUCTO` (`id_producto`) ON DELETE RESTRICT;

ALTER TABLE `MOVIMIENTO_STOCK` ADD CONSTRAINT `fk_mov_stock_unidad` FOREIGN KEY (`id_unidad`) REFERENCES `UNIDAD_MEDIDA` (`id_unidad`) ON DELETE SET NULL;

ALTER TABLE `MOVIMIENTO_STOCK` ADD CONSTRAINT `fk_mov_stock_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `USUARIO` (`id_usuario`) ON DELETE RESTRICT;

ALTER TABLE `MOVIMIENTO_STOCK` ADD CONSTRAINT `fk_mov_stock_venta` FOREIGN KEY (`id_venta`) REFERENCES `VENTA` (`id_venta`) ON DELETE SET NULL;

ALTER TABLE `MOVIMIENTO_STOCK` ADD CONSTRAINT `fk_mov_stock_entrega` FOREIGN KEY (`id_entrega`) REFERENCES `ENTREGA` (`id_entrega`) ON DELETE SET NULL;

ALTER TABLE `MOVIMIENTO_STOCK` ADD CONSTRAINT `fk_mov_stock_recepcion` FOREIGN KEY (`id_recepcion`) REFERENCES `RECEPCION` (`id_recepcion`) ON DELETE SET NULL;

ALTER TABLE `MOVIMIENTO_STOCK` ADD CONSTRAINT `fk_mov_stock_conteo` FOREIGN KEY (`id_sesion_conteo`) REFERENCES `SESION_CONTEO` (`id_sesion`) ON DELETE SET NULL;

ALTER TABLE `MOVIMIENTO_STOCK` ADD CONSTRAINT `fk_mov_stock_lote` FOREIGN KEY (`id_lote`) REFERENCES `LOTE` (`id_lote`) ON DELETE SET NULL;

ALTER TABLE `LOG_AUDITORIA` ADD FOREIGN KEY (`id_usuario`) REFERENCES `USUARIO` (`id_usuario`);

ALTER TABLE `VENTA` ADD FOREIGN KEY (`id_cliente`) REFERENCES `CLIENTE` (`id_cliente`);

ALTER TABLE `VENTA` ADD FOREIGN KEY (`id_usuario`) REFERENCES `USUARIO` (`id_usuario`);

ALTER TABLE `DETALLE_VENTA` ADD FOREIGN KEY (`id_venta`) REFERENCES `VENTA` (`id_venta`);

ALTER TABLE `DETALLE_VENTA` ADD FOREIGN KEY (`id_producto`) REFERENCES `PRODUCTO` (`id_producto`);

ALTER TABLE `DETALLE_VENTA` ADD FOREIGN KEY (`id_promocion`) REFERENCES `PROMOCION` (`id_promocion`);

ALTER TABLE `PROMOCION_PRODUCTO` ADD FOREIGN KEY (`id_promocion`) REFERENCES `PROMOCION` (`id_promocion`);

ALTER TABLE `PROMOCION_PRODUCTO` ADD FOREIGN KEY (`id_producto`) REFERENCES `PRODUCTO` (`id_producto`);

ALTER TABLE `ENTREGA` ADD FOREIGN KEY (`id_repartidor`) REFERENCES `USUARIO` (`id_usuario`);

ALTER TABLE `ENTREGA` ADD FOREIGN KEY (`id_usuario_creacion`) REFERENCES `USUARIO` (`id_usuario`);

ALTER TABLE `ENTREGA_PEDIDO` ADD FOREIGN KEY (`id_entrega`) REFERENCES `ENTREGA` (`id_entrega`);

ALTER TABLE `ENTREGA_PEDIDO` ADD FOREIGN KEY (`id_venta`) REFERENCES `VENTA` (`id_venta`);

ALTER TABLE `REMITO` ADD FOREIGN KEY (`id_entrega`) REFERENCES `ENTREGA` (`id_entrega`);

ALTER TABLE `REMITO` ADD FOREIGN KEY (`id_venta`) REFERENCES `VENTA` (`id_venta`);

ALTER TABLE `DETALLE_REMITO` ADD FOREIGN KEY (`id_remito`) REFERENCES `REMITO` (`id_remito`);

ALTER TABLE `DETALLE_REMITO` ADD FOREIGN KEY (`id_producto`) REFERENCES `PRODUCTO` (`id_producto`);

ALTER TABLE `COBRO` ADD FOREIGN KEY (`id_cliente`) REFERENCES `CLIENTE` (`id_cliente`);

ALTER TABLE `COBRO` ADD FOREIGN KEY (`id_usuario`) REFERENCES `USUARIO` (`id_usuario`);

ALTER TABLE `COBRO` ADD FOREIGN KEY (`id_usuario_anulacion`) REFERENCES `USUARIO` (`id_usuario`);

ALTER TABLE `COBRO_VENTA` ADD FOREIGN KEY (`id_cobro`) REFERENCES `COBRO` (`id_cobro`);

ALTER TABLE `COBRO_VENTA` ADD FOREIGN KEY (`id_venta`) REFERENCES `VENTA` (`id_venta`);

ALTER TABLE `CAJA_MOVIMIENTO` ADD FOREIGN KEY (`id_usuario`) REFERENCES `USUARIO` (`id_usuario`);

ALTER TABLE `CAJA_MOVIMIENTO` ADD FOREIGN KEY (`id_cobro`) REFERENCES `COBRO` (`id_cobro`);

ALTER TABLE `RENDICION` ADD FOREIGN KEY (`id_repartidor`) REFERENCES `USUARIO` (`id_usuario`);

ALTER TABLE `RENDICION` ADD FOREIGN KEY (`id_usuario_validador`) REFERENCES `USUARIO` (`id_usuario`);

ALTER TABLE `RENDICION_COBRO` ADD FOREIGN KEY (`id_rendicion`) REFERENCES `RENDICION` (`id_rendicion`);

ALTER TABLE `RENDICION_COBRO` ADD FOREIGN KEY (`id_cliente`) REFERENCES `CLIENTE` (`id_cliente`);

ALTER TABLE `RENDICION_REMITO` ADD FOREIGN KEY (`id_rendicion`) REFERENCES `RENDICION` (`id_rendicion`);

ALTER TABLE `RENDICION_REMITO` ADD FOREIGN KEY (`id_remito`) REFERENCES `REMITO` (`id_remito`);

ALTER TABLE `RENDICION_DIFERENCIA` ADD FOREIGN KEY (`id_rendicion`) REFERENCES `RENDICION` (`id_rendicion`);

ALTER TABLE `RENDICION_DEVOLUCION` ADD FOREIGN KEY (`id_rendicion`) REFERENCES `RENDICION` (`id_rendicion`);
