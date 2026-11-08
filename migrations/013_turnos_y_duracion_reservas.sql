-- Ejecutar una sola vez sobre la base e_commerce existente.
-- Permite definir la duración de cada servicio y guardar el período reservado.

ALTER TABLE prestador_servicio
  ADD COLUMN duracion_minutos SMALLINT UNSIGNED NOT NULL DEFAULT 60;

ALTER TABLE reserva
  ADD COLUMN fecha_hora_fin DATETIME NULL AFTER fecha_hora;

UPDATE reserva
SET fecha_hora_fin = DATE_ADD(fecha_hora, INTERVAL 1 HOUR)
WHERE fecha_hora_fin IS NULL;

ALTER TABLE reserva
  MODIFY COLUMN fecha_hora_fin DATETIME NOT NULL;
