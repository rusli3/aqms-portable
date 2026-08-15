# Perhitungan ISPU PM2.5 dan PM10

Dashboard menggunakan tabel konversi pada Peraturan Menteri Lingkungan Hidup dan
Kehutanan Nomor P.14/MENLHK/SETJEN/KUM.1/7/2020.

## Rumus

```text
I = ((Ia - Ib) / (Xa - Xb)) × (Xx - Xb) + Ib
```

Keterangan:

- `I`: ISPU terhitung
- `Ia`/`Ib`: ISPU batas atas/bawah
- `Xa`/`Xb`: konsentrasi batas atas/bawah
- `Xx`: konsentrasi rerata hasil pengukuran

## Breakpoint

| ISPU | PM10 (µg/m³) | PM2.5 (µg/m³) |
| ---: | ---: | ---: |
| 50 | 50 | 15,5 |
| 100 | 150 | 55,4 |
| 200 | 350 | 150,4 |
| 300 | 420 | 250,4 |
| 500 | 500 | 500 |

| Rentang indeks | Kategori |
| --- | --- |
| 0–50 | Baik |
| 51–100 | Sedang |
| 101–200 | Tidak Sehat |
| 201–300 | Sangat Tidak Sehat |
| >300 | Berbahaya |

## Implementasi dashboard

1. Cari waktu agregat terbaru pada `coretb`.
2. Ambil data hingga 24 jam ke belakang dari waktu tersebut.
3. Hitung rerata PM2.5 dan PM10.
4. Pilih dua breakpoint yang mengapit konsentrasi.
5. Lakukan interpolasi dan bulatkan ke indeks terdekat.
6. Batasi nilai implementasi maksimum pada 500.
7. Periksa kelengkapan: rentang minimal 23 jam 45 menit, minimal 260 dari 289
   titik lima-menit (≈90%), dan jeda maksimum 15 menit.
8. Tampilkan **ISPU 24 JAM** hanya jika ketiga syarat terpenuhi; selain itu
   tampilkan **ISPU Sementara**.

Pemeriksaan ini mencegah dua sampel yang berjauhan dianggap sebagai data kontinu.
Label sementara penting karena perangkat yang baru menyala atau mengalami jeda
belum memiliki data 24 jam yang memadai. Nilainya tidak boleh
disamakan dengan pelaporan resmi tanpa pemeriksaan kelengkapan data dan QA/QC.

## Rujukan

- [Portal Direktorat Pengendalian Pencemaran Udara KLHK](https://ditppu.menlhk.go.id/portal/read/indeks-standar-pencemar-udara-ispu-sebagai-informasi-mutu-udara-ambien-di-indonesia)
- [Permen LHK P.14/2020](https://jdih.menlhk.go.id/new2/uploads/files/P_14_2020_ISPU_menlhk_07302020074834.pdf)
