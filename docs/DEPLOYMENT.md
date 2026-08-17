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

## Pisahkan jaringan sensor dari jalur internet

Jika Beelink memakai Ethernet untuk administrasi/internet dan Wi-Fi khusus untuk
sensor, jadikan Wi-Fi sebagai jaringan lokal saja. Jangan memberikan gateway,
DNS, default route, atau IPv6 pada profil Wi-Fi sensor. Ini mencegah router atau
modem sensor mengambil alih DNS dan HTTPS host.

Contoh dengan NetworkManager; ganti nama profil dan alamat sesuai inventaris
lokal:

```bash
sudo nmcli connection modify NAMA_PROFIL_SENSOR \
  ipv4.method manual \
  ipv4.addresses "ALAMAT_BEELINK/SUBNET" \
  ipv4.gateway "" \
  ipv4.dns "" \
  ipv4.ignore-auto-dns yes \
  ipv4.never-default yes \
  ipv6.method disabled \
  connection.autoconnect yes

sudo nmcli connection down NAMA_PROFIL_SENSOR
sudo nmcli connection up NAMA_PROFIL_SENSOR
sudo resolvectl flush-caches
```

Verifikasi bahwa hanya Ethernet yang memiliki default route, sedangkan route
langsung menuju subnet sensor tetap tersedia:

```bash
ip -4 -br address
ip -4 route
resolvectl status
curl -4 -fsSI https://api.snapcraft.io/
sudo snap debug connectivity
```

Jangan mengatasi kesalahan sertifikat dengan menonaktifkan verifikasi TLS.

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

Dashboard diuji pada 1024×600 dan 800×480. Pada Ubuntu Server 24.04 LTS,
deployment referensi menggunakan Ubuntu Frame dan WPE WebKit Mir Kiosk agar
dashboard tampil otomatis pada HDMI tanpa sesi desktop pengguna:

```bash
sudo snap install ubuntu-frame
sudo snap install wpe-webkit-mir-kiosk
sudo snap connect wpe-webkit-mir-kiosk:wayland ubuntu-frame

sudo snap set ubuntu-frame daemon=true
sudo snap set ubuntu-frame config="cursor=null"
sudo snap set wpe-webkit-mir-kiosk daemon=true
sudo snap set wpe-webkit-mir-kiosk url=http://127.0.0.1/dashboard/

sudo snap restart ubuntu-frame
sudo snap restart wpe-webkit-mir-kiosk
snap services ubuntu-frame wpe-webkit-mir-kiosk
```

Pasang ketahanan startup setelah kedua snap aktif. Ini membuat Ethernet opsional
dan memastikan WPE baru disegarkan setelah web serta database lokal sehat:

```bash
cd /opt/aqms-portable
sudo scripts/install-display-resilience.sh
```

Rincian verifikasi, uji tanpa LAN, dan rollback tersedia di
[Ketahanan Startup Display](DISPLAY-RESILIENCE.md).

Pastikan konektor HDMI berstatus `connected` dan menyediakan mode panel sebelum
memasang kiosk:

```bash
for connector in /sys/class/drm/card*-*; do
  [ -f "$connector/status" ] || continue
  echo "$connector: $(cat "$connector/status")"
  cat "$connector/modes" 2>/dev/null || true
done
```

Sesudah reboot tanpa kabel LAN, dashboard harus muncul otomatis tanpa menekan
**Try Again**. Periksa kembali service kiosk, health aplikasi, koneksi sensor,
dan pertambahan data. Orientasi foto dokumentasi tidak selalu mencerminkan
orientasi fisik panel.

## Rollback

Jika sensor, database, atau dashboard gagal setelah migrasi:

1. Matikan unit secara normal.
2. Lepas SSD baru.
3. Pasang kembali SSD asli yang tidak dimodifikasi.
4. Pulihkan alamat jaringan yang dicatat sebelumnya.
5. Dokumentasikan penyebab sebelum percobaan berikutnya.
