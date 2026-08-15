<?php

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function aqms_env(string $name, ?string $default = null): ?string
{
    $value = getenv($name);

    return $value === false || $value === '' ? $default : $value;
}

function aqms_database(): mysqli
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    $password = aqms_env('AQMS_DB_PASSWORD');
    if ($password === null) {
        throw new RuntimeException('AQMS_DB_PASSWORD is not configured');
    }

    $connection = new mysqli(
        aqms_env('AQMS_DB_HOST', '127.0.0.1'),
        aqms_env('AQMS_DB_USER', 'aqms'),
        $password,
        aqms_env('AQMS_DB_NAME', 'partikulat'),
        (int) aqms_env('AQMS_DB_PORT', '3306')
    );
    $connection->set_charset('utf8mb4');

    return $connection;
}

date_default_timezone_set(aqms_env('AQMS_TIMEZONE', 'Asia/Jakarta'));
