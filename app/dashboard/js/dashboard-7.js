(function () {
  'use strict';

  var chart = null;
  var refreshTimer = null;
  var elements = {};
  var powerAction = '';
  var powerPin = '';
  var powerBusy = false;
  var dataPin = '';
  var dataBusy = false;
  var dataExpiryTimer = null;

  function byId(id) { return document.getElementById(id); }

  function number(value, digits) {
    if (value === null || value === undefined || !isFinite(Number(value))) return '--';
    return Number(value).toLocaleString('id-ID', {
      minimumFractionDigits: digits || 0,
      maximumFractionDigits: digits || 0,
      useGrouping: false
    });
  }

  function dateFromDatabase(value) {
    if (!value) return null;
    return new Date(String(value).replace(' ', 'T'));
  }

  function formatTime(value, includeSeconds) {
    var options = { hour: '2-digit', minute: '2-digit' };
    if (includeSeconds) options.second = '2-digit';
    return value.toLocaleTimeString('id-ID', options).replace(/\./g, ':');
  }

  function ispuColor(index) {
    if (index <= 50) return '#98f06a';
    if (index <= 100) return '#f2d34b';
    if (index <= 200) return '#ff9a55';
    if (index <= 300) return '#ff6d5e';
    return '#d94a68';
  }

  function setText(element, value) {
    if (element) element.textContent = value;
  }

  function apiError(response, body, fallback) {
    var error = new Error(body.message || fallback);
    error.code = body.code || '';
    error.status = response.status;
    return error;
  }

  function reloadInvalidSession(error, statusElement) {
    if (!error || error.code !== 'invalid_session') return false;
    setText(statusElement, 'Sesi diperbarui…');
    window.setTimeout(function () { window.location.reload(); }, 500);
    return true;
  }

  function updateReading(payload) {
    var latest = payload && payload.latest;
    if (!latest) {
      setText(elements.sensorLinkText, 'Data belum tersedia');
      elements.sensorLink.className = 'sensor-link is-offline';
      return;
    }

    setText(elements.pm1, number(latest.pm1, 0));
    setText(elements.pm25, number(latest.pm25, 0));
    setText(elements.pm10, number(latest.pm10, 0));
    setText(elements.temp, number(latest.temp, 1));
    setText(elements.humidity, number(latest.humidity, 1));
    setText(elements.pressure, number(latest.pressure, 0));
    setText(elements.battery, number(latest.battery, 0));
    setText(elements.voltage, number(latest.voltage, 2));
    setText(elements.current, number(latest.current, 2));

    var pumpActive = Number(latest.pump) > 0;
    setText(elements.pump, pumpActive ? 'AKTIF' : 'MATI');
    elements.pump.style.color = pumpActive ? '#98f06a' : '#ff6d5e';
    setText(elements.systemBadge, pumpActive ? 'OPERASI' : 'PERIKSA');
    elements.systemBadge.style.color = pumpActive ? '#98f06a' : '#ff6d5e';

    var battery = Math.max(0, Math.min(100, Number(latest.battery) || 0));
    elements.batteryFill.style.width = battery + '%';
    elements.batteryFill.style.background = battery < 20 ? '#ff6d5e' : battery < 40 ? '#f2b84b' : '#98f06a';

    var ispu = payload.ispu;
    if (ispu && ispu.pm25 && ispu.pm10) {
      var complete = Boolean(ispu.basis && ispu.basis.complete);
      var label = complete ? 'ISPU 24 JAM' : 'ISPU SEMENTARA';
      var pm25Color = ispuColor(ispu.pm25.value);
      var pm10Color = ispuColor(ispu.pm10.value);

      document.documentElement.style.setProperty('--quality', pm25Color);
      setText(elements.quality, ispu.pm25.category);
      setText(elements.pm25IspuLabel, label);
      setText(elements.pm25Ispu, number(ispu.pm25.value, 0));
      setText(elements.pm25IspuCategory, ispu.pm25.category);
      setText(elements.pm10IspuLabel, label);
      setText(elements.pm10Ispu, number(ispu.pm10.value, 0));
      setText(elements.pm10IspuCategory, ispu.pm10.category);
      elements.pm10Ispu.style.color = pm10Color;
      elements.pm10IspuCategory.style.color = pm10Color;
      elements.levelMarker.style.left = Math.max(1, Math.min(99, Number(ispu.pm25.value) / 5)) + '%';

      var hours = number(ispu.basis.hours, 1);
      setText(elements.ispuBasis, complete
        ? 'Rerata 24 jam; cakupan dan kontinuitas data terpenuhi.'
        : 'Sementara: ' + hours + ' jam · ' + number(ispu.basis.coveragePercent, 0)
          + '% cakupan · jeda maks ' + number(ispu.basis.maxGapMinutes, 0) + ' mnt.');
    } else {
      document.documentElement.style.setProperty('--quality', '#8da09a');
      setText(elements.quality, 'BELUM ADA DATA');
      setText(elements.pm25Ispu, '--');
      setText(elements.pm10Ispu, '--');
      setText(elements.ispuBasis, 'Data belum cukup untuk perhitungan ISPU.');
    }

    var readingTime = dateFromDatabase(latest.time);
    if (readingTime && !isNaN(readingTime.getTime())) {
      setText(elements.lastReading, readingTime.toLocaleString('id-ID', {
        day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit'
      }).replace(/\./g, ':'));
      var ageMinutes = Math.max(0, (Date.now() - readingTime.getTime()) / 60000);
      var online = ageMinutes <= 10;
      elements.sensorLink.className = 'sensor-link ' + (online ? 'is-online' : 'is-offline');
      setText(elements.sensorLinkText, online ? 'Sensor terhubung' : 'Data sensor terlambat');
    }
  }

  function historyValues(history, field) {
    return (history || []).map(function (row) {
      var value = Number(row[field]);
      return isFinite(value) ? value : null;
    });
  }

  function historyLabels(history) {
    return (history || []).map(function (row) {
      var time = dateFromDatabase(row.time);
      return time && !isNaN(time.getTime())
        ? formatTime(time, false)
        : '--:--';
    });
  }

  function chartOptions(history) {
    return {
      type: 'line',
      data: {
        labels: historyLabels(history),
        datasets: [
          { label: 'PM1', borderColor: '#54d6cf', data: historyValues(history, 'pm1'), borderWidth: 2 },
          { label: 'PM2.5', borderColor: '#98f06a', data: historyValues(history, 'pm25'), borderWidth: 3 },
          { label: 'PM10', borderColor: '#f2b84b', data: historyValues(history, 'pm10'), borderWidth: 2 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        normalized: true,
        interaction: { mode: 'index', intersect: false },
        elements: { point: { radius: 0, hoverRadius: 3 }, line: { tension: 0.32 } },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(4,14,12,.94)',
            titleColor: '#8da09a',
            bodyColor: '#f3f7ee',
            borderColor: 'rgba(181,255,209,.12)',
            borderWidth: 1,
            padding: 8,
            displayColors: true
          }
        },
        scales: {
          x: {
            grid: { color: 'rgba(181,255,209,.07)', tickLength: 3 },
            border: { color: 'rgba(181,255,209,.12)' },
            ticks: { color: '#71847e', font: { size: 8 }, maxTicksLimit: 7, maxRotation: 0 }
          },
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(181,255,209,.09)' },
            border: { display: false },
            ticks: { color: '#71847e', font: { size: 8 }, maxTicksLimit: 5 }
          }
        }
      }
    };
  }

  function updateChart(history) {
    if (!window.Chart) return;
    if (!chart) {
      chart = new window.Chart(elements.chart.getContext('2d'), chartOptions(history));
      return;
    }
    chart.data.labels = historyLabels(history);
    ['pm1', 'pm25', 'pm10'].forEach(function (field, index) {
      chart.data.datasets[index].data = historyValues(history, field);
    });
    chart.update('none');
  }

  function render(payload) {
    updateReading(payload);
    updateChart(payload && payload.history ? payload.history : []);
  }

  function fetchData(isManual) {
    if (isManual) elements.refresh.classList.add('is-loading');
    setText(elements.updateMessage, 'Mengambil data terbaru...');

    return fetch('api.php?time=' + Date.now(), { cache: 'no-store' })
      .then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
      })
      .then(function (payload) {
        render(payload);
        setText(elements.updateMessage, 'Diperbarui ' + formatTime(new Date(), true));
      })
      .catch(function () {
        setText(elements.updateMessage, 'Gagal memperbarui — mencoba kembali');
      })
      .then(function () { elements.refresh.classList.remove('is-loading'); });
  }

  function updateClock() {
    var now = new Date();
    setText(elements.clock, formatTime(now, false));
    setText(elements.date, now.toLocaleDateString('id-ID', { weekday: 'short', day: '2-digit', month: 'short' }));
  }

  function updatePowerPanel() {
    var enabled = elements.powerModal.dataset.enabled === 'true';
    var validPin = powerPin.length >= 4 && powerPin.length <= 8;
    var dots = '';
    var index;

    for (index = 0; index < powerPin.length; index += 1) dots += '<i></i>';
    elements.pinDots.innerHTML = dots || '<span>— — — —</span>';
    elements.pinDots.setAttribute('aria-label', powerPin.length
      ? powerPin.length + ' digit PIN telah diisi'
      : 'PIN belum diisi');

    elements.powerActions.forEach(function (button) {
      button.classList.toggle('is-selected', button.dataset.powerAction === powerAction);
      button.disabled = powerBusy || !enabled;
    });
    elements.pinKeys.forEach(function (button) { button.disabled = powerBusy || !enabled; });
    elements.powerConfirm.disabled = powerBusy || !enabled || !powerAction || !validPin;
    elements.powerConfirm.classList.toggle('is-danger', powerAction === 'shutdown');
    setText(elements.powerConfirm, powerBusy ? 'Memproses…' : 'Konfirmasi');

    if (!enabled) {
      setText(elements.powerStatus, 'Kontrol daya belum diaktifkan oleh administrator.');
    } else if (!powerBusy && powerAction && !validPin) {
      setText(elements.powerStatus, 'PIN 4–8 DIGIT');
    }
  }

  function resetPowerPanel() {
    powerAction = '';
    powerPin = '';
    powerBusy = false;
    setText(elements.powerStatus, 'PIN 4–8 DIGIT');
    updatePowerPanel();
  }

  function openPowerPanel() {
    resetPowerPanel();
    elements.powerModal.classList.add('is-open');
    elements.powerModal.setAttribute('aria-hidden', 'false');
    elements.powerClose.focus();
  }

  function closePowerPanel() {
    if (powerBusy) return;
    elements.powerModal.classList.remove('is-open');
    elements.powerModal.setAttribute('aria-hidden', 'true');
    resetPowerPanel();
    elements.powerMenu.focus();
  }

  function submitPowerRequest() {
    if (powerBusy || !powerAction || powerPin.length < 4) return;
    powerBusy = true;
    setText(elements.powerStatus, 'Memverifikasi PIN dan mengirim perintah…');
    updatePowerPanel();

    fetch('../admin/power.php', {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        'X-AQMS-CSRF': elements.powerModal.dataset.csrf
      },
      body: JSON.stringify({ action: powerAction, pin: powerPin })
    })
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (body) {
          if (!response.ok) throw apiError(response, body, 'Perintah ditolak.');
          return body;
        });
      })
      .then(function (body) {
        setText(elements.powerStatus, body.message + ' Tunggu hingga layar berubah.');
        elements.powerConfirm.classList.add('is-accepted');
        setText(elements.powerConfirm, 'Perintah diterima');
      })
      .catch(function (error) {
        if (reloadInvalidSession(error, elements.powerStatus)) return;
        powerBusy = false;
        powerPin = '';
        elements.powerConfirm.classList.remove('is-accepted');
        updatePowerPanel();
        setText(elements.powerStatus, error.message || 'Kontrol daya gagal.');
      });
  }

  function handlePinKey(key) {
    if (powerBusy) return;
    if (key === 'clear') powerPin = '';
    else if (key === 'backspace') powerPin = powerPin.slice(0, -1);
    else if (/^[0-9]$/.test(key) && powerPin.length < 8) powerPin += key;
    updatePowerPanel();
  }

  function updateDataAccessPanel() {
    var enabled = elements.dataAccessModal.dataset.enabled === 'true';
    var validPin = dataPin.length >= 4 && dataPin.length <= 8;
    var dots = '';
    var index;

    for (index = 0; index < dataPin.length; index += 1) dots += '<i></i>';
    elements.dataPinDots.innerHTML = dots || '<span>— — — —</span>';
    elements.dataPinDots.setAttribute('aria-label', dataPin.length
      ? dataPin.length + ' digit PIN telah diisi'
      : 'PIN belum diisi');
    elements.dataPinKeys.forEach(function (button) { button.disabled = dataBusy || !enabled; });
    elements.dataAccessConfirm.disabled = dataBusy || !enabled || !validPin;
    setText(elements.dataAccessConfirm, dataBusy ? 'Memeriksa…' : 'Tampilkan QR');

    if (!enabled) setText(elements.dataAccessStatus, 'Akses data belum dikonfigurasi.');
    else if (!dataBusy) setText(elements.dataAccessStatus, 'PIN 4–8 DIGIT');
  }

  function resetDataAccessPanel() {
    dataPin = '';
    dataBusy = false;
    if (dataExpiryTimer) window.clearInterval(dataExpiryTimer);
    dataExpiryTimer = null;
    elements.dataAuthView.hidden = false;
    elements.dataQrView.hidden = true;
    elements.wifiQr.innerHTML = '';
    elements.dataQr.innerHTML = '';
    updateDataAccessPanel();
  }

  function openDataAccessPanel() {
    resetDataAccessPanel();
    elements.dataAccessModal.classList.add('is-open');
    elements.dataAccessModal.setAttribute('aria-hidden', 'false');
    elements.dataAccessClose.focus();
  }

  function closeDataAccessPanel() {
    if (dataBusy) return;
    elements.dataAccessModal.classList.remove('is-open');
    elements.dataAccessModal.setAttribute('aria-hidden', 'true');
    resetDataAccessPanel();
    elements.dataAccessButton.focus();
  }

  function handleDataPinKey(key) {
    if (dataBusy) return;
    if (key === 'clear') dataPin = '';
    else if (key === 'backspace') dataPin = dataPin.slice(0, -1);
    else if (/^[0-9]$/.test(key) && dataPin.length < 8) dataPin += key;
    updateDataAccessPanel();
  }

  function renderQr(element, payload) {
    if (!window.qrcode) throw new Error('Pembuat QR tidak tersedia.');
    var code = window.qrcode(0, 'M');
    code.addData(payload, 'Byte');
    code.make();
    element.innerHTML = code.createSvgTag({
      cellSize: 4,
      margin: 8,
      scalable: true
    });
  }

  function startDataExpiryCountdown(seconds) {
    var remaining = Math.max(0, Number(seconds) || 0);
    function update() {
      var minutes = Math.floor(remaining / 60);
      var secs = String(remaining % 60).padStart(2, '0');
      setText(elements.dataQrExpiry, remaining > 0
        ? 'Tautan halaman data berlaku ' + minutes + ':' + secs
        : 'Tautan kedaluwarsa — tutup lalu masukkan PIN kembali');
      if (remaining > 0) remaining -= 1;
      else if (dataExpiryTimer) {
        window.clearInterval(dataExpiryTimer);
        dataExpiryTimer = null;
      }
    }
    update();
    dataExpiryTimer = window.setInterval(update, 1000);
  }

  function submitDataAccessRequest() {
    if (dataBusy || dataPin.length < 4) return;
    dataBusy = true;
    setText(elements.dataAccessStatus, 'Memverifikasi PIN…');
    updateDataAccessPanel();

    fetch('../admin/data-access.php', {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        'X-AQMS-CSRF': elements.dataAccessModal.dataset.csrf
      },
      body: JSON.stringify({ pin: dataPin })
    })
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (body) {
          if (!response.ok) throw apiError(response, body, 'Akses ditolak.');
          return body;
        });
      })
      .then(function (body) {
        renderQr(elements.wifiQr, body.wifiPayload);
        renderQr(elements.dataQr, body.accessUrl);
        setText(elements.wifiQrLabel, body.wifiSsid);
        elements.dataAuthView.hidden = true;
        elements.dataQrView.hidden = false;
        dataBusy = false;
        dataPin = '';
        startDataExpiryCountdown(body.expiresIn);
      })
      .catch(function (error) {
        if (reloadInvalidSession(error, elements.dataAccessStatus)) return;
        dataBusy = false;
        dataPin = '';
        updateDataAccessPanel();
        setText(elements.dataAccessStatus, error.message || 'Akses data gagal.');
      });
  }

  function cacheElements() {
    elements = {
      pm1: byId('pm1Value'), pm25: byId('pm25Value'), pm10: byId('pm10Value'),
      temp: byId('tempValue'), humidity: byId('humidityValue'), pressure: byId('pressureValue'),
      battery: byId('batteryValue'), voltage: byId('voltageValue'), current: byId('currentValue'),
      pump: byId('pumpValue'), quality: byId('qualityLabel'), levelMarker: byId('levelMarker'),
      pm25Ispu: byId('pm25IspuValue'), pm25IspuLabel: byId('pm25IspuLabel'),
      pm25IspuCategory: byId('pm25IspuCategory'), pm10Ispu: byId('pm10IspuValue'),
      pm10IspuLabel: byId('pm10IspuLabel'), pm10IspuCategory: byId('pm10IspuCategory'),
      ispuBasis: byId('ispuBasis'),
      batteryFill: byId('batteryFill'), systemBadge: byId('systemBadge'), lastReading: byId('lastReadingTime'),
      sensorLink: byId('sensorLink'), sensorLinkText: byId('sensorLinkText'), clock: byId('liveClock'),
      date: byId('liveDate'), refresh: byId('refreshButton'), updateMessage: byId('updateMessage'),
      chart: byId('particleChart'),
      powerMenu: byId('powerMenuButton'), powerModal: byId('powerModal'),
      powerClose: byId('powerCloseButton'), powerStatus: byId('powerStatus'),
      powerConfirm: byId('powerConfirmButton'), pinDots: byId('pinDots'),
      powerActions: Array.prototype.slice.call(document.querySelectorAll('[data-power-action]')),
      pinKeys: Array.prototype.slice.call(document.querySelectorAll('[data-pin-key]')),
      dataAccessButton: byId('dataAccessButton'), dataAccessModal: byId('dataAccessModal'),
      dataAccessClose: byId('dataAccessCloseButton'), dataAuthView: byId('dataAuthView'),
      dataQrView: byId('dataQrView'), dataPinDots: byId('dataPinDots'),
      dataAccessStatus: byId('dataAccessStatus'), dataAccessConfirm: byId('dataAccessConfirmButton'),
      wifiQr: byId('wifiQr'), wifiQrLabel: byId('wifiQrLabel'), dataQr: byId('dataQr'),
      dataQrExpiry: byId('dataQrExpiry'),
      dataPinKeys: Array.prototype.slice.call(document.querySelectorAll('[data-data-pin-key]'))
    };
  }

  document.addEventListener('DOMContentLoaded', function () {
    cacheElements();
    updateClock();
    window.setInterval(updateClock, 1000);
    render({ latest: null, history: [] });
    fetchData(false);
    elements.refresh.addEventListener('click', function () { fetchData(true); });
    elements.powerMenu.addEventListener('click', openPowerPanel);
    elements.powerClose.addEventListener('click', closePowerPanel);
    elements.powerModal.addEventListener('click', function (event) {
      if (event.target === elements.powerModal) closePowerPanel();
    });
    elements.powerActions.forEach(function (button) {
      button.addEventListener('click', function () {
        powerAction = button.dataset.powerAction;
        setText(elements.powerStatus, 'PIN 4–8 DIGIT');
        updatePowerPanel();
      });
    });
    elements.pinKeys.forEach(function (button) {
      button.addEventListener('click', function () { handlePinKey(button.dataset.pinKey); });
    });
    elements.powerConfirm.addEventListener('click', submitPowerRequest);
    elements.dataAccessButton.addEventListener('click', openDataAccessPanel);
    elements.dataAccessClose.addEventListener('click', closeDataAccessPanel);
    elements.dataAccessModal.addEventListener('click', function (event) {
      if (event.target === elements.dataAccessModal) closeDataAccessPanel();
    });
    elements.dataPinKeys.forEach(function (button) {
      button.addEventListener('click', function () { handleDataPinKey(button.dataset.dataPinKey); });
    });
    elements.dataAccessConfirm.addEventListener('click', submitDataAccessRequest);
    document.addEventListener('keydown', function (event) {
      if (elements.dataAccessModal.classList.contains('is-open')) {
        if (event.key === 'Escape') closeDataAccessPanel();
        else if (event.key === 'Backspace') handleDataPinKey('backspace');
        else if (/^[0-9]$/.test(event.key)) handleDataPinKey(event.key);
        else if (event.key === 'Enter') submitDataAccessRequest();
        return;
      }
      if (elements.powerModal.classList.contains('is-open')) {
        if (event.key === 'Escape') closePowerPanel();
        else if (event.key === 'Backspace') handlePinKey('backspace');
        else if (/^[0-9]$/.test(event.key)) handlePinKey(event.key);
        else if (event.key === 'Enter') submitPowerRequest();
      }
    });
    refreshTimer = window.setInterval(function () { fetchData(false); }, 15000);
    window.addEventListener('beforeunload', function () {
      if (refreshTimer) window.clearInterval(refreshTimer);
      if (dataExpiryTimer) window.clearInterval(dataExpiryTimer);
    });
  });
}());
