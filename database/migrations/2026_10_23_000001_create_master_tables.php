<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('province_id')->nullable()->index();
            $table->string('name');
        });

        // "11 Kota Besar" — kelompok wilayah besar yang menaungi beberapa depo.
        Schema::create('big_cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        // Depo / warehouse.
        Schema::create('warehouses', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->unsignedInteger('province_id')->nullable()->index();
            $table->unsignedInteger('city_id')->nullable()->index();
            $table->foreignId('big_city_id')->nullable()->index();
            $table->unsignedInteger('motoris_quota')->nullable();
        });

        // Sales Koordinator (SK).
        Schema::create('koordinators', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('brand_id')->nullable()->index();
            $table->string('name');
        });

        // Sales Motoris (frontliner).
        Schema::create('salesmen', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('warehouse_id')->index();
            $table->unsignedInteger('koordinator_id')->nullable()->index();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->string('name');
        });

        // Target KPI (Setting Target). Satu baris per periode berlaku.
        Schema::create('kpi_targets', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from');
            $table->unsignedInteger('target_call_per_day')->default(40);
            $table->unsignedInteger('target_ec_per_day')->default(25);
            $table->decimal('target_sku', 8, 2)->default(3);
            $table->unsignedInteger('target_point_per_day')->default(100);
            $table->unsignedInteger('target_min_point_insentif')->default(65);
            $table->unsignedBigInteger('target_value_per_day')->default(1000000);
            $table->unsignedInteger('target_qty_per_day')->default(50);
            $table->unsignedBigInteger('target_omset_per_motoris')->default(750000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['kpi_targets', 'holidays', 'salesmen', 'products', 'brands', 'koordinators', 'warehouses', 'big_cities', 'cities', 'provinces'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
