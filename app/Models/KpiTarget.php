<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiTarget extends Model
{
    protected $fillable = [
        'effective_from',
        'target_call_per_day',
        'target_ec_per_day',
        'target_sku',
        'target_point_per_day',
        'target_min_point_insentif',
        'target_value_per_day',
        'target_qty_per_day',
        'target_omset_per_motoris',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date:Y-m-d',
            'target_sku' => 'float',
        ];
    }
}
