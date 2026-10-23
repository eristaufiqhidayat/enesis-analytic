<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $service = new DashboardService($this->parseFilters($request));
        $limit = min(100, max(1, (int) $request->integer('limit', DashboardService::PER_PAGE)));

        return $this->ok($service->summary(max(1, $request->integer('page', 1)), $limit));
    }

    /** Seluruh baris tabel motoris (untuk ekspor CSV/Excel). */
    public function motoris(Request $request): JsonResponse
    {
        $service = new DashboardService($this->parseFilters($request));

        return $this->ok($service->frontlinerTable(1, null));
    }

    public function filters(): JsonResponse
    {
        return $this->ok([
            'years' => $this->years(),
            'provinces' => DB::table('provinces')->orderBy('name')->get(['id', 'name']),
            'cities' => DB::table('cities')->orderBy('name')->get(['id', 'name', 'province_id']),
            'big_cities' => DB::table('big_cities')->orderBy('sort_order')->get(['id', 'name']),
            'warehouses' => DB::table('warehouses')->orderBy('name')->get(['id', 'name', 'province_id', 'city_id', 'big_city_id']),
            'koordinators' => DB::table('koordinators as k')
                ->leftJoin('salesmen as s', 's.koordinator_id', '=', 'k.id')
                ->groupBy('k.id', 'k.name')
                ->orderBy('k.name')
                ->selectRaw('k.id, k.name, MIN(s.warehouse_id) as warehouse_id')
                ->get(),
            'brands' => DB::table('brands')->orderBy('name')->get(['id', 'name']),
            'products' => DB::table('products')->orderBy('name')->get(['id', 'name', 'brand_id']),
        ]);
    }

    public function targets(Request $request): JsonResponse
    {
        return $this->ok((new DashboardService($this->parseFilters($request)))->targets());
    }

    private function parseFilters(Request $request): array
    {
        [$defaultFrom, $defaultTo] = DashboardService::defaultPeriod();
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'province_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'big_city_id' => ['nullable', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'koordinator_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
        ]);

        return array_merge($validated, [
            'date_from' => $validated['date_from'] ?? $defaultFrom,
            'date_to' => $validated['date_to'] ?? $defaultTo,
        ]);
    }

    private function years(): array
    {
        $min = DB::table('daily_activities')->min('date');
        $first = $min ? (int) substr((string) $min, 0, 4) : (int) date('Y');

        return range(min($first, (int) date('Y') - 2), (int) date('Y'));
    }

    private function ok(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }
}
