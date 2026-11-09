<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/paymentmodel.php';
require_once __DIR__ . '/../config/security.php';

class PaymentController
{
    public function start(): void
    {
        startSecureSession();
        $user = $_SESSION['usuario'] ?? null;
        if ($user === null) {
            header('Location: login.php');
            exit;
        }
        if ((string) ($user['rol'] ?? '') !== 'User' || $_SERVER['REQUEST_METHOD'] !== 'POST'
            || !isValidCsrfToken($_POST['csrf_token'] ?? null)) {
            header('Location: reservas.php?error=' . rawurlencode('No se pudo iniciar el pago.'));
            exit;
        }
        $reservationId = filter_var($_POST['reserva_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$reservationId || $reservationId < 1) {
            header('Location: reservas.php?error=' . rawurlencode('La reserva seleccionada no es válida.'));
            exit;
        }
        try {
            $model = new PaymentModel();
            if (($_POST['accion'] ?? '') === 'simular') {
                $paymentId = $model->simulatePayment(
                    (int) $user['id'],
                    (int) $reservationId,
                    (string) ($_POST['medio_pago'] ?? '')
                );
                header('Location: reservas.php?mensaje=' . rawurlencode('Pago simulado. Ya podés descargar el comprobante.'), true, 303);
                exit;
            }
            if (PaymentModel::demoModeEnabled()) {
                throw new DomainException('La aplicación está en modo de simulación; no se pueden iniciar cobros reales.');
            }
            $url = $model->checkoutForCustomer((int) $user['id'], (int) $reservationId);
            header('Location: ' . $url, true, 303);
            exit;
        } catch (DomainException $error) {
            header('Location: reservas.php?error=' . rawurlencode($error->getMessage()));
            exit;
        } catch (Throwable $error) {
            error_log('Error al iniciar pago de reserva: ' . $error->getMessage());
            header('Location: reservas.php?error=' . rawurlencode('No se pudo iniciar el pago. Revisá la configuración de Mercado Pago e intentá nuevamente.'));
            exit;
        }
    }

    public function receipt(): void
    {
        startSecureSession();
        $user = $_SESSION['usuario'] ?? null;
        if ($user === null || (string) ($user['rol'] ?? '') !== 'User') {
            http_response_code(403);
            echo 'No autorizado.';
            return;
        }
        $paymentId = filter_var($_GET['pago'] ?? null, FILTER_VALIDATE_INT);
        if (!$paymentId || $paymentId < 1) {
            http_response_code(404);
            echo 'No se encontró el comprobante.';
            return;
        }
        $details = (new PaymentModel())->receiptForCustomer((int) $user['id'], (int) $paymentId);
        if ($details === null) {
            http_response_code(404);
            echo 'No se encontró el comprobante.';
            return;
        }

        $isDemo = $details['proveedor'] === 'simulacion';
        $lines = [
            'Comprobante de pago - ServiClick',
            'Numero: ' . (int) $details['pago_id'] . '    Reserva: ' . (int) $details['reserva_id'],
            'Fecha de emision: ' . date('d/m/Y H:i:s'),
            'Cliente: ' . $details['cliente'] . ' (' . $details['email'] . ')',
            'Prestador: ' . $details['prestador'],
            'Servicio: ' . $details['servicio'],
            'Turno: ' . date('d/m/Y H:i', strtotime($details['fecha_hora'])) . ' a ' . date('d/m/Y H:i', strtotime($details['fecha_hora_fin'])),
            'Importe: $' . number_format((float) $details['monto'], 2, ',', '.') . ' ' . $details['moneda'],
            'Medio: ' . $this->paymentMethodLabel((string) $details['metodo_pago']),
            'Estado: ' . ($isDemo ? 'APROBADO - SIMULACION ACADEMICA' : 'APROBADO - MERCADO PAGO'),
            'Referencia: ' . (string) ($details['pago_externo_id'] ?? '-'),
            '',
            $isDemo ? 'SIMULACION ACADEMICA: no es una transaccion real ni tiene validez fiscal.' : 'Constancia informativa emitida por el sitio; no es factura ni comprobante fiscal.',
        ];
        $pdf = $this->buildSimplePdf($lines);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="comprobante-pago-' . (int) $details['pago_id'] . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }

    public function webhook(): void
    {
        http_response_code(200);
        header('Content-Type: text/plain; charset=utf-8');
        $body = json_decode((string) file_get_contents('php://input'), true);
        $dataId = (string) ($_GET['data_id'] ?? ($body['data']['id'] ?? ''));
        $signature = (string) ($_SERVER['HTTP_X_SIGNATURE'] ?? '');
        $requestId = (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
        if (!(new PaymentModel())->validateWebhook($signature, $requestId, $dataId)) {
            http_response_code(401);
            echo 'invalid signature';
            return;
        }
        $type = (string) ($_GET['type'] ?? ($body['type'] ?? ''));
        if ($type !== '' && $type !== 'payment') {
            echo 'ignored';
            return;
        }
        try {
            (new PaymentModel())->synchronizeMercadoPagoPayment($dataId);
            echo 'ok';
        } catch (Throwable $error) {
            error_log('Error al procesar webhook de Mercado Pago: ' . $error->getMessage());
            http_response_code(500);
            echo 'retry';
        }
    }

    private function paymentMethodLabel(string $method): string
    {
        return ['debito' => 'Tarjeta de debito (simulada)', 'credito' => 'Tarjeta de credito (simulada)', 'transferencia' => 'Transferencia bancaria (simulada)'][$method]
            ?? ($method !== '' ? $method : 'Mercado Pago');
    }

    private function buildSimplePdf(array $lines): string
    {
        $content = "BT\n/F1 16 Tf\n50 790 Td\n";
        foreach ($lines as $index => $line) {
            if ($index === 1) $content .= "/F1 11 Tf\n";
            if ($index === count($lines) - 1) {
                $content .= "/F1 9 Tf\n";
            }
            $text = function_exists('iconv') ? iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', (string) $line) : (string) $line;
            if (!is_string($text)) $text = (string) $line;
            $text = str_replace(["\\", '(', ')', "\r", "\n"], ["\\\\", '\\(', '\\)', '', ''], $text);
            $content .= '(' . $text . ") Tj\n0 -25 Td\n";
        }
        $content .= "ET\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length ' . strlen($content) . ">>\nstream\n" . $content . "endstream",
        ];
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) $pdf .= sprintf('%010d 00000 n ', $offset) . "\n";
        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }
}
