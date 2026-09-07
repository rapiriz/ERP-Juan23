-- Estructura base simulada para permitir la integridad referencial del módulo Ventas/Clientes
CREATE TABLE IF NOT EXISTS clientes (
    cliente_id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    direccion VARCHAR(255) NOT NULL,
    zona VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS pedidos (
    pedido_id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    fecha_pedido DATETIME NOT NULL,
    monto_total DECIMAL(10,2) NOT NULL,
    estado_pedido VARCHAR(30) DEFAULT 'confirmado',
    CONSTRAINT fk_pedidos_clientes FOREIGN KEY (cliente_id) REFERENCES clientes(cliente_id)
);

-- Tablas del Módulo Entregas (Grupo 2)
CREATE TABLE IF NOT EXISTS entregas (
    entrega_id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_repartidor_id INT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    usuario_creacion INT NOT NULL,
    usuario_modificacion INT NULL,
    estado VARCHAR(30) DEFAULT 'en_preparacion',
    CONSTRAINT chk_estado_entrega CHECK (estado IN ('en_preparacion', 'en_camino', 'finalizada'))
);

CREATE TABLE IF NOT EXISTS entregas_pedidos (
    entrega_id INT NOT NULL,
    pedido_id INT NOT NULL,
    PRIMARY KEY (entrega_id, pedido_id),
    CONSTRAINT fk_ep_entregas FOREIGN KEY (entrega_id) REFERENCES entregas(entrega_id) ON DELETE CASCADE,
    CONSTRAINT fk_ep_pedidos FOREIGN KEY (pedido_id) REFERENCES pedidos(pedido_id)
);