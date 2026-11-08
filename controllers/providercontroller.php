<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/providermodel.php';
require_once __DIR__ . '/../models/presentationmodel.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/contact.php';

class ProviderController
{
    public function dashboard(): void
    {
        startSecureSession();
        $currentUser = $_SESSION['usuario'] ?? null;
        if ($currentUser === null) {
            header('Location: login.php');
            exit;
        }
        if (($currentUser['rol'] ?? '') !== 'prestador') {
            http_response_code(403);
            echo 'Este panel es exclusivo para prestadores.';
            return;
        }

        $providerModel = new ProviderModel();
        $providerId = $providerModel->providerIdForUser((int) $currentUser['id']);
        if ($providerId === null) {
            http_response_code(403);
            echo 'No encontramos el perfil de prestador asociado a esta cuenta.';
            return;
        }
        $presentationModel = new PresentationModel();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
                $this->redirectWithMessage('error', 'El formulario venció. Recargá la página e intentá de nuevo.');
            }

            try {
                $action = (string) ($_POST['action'] ?? '');
                if ($action === 'actualizar_perfil') {
                    $rawContact = trim((string) ($_POST['contacto'] ?? ''));
                    $contact = $rawContact === '' ? null : normalizeWhatsAppPhone($rawContact);
                    if ($rawContact !== '' && $contact === null) {
                        throw new DomainException('Ingresá un teléfono de WhatsApp válido con código de país.');
                    }
                    $providerModel->updateProfile($providerId, $contact);
                    $this->redirectWithMessage('mensaje', 'Perfil actualizado.');
                }

                if ($action === 'actualizar_horarios') {
                    $postedSchedule = $_POST['horarios'] ?? [];
                    $postedFormats = $_POST['formato_horario'] ?? [];
                    if (!is_array($postedSchedule) || !is_array($postedFormats)) {
                        throw new DomainException('Revisá los horarios seleccionados.');
                    }
                    $schedule = [];
                    for ($day = 1; $day <= 7; $day++) {
                        $windows = $postedSchedule[(string) $day] ?? $postedSchedule[$day] ?? [];
                        if (!is_array($windows)) {
                            continue;
                        }
                        $format = (string) ($postedFormats[(string) $day] ?? $postedFormats[$day] ?? 'corrido');
                        if (!in_array($format, ['corrido', 'separado'], true)) {
                            throw new DomainException('Elegí si ese día atendés de corrido o en dos tramos.');
                        }
                        $cleanWindows = [];
                        $windowCount = $format === 'separado' ? 2 : 1;
                        for ($window = 0; $window < $windowCount; $window++) {
                            $period = $windows[$window] ?? [];
                            if (!is_array($period)) {
                                throw new DomainException('Revisá los horarios seleccionados.');
                            }
                            $startTime = trim((string) ($period['inicio'] ?? ''));
                            $endTime = trim((string) ($period['fin'] ?? ''));
                            if ($startTime === '' && $endTime === '') {
                                if ($format === 'separado') {
                                    throw new DomainException('Completá los dos tramos o elegí atención de corrido para ese día.');
                                }
                                continue;
                            }
                            if (!$this->validTime($startTime) || !$this->validTime($endTime) || $endTime <= $startTime) {
                                throw new DomainException('Completá correctamente las horas desde y hasta de cada tramo.');
                            }
                            $cleanWindows[] = ['inicio' => $startTime, 'fin' => $endTime];
                        }
                        usort($cleanWindows, static fn(array $a, array $b): int => strcmp($a['inicio'], $b['inicio']));
                        for ($i = 1; $i < count($cleanWindows); $i++) {
                            if ($cleanWindows[$i]['inicio'] < $cleanWindows[$i - 1]['fin']) {
                                throw new DomainException('Los tramos de un mismo día no pueden superponerse.');
                            }
                        }
                        if ($cleanWindows !== []) {
                            $schedule[(string) $day] = $cleanWindows;
                        }
                    }
                    $providerModel->updateWeeklySchedule($providerId, $schedule);
                    $this->redirectWithMessage('mensaje', 'Horario de atención guardado.');
                }

                if ($action === 'agregar_bloqueo') {
                    $startDate = $this->validDate((string) ($_POST['fecha_inicio'] ?? ''));
                    $endDate = $this->validDate((string) ($_POST['fecha_fin'] ?? ''));
                    $startTime = trim((string) ($_POST['hora_inicio'] ?? ''));
                    $endTime = trim((string) ($_POST['hora_fin'] ?? ''));
                    if ($endDate < $startDate) {
                        throw new DomainException('La fecha final no puede ser anterior a la inicial.');
                    }
                    if (($startTime === '') !== ($endTime === '')) {
                        throw new DomainException('Para bloquear por horario, completá tanto la hora inicial como la final.');
                    }
                    if ($startTime === '') {
                        $start = new DateTimeImmutable($startDate . ' 00:00:00');
                        $end = (new DateTimeImmutable($endDate . ' 00:00:00'))->modify('+1 day');
                    } else {
                        if (!$this->validTime($startTime) || !$this->validTime($endTime)) {
                            throw new DomainException('Ingresá horarios válidos.');
                        }
                        $start = new DateTimeImmutable($startDate . ' ' . $startTime . ':00');
                        $end = new DateTimeImmutable($endDate . ' ' . $endTime . ':00');
                    }
                    $now = new DateTimeImmutable();
                    $sameDayRemainder = $startTime === '' && $startDate === $now->format('Y-m-d') && $end > $now;
                    if (($start <= $now && !$sameDayRemainder) || $end <= $start) {
                        throw new DomainException('El bloqueo debe ser futuro y finalizar después de su inicio.');
                    }
                    $reason = trim((string) ($_POST['motivo'] ?? ''));
                    if (mb_strlen($reason) > 120) {
                        throw new DomainException('El motivo admite hasta 120 caracteres.');
                    }
                    $providerModel->addAvailabilityBlock($providerId, [
                        'id' => bin2hex(random_bytes(12)),
                        'inicio' => $start->format('Y-m-d H:i:s'),
                        'fin' => $end->format('Y-m-d H:i:s'),
                        'motivo' => $reason,
                    ]);
                    $this->redirectWithMessage('mensaje', 'Período marcado como no disponible.');
                }

                if ($action === 'eliminar_bloqueo') {
                    $blockId = (string) ($_POST['bloqueo_id'] ?? '');
                    if (preg_match('/^[a-f0-9]{24}$/', $blockId) !== 1
                        || !$providerModel->removeAvailabilityBlock($providerId, $blockId)) {
                        throw new DomainException('No encontramos ese bloqueo. Actualizá la página e intentá de nuevo.');
                    }
                    $this->redirectWithMessage('mensaje', 'Período de disponibilidad eliminado.');
                }

                if ($action === 'agregar_existente') {
                    $serviceId = $this->positiveId($_POST['servicio_id'] ?? '');
                    [$price, $tariff, $duration] = $this->priceAndTariff();
                    $providerModel->addExistingService($providerId, $serviceId, $price, $tariff, $duration);
                    $this->redirectWithMessage('mensaje', 'Servicio agregado a tu catálogo.');
                }

                if ($action === 'crear_servicio') {
                    $name = trim((string) ($_POST['nombre_servicio'] ?? ''));
                    $categoryId = $this->positiveId($_POST['categoria_id'] ?? '');
                    if ($name === '' || mb_strlen($name) > 150) {
                        throw new DomainException('El nombre del servicio es obligatorio y admite hasta 150 caracteres.');
                    }
                    if (!$this->containsCategory($providerModel->categories(), $categoryId)) {
                        throw new DomainException('Elegí una categoría disponible.');
                    }
                    [$price, $tariff, $duration] = $this->priceAndTariff();
                    $providerModel->createServiceAndOffer($providerId, $categoryId, $name, $price, $tariff, $duration);
                    $this->redirectWithMessage('mensaje', 'Servicio creado y publicado.');
                }

                if ($action === 'actualizar_oferta') {
                    $serviceId = $this->positiveId($_POST['servicio_id'] ?? '');
                    [$price, $tariff, $duration] = $this->priceAndTariff();
                    if (!$providerModel->updateOffer($providerId, $serviceId, $price, $tariff, $duration)) {
                        if (!$this->containsOffer($providerModel->offers($providerId), $serviceId)) {
                            throw new DomainException('Ese servicio no forma parte de tu catálogo.');
                        }
                    }
                    $this->redirectWithMessage('mensaje', 'Tarifa actualizada.');
                }

                if ($action === 'quitar_oferta') {
                    $serviceId = $this->positiveId($_POST['servicio_id'] ?? '');
                    if (!$providerModel->removeOffer($providerId, $serviceId)) {
                        throw new DomainException('Ese servicio no forma parte de tu catálogo.');
                    }
                    $this->redirectWithMessage('mensaje', 'Servicio quitado de tu catálogo.');
                }

                if ($action === 'guardar_tarjeta') {
                    if ($providerModel->offers($providerId) === []) {
                        throw new DomainException('Publicá al menos un servicio antes de cargar tu tarjeta.');
                    }
                    $existingCard = $presentationModel->cardForProvider($providerId);
                    $file = $_FILES['imagen'] ?? ['error' => UPLOAD_ERR_NO_FILE];
                    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        if (empty($existingCard['imagen'])) {
                            throw new DomainException('Seleccioná la imagen de tu tarjeta de presentación.');
                        }
                        $imagePath = $existingCard['imagen'];
                        $newImagePath = null;
                    } else {
                        $newImagePath = $this->storePresentationImage($file);
                        $imagePath = $newImagePath;
                    }
                    try {
                        $previousImagePath = $presentationModel->save($providerId, $imagePath);
                    } catch (Throwable $exception) {
                        if ($newImagePath !== null) {
                            $this->deletePresentationImage($newImagePath);
                        }
                        throw $exception;
                    }
                    if ($newImagePath !== null && $previousImagePath !== $newImagePath) {
                        $this->deletePresentationImage($previousImagePath);
                    }
                    $this->redirectWithMessage('mensaje', 'Tarjeta de presentación guardada.');
                }

