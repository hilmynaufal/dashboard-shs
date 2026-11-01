# Pengaturan akses — K-8

Mode: Operate. Extend existing accounts/workspace composition; preserve DESIGN.md.
Route: /admin/akun/{id}/akses. Entry: Atur akses in Admin account directory.

Purpose: authorized Admin selects official roles and current organization, sees
validation and saves atomically. Identity is read-only. Internal role combinations
are allowed; Operator SKPD remains exclusive to preserve own-organization scope.
Roles and SKPD alter grants, not ownership of existing proposals or assignments.

First viewport: existing sidebar/topbar, heading, back link, compact form panel.
Identity leads into labelled role fieldset, active-organization select, explanation,
and save/cancel actions. Role controls are 44px-high text-labelled rows, two columns
on desktop and one on phones. Same Jakarta font, cream/charcoal/terracotta tokens,
16px panel and 12px group spacing; no new design system or decorative controls.

States: unauthorized403 with Indonesian recovery page; roles missing/malformed;
inactive/missing SKPD; invalid mixed operator/internal roles; self-Admin revocation;
stale version409 with explicit reload action; successful save and no-change save.
Existing inactive SKPD remains visible and cannot silently disappear.

Verification: real endpoint/render and policy regression tests using disposable
MySQL test database. Developer explicitly deferred authenticated browser testing
because initial Admin has not been created. Do not claim screenshots of live
authenticated UI, keyboard verification, business controller coverage, or UAT pass.
