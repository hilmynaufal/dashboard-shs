<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class AccountActivity
{
    public static function record(int $actor, string $action, int $subject, ?array $before = null, ?array $after = null): void
    {
        DB::table('activity_logs')->insert([
            'actor_id' => $actor, 'action' => $action, 'subject_type' => 'user', 'subject_id' => $subject,
            'before_values' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_values' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
        ]);
    }
}
