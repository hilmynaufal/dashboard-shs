# Arsip KKR dan aktivitas

K-21 follows the four agreed criteria from cached PSE context. During the PSE
outage no live card, document, screen or applicant UAT status is changed.

## Final archives

`KkrArchiveController` lists all finalized `kkr_versions` within `archives.assigned`
and existing `visibleKkrIds` scope, paginated at 20 final versions. Identity and
assignment come from the saved version. Detail validates the source and retained presentation/file through
`KkrExport`; previews and downloads remain explicit version routes and downloads
still require the separate download grant. Final metadata includes number,
version, finalization time and frozen same-version supervisor identity.

The highest finalized version number is the current final. A later draft or
returned correction does not replace it. Older finalized versions remain listed
and openable after a replacement is approved. No ordinary delete/update archive
endpoint exists. Missing/corrupt retained files fail closed as established in K-18.

## Narrative corrections

GET/POST `/kkr/{document}/koreksi` requires the current original examiner's
draft/view/archive and assigned-proposal read grants, primary item scope and
active typed examiner membership/assignment. A reason is mandatory and the token guards stale submissions. The current archive is
verified before a draft is appended; the final version and its bytes are not
edited. Existing `supersedes_version_id` and `change_reason` retain lineage.

The prototype corrects KKR narrative (title, purpose, work steps, conclusion and
recommendation) over the same completed review/item set. It does not reopen a
completed item review or permit adding/dropping findings through this workflow.
`KkrWork::source` checks the exact last-final review set, current assignment and
eligible completed reviews. Its own document's finalized items are allowed for
this correction only; another document's final remains ineligible. A returned
correction may append further drafts; no approval is copied between versions.
Finalization again requires independent lead and technical approval and archives
a new private Word/presentation record. Prior final snapshots/approvals/exports
remain intact. A closed assignment or revoked relevant grant blocks mutation.

No schema migration or new permission code is required: K-21 uses the existing
KKR version, approval, export and activity tables and configured grants.

## Activity projection

`ActivityTrail` projects only recognized action/type pairs from `activity_logs`.
The page selects event ID, actor ID/name, time, action and typed object ID;
bounded decision codes and verification status only refine the action label.
Raw before/after JSON, narrative, search filters and private file paths are never
sent to the page. Unknown actions and mismatched subject types fail closed.

`audit.technical` shows account, period and reference events; it never implies
proposal/review/KKR access. `audit.assigned` also requires assigned proposal read
permission and current typed membership. Version events require submitted
versions. Review events require the permitted `assignment_item_id`; transfer
events likewise match permitted assignment-item scope, so another team reviewing
the same item is excluded. Note/response events reuse note visibility, and KKR events reuse the existing KKR scope. A
broader own/all role cannot expand an assigned audit action. The table is
read-only, filtered by action and paginated at 25 events; filters reset page one.
Actors without a stored name are explicitly marked as unrecorded rather than
invented. The event actor identifier remains visible beside the account name.

## Source interface fit

Archive list/detail, activity list and correction form reuse the existing
Hearth/Jakarta compact workspace and components. Archive badges explicitly say
`Final berlaku` or identify the replacing final; a draft correction says
`Versi koreksi ini belum final.` and links to the still-current final. This
message remains neutral in draft, returned and supervision states. The form
shows retained review rows with hidden fixed IDs and the text `Dipertahankan`;
server validation enforces that same scope independently.

The archive, activity and correction-form tables have labelled focusable
horizontal-scroll regions in Blade. The archive/activity table minimum widths
are 880/680px; their own scroll wrappers contain overflow. KKR heading actions
wrap through the existing workspace CSS. Empty states, visible labels, native
GET action filtering, separate Word download permission and the existing
validation/error components remain in use. These are source findings, not
observations of rendered desktop/mobile behavior or keyboard success.

DESIGN.md tokens and its sidecar are preserved. Their overview still describes
the K-3 prototype; later live-surface scope is recorded in PRODUCT.md and the
surface briefs. The inherited KKR detail aspect table still has an unlabelled,
non-focusable `.table-scroll` wrapper. This pass records that preexisting gap
without extending K-21 into repair of inherited surfaces.

## Verification boundary

Automated coverage exercises frozen final identity, old/new archive bytes,
reason/unchanged-scope enforcement, ordinary-final mutation denial, two-level
reapproval, returned correction, duplicate/stale requests, corrupt archive,
closed assignment, revoked scope/grants, technical/assigned projections and
safe malformed-filter recovery. The supplied local run before the final copy/
focus-region changes passed 194 PHP tests (2620 assertions) and the asset build.
The copy assertion was updated with the final wording; this documentation pass
does not claim a final rerun or CI result. Final validation belongs to the
implementation handoff.
Source review does not constitute authenticated visual/keyboard, Word-layout
or applicant UAT approval. Authenticated desktop/mobile and keyboard checks were
deferred by the developer; the earlier Word UI inspection was stopped and Word
layout remains pending. Cached UAT scenarios already cover this feature and are not duplicated.
