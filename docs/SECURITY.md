# Keamanan

## Keadaan saat ini

- Kredensial database tidak ditulis di source code.
- Query ingest dan scheduler menggunakan prepared statement.
- Parameter sensor harus lengkap, numerik, dan finite.
- Sinkronisasi cloud SenseSync telah dihapus.
- Dashboard dan pustaka grafik utama dapat berjalan lokal.
- Dump database, backup perangkat, dan `.env` dikecualikan dari Git.

## Risiko yang masih diterima

Endpoint `/insert.php` belum memakai autentikasi agar firmware ESP8266 lama tetap
kompatibel. Siapa pun yang dapat menjangkau web server dapat mencoba mengirim
data palsu. Karena itu layanan tidak boleh diekspos langsung ke internet.

## Rekomendasi produksi

1. Tempatkan sensor dan Beelink pada VLAN atau Wi-Fi khusus AQMS.
2. Batasi port web dengan firewall hanya untuk subnet sensor dan operator.
3. Jangan publikasikan phpMyAdmin.
4. Gunakan kredensial database unik dengan hak minimum.
5. Cadangkan database secara berkala dan uji proses restore.
6. Pantau paket ingest yang terlalu cepat, nilai di luar rentang, dan jeda data.
7. Tambahkan token/HMAC setelah firmware berhasil dicadangkan dan dapat diubah.
8. Pasang pembaruan keamanan OS/container secara terjadwal setelah diuji.

## Pelaporan kerentanan

Jangan membuka issue publik yang berisi alamat perangkat, dump database,
kredensial, atau detail akses. Hubungi pemilik repositori secara privat terlebih
dahulu.
