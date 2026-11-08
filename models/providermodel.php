<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class ProviderModel
{
    public function providerIdForUser(int $userId): ?int
    {
        $statement = getDatabaseConnection()->prepare('SELECT id FROM prestador WHERE id_user = :user_id');
        $statement->execute(['user_id' => $userId]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function profile(int $providerId): ?array
    {
        $statement = getDatabaseConnection()->prepare(
            'SELECT p.id, p.monto, p.contacto, u.nombre, u.email
             FROM prestador AS p
             INNER JOIN usuario AS u ON u.id = p.id_user
             WHERE p.id = :id'
        );
        $statement->execute(['id' => $providerId]);
        $profile = $statement->fetch();
        return $profile ?: null;
    }

    public function categories(): array
    {
        $statement = getDatabaseConnection()->query(
            'SELECT c.id, c.nombre, padre.nombre AS nombre_padre
             FROM categoria AS c
             LEFT JOIN categoria AS padre ON padre.id = c.rubro_padre
             ORDER BY COALESCE(padre.nombre, c.nombre), c.rubro_padre IS NOT NULL, c.nombre'
        );
        return $statement->fetchAll();
    }

    public function availableServices(int $providerId): array
    {
        $statement = getDatabaseConnection()->prepare(
            'SELECT s.id, s.nombre, c.nombre AS categoria
             FROM servicio AS s
             INNER JOIN categoria AS c ON c.id = s.rubro
             WHERE NOT EXISTS (
                 SELECT 1 FROM prestador_servicio AS ps
                 WHERE ps.id_servicio = s.id AND ps.id_prestador = :prestador_id
             )
             ORDER BY c.nombre, s.nombre'
        );
        $statement->execute(['prestador_id' => $providerId]);
        return $statement->fetchAll();
    }

    public function offers(int $providerId): array
    {
        $statement = getDatabaseConnection()->prepare(
            'SELECT s.id AS servicio_id, s.nombre AS servicio, c.nombre AS categoria,
                    ps.precio, ps.tipo_tarifa, ps.duracion_minutos
             FROM prestador_servicio AS ps
             INNER JOIN servicio AS s ON s.id = ps.id_servicio
             INNER JOIN categoria AS c ON c.id = s.rubro
             WHERE ps.id_prestador = :prestador_id
             ORDER BY c.nombre, s.nombre'
        );
        $statement->execute(['prestador_id' => $providerId]);
        return $statement->fetchAll();
    }

    public function updateProfile(int $providerId, ?string $contact): void
    {
        $statement = getDatabaseConnection()->prepare(
            'UPDATE prestador SET contacto = :contacto WHERE id = :id'
        );
        $statement->execute(['contacto' => $contact, 'id' => $providerId]);
    }

    public function availability(int $providerId): array
    {
        $statement = getDatabaseConnection()->prepare('SELECT bloqueos FROM prestador WHERE id = :id');
        $statement->execute(['id' => $providerId]);
        $json = $statement->fetchColumn();
        if ($json === false || $json === null || $json === '') {
            return [];
        }
        $blocks = json_decode((string) $json, true);
        return is_array($blocks) ? array_values(array_filter($blocks, 'is_array')) : [];
    }

    public function weeklySchedule(int $providerId): array
    {
        $statement = getDatabaseConnection()->prepare('SELECT horarios FROM prestador WHERE id = :id');
        $statement->execute(['id' => $providerId]);
        $json = $statement->fetchColumn();
        if ($json === false || $json === null || $json === '') {
            return [];
        }
        $schedule = json_decode((string) $json, true);
        return is_array($schedule) ? $schedule : [];
    }

    public function updateWeeklySchedule(int $providerId, array $schedule): void
    {
        $statement = getDatabaseConnection()->prepare('UPDATE prestador SET horarios = :horarios WHERE id = :id');
        $statement->execute([
            'horarios' => json_encode($schedule, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'id' => $providerId,
        ]);
    }

    public function addAvailabilityBlock(int $providerId, array $block): void
    {
        $connection = getDatabaseConnection();
        $connection->beginTransaction();
        try {
            $statement = $connection->prepare('SELECT bloqueos FROM prestador WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $providerId]);
            $json = $statement->fetchColumn();
            if ($json === false) {
                throw new DomainException('No encontramos tu perfil de prestador.');
            }
            $blocks = $json ? json_decode((string) $json, true) : [];
            $blocks = is_array($blocks) ? $blocks : [];
            $bookings = $connection->prepare(
                "SELECT COUNT(*) FROM reserva WHERE id_prestador = :id_prestador AND estado <> 'cancelada'
                   AND fecha_hora < :fin AND fecha_hora_fin > :inicio"
            );
            $effectiveStart = max((string) $block['inicio'], date('Y-m-d H:i:s'));
            $bookings->execute(['id_prestador' => $providerId, 'inicio' => $effectiveStart, 'fin' => $block['fin']]);
            if ((int) $bookings->fetchColumn() > 0) {
                throw new DomainException('Ese período ya tiene reservas. Reprogramalas o cancelalas antes de bloquearlo.');
            }
            foreach ($blocks as $existing) {
                if (isset($existing['reserva_id'])) {
                    continue; // Reservation conflicts are checked from reserva, the source of truth.
                }
                if (($block['inicio'] < ($existing['fin'] ?? '') ) && ($existing['inicio'] ?? '') < $block['fin']) {
                    throw new DomainException('Ese período se superpone con un bloqueo que ya cargaste.');
                }
            }
            $blocks[] = $block;
            $save = $connection->prepare('UPDATE prestador SET bloqueos = :bloqueos WHERE id = :id');
            $save->execute(['bloqueos' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'id' => $providerId]);
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public function removeAvailabilityBlock(int $providerId, string $blockId): bool
    {
        $connection = getDatabaseConnection();
        $connection->beginTransaction();
        try {
            $statement = $connection->prepare('SELECT bloqueos FROM prestador WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $providerId]);
            $json = $statement->fetchColumn();
            $blocks = $json ? json_decode((string) $json, true) : [];
            $blocks = is_array($blocks) ? $blocks : [];
            $filtered = array_values(array_filter($blocks, static fn(array $block): bool => ($block['id'] ?? '') !== $blockId || isset($block['reserva_id'])));
            if (count($filtered) === count($blocks)) {
                $connection->rollBack();
                return false;
            }
            $save = $connection->prepare('UPDATE prestador SET bloqueos = :bloqueos WHERE id = :id');
            $save->execute(['bloqueos' => json_encode($filtered, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'id' => $providerId]);
            $connection->commit();
            return true;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public function addExistingService(int $providerId, int $serviceId, float $price, string $tariff, int $durationMinutes): void
    {
        $statement = getDatabaseConnection()->prepare(
            'INSERT INTO prestador_servicio (id_prestador, id_servicio, precio, tipo_tarifa, duracion_minutos)
             VALUES (:prestador_id, :servicio_id, :precio, :tipo_tarifa, :duracion_minutos)'
        );
        $statement->execute([
            'prestador_id' => $providerId,
            'servicio_id' => $serviceId,
            'precio' => $price,
            'tipo_tarifa' => $tariff,
            'duracion_minutos' => $durationMinutes,
        ]);
    }

    public function createServiceAndOffer(int $providerId, int $categoryId, string $name, float $price, string $tariff, int $durationMinutes): void
    {
        $connection = getDatabaseConnection();
        $connection->beginTransaction();
        try {
            $findService = $connection->prepare(
                'SELECT id FROM servicio WHERE nombre = :nombre AND rubro = :rubro LIMIT 1'
            );
            $findService->execute(['nombre' => $name, 'rubro' => $categoryId]);
            $serviceId = $findService->fetchColumn();

            if ($serviceId === false) {
                $createService = $connection->prepare('INSERT INTO servicio (nombre, rubro) VALUES (:nombre, :rubro)');
                $createService->execute(['nombre' => $name, 'rubro' => $categoryId]);
                $serviceId = $connection->lastInsertId();
            }

            $offer = $connection->prepare(
                'INSERT INTO prestador_servicio (id_prestador, id_servicio, precio, tipo_tarifa, duracion_minutos)
                 VALUES (:prestador_id, :servicio_id, :precio, :tipo_tarifa, :duracion_minutos)'
            );
            $offer->execute([
                'prestador_id' => $providerId,
                'servicio_id' => (int) $serviceId,
                'precio' => $price,
                'tipo_tarifa' => $tariff,
                'duracion_minutos' => $durationMinutes,
            ]);
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public function updateOffer(int $providerId, int $serviceId, float $price, string $tariff, int $durationMinutes): bool
    {
        $statement = getDatabaseConnection()->prepare(
            'UPDATE prestador_servicio
             SET precio = :precio, tipo_tarifa = :tipo_tarifa, duracion_minutos = :duracion_minutos
             WHERE id_prestador = :prestador_id AND id_servicio = :servicio_id'
        );
        $statement->execute([
            'precio' => $price,
            'tipo_tarifa' => $tariff,
            'duracion_minutos' => $durationMinutes,
            'prestador_id' => $providerId,
            'servicio_id' => $serviceId,
        ]);
        return $statement->rowCount() > 0;
    }

    public function removeOffer(int $providerId, int $serviceId): bool
    {
        $statement = getDatabaseConnection()->prepare(
            'DELETE FROM prestador_servicio WHERE id_prestador = :prestador_id AND id_servicio = :servicio_id'
        );
        $statement->execute(['prestador_id' => $providerId, 'servicio_id' => $serviceId]);
        return $statement->rowCount() > 0;
    }
}
