# KKR dan supervisi — K-17

Mode: Operate. Ordinary extension of assignment/review workflow. Preserve
DESIGN.md, design.json and the confirmed Hearth/Jakarta/compact identity.

Task: examiner prepares an immutable-content version of completed results,
submits to lead, receives reasoned return or approval, then technical controller
returns or approves-and-seals final. Revised content starts both approvals again.

Surfaces: /kkr queue table; assignment /kkr/buat and document /ubah preparation
form; /kkr/{document} selected-version inspection with separate result/version
pagination. Existing assignment and sidebar entry points. No modal or decorative
metrics. Preparation is prerequisite supervision content, not Word-template QA.

First viewport shows identity/current stage and a plain warning for open notes,
stale source, integrity error or closed/final state. Scope/team and narrative are
followed by saved result tables, separate prices, original-versus-latest submitted
version context, retained evidence, and explicit status/content of all recorded
notes. Supervisor history belongs to selected version. Mutating forms appear only
on latest eligible version for current role; stale approval disabled, return still
available; final/closed is read-only. Old decisions remain browsable.

Open-note acknowledgement is required for submission and approval, not return.
Return reason required; technical approved option explicitly names final locking.
No author self-approval or same-person two-level approval. Revisions retain old
content and require reason; no approval state is inherited. Missing/corrupt source
never displays a ready-to-approve claim. No private SKPD draft content is loaded.

Reuse cream canvas, warm panels, charcoal400/active500 sidebar, terracotta actions,
Jakarta400/500/600, title/subtitle2px, groups12px, panel16px, tablevertical8px,
topbar56px, desktopgutter28px, >=40px targets and existing scroll/focus/validation
components. No new tokens, imagery, logo or world. Tables own narrow overflow.

States: empty/permitted queues, eligible completed results, invalid/revoked source,
prepared, submitted, lead approved, returned with reason, new version, stale token,
stale source, open/resolved/internal notes, two-level approved/final, old version,
closed assignment, invalid HTML values and CSRF. Shared submit guard is reused.

Evidence boundary: source review + automated MySQL/HTML-render regression and
build. Real-account visual/keyboard checks and applicant UAT remain pending under
the developer's implementation-first instruction. No Word/export claim in K-17.
