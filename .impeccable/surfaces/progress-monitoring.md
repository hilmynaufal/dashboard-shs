# Pemantauan — K-19

Mode: Operate. Ordinary extension of the confirmed Hearth/Jakarta/compact UI.
Authority: cached PSE K-19 criteria, feature d70e33f6-10c3-4b50-a6e9-bb1e76c7ca5e.
PSE is unavailable; the developer authorized cached official criteria. No live
card status, screen-map mutation or acceptance is claimed.

Route /pemantauan. Users switch between packages, items, visible notes and,
for authorized assignment-team members, KKR supervision. Every status link
opens a paginated drilldown using the same scoped query as its summary count.
The first viewport identifies the scope, refresh time, dataset totals and
separate review-completed/compliant/open-note indicators where authorized.
No decorative charts, forecast, invented target or aggregate score.

Completed means latest visible review per item is completed. Compliant is a
separate subset: at least one compliant result, no unassessed/noncompliant/
insufficient-evidence result, and every not-applicable result has a reason.
Open notes remain independent and may coexist with completed/compliant reviews.
SKPD sees official own notes; BKAD administrative notes; assignment teams see
notes permitted by existing policies. Internal review outcome metrics and KKR
are not exposed through monitoring to actors without substantive read access.

Own empty draft packages count; assigned packages count once and their item
counts exclude unassigned siblings. External item descriptions/links use latest
submitted version, never an owner's private revision. Changing status or metric
resets pagination. Metrics and item statuses are separate selections; combining
a metric with a non-all status is rejected. Active dataset/status/indicator links
use aria-current="page". Metric rows say Buka reviu and open the exact latest
visible review that supplied the count. Ordinary item rows open their selected
visible proposal version. No auto refresh promise: Muat ulang reloads the
dataset/status/metric selection from the server at page one, with a WIB load time.

Reuse sidebar400/active500, Jakarta400/500/600, 2px heading/subtitle gap, 12px
groups, 16px panels, 8px table vertical padding and >=40px actions. Wrapping
dataset/status links; table owns its narrow overflow with a keyboard-focusable
labelled scroll region. No new colors or global token changes.

Source fit: the shared workspace provides the Hearth shell and existing tokens;
local rules reuse ink, line, accent-soft and accent with no new global tokens.
The 680px minimum table stays within its labelled scroll region. This is an
ordinary extension; DESIGN.md and its sidecar remain the incumbent K-3 system
record. Their prototype-focused narrative is not evidence of current feature
completion; it is left unchanged in this scoped documentation pass.

Evidence: inspected ProgressController, ProgressBoard, ResourceAccess, Blade,
shared/local CSS and ProgressBoardTest source. The source-only review's two
suggestions (aria-current and specific review metric actions) are implemented.
The production asset build was reported passing with K-19 styles and the K-18
action-wrap fix; final PHP test results belong to the implementation handoff. Authenticated
desktop/mobile and keyboard checks and applicant UAT remain deferred under the
developer's implementation-first choice; no visual validation is claimed.
