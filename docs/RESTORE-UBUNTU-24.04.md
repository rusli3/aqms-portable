# Panduan Restore AQMS pada Ubuntu Server 24.04 LTS

Dokumen ini adalah prosedur pemulihan operasional AQMS Portable pada Beelink
dengan Ubuntu Server 24.04.4 LTS. Target akhirnya adalah dashboard kembali
berjalan, data lama tersedia, scheduler aktif, dan paket sensor baru tersimpan
secara lokal.

## Ruang lingkup dan aturan keselamatan

Gunakan instalasi bersih pada SSD pengganti. Jangan menghapus, memformat, atau
melakukan upgrade pada SSD asli sebelum seluruh pemeriksaan dalam dokumen ini
lulus. SSD asli adalah jalur rollback terakhir.

Panduan ini mengenali dua jenis backup:

1. `aqms-YYYY-MM-DD.sql.gz`, dibuat oleh `scripts/backup.sh`; ini adalah format
   restore yang direkomendasikan.
2. `aqms-portable-backup-20260815.tar.gz`, arsip penyelamatan sistem lama yang
   memuat `aqms-backup-20260815/partikulat-database.sql`.

Arsip lama juga memuat aplikasi PHP lama. **Jangan menyalin direktori
`var/www/html` dari arsip tersebut ke instalasi baru.** Gunakan repository ini
sebagai aplikasi dan ambil hanya dump database serta inventaris bila diperlukan.

Semua perintah di bawah dijalankan pada Beelink. Baris yang diawali `#` adalah
penjelasan, bukan perintah.

## 1. Persiapan sebelum mengganti SSD

Catat informasi berikut dari unit lama:

- alamat IP Beelink, gateway, dan subnet;
- alamat IP/CIDR pengirim sensor;
- resolusi dan orientasi layar 7 inci;
- jenis koneksi sensor: HTTP melalui jaringan atau USB/serial;
- timezone perangkat;
- checksum backup dan lokasi salinan kedua.

Simpan backup pada sedikitnya dua media berbeda. Verifikasi arsip penyelamatan:

```bash
sha256sum aqms-portable-backup-20260815.tar.gz
tar -tzf aqms-portable-backup-20260815.tar.gz >/dev/null
```

Simpan hasil `sha256sum` di luar Beelink. Jangan melanjutkan bila `tar` melaporkan
kerusakan.

## 2. Siapkan Ubuntu Server 24.04.4

Instal Ubuntu Server 24.04.4 amd64 pada SSD pengganti. Saat installer berjalan:

- gunakan hostname yang mudah dikenali, misalnya `aqms-portable`;
- aktifkan OpenSSH Server;
- jangan gunakan full disk encryption bila unit harus menyala otomatis tanpa
  operator setelah listrik padam;
- pertahankan alamat IP lama jika alamat tersebut tertanam pada firmware sensor.

Setelah boot pertama:

```bash
sudo apt update
sudo apt full-upgrade -y
sudo timedatectl set-timezone Asia/Pontianak
sudo apt install -y docker.io docker-compose-v2 git curl ca-certificates
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"
```

Keluar dari sesi SSH lalu masuk kembali agar keanggotaan grup `docker` berlaku.
Verifikasi:

```bash
docker version
docker compose version
timedatectl
```

## 3. Ambil aplikasi AQMS

Lokasi instalasi yang digunakan panduan ini adalah `/opt/aqms-portable`:

```bash
sudo install -d -o "$USER" -g "$USER" /opt/aqms-portable
git clone https://github.com/rusli3/aqms-portable.git /opt/aqms-portable
cd /opt/aqms-portable
git status
```

Pastikan perintah terakhir tidak memperlihatkan perubahan lokal. Buat konfigurasi:

```bash
cp .env.example .env
chmod 600 .env
nano .env
```

Nilai minimal yang harus diperiksa:

