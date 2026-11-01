# Verifikasi kelengkapan — K-12

Mode: Operate. Ordinary extension of the existing workspace and proposal flow.
Preserve DESIGN.md and .impeccable/design.json; no new visual world.

Routes: /verifikasi (BKAD queue), /verifikasi/{item} (submitted item + decision),
/usulan/{package}/item/{item}/kelengkapan (owner check/resubmit), plus existing
item editor and package table extensions.

BKAD's task: inspect submitted data/evidence, choose ready or needs-completion,
give a reason for returns, and see an append-only decision history. Latest draft
corrections stay private to their SKPD until resubmission. Operator's task: find
the returned item/reason, revise data or files, check only that item, and resend.
Sibling items and submitted snapshots remain locked.

Inherit charcoal sidebar (normal400/active500), cream canvas, warm panels,
terracotta actions, Plus Jakarta Sans400/500/600, 2px title/subtitle, 12px groups,
16px panels, 8px vertical table padding, 56px topbar, 28px desktop gutter and
>=40px action targets. Use existing text status badges, toolbar/pagination,
contained table scrolling, check-layout and shared field error associations.
History uses a simple text list with versions, names, WIB dates and reasons.

States: waiting, returned, ready, empty/filter-empty; required return reason,
stale decision, unauthorized role/SKPD, missing evidence/inactive reference,
unchanged or incomplete correction, closed period, resubmission success.
Disabled send actions remain disabled through shared page restoration handling.
One item ready never implies the package as a whole is approved.

No whole-package reopen, administrative bulk approval, email promise, new logo,
or fabricated data. Actual assignment is K-13; eligibility query is prepared
here but not presented as a working assignment screen.

Verification scope: source-only independent review and server-rendered MySQL
endpoint tests. Authenticated BKAD/Operator browser and keyboard checks remain
pending; no visual certification or UAT claim.
