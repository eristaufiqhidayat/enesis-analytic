# Panduan pengembangan

- Backend: Laravel 13 (PHP 8.3). Semua perhitungan dashboard ada di `app/Services/DashboardService.php`; controller hanya memvalidasi filter.
- Kolom kunjungan memakai nama `calls` (bukan `call`, kata kunci MySQL).
- Frontend: Vue 3 + vue-router + ECharts (vue-echarts), Tailwind CSS 4, dibangun dengan Vite. Entry `resources/js/app.js`.
- API berada di `routes/web.php` dengan prefix `api/v1` dan memakai sesi web (cookie), bukan token.
- Data contoh: `php artisan migrate:fresh --seed`. Snapshot sumber di `database/data`.
- Gaya kode: Pint (PSR-12 gaya Laravel) untuk PHP, indentasi 4 spasi untuk Vue/JS.
