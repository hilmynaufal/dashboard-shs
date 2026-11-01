<?php

namespace Tests\Support;

use App\Models\{ItemReview, KkrDocument, KkrVersion, Proposal, ProposalItem, ProposalItemVersion, User};
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class MySqlAccessTestCase extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'shs_new_testing') {
            throw new \RuntimeException('Uji hanya boleh mengosongkan MySQL shs_new_testing.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    protected function organization(): int
    {
        return DB::table('organizations')->insertGetId(['code' => Str::uuid()->toString(), 'name' => 'Organisasi simulasi']);
    }

    protected function actor(string $role, ?int $organization = null): User
    {
        $user = User::factory()->create(['organization_id' => $organization, 'name' => 'Aktor simulasi', 'email' => Str::uuid().'@example.test']);
        $this->grantRole($user, $role);

        return $user;
    }

    protected function grantRole(User $user, string $role): void
    {
        DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => DB::table('roles')->where('code', $role)->value('id')]);
    }

    protected function submission(User $owner): array
    {
        $period = DB::table('submission_periods')->insertGetId(['budget_year' => 2026, 'name' => 'Periode simulasi', 'opens_at' => '2026-01-01', 'closes_at' => '2026-12-31', 'created_by' => $owner->id]);
        $classification = DB::table('item_classifications')->insertGetId(['code' => Str::uuid()->toString(), 'name' => 'Klasifikasi simulasi']);
        $unit = DB::table('units')->insertGetId(['code' => Str::uuid()->toString(), 'name' => 'Unit']);
        $proposalId = DB::table('proposals')->insertGetId(['organization_id' => $owner->organization_id, 'submission_period_id' => $period, 'type' => 'SHS', 'title' => 'Usulan simulasi', 'created_by' => $owner->id, 'status' => 'submitted']);
        $itemId = DB::table('proposal_items')->insertGetId(['proposal_id' => $proposalId, 'public_id' => Str::uuid()->toString(), 'status' => 'assigned']);
        $versionId = DB::table('proposal_item_versions')->insertGetId(['proposal_item_id' => $itemId, 'version_number' => 1, 'item_classification_id' => $classification, 'unit_id' => $unit, 'description' => 'Item simulasi', 'specification' => 'Spesifikasi simulasi', 'proposed_price' => '1000.00', 'reference_snapshot' => '{}', 'created_by' => $owner->id, 'submitted_at' => now()]);

        return ['period_id' => $period, 'proposal' => Proposal::findOrFail($proposalId), 'item' => ProposalItem::findOrFail($itemId), 'version' => ProposalItemVersion::findOrFail($versionId)];
    }

    protected function scope(array $submission, User $examiner, ?User $lead = null, ?User $controller = null): array
    {
        $task = DB::table('review_assignments')->insertGetId(['number' => Str::uuid()->toString(), 'title' => 'Tugas simulasi', 'submission_period_id' => $submission['period_id'], 'object_organization_id' => $submission['proposal']->organization_id, 'created_by' => $examiner->id, 'status' => 'active']);
        foreach (['examiner' => $examiner, 'team_lead' => $lead, 'technical_controller' => $controller] as $role => $user) {
            if ($user) {
                $members[$role] = DB::table('assignment_members')->insertGetId(['review_assignment_id' => $task, 'user_id' => $user->id, 'role' => $role]);
            }
        }
        $scope = DB::table('assignment_items')->insertGetId(['review_assignment_id' => $task, 'proposal_item_id' => $submission['item']->id, 'initial_version_id' => $submission['version']->id, 'examiner_member_id' => $members['examiner']]);
        $reviewId = DB::table('item_reviews')->insertGetId(['assignment_item_id' => $scope, 'proposal_item_id' => $submission['item']->id, 'item_version_id' => $submission['version']->id, 'reviewed_by' => $examiner->id]);

        return ['assignment_id' => $task, 'scope_id' => $scope, 'members' => $members, 'review' => ItemReview::findOrFail($reviewId)];
    }

    protected function kkr(array $scope, User $examiner): array
    {
        $id = DB::table('kkr_documents')->insertGetId(['review_assignment_id' => $scope['assignment_id'], 'prepared_by' => $examiner->id, 'number' => Str::uuid()->toString(), 'title' => 'KKR simulasi', 'status' => 'awaiting_lead']);
        $version = DB::table('kkr_versions')->insertGetId(['kkr_document_id' => $id, 'version_number' => 1, 'template_version' => 'simulasi', 'snapshot' => '{}', 'snapshot_sha256' => hash('sha256', '{}'), 'created_by' => $examiner->id, 'submitted_at' => now()]);

        return ['document' => KkrDocument::findOrFail($id), 'version' => KkrVersion::findOrFail($version)];
    }
}
