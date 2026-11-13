<?php

namespace App\Support;

/**
 * Satu peta nada warna untuk seluruh badge status, agar status yang sama
 * tampil sama di setiap layar. Label teks tetap wajib menyertai warna.
 */
final class StatusTone
{
    private const TONES = [
        'draft' => 'neutral', 'internal' => 'neutral', 'inactive' => 'neutral', 'superseded' => 'neutral', 'closed' => 'neutral',
        'submitted' => 'blue', 'verification' => 'blue', 'active' => 'blue', 'in_progress' => 'blue',
        'assigned' => 'purple', 'reviewing' => 'purple', 'review' => 'purple', 'awaiting_lead' => 'purple', 'awaiting_controller' => 'purple',
        'needs_completion' => 'warning', 'awaiting_response' => 'warning', 'recheck' => 'warning', 'sent' => 'warning',
        'responded' => 'warning', 'returned' => 'warning', 'open_at_final' => 'warning',
        'ready' => 'green', 'reviewed' => 'green', 'completed' => 'green', 'resolved' => 'green', 'final' => 'green', 'open' => 'green',
    ];

    public static function of(?string $status): string
    {
        return self::TONES[$status ?? ''] ?? 'neutral';
    }
}
