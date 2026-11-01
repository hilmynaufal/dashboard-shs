<?php

namespace Tests\Feature;

use App\Authorization\RolePermissions;
use App\Models\User;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Tests\Support\MySqlAccessTestCase;

class AccountManagementTest extends MySqlAccessTestCase
{
    private function password(): string
    {
        return Str::random(36).'aA9';
    }

    public function test_each_role_can_log_in_to_a_role_scoped_workspace_and_log_out(): void
    {
        foreach (RolePermissions::ROLES as $code => $name) {
            $actor = $this->actor($code, $code === 'skpd_operator' ? $this->organization() : null);
            $password = $this->password();
            $actor->password = $password; $actor->save();
            $this->post('/auth/login', ['email' => $actor->email, 'password' => $password])->assertRedirect('/aplikasi');
            $home = $this->get('/aplikasi')->assertOk()->assertSee($name)->assertHeader('Cache-Control', 'no-store, private');
            if ($code !== 'admin') { $home->assertDontSee('href="'.route('accounts.index').'"', false); }
            $this->post('/auth/logout')->assertRedirect('/masuk');
            $this->get('/aplikasi')->assertRedirect('/masuk');
        }
    }

    public function test_protected_pages_redirect_guests_and_reject_non_admins_even_on_direct_urls(): void
    {
        foreach (['/aplikasi', '/profil', '/admin/akun', '/admin/akun/buat'] as $url) { $this->get($url)->assertRedirect('/masuk'); }
        foreach (array_diff(array_keys(RolePermissions::ROLES), ['admin']) as $role) {
            $this->flushSession();
            $user = $this->actor($role, $role === 'skpd_operator' ? $this->organization() : null);
            $this->actingAs($user)->get('/admin/akun')->assertForbidden();
            $this->get('/admin/akun/buat')->assertForbidden();
            $this->postJson('/admin/akun', [])->assertForbidden();
            $this->patchJson('/admin/akun/'.$user->id.'/status', ['is_active' => false])->assertForbidden();
        }
    }

    public function test_admin_can_create_an_account_with_a_hashed_password_and_initial_scope(): void
    {
        $admin = $this->actor('admin'); $org = $this->organization(); $password = $this->password();
        $data = ['name' => 'Pengguna simulasi', 'email' => Str::uuid().'@example.test', 'role' => 'skpd_operator', 'organization_id' => $org, 'password' => $password, 'password_confirmation' => $password];
        $response = $this->actingAs($admin)->postJson('/admin/akun', $data)->assertCreated()->assertJsonMissingPath('user.password');
        $created = User::findOrFail($response->json('user.id'));
        $this->assertTrue(Hash::check($password, $created->password));
        $this->assertTrue($created->hasRole('skpd_operator'));
        $this->assertSame($org, $created->organization_id);
        $this->assertTrue($created->is_active);
        $this->postJson('/admin/akun', $data)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('activity_logs', 1);
        $log = DB::table('activity_logs')->first();
        $this->assertStringNotContainsString($password, json_encode($log));
        $this->assertStringNotContainsString($created->password, json_encode($log));
    }