```dotenv
AQMS_DB_NAME=partikulat
AQMS_DB_USER=aqms
AQMS_DB_PASSWORD=GANTI_DENGAN_PASSWORD_UNIK
AQMS_DB_ROOT_PASSWORD=GANTI_DENGAN_PASSWORD_ROOT_UNIK
AQMS_DISPLAY_NAME=PARTIKULAT 02
AQMS_TIMEZONE=Asia/Pontianak
AQMS_HTTP_BIND=0.0.0.0
AQMS_HTTP_PORT=80
AQMS_INGEST_ALLOWED_CIDRS=192.168.1.22/32
AQMS_INGEST_TOKEN=
AQMS_INGEST_MIN_INTERVAL_SECONDS=1
```

Sesuaikan `AQMS_INGEST_ALLOWED_CIDRS` dengan IP sensor yang sebenarnya. Jangan
menyalin password contoh. Simpan `.env` di pengelola password atau media offline;
file ini tidak boleh masuk Git.

Validasi konfigurasi Compose tanpa menampilkan `.env`:

```bash
docker compose -f compose.yaml -f compose.production.yaml config --quiet
```

## 4. Siapkan file database

### Pilihan A — backup modern `.sql.gz`

Salin backup ke direktori lokal:

```bash
install -d -m 700 backups
cp /media/usb/aqms-YYYY-MM-DD.sql.gz backups/
gzip -t backups/aqms-YYYY-MM-DD.sql.gz
sha256sum backups/aqms-YYYY-MM-DD.sql.gz
```

Ganti `/media/usb` dan tanggal dengan lokasi sebenarnya. Bila `gzip -t` gagal,
jangan lakukan impor.

### Pilihan B — arsip penyelamatan 15 Agustus 2026

Ekstrak hanya dump database ke direktori sementara:

```bash
install -d -m 700 restore-source
tar -xzf /media/usb/aqms-portable-backup-20260815.tar.gz \
  -C restore-source \
  aqms-backup-20260815/partikulat-database.sql
gzip -c restore-source/aqms-backup-20260815/partikulat-database.sql \
  > backups/aqms-legacy-20260815.sql.gz
gzip -t backups/aqms-legacy-20260815.sql.gz
```

Dump tersebut berasal dari MySQL 8.0.27 dan berisi tabel `maintb` serta `coretb`.
Jangan mengekstrak aplikasi lama ke `/opt/aqms-portable`.

## 5. Hidupkan database tujuan yang kosong

Jalankan hanya service database terlebih dahulu:

```bash
cd /opt/aqms-portable
docker compose -f compose.yaml -f compose.production.yaml up -d --build database
docker compose -f compose.yaml -f compose.production.yaml ps
```

Tunggu sampai database berstatus `healthy`:

```bash
docker compose -f compose.yaml -f compose.production.yaml exec -T database \
  sh -c 'mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent'
```

Perintah restore akan menjalankan `DROP TABLE` bila dump lama memuatnya. Pastikan
ini benar-benar volume baru dan bukan database produksi yang masih diperlukan.

## 6. Impor database

Untuk backup modern:

```bash
scripts/restore.sh backups/aqms-YYYY-MM-DD.sql.gz --confirm-import
```

Untuk backup lama:

```bash
scripts/restore.sh backups/aqms-legacy-20260815.sql.gz --confirm-import
```

Kata sandi database dibaca dari environment container sehingga tidak muncul pada
argumen proses host. Pesan yang diharapkan:

```text
restore import completed; verify row counts and dashboard
```

Jika proses berhenti atau menampilkan error SQL, jangan mengulang impor berkali-
kali. Simpan output error dan lihat bagian pemecahan masalah.

## 7. Terapkan migrasi untuk dump lama

Langkah ini diperlukan untuk dump lama yang belum mempunyai kunci unik waktu
agregat. Migrasi mempertahankan baris terbaru bila ditemukan waktu `coretb` yang
duplikat:

```bash
docker compose -f compose.yaml -f compose.production.yaml exec -T database \
  sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
  < database/migrations/002_coretb_unique.sql
```

Jangan menjalankannya lagi bila indeks `uq_coretb_waktu` sudah ada. Periksa dengan:

