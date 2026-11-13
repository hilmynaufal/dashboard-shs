# Usulan Operator — K-10/K-11

Mode: Operate. Extend the existing Hearth workspace; preserve DESIGN.md and its
sidecar. Routes: /usulan, /usulan/buat, /usulan/{id}, item create/edit/history,
import upload/preview/row correction, and /usulan/{id}/periksa.

Task: create a persisted SHS/SBU draft, add or import items, attach private
evidence per item, fix explicit readiness issues, then submit an immutable set
of item versions. This is separate from the synthetic /prototype/operator demo.

Keep Plus Jakarta Sans 400/500/600, charcoal sidebar labels 400/active 500,
cream canvas, warm white panels, terracotta primary actions, dense tables.
Preserve approved spacing: title/subtitle 2px, groups 12px, panel padding 16px,
table vertical padding 8px, topbar 56px, desktop gutters 28px, controls >=40px.

Each page exposes one next task: create package, add/import item, correct row,
complete evidence, or check/send. Draft versus submitted versions are explicit.
Import shows mapping (including blank headings), original row numbers, ignored
groups/headers, corrections, counts, and errors before writing any item. Invalid
commit/send buttons remain disabled even after browser page restoration.
Read-only viewers see authorized items and version/evidence links, not edit forms.

States: empty/search-empty, required or inactive reference, invalid decimal,
missing evidence, date window closed, stale form with reload, invalid XLSX or
file MIME/size, failed upload, duplicate evidence/import, preview cancellation,
successful save/send, authorization denial, immutable submitted version.
Contained table scrolling and pagination support phones; bulk work is desktop.
No fabricated real records, logo, dashboard claims, or new review actions.

Evidence: server-rendered HTML and real MySQL endpoint tests; frontend regression
for restoration of submit controls. Source-only independent review closed three
material findings. Authenticated Operator browser/keyboard inspection remains
pending; no claim of visual certification or user acceptance.

Local demo exception requested by the developer: when APP_ENV=local and
DEMO_ALLOW_MISSING_EVIDENCE=true, absent evidence does not block sending or
BKAD verification. Check/completion/verification pages disclose the exception;
readiness and agreement copy refer to data without claiming evidence is complete.
All other validation and uploaded-file integrity checks remain enforced.
The default is false and the flag has no effect outside local. See
docs/local-demo-evidence.md. Layout and visual tokens remain unchanged.
