<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class PaymentModel
{
    public static function demoModeEnabled(): bool
    {
        $setting = strtolower(trim((string) (getenv('PAYMENT_DEMO_MODE') ?: '1')));
        return in_array($setting, ['1', 'true', 'yes', 'on'], true);
    }

    public function simulatePayment(int $customerId, int $reservationId, string $method): int
    {
        if (!self::demoModeEnabled()) {
            throw new DomainException('El modo de simulación está desactivado.');
        }
        $allowedMethods = ['debito', 'credito', 'transferencia'];
        if (!in_array($method, $allowedMethods, true)) {
            throw new DomainException('Elegí un medio de pago válido para la demostración.');
        }

        $db = getDatabaseConnection();
        $db->beginTransaction();
        try {
            $query = $db->prepare(
                "SELECT r.id, r.precio_acordado, r.estado FROM reserva r
                 WHERE r.id = :reservation AND r.id_cliente = :customer FOR UPDATE"
            );
            $query->execute(['reservation' => $reservationId, 'customer' => $customerId]);
            $reservation = $query->fetch();
            if (!$reservation || $reservation['estado'] !== 'pendiente') {
                throw new DomainException('Solo podés simular el pago de una reserva pendiente de tu cuenta.');
            }
            $paid = $db->prepare('SELECT COUNT(*) FROM pagos WHERE id_reserva = :id AND confirmacion = 1');
            $paid->execute(['id' => $reservationId]);
            if ((int) $paid->fetchColumn() > 0) {
                throw new DomainException('Esta reserva ya figura como pagada.');
            }
            $reference = 'SIM-' . $reservationId . '-' . bin2hex(random_bytes(6));
            $insert = $db->prepare(
                "INSERT INTO pagos (id_reserva, confirmacion, estado, proveedor, pago_externo_id,
                 metodo_pago, monto, moneda, detalle_estado)
                 VALUES (:reservation, 1, 'simulado_aprobado', 'simulacion', :reference,
                 :method, :amount, 'ARS', 'Simulación académica; no es una transacción real')"
            );
            $insert->execute([
                'reservation' => $reservationId,
                'reference' => $reference,
                'method' => $method,
                'amount' => round((float) $reservation['precio_acordado'], 2),
            ]);
            $paymentId = (int) $db->lastInsertId();
            $db->commit();
            return $paymentId;
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }
    }

    public function receiptForCustomer(int $customerId, int $paymentId): ?array
    {
        $statement = getDatabaseConnection()->prepare(
            'SELECT pg.id AS pago_id, pg.estado AS pago_estado, pg.proveedor, pg.pago_externo_id,
                    pg.metodo_pago, pg.monto, pg.moneda, pg.detalle_estado, pg.fecha_actualizacion,
                    r.id AS reserva_id, r.fecha_hora, r.fecha_hora_fin, s.nombre AS servicio,
                    cliente.nombre AS cliente, cliente.email, prestador.nombre AS prestador
             FROM pagos pg INNER JOIN reserva r ON r.id = pg.id_reserva
             INNER JOIN servicio s ON s.id = r.id_servicio
             INNER JOIN usuario cliente ON cliente.id = r.id_cliente
             INNER JOIN prestador p ON p.id = r.id_prestador
             INNER JOIN usuario prestador ON prestador.id = p.id_user
             WHERE pg.id = :payment AND r.id_cliente = :customer AND pg.confirmacion = 1'
        );
        $statement->execute(['payment' => $paymentId, 'customer' => $customerId]);
        $details = $statement->fetch();
        return $details ?: null;
    }

    public function checkoutForCustomer(int $customerId, int $reservationId): string
    {
        $token = trim((string) (getenv('MP_ACCESS_TOKEN') ?: ''));
        if ($token === '') {
            throw new RuntimeException('Falta configurar MP_ACCESS_TOKEN en Apache.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL no está habilitado en XAMPP.');
        }

        $db = getDatabaseConnection();
        $query = $db->prepare(
            "SELECT r.id, r.precio_acordado, r.estado, s.nombre AS servicio, u.email
             FROM reserva r INNER JOIN servicio s ON s.id = r.id_servicio
             INNER JOIN usuario u ON u.id = r.id_cliente
             WHERE r.id = :reservation AND r.id_cliente = :customer"
        );
        $query->execute(['reservation' => $reservationId, 'customer' => $customerId]);
        $reservation = $query->fetch();
        if (!$reservation || $reservation['estado'] !== 'pendiente') {
            throw new DomainException('Solo podés pagar una reserva pendiente de tu cuenta.');
        }
        $paid = $db->prepare("SELECT COUNT(*) FROM pagos WHERE id_reserva = :id AND confirmacion = 1");
        $paid->execute(['id' => $reservationId]);
        if ((int) $paid->fetchColumn() > 0) {
            throw new DomainException('Esta reserva ya figura como pagada.');
        }

        $price = round((float) $reservation['precio_acordado'], 2);
        if ($price <= 0) {
            throw new DomainException('El importe de la reserva no es válido.');
        }
        $insert = $db->prepare("INSERT INTO pagos (id_reserva, confirmacion, estado, monto, moneda) VALUES (:id, 0, 'creando', :monto, 'ARS')");
        $insert->execute(['id' => $reservationId, 'monto' => $price]);
        $paymentId = (int) $db->lastInsertId();

        $baseUrl = rtrim(trim((string) (getenv('MP_PUBLIC_BASE_URL') ?: '')), '/');
        $preference = [
            'items' => [[
                'id' => 'reserva-' . $reservationId,
                'title' => mb_substr('Servicio: ' . (string) $reservation['servicio'], 0, 250),
                'quantity' => 1,
                'currency_id' => 'ARS',
                'unit_price' => $price,
            ]],
            'payer' => ['email' => (string) $reservation['email']],
            'external_reference' => (string) $reservationId,
            'metadata' => ['id_reserva' => $reservationId, 'id_pago_local' => $paymentId],
        ];
        if ($baseUrl !== '' && str_starts_with($baseUrl, 'https://')) {
            $preference['back_urls'] = [
                'success' => $baseUrl . '/reservas.php?mensaje=' . rawurlencode('Volviste de Mercado Pago. Estamos verificando el pago.'),
                'pending' => $baseUrl . '/reservas.php?mensaje=' . rawurlencode('El pago está pendiente de confirmación.'),
                'failure' => $baseUrl . '/reservas.php?mensaje=' . rawurlencode('El pago no se aprobó. Podés intentar nuevamente.'),
            ];
            $preference['auto_return'] = 'approved';
            $preference['notification_url'] = $baseUrl . '/webhook_mercadopago.php';
        }

        try {
            $response = $this->request('POST', 'https://api.mercadopago.com/checkout/preferences', $token, $preference);
            $environment = strtolower(trim((string) (getenv('MP_ENVIRONMENT') ?: 'test')));
            $checkoutUrl = $environment === 'production'
                ? (string) ($response['init_point'] ?? '')
                : (string) ($response['sandbox_init_point'] ?? '');
            $preferenceId = (string) ($response['id'] ?? '');
            if ($checkoutUrl === '' || $preferenceId === '') {
                throw new RuntimeException('Mercado Pago no devolvió una URL de pago válida.');
            }
            $save = $db->prepare("UPDATE pagos SET preferencia_id = :preference, estado = 'pendiente' WHERE id = :id");
            $save->execute(['preference' => $preferenceId, 'id' => $paymentId]);
            return $checkoutUrl;
        } catch (Throwable $error) {
            $failed = $db->prepare("UPDATE pagos SET estado = 'error', detalle_estado = :detail WHERE id = :id");
            $failed->execute(['detail' => mb_substr($error->getMessage(), 0, 120), 'id' => $paymentId]);
            throw $error;
        }
    }

    public function validateWebhook(string $signature, string $requestId, string $dataId): bool
    {
        $secret = trim((string) (getenv('MP_WEBHOOK_SECRET') ?: ''));
        if ($secret === '' || $signature === '' || $dataId === '') {
            return false;
        }
        $parts = [];
        foreach (explode(',', $signature) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }
        if (empty($parts['ts']) || empty($parts['v1'])) {
            return false;
        }
        $manifest = 'id:' . strtolower($dataId) . ';';
        if ($requestId !== '') {
            $manifest .= 'request-id:' . $requestId . ';';
        }
        $manifest .= 'ts:' . $parts['ts'] . ';';
        return hash_equals(hash_hmac('sha256', $manifest, $secret), $parts['v1']);
    }

    public function synchronizeMercadoPagoPayment(string $externalPaymentId): void
    {
        $token = trim((string) (getenv('MP_ACCESS_TOKEN') ?: ''));
        if ($token === '') {
            throw new RuntimeException('Falta configurar MP_ACCESS_TOKEN.');
        }
        $payment = $this->request('GET', 'https://api.mercadopago.com/v1/payments/' . rawurlencode($externalPaymentId), $token);
        $reservationId = filter_var($payment['external_reference'] ?? null, FILTER_VALIDATE_INT);
        $preferenceId = (string) ($payment['preference_id'] ?? '');
        if (!$reservationId || $preferenceId === '') {
            throw new RuntimeException('La notificación no corresponde a una preferencia válida.');
        }
        $status = (string) ($payment['status'] ?? '');
        $amount = round((float) ($payment['transaction_amount'] ?? 0), 2);
        $db = getDatabaseConnection();
        $find = $db->prepare('SELECT id, monto FROM pagos WHERE id_reserva = :reservation AND preferencia_id = :preference LIMIT 1');
        $find->execute(['reservation' => $reservationId, 'preference' => $preferenceId]);
        $local = $find->fetch();
        if (!$local || abs(round((float) $local['monto'], 2) - $amount) > 0.009 || ($payment['currency_id'] ?? '') !== 'ARS') {
            throw new RuntimeException('El importe o moneda notificados no coinciden con la reserva.');
        }
        $db->beginTransaction();
        try {
            $update = $db->prepare(
                'UPDATE pagos SET pago_externo_id = :external, estado = :status, confirmacion = :confirmed,
                 metodo_pago = :method, detalle_estado = :detail WHERE id = :id'
            );
            $update->execute([
                'external' => $externalPaymentId,
                'status' => mb_substr($status, 0, 30),
                'confirmed' => $status === 'approved' ? 1 : 0,
                'method' => mb_substr((string) ($payment['payment_method_id'] ?? ''), 0, 80),
                'detail' => mb_substr((string) ($payment['status_detail'] ?? ''), 0, 120),
                'id' => $local['id'],
            ]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }
    }

    private function request(string $method, string $url, string $token, ?array $body = null): array
    {
        $curl = curl_init($url);
        $headers = ['Authorization: Bearer ' . $token, 'Accept: application/json'];
        if ($body !== null) $headers[] = 'Content-Type: application/json';
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 25,
        ]);
        if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if (!is_string($raw) || $status < 200 || $status >= 300) {
            error_log('Mercado Pago API HTTP ' . $status . ': ' . $error . ' ' . (is_string($raw) ? $raw : ''));
            throw new RuntimeException('No se pudo iniciar o consultar el pago en Mercado Pago.');
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) throw new RuntimeException('Respuesta inválida de Mercado Pago.');
        return $decoded;
    }
}