```bash
docker compose -f compose.yaml -f compose.production.yaml exec -T database \
  sh -c 'mysql -N -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e \
  "SHOW INDEX FROM coretb WHERE Key_name=\"uq_coretb_waktu\""'
```

## 8. Verifikasi data sebelum aplikasi dibuka

Hitung jumlah data dan rentang waktunya:

```bash
docker compose -f compose.yaml -f compose.production.yaml exec -T database \
  sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e \
  "SELECT COUNT(*) AS maintb_rows, MIN(waktu) AS awal, MAX(waktu) AS akhir FROM maintb; \
   SELECT COUNT(*) AS coretb_rows, MIN(waktu) AS awal, MAX(waktu) AS akhir FROM coretb;"'
```

Untuk arsip penyelamatan 15 Agustus 2026, nilai acuan hasil audit adalah:

| Tabel | Jumlah baris acuan |
| --- | ---: |
| `maintb` | 3131 |
| `coretb` | 1218 |

Perbedaan jumlah setelah migrasi `coretb` hanya dapat diterima jika migrasi
menghapus waktu agregat duplikat. Selidiki perbedaan lain sebelum melanjutkan.

Periksa tabel dan indeks:

```bash
docker compose -f compose.yaml -f compose.production.yaml exec -T database \
  sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e \
  "CHECK TABLE maintb, coretb; SHOW INDEX FROM maintb; SHOW INDEX FROM coretb;"'
```

Status `CHECK TABLE` harus `OK`.

## 9. Jalankan seluruh layanan

```bash
docker compose -f compose.yaml -f compose.production.yaml up -d --build
docker compose -f compose.yaml -f compose.production.yaml ps
curl -fsS http://127.0.0.1/health.php
curl -fsSI http://127.0.0.1/dashboard/
```

Ketiga service harus berjalan, database dan web harus sehat, dan endpoint health
harus mengembalikan `ok`.

Buka dashboard dari komputer jaringan:

```text
http://IP-BEELINK/dashboard/
```

Pastikan grafik, nilai terakhir, PM2.5, PM10, dan ISPU tampil. Data historis tidak
otomatis memenuhi syarat ISPU resmi; status bergantung pada cakupan 24 jam dan
kelengkapan data terbaru.

## 10. Uji paket sensor nyata

Sebelum pengujian, catat jumlah baris saat ini:

```bash
docker compose -f compose.yaml -f compose.production.yaml exec -T database \
  sh -c 'mysql -N -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e \
  "SELECT COUNT(*) FROM maintb"'
```

Nyalakan sensor dan tunggu satu interval kirim. Pantau log tanpa menampilkan
isi `.env`:

```bash
docker compose -f compose.yaml -f compose.production.yaml logs --tail=100 web scheduler
```

Ulangi hitungan `maintb`; nilainya harus bertambah. Endpoint kompatibilitas
firmware lama adalah:

```text
http://IP-BEELINK/partikulat/insert.php
```

Jika respons `403`, periksa `AQMS_INGEST_ALLOWED_CIDRS`. Jika respons `401`,
firmware belum mengirim token yang ditetapkan pada `AQMS_INGEST_TOKEN`.

Setelah sedikitnya satu bucket lima menit lengkap, periksa agregasi:

```bash
docker compose -f compose.yaml -f compose.production.yaml exec -T scheduler \
  php /var/www/html/scheduler/main.php
docker compose -f compose.yaml -f compose.production.yaml exec -T database \
  sh -c 'mysql -N -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e \
  "SELECT waktu,pm25,pm10 FROM coretb ORDER BY waktu DESC LIMIT 5"'
```

## 11. Uji persistence dan buat backup baru

Catat jumlah baris, restart stack tanpa `-v`, lalu bandingkan kembali:

```bash
docker compose -f compose.yaml -f compose.production.yaml exec -T database \
  sh -c 'mysql -N -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e \
  "SELECT COUNT(*) FROM maintb; SELECT COUNT(*) FROM coretb"'
docker compose -f compose.yaml -f compose.production.yaml down
docker compose -f compose.yaml -f compose.production.yaml up -d
```

