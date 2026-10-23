<?php

namespace App\Services;

use App\Models\KpiTarget;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Seluruh perhitungan "Dashboard Performance".
 *
 * Definisi metrik (mengikuti dashboard sumber):
 *  - Hari Hadir      : jumlah hari motoris tercatat beraktivitas (baris daily_activities).
 *  - HK Nasional     : hari kerja Senin–Sabtu dalam periode, dikurangi hari libur.
 *  - Avg Call/Hari   : total call / hari hadir.
 *  - Avg EC/Hari     : depo → EC outlet unik / hari hadir; motoris → EC transaksi / hari hadir.
 *  - IPT             : total SKU terjual / EC transaksi.
 *  - Omset/Hari      : total sales / hari hadir.
 *  - Success Rate    : EC / Call × 100.
 *  - RATA-RATA       : rata-rata nilai per depo pada tabel tersebut.
 */
class DashboardService
{
    public const PER_PAGE = 50;

    private array $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function dateFrom(): string
    {
        return $this->filters['date_from'];
    }

    public function dateTo(): string
    {
        return $this->filters['date_to'];
    }

    public function targets(): array
    {
        $target = KpiTarget::query()
            ->where('effective_from', '<=', $this->dateTo())
            ->orderByDesc('effective_from')
            ->first() ?? new KpiTarget;

        return [
            'target_call_per_day' => (int) ($target->target_call_per_day ?? 40),
            'target_ec_per_day' => (int) ($target->target_ec_per_day ?? 25),
            'target_sku' => (float) ($target->target_sku ?? 3),
            'target_point_per_day' => (int) ($target->target_point_per_day ?? 100),
            'target_min_point_insentif' => (int) ($target->target_min_point_insentif ?? 65),
            'target_value_per_day' => (int) ($target->target_value_per_day ?? 1000000),
            'target_qty_per_day' => (int) ($target->target_qty_per_day ?? 50),
            'target_omset_per_motoris' => (int) ($target->target_omset_per_motoris ?? 750000),
        ];
    }

    public function holidays(): Collection
    {
        return DB::table('holidays')
            ->whereBetween('date', [$this->dateFrom(), $this->dateTo()])
            ->orderBy('date')
            ->get(['date', 'name']);
    }

    public function hkNasional(): int
    {
        $holidays = $this->holidays()->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->all();
        $count = 0;
        foreach (CarbonPeriod::create($this->dateFrom(), $this->dateTo()) as $day) {
            if ($day->dayOfWeekIso !== 7 && ! in_array($day->toDateString(), $holidays, true)) {
                $count++;
            }
        }

        return $count;
    }

    public function summary(int $page = 1, int $limit = self::PER_PAGE): array
    {
        $targets = $this->targets();
        $byDepo = $this->byDepo();

        $call = $this->perDepoKpi($byDepo, fn ($d) => $d->hadir ? $d->calls / $d->hadir : 0, $targets['target_call_per_day']);
        $ec = $this->perDepoKpi($byDepo, fn ($d) => $d->hadir ? $d->effective_call / $d->hadir : 0, $targets['target_ec_per_day']);
        $ipt = $this->perDepoKpi($byDepo, fn ($d) => $d->ec ? $d->total_sku / $d->ec : 0, $targets['target_sku']);
        $omset = $this->perDepoKpi($byDepo, fn ($d) => $d->hadir ? $d->total_sales / $d->hadir : 0, $targets['target_omset_per_motoris']);

        $table = $this->frontlinerTable($page, $limit);

        return [
            'period' => [
                'date_from' => $this->dateFrom(),
                'date_to' => $this->dateTo(),
                'hk_nasional' => $this->hkNasional(),
                'holidays' => $this->holidays(),
            ],
            'targets' => $targets,
            'kpi' => $this->kpi($byDepo),
            'by_region' => $byDepo->map(fn ($d) => [
                'warehouse_id' => (int) $d->warehouse_id,
                'region' => $d->region,
                'call' => (int) $d->calls,
                'ec' => (int) $d->effective_call,
                'sales' => (int) $d->freq_sale,
                'success_rate' => $this->pct($d->effective_call, $d->calls),
                'total_sales' => (int) $d->total_sales,
                'frontliner_count' => (int) $d->frontliner_count,
            ])->values(),
            'by_big_city' => $this->byBigCity(),
            'motoris_quota' => $this->motorisQuota(),
            'call_by_depo' => $call['rows'],
            'call_total' => $call['total'],
            'ec_by_depo' => $ec['rows'],
            'ec_total' => $ec['total'],
            'ipt_by_depo' => $ipt['rows'],
            'ipt_total' => $ipt['total'],
            'omset_by_depo' => $omset['rows'],
            'omset_total' => $omset['total'],
            'sku_performance' => $this->skuPerformance(),
            'frontliner_table' => $table['rows'],
            'pagination' => $table['pagination'],
        ];
    }

