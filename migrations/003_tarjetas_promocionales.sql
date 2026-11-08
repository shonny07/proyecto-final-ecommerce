-- Ejecutar una sola vez sobre la base e_commerce existente.
CREATE TABLE tarjeta_promocional (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(120) NOT NULL,
  texto TEXT NOT NULL,
  imagen VARCHAR(255) NOT NULL,
  activa BOOLEAN NOT NULL DEFAULT TRUE,
  posicion INT NOT NULL DEFAULT 0,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
