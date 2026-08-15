<?php

declare(strict_types=1);

function aqms_empty_dashboard_payload(): array
{
    return ['latest' => null, 'history' => [], 'ispu' => null, 'serverTime' => date(DATE_ATOM)];
}

function aqms_float_or_null($value): ?float
{
    return $value === null ? null : (float) $value;
}

function aqms_reading_row(array $row): array
{
    return [
        'time' => $row['waktu'] ?? null,
        'pm1' => aqms_float_or_null($row['pm1'] ?? null),
        'pm25' => aqms_float_or_null($row['pm25'] ?? null),
        'pm10' => aqms_float_or_null($row['pm10'] ?? null),
        'temp' => aqms_float_or_null($row['temp'] ?? null),
        'humidity' => aqms_float_or_null($row['humd'] ?? null),
        'current' => aqms_float_or_null($row['ampere'] ?? null),
        'battery' => aqms_float_or_null($row['baterai'] ?? null),
        'pump' => aqms_float_or_null($row['pompa'] ?? null),
        'voltage' => aqms_float_or_null($row['volt'] ?? null),
        'pressure' => aqms_float_or_null($row['press'] ?? null),
    ];
}

function aqms_ispu_category(int $index): string
{
    if ($index <= 50) {
        return 'BAIK';
    }
    if ($index <= 100) {
        return 'SEDANG';
    }
    if ($index <= 200) {
        return 'TIDAK SEHAT';
    }
    if ($index <= 300) {
        return 'SANGAT TIDAK SEHAT';
    }
    return 'BERBAHAYA';
}

function aqms_ispu_index(float $concentration, string $parameter): int
{
    $breakpoints = [
        'pm10' => [[0.0, 0], [50.0, 50], [150.0, 100], [350.0, 200], [420.0, 300], [500.0, 500]],
        'pm25' => [[0.0, 0], [15.5, 50], [55.4, 100], [150.4, 200], [250.4, 300], [500.0, 500]],
    ];

    $points = $breakpoints[$parameter] ?? $breakpoints['pm25'];
    $value = max(0.0, $concentration);

    for ($index = 1, $count = count($points); $index < $count; $index++) {
        [$upperConcentration, $upperIspu] = $points[$index];
        if ($value <= $upperConcentration) {
            [$lowerConcentration, $lowerIspu] = $points[$index - 1];
            $calculated = (($upperIspu - $lowerIspu) / ($upperConcentration - $lowerConcentration))
                * ($value - $lowerConcentration) + $lowerIspu;
            return (int) round($calculated);
        }
    }

    return 500;
}

function aqms_ispu_basis(array $timestamps): array
{
    $sampleCount = count($timestamps);
    $hours = $sampleCount > 1
        ? max(0.0, min(24.0, (end($timestamps) - $timestamps[0]) / 3600))
        : 0.0;
    $maximumGapSeconds = 0;
    for ($index = 1; $index < $sampleCount; $index++) {
        $maximumGapSeconds = max($maximumGapSeconds, $timestamps[$index] - $timestamps[$index - 1]);
    }

    // Agregator menghasilkan satu sampel per lima menit: 289 titik termasuk kedua ujung 24 jam.
    $expectedSamples = 289;
    $minimumSamples = 260;
    $complete = $hours >= 23.75 && $sampleCount >= $minimumSamples && $maximumGapSeconds <= 15 * 60;

    return [
        'hours' => round($hours, 1),
        'sampleCount' => $sampleCount,
        'expectedSampleCount' => $expectedSamples,
        'coveragePercent' => round(min(100.0, ($sampleCount / $expectedSamples) * 100), 1),
        'maxGapMinutes' => round($maximumGapSeconds / 60, 1),
        'complete' => $complete,
    ];
}

function aqms_ispu_payload(mysqli $database): ?array
{
    $result = $database->query(
        'SELECT waktu, pm25, pm10 FROM coretb '
        . 'WHERE waktu >= (SELECT DATE_SUB(MAX(waktu), INTERVAL 24 HOUR) FROM coretb) '
        . 'ORDER BY waktu ASC'
    );
    $timestamps = [];
    $pm25Sum = 0.0;
    $pm10Sum = 0.0;
    $sampleCount = 0;
    while ($row = $result->fetch_assoc()) {
        $timestamp = strtotime((string) $row['waktu']);
        if ($timestamp === false || $row['pm25'] === null || $row['pm10'] === null) {
            continue;
        }
        $timestamps[] = $timestamp;
        $pm25Sum += (float) $row['pm25'];
        $pm10Sum += (float) $row['pm10'];
        $sampleCount++;
    }

    if ($sampleCount === 0) {
        return null;
    }

    $pm25Average = $pm25Sum / $sampleCount;
    $pm10Average = $pm10Sum / $sampleCount;
    $pm25Index = aqms_ispu_index($pm25Average, 'pm25');
    $pm10Index = aqms_ispu_index($pm10Average, 'pm10');
    $basis = aqms_ispu_basis($timestamps);

    return [
        'basis' => $basis,
        'pm25' => [
            'value' => $pm25Index,
            'category' => aqms_ispu_category($pm25Index),
            'average' => round($pm25Average, 2),
        ],
        'pm10' => [
            'value' => $pm10Index,
            'category' => aqms_ispu_category($pm10Index),
            'average' => round($pm10Average, 2),
        ],
    ];
}

function aqms_dashboard_payload(mysqli $database): array
{
    $latestResult = $database->query('SELECT * FROM maintb ORDER BY waktu DESC LIMIT 1');
    $latestRow = $latestResult->fetch_assoc();
    $historyResult = $database->query('SELECT waktu, pm1, pm25, pm10 FROM coretb ORDER BY waktu DESC LIMIT 36');
    $history = [];

    while ($row = $historyResult->fetch_assoc()) {
        $history[] = [
            'time' => $row['waktu'],
            'pm1' => aqms_float_or_null($row['pm1']),
            'pm25' => aqms_float_or_null($row['pm25']),
            'pm10' => aqms_float_or_null($row['pm10']),
        ];
    }

    return [
        'latest' => $latestRow ? aqms_reading_row($latestRow) : null,
        'history' => array_reverse($history),
        'ispu' => aqms_ispu_payload($database),
        'serverTime' => date(DATE_ATOM),
    ];
}
