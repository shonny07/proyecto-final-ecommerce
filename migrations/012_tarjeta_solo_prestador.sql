-- La tarjeta pertenece al prestador y lleva a todos sus servicios.
ALTER TABLE tarjeta_presentacion
  DROP FOREIGN KEY fk_tarjeta_presentacion_servicio,
  DROP FOREIGN KEY fk_tarjeta_presentacion_rubro,
  DROP INDEX idx_tarjeta_presentacion_oferta,
  DROP INDEX fk_tarjeta_presentacion_rubro,
  DROP COLUMN id_servicio,
  DROP COLUMN rubro_id;
