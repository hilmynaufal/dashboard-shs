<?php

namespace Tests\Feature;

use App\Authorization\RolePermissions;
use App\Http\Middleware\RequireActiveSession;
use App\Models\{EvidenceFile, KkrVersion, Proposal, ReviewNote};
use Database\Seeders\AccessControlSeeder;
use Illuminate\Support\Facades\{DB, Gate, Route};
use Illuminate\Support\Str;
use Tests\Support\MySqlAccessTestCase;

class ResourceAuthorizationTest extends MySqlAccessTestCase
{
    public function test_role_matrix_has_expected_sensitive_boundaries(): void
    {
        $expected = [
            'admin' => ['accounts.manage', 'audit.technical'], 'bkad' => ['verification.perform', 'references.manage'],
            'examiner' => ['reviews.write', 'kkr.draft'], 'team_lead' => ['assignments.manage', 'notes.send', 'kkr.approve_lead'],
            'technical_controller' => ['kkr.view', 'kkr.approve_technical'], 'skpd_operator' => ['proposals.create', 'notes.respond_own'],
        ];
        foreach ($expected as $role => $allowed) {
            $user = $this->actor($role);
            foreach ($allowed as $permission) { $this->assertTrue(Gate::forUser($user)->allows($permission), $role.' '.$permission); }
            if ($role !== 'admin') { $this->assertFalse(Gate::forUser($user)->allows('access.manage')); }
            if ($role !== 'team_lead') { $this->assertFalse(Gate::forUser($user)->allows('kkr.approve_lead')); }
            if ($role !== 'technical_controller') { $this->assertFalse(Gate::forUser($user)->allows('kkr.approve_technical')); }
        }
        $this->assertFalse($this->actor('bkad')->hasPermission('kkr.view'));
        $this->assertFalse($this->actor('admin')->hasPermission('kkr.view'));
    }

    public function test_seed_is_idempotent_and_creates_no_accounts(): void
    {
        $before = DB::table('users')->count();
        $grants = DB::table('permission_role')->count();
        $this->seed(AccessControlSeeder::class);
        $this->assertSame(6, DB::table('roles')->count());
        $this->assertSame($grants, DB::table('permission_role')->count());
        $this->assertSame($before, DB::table('users')->count());
    }

    public function test_operator_cannot_read_other_organization_even_through_direct_url(): void
    {
        $owner = $this->actor('skpd_operator', $this->organization());
        $other = $this->actor('skpd_operator', $this->organization());
        $f = $this->submission($owner);
        Route::get('/__test/proposals/{proposal}', function (Proposal $proposal) {
            Gate::authorize('view', $proposal);
            return response()->json(['id' => $proposal->id]);
        })->middleware(['web', RequireActiveSession::class]);
        $this->actingAs($owner)->getJson('/__test/proposals/'.$f['proposal']->id)->assertOk();
        $this->flushSession(); // A different actor represents a separate browser session.
        $this->actingAs($other)->getJson('/__test/proposals/'.$f['proposal']->id)->assertForbidden();
    }

    public function test_bkad_cannot_read_unsubmitted_draft_versions(): void
    {
        $f = $this->submission($this->actor('skpd_operator', $this->organization())); $bkad = $this->actor('bkad');
        $this->assertTrue(Gate::forUser($bkad)->allows('view', $f['version']));
        $f['version']->submitted_at = null;
        $this->assertFalse(Gate::forUser($bkad)->allows('view', $f['version']));
        $f['proposal']->status = 'draft';
        $this->assertFalse(Gate::forUser($bkad)->allows('view', $f['proposal']));
    }

    public function test_assignment_and_current_examiner_limit_access(): void
    {
        $f = $this->submission($this->actor('skpd_operator', $this->organization()));
        $examiner = $this->actor('examiner'); $other = $this->actor('examiner'); $lead = $this->actor('team_lead');
        $scope = $this->scope($f, $examiner, $lead);
        $this->assertTrue(Gate::forUser($examiner)->allows('update', $scope['review']));
        $this->assertFalse(Gate::forUser($other)->allows('view', $f['item']));
        $this->assertTrue(Gate::forUser($lead)->allows('view', $scope['review']));
        $this->assertFalse(Gate::forUser($lead)->allows('update', $scope['review']));
        $newMember = DB::table('assignment_members')->insertGetId(['review_assignment_id' => $scope['assignment_id'], 'user_id' => $other->id, 'role' => 'examiner']);
        DB::table('assignment_items')->where('id', $scope['scope_id'])->update(['examiner_member_id' => $newMember]);
        $this->assertFalse(Gate::forUser($examiner)->allows('update', $scope['review']));
        $this->assertTrue(Gate::forUser($other)->allows('update', $scope['review']));
    }

