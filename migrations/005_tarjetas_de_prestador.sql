-- Ejecutar una sola vez sobre e_commerce. Cada prestador puede publicar una tarjeta.
CREATE TABLE tarjeta_presentacion (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_prestador INT UNSIGNED NOT NULL UNIQUE,
  rubro_id INT UNSIGNED NOT NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tarjeta_presentacion_prestador FOREIGN KEY (id_prestador) REFERENCES prestador(id) ON DELETE CASCADE,
  CONSTRAINT fk_tarjeta_presentacion_rubro FOREIGN KEY (rubro_id) REFERENCES categoria(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
