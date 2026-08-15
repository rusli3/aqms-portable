<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

try {
    aqms_database()->query('SELECT 1');
    echo "ok\n";
} catch (Throwable $error) {
    error_log('AQMS health check failed: ' . $error->getMessage());
    http_response_code(503);
    echo "unavailable\n";
}