    public function test_global_role_does_not_override_membership_role(): void
    {
        $f = $this->submission($this->actor('skpd_operator', $this->organization()));
        $examiner = $this->actor('examiner'); $this->grantRole($examiner, 'team_lead');
        $scope = $this->scope($f, $examiner);
        $note = ReviewNote::findOrFail(DB::table('review_notes')->insertGetId(['item_version_id' => $f['version']->id, 'item_review_id' => $scope['review']->id, 'created_by' => $examiner->id, 'type' => 'substantive', 'category' => 'simulasi', 'description' => 'Catatan simulasi']));
        $this->assertFalse(Gate::forUser($examiner)->allows('send', $note));
    }

    public function test_operator_sees_only_official_notes_and_can_respond_only_to_own(): void
    {
        $owner = $this->actor('skpd_operator', $this->organization()); $other = $this->actor('skpd_operator', $this->organization());
        $f = $this->submission($owner); $examiner = $this->actor('examiner'); $lead = $this->actor('team_lead');
        $scope = $this->scope($f, $examiner, $lead);
        $note = ReviewNote::findOrFail(DB::table('review_notes')->insertGetId(['item_version_id' => $f['version']->id, 'item_review_id' => $scope['review']->id, 'created_by' => $examiner->id, 'type' => 'substantive', 'category' => 'simulasi', 'description' => 'Catatan simulasi']));
        $this->assertFalse(Gate::forUser($owner)->allows('view', $note));
        $this->assertFalse(Gate::forUser($examiner)->allows('send', $note));
        $this->assertTrue(Gate::forUser($lead)->allows('send', $note));
        $note->status = 'sent'; $note->save();
        $this->assertTrue(Gate::forUser($owner)->allows('respond', $note));
        $this->assertFalse(Gate::forUser($other)->allows('respond', $note));
        $this->assertFalse(Gate::forUser($this->actor('bkad'))->allows('view', $note));
    }

    public function test_evidence_requires_ownership_or_assigned_submitted_item(): void
    {
        $owner = $this->actor('skpd_operator', $this->organization()); $other = $this->actor('skpd_operator', $this->organization());
        $f = $this->submission($owner); $examiner = $this->actor('examiner'); $this->scope($f, $examiner);
        $file = EvidenceFile::findOrFail(DB::table('evidence_files')->insertGetId(['organization_id' => $owner->organization_id, 'uploaded_by' => $owner->id, 'original_name' => 'simulasi.pdf', 'path' => Str::uuid().'.pdf', 'mime_type' => 'application/pdf', 'byte_size' => 10, 'sha256' => hash('sha256', 'simulasi')]));
        DB::table('item_version_evidence')->insert(['item_version_id' => $f['version']->id, 'evidence_file_id' => $file->id, 'purpose' => 'simulasi']);
        $this->assertTrue(Gate::forUser($owner)->allows('download', $file));
        $this->assertTrue(Gate::forUser($examiner)->allows('download', $file));
        $this->assertFalse(Gate::forUser($other)->allows('download', $file));
        $this->assertFalse(Gate::forUser($this->actor('examiner'))->allows('download', $file));
        DB::table('proposal_item_versions')->where('id', $f['version']->id)->update(['submitted_at' => null]);
        $this->assertFalse(Gate::forUser($examiner)->allows('download', $file));
    }

    public function test_kkr_supervision_requires_exact_team_level_and_same_version_lead_approval(): void
    {
        $f = $this->submission($this->actor('skpd_operator', $this->organization()));
        $examiner = $this->actor('examiner'); $lead = $this->actor('team_lead'); $controller = $this->actor('technical_controller');
        $scope = $this->scope($f, $examiner, $lead, $controller); $kkr = $this->kkr($scope, $examiner);
        $this->assertTrue(Gate::forUser($lead)->allows('approveLead', $kkr['version']));
        $this->assertFalse(Gate::forUser($controller)->allows('approveLead', $kkr['version']));
        $this->assertFalse(Gate::forUser($this->actor('team_lead'))->allows('approveLead', $kkr['version']));
        $this->assertFalse(Gate::forUser($this->actor('bkad'))->allows('view', $kkr['document']));
        $this->assertFalse(Gate::forUser($this->actor('admin'))->allows('view', $kkr['document']));
        $kkr['document']->status = 'awaiting_controller'; $kkr['document']->save();
        $this->assertFalse(Gate::forUser($controller)->allows('approveTechnical', $kkr['version']));
        DB::table('kkr_approvals')->insert(['kkr_version_id' => $kkr['version']->id, 'level' => 'team_lead', 'decision' => 'approved', 'decided_by' => $lead->id, 'decided_at' => now()]);
        $this->assertTrue(Gate::forUser($controller)->allows('approveTechnical', $kkr['version']));
        $new = $kkr['version']->replicate(); $new->version_number = 2; $new->save();
        $this->assertFalse(Gate::forUser($controller)->allows('approveTechnical', $kkr['version']));
        $this->assertFalse(Gate::forUser($controller)->allows('approveTechnical', $new));
    }

