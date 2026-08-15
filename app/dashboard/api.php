<?php

declare(strict_types=1);

require_once __DIR__ . '/con_.php';
require_once __DIR__ . '/lib/dashboard-data.php';

date_default_timezone_set(aqms_env('AQMS_TIMEZONE', 'Asia/Jakarta'));
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

try {
    echo json_encode(
        aqms_dashboard_payload($koneksi),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
} catch (Throwable $error) {
    error_log('AQMS dashboard API failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Data belum dapat dibaca']);
}
