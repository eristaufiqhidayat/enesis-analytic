<?php

namespace App\Support\Seeding;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;

/**
 * Mengubah snapshot rekap dashboard (database/data) menjadi data transaksi harian
 * (daily_activities + sales_items) yang bila diagregasi kembali menghasilkan angka
 * yang sama persis dengan dashboard sumber untuk periode snapshot.
 *
 * Kelas ini sengaja tidak bergantung pada Laravel agar bisa diuji berdiri sendiri.
 */
class SnapshotBuilder
{
    private const METRICS = ['call', 'visit', 'ec', 'freq_sale', 'total_sku', 'outlet', 'point', 'total_sales'];

    /** Motoris yang berpindah depo di tengah periode: sebagian hari hadirnya tercatat di depo lain. */
    private const TRANSFERS = [
        1109 => [
            'to' => 'Cimahi',
            'days' => 10,
            'call' => 399, 'ec' => 293, 'freq_sale' => 293, 'total_sku' => 789, 'total_sales' => 11291000,
        ],
    ];

    /** Motoris terdaftar (aktif) tanpa aktivitas di periode snapshot, agar kuota motoris sesuai sumber. */
    private const ROSTER_ONLY = [
        'Banjarmasin' => 4,
        'Yogyakarta' => 2,
        'Balaraja' => 1,
        'Sidoarjo' => 1,
        'Negare' => 1,
        'Cimahi' => 1,
    ];

    /** Porsi produk di luar 10 besar (sisa omset/qty/EB dibagi dengan bobot ini). */
    private const OTHER_PRODUCTS = [
        'Kispray Hanger (Amoris)' => 0.35,
        'Kispray Hanger (Violet)' => 0.30,
        'Soffel Bunga Geranium Sachet 5.5 Gram' => 0.20,
        'AMUNIZER BOTOL 140ML' => 0.10,
        'Plossa mini starterpack' => 0.05,
    ];

    private array $snapshot;

    private array $motoris;

