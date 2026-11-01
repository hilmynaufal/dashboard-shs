<?php

namespace Tests\Feature;

use App\Authorization\AccountAccess;
use App\Models\User;
use Illuminate\Support\Facades\{DB, Gate};
use Tests\Support\MySqlAccessTestCase;

class AccountAccessManagementTest extends MySqlAccessTestCase
{
    private function data(User $user, array $roles, ?int $organization = null): array
    {
        $user->refresh();

        return ['roles' => $roles, 'organization_id' => $organization,
            'access_version' => AccountAccess::version($user, AccountAccess::snapshot($user))];
    }

    private function url(User $user): string
    {
        return '/admin/akun/'.$user->id.'/akses';
    }

    public function test_admin_changes_roles_and_scope_atomically_and_records_before_and_after(): void
    {
        $admin = $this->actor('admin'); $user = $this->actor('examiner'); $org = $this->organization();
        $this->actingAs($admin)->get($this->url($user))->assertOk()->assertSee('Simpan hak akses')->assertDontSee($user->password);
        $this->patchJson($this->url($user), $this->data($user, ['team_lead', 'technical_controller'], $org))
            ->assertOk()->assertJsonPath('access.roles', ['team_lead', 'technical_controller'])->assertJsonPath('access.organization_id', $org);
        $this->assertFalse($user->fresh()->hasRole('examiner'));
        $this->assertTrue($user->fresh()->hasRole('team_lead'));
        $this->assertTrue($user->fresh()->hasRole('technical_controller'));
        $log = DB::table('activity_logs')->where('action', 'account.access_updated')->first();
        $this->assertSame(['examiner'], json_decode($log->before_values, true)['roles']);
        $this->assertSame($org, json_decode($log->after_values, true)['organization_id']);
        $this->assertStringNotContainsString($user->password, json_encode($log));
    }

