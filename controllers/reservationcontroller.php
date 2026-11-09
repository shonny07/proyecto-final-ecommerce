<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/reservationmodel.php';
require_once __DIR__ . '/../models/providermodel.php';
require_once __DIR__ . '/../models/bookingnotifier.php';
require_once __DIR__ . '/../models/paymentmodel.php';
require_once __DIR__ . '/../config/security.php';

class ReservationController
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
        $model = new ReservationModel();
        $providerId = null;
        if ($role === 'prestador') {
            $providerId = (new ProviderModel())->providerIdForUser((int) $user['id']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
                $this->redirect('error', 'El formulario venció. Recargá la página e intentá de nuevo.');
            }
            try {
                $action = (string) ($_POST['action'] ?? '');
                if ($action === 'reservar' && $role === 'User') {
                    $providerIdFromPost = $this->positiveId($_POST['prestador_id'] ?? '');
                    $serviceId = $this->positiveId($_POST['servicio_id'] ?? '');
                    $inputDate = trim((string) ($_POST['fecha'] ?? ''));
                    $inputTime = trim((string) ($_POST['hora'] ?? ''));
                    $date = DateTime::createFromFormat('!Y-m-d H:i', $inputDate . ' ' . $inputTime);
                    if (!$date || $date->format('Y-m-d H:i') !== $inputDate . ' ' . $inputTime || $date <= new DateTime()) {
                        throw new DomainException('Elegí un turno disponible y futuro.');
                    }
                    $comments = trim((string) ($_POST['comentarios'] ?? ''));
                    if (mb_strlen($comments) > 2000) {
                        throw new DomainException('El comentario admite hasta 2000 caracteres.');
                    }
                    $reservationId = $model->create((int) $user['id'], $providerIdFromPost, $serviceId, $date->format('Y-m-d H:i:s'), $comments);
                    $delivery = (new BookingNotifier($model))->notifyProvider($reservationId);
                    $message = 'Solicitud de reserva enviada. Si el prestador compartió su WhatsApp, lo vas a ver en Mis reservas.';
                    if (!$delivery['email']) {
                        $message .= ' No se pudo enviar el correo; revisá la configuración de correo.';
                    }
                    if (!$delivery['whatsapp']) {
                        $message .= ' No se pudo enviar el WhatsApp; revisá la configuración de WhatsApp.';
                    }
                    $this->redirect('mensaje', $message);
                }
                if ($action === 'completar' && $role === 'prestador' && $providerId !== null) {
                    $reservationId = $this->positiveId($_POST['reserva_id'] ?? '');
                    if (!$model->complete($providerId, $reservationId)) {
                        throw new DomainException('Solo podés completar una reserva propia cuya fecha ya haya pasado.');
                    }
                    $message = 'Reserva marcada como completada.';
                    if (!(new BookingNotifier($model))->notifyCustomerRating($reservationId)) {
                        $message .= ' No se pudo enviar el correo para pedir la calificación; revisá MAIL_FROM y el SMTP de XAMPP.';
                    }
                    $this->redirect('mensaje', $message);
                }
                if ($action === 'cancelar' && in_array($role, ['User', 'prestador'], true)) {
                    $reservationId = $this->positiveId($_POST['reserva_id'] ?? '');
                    if (!$model->cancel($reservationId, (int) $user['id'], $role, $providerId)) {
                        throw new DomainException('No se pudo cancelar: la reserva ya no está pendiente, ya comenzó o no pertenece a tu cuenta.');
                    }
                    $this->redirect('mensaje', 'Reserva cancelada y turno liberado.');
                }
                if ($action === 'calificar' && $role === 'User') {
                    $reservationId = $this->positiveId($_POST['reserva_id'] ?? '');
                    $stars = $this->positiveId($_POST['estrellas'] ?? '');
                    if ($stars > 5) {
                        throw new DomainException('La calificación debe ser de 1 a 5 estrellas.');
                    }
                    $comment = trim((string) ($_POST['opinion'] ?? ''));
                    if (mb_strlen($comment) > 500) {
                        throw new DomainException('La opinión admite hasta 500 caracteres.');
                    }
                    $model->rate((int) $user['id'], $reservationId, $stars, $comment);
                    $this->redirect('mensaje', 'Gracias por calificar al prestador.');
                }
                throw new DomainException('La acción solicitada no es válida para tu cuenta.');
            } catch (DomainException $exception) {
                $this->redirect('error', $exception->getMessage());
            } catch (PDOException $exception) {
                error_log('Error al guardar una reserva o calificación: ' . $exception->getMessage());
                $this->redirect('error', 'No se pudo guardar el cambio. Revisá la base de datos e intentá nuevamente.');
            }
        }

        try {
            $reservations = $role === 'prestador' && $providerId !== null
                ? $model->providerReservations($providerId)
                : ($role === 'User' ? $model->customerReservations((int) $user['id']) : []);
        } catch (PDOException $exception) {
            error_log('Error al cargar reservas: ' . $exception->getMessage());
            http_response_code(500);
            echo 'No se pudieron cargar las reservas. Aplicá las migraciones pendientes e intentá de nuevo.';
            return;
        }

        $message = trim((string) ($_GET['mensaje'] ?? ''));
        $error = trim((string) ($_GET['error'] ?? ''));
        $demoPaymentsEnabled = PaymentModel::demoModeEnabled();
        $mercadoPagoEnabled = !$demoPaymentsEnabled && trim((string) (getenv('MP_ACCESS_TOKEN') ?: '')) !== '';
        require __DIR__ . '/../views/reservations.php';
    }

    private function positiveId(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new DomainException('Seleccioná una opción válida.');
        }
        return (int) $id;
    }

    private function redirect(string $key, string $message): never
    {
        header('Location: reservas.php?' . $key . '=' . rawurlencode($message));
        exit;
    }
}
