<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class ServiceModel
{
    /** @return array<int, array<string, mixed>> */
    public function search(string $term = '', ?int $providerId = null): array
    {
        $sql = 'SELECT
                    s.id AS servicio_id,
                    p.id AS prestador_id,
                    s.nombre AS servicio,
                    c.nombre AS categoria,
                    ps.precio,
                    ps.tipo_tarifa,
                    ps.duracion_minutos,
                    u.nombre AS prestador,
                    COALESCE(cal.promedio, 0) AS promedio_calificacion,
                    COALESCE(cal.cantidad, 0) AS cantidad_calificaciones
                FROM servicio AS s
                INNER JOIN categoria AS c ON c.id = s.rubro
                INNER JOIN prestador_servicio AS ps ON ps.id_servicio = s.id
                INNER JOIN prestador AS p ON p.id = ps.id_prestador
                INNER JOIN usuario AS u ON u.id = p.id_user
                LEFT JOIN (
                    SELECT r.id_prestador, AVG(ca.estrellas) AS promedio, COUNT(*) AS cantidad
                    FROM reserva AS r
                    INNER JOIN calificacion AS ca ON ca.id_reserva = r.id
                    GROUP BY r.id_prestador
                ) AS cal ON cal.id_prestador = p.id';

        $conditions = [];
        if ($term !== '') {
            $conditions[] = '(s.nombre LIKE :service_search
                              OR c.nombre LIKE :category_search
                              OR u.nombre LIKE :provider_search)';
        }
        if ($providerId !== null) {
            $conditions[] = 'p.id = :provider_id';
        }
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY promedio_calificacion DESC, cantidad_calificaciones DESC, s.nombre, u.nombre';
        $statement = getDatabaseConnection()->prepare($sql);

        if ($term !== '') {
            $searchTerm = '%' . $term . '%';
            $statement->bindValue(':service_search', $searchTerm, PDO::PARAM_STR);
            $statement->bindValue(':category_search', $searchTerm, PDO::PARAM_STR);
            $statement->bindValue(':provider_search', $searchTerm, PDO::PARAM_STR);
        }
        if ($providerId !== null) {
            $statement->bindValue(':provider_id', $providerId, PDO::PARAM_INT);
        }

        $statement->execute();
        return $statement->fetchAll();
    }
}
