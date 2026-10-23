<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rekap harian aktivitas motoris. Satu baris = satu motoris hadir (check-in) di satu hari
        // pada satu depo. Jumlah baris per motoris = "Hari Hadir".
        Schema::create('daily_activities', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedInteger('salesman_id');
            $table->unsignedInteger('warehouse_id');
            $table->unsignedInteger('koordinator_id')->nullable();
            $table->unsignedInteger('calls')->default(0);       // kunjungan terjadwal yang dilakukan
            $table->unsignedInteger('visit')->default(0);       // total visit (termasuk di luar rute)
            $table->unsignedInteger('ec')->default(0);          // EC transaksi (dasar tabel motoris & IPT)
            $table->unsignedInteger('effective_call')->default(0); // EC outlet unik (dasar grafik & tabel EC per depo)
            $table->unsignedInteger('freq_sale')->default(0);   // jumlah transaksi
            $table->unsignedInteger('total_sku')->default(0);   // jumlah SKU terjual (dasar IPT)
            $table->unsignedInteger('outlet')->default(0);      // outlet dikunjungi
            $table->unsignedInteger('buying_outlet')->default(0); // outlet unik yang membeli (EB)
            $table->unsignedInteger('point')->default(0);
            $table->unsignedBigInteger('total_sales')->default(0); // rupiah
            $table->timestamps();

            $table->unique(['date', 'salesman_id', 'warehouse_id']);
            $table->index(['warehouse_id', 'date']);
            $table->index(['salesman_id', 'date']);
        });

        // Penjualan per produk per motoris per hari.
        Schema::create('sales_items', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedInteger('salesman_id');
            $table->unsignedInteger('warehouse_id');
            $table->unsignedInteger('product_id');
            $table->decimal('qty', 14, 2)->default(0);
            $table->unsignedInteger('eb')->default(0);          // outlet pembeli (effective buyer) produk ini
            $table->unsignedBigInteger('omset')->default(0);

            $table->index(['warehouse_id', 'date']);
            $table->index(['product_id', 'date']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_items');
        Schema::dropIfExists('daily_activities');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
