<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function data_access_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function wifi_qr_escape(string $value): string
{
    return str_replace(
        ['\\', ';', ',', ':', '"'],
        ['\\\\', '\\;', '\\,', '\\:', '\\"'],
        $value
    );
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    data_access_response(405, ['message' => 'Metode tidak diizinkan.']);
}

$pinHash = aqms_env('AQMS_ADMIN_PIN_HASH');
$wifiSsid = aqms_env('AQMS_WIFI_SSID');
$wifiPsk = aqms_env('AQMS_WIFI_PSK');
$dataUrl = aqms_env('AQMS_DATA_URL', 'http://192.168.100.135/display/');
if ($pinHash === null || $wifiSsid === null || $wifiPsk === null || $dataUrl === null) {
    data_access_response(503, ['message' => 'Akses data belum dikonfigurasi.']);
}

if (filter_var($dataUrl, FILTER_VALIDATE_URL) === false
    || !in_array((string) parse_url($dataUrl, PHP_URL_SCHEME), ['http', 'https'], true)
) {
    data_access_response(503, ['message' => 'Alamat halaman data tidak valid.']);
}

$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if (!str_starts_with($contentType, 'application/json')) {
    data_access_response(415, ['message' => 'Permintaan harus menggunakan JSON.']);
}

$rawBody = file_get_contents('php://input', false, null, 0, 2049);
if ($rawBody === false || strlen($rawBody) > 2048) {
    data_access_response(413, ['message' => 'Permintaan terlalu besar.']);
}

$payload = json_decode($rawBody, true);
$pin = is_array($payload) ? ($payload['pin'] ?? null) : null;
if (!is_string($pin) || preg_match('/^[0-9]{4,8}$/D', $pin) !== 1) {
    data_access_response(422, ['message' => 'PIN harus terdiri dari 4–8 digit.']);
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
    data_access_response(403, [
        'message' => 'Sesi kontrol tidak valid. Memuat ulang dashboard.',
        'code' => 'invalid_session',
    ]);
}

$remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$ratePath = sys_get_temp_dir() . '/aqms-admin-pin-' . hash('sha256', $remoteAddress) . '.json';
$rateHandle = fopen($ratePath, 'c+');
if ($rateHandle === false || !flock($rateHandle, LOCK_EX)) {
    data_access_response(503, ['message' => 'Pemeriksaan keamanan tidak tersedia.']);
}

$rateRaw = stream_get_contents($rateHandle);
$rate = is_string($rateRaw) ? json_decode($rateRaw, true) : null;
$rate = is_array($rate) ? $rate : ['failures' => 0, 'lock_until' => 0];
$now = time();

if ((int) ($rate['lock_until'] ?? 0) > $now) {
    flock($rateHandle, LOCK_UN);
    fclose($rateHandle);
    data_access_response(429, ['message' => 'Terlalu banyak percobaan. Tunggu lima menit.']);
}

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
    data_access_response(403, ['message' => 'PIN tidak benar.']);
}

ftruncate($rateHandle, 0);
rewind($rateHandle);
fwrite($rateHandle, '{"failures":0,"lock_until":0}');
fflush($rateHandle);
flock($rateHandle, LOCK_UN);
fclose($rateHandle);

$token = bin2hex(random_bytes(32));
$tokenLifetime = 600;
$staleTokenPaths = glob(sys_get_temp_dir() . '/aqms-data-token-*.json') ?: [];
foreach ($staleTokenPaths as $staleTokenPath) {
    $modifiedAt = @filemtime($staleTokenPath);
    if ($modifiedAt !== false && $modifiedAt < $now - $tokenLifetime) {
        @unlink($staleTokenPath);
    }
}
$tokenPath = sys_get_temp_dir() . '/aqms-data-token-' . hash('sha256', $token) . '.json';
$tokenBody = json_encode(['expires' => $now + $tokenLifetime]);
$tokenHandle = $tokenBody === false ? false : fopen($tokenPath, 'x');
if (is_resource($tokenHandle)) {
    chmod($tokenPath, 0600);
}
if ($tokenHandle === false
    || !flock($tokenHandle, LOCK_EX)
    || fwrite($tokenHandle, $tokenBody) !== strlen($tokenBody)
) {
    if (is_resource($tokenHandle)) {
        fclose($tokenHandle);
    }
    @unlink($tokenPath);
    data_access_response(503, ['message' => 'Tautan akses tidak dapat dibuat.']);
}
fflush($tokenHandle);
flock($tokenHandle, LOCK_UN);
fclose($tokenHandle);

$separator = str_contains($dataUrl, '?') ? '&' : '?';
$accessUrl = $dataUrl . $separator . http_build_query(['access' => $token]);
$hidden = in_array(strtolower((string) aqms_env('AQMS_WIFI_HIDDEN', 'false')), ['1', 'true', 'yes', 'on'], true);
$wifiPayload = 'WIFI:T:WPA;S:' . wifi_qr_escape($wifiSsid)
    . ';P:' . wifi_qr_escape($wifiPsk)
    . ';H:' . ($hidden ? 'true' : 'false') . ';;';

session_regenerate_id(true);
data_access_response(200, [
    'wifiPayload' => $wifiPayload,
    'wifiSsid' => $wifiSsid,
    'accessUrl' => $accessUrl,
    'expiresIn' => $tokenLifetime,
]);
