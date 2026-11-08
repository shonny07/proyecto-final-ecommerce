<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/servicemodel.php';
require_once __DIR__ . '/../models/usermodel.php';
require_once __DIR__ . '/../models/presentationmodel.php';
require_once __DIR__ . '/../config/security.php';

class HomeController
{
    public function index(): void
    {
        startSecureSession();
        $search = trim((string) ($_GET['q'] ?? ''));
        $providerId = filter_var($_GET['prestador_id'] ?? null, FILTER_VALIDATE_INT);
        $providerId = $providerId !== false && $providerId !== null && $providerId > 0 ? (int) $providerId : null;
        $services = [];
        $presentations = [];
        $databaseError = false;
        $currentUser = $_SESSION['usuario'] ?? null;
        $needsAdminSetup = false;

        try {
            $serviceModel = new ServiceModel();
            $services = $serviceModel->search($search, $providerId);
            $presentations = (new PresentationModel())->cardsForHeader();
            $needsAdminSetup = !(new UserModel())->hasAdministrator();
        } catch (PDOException $exception) {
            error_log('Error de conexión o consulta en e_commerce: ' . $exception->getMessage());
            $databaseError = true;
        }

        require __DIR__ . '/../views/home.php';
    }
}