    public function test_non_admin_roles_cannot_change_access_through_direct_urls_or_forged_payloads(): void
    {
        $target = $this->actor('examiner');
        foreach (['bkad', 'examiner', 'team_lead', 'technical_controller', 'skpd_operator'] as $role) {
            $this->flushSession();
            $user = $this->actor($role, $role === 'skpd_operator' ? $this->organization() : null);
            $this->actingAs($user)->get($this->url($target))->assertForbidden()->assertSee('Akses dibatasi');
            $this->patchJson($this->url($target), $this->data($target, ['admin']))->assertForbidden();
        }
        $this->assertTrue($target->fresh()->hasRole('examiner'));
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_current_session_reads_new_permissions_after_admin_changes_another_users_access(): void
    {
        $admin = $this->actor('admin'); $user = $this->actor('examiner');
        $this->actingAs($user)->getJson('/auth/me')->assertJsonFragment(['reviews.write']);
        $oldSession = session()->all();
        $this->flushSession();
        $this->actingAs($admin)->patchJson($this->url($user), $this->data($user, ['bkad']))->assertOk();
        $this->flushSession();
        $this->actingAs($user->fresh())->withSession($oldSession)->getJson('/auth/me')->assertOk()
            ->assertJsonFragment(['verification.perform'])->assertJsonMissing(['reviews.write']);
        $this->get('/aplikasi')->assertOk()->assertSee('BKAD')->assertDontSee('>Pemeriksa<', false);
    }

    public function test_changing_skpd_changes_policy_scope_without_moving_existing_proposals(): void
    {
        $admin = $this->actor('admin'); $firstOrg = $this->organization(); $secondOrg = $this->organization();
        $user = $this->actor('skpd_operator', $firstOrg); $other = $this->actor('skpd_operator', $secondOrg);
        $first = $this->submission($user); $second = $this->submission($other);
        $this->assertTrue(Gate::forUser($user)->allows('view', $first['proposal']));
        $this->actingAs($admin)->patchJson($this->url($user), $this->data($user, ['skpd_operator'], $secondOrg))->assertOk();
        $this->assertFalse(Gate::forUser($user->fresh())->allows('view', $first['proposal']));
        $this->assertTrue(Gate::forUser($user->fresh())->allows('view', $second['proposal']));
        $this->assertDatabaseHas('proposals', ['id' => $first['proposal']->id, 'organization_id' => $firstOrg]);
    }

    public function test_revoking_review_role_removes_policy_write_access_despite_existing_assignment(): void
    {
        $admin = $this->actor('admin'); $owner = $this->actor('skpd_operator', $this->organization());
        $user = $this->actor('examiner'); $submission = $this->submission($owner); $scope = $this->scope($submission, $user);
        $this->assertTrue(Gate::forUser($user)->allows('update', $scope['review']));
        $this->actingAs($admin)->patchJson($this->url($user), $this->data($user, ['bkad']))->assertOk();
        $this->assertFalse(Gate::forUser($user->fresh())->allows('update', $scope['review']));
        $this->assertDatabaseHas('assignment_members', ['id' => $scope['members']['examiner'], 'user_id' => $user->id]);
    }

    public function test_stale_forms_cannot_overwrite_access_or_account_status_changes(): void
    {
        $admin = $this->actor('admin'); $user = $this->actor('examiner');
        $stale = $this->data($user, ['technical_controller']);
        $this->actingAs($admin)->patchJson($this->url($user), $this->data($user, ['team_lead']))->assertOk();
        $this->patchJson($this->url($user), $stale)->assertStatus(409)->assertJsonValidationErrors('access_version');
        $this->assertTrue($user->fresh()->hasRole('team_lead'));
        $current = $this->data($user, ['examiner']);
        $this->patchJson('/admin/akun/'.$user->id.'/status', ['is_active' => false])->assertOk();
        $this->patchJson($this->url($user), $current)->assertStatus(409);
        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseCount('activity_logs', 2);
    }

    public function test_admin_cannot_revoke_own_admin_role_but_another_admin_can_change_it(): void
    {
        $first = $this->actor('admin'); $second = $this->actor('admin');
        $this->actingAs($first)->patchJson($this->url($first), $this->data($first, ['examiner']))->assertUnprocessable()->assertJsonValidationErrors('roles');
        $this->assertTrue($first->fresh()->hasRole('admin'));
        $this->patchJson($this->url($second), $this->data($second, ['examiner']))->assertOk();
        $this->assertFalse($second->fresh()->hasRole('admin'));
        $this->assertTrue($first->fresh()->hasRole('admin'));
    }

    public function test_validation_rejects_invalid_roles_mixed_operator_scope_and_missing_or_inactive_skpd(): void
    {
        $admin = $this->actor('admin'); $user = $this->actor('examiner');
        $this->actingAs($admin);
        foreach ([[], ['unknown'], ['examiner', 'examiner'], ['skpd_operator']] as $roles) {
            $this->patchJson($this->url($user), $this->data($user, $roles))->assertUnprocessable();
        }
        $org = $this->organization();
        $this->patchJson($this->url($user), $this->data($user, ['skpd_operator', 'bkad'], $org))->assertUnprocessable()->assertJsonValidationErrors('roles');
        DB::table('organizations')->where('id', $org)->update(['is_active' => false]);
        $this->patchJson($this->url($user), $this->data($user, ['skpd_operator'], $org))->assertUnprocessable()->assertJsonValidationErrors('organization_id');
        $this->assertTrue($user->fresh()->hasRole('examiner'));
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_malformed_html_submission_renders_validation_without_an_error_page(): void
    {
        $admin = $this->actor('admin'); $user = $this->actor('examiner');
        $this->actingAs($admin)->from($this->url($user))->patch($this->url($user), ['roles' => 'admin', 'access_version' => 'invalid'])
            ->assertRedirect($this->url($user))->assertSessionHasErrors(['roles', 'access_version']);
        $this->assertTrue(session('errors')->getBag('default')->has('roles'), 'Validation bag should contain roles before render.');
        // Carry the response session into the redirect target, as a browser would.
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get($this->url($user))->assertOk();
        $this->assertTrue(view()->getShared()['errors']->getBag('default')->has('roles'), 'View must receive validation bag.');
        $this->assertTrue(str_contains($response->getContent(), 'Periksa kembali isian Anda.'), 'Validation summary must be visible.');
    }

    public function test_access_form_keeps_existing_inactive_organization_visible_and_preserves_unchanged_roles(): void
    {
        $admin = $this->actor('admin'); $org = $this->organization(); $user = $this->actor('examiner', $org);
        DB::table('organizations')->where('id', $org)->update(['is_active' => false]);
        $this->actingAs($admin)->get($this->url($user))->assertOk()->assertSee('nonaktif — pilih SKPD aktif');
        $this->patchJson($this->url($user), $this->data($user, ['examiner'], $org))->assertUnprocessable();
        $this->assertSame($org, $user->fresh()->organization_id);
        $this->patchJson($this->url($user), $this->data($user, ['examiner']))->assertOk();
        $this->assertNull($user->fresh()->organization_id);
        $this->patchJson($this->url($user), $this->data($user, ['examiner']))->assertOk();
        $this->assertDatabaseCount('activity_logs', 1);
    }

    public function test_unchecking_all_roles_keeps_choices_empty_after_validation_redirect(): void
    {
        $admin = $this->actor('admin'); $user = $this->actor('examiner');
        $data = $this->data($user, ['examiner']); unset($data['roles']);
        $this->actingAs($admin)->from($this->url($user))->patch($this->url($user), $data)
            ->assertRedirect($this->url($user))->assertSessionHasErrors('roles');
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get($this->url($user))->assertOk();
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//input[@name="roles[]"][@checked]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="roles-error"]')->length);
        $this->assertTrue($user->fresh()->hasRole('examiner'), 'Rejected selection must not change saved roles.');
    }

    public function test_invalid_role_array_entry_has_a_message_associated_with_all_role_choices(): void
    {
        $admin = $this->actor('admin'); $user = $this->actor('examiner');
        $this->actingAs($admin)->from($this->url($user))->patch($this->url($user), $this->data($user, ['unknown']))
            ->assertRedirect($this->url($user))->assertSessionHasErrors('roles.0');
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get($this->url($user))->assertOk();
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//*[@id="roles-error"]')->length);
        $this->assertSame(0, $xpath->query('//input[@name="roles[]"][not(@aria-invalid="true") or not(contains(@aria-describedby,"roles-error"))]')->length);
        $this->assertSame(1, $xpath->query('//fieldset[@aria-invalid="true"][contains(@aria-describedby,"roles-error")]')->length);
    }

    public function test_access_mutation_does_not_accept_other_account_fields_and_requires_csrf(): void
    {
        $admin = $this->actor('admin'); $user = $this->actor('examiner');
        $this->actingAs($admin)->patchJson($this->url($user), $this->data($user, ['bkad']) + ['is_active' => false, 'permissions' => ['kkr.view']])
            ->assertUnprocessable()->assertJsonValidationErrors(['is_active', 'permissions']);
        $this->assertTrue($user->fresh()->is_active);
        $this->app->instance('env', 'production');
        $this->patch($this->url($user), $this->data($user, ['bkad']))->assertStatus(419);
    }
}
