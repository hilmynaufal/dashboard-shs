# Catatan dan tanggapan — K-15

Mode: Operate. Ordinary extension; preserve DESIGN.md and design.json.
Routes: /catatan, /catatan/{id}; send/respond/decision POST actions; BKAD's
administrative form on /verifikasi/{item}. Reuse proposal editor and private files.

Task: lead sends an internal note officially; BKAD sends an administrative note
before assignment; owning SKPD replies with optional explicit publication of a
single-item draft; current examiner/BKAD records a reasoned disposition.

List first viewport: compact status filter and scoped item/category/type/status
table, with a named open-note action. Detail first viewport: item+origin version,
note content, status and action permitted to current actor. Explicit checkbox confirmation
before the lead sends a substantive note; BKAD sends administratively through
its named submit action. SKPD sees no draft. Text-only reply states that a private
draft is not published. Item editing and evidence stay in the existing editor.
History contains paginated append-only responses with a before/after table and
private evidence links, then decision with mandatory reason. No decorative charts.

Preserve Plus Jakarta Sans400/500/600, charcoal sidebar400/active500, cream/warm
surfaces, terracotta actions, 2px title/subtitle,12px groups,16px panel,8px table
vertical,56px topbar,28px desktop gutter,>=40px actions. Shared table scroll owns
wide comparisons on mobile; no page overflow or new visual identity.

States: internal, official sent, reply pending, returned with reason, resolved;
empty/filtered, private draft present/absent, text-only reply, optional publication,
revision blocked by completeness/period/file, stale/transfer denial, final/closed.
Shared isMutable hides send/respond/decide forms on closed/final records and
shows a read-only explanation; mutations use the same guard.
Errors use shared summary+field recovery, form values are escaped/scalar-safe,
submit guard preserves button intent. Current permissions control every action.

Evidence boundary: source review and real MySQL endpoint/regression tests.
Authenticated desktop/mobile, keyboard inspection and UAT remain pending because
the developer requested implementation/testing first without real accounts.
