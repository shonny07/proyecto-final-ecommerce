-- Ejecutar una sola vez sobre e_commerce.
ALTER TABLE tarjeta_promocional
  ADD COLUMN rubro_id INT UNSIGNED NULL AFTER imagen;

UPDATE tarjeta_promocional AS t
INNER JOIN categoria AS c ON c.nombre = t.texto
SET t.rubro_id = c.id;

ALTER TABLE tarjeta_promocional
  ADD CONSTRAINT fk_tarjeta_rubro FOREIGN KEY (rubro_id) REFERENCES categoria(id) ON DELETE SET NULL;
