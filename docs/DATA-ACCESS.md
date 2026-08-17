# Akses dan Unduh Data melalui HP

Fitur ini memindahkan data dari AQMS ke HP tanpa internet, cloud, atau USB.
Dashboard menampilkan dua QR hanya setelah PIN administrator diverifikasi.

## Alur operator

1. Tekan **Akses Data** pada dashboard.
2. Masukkan PIN administrator 4–8 digit.
3. Pindai QR pertama agar HP terhubung ke Wi-Fi AQMS.
4. Jika HP menyatakan Wi-Fi tidak memiliki internet, pilih untuk tetap terhubung.
5. Pindai QR kedua untuk membuka halaman data lokal.
6. Pilih tanggal awal dan akhir, atau centang **Semua Data**.
7. Tekan **Unduh CSV**.

CSV memuat data mentah dari tabel `maintb` dalam urutan waktu paling lama ke
paling baru. Kolomnya adalah `waktu`, `pm1`, `pm25`, `pm10`, `temp`, `humd`,
`ampere`, `baterai`, `pompa`, `volt`, dan `press`. Tabel pada browser hanya
menampilkan 1.000 rekaman terbaru agar halaman tetap ringan; batas ini tidak
diterapkan pada CSV.

## Konfigurasi

Simpan konfigurasi berikut hanya di `.env` pada unit AQMS:

```dotenv
AQMS_WIFI_SSID=PARTIKULAT02
AQMS_WIFI_PSK=ganti-dengan-psk-operasional
AQMS_WIFI_HIDDEN=false
AQMS_DATA_URL=http://192.168.100.135/display/
```

Jangan commit `.env` atau menyalin PSK ke dokumentasi, issue, tangkapan layar,
dan log. Setelah mengubah konfigurasi, recreate service web.

## Model keamanan

- PIN tidak dikirim ke layanan luar dan diperiksa memakai hash yang sama dengan
  kontrol daya.
- Lima kegagalan PIN pada menu akses data atau kontrol daya mengunci alamat
  sumber selama lima menit.
- QR halaman data berisi token acak 256-bit, berlaku 10 menit, dan hanya dapat
  dipakai satu kali.
- Pemindaian yang berhasil membuat sesi browser HP selama 60 menit kemudian
  menghapus token dari server dan URL browser.
- Halaman dan CSV tidak dapat diakses tanpa sesi tersebut.
- QR dibuat oleh library MIT yang disimpan lokal; internet tidak diperlukan.

QR Wi-Fi secara teknis mengandung PSK. Karena itu QR tidak boleh diletakkan
permanen pada casing atau halaman dashboard utama. Orang yang mendapat akses
fisik ke QR setelah PIN benar dapat memperoleh kredensial Wi-Fi.

## Pemeriksaan setelah deployment

```bash
cd /opt/aqms-portable
docker compose -f compose.yaml -f compose.production.yaml ps
curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1/display/
```

Respons langsung `/display/` harus `403`. Selanjutnya uji alur pada dashboard
dengan satu HP Android atau iPhone. Pastikan QR pertama menyambung ke SSID yang
benar, QR kedua membuka halaman data, CSV dapat dibuka, dan QR kedua tidak dapat
dipakai ulang dari browser baru.
