<?php
declare(strict_types=1);

require_once __DIR__ . '/reservationmodel.php';
require_once __DIR__ . '/../config/contact.php';

class BookingNotifier
{
    public function __construct(private ReservationModel $reservations)
    {
    }

    /** @return array{email: bool, whatsapp: bool} */
    public function notifyProvider(int $reservationId): array
    {
        try {
            $details = $this->reservations->notificationDetails($reservationId);
        } catch (Throwable $exception) {
            error_log('No se pudieron leer los datos de notificación de la reserva ' . $reservationId . '.');
            return ['email' => false, 'whatsapp' => false];
        }
        if ($details === null) {
            error_log('No se encontraron datos para notificar la reserva ' . $reservationId . '.');
            return ['email' => false, 'whatsapp' => false];
        }

        try {
            $emailSent = $this->sendEmail($details);
        } catch (Throwable $exception) {
            error_log('No se pudo completar el envío de correo para la reserva ' . $reservationId . '.');
            $emailSent = false;
        }
        $hasWhatsApp = trim((string) ($details['telefono_prestador'] ?? '')) !== '';
        if ($hasWhatsApp) {
            try {
                $whatsAppSent = $this->sendWhatsApp($details);
            } catch (Throwable $exception) {
                error_log('No se pudo completar el envío de WhatsApp para la reserva ' . $reservationId . '.');
                $whatsAppSent = false;
            }
        } else {
            $whatsAppSent = true;
        }
        $result = ['email' => $emailSent, 'whatsapp' => $whatsAppSent];
        if (!$result['email'] || !$result['whatsapp']) {
            error_log('Una o más notificaciones fallaron para la reserva ' . $reservationId . '.');
        }
        return $result;
    }

    public function notifyCustomerRating(int $reservationId): bool
    {
        try {
            $details = $this->reservations->notificationDetails($reservationId);
            if ($details === null || !filter_var($details['email_cliente'] ?? '', FILTER_VALIDATE_EMAIL)) {
                error_log('No hay un correo válido para solicitar la calificación de la reserva ' . $reservationId . '.');
                return false;
            }
            $from = trim((string) getenv('MAIL_FROM'));
            if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
                error_log('No se pudo solicitar la calificación: configurá MAIL_FROM.');
                return false;
            }

            $subject = mb_encode_mimeheader('Tu servicio terminó: calificá al prestador', 'UTF-8');
            $body = 'Hola ' . $details['cliente'] . ",\n\n"
                . 'La reserva del servicio "' . $details['servicio'] . '" con '
                . ($details['nombre_prestador'] ?? 'el prestador') . " fue marcada como completada.\n\n"
                . "Te pedimos que califiques cómo fue el servicio para ayudar a otros clientes. "
                . "Ingresá a tu cuenta y abrí la sección Mis reservas; allí vas a encontrar el formulario de estrellas y opinión.\n";
            $baseUrl = rtrim(trim((string) (getenv('APP_BASE_URL') ?: '')), '/');
            if ($baseUrl !== '' && filter_var($baseUrl, FILTER_VALIDATE_URL)) {
                $body .= "\nIr a Mis reservas: " . $baseUrl . '/reservas.php' . "\n";
            }
            $body .= "\nReserva: " . (int) $details['id'] . "\n"
                . 'Turno: ' . date('d/m/Y H:i', strtotime($details['fecha_hora']))
                . ' a ' . date('d/m/Y H:i', strtotime($details['fecha_hora_fin'])) . "\n\n"
                . "Gracias por usar ServiClick.\n";
            $headers = "From: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\n";
            $sent = @mail((string) $details['email_cliente'], $subject, $body, $headers);
            if (!$sent) {
                error_log('PHP mail() no pudo enviar el pedido de calificación de la reserva ' . $reservationId . '.');
            }
            return $sent;
        } catch (Throwable $exception) {
            error_log('No se pudo enviar el pedido de calificación para la reserva ' . $reservationId . '.');
            return false;
        }
    }

    private function sendEmail(array $details): bool
    {
        $from = trim((string) getenv('MAIL_FROM'));
        if (!filter_var($details['email_prestador'], FILTER_VALIDATE_EMAIL)
            || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            error_log('Notificación por correo omitida: configurá MAIL_FROM y un correo válido del prestador.');
            return false;
        }

        $subject = mb_encode_mimeheader('Nueva solicitud de reserva', 'UTF-8');
        $body = "Recibiste una nueva solicitud de reserva.\n\n"
            . 'Servicio: ' . $details['servicio'] . "\n"
            . 'Cliente: ' . $details['cliente'] . "\n"
            . 'Correo del cliente: ' . $details['email_cliente'] . "\n"
            . 'Turno: ' . date('d/m/Y H:i', strtotime($details['fecha_hora']))
                . ' a ' . date('d/m/Y H:i', strtotime($details['fecha_hora_fin'])) . "\n"
            . 'Número de reserva: ' . $details['id'] . "\n";
        $headers = "From: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        return @mail((string) $details['email_prestador'], $subject, $body, $headers);
    }

    private function sendWhatsApp(array $details): bool
    {
        $token = trim((string) getenv('WHATSAPP_ACCESS_TOKEN'));
        $phoneNumberId = trim((string) getenv('WHATSAPP_PHONE_NUMBER_ID'));
        $version = trim((string) getenv('WHATSAPP_GRAPH_VERSION'));
        $template = trim((string) getenv('WHATSAPP_TEMPLATE_NAME'));
        $language = trim((string) getenv('WHATSAPP_TEMPLATE_LANGUAGE'));
        $to = normalizeWhatsAppPhone((string) $details['telefono_prestador']);

        if ($to === null || $to === '') {
            return true;
        }
        if ($token === '' || $phoneNumberId === '' || !preg_match('/^v\d+\.\d+$/', $version)
            || $template === '' || $language === '' || $to === '') {
            error_log('Notificación por WhatsApp omitida: falta configurar la API, la plantilla o el teléfono del prestador.');
            return false;
        }
        if (!function_exists('curl_init')) {
            error_log('Notificación por WhatsApp omitida: la extensión cURL de PHP no está habilitada.');
            return false;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language],
                'components' => [[
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => (string) $details['cliente']],
                        ['type' => 'text', 'text' => (string) $details['servicio']],
                        ['type' => 'text', 'text' => date('d/m/Y H:i', strtotime($details['fecha_hora']))],
                    ],
                ]],
            ],
        ];

        $handle = curl_init('https://graph.facebook.com/' . $version . '/' . rawurlencode($phoneNumberId) . '/messages');
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
        curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_errno($handle);
        curl_close($handle);

        if ($curlError !== 0 || $status < 200 || $status >= 300) {
            error_log('La API de WhatsApp devolvió un error para la reserva ' . (int) $details['id'] . ' (HTTP ' . $status . ').');
            return false;
        }
        return true;
    }
}
