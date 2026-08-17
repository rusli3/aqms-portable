# Ketahanan Startup Display

AQMS harus dapat menampilkan dashboard lokal walaupun kabel Ethernet tidak
terpasang. Ethernet digunakan untuk administrasi dan internet, sedangkan sensor
berkomunikasi melalui Wi-Fi lokal `PARTIKULAT02`.

## Masalah yang diperbaiki

Pada konfigurasi bawaan, `systemd-networkd-wait-online` menunggu Ethernet sampai
sekitar dua menit. Ubuntu Frame dan WPE WebKit dapat lebih dahulu membuka
`http://127.0.0.1/dashboard/` ketika web/database Docker belum siap. WPE lalu
menampilkan `Connection refused` dan tidak selalu mencoba ulang sendiri.

Perbaikan terdiri dari dua lapisan:

1. Ethernet ditandai `optional` pada Netplan sehingga boot tidak menunggu kabel.
2. Drop-in wait-online mengabaikan hanya interface Ethernet administrasi.
3. `aqms-kiosk-ready.service` menunggu `/health.php` berhasil, lalu menyegarkan
   WPE satu kali agar dashboard tampil tanpa menyentuh **Try Again**. Ubuntu
   Frame tidak direstart agar panel tidak berkedip dua kali.

## Pemasangan

Jalankan dari direktori repositori pada Beelink:

```bash
cd /opt/aqms-portable
sudo scripts/install-display-resilience.sh
```

Nama interface bawaan adalah `enp1s0`. Untuk perangkat dengan nama lain:

```bash
sudo scripts/install-display-resilience.sh eno1
```

Installer hanya menjalankan `netplan generate`; koneksi aktif tidak diputus.
Efek boot tanpa menunggu Ethernet berlaku penuh pada reboot berikutnya.

## Verifikasi

```bash
sudo netplan get ethernets.enp1s0.optional
systemctl cat systemd-networkd-wait-online.service | grep -- '--ignore=enp1s0'
systemctl is-enabled aqms-kiosk-ready.service
systemctl is-active aqms-kiosk-ready.service
journalctl -u aqms-kiosk-ready.service -b --no-pager
curl -fsS http://127.0.0.1/health.php
```

Hasil yang diharapkan adalah `true`, baris `--ignore=enp1s0`, service `enabled`
dan `active`, log `kiosk AQMS siap`, serta respons health `ok`.

Lakukan uji penerimaan dengan mematikan unit secara normal, mencabut Ethernet,
kemudian menyalakan kembali. Dashboard harus muncul otomatis setelah stack
Docker sehat. Wi-Fi sensor tetap harus aktif dan data baru harus bertambah.

## Pemulihan masalah

Jika dashboard belum muncul:

```bash
systemctl status aqms-kiosk-ready.service --no-pager
journalctl -u aqms-kiosk-ready.service -b --no-pager
snap services ubuntu-frame wpe-webkit-mir-kiosk
docker compose -f compose.yaml -f compose.production.yaml ps
curl -v http://127.0.0.1/health.php
```

Untuk mengulang proses kesiapan tanpa reboot:

```bash
sudo systemctl restart aqms-kiosk-ready.service
```

## Rollback

```bash
sudo systemctl disable --now aqms-kiosk-ready.service
sudo rm /etc/systemd/system/aqms-kiosk-ready.service
sudo rm /usr/local/libexec/aqms-kiosk-ready
sudo rm /etc/systemd/system/systemd-networkd-wait-online.service.d/50-aqms-ethernet-optional.conf
sudo rm /etc/netplan/99-aqms-resilience.yaml
sudo netplan generate
sudo systemctl daemon-reload
```

Rollback baru berlaku penuh pada reboot berikutnya. Jangan menjalankan
`netplan apply` melalui koneksi jarak jauh tanpa jalur akses cadangan.
