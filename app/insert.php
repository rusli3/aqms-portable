<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=utf-8');

$parameterNames = [
    'pm1',
    'pm25',
    'pm10',
    'temp',
    'humd',
    'ampere',
    'baterai',
    'pompa',
    'volt',
    'press',
];

$values = [];
foreach ($parameterNames as $name) {
    $rawValue = $_GET[$name] ?? null;
    if ($rawValue === null || !is_numeric($rawValue)) {
        http_response_code(400);
        echo "invalid parameter: {$name}\n";
        exit;
    }

    $value = (float) $rawValue;
    if (!is_finite($value)) {
        http_response_code(400);
        echo "invalid parameter: {$name}\n";
        exit;
    }

    $values[$name] = $value;
}

try {
    $statement = aqms_database()->prepare(
        'INSERT INTO maintb '
        . '(waktu, pm1, pm25, pm10, temp, humd, ampere, baterai, pompa, volt, press) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $timestamp = date('Y-m-d H:i:s');
    $statement->bind_param(
        'sdddddddddd',
        $timestamp,
        $values['pm1'],
        $values['pm25'],
        $values['pm10'],
        $values['temp'],
        $values['humd'],
        $values['ampere'],
        $values['baterai'],
        $values['pompa'],
        $values['volt'],
        $values['press']
    );
    $statement->execute();

    echo "received\n";
} catch (Throwable $error) {
    error_log('AQMS ingest failed: ' . $error->getMessage());
    http_response_code(500);
    echo "storage error\n";
}
