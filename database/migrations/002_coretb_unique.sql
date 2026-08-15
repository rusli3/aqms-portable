-- Jalankan setelah backup pada instalasi lama. Pertahankan baris terbaru jika
-- pernah ada dua agregat dengan waktu bucket yang sama.
DELETE older
FROM coretb AS older
JOIN coretb AS newer ON newer.waktu = older.waktu AND newer.no > older.no;

ALTER TABLE coretb ADD UNIQUE KEY uq_coretb_waktu (waktu);
