<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

try {
    $database = aqms_database();
    $now = new DateTimeImmutable('now');
    $from = $now->modify('-5 minutes');

    $averageStatement = $database->prepare(
        'SELECT '
        . 'ROUND(AVG(pm1), 2) AS pm1, '
        . 'ROUND(AVG(pm25), 2) AS pm25, '
        . 'ROUND(AVG(pm10), 2) AS pm10, '
        . 'ROUND(AVG(temp), 2) AS temp, '
        . 'ROUND(AVG(humd), 2) AS humd, '
        . 'ROUND(AVG(ampere), 2) AS ampere, '
        . 'ROUND(AVG(baterai), 2) AS baterai, '
        . 'ROUND(AVG(pompa), 2) AS pompa, '
        . 'ROUND(AVG(volt), 2) AS volt, '
        . 'ROUND(AVG(press), 2) AS press '
        . 'FROM maintb WHERE waktu BETWEEN ? AND ?'
    );
    $fromText = $from->format('Y-m-d H:i:s');
    $nowText = $now->format('Y-m-d H:i:s');
    $averageStatement->bind_param('ss', $fromText, $nowText);
    $averageStatement->execute();
    $averages = $averageStatement->get_result()->fetch_assoc();

    if ($averages === null || $averages['pm1'] === null) {
        echo "no samples in the last five minutes\n";
        exit(0);
    }

    $insertStatement = $database->prepare(
        'INSERT INTO coretb '
        . '(waktu, pm1, pm25, pm10, temp, humd, ampere, baterai, pompa, volt, press) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertStatement->bind_param(
        'sdddddddddd',
        $nowText,
        $averages['pm1'],
        $averages['pm25'],
        $averages['pm10'],
        $averages['temp'],
        $averages['humd'],
        $averages['ampere'],
        $averages['baterai'],
        $averages['pompa'],
        $averages['volt'],
        $averages['press']
    );
    $insertStatement->execute();

    echo "local average stored\n";
} catch (Throwable $error) {
    error_log('AQMS scheduler failed: ' . $error->getMessage());
    fwrite(STDERR, "scheduler error\n");
    exit(1);
}
