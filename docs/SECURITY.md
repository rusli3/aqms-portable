# Keamanan

## Kontrol yang diterapkan

- Database tidak dipublikasikan ke host dan memakai kredensial environment.
- Endpoint ingest hanya menerima GET dari IP/CIDR yang diizinkan, mendukung token
  opsional, membatasi laju global, menolak parameter asing, dan memeriksa rentang.
- Query tulis dan filter riwayat memakai prepared statement.
- Apache menyembunyikan versi rinci, menonaktifkan directory listing dan TRACE,
  serta mengirim CSP, `nosniff`, frame denial, referrer, dan permissions policy.
- PHP tidak mengekspos versi atau error ke browser.
- Dashboard dan ekspor CSV tidak memerlukan CDN atau koneksi internet.
- Ekspor data mentah dilindungi PIN, rate limit, token 256-bit sekali pakai
  berumur 10 menit, dan sesi HP berumur 60 menit.
- Named volume, backup terverifikasi, healthcheck, restart policy, dan CI tersedia.
- Sinkronisasi cloud SenseSync tidak ada dalam aplikasi modern.
- Kontrol daya dinonaktifkan secara default; bila diaktifkan, PIN menggunakan
  password hash, token sesi, rate limit, dan broker systemd dengan daftar aksi
  tetap. Container tidak mendapat Docker socket atau D-Bus host.

CSP tidak mengizinkan script inline. `style-src` mengizinkan inline style karena
Chart.js dan indikator baterai/ISPU mengubah dimensi atau warna langsung pada
elemen; tidak ada nilai style yang berasal dari input pengguna.

## Konfigurasi lapangan

1. Gunakan `compose.production.yaml` dan password unik di `.env`.
2. Isi `AQMS_INGEST_ALLOWED_CIDRS` sesempit mungkin, idealnya satu alamat `/32`.
3. Aktifkan `AQMS_INGEST_TOKEN` setelah firmware dapat mengirim header
   `X-AQMS-Token` atau parameter `token`.
4. Tempatkan sensor dan Beelink pada VLAN/Wi-Fi AQMS; jangan ekspos layanan ke
   internet dan jangan memasang phpMyAdmin.
5. Simpan backup di media lain, uji restore, dan pantau respons 403/422/429.
6. Tinjau Dependabot dan scan image sebelum setiap deployment.
7. Bila menu daya dipakai, pasang broker melalui panduan
   `docs/POWER-CONTROL.md`, gunakan PIN minimal enam digit, dan jangan membuka
   dashboard ke internet.
8. Simpan PSK QR Wi-Fi hanya di `.env`; QR mengandung PSK sehingga hanya boleh
   ditampilkan sesudah PIN benar. Ikuti `docs/DATA-ACCESS.md`.

## Batas yang tetap berlaku

Firmware lama tanpa token mengandalkan kontrol jaringan dan CIDR. Rate limit
bersifat global per unit, bukan identitas sensor. Image database resmi masih
dapat membawa temuan upstream; status dan alasan penerimaan residual dicatat di
`docs/CONTAINER_SECURITY.md` dan harus ditinjau ulang berkala.

## Pelaporan kerentanan

Jangan membuka issue publik yang berisi alamat perangkat, dump database,
kredensial, atau detail akses. Hubungi pemilik repositori secara privat.
