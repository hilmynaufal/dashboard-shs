# Pemantauan pengajuan reviu dan tindak lanjut

K-19 implements GET `/pemantauan`. The cached agreed PSE card is
398df9a8-3a2a-4a0a-99c1-a716ede33d69; no live update was possible during the
PSE outage. The developer authorized implementation from cached official
criteria; no live card status, screen map update or acceptance is claimed.
This document describes implemented code, not applicant UAT results.

## Scope and counts

`ResourceAccess::monitoredItemIds` intersects each monitoring action with its
matching own/all/assigned proposal resource permission. An unrelated broader
read grant never expands a narrower monitoring grant. Disabled sessions are
rejected by the shared middleware; current membership and role grants are read
on each request. Admin has no default business monitoring access.

`ProgressBoard` builds shared queries for summary groups and detail pagination.
Packages are counted once, including empty own drafts; external drafts remain
private. Assigned package item totals include only visible items. Item names and
version links use the latest submitted version outside the owner's SKPD. Notes
reuse `visibleNoteIds` and intersect the monitored items; private internal notes
are not inferred from broad package visibility. KKR additionally requires its
existing document policy and assignment monitoring permission.

Counts and selected details are read inside one database transaction; no
background counter or stale cache is used. Twenty rows are shown per page.
Dataset totals and status counts cover the actor's whole visible dataset,
independently of the selected detail filter. Each status/metric link resets
pagination and uses the same predicate as its count. `Muat ulang` preserves the
dataset/status/metric selection, starts at page one and reloads server data.
The displayed load time uses Asia/Jakarta (WIB); there is no automatic refresh.

## Independent indicators

- Reviu selesai: latest visible review per item has status `completed`.
- Hasil sesuai: that completed review has at least one `compliant` result,
  no result outside `compliant`/`not_applicable`, and a nonblank reason for
  each `not_applicable` result. All-exempt and empty results do not count.
- Catatan terbuka: count of individually visible notes whose status is not
  `resolved`; multiple notes on one item are counted separately.

The indicators overlap intentionally. Completing a review or approving a KKR
never silently resolves a note or labels a substantive result compliant. Only
actors with assignment monitoring and substantive review read scope see review
outcome metrics. BKAD and SKPD continue to see permitted workflow statuses and
notes, without access to internal results or KKR through this page.

## Navigation and presentation

The default dataset is items. Dataset, status and metric links expose the active
selection with `aria-current="page"`. Status and review metric are separate
selections; combining a non-all status with a metric is rejected. Metric
drilldowns use `Buka reviu` to open the exact latest visible review used in the
count. Ordinary item rows open the selected visible proposal version; package,
note and KKR rows open their existing policy-protected detail screens.

The page inherits the shared Hearth workspace, Jakarta typography, compact
spacing, buttons and text badges. Dataset/status links wrap; the 680px minimum
detail table scrolls within a labelled, focusable region. Empty selections
explain that there are no rows within the current access scope. No chart,
forecast, target or combined compliance score is introduced.

## Verification boundary

`tests/Feature/ProgressBoardTest.php` covers own/assigned/global scope,
unassigned siblings, private revisions, internal notes, independent indicators, count/drilldown
equality, latest review, revoked membership, mixed roles, pagination, invalid
filters and actual verification and KKR supervision transitions. The production
asset build was reported passing with K-19 styles and the K-18 action wrapping.
The final developer test run on 5 November 2026 passed all 170 PHP tests
with 2168 assertions; this includes the K-18 and K-19 regression coverage. The design review is source-only: the two review
suggestions (active selection semantics and a specific review destination for
metric actions) are reflected in code. Authenticated desktop/mobile and keyboard
checks and applicant UAT remain pending. PSE remains unavailable for live updates.
