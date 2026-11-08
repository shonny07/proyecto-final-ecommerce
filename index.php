<?php
declare(strict_types=1);

require_once __DIR__ . '/controllers/homecontroller.php';

$controller = new HomeController();
$controller->index();
