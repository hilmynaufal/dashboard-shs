<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\MySqlAccessTestCase;

class SessionAuthenticationTest extends MySqlAccessTestCase
{
    public function test_login_rotates_session_and_logout_removes_access(): void
    {
        $user = $this->actor('skpd_operator', $this->organization());
        $password = Str::random(40);
        $user->password = Hash::make($password); $user->save();
        $this->getJson('/auth/csrf-token')->assertOk()->assertJsonStructure(['csrf_token']);
        $oldSession = session()->getId();
        $this->postJson('/auth/login', ['email' => $user->email, 'password' => $password])
            ->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonMissingPath('user.password');
        $this->assertNotSame($oldSession, session()->getId());
        $this->getJson('/auth/me')->assertOk()->assertJsonPath('user.id', $user->id);
        $this->postJson('/auth/logout')->assertOk();
        $this->getJson('/auth/me')->assertUnauthorized();
    }

    public function test_wrong_password_and_disabled_account_have_same_generic_response(): void
    {
        $user = $this->actor('examiner'); $password = Str::random(40);
        $user->password = Hash::make($password); $user->save();
        $wrong = $this->postJson('/auth/login', ['email' => $user->email, 'password' => Str::random(40)])->assertUnauthorized()->json();
        $user->is_active = false; $user->save();
        $disabled = $this->postJson('/auth/login', ['email' => $user->email, 'password' => $password])->assertUnauthorized()->json();
        $this->assertSame($wrong, $disabled); $this->assertGuest();
    }

    public function test_disabled_organization_cannot_login(): void
    {
        $org = $this->organization(); $user = $this->actor('skpd_operator', $org); $password = Str::random(40);
        $user->password = Hash::make($password); $user->save();
        DB::table('organizations')->where('id', $org)->update(['is_active' => false]);
        $this->postJson('/auth/login', ['email' => $user->email, 'password' => $password])->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_deactivated_user_loses_existing_session(): void
    {
        $user = $this->actor('examiner');
        $this->actingAs($user)->getJson('/auth/me')->assertOk();
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
        $this->getJson('/auth/me')->assertUnauthorized(); $this->assertGuest();
    }

    public function test_role_revocation_is_visible_without_reusing_old_permissions(): void
    {
        $user = $this->actor('admin');
        $this->actingAs($user)->getJson('/auth/me')->assertJsonFragment(['accounts.manage']);
        DB::table('role_user')->where('user_id', $user->id)->delete();
        $this->getJson('/auth/me')->assertOk()->assertJsonPath('permissions', []);
    }

    public function test_login_attempts_are_limited(): void
    {
        $email = Str::uuid().'@example.test';
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/auth/login', ['email' => $email, 'password' => Str::random(40)])->assertUnauthorized();
        }
        $this->postJson('/auth/login', ['email' => $email, 'password' => Str::random(40)])->assertStatus(429);
    }

    public function test_csrf_is_required_outside_unit_test_bypass(): void
    {
        $this->app->instance('env', 'production');
        $this->postJson('/auth/login', ['email' => 'simulation@example.test', 'password' => Str::random(40)])->assertStatus(419);
    }

    public function test_anonymous_access_is_denied_even_without_json_header(): void
    {
        $this->get('/auth/me')->assertUnauthorized();
    }

    public function test_malformed_login_input_is_rejected(): void
    {
        $this->postJson('/auth/login', ['email' => ['invalid'], 'password' => ['invalid']])->assertUnprocessable();
    }

    public function test_authenticated_user_cannot_switch_identity_without_logging_out(): void
    {
        $user = $this->actor('admin');
        $other = $this->actor('examiner');
        $this->actingAs($user)->postJson('/auth/login', ['email' => $other->email, 'password' => Str::random(40)])->assertStatus(409);
        $this->assertAuthenticatedAs($user);
    }
}
