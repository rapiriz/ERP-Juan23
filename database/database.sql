CREATE DATABASE IF NOT EXISTS sistema_gestion
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sistema_gestion;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    rol ENUM('administrativo', 'vendedor') NOT NULL,
    estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
    intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta DATETIME NULL,
    ultimo_intento_fallido DATETIME NULL,
    current_session_id VARCHAR(128) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuarios_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    apellido_razon_social VARCHAR(160) NOT NULL,
    dni_cuit VARCHAR(20) NOT NULL,
    telefono VARCHAR(30) NOT NULL,
    email VARCHAR(160) NOT NULL,
    direccion VARCHAR(255) NOT NULL,
    tipo_cliente ENUM('minorista', 'mayorista') NOT NULL,
    estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
    creado_por INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_clientes_dni_cuit (dni_cuit),
    UNIQUE KEY uq_clientes_email (email),
    KEY idx_clientes_creado_por (creado_por),
    CONSTRAINT fk_clientes_creado_por
        FOREIGN KEY (creado_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip VARCHAR(45) NULL,
    KEY idx_login_logs_usuario_id (usuario_id),
    CONSTRAINT fk_login_logs_usuario_id
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reclamos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    asunto VARCHAR(160) NOT NULL,
    descripcion TEXT NOT NULL,
    prioridad ENUM('baja', 'media', 'alta') NOT NULL DEFAULT 'media',
    estado ENUM('abierto', 'en_proceso', 'cerrado') NOT NULL DEFAULT 'abierto',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_reclamos_cliente_id (cliente_id),
    KEY idx_reclamos_usuario_id (usuario_id),
    KEY idx_reclamos_estado (estado),
    CONSTRAINT fk_reclamos_cliente_id
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_reclamos_usuario_id
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO usuarios (usuario, password_hash, nombre, rol, estado)
VALUES
    ('admin', '$2y$12$ANsXNeCiggg9JGyVdCzvzOfdLWveLkj05ghYBjggIajDzzTYmpDI2', 'Usuario Administrativo', 'administrativo', 'activo'),
    ('vendedor', '$2y$12$THO53TWdVvSVBJ58Bwm4EeYXPfzT.RnuXMoOZNbRPhuoljXIKILY2', 'Usuario Vendedor', 'vendedor', 'activo'),
    ('inactivo', '$2y$12$hZwvCqPlVn2Fy0NxRpoeR.D0aBSd4sVi2.lZQZ361JjoECd.VJJda', 'Usuario Inactivo', 'vendedor', 'inactivo')
ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    nombre = VALUES(nombre),
    rol = VALUES(rol),
    estado = VALUES(estado);
