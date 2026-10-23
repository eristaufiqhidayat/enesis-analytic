<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KpiTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Menu "Setting Target": melihat dan menyimpan target KPI motoris. */
class TargetController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => KpiTarget::query()->orderByDesc('effective_from')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'target_call_per_day' => ['required', 'integer', 'min:0'],
            'target_ec_per_day' => ['required', 'integer', 'min:0'],
            'target_sku' => ['required', 'numeric', 'min:0'],
            'target_point_per_day' => ['required', 'integer', 'min:0'],
            'target_min_point_insentif' => ['required', 'integer', 'min:0'],
            'target_value_per_day' => ['required', 'integer', 'min:0'],
            'target_qty_per_day' => ['required', 'integer', 'min:0'],
            'target_omset_per_motoris' => ['required', 'integer', 'min:0'],
        ]);

        $target = KpiTarget::updateOrCreate(['effective_from' => $data['effective_from']], $data);

        return response()->json(['success' => true, 'message' => 'Target disimpan', 'data' => $target]);
    }
}
