# Akun — K-7

Mode: Operate. Extend the approved Hearth system and compact spacing in DESIGN.md.
Routes: /masuk, /aplikasi, /profil, /admin/akun, /admin/akun/buat.

Purpose: sign in with a real active account, see role identity, update one's own
name/position/password, and let Admin create or activate/deactivate accounts.
No public registration, default passwords, password recovery service, or completed
business modules are claimed. Initial role/SKPD is captured when creating an
account; later access-management changes belong to K-8.

First viewport: login has charcoal identity region and a compact cream credential
form; on narrow screens identity reduces to a content-height header. Signed-in
pages inherit the 240px sidebar, topbar and compact forms/table already approved.
Profile separates identity and password forms. Admin list emphasizes name/email,
role, SKPD and active status with explicit status-change confirmation.

States: anonymous redirect; generic invalid/nonactive login; rate limit; field
validation; saved success; no search results; disabled account/session; self-disable
rejected. All account pages have private no-store responses. All six roles may
access their own profile; only accounts.manage grants account administration.

Verification: MySQL shs_new_testing with synthetic identities and random secrets;
browser login desktop/mobile and actual user width; no real account credentials
written into source, documentation, screenshots or commits.
