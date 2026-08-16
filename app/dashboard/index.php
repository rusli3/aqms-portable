<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

date_default_timezone_set(aqms_env('AQMS_TIMEZONE', 'Asia/Jakarta'));
$powerControlsEnabled = in_array(
    strtolower((string) aqms_env('AQMS_POWER_CONTROLS_ENABLED', 'false')),
    ['1', 'true', 'yes', 'on'],
    true
) && aqms_env('AQMS_ADMIN_PIN_HASH') !== null;

session_name('AQMSCONTROL');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();
if (!isset($_SESSION['power_csrf']) || !is_string($_SESSION['power_csrf'])) {
    $_SESSION['power_csrf'] = bin2hex(random_bytes(24));
}

$powerCsrf = htmlspecialchars($_SESSION['power_csrf'], ENT_QUOTES, 'UTF-8');
$pageTitle = htmlspecialchars((string) aqms_env('AQMS_DISPLAY_NAME', 'PARTIKULAT 02'), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#07110f">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="css/dashboard-7.css">
</head>
<body>
    <main class="dashboard-shell" aria-label="Dashboard pemantauan kualitas udara">
        <header class="topbar">
            <div class="brand-lockup">
                <div class="brand-mark" aria-hidden="true">AQ</div>
                <div class="brand-copy">
                    <span class="eyebrow">AIR QUALITY MONITORING</span>
                    <h1><?= $pageTitle ?></h1>
                </div>
            </div>

            <div class="topbar-status">
                <div class="sensor-link" id="sensorLink">
                    <span class="status-dot" aria-hidden="true"></span>
                    <span id="sensorLinkText">Memeriksa sensor</span>
                </div>
                <div class="clock-block">
                    <strong id="liveClock">--:--</strong>
                    <span id="liveDate">---</span>
                </div>
                <button class="icon-button" id="fullscreenButton" type="button" aria-label="Tampilkan layar penuh" title="Layar penuh">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5"/></svg>
                </button>
                <button class="icon-button power-menu-button" id="powerMenuButton" type="button" aria-label="Buka menu daya" title="Menu daya">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v9"/><path d="M6.7 6.7a7.5 7.5 0 1 0 10.6 0"/></svg>
                </button>
            </div>
        </header>

        <section class="dashboard-grid">
            <section class="main-panel" aria-label="Data partikulat">
                <div class="particle-grid">
                    <article class="metric-card metric-primary" id="primaryCard">
                        <div class="metric-card-head">
                            <span class="metric-label">PM2.5</span>
                            <span class="quality-pill" id="qualityLabel">--</span>
                        </div>
                        <div class="primary-reading">
                            <strong id="pm25Value">--</strong>
                            <span>&micro;g/m<sup>3</sup></span>
                        </div>
                        <div class="ispu-primary-row">
                            <span id="pm25IspuLabel">ISPU 24 JAM</span>
                            <strong id="pm25IspuValue">--</strong>
                            <small id="pm25IspuCategory">--</small>
                        </div>
                        <div class="level-track" aria-hidden="true"><span id="levelMarker"></span></div>
                        <p id="ispuBasis">Menyiapkan rerata pengukuran 24 jam.</p>
                    </article>

                    <article class="metric-card metric-secondary">
                        <span class="metric-label">PM1</span>
                        <strong id="pm1Value">--</strong>
                        <span class="metric-unit">&micro;g/m<sup>3</sup></span>
                        <i class="metric-accent accent-cyan"></i>
                    </article>

                    <article class="metric-card metric-secondary">
                        <span class="metric-label">PM10</span>
                        <strong id="pm10Value">--</strong>
                        <span class="metric-unit">&micro;g/m<sup>3</sup></span>
                        <div class="ispu-compact">
                            <span id="pm10IspuLabel">ISPU 24 JAM</span>
                            <strong id="pm10IspuValue">--</strong>
                            <small id="pm10IspuCategory">--</small>
                        </div>
                        <i class="metric-accent accent-amber"></i>
                    </article>
                </div>

                <article class="chart-card">
                    <div class="section-heading">
                        <div>
                            <span class="eyebrow">TREN PARTIKULAT</span>
                            <h2>Riwayat pembacaan</h2>
                        </div>
                        <div class="chart-legend" aria-label="Legenda grafik">
                            <span><i class="legend-dot pm1"></i>PM1</span>
                            <span><i class="legend-dot pm25"></i>PM2.5</span>
                            <span><i class="legend-dot pm10"></i>PM10</span>
                        </div>
                    </div>
                    <div class="chart-frame">
                        <canvas id="particleChart" role="img" aria-label="Grafik riwayat partikulat"></canvas>
                    </div>
                </article>
            </section>

            <aside class="side-panel" aria-label="Kondisi lingkungan dan perangkat">
                <section class="environment-grid">
                    <article class="mini-card">
                        <div class="mini-icon temperature" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M14 14.76V5a4 4 0 0 0-8 0v9.76a6 6 0 1 0 8 0Z"/><path d="M10 6v10"/></svg>
                        </div>
                        <span>Suhu</span>
                        <strong><b id="tempValue">--</b><small>&deg;C</small></strong>
                    </article>
                    <article class="mini-card">
                        <div class="mini-icon humidity" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M12 3s6 6.4 6 11a6 6 0 0 1-12 0c0-4.6 6-11 6-11Z"/></svg>
                        </div>
                        <span>Kelembapan</span>
                        <strong><b id="humidityValue">--</b><small>%</small></strong>
                    </article>
                    <article class="mini-card">
                        <div class="mini-icon pressure" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="m12 12 4-3M8 17h8"/></svg>
                        </div>
                        <span>Tekanan</span>
                        <strong><b id="pressureValue">--</b><small>hPa</small></strong>
                    </article>
                </section>

                <section class="system-card">
                    <div class="section-heading compact">
                        <div>
                            <span class="eyebrow">STATUS UNIT</span>
                            <h2>Daya &amp; sampling</h2>
                        </div>
                        <span class="system-badge" id="systemBadge">--</span>
                    </div>

                    <div class="battery-row">
                        <div class="battery-icon" aria-hidden="true"><i id="batteryFill"></i></div>
                        <div class="battery-copy">
                            <span>Kapasitas baterai</span>
                            <strong><b id="batteryValue">--</b>%</strong>
                        </div>
                    </div>

                    <div class="system-stats">
                        <div><span>Tegangan</span><strong><b id="voltageValue">--</b> V</strong></div>
                        <div><span>Arus</span><strong><b id="currentValue">--</b> A</strong></div>
                        <div><span>Pompa</span><strong id="pumpValue">--</strong></div>
                    </div>
                </section>

                <section class="last-reading-card">
                    <div>
                        <span class="eyebrow">DATA TERAKHIR</span>
                        <strong id="lastReadingTime">--</strong>
                    </div>
                    <button class="refresh-button" id="refreshButton" type="button">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6v5h-5M4 18v-5h5"/><path d="M18.5 9A7 7 0 0 0 6 6.5L4 9m16 6-2 2.5A7 7 0 0 1 5.5 15"/></svg>
                        <span>Perbarui</span>
                    </button>
                </section>

                <a class="download-link" href="../display/index.php">
                    <span>Riwayat &amp; unduh data</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                </a>
            </aside>
        </section>

        <footer class="footer-strip">
            <span><i></i>LOCAL MONITOR</span>
            <span id="updateMessage" role="status">Pembaruan otomatis setiap 15 detik</span>
            <a href="https://github.com/rusli3/" target="_blank" rel="noopener noreferrer">UNIT PORTABEL / AQMS</a>
        </footer>
    </main>

    <div
        class="power-modal"
        id="powerModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="powerDialogTitle"
        aria-hidden="true"
        data-enabled="<?= $powerControlsEnabled ? 'true' : 'false' ?>"
        data-csrf="<?= $powerCsrf ?>"
    >
        <div class="power-dialog">
            <div class="power-dialog-head">
                <div>
                    <span class="eyebrow">KONTROL ADMINISTRATOR</span>
                    <h2 id="powerDialogTitle">Daya unit AQMS</h2>
                </div>
                <button class="power-close" id="powerCloseButton" type="button" aria-label="Tutup menu daya">&times;</button>
            </div>

            <p class="power-warning">Pilih tindakan, masukkan PIN, lalu konfirmasi. Akuisisi data akan berhenti sementara.</p>

            <div class="power-actions" role="group" aria-label="Pilih tindakan daya">
                <button class="power-action" type="button" data-power-action="reboot">
                    <span class="power-action-icon reboot" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M20 6v5h-5"/><path d="M18.5 9A7 7 0 1 0 19 15"/></svg>
                    </span>
                    <span><strong>Mulai ulang</strong><small>Reboot sistem dengan aman</small></span>
                </button>
                <button class="power-action danger" type="button" data-power-action="shutdown">
                    <span class="power-action-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M12 3v9"/><path d="M6.7 6.7a7.5 7.5 0 1 0 10.6 0"/></svg>
                    </span>
                    <span><strong>Matikan unit</strong><small>Shutdown sebelum melepas daya</small></span>
                </button>
            </div>

            <div class="pin-panel">
                <div class="pin-heading">
                    <span>PIN ADMIN</span>
                    <div class="pin-dots" id="pinDots" aria-label="PIN belum diisi"></div>
                </div>
                <div class="pin-keypad" id="pinKeypad" aria-label="Keypad PIN">
                    <button type="button" data-pin-key="1">1</button>
                    <button type="button" data-pin-key="2">2</button>
                    <button type="button" data-pin-key="3">3</button>
                    <button type="button" data-pin-key="4">4</button>
                    <button type="button" data-pin-key="5">5</button>
                    <button type="button" data-pin-key="6">6</button>
                    <button type="button" data-pin-key="7">7</button>
                    <button type="button" data-pin-key="8">8</button>
                    <button type="button" data-pin-key="9">9</button>
                    <button type="button" data-pin-key="clear" aria-label="Hapus seluruh PIN">C</button>
                    <button type="button" data-pin-key="0">0</button>
                    <button type="button" data-pin-key="backspace" aria-label="Hapus angka terakhir">&#9003;</button>
                </div>
            </div>

            <div class="power-dialog-foot">
                <p id="powerStatus" role="status">Pilih tindakan dan masukkan 4–8 digit PIN.</p>
                <button class="power-confirm" id="powerConfirmButton" type="button" disabled>Konfirmasi</button>
            </div>
        </div>
    </div>

    <script src="js/vendor/chart.js/chart.umd.min.js"></script>
    <script src="js/dashboard-7.js"></script>
</body>
</html>
