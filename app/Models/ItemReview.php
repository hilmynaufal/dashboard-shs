<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemReview extends Model
{
    protected function casts(): array
    {
        return [
            'comparison_price' => 'decimal:2',
            'recommended_price' => 'decimal:2',
        ];
    }
}
