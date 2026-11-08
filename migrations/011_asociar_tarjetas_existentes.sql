-- Vincula tarjetas antiguas solo cuando el prestador ofrece un único servicio en ese rubro.
UPDATE tarjeta_presentacion AS tp
INNER JOIN (
  SELECT ps.id_prestador, s.rubro, MIN(s.id) AS id_servicio
  FROM prestador_servicio AS ps
  INNER JOIN servicio AS s ON s.id = ps.id_servicio
  GROUP BY ps.id_prestador, s.rubro
  HAVING COUNT(DISTINCT s.id) = 1
) AS oferta_unica
  ON oferta_unica.id_prestador = tp.id_prestador
 AND oferta_unica.rubro = tp.rubro_id
SET tp.id_servicio = oferta_unica.id_servicio
WHERE tp.id_servicio IS NULL;
