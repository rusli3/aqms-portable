<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

function aqms_ingest_fail(int $status, string $message): never
{
    http_response_code($status);
    echo $message . "\n";
    exit;
}

function aqms_ip_in_cidr(string $ip, string $cidr): bool
{
    $parts = explode('/', trim($cidr), 2);
    $network = inet_pton($parts[0]);
    $address = inet_pton($ip);
    if ($network === false || $address === false || strlen($network) !== strlen($address)) {
        return false;
    }
    $prefix = isset($parts[1]) ? filter_var($parts[1], FILTER_VALIDATE_INT) : strlen($network) * 8;
    $maximum = strlen($network) * 8;
    if ($prefix === false || $prefix < 0 || $prefix > $maximum) {
        return false;
    }
    $fullBytes = intdiv($prefix, 8);
    $remainingBits = $prefix % 8;
    if ($fullBytes > 0 && substr($network, 0, $fullBytes) !== substr($address, 0, $fullBytes)) {
        return false;
    }
    if ($remainingBits === 0) {
        return true;
    }
    $mask = (0xff << (8 - $remainingBits)) & 0xff;
    return (ord($network[$fullBytes]) & $mask) === (ord($address[$fullBytes]) & $mask);
}

function aqms_ingest_source_allowed(string $ip, string $configuredCidrs): bool
{
    foreach (explode(',', $configuredCidrs) as $cidr) {
        if ($cidr !== '' && aqms_ip_in_cidr($ip, trim($cidr))) {
            return true;
        }
    }
    return false;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    aqms_ingest_fail(405, 'method not allowed');
}
if (strlen($_SERVER['QUERY_STRING'] ?? '') > 2048) {
    aqms_ingest_fail(414, 'query too long');
}

$remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$allowedCidrs = (string) aqms_env('AQMS_INGEST_ALLOWED_CIDRS', '127.0.0.1/32,::1/128');
if (!aqms_ingest_source_allowed($remoteAddress, $allowedCidrs)) {
    error_log('AQMS ingest denied for source ' . $remoteAddress);
    aqms_ingest_fail(403, 'source not allowed');
}

$expectedToken = (string) aqms_env('AQMS_INGEST_TOKEN', '');
$providedToken = (string) ($_SERVER['HTTP_X_AQMS_TOKEN'] ?? ($_GET['token'] ?? ''));
if ($expectedToken !== '' && !hash_equals($expectedToken, $providedToken)) {
    aqms_ingest_fail(401, 'invalid token');
}

$ranges = [
    'pm1' => [0.0, 5000.0], 'pm25' => [0.0, 5000.0], 'pm10' => [0.0, 5000.0],
    'temp' => [-50.0, 100.0], 'humd' => [0.0, 100.0], 'ampere' => [-100.0, 100.0],
    'baterai' => [0.0, 100.0], 'pompa' => [0.0, 4095.0], 'volt' => [0.0, 100.0],
    'press' => [0.0, 1500.0],
];
$allowedParameters = array_merge(array_keys($ranges), ['token']);
foreach (array_keys($_GET) as $name) {
    if (!in_array($name, $allowedParameters, true) || is_array($_GET[$name])) {
        aqms_ingest_fail(400, 'unknown parameter');
    }
}

$values = [];
foreach ($ranges as $name => [$minimum, $maximum]) {
    $rawValue = $_GET[$name] ?? null;
    if (!is_string($rawValue) || $rawValue === '' || !is_numeric($rawValue)) {
        aqms_ingest_fail(400, 'invalid parameter: ' . $name);
    }
    $value = (float) $rawValue;
    if (!is_finite($value) || $value < $minimum || $value > $maximum) {
        aqms_ingest_fail(422, 'out of range: ' . $name);
    }
    $values[$name] = $value;
}

try {
    $database = aqms_database();
    $lockResult = $database->query("SELECT GET_LOCK('aqms_ingest', 2) AS acquired")->fetch_assoc();
    if (!$lockResult || (int) $lockResult['acquired'] !== 1) {
        aqms_ingest_fail(503, 'ingest busy');
    }
    try {
        $minimumInterval = max(0.0, (float) aqms_env('AQMS_INGEST_MIN_INTERVAL_SECONDS', '1'));
        $latest = $database->query('SELECT waktu FROM maintb ORDER BY waktu DESC LIMIT 1')->fetch_assoc();
        if ($latest && $minimumInterval > 0) {
            $elapsed = microtime(true) - (float) strtotime((string) $latest['waktu']);
            if ($elapsed < $minimumInterval) {
                header('Retry-After: ' . (string) max(1, (int) ceil($minimumInterval - $elapsed)));
                aqms_ingest_fail(429, 'too many requests');
            }
        }
        $statement = $database->prepare(
            'INSERT INTO maintb (waktu, pm1, pm25, pm10, temp, humd, ampere, baterai, pompa, volt, press) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $timestamp = date('Y-m-d H:i:s');
        $statement->bind_param(
            'sdddddddddd', $timestamp, $values['pm1'], $values['pm25'], $values['pm10'],
            $values['temp'], $values['humd'], $values['ampere'], $values['baterai'],
            $values['pompa'], $values['volt'], $values['press']
        );
        $statement->execute();
    } finally {
        $database->query("SELECT RELEASE_LOCK('aqms_ingest')");
    }
    echo "received\n";
} catch (Throwable $error) {
    error_log('AQMS ingest failed: ' . $error->getMessage());
    aqms_ingest_fail(500, 'storage error');
}
