<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

const AQMS_DATA_SESSION_SECONDS = 3600;

function aqms_valid_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function aqms_data_session_start(): void
{
    session_name('AQMSCONTROL');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function aqms_consume_access_token(string $token): bool
{
    if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
        return false;
    }

    $tokenPath = sys_get_temp_dir() . '/aqms-data-token-' . hash('sha256', $token) . '.json';
    $handle = @fopen($tokenPath, 'r');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        return false;
    }

    clearstatcache(true, $tokenPath);
    if (!file_exists($tokenPath)) {
        flock($handle, LOCK_UN);
        fclose($handle);
        return false;
    }

    $raw = stream_get_contents($handle);
    $record = is_string($raw) ? json_decode($raw, true) : null;
    $valid = is_array($record) && (int) ($record['expires'] ?? 0) >= time();
    @unlink($tokenPath);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $valid;
}

function aqms_raw_statement(
    mysqli $database,
    ?string $startTime,
    ?string $endTime,
    ?int $limit = null,
    bool $ascending = false
): mysqli_stmt {
    $sql = 'SELECT waktu, pm1, pm25, pm10, temp, humd, ampere, baterai, pompa, volt, press FROM maintb';
    if ($startTime !== null && $endTime !== null) {
        $sql .= ' WHERE waktu BETWEEN ? AND ?';
    }
    $sql .= ' ORDER BY waktu ' . ($ascending ? 'ASC' : 'DESC');
    if ($limit !== null) {
        $sql .= ' LIMIT ' . $limit;
    }

    $statement = $database->prepare($sql);
    if ($startTime !== null && $endTime !== null) {
        $statement->bind_param('ss', $startTime, $endTime);
    }
    $statement->execute();
    return $statement;
}

aqms_data_session_start();
$accessToken = (string) ($_GET['access'] ?? '');
if ($accessToken !== '' && aqms_consume_access_token($accessToken)) {
    $_SESSION['data_access_until'] = time() + AQMS_DATA_SESSION_SECONDS;
    session_regenerate_id(true);
    header('Location: ./', true, 303);
    exit;
}

$authorizedUntil = (int) ($_SESSION['data_access_until'] ?? 0);
if ($authorizedUntil < time()) {
    unset($_SESSION['data_access_until']);
    http_response_code(403);
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#07110f">
        <title>Akses data diperlukan</title>
        <link rel="stylesheet" href="display.css">
    </head>
    <body class="access-denied-page">
        <main class="access-denied-card">
            <span class="eyebrow">ARSIP LOKAL AQMS</span>
            <h1>Akses diperlukan</h1>
            <p>Buka menu <strong>AKSES DATA</strong> pada layar AQMS, masukkan PIN, lalu pindai QR halaman data.</p>
        </main>
    </body>
    </html>
    <?php
    exit;
}

header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

$today = date('Y-m-d');
$startDate = (string) ($_GET['from'] ?? $today);
$endDate = (string) ($_GET['to'] ?? $startDate);
$allData = ($_GET['all'] ?? '') === '1';
$dateError = null;
$rows = null;

if (!$allData && (!aqms_valid_date($startDate) || !aqms_valid_date($endDate) || $startDate > $endDate)) {
    $dateError = 'Rentang tanggal tidak valid.';
} else {
    $startTime = $allData ? null : $startDate . ' 00:00:00';
    $endTime = $allData ? null : $endDate . ' 23:59:59';
    $database = aqms_database();

    if (($_GET['format'] ?? '') === 'csv') {
        $statement = aqms_raw_statement($database, $startTime, $endTime, null, true);
        $filenameRange = $allData ? 'semua-data' : $startDate . '-' . $endDate;
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="aqms-mentah-' . $filenameRange . '.csv"');
        header('Cache-Control: no-store, max-age=0');
        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['waktu', 'pm1', 'pm25', 'pm10', 'temp', 'humd', 'ampere', 'baterai', 'pompa', 'volt', 'press']);

        $statement->bind_result($waktu, $pm1, $pm25, $pm10, $temp, $humd, $ampere, $baterai, $pompa, $volt, $press);
        while ($statement->fetch()) {
            fputcsv($output, [$waktu, $pm1, $pm25, $pm10, $temp, $humd, $ampere, $baterai, $pompa, $volt, $press]);
        }
        fclose($output);
        exit;
    }

    $rows = aqms_raw_statement($database, $startTime, $endTime, 1000)->get_result();
}

$pageTitle = htmlspecialchars((string) aqms_env('AQMS_DISPLAY_NAME', 'PARTIKULAT 02'), ENT_QUOTES, 'UTF-8');
$queryParameters = ['from' => $startDate, 'to' => $endDate, 'format' => 'csv'];
if ($allData) {
    $queryParameters['all'] = '1';
}
$query = http_build_query($queryParameters);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07110f">
    <title>Data mentah — <?= $pageTitle ?></title>
    <link rel="stylesheet" href="display.css">
</head>
<body>
    <main class="history-shell">
        <header class="history-header">
            <div>
                <span class="eyebrow">ARSIP LOKAL AQMS</span>
                <h1>Data mentah</h1>
                <p><?= $pageTitle ?> · seluruh parameter sensor</p>
            </div>
            <span class="session-badge">Akses aktif 60 menit</span>
        </header>

        <section class="filter-panel" aria-label="Filter data mentah">
            <form method="get">
                <label>Dari<input class="date-range-input" type="date" name="from" value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Sampai<input class="date-range-input" type="date" name="to" value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>"></label>
                <label class="all-data-toggle"><input id="allDataToggle" type="checkbox" name="all" value="1" <?= $allData ? 'checked' : '' ?>> Semua Data</label>
                <button type="submit">Tampilkan</button>
                <?php if ($dateError === null): ?>
                    <a class="export-link" href="?<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>">Unduh CSV</a>
                <?php endif; ?>
            </form>
            <p>Tabel menampilkan 1.000 rekaman terbaru. CSV memuat seluruh rekaman pada pilihan periode.</p>
        </section>

        <section class="table-panel">
            <?php if ($dateError !== null): ?>
                <div class="notice is-error"><?= htmlspecialchars($dateError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php elseif ($rows === null || $rows->num_rows === 0): ?>
                <div class="notice">Tidak ada data pada pilihan ini.</div>
            <?php else: ?>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Waktu</th><th>PM1</th><th>PM2.5</th><th>PM10</th><th>Suhu</th><th>RH</th><th>Arus</th><th>Baterai</th><th>Pompa</th><th>Volt</th><th>Tekanan</th></tr></thead>
                        <tbody>
                        <?php while ($row = $rows->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $row['waktu'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['pm1'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['pm25'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['pm10'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['temp'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['humd'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['ampere'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['baterai'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['pompa'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['volt'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['press'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <script src="display.js"></script>
</body>
</html>
