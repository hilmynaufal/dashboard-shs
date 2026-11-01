<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class CatalogAudit
{
    public static function record(int $actor, string $action, string $type, int $id, ?array $before, ?array $after): void
    {
        DB::table('activity_logs')->insert([
            'actor_id' => $actor, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id,
            'before_values' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_values' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR), 'occurred_at' => now(),
        ]);
    }
}
