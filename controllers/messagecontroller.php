<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/messagemodel.php';
require_once __DIR__ . '/../config/security.php';

class MessageController
{
    public function index(): void
    {
        startSecureSession();
        $user = $_SESSION['usuario'] ?? null;
        if ($user === null) {
            header('Location: login.php');
            exit;
        }
        $role = (string) ($user['rol'] ?? '');
        if (!in_array($role, ['User', 'prestador'], true)) {
            http_response_code(403);
            echo 'La mensajería está disponible para clientes y prestadores.';
            return;
        }
        $reservationId = filter_var($_GET['reserva'] ?? null, FILTER_VALIDATE_INT);
        if (!$reservationId || $reservationId < 1) {
            http_response_code(404);
            echo 'No se encontró esa reserva.';
            return;
        }

        $model = new MessageModel();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
                $this->redirect((int) $reservationId, 'error', 'El formulario venció. Recargá la página e intentá de nuevo.');
            }
            try {
                $model->send((int) $user['id'], $role, (int) $reservationId, (string) ($_POST['contenido'] ?? ''));
                $this->redirect((int) $reservationId, 'mensaje', 'Mensaje enviado.');
            } catch (DomainException $exception) {
                $this->redirect((int) $reservationId, 'error', $exception->getMessage());
            } catch (Throwable $exception) {
                error_log('No se pudo guardar un mensaje de la reserva ' . (int) $reservationId . ': ' . $exception->getMessage());
                $this->redirect((int) $reservationId, 'error', 'No se pudo enviar el mensaje. Intentá nuevamente.');
            }
        }

        try {
            $conversation = $model->conversationForReservation((int) $user['id'], $role, (int) $reservationId);
            if ($conversation === null) {
                http_response_code(404);
                echo 'No encontramos una conversación asociada a una reserva de tu cuenta.';
                return;
            }
            if (($_GET['ajax'] ?? '') === '1') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($conversation['mensajes'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                return;
            }
        } catch (Throwable $exception) {
            error_log('No se pudo cargar la conversación: ' . $exception->getMessage());
            http_response_code(500);
            echo 'No se pudo cargar la conversación. Revisá la migración de mensajería.';
            return;
        }

        $currentUserId = (int) $user['id'];
        $otherParticipant = $role === 'User' ? $conversation['nombre_prestador'] : $conversation['cliente'];
        $message = trim((string) ($_GET['mensaje'] ?? ''));
        $error = trim((string) ($_GET['error'] ?? ''));
        require __DIR__ . '/../views/messages.php';
    }

    private function redirect(int $reservationId, string $key, string $message): never
    {
        header('Location: mensajes.php?reserva=' . $reservationId . '&' . $key . '=' . rawurlencode($message));
        exit;
    }
}
