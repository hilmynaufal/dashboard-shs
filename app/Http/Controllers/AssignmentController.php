<?php

namespace App\Http\Controllers;

use App\Authorization\ResourceAccess;
use App\Models\{Proposal, ProposalItem, ReviewAssignment, User};
use App\Support\{AccountValidation, AssignmentTeam, AssignmentWork, CatalogAudit, ReferenceCatalog};
use Illuminate\Database\QueryException;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

class AssignmentController extends Controller
{
    private const STATUS = ['active' => 'Aktif', 'completed' => 'Selesai', 'draft' => 'Draf'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $roles = array_values(array_filter(['examiner', 'team_lead', 'technical_controller'], fn ($role) => $user->hasRole($role)));
        abort_unless($roles && $user->hasPermission('proposals.view_assigned'), 403);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'sort' => ['nullable', Rule::in(['newest', 'number'])]], AccountValidation::MESSAGES);
        $assignments = ReviewAssignment::whereIn('id', DB::table('assignment_members')->select('review_assignment_id')
            ->where('user_id', $user->id)->where('is_active', true)->whereIn('role', $roles))
            ->when(($data['q'] ?? null) !== null, fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', ReferenceCatalog::searchPattern($data['q']))->orWhere('title', 'like', ReferenceCatalog::searchPattern($data['q']))))
            ->orderBy(($data['sort'] ?? 'newest') === 'number' ? 'number' : 'id', ($data['sort'] ?? 'newest') === 'number' ? 'asc' : 'desc')
            ->orderBy('id')->paginate(15)->withQueryString();

        return view('assignments.index', ['assignments' => $assignments, 'statuses' => self::STATUS]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', ReviewAssignment::class);

        return view('assignments.create', [
            'periods' => DB::table('submission_periods')->orderByDesc('budget_year')->orderBy('id')->get(),
            'organizations' => DB::table('organizations')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'examiners' => AssignmentTeam::candidates('examiner')->whereKeyNot($request->user()->id)->get(['id', 'name']),
            'controllers' => AssignmentTeam::candidates('technical_controller')->whereKeyNot($request->user()->id)->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', ReviewAssignment::class);
        $data = $request->validate([
            'number' => ['required', 'string', 'max:100', 'unique:review_assignments,number'],
            'title' => ['required', 'string', 'max:255'], 'submission_period_id' => ['required', 'integer', 'exists:submission_periods,id'],
            'object_organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'task_basis' => ['nullable', 'string', 'max:10000'], 'scope_description' => ['nullable', 'string', 'max:10000'],
            'examiners' => ['required', 'array', 'min:1', 'max:200'], 'examiners.*' => ['required', 'integer', 'distinct'],
            'technical_controller_id' => ['nullable', 'integer'], 'created_by' => ['prohibited'], 'status' => ['prohibited'],
        ], AccountValidation::MESSAGES, ['number' => 'Nomor penugasan', 'title' => 'Judul', 'examiners' => 'Pemeriksa', 'submission_period_id' => 'Periode', 'object_organization_id' => 'SKPD objek']);
        try {
            $assignment = DB::transaction(function () use ($request, $data): ReviewAssignment {
                $participants = array_map('intval', array_merge([$request->user()->id], $data['examiners'], isset($data['technical_controller_id']) ? [$data['technical_controller_id']] : []));
                if (count($participants) !== count(array_unique($participants))) {
                    throw ValidationException::withMessages(['examiners' => 'Ketua Tim, pemeriksa, dan Pengendali Teknis harus orang yang berbeda.']);
                }
                $users = User::whereIn('id', $participants)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                Gate::forUser($request->user()->refresh())->authorize('create', ReviewAssignment::class);
                foreach ($data['examiners'] as $id) {
                    $user = $users[$id] ?? null;
                    if (!$user || !$user->hasRole('examiner') || !$user->hasPermission('proposals.view_assigned')) {
                        throw ValidationException::withMessages(['examiners' => 'Pilih akun Pemeriksa aktif dengan akses penugasan.']);
                    }
                }
                $controller = isset($data['technical_controller_id']) ? ($users[$data['technical_controller_id']] ?? null) : null;
                if (isset($data['technical_controller_id']) && (!$controller || !$controller->hasRole('technical_controller') || !$controller->hasPermission('proposals.view_assigned'))) {
                    throw ValidationException::withMessages(['technical_controller_id' => 'Pilih akun Pengendali Teknis aktif.']);
                }
                if (!DB::table('organizations')->where('id', $data['object_organization_id'])->where('is_active', true)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['object_organization_id' => 'SKPD objek tidak aktif.']);
                }
                $assignment = new ReviewAssignment;
                foreach (['number', 'title', 'submission_period_id', 'object_organization_id', 'task_basis', 'scope_description'] as $key) { $assignment->$key = $data[$key] ?? null; }
                $assignment->created_by = $request->user()->id; $assignment->status = 'active'; $assignment->save();
                AssignmentTeam::add($assignment, $request->user(), 'team_lead');
                foreach ($data['examiners'] as $id) { AssignmentTeam::add($assignment, $users[$id], 'examiner'); }
                if ($controller) { AssignmentTeam::add($assignment, $controller, 'technical_controller'); }
                CatalogAudit::record($request->user()->id, 'assignment.created', 'review_assignment', $assignment->id, null, ['member_user_ids' => $participants]);

                return $assignment;
            }, 3);
        } catch (QueryException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) { throw ValidationException::withMessages(['number' => 'Nomor penugasan sudah digunakan.']); }
            throw $error;
        }

        return $request->expectsJson() ? response()->json(['id' => $assignment->id, 'message' => 'Penugasan dan tim tersimpan.'], 201)
            : redirect()->route('assignments.show', $assignment)->with('status', 'Penugasan dan tim tersimpan. Pilih item siap reviu untuk dibagikan.');
    }

    public function show(Request $request, ReviewAssignment $assignment): View
    {
        Gate::authorize('view', $assignment);
        $data = $this->filters($request);
        $items = $this->filtered(AssignmentWork::items($request->user(), $assignment), $data)->paginate(25)->withQueryString();
        $ids = $items->pluck('id');
        $history = DB::table('assignment_transfers as transfer')->join('assignment_members as previous', 'previous.id', '=', 'transfer.from_member_id')
            ->join('users as from_user', 'from_user.id', '=', 'previous.user_id')->join('assignment_members as next', 'next.id', '=', 'transfer.to_member_id')
            ->join('users as to_user', 'to_user.id', '=', 'next.user_id')->join('users as actor', 'actor.id', '=', 'transfer.transferred_by')
            ->whereIn('transfer.assignment_item_id', $ids)->orderByDesc('transfer.id')
            ->select('transfer.*', 'from_user.name as from_name', 'to_user.name as to_name', 'actor.name as actor_name')->get()->groupBy('assignment_item_id');

        return view('assignments.show', [
            'assignment' => $assignment, 'items' => $items, 'history' => $history,
            'recordVersion' => Gate::allows('manage', $assignment) ? AssignmentWork::token($assignment) : null,
            'organization' => DB::table('organizations')->where('id', $assignment->object_organization_id)->value('name'),
            'examiners' => Gate::allows('manage', $assignment) ? $this->team($assignment) : collect(),
            'statuses' => self::STATUS,
        ]);
    }

    public function candidates(Request $request, ReviewAssignment $assignment): View
    {
        Gate::authorize('manage', $assignment); abort_unless($assignment->status === 'active', 403);
        $data = $this->filters($request);
        $query = AssignmentWork::candidates($assignment)->select(['item.id', 'proposal.number as proposal_number', 'proposal.type', 'version.description', 'version.version_number']);
        if (($data['q'] ?? null) !== null) {
            $term = ReferenceCatalog::searchPattern($data['q']);
            $query->where(fn ($q) => $q->where('version.description', 'like', $term)->orWhere('proposal.number', 'like', $term));
        }
        $query->orderBy(($data['sort'] ?? 'newest') === 'name' ? 'version.description' : 'item.id', ($data['sort'] ?? 'newest') === 'name' ? 'asc' : 'desc')->orderBy('item.id');

        return view('assignments.candidates', ['assignment' => $assignment, 'items' => $query->paginate(25)->withQueryString(),
            'examiners' => $this->team($assignment), 'recordVersion' => AssignmentWork::token($assignment)]);
    }

    public function allocate(Request $request, ReviewAssignment $assignment): JsonResponse|RedirectResponse
    {
        Gate::authorize('manage', $assignment);
        $raw = $request->input('items');
        $selection = is_array($raw) ? array_filter($raw, fn ($value) => $value !== null && $value !== '') : $raw;
        $data = validator(['items' => $selection, 'record_version' => $request->input('record_version')], [
            'items' => ['required', 'array', 'min:1', 'max:50'], 'items.*' => ['required', 'integer', 'min:1'],
            'record_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
        ], AccountValidation::MESSAGES, ['items' => 'Pilihan item'])->validate();
        foreach (array_keys($data['items']) as $id) {
            if (!preg_match('/\A[1-9][0-9]{0,18}\z/', (string)$id)) { throw ValidationException::withMessages(['items' => 'Pilihan harus menggunakan ID item yang ditampilkan.']); }
        }
        DB::transaction(function () use ($request, $assignment, $data): void {
            $locked = AssignmentWork::lock($request->user(), $assignment->id, $data['record_version']);
            AssignmentWork::allocate($request->user(), $locked, $data['items']);
        }, 3);

        return $request->expectsJson() ? response()->json(['message' => 'Item ditugaskan kepada pemeriksa yang dipilih.'])
            : redirect()->route('assignments.show', $assignment)->with('status', 'Item ditugaskan kepada pemeriksa yang dipilih.');
    }

    public function transfer(Request $request, ReviewAssignment $assignment, int $scope): JsonResponse|RedirectResponse
    {
        Gate::authorize('manage', $assignment);
        $data = $request->validate(['examiner_member_id' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:5000'],
            'record_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']], AccountValidation::MESSAGES, ['reason' => 'Alasan pengalihan', 'examiner_member_id' => 'Pemeriksa tujuan']);
        DB::transaction(function () use ($request, $assignment, $scope, $data): void {
            $locked = AssignmentWork::lock($request->user(), $assignment->id, $data['record_version']);
            $initial = DB::table('assignment_items')->where('review_assignment_id', $locked->id)->where('id', $scope)->first(); abort_unless($initial, 404);
            $item = ProposalItem::findOrFail($initial->proposal_item_id);
            Proposal::query()->whereKey($item->proposal_id)->lockForUpdate()->firstOrFail();
            $item = ProposalItem::query()->lockForUpdate()->findOrFail($item->id);
            $record = DB::table('assignment_items')->where('id', $scope)->lockForUpdate()->first();
            if (!in_array($item->status, ['assigned', 'reviewing', 'awaiting_response', 'recheck'], true) ||
                DB::table('item_reviews')->where('assignment_item_id', $scope)->where('status', 'completed')->exists()) {
                throw ValidationException::withMessages(['examiner_member_id' => 'Item yang sudah selesai atau tidak dalam penugasan tidak dapat dialihkan.']);
            }
            $target = AssignmentTeam::examiner($locked, $data['examiner_member_id']);
            if ((int)$target->id === (int)$record->examiner_member_id) { throw ValidationException::withMessages(['examiner_member_id' => 'Pilih pemeriksa yang berbeda dari pemeriksa saat ini.']); }
            DB::table('assignment_items')->where('id', $scope)->update(['examiner_member_id' => $target->id, 'updated_at' => now()]);
            $id = DB::table('assignment_transfers')->insertGetId(['assignment_item_id' => $scope, 'from_member_id' => $record->examiner_member_id,
                'to_member_id' => $target->id, 'transferred_by' => $request->user()->id, 'reason' => $data['reason'],
                'transferred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $locked->touch();
            CatalogAudit::record($request->user()->id, 'assignment.transferred', 'assignment_transfer', $id, null,
                ['scope_id' => $scope, 'from_member_id' => $record->examiner_member_id, 'to_member_id' => $target->id]);
        }, 3);

        return $request->expectsJson() ? response()->json(['message' => 'Pemeriksa utama dialihkan dan riwayat tersimpan.'])
            : redirect()->route('assignments.show', $assignment)->with('status', 'Pemeriksa utama dialihkan dan riwayat tersimpan.');
    }

    public function transferForm(Request $request, ReviewAssignment $assignment, int $scope): View
    {
        Gate::authorize('manage', $assignment); abort_unless($assignment->status === 'active', 403);
        $item = AssignmentWork::items($request->user(), $assignment)->where('scope.id', $scope)->first(); abort_unless($item, 404);
        abort_unless(in_array($item->item_status, ['assigned', 'reviewing', 'awaiting_response', 'recheck'], true) && !$item->completed_reviews, 403);

        return view('assignments.transfer', ['assignment' => $assignment, 'item' => $item,
            'examiners' => $this->team($assignment), 'recordVersion' => AssignmentWork::token($assignment)]);
    }

    public function work(Request $request): View
    {
        abort_unless($request->user()->hasRole('examiner') && $request->user()->hasPermission('proposals.view_assigned'), 403);
        $data = $this->filters($request);
        $items = $this->filtered(AssignmentWork::examinerItems($request->user()), $data)->paginate(25)->withQueryString();

        return view('assignments.work', compact('items'));
    }

    private function filters(Request $request): array
    {
        return $request->validate(['q' => ['nullable', 'string', 'max:100'], 'sort' => ['nullable', Rule::in(['newest', 'name', 'assignment'])]], AccountValidation::MESSAGES);
    }

    private function filtered($query, array $data)
    {
        if (($data['q'] ?? null) !== null) {
            $term = ReferenceCatalog::searchPattern($data['q']);
            $query->where(fn ($q) => $q->where('version.description', 'like', $term)->orWhere('assignment.number', 'like', $term)->orWhere('proposal.number', 'like', $term));
        }
        $sort = $data['sort'] ?? 'newest';

        return $query->orderBy(['newest' => 'scope.id', 'name' => 'version.description', 'assignment' => 'assignment.number'][$sort], $sort === 'newest' ? 'desc' : 'asc')->orderBy('scope.id');
    }

    private function team(ReviewAssignment $assignment)
    {
        return DB::table('assignment_members as member')->join('users as user', 'user.id', '=', 'member.user_id')
            ->where('member.review_assignment_id', $assignment->id)->where('member.role', 'examiner')->where('member.is_active', true)
            ->whereIn('user.id', AssignmentTeam::candidates('examiner')->select('users.id'))
            ->orderBy('user.name')->orderBy('member.id')->get(['member.id', 'user.name']);
    }
}