                if ($action === 'eliminar_tarjeta') {
                    $imagePath = $presentationModel->delete($providerId);
                    $this->deletePresentationImage($imagePath);
                    $this->redirectWithMessage('mensaje', 'Tarjeta de presentación eliminada.');
                }

                throw new DomainException('La acción solicitada no es válida.');
            } catch (DomainException $exception) {
                $this->redirectWithMessage('error', $exception->getMessage());
            } catch (PDOException $exception) {
                error_log('Error al actualizar el catálogo del prestador: ' . $exception->getMessage());
                $message = $exception->getCode() === '23000'
                    ? 'Ya ofrecés ese servicio, o hay reservas vinculadas que impiden quitarlo.'
                    : 'No se pudo guardar el cambio. Revisá los datos e intentá de nuevo.';
                $this->redirectWithMessage('error', $message);
            } catch (Throwable $exception) {
                error_log('Error inesperado en el panel del prestador: ' . $exception->getMessage());
                $this->redirectWithMessage('error', 'No se pudo completar la operación. Intentá nuevamente.');
            }
        }

        try {
            $profile = $providerModel->profile($providerId);
            $weeklySchedule = $providerModel->weeklySchedule($providerId);
            $weekdays = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
            $availabilityBlocks = $providerModel->availability($providerId);
            usort($availabilityBlocks, static fn(array $a, array $b): int => strcmp((string) ($a['inicio'] ?? ''), (string) ($b['inicio'] ?? '')));
            $categories = $providerModel->categories();
            $availableServices = $providerModel->availableServices($providerId);
            $offers = $providerModel->offers($providerId);
            $presentation = $presentationModel->cardForProvider($providerId);
        } catch (PDOException $exception) {
            error_log('Error al cargar el panel del prestador: ' . $exception->getMessage());
            http_response_code(500);
            echo 'No se pudo cargar tu panel. Intentá nuevamente.';
            return;
        }

        $message = trim((string) ($_GET['mensaje'] ?? ''));
        $error = trim((string) ($_GET['error'] ?? ''));
        require __DIR__ . '/../views/provider/dashboard.php';
    }

    private function positiveAmount(mixed $value): float
    {
        $amountText = str_replace(',', '.', trim((string) $value));
        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $amountText) !== 1
            || (float) $amountText <= 0
            || (float) $amountText > 99999999.99) {
            throw new DomainException('Ingresá una tarifa mayor que cero.');
        }
        return (float) $amountText;
    }

    private function positiveId(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new DomainException('Seleccioná una opción válida.');
        }
        return (int) $id;
    }

    private function validDate(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new DomainException('Elegí fechas válidas para el período.');
        }
        return $value;
    }

    private function validTime(string $value): bool
    {
        $time = DateTimeImmutable::createFromFormat('!H:i', $value);
        return $time !== false && $time->format('H:i') === $value;
    }

    private function priceAndTariff(): array
    {
        $price = $this->positiveAmount($_POST['precio'] ?? '');
        $tariff = (string) ($_POST['tipo_tarifa'] ?? '');
        if (!in_array($tariff, ['visita', 'por_hora', 'turno'], true)) {
            throw new DomainException('Elegí si cobrás por visita, por hora o por turno.');
        }
        $durationText = str_replace(',', '.', trim((string) ($_POST['duracion_valor'] ?? '')));
        $durationUnit = (string) ($_POST['duracion_unidad'] ?? '');
        if (preg_match('/^\d+(?:\.\d{1,6})?$/', $durationText) !== 1
            || !in_array($durationUnit, ['min', 'hs'], true)) {
            throw new DomainException('Ingresá una duración válida y elegí minutos u horas.');
        }
        $durationNumber = (float) $durationText;
        if ($durationUnit === 'min') {
            if (floor($durationNumber) !== $durationNumber) {
                throw new DomainException('La duración en minutos debe ser un número entero.');
            }
            $duration = (int) $durationNumber;
        } else {
            $duration = (int) round($durationNumber * 60);
            if (abs($durationNumber * 60 - $duration) > 0.01) {
                throw new DomainException('La duración en horas debe equivaler a un número entero de minutos.');
            }
        }
        if ($duration < 5 || $duration > 10080 || $duration % 5 !== 0) {
            throw new DomainException('La duración debe ser de 5 minutos a 7 días, en intervalos de 5 minutos.');
        }
        return [$price, $tariff, $duration];
    }

    private function storePresentationImage(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_INI_SIZE
                || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_FORM_SIZE) {
                throw new DomainException('La imagen supera el tamaño máximo permitido de 5 MB.');
            }
            throw new DomainException('No se pudo cargar la imagen. Seleccioná un archivo JPG, PNG o WebP.');
        }

        $temporaryPath = (string) ($file['tmp_name'] ?? '');
        $fileSize = (int) ($file['size'] ?? 0);
        if ($fileSize < 1 || $fileSize > 5 * 1024 * 1024 || !is_uploaded_file($temporaryPath)) {
            throw new DomainException('La imagen debe pesar hasta 5 MB y haberse cargado correctamente.');
        }

        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($fileInfo === false) {
            throw new RuntimeException('No está disponible la validación MIME de archivos.');
        }
        $mimeType = finfo_file($fileInfo, $temporaryPath);
        finfo_close($fileInfo);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $imageInfo = @getimagesize($temporaryPath);
        if (!isset($extensions[$mimeType]) || $imageInfo === false
            || ($imageInfo['mime'] ?? '') !== $mimeType
            || (int) $imageInfo[0] > 6000 || (int) $imageInfo[1] > 4000) {
            throw new DomainException('La tarjeta debe ser una imagen JPG, PNG o WebP de hasta 6000 × 4000 píxeles.');
        }

        $directory = __DIR__ . '/../assets/uploads/tarjetas';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo preparar la carpeta de imágenes de tarjetas.');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
        if (!move_uploaded_file($temporaryPath, $directory . '/' . $filename)) {
            throw new RuntimeException('No se pudo guardar la imagen de la tarjeta.');
        }
        return 'assets/uploads/tarjetas/' . $filename;
    }

    private function deletePresentationImage(?string $relativePath): void
    {
        if ($relativePath === null
            || preg_match('~^assets/uploads/tarjetas/[a-f0-9]{32}\.(?:jpg|png|webp)$~', $relativePath) !== 1) {
            return;
        }
        $absolutePath = __DIR__ . '/../' . $relativePath;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function containsCategory(array $categories, int $categoryId): bool
    {
        foreach ($categories as $category) {
            if ((int) $category['id'] === $categoryId) {
                return true;
            }
        }
        return false;
    }

    private function containsOffer(array $offers, int $serviceId): bool
    {
        foreach ($offers as $offer) {
            if ((int) $offer['servicio_id'] === $serviceId) {
                return true;
            }
        }
        return false;
    }

    private function redirectWithMessage(string $key, string $message): never
    {
        header('Location: provider.php?' . $key . '=' . rawurlencode($message));
        exit;
    }
}
