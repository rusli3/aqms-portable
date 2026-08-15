# Keamanan image container

## Image terkunci

| Komponen | Image | Digest index |
| --- | --- | --- |
| Web | `php:8.3.32-apache-bookworm` | `sha256:ff23b916...c00aae2` |
| Database base | `mysql:8.4.11-oraclelinux9` | `sha256:b3b90af2...57fd3fb` |

Digest mencegah perubahan image diam-diam. Dependabot tetap memantau tag Docker;
pembaruan digest harus melalui build, smoke test, backup/restore test, dan scan.

## Hasil baseline 15 Agustus 2026

Docker Scout pada image PHP resmi melaporkan 3 critical dan 7 high total, tetapi
**0 critical/high yang memiliki perbaikan tersedia**. Image MySQL resmi
melaporkan 2 critical dan 26 high yang ditandai fixable pada runtime Go/Python
milik paket `mysql-shell`. AQMS tidak menggunakan MySQL Shell, sehingga image
database turunan menghapus paket tersebut tanpa menghapus server, klien SQL,
`mysqldump`, atau entrypoint. Binary Go `gosu` diganti dengan `setpriv` dari paket
Oracle Linux `util-linux`; fungsi entrypoint untuk turun ke user `mysql` tetap
dipertahankan. Hasilnya diverifikasi lewat smoke test dan scan.

Scanner berbasis layer masih menghitung 2 critical/20 high dari `gosu` pada layer
base yang immutable. Verifikasi runtime memastikan `/usr/local/bin/gosu` dan
`mysqlsh` tidak ada, sedangkan `/usr/bin/setpriv` aktif pada entrypoint. Temuan
tersebut tidak dapat dieksekusi dari filesystem image akhir; flattening image
sengaja tidak dilakukan karena akan menghilangkan metadata/provenance vendor.

Kontrol kompensasi database: tidak ada port host, hanya jaringan Compose,
kredensial unik, healthcheck, backup, dan penggunaan query terbatas dari PHP.
Re-scan wajib ketika digest vendor berubah. Jangan menyatakan image bebas CVE;
nilai scan bergantung platform dan database advisory pada tanggal pemeriksaan.
