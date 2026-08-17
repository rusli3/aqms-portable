<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function power_response(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['message' => $message], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function power_enabled(): bool
{
    return in_array(
        strtolower((string) aqms_env('AQMS_POWER_CONTROLS_ENABLED', 'false')),
        ['1', 'true', 'yes', 'on'],
        true
    );
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    power_response(405, 'Metode tidak diizinkan.');
}

if (!power_enabled() || aqms_env('AQMS_ADMIN_PIN_HASH') === null) {
    power_response(503, 'Kontrol daya belum dikonfigurasi.');
}

$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if (!str_starts_with($contentType, 'application/json')) {
    power_response(415, 'Permintaan harus menggunakan JSON.');
}

$rawBody = file_get_contents('php://input', false, null, 0, 2049);
if ($rawBody === false || strlen($rawBody) > 2048) {
    power_response(413, 'Permintaan terlalu besar.');
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    power_response(400, 'Format permintaan tidak valid.');
}

session_name('AQMSCONTROL');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

$csrf = (string) ($_SERVER['HTTP_X_AQMS_CSRF'] ?? '');
$sessionCsrf = $_SESSION['power_csrf'] ?? null;
if (!is_string($sessionCsrf) || $csrf === '' || !hash_equals($sessionCsrf, $csrf)) {
    power_response(403, 'Sesi kontrol tidak valid. Muat ulang dashboard.');
}

$action = $payload['action'] ?? null;
$pin = $payload['pin'] ?? null;
if (!is_string($action) || !in_array($action, ['reboot', 'shutdown'], true)) {
    power_response(422, 'Tindakan tidak valid.');
}
if (!is_string($pin) || preg_match('/^[0-9]{4,8}$/D', $pin) !== 1) {
    power_response(422, 'PIN harus terdiri dari 4–8 digit.');
}

$remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$ratePath = sys_get_temp_dir() . '/aqms-admin-pin-' . hash('sha256', $remoteAddress) . '.json';
$rateHandle = fopen($ratePath, 'c+');
if ($rateHandle === false || !flock($rateHandle, LOCK_EX)) {
    power_response(503, 'Pemeriksaan keamanan tidak tersedia.');
}

$rateRaw = stream_get_contents($rateHandle);
$rate = is_string($rateRaw) ? json_decode($rateRaw, true) : null;
$rate = is_array($rate) ? $rate : ['failures' => 0, 'lock_until' => 0];
$now = time();

if ((int) ($rate['lock_until'] ?? 0) > $now) {
    flock($rateHandle, LOCK_UN);
    fclose($rateHandle);
    power_response(429, 'Terlalu banyak percobaan. Tunggu lima menit.');
}

$pinHash = (string) aqms_env('AQMS_ADMIN_PIN_HASH', '');
if (!password_verify($pin, $pinHash)) {
    $failures = (int) ($rate['failures'] ?? 0) + 1;
    $rate = [
        'failures' => $failures >= 5 ? 0 : $failures,
        'lock_until' => $failures >= 5 ? $now + 300 : 0,
    ];
    ftruncate($rateHandle, 0);
    rewind($rateHandle);
    fwrite($rateHandle, json_encode($rate));
    fflush($rateHandle);
    flock($rateHandle, LOCK_UN);
    fclose($rateHandle);
    usleep(350000);
    power_response(403, 'PIN tidak benar.');
}

ftruncate($rateHandle, 0);
rewind($rateHandle);
fwrite($rateHandle, '{"failures":0,"lock_until":0}');
fflush($rateHandle);
flock($rateHandle, LOCK_UN);
fclose($rateHandle);

$requestPath = (string) aqms_env('AQMS_CONTROL_REQUEST_PATH', '/run/aqms-control/request');
$requestDirectory = dirname($requestPath);
if (!is_dir($requestDirectory) || !is_writable($requestDirectory)) {
    power_response(503, 'Broker daya host tidak tersedia.');
}
if (file_exists($requestPath) || is_link($requestPath)) {
    power_response(409, 'Permintaan daya lain sedang diproses.');
}

$temporaryPath = tempnam($requestDirectory, '.request-');
if ($temporaryPath === false) {
    power_response(503, 'Permintaan tidak dapat dibuat.');
}

$request = sprintf("v1 %s %d %s\n", $action, $now, bin2hex(random_bytes(16)));
if (file_put_contents($temporaryPath, $request, LOCK_EX) !== strlen($request)) {
    @unlink($temporaryPath);
    power_response(503, 'Permintaan tidak dapat disimpan.');
}
chmod($temporaryPath, 0600);

if (!link($temporaryPath, $requestPath)) {
    @unlink($temporaryPath);
    power_response(409, 'Permintaan daya lain sedang diproses.');
}
unlink($temporaryPath);

session_regenerate_id(true);
power_response(202, $action === 'reboot'
    ? 'Perintah mulai ulang diterima.'
    : 'Perintah shutdown diterima.');
