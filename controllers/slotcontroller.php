<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/reservationmodel.php';

class SlotController
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $providerId = filter_var($_GET['prestador_id'] ?? null, FILTER_VALIDATE_INT);
        $serviceId = filter_var($_GET['servicio_id'] ?? null, FILTER_VALIDATE_INT);
        $date = trim((string) ($_GET['fecha'] ?? ''));
        if ($providerId === false || $providerId < 1 || $serviceId === false || $serviceId < 1) {
            http_response_code(400);
            echo json_encode(['turnos' => []]);
            return;
        }

        try {
            $slots = (new ReservationModel())->availableSlots((int) $providerId, (int) $serviceId, $date);
            echo json_encode(['turnos' => $slots], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            error_log('Error al consultar turnos disponibles: ' . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['turnos' => []]);
        }
    }
}
