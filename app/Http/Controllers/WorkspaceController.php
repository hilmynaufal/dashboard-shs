<?php

namespace App\Http\Controllers;

use App\Authorization\ResourceAccess;
use App\Authorization\RolePermissions;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Support\ProgressBoard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $roles = DB::table('roles')->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)->orderBy('roles.id')->pluck('roles.code')->all();

        // Ringkasan memakai hitungan Pemantauan, sehingga lingkup aksesnya sama persis.
        $board = null;
        if (array_intersect($user->permissionCodes(), ['monitoring.own', 'monitoring.all', 'monitoring.assigned'])) {
            $board = DB::transaction(fn () => (new ProgressBoard($user, app(ResourceAccess::class)))->page(['view' => 'packages']));
        }
        $now = now();
        $period = SubmissionPeriod::query()->where('status', 'open')->where('opens_at', '<=', $now)
            ->where('closes_at', '>=', $now)->orderBy('closes_at')->first();

        return view('workspace.home', [
            'roles' => array_intersect_key(RolePermissions::ROLES, array_flip($roles)),
            'board' => $board,
            'queue' => $board ? $this->queue($user, $board) : [],
            'period' => $period,
            'periodProgress' => $period ? $this->elapsed($period) : 0,
        ]);
    }

    /** Antrean "perlu tindakan Anda": hanya baris yang memang menjadi tugas peran pengguna. */
    private function queue(User $user, array $board): array
    {
        $items = $board['groups']['items'] ?? [];
        $packages = $board['groups']['packages'] ?? [];
        $count = fn (array $source, string ...$keys): int => array_sum(array_map(fn ($key) => $source[$key] ?? 0, $keys));
        $operator = $user->hasPermission('proposals.view_own') && $user->organization_id !== null;
        $rows = [];
        if ($operator) {
            $rows[] = ['Item perlu dilengkapi', 'Dikembalikan BKAD; lengkapi lalu kirim ulang.', $count($items, 'needs_completion'), 'alert', 'warning', route('progress.index', ['view' => 'items', 'status' => 'needs_completion'])];
            $rows[] = ['Catatan menunggu tanggapan', 'Tanggapi catatan resmi dan kirim revisi bila perlu.', $count($items, 'awaiting_response'), 'message', 'warning', route('notes.index', ['status' => 'sent'])];
            $rows[] = ['Paket masih berupa draf', 'Lengkapi item dan bukti, lalu periksa dan kirim.', $count($packages, 'draft'), 'folder', 'neutral', route('proposals.index', ['status' => 'draft'])];
        }
        if ($user->can('verification.perform') && $user->can('proposals.view_all')) {
            $rows[] = ['Item menunggu verifikasi', 'Periksa kelengkapan versi yang dikirim SKPD.', $count($items, 'submitted'), 'shield', 'blue', route('verification.index')];
        }
        if ($user->hasPermission('proposals.view_assigned')) {
            if ($user->hasRole('team_lead')) {
                $rows[] = ['Item siap ditugaskan', 'Bagikan item siap reviu kepada pemeriksa.', $count($items, 'ready'), 'user-check', 'purple', route('assignments.index')];
            }
            if ($user->hasRole('examiner')) {
                $rows[] = ['Item dalam pemeriksaan Anda', 'Lanjutkan penilaian aspek dan rekomendasi.', $count($items, 'assigned', 'reviewing', 'recheck'), 'clipboard', 'purple', route('assignments.work')];
            }
        }
        if ($user->can('kkr.view') && ($board['canKkr'] ?? false)) {
            $kkr = $board['groups']['kkr'] ?? [];
            $rows[] = ['KKR dalam supervisi', 'Menunggu persetujuan atau perbaikan.', $count($kkr, 'awaiting_lead', 'awaiting_controller', 'returned'), 'file-check', 'purple', route('kkr.index', ['queue' => 'all'])];
        }

        return array_values(array_filter($rows, fn (array $row): bool => $row[2] > 0));
    }

    private function elapsed(SubmissionPeriod $period): int
    {
        $total = max(1, $period->opens_at->diffInSeconds($period->closes_at));

        return (int) max(2, min(100, round($period->opens_at->diffInSeconds(now()) / $total * 100)));
    }
}
