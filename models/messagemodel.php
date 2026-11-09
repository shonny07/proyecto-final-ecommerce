<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class MessageModel
{
    public function conversationForReservation(int $userId, string $role, int $reservationId): ?array
    {
        $participantCondition = match ($role) {
            'User' => 'r.id_cliente = :user_id',
            'prestador' => 'p.id_user = :user_id',
            default => '1 = 0',
        };
        $db = getDatabaseConnection();
        $reservationQuery = $db->prepare(
            "SELECT r.id AS reserva_id, r.estado AS estado_reserva, r.fecha_hora, s.nombre AS servicio,
                    cliente.nombre AS cliente, prestador.nombre AS nombre_prestador
             FROM reserva r
             INNER JOIN servicio s ON s.id = r.id_servicio
             INNER JOIN usuario cliente ON cliente.id = r.id_cliente
             INNER JOIN prestador p ON p.id = r.id_prestador
             INNER JOIN usuario prestador ON prestador.id = p.id_user
             WHERE r.id = :reservation_id AND {$participantCondition}"
        );
        $reservationQuery->execute(['reservation_id' => $reservationId, 'user_id' => $userId]);
        $reservation = $reservationQuery->fetch();
        if (!$reservation) {
            return null;
        }

        $create = $db->prepare('INSERT IGNORE INTO conversacion (id_reserva) VALUES (:reservation_id)');
        $create->execute(['reservation_id' => $reservationId]);
        $conversationQuery = $db->prepare('SELECT id FROM conversacion WHERE id_reserva = :reservation_id');
        $conversationQuery->execute(['reservation_id' => $reservationId]);
        $conversationId = (int) $conversationQuery->fetchColumn();
        if ($conversationId < 1) {
            throw new RuntimeException('No se pudo abrir la conversación de la reserva.');
        }

        $markRead = $db->prepare(
            'UPDATE mensaje SET leido_en = NOW()
             WHERE id_conversacion = :conversation_id AND id_emisor <> :user_id AND leido_en IS NULL'
        );
        $markRead->execute(['conversation_id' => $conversationId, 'user_id' => $userId]);

        $messagesQuery = $db->prepare(
            'SELECT m.id, m.id_emisor, m.contenido, m.fecha_creacion, u.nombre AS nombre_emisor
             FROM mensaje m INNER JOIN usuario u ON u.id = m.id_emisor
             WHERE m.id_conversacion = :conversation_id
             ORDER BY m.id ASC'
        );
        $messagesQuery->execute(['conversation_id' => $conversationId]);
        $reservation['conversacion_id'] = $conversationId;
        $reservation['mensajes'] = $messagesQuery->fetchAll();
        return $reservation;
    }

    public function send(int $userId, string $role, int $reservationId, string $content): void
    {
        $content = trim($content);
        if ($content === '' || mb_strlen($content) > 4000) {
            throw new DomainException('El mensaje debe tener entre 1 y 4000 caracteres.');
        }
        $conversation = $this->conversationForReservation($userId, $role, $reservationId);
        if ($conversation === null) {
            throw new DomainException('No tenés acceso a esa conversación.');
        }
        $statement = getDatabaseConnection()->prepare(
            'INSERT INTO mensaje (id_conversacion, id_emisor, contenido)
             VALUES (:conversation_id, :sender_id, :content)'
        );
        $statement->execute([
            'conversation_id' => $conversation['conversacion_id'],
            'sender_id' => $userId,
            'content' => $content,
        ]);
    }
}
