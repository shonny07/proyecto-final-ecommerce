<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class ReservationModel
{
    public function availableSlots(int $providerId, int $serviceId, string $date): array
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($day === false || $day->format('Y-m-d') !== $date || $day < new DateTimeImmutable('today')) {
            return [];
        }
        $connection = getDatabaseConnection();
        $offer = $connection->prepare(
            'SELECT ps.duracion_minutos, p.horarios, p.bloqueos
             FROM prestador_servicio AS ps
             INNER JOIN prestador AS p ON p.id = ps.id_prestador
             WHERE ps.id_prestador = :provider_id AND ps.id_servicio = :service_id'
        );
        $offer->execute(['provider_id' => $providerId, 'service_id' => $serviceId]);
        $details = $offer->fetch();
        if (!$details) {
            return [];
        }
        $schedule = $details['horarios'] ? json_decode((string) $details['horarios'], true) : [];
        $blocks = $details['bloqueos'] ? json_decode((string) $details['bloqueos'], true) : [];
        $windows = is_array($schedule) ? ($schedule[$day->format('N')] ?? []) : [];
        $blocks = is_array($blocks) ? $blocks : [];
        $duration = max(5, (int) $details['duracion_minutos']);
        $dayStart = $day->format('Y-m-d 00:00:00');
        $dayEnd = $day->modify('+1 day')->format('Y-m-d 00:00:00');
        $bookings = $connection->prepare(
            "SELECT fecha_hora, fecha_hora_fin FROM reserva
             WHERE id_prestador = :provider_id AND estado <> 'cancelada'
               AND fecha_hora < :day_end AND fecha_hora_fin > :day_start"
        );
        $bookings->execute(['provider_id' => $providerId, 'day_start' => $dayStart, 'day_end' => $dayEnd]);
        $bookings = $bookings->fetchAll();

        $slots = [];
        foreach (is_array($windows) ? $windows : [] as $window) {
            $from = $this->minutesFromTime((string) ($window['inicio'] ?? ''));
            $until = $this->minutesFromTime((string) ($window['fin'] ?? ''));
            if ($from === null || $until === null || $until <= $from) {
                continue;
            }
            for ($minute = $from; $minute + $duration <= $until; $minute += $duration) {
                $start = $day->setTime(intdiv($minute, 60), $minute % 60);
                if ($start <= new DateTimeImmutable()) {
                    continue;
                }
                $end = $start->modify('+' . $duration . ' minutes');
                $startText = $start->format('Y-m-d H:i:s');
                $endText = $end->format('Y-m-d H:i:s');
                $blocked = false;
                foreach ($blocks as $block) {
                    if (isset($block['reserva_id'])) {
                        continue;
                    }
                    if ($startText < (string) ($block['fin'] ?? '') && (string) ($block['inicio'] ?? '') < $endText) {
                        $blocked = true;
                        break;
                    }
                }
                if (!$blocked) {
                    foreach ($bookings as $booking) {
                        if ($startText < $booking['fecha_hora_fin'] && $booking['fecha_hora'] < $endText) {
                            $blocked = true;
                            break;
                        }
                    }
                }
                if (!$blocked) {
                    $slots[] = $start->format('H:i');
                }
            }
        }
        return array_values(array_unique($slots));
    }

    public function create(int $customerId, int $providerId, int $serviceId, string $dateTime, string $comments): int
    {
        $connection = getDatabaseConnection();
        $connection->beginTransaction();
        try {
            $provider = $connection->prepare('SELECT horarios, bloqueos FROM prestador WHERE id = :id FOR UPDATE');
            $provider->execute(['id' => $providerId]);
            $providerData = $provider->fetch();
            if (!$providerData) {
                throw new DomainException('Ese prestador ya no está disponible.');
            }
            $offer = $connection->prepare(
                'SELECT precio, tipo_tarifa, duracion_minutos FROM prestador_servicio
                 WHERE id_prestador = :provider_id AND id_servicio = :service_id'
            );
            $offer->execute(['provider_id' => $providerId, 'service_id' => $serviceId]);
            $offerData = $offer->fetch();
            if (!$offerData) {
                throw new DomainException('Ese prestador ya no ofrece el servicio seleccionado.');
            }

            $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $dateTime);
            if (!$start || $start->format('Y-m-d H:i:s') !== $dateTime || $start <= new DateTimeImmutable()) {
                throw new DomainException('Elegí un turno futuro válido.');
            }
            $duration = (int) $offerData['duracion_minutos'];
            $end = $start->modify('+' . $duration . ' minutes');
            $slotEnd = $end->format('Y-m-d H:i:s');
            $schedule = $providerData['horarios'] ? json_decode((string) $providerData['horarios'], true) : [];
            $dayWindows = is_array($schedule) ? ($schedule[$start->format('N')] ?? []) : [];
            $startMinute = ((int) $start->format('H')) * 60 + (int) $start->format('i');
            $validWindow = false;
            foreach (is_array($dayWindows) ? $dayWindows : [] as $window) {
                $from = $this->minutesFromTime((string) ($window['inicio'] ?? ''));
                $until = $this->minutesFromTime((string) ($window['fin'] ?? ''));
                if ($from !== null && $until !== null && $startMinute >= $from
                    && $startMinute + $duration <= $until && ($startMinute - $from) % $duration === 0) {
                    $validWindow = true;
                    break;
                }
            }
            if (!$validWindow) {
                throw new DomainException('Ese turno no está dentro de los horarios que ofrece el prestador.');
            }

            $blocks = $providerData['bloqueos'] ? json_decode((string) $providerData['bloqueos'], true) : [];
            $blocks = is_array($blocks) ? $blocks : [];
            foreach ($blocks as $block) {
                if (isset($block['reserva_id'])) {
                    continue;
                }
                if ($dateTime < (string) ($block['fin'] ?? '') && (string) ($block['inicio'] ?? '') < $slotEnd) {
                    throw new DomainException('El prestador no está disponible en ese período. Elegí otro turno.');
                }
            }
            $occupied = $connection->prepare(
                "SELECT COUNT(*) FROM reserva WHERE id_prestador = :provider_id AND estado <> 'cancelada'
                   AND fecha_hora < :slot_end AND fecha_hora_fin > :slot_start"
            );
            $occupied->execute(['provider_id' => $providerId, 'slot_start' => $dateTime, 'slot_end' => $slotEnd]);
            if ((int) $occupied->fetchColumn() > 0) {
                throw new DomainException('Ese turno se superpone con otra reserva. Elegí otro horario.');
            }

            $unitPrice = (float) $offerData['precio'];
            $price = $offerData['tipo_tarifa'] === 'por_hora'
                ? round($unitPrice * $duration / 60, 2)
                : $unitPrice;
            $statement = $connection->prepare(
                'INSERT INTO reserva (fecha_hora, fecha_hora_fin, id_servicio, id_prestador, id_cliente, comentarios, precio_acordado)
                 VALUES (:fecha_hora, :fecha_hora_fin, :servicio_id, :prestador_id, :cliente_id, :comentarios, :precio)'
            );
            $statement->execute([
                'fecha_hora' => $dateTime,
                'fecha_hora_fin' => $slotEnd,
                'servicio_id' => $serviceId,
                'prestador_id' => $providerId,
                'cliente_id' => $customerId,
                'comentarios' => $comments !== '' ? $comments : null,
                'precio' => $price,
            ]);
            $reservationId = (int) $connection->lastInsertId();
            $blocks[] = [
                'id' => bin2hex(random_bytes(12)),
                'reserva_id' => $reservationId,
                'inicio' => $dateTime,
                'fin' => $slotEnd,
                'motivo' => 'Reserva solicitada',
            ];
            $saveAvailability = $connection->prepare('UPDATE prestador SET bloqueos = :bloqueos WHERE id = :id');
            $saveAvailability->execute([
                'bloqueos' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'id' => $providerId,
            ]);
            $connection->commit();
            return $reservationId;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public function customerReservations(int $customerId): array
    {
        $statement = getDatabaseConnection()->prepare(
            'SELECT r.id, r.fecha_hora, r.fecha_hora_fin, r.estado, r.precio_acordado, r.comentarios AS detalle,
                    s.nombre AS servicio, u.nombre AS prestador, p.contacto AS contacto_prestador,
                    (SELECT pg.id FROM pagos pg WHERE pg.id_reserva = r.id ORDER BY pg.id DESC LIMIT 1) AS pago_id,
                    (SELECT pg.proveedor FROM pagos pg WHERE pg.id_reserva = r.id ORDER BY pg.id DESC LIMIT 1) AS pago_proveedor,
                    (SELECT pg.metodo_pago FROM pagos pg WHERE pg.id_reserva = r.id ORDER BY pg.id DESC LIMIT 1) AS pago_metodo,
                    (SELECT pg.estado FROM pagos pg WHERE pg.id_reserva = r.id ORDER BY pg.id DESC LIMIT 1) AS pago_estado,
                    (SELECT pg.confirmacion FROM pagos pg WHERE pg.id_reserva = r.id ORDER BY pg.id DESC LIMIT 1) AS pago_confirmado,
                    ca.estrellas, ca.comentario AS opinion
             FROM reserva AS r
             INNER JOIN servicio AS s ON s.id = r.id_servicio
             INNER JOIN prestador AS p ON p.id = r.id_prestador
             INNER JOIN usuario AS u ON u.id = p.id_user
             LEFT JOIN calificacion AS ca ON ca.id_reserva = r.id
             WHERE r.id_cliente = :customer_id
             ORDER BY r.fecha_hora DESC'
        );
        $statement->execute(['customer_id' => $customerId]);
        return $statement->fetchAll();
    }

    public function notificationDetails(int $reservationId): ?array
    {
        $statement = getDatabaseConnection()->prepare(
            'SELECT r.id, r.fecha_hora, r.fecha_hora_fin, s.nombre AS servicio,
                    cliente.nombre AS cliente, cliente.email AS email_cliente,
                    prestador_user.nombre AS nombre_prestador,
                    prestador_user.email AS email_prestador, p.contacto AS telefono_prestador
             FROM reserva AS r
             INNER JOIN servicio AS s ON s.id = r.id_servicio
             INNER JOIN usuario AS cliente ON cliente.id = r.id_cliente
             INNER JOIN prestador AS p ON p.id = r.id_prestador
             INNER JOIN usuario AS prestador_user ON prestador_user.id = p.id_user
             WHERE r.id = :id'
        );
        $statement->execute(['id' => $reservationId]);
        $details = $statement->fetch();
        return $details ?: null;
    }

    public function providerReservations(int $providerId): array
    {
        $statement = getDatabaseConnection()->prepare(
            'SELECT r.id, r.fecha_hora, r.fecha_hora_fin, r.estado, r.precio_acordado, r.comentarios AS detalle,
                    (SELECT pg.estado FROM pagos pg WHERE pg.id_reserva = r.id ORDER BY pg.id DESC LIMIT 1) AS pago_estado,
                    (SELECT pg.id FROM pagos pg WHERE pg.id_reserva = r.id ORDER BY pg.id DESC LIMIT 1) AS pago_id,
                    (SELECT pg.proveedor FROM pagos pg WHERE pg.id_reserva = r.id ORDER BY pg.id DESC LIMIT 1) AS pago_proveedor,
                    (SELECT pg.metodo_pago FROM pagos pg WHERE pg.id_reserva = r.id ORDER BY pg.id DESC LIMIT 1) AS pago_metodo,
                    (SELECT pg.confirmacion FROM pagos pg WHERE pg.id_reserva = r.id ORDER BY pg.id DESC LIMIT 1) AS pago_confirmado,
                    s.nombre AS servicio, u.nombre AS cliente
             FROM reserva AS r
             INNER JOIN servicio AS s ON s.id = r.id_servicio
             INNER JOIN usuario AS u ON u.id = r.id_cliente
             WHERE r.id_prestador = :provider_id
             ORDER BY r.fecha_hora DESC'
        );
        $statement->execute(['provider_id' => $providerId]);
        return $statement->fetchAll();
    }

    public function complete(int $providerId, int $reservationId): bool
    {
        $statement = getDatabaseConnection()->prepare(
            "UPDATE reserva SET estado = 'completada'
             WHERE id = :reservation_id AND id_prestador = :provider_id
               AND estado = 'pendiente' AND fecha_hora_fin <= :ahora"
        );
        $statement->execute(['reservation_id' => $reservationId, 'provider_id' => $providerId, 'ahora' => date('Y-m-d H:i:s')]);
        return $statement->rowCount() > 0;
    }

    public function cancel(int $reservationId, int $userId, string $role, ?int $providerId): bool
    {
        $connection = getDatabaseConnection();
        $connection->beginTransaction();
        try {
            $conditions = $role === 'prestador'
                ? 'id_prestador = :owner_id'
                : 'id_cliente = :owner_id';
            $find = $connection->prepare("SELECT id_prestador FROM reserva WHERE id = :id AND {$conditions} AND estado = 'pendiente' FOR UPDATE");
            $ownerId = $role === 'prestador' ? $providerId : $userId;
            if ($ownerId === null) {
                $connection->rollBack();
                return false;
            }
            $find->execute(['id' => $reservationId, 'owner_id' => $ownerId]);
            $reservationProviderId = $find->fetchColumn();
            if ($reservationProviderId === false) {
                $connection->rollBack();
                return false;
            }

            $provider = $connection->prepare('SELECT bloqueos FROM prestador WHERE id = :id FOR UPDATE');
            $provider->execute(['id' => (int) $reservationProviderId]);
            $json = $provider->fetchColumn();
            $blocks = $json ? json_decode((string) $json, true) : [];
            $blocks = is_array($blocks) ? $blocks : [];
            $blocks = array_values(array_filter($blocks, static fn(array $block): bool => (int) ($block['reserva_id'] ?? 0) !== $reservationId));

            $update = $connection->prepare(
                "UPDATE reserva SET estado = 'cancelada'
                 WHERE id = :id AND estado = 'pendiente' AND fecha_hora > :ahora"
            );
            $update->execute(['id' => $reservationId, 'ahora' => date('Y-m-d H:i:s')]);
            if ($update->rowCount() === 0) {
                $connection->rollBack();
                return false;
            }
            $save = $connection->prepare('UPDATE prestador SET bloqueos = :bloqueos WHERE id = :id');
            $save->execute([
                'bloqueos' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'id' => (int) $reservationProviderId,
            ]);
            $connection->commit();
            return true;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public function rate(int $customerId, int $reservationId, int $stars, string $comment): void
    {
        $statement = getDatabaseConnection()->prepare(
            "INSERT INTO calificacion (id_reserva, estrellas, comentario)
             SELECT r.id, :stars, :comment FROM reserva AS r
             WHERE r.id = :reservation_id AND r.id_cliente = :customer_id AND r.estado = 'completada'
               AND NOT EXISTS (SELECT 1 FROM calificacion AS c WHERE c.id_reserva = r.id)"
        );
        $statement->execute([
            'stars' => $stars,
            'comment' => $comment !== '' ? $comment : null,
            'reservation_id' => $reservationId,
            'customer_id' => $customerId,
        ]);
        if ($statement->rowCount() === 0) {
            throw new DomainException('Solo podés calificar una reserva completada y todavía no calificada.');
        }
    }

    private function minutesFromTime(string $value): ?int
    {
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) !== 1) {
            return null;
        }
        [$hours, $minutes] = array_map('intval', explode(':', $value));
        return $hours * 60 + $minutes;
    }
}
