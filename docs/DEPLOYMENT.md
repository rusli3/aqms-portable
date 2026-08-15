# Pemasangan dan Migrasi Beelink

## Prinsip migrasi

Jangan melakukan upgrade distribusi langsung pada SSD lama. Simpan media asli
sebagai jalur rollback dan lakukan pemasangan pada SSD pengganti atau hasil clone
yang telah diuji.

## Tahap persiapan

1. Buat image penuh SSD asli dan verifikasi checksum.
2. Catat alamat IP, subnet Wi-Fi sensor, resolusi layar, timezone, dan jadwal
   scheduler.
3. Siapkan SSD pengganti dengan Ubuntu LTS yang masih didukung.
4. Pasang Docker Engine dan Docker Compose v2.
5. Clone repositori dan buat konfigurasi rahasia lokal.

## Menjalankan stack

```bash
docker compose up -d --build
docker compose ps
curl -I http://127.0.0.1:18080/dashboard/
```

Untuk produksi, ubah kredensial pengujian di `compose.yaml` atau pindahkan ke
secret/environment yang tidak dilacak Git.

## Impor data lama

Repositori tidak menyertakan dump operasional. Impor hanya dari backup yang
telah diverifikasi:

```bash
docker compose exec -T database mysql -uroot -p partikulat < backup.sql
```

Lakukan perintah ini hanya pada database tujuan yang benar. Simpan backup sebelum
mengimpor atau mengubah skema.

## Menjaga koneksi firmware lama

Firmware ESP8266 biasanya menyimpan alamat penerima secara statis. Cara migrasi
paling aman adalah mempertahankan alamat Wi-Fi Beelink lama pada instalasi baru,
kemudian menguji satu paket sensor nyata ke `/insert.php`.

Checklist:

- respons endpoint `received`;
- baris baru muncul pada `maintb`;
- scheduler menghasilkan baris `coretb` setelah lima menit;
- status sensor dashboard berubah menjadi terhubung;
- nilai pompa dan daya masuk akal;
- tidak ada permintaan keluar menuju cloud SenseSync.

## Scheduler produksi

Contoh cron host setiap lima menit:

```cron
*/5 * * * * cd /opt/aqms-portable && docker compose exec -T web php /var/www/html/scheduler/main.php >> /var/log/aqms-scheduler.log 2>&1
```

Sesuaikan path dan kebijakan rotasi log. Systemd timer lebih baik jika dibutuhkan
monitoring kegagalan yang lebih jelas.

## Mode layar 7 inci

Dashboard diuji pada 1024×600 dan 800×480. Buka URL lokal dan gunakan tombol
fullscreen. Untuk kiosk otomatis, jalankan Chromium/Chrome dengan profil khusus:

```bash
chromium-browser --kiosk --noerrdialogs http://127.0.0.1:18080/dashboard/
```

Nama executable dapat berbeda pada tiap distribusi.

## Rollback

Jika sensor, database, atau dashboard gagal setelah migrasi:

1. Matikan unit secara normal.
2. Lepas SSD baru.
3. Pasang kembali SSD asli yang tidak dimodifikasi.
4. Pulihkan alamat jaringan yang dicatat sebelumnya.
5. Dokumentasikan penyebab sebelum percobaan berikutnya.
