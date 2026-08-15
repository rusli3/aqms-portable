# Arsitektur dan Alur Data

## Komponen utama

| Komponen | Fungsi |
| --- | --- |
| Sensor PM | Mengukur PM1, PM2.5, dan PM10 |
| Sensor lingkungan | Mengukur suhu, kelembapan, dan tekanan |
| Pengukuran daya | Mengirim arus, tegangan, kapasitas baterai, dan status pompa |
| ESP8266/SenseSync | Mengemas pembacaan dan mengirim HTTP GET melalui Wi-Fi lokal |
| Beelink | Menjalankan web server, PHP, database, scheduler, dan dashboard |
| Layar 7 inci | Menampilkan dashboard lokal dalam mode fullscreen/kiosk |

SenseSync pada dokumen ini mengacu pada papan/firmware akuisisi sensor. Layanan
cloud vendor tidak digunakan oleh build modern.

## Urutan pemrosesan

1. Sensor memberikan pembacaan kepada ESP8266.
2. ESP8266 mengirim sepuluh parameter numerik ke `insert.php`.
3. Endpoint memvalidasi paket dan menulisnya ke `maintb` dengan prepared
   statement.
4. `scheduler/main.php` membaca sampel lima menit terakhir.
5. Jika sampel tersedia, scheduler menulis rata-ratanya ke `coretb`.
6. Dashboard API mengambil pembacaan mentah terbaru, riwayat agregat, dan rerata
   untuk ISPU.
7. Browser memperbarui data setiap 15 detik tanpa reload halaman penuh.

## Model data

`maintb` menyimpan data mentah. `coretb` memiliki kolom yang sama tetapi berisi
hasil agregasi.

| Kolom | Arti | Satuan umum |
| --- | --- | --- |
| `waktu` | Waktu penerimaan/agregasi | waktu lokal |
| `pm1` | Partikulat ≤1 µm | µg/m³ |
| `pm25` | Partikulat ≤2,5 µm | µg/m³ |
| `pm10` | Partikulat ≤10 µm | µg/m³ |
| `temp` | Suhu | °C |
| `humd` | Kelembapan relatif | % |
| `ampere` | Arus sistem | A |
| `baterai` | Kapasitas baterai | % |
| `pompa` | Nilai/status kendali pompa | nilai ADC/status |
| `volt` | Tegangan sistem | V |
| `press` | Tekanan atmosfer | hPa |

Kolom `pompa` berasal dari protokol lama. Dashboard menganggap nilai lebih besar
dari nol sebagai aktif. Verifikasi pemetaan terhadap firmware diperlukan jika
logika kendali diubah.

## Batas sistem

- Tidak ada sinkronisasi cloud SenseSync.
- Endpoint ingest belum memakai token agar kompatibel dengan firmware lama.
- Dashboard dapat berjalan tanpa internet karena aset utamanya disimpan lokal.
- Halaman riwayat memakai prepared statement, tetapi aset ekspor DataTables masih
  bergantung pada internet dan menjadi area migrasi lanjutan.