    public function test_creation_rejects_missing_or_disabled_skpd_and_invalid_roles(): void
    {
        $admin = $this->actor('admin'); $password = $this->password();
        $data = ['name' => 'Akun simulasi', 'email' => Str::uuid().'@example.test', 'role' => 'skpd_operator', 'password' => $password, 'password_confirmation' => $password];
        $this->actingAs($admin)->postJson('/admin/akun', $data)->assertUnprocessable()->assertJsonValidationErrors('organization_id');
        $org = $this->organization(); DB::table('organizations')->where('id', $org)->update(['is_active' => false]);
        $this->postJson('/admin/akun', $data + ['organization_id' => $org])->assertUnprocessable()->assertJsonValidationErrors('organization_id');
        $this->postJson('/admin/akun', array_replace($data, ['role' => 'unrecognized']))->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_profile_changes_persist_but_cannot_escalate_access(): void
    {
        $user = $this->actor('skpd_operator', $this->organization());
        $this->actingAs($user)->patchJson('/profil', ['name' => 'Nama simulasi baru', 'position' => 'Jabatan simulasi'])->assertOk();
        $this->get('/profil')->assertOk()->assertSee('Nama simulasi baru')->assertSee('Jabatan simulasi');
        foreach (['role' => 'admin', 'organization_id' => $this->organization(), 'is_active' => false, 'email' => Str::uuid().'@example.test'] as $key => $value) {
            $this->patchJson('/profil', ['name' => 'Nama simulasi baru', $key => $value])->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        $this->assertFalse($user->fresh()->hasRole('admin'));
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_password_requires_current_secret_confirmation_and_strength_then_old_secret_stops_working(): void
    {
        $user = $this->actor('examiner'); $old = $this->password(); $new = $this->password();
        $user->password = $old; $user->save();
        $this->actingAs($user)->putJson('/profil/kata-sandi', ['current_password' => $this->password(), 'password' => $new, 'password_confirmation' => $new])->assertUnprocessable();
        $this->putJson('/profil/kata-sandi', ['current_password' => $old, 'password' => $new, 'password_confirmation' => $this->password()])->assertUnprocessable();
        $this->putJson('/profil/kata-sandi', ['current_password' => $old, 'password' => 'x', 'password_confirmation' => 'x'])->assertUnprocessable();
        $long = str_repeat('é', 37).'aA9'; // Fewer than 72 characters, but exceeds bcrypt's byte limit.
        $this->putJson('/profil/kata-sandi', ['current_password' => $old, 'password' => $long, 'password_confirmation' => $long])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->putJson('/profil/kata-sandi', ['current_password' => $old, 'password' => $new, 'password_confirmation' => $new])->assertOk();
        $this->getJson('/auth/me')->assertOk();
        $this->postJson('/auth/logout')->assertOk();
        $this->postJson('/auth/login', ['email' => $user->email, 'password' => $old])->assertUnauthorized();
        $this->postJson('/auth/login', ['email' => $user->email, 'password' => $new])->assertOk();
        $log = DB::table('activity_logs')->where('action', 'account.password_changed')->first();
        $this->assertNull($log->after_values); $this->assertNull($log->before_values);
    }

    public function test_password_change_invalidates_an_existing_stale_session(): void
    {
        $user = $this->actor('examiner');
        $this->actingAs($user)->get('/profil')->assertOk();
        $user->password = $this->password(); $user->save();
        $this->get('/profil')->assertRedirect('/masuk');
        $this->assertGuest();
    }

    public function test_admin_can_toggle_status_and_disabled_users_lose_access(): void
    {
        $admin = $this->actor('admin'); $user = $this->actor('examiner');
        $this->actingAs($admin)->patchJson('/admin/akun/'.$user->id.'/status', ['is_active' => false])->assertOk();
        $this->assertFalse($user->fresh()->is_active);
        $this->actingAs($user)->get('/profil')->assertRedirect('/masuk');
        $this->actingAs($admin)->patchJson('/admin/akun/'.$user->id.'/status', ['is_active' => true])->assertOk();
        $this->assertTrue($user->fresh()->is_active);
        $this->patchJson('/admin/akun/'.$admin->id.'/status', ['is_active' => false])->assertUnprocessable();
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_directory_search_status_filter_and_pagination_do_not_expose_secrets(): void
    {
        $admin = $this->actor('admin');
        for ($i = 0; $i < 17; $i++) { $this->actor('examiner'); }
        $target = $this->actor('examiner'); $target->name = 'Nama unik simulasi'; $target->is_active = false; $target->save();
        $this->actingAs($admin)->get('/admin/akun')->assertOk()->assertSee('Berikutnya')->assertDontSee($target->password)->assertSee('aria-label="Profil saya"', false);
        $this->get('/admin/akun/buat')->assertOk()->assertSee('Identitas dan akses awal');
        $this->get('/admin/akun?q=Nama%20unik&status=inactive')->assertOk()->assertSee('Nama unik simulasi')->assertSee('1 akun')->assertDontSee($admin->email);
        $this->get('/admin/akun?q=Nama%20unik&status=active')->assertOk()->assertSee('Tidak ada akun yang sesuai');
    }

    public function test_login_errors_keep_only_email_and_never_flash_secrets(): void
    {
        $password = $this->password();
        $this->from('/masuk')->post('/auth/login', ['email' => Str::uuid().'@example.test', 'password' => $password])->assertRedirect('/masuk')->assertSessionHasErrors('email');
        $this->assertNull(session()->getOldInput('password'));
        $this->get('/masuk')->assertOk()->assertDontSee($password);
    }

    public function test_account_mutations_require_csrf_outside_testing_bypass(): void
    {
        $admin = $this->actor('admin');
        $this->actingAs($admin); $this->app->instance('env', 'production');
        $this->patch('/profil', ['name' => 'Nama simulasi'])->assertStatus(419);
        $this->post('/admin/akun', [])->assertStatus(419);
        $this->patch('/admin/akun/'.$admin->id.'/status', ['is_active' => false])->assertStatus(419);
    }

    public function test_initial_admin_provisioning_has_no_default_password_and_is_only_allowed_once(): void
    {
        $password = $this->password(); $email = Str::uuid().'@example.test';
        $this->artisan('app:create-admin')->expectsQuestion('Nama Admin', 'Admin simulasi')->expectsQuestion('Email Admin', $email)
            ->expectsQuestion('Kata sandi (minimal 12 karakter, huruf besar/kecil dan angka)', $password)
            ->expectsQuestion('Konfirmasi kata sandi', $password)->assertExitCode(0);
        $admin = User::where('email', $email)->firstOrFail();
        $this->assertTrue($admin->hasPermission('accounts.manage'));
        $this->assertTrue(Hash::check($password, $admin->password));
        $this->artisan('app:create-admin')->assertExitCode(1);
    }
}
