# Meja reviu per item — K-14

Mode: Operate. Ordinary extension of assigned-item workflow. Preserve DESIGN.md
and sidecar; no new visual world. Routes: GET/POST assigned scope /reviu, plus
GET/POST /reviu/{id} and POST /reviu/{id}/catatan.

Task: current examiner starts the assigned submitted version, assesses stored
criteria, records separate prices/recommendations and multiple internal issues,
then saves draft or saves and completes subject to required/N-A checks.

Two-column desktop workspace: sticky source/version/evidence panel, wider
assessment form; one column on smaller screens. Aspect blocks are separated by
plain rules, not nested cards. Source price remains visible. Price inputs are
explicitly separate and nullable. Notes are a separate append-only section with
actor/time, pagination and an explicit warning to save result edits first.

Keep Jakarta400/500/600, charcoal sidebar400/active500, cream/warm surfaces,
terracotta actions, title/subtitle2px, groups12px, panels16px, 8px table vertical,
56px topbar,28px desktop gutter,>=40px controls. Use shared field errors, focus,
private evidence links and source components. No new logo or decorative metrics.

States: unstarted, five explicitly labelled prototype aspects approved by user,
not assessed, assessed/NA-reason, empty configuration, optional price versus zero,
draft saved, completion blocked, completed/read-only, stale/transfer denial,
validation recovery, notes empty/added. Names/codes/required flags are snapshots.
Two submit buttons preserve selected intent before disabling; browser restoration
restores only touched buttons and retains server-disabled actions.

Internal notes are not presented as official SKPD communication. No send,
supervisor approval or KKR-final action. Existing work-list links are keyed by
scope ID inside each row, never by row index or an undefined header item.

Evidence boundary: independent source review + real MySQL endpoint tests and
frontend submit lifecycle regression. Authenticated browser/keyboard inspection
and UAT remain pending; no visual certification claimed.