    public function test_author_cannot_approve_own_kkr_even_with_both_roles(): void
    {
        $f = $this->submission($this->actor('skpd_operator', $this->organization()));
        $examiner = $this->actor('examiner'); $this->grantRole($examiner, 'team_lead');
        $scope = $this->scope($f, $examiner, $examiner); $kkr = $this->kkr($scope, $examiner);
        $this->assertFalse(Gate::forUser($examiner)->allows('approveLead', $kkr['version']));
    }

    public function test_inactive_assignment_member_cannot_read_or_write_scope(): void
    {
        $f = $this->submission($this->actor('skpd_operator', $this->organization())); $examiner = $this->actor('examiner');
        $scope = $this->scope($f, $examiner);
        DB::table('assignment_members')->where('id', $scope['members']['examiner'])->update(['is_active' => false]);
        $this->assertFalse(Gate::forUser($examiner)->allows('view', $f['item']));
        $this->assertFalse(Gate::forUser($examiner)->allows('update', $scope['review']));
    }

    public function test_draft_version_cannot_be_exposed_through_review(): void
    {
        $f = $this->submission($this->actor('skpd_operator', $this->organization())); $examiner = $this->actor('examiner');
        $scope = $this->scope($f, $examiner);
        DB::table('proposal_item_versions')->where('id', $f['version']->id)->update(['submitted_at' => null]);
        $this->assertFalse(Gate::forUser($examiner)->allows('view', $scope['review']));
        $this->assertFalse(Gate::forUser($examiner)->allows('update', $scope['review']));
    }

    public function test_mislinked_substantive_note_is_denied_even_when_marked_official(): void
    {
        $owner = $this->actor('skpd_operator', $this->organization()); $f = $this->submission($owner);
        $other = $this->submission($this->actor('skpd_operator', $this->organization()));
        $examiner = $this->actor('examiner'); $lead = $this->actor('team_lead'); $scope = $this->scope($f, $examiner, $lead);
        $note = ReviewNote::findOrFail(DB::table('review_notes')->insertGetId(['item_version_id' => $other['version']->id, 'item_review_id' => $scope['review']->id, 'created_by' => $examiner->id, 'type' => 'substantive', 'category' => 'simulasi', 'description' => 'Catatan simulasi', 'status' => 'sent']));
        $this->assertFalse(Gate::forUser($examiner)->allows('view', $note));
        $this->assertFalse(Gate::forUser($lead)->allows('view', $note));
        $otherOwner = \App\Models\User::findOrFail($other['proposal']->created_by);
        $this->assertFalse(Gate::forUser($otherOwner)->allows('view', $note));
    }

    public function test_operator_without_organization_cannot_create_or_view_packages(): void
    {
        $user = $this->actor('skpd_operator');
        $f = $this->submission($this->actor('skpd_operator', $this->organization()));
        $this->assertFalse(Gate::forUser($user)->allows('create', Proposal::class));
        $this->assertFalse(Gate::forUser($user)->allows('view', $f['proposal']));
    }

    public function test_list_scope_does_not_expose_other_items_inside_same_package(): void
    {
        $owner = $this->actor('skpd_operator', $this->organization()); $f = $this->submission($owner);
        $examiner = $this->actor('examiner'); $this->scope($f, $examiner);
        $otherId = DB::table('proposal_items')->insertGetId(['proposal_id' => $f['proposal']->id, 'public_id' => Str::uuid()->toString()]);
        $other = $this->submission($this->actor('skpd_operator', $this->organization()));
        $access = app(\App\Authorization\ResourceAccess::class);
        $this->assertSame([$f['item']->id], $access->visibleItemIds($examiner)->pluck('item.id')->all());
        $this->assertEqualsCanonicalizing([$f['item']->id, $otherId], $access->visibleItemIds($owner)->pluck('item.id')->all());
        $this->assertSame([], $access->visibleItemIds($this->actor('admin'))->pluck('item.id')->all());
    }

    public function test_team_lead_cannot_manage_another_teams_assignment(): void
    {
        $f = $this->submission($this->actor('skpd_operator', $this->organization()));
        $examiner = $this->actor('examiner'); $lead = $this->actor('team_lead');
        $scope = $this->scope($f, $examiner, $lead);
        $assignment = \App\Models\ReviewAssignment::findOrFail($scope['assignment_id']);
        $this->assertTrue(Gate::forUser($lead)->allows('manage', $assignment));
        $this->assertFalse(Gate::forUser($this->actor('team_lead'))->allows('manage', $assignment));
        $this->assertFalse(Gate::forUser($examiner)->allows('manage', $assignment));
    }
}
