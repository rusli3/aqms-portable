# Kontrol Reboot dan Shutdown

Dashboard menyediakan menu daya untuk layar kiosk. Menu ini tidak memberi
container akses ke Docker socket, D-Bus host, atau shell root. Setelah PIN
diverifikasi di aplikasi, container hanya membuat satu berkas permintaan singkat
di bind mount khusus. Unit systemd host memvalidasi versi, tindakan, timestamp,
dan nonce sebelum menjalankan reboot atau shutdown.

Kontrol dinonaktifkan secara default. Lakukan instalasi berikut langsung pada
host AQMS setelah stack dasar sehat.

## 1. Pasang broker host

```bash
cd /opt/aqms-portable
sudo scripts/install-power-control.sh
```

Installer membuat `/run/aqms-control` dengan akses terbatas, memasang helper
root pada `/usr/local/libexec`, dan mengaktifkan `aqms-power-control.path`.

Verifikasi tanpa mengirim perintah daya:

```bash
systemctl is-enabled aqms-power-control.path
systemctl is-active aqms-power-control.path
stat -c '%A %U:%G %n' /run/aqms-control
```

Hasil yang diharapkan adalah path unit `enabled` dan `active`, serta direktori
`drwx-wx--- root:www-data` (mode `0730`).

## 2. Buat PIN administrator

Jalankan script interaktif dari terminal lokal/SSH:

```bash
cd /opt/aqms-portable
scripts/set-admin-pin.sh
```

PIN harus berisi 4–8 digit; enam digit atau lebih direkomendasikan. Script memakai
PHP di container web untuk membentuk hash dan tidak memerlukan PHP pada host.
PIN polos tidak ditulis ke disk; `.env` hanya menerima password hash dan flag
aktivasi. Jangan menampilkan `.env`, hash, atau PIN di tangkapan layar dan log
publik.

Terapkan konfigurasi hanya pada service web:

```bash
docker compose -f compose.yaml -f compose.production.yaml \
  up -d --build --force-recreate web
docker compose -f compose.yaml -f compose.production.yaml ps
curl -fsS http://127.0.0.1/health.php
```

## 3. Uji aman

Sebelum benar-benar menyalakan kontrol, uji validator broker tanpa reboot:

```bash
tests/power-broker.sh
```

Buka dashboard dan pastikan ikon daya menampilkan dialog. Uji satu PIN salah,
lalu PIN benar dengan tindakan **Mulai ulang**. Setelah host kembali hidup,
verifikasi:

```bash
systemctl --failed
systemctl is-active aqms-power-control.path docker
snap services ubuntu-frame wpe-webkit-mir-kiosk
curl -fsS http://127.0.0.1/health.php
journalctl -t aqms-power-control -n 20 --no-pager
```

Uji shutdown hanya ketika operator berada di dekat unit. Tunggu sampai layar,
kipas, dan aktivitas penyimpanan berhenti sebelum melepas daya.

## Pengamanan

- PIN diperiksa menggunakan `password_verify`; hash dibuat oleh
  `password_hash(PASSWORD_DEFAULT)`.
- Permintaan memerlukan token sesi same-origin dan JSON; endpoint menolak GET.
- Lima PIN salah mengunci alamat sumber selama lima menit.
- Broker menerima hanya `reboot` atau `shutdown`, nonce heksadesimal, dan
  permintaan berumur maksimum 30 detik.
- Berkas permintaan dihapus sebelum aksi untuk mencegah eksekusi ulang saat boot.
- Container hanya mendapat bind mount direktori kontrol, bukan akses istimewa.

Kontrol ini ditujukan untuk jaringan lokal tepercaya. Dashboard jangan diekspos
langsung ke internet. Ubah PIN berkala dengan menjalankan kembali
`scripts/set-admin-pin.sh` dan recreate service web.
