<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalItemVersion extends Model
{
    protected function casts(): array
    {
        return [
            'reference_snapshot' => 'array',
            'proposed_price' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }
}
