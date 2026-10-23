<?php

namespace Database\Seeders;

use App\Support\Seeding\SnapshotBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi master data dan transaksi harian periode 1–23 Oktober 2026 dari snapshot
 * dashboard Marketama Motoris Analytics (database/data).
 */
class MotorisSnapshotSeeder extends Seeder
{
    public function run(): void
    {
        $builder = new SnapshotBuilder(database_path('data'));
        $snapshot = $builder->snapshot();
        $masters = $builder->masters();
        $now = now();

        DB::transaction(function () use ($builder, $snapshot, $masters, $now) {
            foreach (['sales_items', 'daily_activities', 'salesmen', 'products', 'brands', 'koordinators', 'warehouses', 'big_cities', 'cities', 'provinces', 'holidays', 'kpi_targets'] as $table) {
                DB::table($table)->delete();
            }

            foreach ($masters as $table => $rows) {
                $this->insert($table, $rows);
            }

            $this->insert('salesmen', $builder->salesmen());

            DB::table('kpi_targets')->insert(array_merge(
                $snapshot['targets'],
                ['effective_from' => '2024-01-01', 'created_at' => $now, 'updated_at' => $now],
            ));

            $activities = $builder->dailyActivities();
            $this->insert('daily_activities', array_map(
                fn ($row) => $row + ['created_at' => $now, 'updated_at' => $now],
                $activities,
            ));
            $this->insert('sales_items', $builder->salesItems($activities));
        });

        $this->command?->info(sprintf(
            'Snapshot dimuat: %d motoris, %d baris aktivitas harian, %d baris penjualan produk.',
            DB::table('salesmen')->count(),
            DB::table('daily_activities')->count(),
            DB::table('sales_items')->count(),
        ));
    }

    private function insert(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
