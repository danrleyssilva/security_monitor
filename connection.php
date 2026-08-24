<?php

declare(strict_types=1);

// Mantido para compatibilidade com scripts externos que ainda façam require deste arquivo.
require_once __DIR__ . '/bootstrap.php';

return App\Config\Database::connect();
