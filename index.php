<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

(new App\Controllers\MonitoringController())->index();
