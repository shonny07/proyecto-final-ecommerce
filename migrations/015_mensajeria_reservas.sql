-- Sistema de conversaciones internas entre las partes de una reserva.
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
