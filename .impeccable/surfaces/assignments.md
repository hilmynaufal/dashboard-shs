# Penugasan dan pekerjaan Pemeriksa — K-13

Mode: Operate. Ordinary extension of the existing Hearth workspace; preserve
DESIGN.md and its sidecar. Routes: /penugasan, /penugasan/buat, /penugasan/{id},
/penugasan/{id}/pilih-item, /penugasan/{id}/item/{scope}/alihkan, /pekerjaan.

Task: lead creates a bounded team, selects ready items in its organization and
period, assigns each stable item ID to an examiner member ID, or transfers the
primary examiner with a reason. Examiner finds current work and authorized
version/evidence. This extends the existing verification-to-proposal flow.

Keep Jakarta400/500/600, charcoal sidebar400/active500, cream canvas, warm panels,
terracotta actions, title/subtitle2px, groups12px, panels16px, table vertical8px,
topbar56px, desktop gutter28px and >=40px targets. Reuse table toolbar/pagination,
form fields/error association, contained scrolling and empty states. Transfer
uses a dedicated form; optional history is an ordinary details disclosure.

Candidate page shows minimal selection metadata until assigned. It explains
that choices belong to the current page and must be saved before filtering or
paging. Blank examiner means unselected; sorting never supplies item identity.
Detail/work lists expose only the viewer's current item scope. No decorative
metrics, fake logos/accounts, or button to start an unimplemented K-14 review.

States: empty/filter-empty, no eligible users/periods/SKPD, ready candidate,
assigned/current examiner, transfer history, invalid/foreign team selection,
returned/out-of-period-scope item, inactive account/role, stale form/reload,
completed/read-only, invalid HTML recovery, save/transfer success, role403.

Evidence scope: real MySQL endpoint tests and independent source review.
Authenticated lead/examiner browser and keyboard verification remain pending;
no visual certification or UAT claim. No token or visual-world changes.
