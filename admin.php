<?php
declare(strict_types=1);

require_once __DIR__ . '/controllers/admincontroller.php';
(new AdminController())->categories();
