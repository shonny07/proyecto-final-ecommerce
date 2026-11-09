<?php
declare(strict_types=1);

require_once __DIR__ . '/controllers/messagecontroller.php';
(new MessageController())->index();
