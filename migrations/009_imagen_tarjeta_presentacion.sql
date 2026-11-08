-- Guarda una sola imagen de presentación por prestador.
ALTER TABLE tarjeta_presentacion
  ADD COLUMN imagen VARCHAR(255) NULL AFTER rubro_id;
