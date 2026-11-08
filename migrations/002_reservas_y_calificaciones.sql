-- Ejecutar una sola vez sobre la base e_commerce existente.
ALTER TABLE reserva
  ADD COLUMN estado ENUM('pendiente', 'completada', 'cancelada') NOT NULL DEFAULT 'pendiente';

CREATE TABLE calificacion (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_reserva INT UNSIGNED NOT NULL UNIQUE,
  estrellas TINYINT UNSIGNED NOT NULL,
  comentario VARCHAR(500) NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_calificacion_estrellas CHECK (estrellas BETWEEN 1 AND 5),
  CONSTRAINT fk_calificacion_reserva FOREIGN KEY (id_reserva) REFERENCES reserva(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