    public function __construct(string $dataDir)
    {
        $this->snapshot = json_decode(file_get_contents($dataDir.'/snapshot.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->motoris = $this->readCsv($dataDir.'/motoris.csv');
    }

    public function snapshot(): array
    {
        return $this->snapshot;
    }

    /** Hari kerja nasional (Senin–Sabtu, dikurangi libur) dalam periode snapshot. */
    public function workingDays(): array
    {
        $from = new DateTimeImmutable($this->snapshot['period']['date_from']);
        $to = (new DateTimeImmutable($this->snapshot['period']['date_to']))->modify('+1 day');
        $days = [];
        foreach (new DatePeriod($from, new DateInterval('P1D'), $to) as $day) {
            if ((int) $day->format('N') !== 7) {
                $days[] = $day->format('Y-m-d');
            }
        }

        return $days;
    }

    public function masters(): array
    {
        $f = $this->snapshot['filters'];
        $provinceId = array_column($f['provinces'], 'id', 'name');
        $cityId = array_column($f['cities'], 'id', 'name');
        $brandId = array_column($f['brands'], 'id', 'name');
        $quota = array_column($this->snapshot['motoris_quota'], 'kuota', 'depo');

        $bigCities = [];
        $bigCityOf = [];
        $order = 1;
        foreach ($this->snapshot['big_cities'] as $name => $depos) {
            $bigCities[] = ['id' => $order, 'name' => $name, 'sort_order' => $order];
            foreach ($depos as $depo) {
                $bigCityOf[$depo] = $order;
            }
            $order++;
        }

        $warehouses = [];
        foreach ($f['warehouses'] as $w) {
            $name = $w['name'];
            $warehouses[] = [
                'id' => $w['id'],
                'name' => $name,
                'province_id' => $provinceId[$this->snapshot['warehouse_province'][$name] ?? ''] ?? null,
                'city_id' => $cityId[$this->snapshot['warehouse_city'][$name] ?? ''] ?? null,
                'big_city_id' => $bigCityOf[$name] ?? null,
                'motoris_quota' => $quota[$name] ?? null,
            ];
        }

        // Kota diberi provinsi berdasarkan depo yang berada di kota tersebut (jika ada).
        $cityProvince = [];
        foreach ($warehouses as $w) {
            if ($w['city_id'] && $w['province_id']) {
                $cityProvince[$w['city_id']] = $w['province_id'];
            }
        }
        $cities = array_map(fn ($c) => [
            'id' => $c['id'],
            'name' => $c['name'],
            'province_id' => $cityProvince[$c['id']] ?? $this->guessProvince($c['name'], $provinceId),
        ], $f['cities']);

        $products = array_map(fn ($p) => [
            'id' => $p['id'],
            'name' => $p['name'],
            'brand_id' => $brandId[$this->snapshot['product_brand'][$p['name']] ?? ''] ?? null,
        ], $f['products']);

        return [
            'provinces' => $f['provinces'],
            'cities' => $cities,
            'big_cities' => $bigCities,
            'warehouses' => $warehouses,
            'koordinators' => $this->uniqueById($f['koordinators']),
            'brands' => $f['brands'],
            'products' => $products,
        ];
    }

    public function salesmen(): array
    {
        $warehouseId = array_column($this->snapshot['filters']['warehouses'], 'id', 'name');
        $koordinatorId = $this->koordinatorIds();
        $rows = [];
        foreach ($this->motoris as $m) {
            $rows[] = [
                'id' => (int) $m['id'],
                'name' => $m['name'],
                'is_active' => $m['is_active'] === '1',
                'warehouse_id' => $warehouseId[$m['depo']],
                'koordinator_id' => $koordinatorId[$m['koordinator']] ?? null,
            ];
        }

        $nextId = 900001;
        foreach (self::ROSTER_ONLY as $depo => $count) {
            for ($i = 1; $i <= $count; $i++) {
                $rows[] = [
                    'id' => $nextId++,
                    'name' => "Motoris Baru {$depo} {$i}",
                    'is_active' => true,
                    'warehouse_id' => $warehouseId[$depo],
                    'koordinator_id' => null,
                ];
            }
        }

        return $rows;
    }

    /** @return array<int, array> baris daily_activities */
    public function dailyActivities(): array
    {
        $warehouseId = array_column($this->snapshot['filters']['warehouses'], 'id', 'name');
        $koordinatorId = $this->koordinatorIds();
        $workingDays = $this->workingDays();
        $rows = [];

        foreach ($this->motoris as $m) {
            $id = (int) $m['id'];
            $hadir = (int) $m['hari_hadir'];
            $days = $this->pickDays($workingDays, $hadir, $id);
            $totals = array_map('intval', array_intersect_key($m, array_flip(self::METRICS)));
            $segments = [['depo' => $m['depo'], 'days' => $days, 'totals' => $totals]];

            if (isset(self::TRANSFERS[$id])) {
                $t = self::TRANSFERS[$id];
                $moved = [];
                $ratio = $t['call'] / max(1, $totals['call']);
                foreach (self::METRICS as $metric) {
                    $moved[$metric] = $t[$metric] ?? (int) round($totals[$metric] * $ratio);
                }
                $kept = [];
                foreach (self::METRICS as $metric) {
                    $kept[$metric] = $totals[$metric] - $moved[$metric];
                }
                $segments = [
                    ['depo' => $t['to'], 'days' => array_slice($days, 0, $t['days']), 'totals' => $moved],
                    ['depo' => $m['depo'], 'days' => array_slice($days, $t['days']), 'totals' => $kept],
                ];
            }

            foreach ($segments as $seg) {
                $n = count($seg['days']);
                $weights = $this->weights($n, $id);
                $split = [];
                foreach (self::METRICS as $metric) {
                    $unit = $metric === 'total_sales' ? $this->unit($seg['totals'][$metric]) : 1;
                    $split[$metric] = $this->allocate($seg['totals'][$metric], $weights, $unit);
                }
                foreach ($seg['days'] as $i => $date) {
                    $row = [
                        'date' => $date,
                        'salesman_id' => $id,
                        'warehouse_id' => $warehouseId[$seg['depo']],
                        'koordinator_id' => $koordinatorId[$m['koordinator']] ?? null,
                    ];
                    foreach (self::METRICS as $metric) {
                        $row[$metric === 'call' ? 'calls' : $metric] = $split[$metric][$i];
                    }
                    $rows[] = $row;
                }
            }
        }

        usort($rows, fn ($a, $b) => [$a['date'], $a['salesman_id']] <=> [$b['date'], $b['salesman_id']]);
        $this->assignEffectiveCalls($rows);
        $this->assignBuyingOutlets($rows);

        return $rows;
    }

    /**
     * Membagi penjualan tiap baris aktivitas ke produk sehingga:
     *  - jumlah omset produk per baris = total_sales baris tersebut, dan
     *  - jumlah per produk se-nasional = angka SKU Performance sumber.
     *
     * @return array<int, array> baris sales_items
     */
    public function salesItems(array $activities): array
    {
        $productId = array_column($this->snapshot['filters']['products'], 'id', 'name');
        $targets = $this->productTargets();

        $remOmset = [];
        $remQty = [];
        $remEb = [];
        foreach ($targets as $name => $t) {
            $remOmset[$name] = (int) $t['omset'];
            $remQty[$name] = (int) round($t['qty'] * 100);   // sen-qty agar desimal tetap tepat
            $remEb[$name] = (int) $t['eb'];
        }

        $items = [];
        $remainingTotal = array_sum($remOmset);
        foreach ($activities as $row) {
            $sales = (int) $row['total_sales'];
            if ($sales <= 0) {
                continue;
            }
            $names = array_keys(array_filter($remOmset, fn ($v) => $v > 0));
            $weights = array_map(fn ($n) => $remOmset[$n], $names);
            $alloc = $sales >= $remainingTotal
                ? array_map(fn ($n) => $remOmset[$n], $names)
                : $this->allocateCapped($sales, $weights, 10);

            foreach ($names as $i => $name) {
                $omset = $alloc[$i];
                if ($omset <= 0) {
                    continue;
                }
                $share = $omset / $remOmset[$name];
                $qty = (int) round($remQty[$name] * $share);
                $eb = (int) round($remEb[$name] * $share);
                if ($share >= 1) {
                    $qty = $remQty[$name];
                    $eb = $remEb[$name];
                }
                $remOmset[$name] -= $omset;
                $remQty[$name] -= $qty;
                $remEb[$name] -= $eb;

                $items[] = [
                    'date' => $row['date'],
                    'salesman_id' => $row['salesman_id'],
                    'warehouse_id' => $row['warehouse_id'],
                    'product_id' => $productId[$name],
                    'qty' => $qty / 100,
                    'eb' => $eb,
                    'omset' => $omset,
                ];
            }
            $remainingTotal -= $sales;
        }

        return $items;
    }

    /** Target per produk: 10 produk teratas dari sumber + sisa dibagi ke produk lain. */
    public function productTargets(): array
    {
        $targets = [];
        foreach ($this->snapshot['sku'] as $s) {
            $targets[$s['produk']] = ['qty' => $s['qty'], 'eb' => $s['eb'], 'omset' => $s['omset']];
        }
        $all = $this->snapshot['sku_all'];
        $restOmset = $all['omset'] - array_sum(array_column($targets, 'omset'));
        $restQty = (int) round(($all['qty'] - array_sum(array_column($targets, 'qty'))) * 100);
        $weights = array_values(self::OTHER_PRODUCTS);
        $omsets = $this->allocate($restOmset, $weights, 250);
        $qtys = $this->allocate($restQty, $weights, 100);
        foreach (array_keys(self::OTHER_PRODUCTS) as $i => $name) {
            $targets[$name] = [
                'qty' => $qtys[$i] / 100,
                'eb' => (int) round($qtys[$i] / 100 * 0.6),
                'omset' => $omsets[$i],
            ];
        }

        return $targets;
    }

    // ------------------------------------------------------------------ helpers

    /**
     * EC tingkat depo (outlet unik yang bertransaksi per kunjungan) sedikit lebih kecil dari
     * jumlah transaksi EC motoris. Total per depo diambil dari sumber lalu dibagi ke baris
     * sebanding dengan EC transaksi.
     */
    private function assignEffectiveCalls(array &$rows): void
    {
        $warehouseId = array_column($this->snapshot['filters']['warehouses'], 'id', 'name');
        $remaining = [];
        $remainingEc = [];
        foreach ($this->snapshot['by_region'] as $r) {
            $remaining[$warehouseId[$r['region']]] = (int) $r['ec'];
        }
        foreach ($rows as $row) {
            $remainingEc[$row['warehouse_id']] = ($remainingEc[$row['warehouse_id']] ?? 0) + (int) $row['ec'];
        }
        foreach ($rows as &$row) {
            $w = $row['warehouse_id'];
            $ec = (int) $row['ec'];
            if (! isset($remaining[$w])) {
                $row['effective_call'] = $ec;

                continue;
            }
            $value = $remainingEc[$w] > 0 ? (int) round($remaining[$w] * $ec / $remainingEc[$w]) : 0;
            $value = min($value, $ec, $remaining[$w]);
            $row['effective_call'] = $value;
            $remaining[$w] -= $value;
            $remainingEc[$w] -= $ec;
        }
    }

    private function assignBuyingOutlets(array &$rows): void
    {
        $remaining = (int) $this->snapshot['sku_all']['eb'];
        $remainingEc = array_sum(array_column($rows, 'ec'));
        foreach ($rows as &$row) {
            $ec = (int) $row['ec'];
            $value = $remainingEc > 0 ? (int) round($remaining * $ec / $remainingEc) : 0;
            $value = min($value, $ec, $remaining);
            $row['buying_outlet'] = $value;
            $remaining -= $value;
            $remainingEc -= $ec;
        }
    }

    private function pickDays(array $workingDays, int $count, int $seed): array
    {
        $n = count($workingDays);
        $count = min($count, $n);
        if ($count === 0) {
            return [];
        }
        $offset = $seed % max(1, $n - $count + 1);
        if ($count === $n) {
            return $workingDays;
        }
        $picked = [];
        for ($i = 0; $i < $count; $i++) {
            $index = (int) floor($i * ($n - $offset) / $count) + $offset;
            $picked[$index] = $workingDays[min($index, $n - 1)];
        }
        // isi kekurangan bila ada indeks yang bertabrakan
        for ($i = 0; count($picked) < $count && $i < $n; $i++) {
            $picked[$i] ??= $workingDays[$i];
        }
        ksort($picked);

        return array_values($picked);
    }

    private function weights(int $n, int $seed): array
    {
        $weights = [];
        $state = $seed * 2654435761 % 4294967296;
        for ($i = 0; $i < $n; $i++) {
            $state = (1103515245 * $state + 12345) % 2147483648;
            $weights[] = 0.75 + 0.5 * ($state / 2147483648);
        }

        return $weights;
    }

    private function unit(int $total): int
    {
        return $total % 250 === 0 ? 250 : ($total % 10 === 0 ? 10 : 1);
    }

    /** Pembagian bilangan bulat dengan metode sisa terbesar. Total selalu tepat. */
    private function allocate(int $total, array $weights, int $unit = 1): array
    {
        $n = count($weights);
        if ($n === 0) {
            return [];
        }
        $units = intdiv($total, $unit);
        $remainder = $total - $units * $unit;
        $sum = array_sum($weights) ?: 1;
        $base = [];
        $fractions = [];
        foreach ($weights as $i => $w) {
            $exact = $units * $w / $sum;
            $base[$i] = (int) floor($exact);
            $fractions[$i] = $exact - $base[$i];
        }
        $left = $units - array_sum($base);
        arsort($fractions);
        foreach (array_keys($fractions) as $i) {
            if ($left-- <= 0) {
                break;
            }
            $base[$i]++;
        }
        $result = array_map(fn ($b) => $b * $unit, $base);
        $result[$n - 1] += $remainder;

        return $result;
    }

    /** Seperti allocate(), tetapi tidak ada bagian yang melebihi bobotnya (sisa kapasitas). */
    private function allocateCapped(int $total, array $caps, int $unit): array
    {
        $result = $this->allocate($total, $caps, $unit);
        $overflow = 0;
        foreach ($result as $i => $value) {
            if ($value > $caps[$i]) {
                $overflow += $value - $caps[$i];
                $result[$i] = $caps[$i];
            }
        }
        foreach ($result as $i => $value) {
            if ($overflow <= 0) {
                break;
            }
            $room = $caps[$i] - $value;
            $take = min($room, $overflow);
            $result[$i] += $take;
            $overflow -= $take;
        }

        return $result;
    }

    private function koordinatorIds(): array
    {
        $ids = [];
        foreach ($this->snapshot['filters']['koordinators'] as $k) {
            $ids[$k['name']] ??= $k['id'];
        }

        return $ids;
    }

    private function uniqueById(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[$r['id']] = $r;
        }

        return array_values($out);
    }

    private function guessProvince(string $city, array $provinceId): ?int
    {
        $map = [
            'Badung' => 'Bali', 'Buleleng' => 'Bali', 'Gianyar' => 'Bali', 'Jembrana' => 'Bali', 'Tabanan' => 'Bali', 'Denpasar' => 'Bali',
            'Bandung' => 'Jawa Barat', 'Bekasi' => 'Jawa Barat', 'Bogor' => 'Jawa Barat', 'Karawang' => 'Jawa Barat', 'Pangandaran' => 'Jawa Barat',
            'Sumedang' => 'Jawa Barat', 'Cimahi' => 'Jawa Barat', 'Depok' => 'Jawa Barat', 'Sukabumi' => 'Jawa Barat',
            'Banjar' => 'Kalimantan Selatan', 'Barito Kuala' => 'Kalimantan Selatan', 'Banjarbaru' => 'Kalimantan Selatan', 'Banjarmasin' => 'Kalimantan Selatan',
            'Bantul' => 'DI Yogyakarta', 'Kulon Progo' => 'DI Yogyakarta', 'Sleman' => 'DI Yogyakarta', 'Yogyakarta' => 'DI Yogyakarta',
            'Banyuasin' => 'Sumatera Selatan', 'Musi Banyuasin' => 'Sumatera Selatan', 'Ogan Ilir' => 'Sumatera Selatan', 'Palembang' => 'Sumatera Selatan',
            'Deli Serdang' => 'Sumatera Utara', 'Langkat' => 'Sumatera Utara', 'Binjai' => 'Sumatera Utara', 'Medan' => 'Sumatera Utara',
            'Demak' => 'Jawa Tengah', 'Kendal' => 'Jawa Tengah', 'Semarang' => 'Jawa Tengah',
            'Gowa' => 'Sulawesi Selatan', 'Maros' => 'Sulawesi Selatan', 'Takalar' => 'Sulawesi Selatan', 'Makassar' => 'Sulawesi Selatan',
            'Gresik' => 'Jawa Timur', 'Lamongan' => 'Jawa Timur', 'Mojokerto' => 'Jawa Timur', 'Pasuruan' => 'Jawa Timur', 'Sidoarjo' => 'Jawa Timur', 'Surabaya' => 'Jawa Timur',
            'Serang' => 'Banten', 'Tangerang' => 'Banten',
            'Jakarta' => 'DKI Jakarta',
        ];
        $bare = preg_replace('/^(Kabupaten|Kota)\s+/', '', $city);
        foreach ($map as $needle => $province) {
            if (str_starts_with($bare, $needle)) {
                return $provinceId[$province] ?? null;
            }
        }

        return null;
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, separator: ';', escape: '');
        $rows = [];
        while (($line = fgetcsv($handle, separator: ';', escape: '')) !== false) {
            if ($line === [null]) {
                continue;
            }
            $rows[] = array_combine($header, $line);
        }
        fclose($handle);

        return $rows;
    }
}
