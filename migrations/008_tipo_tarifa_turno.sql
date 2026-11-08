-- Agrega el tipo de tarifa por turno a las ofertas de servicios.
ALTER TABLE prestador_servicio
  MODIFY tipo_tarifa ENUM('visita', 'por_hora', 'turno') NOT NULL;
