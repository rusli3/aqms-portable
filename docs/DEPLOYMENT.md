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

## Menjalankan stack produksi

```bash
cp .env.example .env
# Isi password unik, IP sensor, AQMS_HTTP_BIND=0.0.0.0, dan AQMS_HTTP_PORT=80.
docker compose -f compose.yaml -f compose.production.yaml up -d --build
docker compose ps
curl -I http://127.0.0.1/dashboard/
```

`compose.yaml` sendiri adalah mode pengembangan pada port loopback 18080.
Override produksi mewajibkan kredensial dan allowlist, lalu membuka port web
sesuai `AQMS_HTTP_BIND`/`AQMS_HTTP_PORT`. Database tidak memiliki port host.

## Impor data lama

Repositori tidak menyertakan dump operasional. Impor hanya dari backup yang
telah diverifikasi:

Gunakan `scripts/backup.sh` dan `scripts/restore.sh`. Keduanya mengambil
kredensial dari container sehingga password tidak tampil sebagai argumen host.

Lakukan perintah ini hanya pada database tujuan yang benar. Simpan backup sebelum
mengimpor atau mengubah skema.

## Menjaga koneksi firmware lama

Firmware ESP8266 biasanya menyimpan alamat penerima secara statis. Cara migrasi
paling aman adalah mempertahankan alamat Wi-Fi Beelink lama pada instalasi baru,
kemudian menguji satu paket sensor nyata ke `/partikulat/insert.php`. Alias ini
mempertahankan URL yang umum ditanam pada firmware lama.

Checklist:

- respons endpoint `received`;
- baris baru muncul pada `maintb`;
- scheduler menghasilkan baris `coretb` setelah lima menit;
- status sensor dashboard berubah menjadi terhubung;
- nilai pompa dan daya masuk akal;
- tidak ada permintaan keluar menuju cloud SenseSync.

## Scheduler produksi

Service `scheduler` sudah berjalan setiap lima menit dengan `restart:
unless-stopped`. Scheduler memakai advisory lock dan kunci unik waktu bucket,
sehingga restart atau eksekusi bersamaan tidak menggandakan agregat.

## Migrasi instalasi lama

1. Buat dan uji backup.
2. Impor dump ke volume baru; jangan memasang langsung volume MySQL 8.0 lama ke
   image 8.4 tanpa jalur upgrade yang diuji.
3. Jalankan `database/migrations/002_coretb_unique.sql` satu kali setelah
   memeriksa kemungkinan duplikat waktu agregat.
4. Jalankan `tests/smoke.sh` pada stack uji sebelum mengganti unit lapangan.

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
