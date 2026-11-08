<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class PresentationModel
{
    public function cardsForHeader(): array
    {
        $statement = getDatabaseConnection()->query(
            "SELECT tp.id, tp.id_prestador AS prestador_id, tp.imagen, u.nombre AS nombre
             FROM tarjeta_presentacion AS tp
             INNER JOIN prestador AS p ON p.id = tp.id_prestador
             INNER JOIN usuario AS u ON u.id = p.id_user
             WHERE tp.imagen IS NOT NULL AND tp.imagen <> ''
               AND EXISTS (
                   SELECT 1 FROM prestador_servicio AS ps
                   WHERE ps.id_prestador = tp.id_prestador
               )
             ORDER BY tp.fecha_creacion DESC, tp.id DESC"
        );
        return $statement->fetchAll();
    }

    public function cardForProvider(int $providerId): ?array
    {
        $statement = getDatabaseConnection()->prepare(
            'SELECT id, imagen FROM tarjeta_presentacion WHERE id_prestador = :provider_id'
        );
        $statement->execute(['provider_id' => $providerId]);
        $card = $statement->fetch();
        return $card ?: null;
    }

    public function save(int $providerId, string $imagePath): ?string
    {
        $find = getDatabaseConnection()->prepare(
            'SELECT imagen FROM tarjeta_presentacion WHERE id_prestador = :provider_id'
        );
        $find->execute(['provider_id' => $providerId]);
        $previousImage = $find->fetchColumn();
        $statement = getDatabaseConnection()->prepare(
            'INSERT INTO tarjeta_presentacion (id_prestador, imagen)
             VALUES (:provider_id, :image)
             ON DUPLICATE KEY UPDATE imagen = VALUES(imagen)'
        );
        $statement->execute(['provider_id' => $providerId, 'image' => $imagePath]);
        return is_string($previousImage) && $previousImage !== '' ? $previousImage : null;
    }

    public function delete(int $providerId): ?string
    {
        $find = getDatabaseConnection()->prepare(
            'SELECT imagen FROM tarjeta_presentacion WHERE id_prestador = :provider_id'
        );
        $find->execute(['provider_id' => $providerId]);
        $imagePath = $find->fetchColumn();
        $statement = getDatabaseConnection()->prepare(
            'DELETE FROM tarjeta_presentacion WHERE id_prestador = :provider_id'
        );
        $statement->execute(['provider_id' => $providerId]);
        return is_string($imagePath) && $imagePath !== '' ? $imagePath : null;
    }
}
