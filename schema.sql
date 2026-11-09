-- Esquema inicial para la base e_commerce.
-- Importar en phpMyAdmin después de seleccionar la base de datos e_commerce.

CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rol VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL UNIQUE,
  id_rol INT UNSIGNED NOT NULL,
  password VARCHAR(255) NOT NULL,
  CONSTRAINT fk_usuario_rol FOREIGN KEY (id_rol) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categoria (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  rubro_padre INT UNSIGNED NULL,
  CONSTRAINT fk_categoria_padre FOREIGN KEY (rubro_padre) REFERENCES categoria(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prestador (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  monto DECIMAL(10,2) NULL,
  id_user INT UNSIGNED NOT NULL UNIQUE,
  contacto VARCHAR(255) NULL,
  horarios JSON NULL,
  bloqueos JSON NULL,
  CONSTRAINT fk_prestador_usuario FOREIGN KEY (id_user) REFERENCES usuario(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS servicio (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  rubro INT UNSIGNED NOT NULL,
  CONSTRAINT fk_servicio_categoria FOREIGN KEY (rubro) REFERENCES categoria(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relación muchos a muchos: varios prestadores pueden ofrecer un servicio.
CREATE TABLE IF NOT EXISTS prestador_servicio (
  id_prestador INT UNSIGNED NOT NULL,
  id_servicio INT UNSIGNED NOT NULL,
  precio DECIMAL(10,2) NOT NULL,
  tipo_tarifa ENUM('visita', 'por_hora', 'turno') NOT NULL,
  duracion_minutos SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  PRIMARY KEY (id_prestador, id_servicio),
  CONSTRAINT fk_ps_prestador FOREIGN KEY (id_prestador) REFERENCES prestador(id),
  CONSTRAINT fk_ps_servicio FOREIGN KEY (id_servicio) REFERENCES servicio(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reserva (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha_hora DATETIME NOT NULL,
  fecha_hora_fin DATETIME NOT NULL,
  id_servicio INT UNSIGNED NOT NULL,
  id_prestador INT UNSIGNED NOT NULL,
  id_cliente INT UNSIGNED NOT NULL,
  comentarios TEXT NULL,
  precio_acordado DECIMAL(10,2) NOT NULL,
  estado ENUM('pendiente', 'completada', 'cancelada') NOT NULL DEFAULT 'pendiente',
  CONSTRAINT fk_reserva_oferta FOREIGN KEY (id_prestador, id_servicio)
    REFERENCES prestador_servicio(id_prestador, id_servicio),
  CONSTRAINT fk_reserva_cliente FOREIGN KEY (id_cliente) REFERENCES usuario(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversacion (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_reserva INT UNSIGNED NOT NULL UNIQUE,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_conversacion_reserva FOREIGN KEY (id_reserva) REFERENCES reserva(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensaje (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_conversacion INT UNSIGNED NOT NULL,
  id_emisor INT UNSIGNED NOT NULL,
  contenido VARCHAR(4000) NOT NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  leido_en DATETIME NULL,
  INDEX idx_mensaje_conversacion_fecha (id_conversacion, fecha_creacion, id),
  INDEX idx_mensaje_emisor (id_emisor),
  CONSTRAINT fk_mensaje_conversacion FOREIGN KEY (id_conversacion) REFERENCES conversacion(id) ON DELETE CASCADE,
  CONSTRAINT fk_mensaje_emisor FOREIGN KEY (id_emisor) REFERENCES usuario(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calificacion (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_reserva INT UNSIGNED NOT NULL UNIQUE,
  estrellas TINYINT UNSIGNED NOT NULL,
  comentario VARCHAR(500) NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_calificacion_estrellas CHECK (estrellas BETWEEN 1 AND 5),
  CONSTRAINT fk_calificacion_reserva FOREIGN KEY (id_reserva) REFERENCES reserva(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tarjeta_presentacion (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_prestador INT UNSIGNED NOT NULL UNIQUE,
  imagen VARCHAR(255) NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tarjeta_presentacion_prestador FOREIGN KEY (id_prestador) REFERENCES prestador(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_reserva INT UNSIGNED NOT NULL,
  confirmacion BOOLEAN NOT NULL DEFAULT FALSE,
  estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
  proveedor VARCHAR(30) NOT NULL DEFAULT 'mercado_pago',
  preferencia_id VARCHAR(100) NULL UNIQUE,
  pago_externo_id VARCHAR(100) NULL UNIQUE,
  metodo_pago VARCHAR(80) NULL,
  monto DECIMAL(10,2) NOT NULL DEFAULT 0,
  moneda CHAR(3) NOT NULL DEFAULT 'ARS',
  detalle_estado VARCHAR(120) NULL,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pagos_reserva_estado (id_reserva, estado),
  CONSTRAINT fk_pago_reserva FOREIGN KEY (id_reserva) REFERENCES reserva(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (rol) VALUES ('Adm'), ('User'), ('prestador');
