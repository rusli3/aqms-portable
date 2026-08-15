<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/dashboard/lib/dashboard-data.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

expect(aqms_ispu_index(15.5, 'pm25') === 50, 'PM2.5 breakpoint 15.5');
expect(aqms_ispu_index(55.4, 'pm25') === 100, 'PM2.5 breakpoint 55.4');
expect(aqms_ispu_index(50, 'pm10') === 50, 'PM10 breakpoint 50');
expect(aqms_ispu_index(150, 'pm10') === 100, 'PM10 breakpoint 150');

$start = strtotime('2026-01-01 00:00:00');
$continuous = [];
for ($index = 0; $index <= 288; $index++) {
    $continuous[] = $start + $index * 300;
}
expect(aqms_ispu_basis($continuous)['complete'] === true, 'continuous 24-hour series');
expect(aqms_ispu_basis([$start, $start + 23 * 3600])['complete'] === false, 'two samples cannot be complete');

$withGap = $continuous;
array_splice($withGap, 100, 4);
expect(aqms_ispu_basis($withGap)['complete'] === false, 'gap over 15 minutes cannot be complete');

echo "ISPU tests passed\n";
