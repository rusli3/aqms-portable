(function () {
  'use strict';

  var chart = null;
  var refreshTimer = null;
  var elements = {};

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
        ? time.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':')
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
        setText(elements.updateMessage, 'Diperbarui ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
      })
      .catch(function () {
        setText(elements.updateMessage, 'Gagal memperbarui — mencoba kembali');
      })
      .then(function () { elements.refresh.classList.remove('is-loading'); });
  }

  function updateClock() {
    var now = new Date();
    setText(elements.clock, now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':'));
    setText(elements.date, now.toLocaleDateString('id-ID', { weekday: 'short', day: '2-digit', month: 'short' }));
  }

  function toggleFullscreen() {
    if (!document.fullscreenElement && document.documentElement.requestFullscreen) {
      document.documentElement.requestFullscreen().catch(function () {});
    } else if (document.exitFullscreen) {
      document.exitFullscreen();
    }
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
      fullscreen: byId('fullscreenButton'), chart: byId('particleChart')
    };
  }

  document.addEventListener('DOMContentLoaded', function () {
    cacheElements();
    updateClock();
    window.setInterval(updateClock, 1000);
    render({ latest: null, history: [] });
    fetchData(false);
    elements.refresh.addEventListener('click', function () { fetchData(true); });
    elements.fullscreen.addEventListener('click', toggleFullscreen);
    refreshTimer = window.setInterval(function () { fetchData(false); }, 15000);
    window.addEventListener('beforeunload', function () {
      if (refreshTimer) window.clearInterval(refreshTimer);
    });
  });
}());
