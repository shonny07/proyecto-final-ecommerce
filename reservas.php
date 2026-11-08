<?php
declare(strict_types=1);

require_once __DIR__ . '/controllers/reservationcontroller.php';
(new ReservationController())->index();
