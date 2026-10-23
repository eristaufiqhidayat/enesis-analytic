# Enesis Analytic — Marketama Motoris Analytics

Aplikasi **Laravel 13 + Vue 3** yang mereplikasi halaman *Dashboard Performance* dari
`analytic.cnvmarketama.com/dashboard`: ringkasan performa penjualan dan aktivitas sales motoris,
lengkap dengan filter, grafik, tabel KPI per depo, SKU performance, dan tabel sales motoris.

Data awal (seeder) adalah snapshot dashboard sumber untuk periode **1–23 Oktober 2026**
(449 motoris aktif bertransaksi, 22 depo, 15 produk). Bila diagregasi, data harian hasil seeder
menghasilkan angka per depo dan per SKU yang sama dengan dashboard sumber.

## Fitur

- **Filter**: tahun, bulan, rentang tanggal, propinsi, kota, 11 kota besar, depo, Sales Koordinator, brand, SKU — saling bertingkat (cascading) dan langsung memuat ulang data.
- **Grafik** (ECharts): Success Rate by Depo, Total Sales by Depo, Success Rate & Total Sales 11 Kota Besar.
- **Tabel KPI per depo**: Kuota Motoris, Call, EC, IPT, Omset, SKU Performance — masing-masing bisa diekspor ke CSV/Excel.
- **Data Table Sales Motoris**: paginasi 50 baris, warna pencapaian, ekspor seluruh baris ke CSV/Excel.
- **Setting Target**: ubah target KPI (Call, EC, IPT, Omset, dll.) per tanggal berlaku.
- Login berbasis sesi, mode gelap, tampilan responsif. Menu lain (Sales Overview, Brand Overview, dst.) sudah disiapkan sebagai placeholder.

## Menjalankan

Kebutuhan: PHP 8.3+, Composer, Node.js 20+.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite      # atau atur DB_* untuk MySQL
php artisan migrate --seed          # ±100 ribu baris penjualan, beberapa detik
npm install
npm run build                       # atau `npm run dev` saat pengembangan
php artisan serve
```

Buka `http://localhost:8000` dan masuk dengan akun dari `.env` (`SEED_USER_USERNAME` / `SEED_USER_PASSWORD`,
bawaan `user` / `password`). **Ganti password ini sebelum dipakai di server.**

Di shared hosting yang membatasi jumlah thread, `npm run build` bisa gagal dengan pesan
*"Rolldown panicked … ThreadPoolBuildError"*. Jalankan dengan thread dibatasi:
`RAYON_NUM_THREADS=1 UV_THREADPOOL_SIZE=2 npm run build`, atau build di komputer lokal lalu
unggah folder `public/build` ke server.

Untuk memuat ulang data snapshot saja: `php artisan db:seed --class=MotorisSnapshotSeeder`.

## Struktur

| Bagian | Lokasi |
| --- | --- |
| Skema data | `database/migrations/2026_10_23_*` |
| Snapshot sumber | `database/data/snapshot.json`, `database/data/motoris.csv` |
| Pembangun data harian dari snapshot | `app/Support/Seeding/SnapshotBuilder.php` |
| Seluruh perhitungan dashboard | `app/Services/DashboardService.php` |
| API JSON | `app/Http/Controllers/Api/*`, `routes/web.php` (prefix `/api/v1`) |
| SPA Vue | `resources/js` (halaman di `pages/`, komponen di `components/`) |

### Tabel utama

- `daily_activities` — satu baris per motoris per hari hadir per depo: `calls`, `visit`, `ec`, `effective_call`, `freq_sale`, `total_sku`, `outlet`, `buying_outlet`, `point`, `total_sales`.
- `sales_items` — penjualan per produk per motoris per hari: `qty`, `eb`, `omset`.
- Master: `warehouses` (depo, kuota motoris, kota, 11 kota besar), `salesmen`, `koordinators`, `provinces`, `cities`, `big_cities`, `brands`, `products`, `holidays`, `kpi_targets`.

### API

| Endpoint | Isi |
| --- | --- |
| `GET /api/v1/dashboard` | Semua bagian dashboard + halaman tabel motoris (`page`, `limit`) |
| `GET /api/v1/dashboard/motoris` | Seluruh baris tabel motoris (untuk ekspor) |
| `GET /api/v1/dashboard/filters` | Opsi filter |
| `GET /api/v1/config/targets` | Target KPI yang berlaku |
| `GET/POST /api/v1/targets` | Riwayat & simpan target |

Parameter filter: `date_from`, `date_to` (Y-m-d), `province_id`, `city_id`, `big_city_id`,
`warehouse_id`, `koordinator_id`, `brand_id`, `product_id`.

## Definisi metrik

| Metrik | Rumus |
| --- | --- |
| HK Nasional | Hari Senin–Sabtu dalam periode, dikurangi tabel `holidays` |
| Hari Hadir | Jumlah hari motoris beraktivitas |
| Avg Call/Hari | Call ÷ hari hadir |
| Avg EC/Hari (depo) | EC outlet unik (`effective_call`) ÷ hari hadir |
| Avg EC/Hari (motoris) | EC transaksi (`ec`) ÷ hari hadir |
| IPT | Total SKU terjual ÷ EC transaksi |
| Omset/Hari | Total sales ÷ hari hadir |
| Success Rate | EC ÷ Call × 100 |
| Kuota Motoris — Actual | Jumlah motoris aktif yang terdaftar di depo |
| RATA-RATA (tabel KPI) | Rata-rata nilai per depo |

Filter **Brand/SKU** memengaruhi angka penjualan (total sales, omset, SKU performance);
metrik kunjungan (Call/EC) tetap dihitung dari seluruh aktivitas.

## Catatan

- Baris RATA-RATA pada dashboard sumber memakai rumus yang tidak terdokumentasi
  (mis. Call 36,95 sedangkan rata-rata per depo 37,94). Aplikasi ini memakai rata-rata per depo.
- Pembagian penjualan per produk ke tiap motoris/hari dibuat proporsional agar total per produk
  dan total per motoris sama dengan sumber; rincian per produk per depo tidak tersedia di sumber.
- Lima produk di luar 10 besar dan 10 motoris tanpa aktivitas (`Motoris Baru …`, untuk mencocokkan
  kuota) adalah pengisi agar total sesuai sumber.
