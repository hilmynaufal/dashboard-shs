# Arsip KKR koreksi dan aktivitas — K-21

Mode: Operate. Ordinary extension of confirmed Hearth/Jakarta/compact workspace.
Authority: cached PSE card 241458a1-d59f-4aa7-a335-d26ba6a3b8d4. Live PSE remains
unavailable and its status/screen map is not changed during this work.

Tasks: browse immutable final versions, inspect frozen assignment/approval
identity, preview/download exact retained Word, prepare a reasoned narrative
correction with unchanged item scope, and read permitted event metadata.

Surfaces /arsip-kkr, /arsip-kkr/{version}, existing KKR /koreksi GET/POST and
/aktivitas. Archive first viewport identifies selected final version and whether
it is current or replaced. While a correction is draft/returned/in supervision,
the last approved final remains current. All finalized versions remain listed.
Archive metadata comes from saved snapshots; preview/download identify version.

Only the original examiner with current assignment/draft/view/archive and
assigned-proposal read grants can prepare a correction on an active assignment. The form labels this as
narrative correction: title/purpose/steps/conclusion/recommendation may change;
the exact review IDs from the latest final are retained. A reason is required.
No editing or deleting final content, approvals or files; saving appends a new version and
both approval levels start again. Closed assignment stays read-only. Old source
and Word hashes remain intact; corrupt archive blocks correction.

The selected nonfinal correction uses the neutral message “Versi koreksi ini
belum final.” and links to the final that still applies, including after return
or while in supervision. The correction review table labels rows as retained
and provides hidden fixed IDs, independently checked on the server.

Activity is a read-only, paginated table of time (WIB), actor, recognized action
and object identifier. Technical Admin sees technical account/period/reference
events without raw audit JSON or KKR content. Assigned audit uses current typed
membership and matching resource visibility; private proposal revisions, other
teams' scopes and unknown actions remain excluded. Review and transfer events
match assignment-item scope even when another team has the same item. No raw
before/after values, passwords, internal narratives or file paths are rendered. There is no edit/delete
activity endpoint. Action filter is native GET and resets pagination.

Preserve sidebar400/active500, warm panels/cream/terracotta/Jakarta400/500/600,
2px title/subtitle,12px groups,16px panels,8px table padding,>=40px controls.
Reuse KKR scoped wrapping heading actions, form/error components and explicit
version messages; focusable labelled tables own horizontal overflow. No new
tokens, decorative statistics, modal or automatic-finalization shortcut.

Source fit: the archive/activity/correction-form tables each have a labelled,
focusable scroll region; archive/activity tables use 880/680px minimum widths.
Heading actions wrap through the existing KKR workspace rule. Empty states,
visible native filter/form labels and shared validation affordances are present
in Blade. This supports the ordinary-extension fit at source level only.

Inherited gaps are recorded without repair: DESIGN.md/sidecar overview remains
explicitly about K-3, and the existing KKR detail aspect table still has a plain
unlabelled/non-focusable scroll wrapper. Product/surface documents carry the
later live feature scope; no token or sidecar refresh is part of this pass.

Evidence boundary: source review plus supplied local MySQL/Blade regression
(194 PHP tests, 2620 assertions) and asset build before the final copy/focus
changes. The wording assertion is updated; no final rerun/CI result is claimed
here. Authenticated desktop/mobile and keyboard validation remain deferred by
the developer. Earlier Word UI inspection was stopped; Word layout and
applicant UAT remain pending. Cached scenarios are not duplicated; no live PSE
status or UAT approval is asserted during the outage.
