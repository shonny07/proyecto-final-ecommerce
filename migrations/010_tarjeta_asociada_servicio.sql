-- Asocia la tarjeta a una oferta específica del prestador.
ALTER TABLE tarjeta_presentacion
  ADD COLUMN id_servicio INT UNSIGNED NULL AFTER id_prestador,
  ADD INDEX idx_tarjeta_presentacion_oferta (id_prestador, id_servicio),
  ADD CONSTRAINT fk_tarjeta_presentacion_servicio FOREIGN KEY (id_prestador, id_servicio)
    REFERENCES prestador_servicio(id_prestador, id_servicio) ON DELETE CASCADE;
