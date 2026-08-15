<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function aqms_valid_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function aqms_history_statement(mysqli $database, string $startTime, string $endTime, ?int $limit = null): mysqli_stmt
{
    $sql = 'SELECT waktu, pm1, pm25, pm10, temp, humd, ampere, baterai, pompa, volt, press '
        . 'FROM coretb WHERE waktu BETWEEN ? AND ? ORDER BY waktu DESC';
    if ($limit !== null) {
        $sql .= ' LIMIT ' . $limit;
    }
    $statement = $database->prepare($sql);
    $statement->bind_param('ss', $startTime, $endTime);
    $statement->execute();
    return $statement;
}

$today = date('Y-m-d');
$startDate = (string) ($_GET['from'] ?? $today);
$endDate = (string) ($_GET['to'] ?? $startDate);
$dateError = null;
$rows = null;

if (!aqms_valid_date($startDate) || !aqms_valid_date($endDate) || $startDate > $endDate) {
    $dateError = 'Rentang tanggal tidak valid.';
} else {
    $start = new DateTimeImmutable($startDate);
    $end = new DateTimeImmutable($endDate);
    if ($start->diff($end)->days > 31) {
        $dateError = 'Rentang tampilan maksimal 31 hari.';
    } else {
        $startTime = $startDate . ' 00:00:00';
        $endTime = $endDate . ' 23:59:59';
        $database = aqms_database();

        if (($_GET['format'] ?? '') === 'csv') {
            $statement = aqms_history_statement($database, $startTime, $endTime);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="aqms-' . $startDate . '-' . $endDate . '.csv"');
            header('Cache-Control: no-store, max-age=0');
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['waktu', 'pm1', 'pm25', 'pm10', 'temp', 'humd', 'ampere', 'baterai', 'pompa', 'volt', 'press']);
            $result = $statement->get_result();
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, array_values($row));
            }
            fclose($output);
            exit;
        }

        $rows = aqms_history_statement($database, $startTime, $endTime, 1000)->get_result();
    }
}

$pageTitle = htmlspecialchars((string) aqms_env('AQMS_DISPLAY_NAME', 'PARTIKULAT 02'), ENT_QUOTES, 'UTF-8');
$query = http_build_query(['from' => $startDate, 'to' => $endDate, 'format' => 'csv']);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07110f">
    <title>Riwayat — <?= $pageTitle ?></title>
    <link rel="stylesheet" href="display.css">
</head>
<body>
    <main class="history-shell">
        <header class="history-header">
            <div>
                <span class="eyebrow">ARSIP LOKAL AQMS</span>
                <h1>Riwayat partikulat</h1>
                <p><?= $pageTitle ?> · data agregat lima menit</p>
            </div>
            <a class="back-link" href="../dashboard/">Kembali ke monitor</a>
        </header>

        <section class="filter-panel" aria-label="Filter riwayat">
            <form method="get">
                <label>Dari<input type="date" name="from" value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>" required></label>
                <label>Sampai<input type="date" name="to" value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>" required></label>
                <button type="submit">Tampilkan</button>
                <?php if ($dateError === null): ?>
                    <a class="export-link" href="?<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>">Unduh CSV</a>
                <?php endif; ?>
            </form>
            <p>Maksimal 31 hari per tampilan; tabel dibatasi 1.000 baris. CSV memuat seluruh baris dalam rentang.</p>
        </section>

        <section class="table-panel">
            <?php if ($dateError !== null): ?>
                <div class="notice is-error"><?= htmlspecialchars($dateError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php elseif ($rows === null || $rows->num_rows === 0): ?>
                <div class="notice">Tidak ada data pada rentang ini.</div>
            <?php else: ?>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Waktu</th><th>PM1</th><th>PM2.5</th><th>PM10</th><th>Suhu</th><th>RH</th><th>Tekanan</th></tr></thead>
                        <tbody>
                        <?php while ($row = $rows->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $row['waktu'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['pm1'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['pm25'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['pm10'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['temp'], ENT_QUOTES, 'UTF-8') ?> °C</td>
                                <td><?= htmlspecialchars((string) $row['humd'], ENT_QUOTES, 'UTF-8') ?>%</td>
                                <td><?= htmlspecialchars((string) $row['press'], ENT_QUOTES, 'UTF-8') ?> hPa</td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