    // ----------------------------------------------------------------- sections

    /** Agregat aktivitas per depo, diurutkan dari total sales terbesar. */
    public function byDepo(): Collection
    {
        $sales = $this->salesFilterActive() ? $this->salesItemsQuery()
            ->groupBy('si.warehouse_id')
            ->selectRaw('si.warehouse_id as id, SUM(si.omset) as omset')
            ->pluck('omset', 'id') : null;

        $rows = $this->activityQuery()
            ->join('salesmen as s', 's.id', '=', 'a.salesman_id')
            ->groupBy('a.warehouse_id', 'w.name')
            ->selectRaw('a.warehouse_id, w.name as region,
                SUM(a.calls) as calls, SUM(a.visit) as visit, SUM(a.ec) as ec, SUM(a.effective_call) as effective_call,
                SUM(a.freq_sale) as freq_sale, SUM(a.total_sku) as total_sku, SUM(a.outlet) as outlet,
                SUM(a.buying_outlet) as buying_outlet, SUM(a.total_sales) as total_sales, COUNT(*) as hadir,
                COUNT(DISTINCT CASE WHEN s.is_active = 1 THEN a.salesman_id END) as frontliner_count')
            ->get();

        if ($sales !== null) {
            $rows->each(fn ($r) => $r->total_sales = (int) ($sales[$r->warehouse_id] ?? 0));
        }

        return $rows->sortByDesc(fn ($r) => (int) $r->total_sales)->values();
    }

    public function byBigCity(): Collection
    {
        $byDepo = $this->byDepo()->keyBy('warehouse_id');
        $warehouses = DB::table('warehouses')->whereNotNull('big_city_id')->get(['id', 'big_city_id'])->groupBy('big_city_id');
        $bigCities = DB::table('big_cities')->orderBy('sort_order')->get();
        if (! empty($this->filters['big_city_id'])) {
            $bigCities = $bigCities->where('id', (int) $this->filters['big_city_id']);
        }

        return $bigCities->map(function ($city) use ($warehouses, $byDepo) {
            $depos = collect($warehouses[$city->id] ?? [])->map(fn ($w) => $byDepo[$w->id] ?? null)->filter();
            $call = $depos->sum('calls');
            $ec = $depos->sum('effective_call');

            return [
                'big_city_id' => (int) $city->id,
                'name' => $city->name,
                'call' => (int) $call,
                'ec' => (int) $ec,
                'success_rate' => $this->pct($ec, $call),
                'total_sales' => (int) $depos->sum('total_sales'),
            ];
        })->values();
    }

    public function motorisQuota(): Collection
    {
        $query = DB::table('warehouses as w')
            ->leftJoin('salesmen as s', fn ($j) => $j->on('s.warehouse_id', '=', 'w.id')->where('s.is_active', true))
            ->whereNotNull('w.motoris_quota')
            ->groupBy('w.id', 'w.name', 'w.motoris_quota')
            ->orderBy('w.name')
            ->selectRaw('w.name as depo, w.motoris_quota as kuota, COUNT(s.id) as actual');
        $this->applyGeoFilters($query, 'w.id');
        if (! empty($this->filters['koordinator_id'])) {
            $query->where('s.koordinator_id', (int) $this->filters['koordinator_id']);
        }

        return $query->get()->map(fn ($r) => [
            'depo' => $r->depo,
            'kuota' => (int) $r->kuota,
            'actual' => (int) $r->actual,
            'ach_pct' => $this->pct($r->actual, $r->kuota),
        ])->sortBy('depo', SORT_STRING)->values();
    }

    public function skuPerformance(): array
    {
        $rows = $this->salesItemsQuery()
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->groupBy('si.product_id', 'p.name')
            ->selectRaw('p.name as produk, SUM(si.qty) as qty, SUM(si.eb) as eb, SUM(si.omset) as omset')
            ->orderByDesc('omset')
            ->get();

        $buyingOutlets = $this->salesFilterActive()
            ? (int) $rows->sum('eb')
            : (int) $this->activityQuery()->sum('a.buying_outlet');

        $top = $rows->take(10)->map(fn ($r) => [
            'produk' => $r->produk,
            'qty' => round((float) $r->qty, 2),
            'eb' => (int) $r->eb,
            'omset' => (int) $r->omset,
        ])->values()->all();

        $top[] = [
            'produk' => 'All Products',
            'qty' => round((float) $rows->sum('qty'), 2),
            'eb' => min($buyingOutlets, (int) $rows->sum('eb')),
            'omset' => (int) $rows->sum('omset'),
            'is_total' => true,
        ];

        return $top;
    }

    public function frontlinerTable(int $page = 1, ?int $limit = self::PER_PAGE): array
    {
        $base = $this->activityQuery()
            ->join('salesmen as s', 's.id', '=', 'a.salesman_id')
            ->leftJoin('koordinators as k', 'k.id', '=', 's.koordinator_id')
            ->join('warehouses as hw', 'hw.id', '=', 's.warehouse_id')
            ->groupBy('a.salesman_id', 's.name', 's.is_active', 'k.name', 'hw.name')
            ->selectRaw('a.salesman_id as id_frontliner, s.name as frontliner, s.is_active, k.name as teamleader,
                hw.name as project, SUM(a.calls) as calls, SUM(a.visit) as visit, SUM(a.ec) as ec,
                SUM(a.freq_sale) as freq_sale, SUM(a.total_sku) as total_sku, SUM(a.outlet) as outlet,
                SUM(a.total_sales) as total_sales, COUNT(DISTINCT a.date) as hari_hadir, SUM(a.point) as point');

        $total = DB::query()->fromSub($base, 't')->count();
        $query = DB::query()->fromSub($base, 't')->orderByDesc('total_sales')->orderBy('id_frontliner');
        if ($limit) {
            $query->forPage(max(1, $page), $limit);
        }
        $rows = $query->get();

        if ($this->salesFilterActive() && $rows->isNotEmpty()) {
            $sales = $this->salesItemsQuery()
                ->whereIn('si.salesman_id', $rows->pluck('id_frontliner'))
                ->groupBy('si.salesman_id')
                ->selectRaw('si.salesman_id as id, SUM(si.omset) as omset')
                ->pluck('omset', 'id');
            $rows->each(fn ($r) => $r->total_sales = (int) ($sales[$r->id_frontliner] ?? 0));
        }

        $targets = $this->targets();
        $hk = $this->hkNasional();

        return [
            'rows' => $rows->map(function ($r) use ($targets, $hk) {
                $hadir = (int) $r->hari_hadir;
                $avgCall = $hadir ? $r->calls / $hadir : 0;
                $avgEc = $hadir ? $r->ec / $hadir : 0;
                $avgIpt = $r->ec ? $r->total_sku / $r->ec : 0;

                return [
                    'id_frontliner' => (int) $r->id_frontliner,
                    'frontliner' => $r->frontliner,
                    'is_active' => (bool) $r->is_active,
                    'teamleader' => $r->teamleader,
                    'project' => $r->project,
                    'hari_kerja' => $hk,
                    'hari_hadir' => $hadir,
                    'target_call' => $targets['target_call_per_day'],
                    'call' => (int) $r->calls,
                    'avg_call' => round($avgCall, 2),
                    'sr_call' => $this->pct($avgCall, $targets['target_call_per_day']),
                    'target_ec' => $targets['target_ec_per_day'],
                    'ec' => (int) $r->ec,
                    'avg_ec' => round($avgEc, 2),
                    'sr_ec' => $this->pct($avgEc, $targets['target_ec_per_day']),
                    'target_ipt' => $targets['target_sku'],
                    'avg_ipt' => round($avgIpt, 2),
                    'sr_ipt' => $this->pct($avgIpt, $targets['target_sku']),
                    'visit' => (int) $r->visit,
                    'freq_sale' => (int) $r->freq_sale,
                    'total_sku' => (int) $r->total_sku,
                    'outlet' => (int) $r->outlet,
                    'point' => (int) $r->point,
                    'total_sales' => (int) $r->total_sales,
                ];
            })->values(),
            'pagination' => [
                'page' => $limit ? max(1, $page) : 1,
                'limit' => $limit ?? $total,
                'total' => $total,
                'total_pages' => $limit ? (int) ceil($total / $limit) : 1,
            ],
        ];
    }

    // ------------------------------------------------------------------ queries

    private function kpi(Collection $byDepo): array
    {
        $call = (int) $byDepo->sum('calls');
        $ec = (int) $byDepo->sum('effective_call');
        $hadir = (int) $byDepo->sum('hadir');
        $motoris = (int) $this->activityQuery()->distinct()->count('a.salesman_id');

        return [
            'call' => $call,
            'effective_call' => $ec,
            'freq_sales' => (int) $byDepo->sum('freq_sale'),
            'visit_outlet' => (int) $byDepo->sum('visit'),
            'total_sales' => (int) $byDepo->sum('total_sales'),
            'frontliner' => $motoris,
            'success_rate' => $this->pct($ec, $call),
            'avg_hari_kerja' => $motoris ? round($hadir / $motoris, 2) : 0,
            'avg_ec_per_day' => $hadir ? round($byDepo->sum('ec') / $hadir, 2) : 0,
            'avg_ipt_per_day' => $byDepo->sum('ec') ? round($byDepo->sum('total_sku') / $byDepo->sum('ec'), 2) : 0,
            'avg_sales_amt_per_motoris' => $hadir ? round($byDepo->sum('total_sales') / $hadir, 2) : 0,
        ];
    }

    private function perDepoKpi(Collection $byDepo, callable $actual, float $target): array
    {
        $rows = $byDepo->map(fn ($d) => ['depo' => $d->region, 'actual' => (float) $actual($d)])
            ->sortBy('depo', SORT_STRING)
            ->values()
            ->map(fn ($r) => $r + ['target' => $target, 'ach_pct' => $this->pct($r['actual'], $target)]);

        $avg = $rows->count() ? $rows->avg('actual') : 0;

        return [
            'rows' => $rows,
            'total' => ['target' => $target, 'actual' => round($avg, 2), 'ach_pct' => round($this->pct($avg, $target), 1)],
        ];
    }

    private function activityQuery(): Builder
    {
        $query = DB::table('daily_activities as a')
            ->join('warehouses as w', 'w.id', '=', 'a.warehouse_id')
            ->whereBetween('a.date', [$this->dateFrom(), $this->dateTo()]);
        $this->applyGeoFilters($query, 'a.warehouse_id');
        if (! empty($this->filters['koordinator_id'])) {
            $query->where('a.koordinator_id', (int) $this->filters['koordinator_id']);
        }

        return $query;
    }

    private function salesItemsQuery(): Builder
    {
        $query = DB::table('sales_items as si')
            ->join('warehouses as w', 'w.id', '=', 'si.warehouse_id')
            ->whereBetween('si.date', [$this->dateFrom(), $this->dateTo()]);
        $this->applyGeoFilters($query, 'si.warehouse_id');
        if (! empty($this->filters['koordinator_id'])) {
            $query->whereIn('si.salesman_id', DB::table('salesmen')->where('koordinator_id', (int) $this->filters['koordinator_id'])->select('id'));
        }
        if (! empty($this->filters['product_id'])) {
            $query->where('si.product_id', (int) $this->filters['product_id']);
        }
        if (! empty($this->filters['brand_id'])) {
            $query->whereIn('si.product_id', DB::table('products')->where('brand_id', (int) $this->filters['brand_id'])->select('id'));
        }

        return $query;
    }

    private function applyGeoFilters(Builder $query, string $warehouseColumn): void
    {
        foreach (['province_id' => 'w.province_id', 'city_id' => 'w.city_id', 'big_city_id' => 'w.big_city_id'] as $key => $column) {
            if (! empty($this->filters[$key])) {
                $query->where($column, (int) $this->filters[$key]);
            }
        }
        if (! empty($this->filters['warehouse_id'])) {
            $query->where($warehouseColumn, (int) $this->filters['warehouse_id']);
        }
    }

    private function salesFilterActive(): bool
    {
        return ! empty($this->filters['brand_id']) || ! empty($this->filters['product_id']);
    }

    private function pct(float|int|null $value, float|int|null $base): float
    {
        return $base ? round($value / $base * 100, 2) : 0.0;
    }

    public static function defaultPeriod(): array
    {
        $today = CarbonImmutable::now();

        return [$today->startOfMonth()->toDateString(), $today->toDateString()];
    }
}
