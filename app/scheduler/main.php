<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

try {
    $database = aqms_database();
    $lock = $database->query("SELECT GET_LOCK('aqms_scheduler', 3) AS acquired")->fetch_assoc();
    if (!$lock || (int) $lock['acquired'] !== 1) {
        fwrite(STDERR, "scheduler busy\n");
        exit(1);
    }

    $now = new DateTimeImmutable('now');
    $minute = (int) $now->format('i');
    $bucketEnd = $now->setTime((int) $now->format('H'), $minute - ($minute % 5), 0);
    $from = $bucketEnd->modify('-5 minutes');

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
        . 'FROM maintb WHERE waktu > ? AND waktu <= ?'
    );
    $fromText = $from->format('Y-m-d H:i:s');
    $bucketText = $bucketEnd->format('Y-m-d H:i:s');
    $averageStatement->bind_param('ss', $fromText, $bucketText);
    $averageStatement->execute();
    $averages = $averageStatement->get_result()->fetch_assoc();

    if ($averages === null || $averages['pm1'] === null) {
        $database->query("SELECT RELEASE_LOCK('aqms_scheduler')");
        echo "no samples for bucket {$bucketText}\n";
        exit(0);
    }

    $insertStatement = $database->prepare(
        'INSERT INTO coretb '
        . '(waktu, pm1, pm25, pm10, temp, humd, ampere, baterai, pompa, volt, press) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE waktu = VALUES(waktu)'
    );
    $insertStatement->bind_param(
        'sdddddddddd',
        $bucketText,
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
    $database->query("SELECT RELEASE_LOCK('aqms_scheduler')");

    echo $insertStatement->affected_rows === 1
        ? "local average stored for {$bucketText}\n"
        : "bucket {$bucketText} already stored\n";
} catch (Throwable $error) {
    if (isset($database) && $database instanceof mysqli) {
        try {
            $database->query("SELECT RELEASE_LOCK('aqms_scheduler')");
        } catch (Throwable $ignored) {
        }
    }
    error_log('AQMS scheduler failed: ' . $error->getMessage());
    fwrite(STDERR, "scheduler error\n");
    exit(1);
}
