# Pemetaan Perangkat Keras

Dokumen ini merangkum identifikasi awal dari inspeksi unit lama. Nomor part harus
dikonfirmasi dari label fisik sebelum pembelian pengganti.

## Blok fungsi

```text
Udara ambien
   ↓
inlet dan selang sampel
   ↓
sensor partikulat berbantuan pompa
   ↓
ESP8266 / papan SenseSync
   ↓ Wi-Fi lokal
Beelink (web, database, dashboard)
   ↓
layar 7 inci
```

Jalur daya secara umum:

```text
baterai / sumber DC
   ├── regulator → Beelink dan layar
   ├── regulator → papan sensor/ESP8266
   └── driver → pompa sampling
```

## Identifikasi awal

| Bagian | Identifikasi | Catatan servis |
| --- | --- | --- |
| Komputer | Beelink x86-64 | Menjalankan Ubuntu, Apache/PHP, MySQL, dan browser |
| Pengendali | ESP8266 pada papan SenseSync | Mengirim paket HTTP melalui Wi-Fi lokal |
| Sensor PM | Keluarga Plantower pump-suction/PMSx003-N, belum final | Pastikan model dari label dan konektor sebelum membeli |
| Pompa | Pompa sampling DC | Periksa aliran, selang, kebocoran, dan driver |
| Sensor meteo | Suhu, kelembapan, tekanan | Model belum terkonfirmasi |
| Sistem daya | Baterai, pengukur arus/tegangan, regulator | Verifikasi tegangan rail sebelum melepas modul |

## Pembersihan sensor PM

- Matikan daya dan dokumentasikan posisi selang/konektor.
- Bersihkan inlet, selang, dan ruang aliran yang dapat diakses tanpa membuka
  ruang optik sensor.
- Gunakan udara kering bertekanan rendah pada jalur yang diizinkan produsen.
- Jangan menyentuh laser, fotodioda, atau menyemprot cairan ke ruang optik.
- Ganti selang yang retak, mengeras, atau terkontaminasi berat.
- Setelah perakitan, periksa kestabilan aliran dan bandingkan pembacaan dengan
  instrumen referensi.

Membuka ruang optik dapat mengubah kalibrasi. Untuk hasil yang dapat dilaporkan,
gunakan prosedur produsen atau ganti modul dengan part yang identik.