**Jangan pernah menambahkan `-v` pada `docker compose down` untuk operasi biasa.**
Opsi itu menghapus named volume database.

Setelah service kembali sehat, periksa jumlah baris lagi. Kemudian buat backup
baru dari sistem hasil restore:

```bash
install -d -m 700 backups
scripts/backup.sh "backups/aqms-setelah-restore-$(date +%F).sql.gz"
sha256sum backups/aqms-setelah-restore-*.sql.gz
```

Salin backup dan checksumnya ke media kedua.

## 12. Kiosk layar 7 inci

Restore database tidak mengatur desktop. Setelah Xorg, window manager ringan,
dan Chromium dipasang, gunakan URL produksi:

```bash
chromium-browser --kiosk --noerrdialogs http://127.0.0.1/dashboard/
```

Nama executable dapat berupa `chromium`, tergantung metode instalasi. Uji resolusi
800×480 atau 1024×600, touchscreen, fullscreen, dan start otomatis setelah listrik
diputus lalu disambungkan kembali.

## 13. Checklist penerimaan

Restore dinyatakan selesai hanya bila seluruh kondisi berikut terpenuhi:

- [ ] checksum backup tercatat dan backup dapat dibaca;
- [ ] database serta web berstatus sehat;
- [ ] jumlah dan rentang waktu data lama masuk akal;
- [ ] `CHECK TABLE maintb, coretb` menghasilkan `OK`;
- [ ] dashboard tampil benar pada layar 7 inci;
- [ ] PM2.5, PM10, dan status ISPU tampil;
- [ ] paket sensor baru menambah baris `maintb`;
- [ ] scheduler menambah `coretb` tanpa duplikasi;
- [ ] data tetap ada setelah restart stack dan reboot Beelink;
- [ ] tidak ada koneksi keluar menuju SenseSync;
- [ ] backup baru setelah restore sudah disalin ke media kedua;
- [ ] SSD lama masih utuh dan diberi label rollback.

## 14. Pemecahan masalah

### Database tidak sehat

```bash
docker compose -f compose.yaml -f compose.production.yaml ps
docker compose -f compose.yaml -f compose.production.yaml logs --tail=200 database
```

Periksa kapasitas disk dengan `df -h`, permission Docker, dan nilai `.env`. Jangan
menempelkan isi `.env` ke forum atau laporan publik.

### Restore ditolak karena file bukan `.gz`

Kompres dump tanpa mengubah sumber:

```bash
gzip -c sumber.sql > backups/sumber.sql.gz
gzip -t backups/sumber.sql.gz
```

### Restore terlanjur dilakukan pada database yang salah

Hentikan ingest sensor dan scheduler, jangan lakukan penulisan tambahan, lalu
pulihkan backup pra-restore. Jangan menghapus volume sebelum lokasi backup dan
target database dikonfirmasi.

### Dashboard sehat tetapi data tidak bertambah

Periksa IP sumber sensor, allowlist CIDR, alamat tujuan firmware, dan log web.
Ping hanya membuktikan konektivitas jaringan; tidak membuktikan paket ingest
diterima.

### Port 80 sudah digunakan

```bash
sudo ss -lntp | grep ':80 '
```

Hentikan hanya service yang sudah diidentifikasi dan memang tidak diperlukan,
atau ubah `AQMS_HTTP_PORT` secara terencana. Jangan menghentikan proses acak.

## 15. Rollback fisik

Jika sensor, database, touchscreen, atau startup otomatis belum stabil:

1. simpan log dan backup dari percobaan restore;
2. matikan Beelink secara normal;
3. lepas SSD baru;
4. pasang kembali SSD asli yang tidak dimodifikasi;
5. kembalikan konfigurasi IP yang dicatat;
6. hidupkan alat dan verifikasi penerimaan data;
7. analisis kegagalan pada SSD baru, bukan pada unit operasional.

Jangan menghapus SSD baru setelah rollback. Media tersebut berguna untuk analisis
dan percobaan restore berikutnya.
