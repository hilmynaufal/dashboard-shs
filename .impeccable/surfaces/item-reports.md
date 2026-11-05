# Rekap usulan — K-20

Mode: Operate. Ordinary extension of the Hearth/Jakarta/compact workspace.
Authority: cached PSE card 43671bc6-09fc-44cc-a5af-13429766295e and feature
0369c489-d05e-4577-8b11-d2ccf609073e. PSE outage was explicitly acknowledged
by the developer; no live card, screen-map or UI-document mutation is claimed.

Task: find item by saved code/description, combine budget year, SHS/SBU, item
status and allowed SKPD filters, inspect a paginated list and download all
selected data as XLSX. No review-result reporting or KKR content added here.

Routes /rekap and /rekap/excel. First viewport names the task, exposes export
with total item count, and presents labelled filters. Filters are applied by a
native GET form; applying/resetting resets pagination. Export preserves applied
filters and ignores page. Plain copy explains that unapplied form changes need
Terapkan filter before export, all pages are included, and code/account/price
cells are text to preserve leading zeros and every decimal digit. The page also
explains that text prices are not directly summed in Excel.

One item is one row, with all its account codes joined by semicolons from the
saved reference snapshot. Money is formatted as an exact decimal string with
Indonesian separators. Only the latest permitted proposal version is used.
Private drafts require both reports.own and proposals.view_own in the actor's
own SKPD. Assigned/all report grants alone use the latest submitted version,
including for mixed-role actors with own-SKPD read permission. This applies to
searches and dropdowns as well as rows and exports. Item links open that exact
version. Own/all/assigned report grants require matching resource scope;
technical Admin is not automatically entitled to proposal reports.

Preserve sidebar400/active500, cream/warm surfaces, terracotta actions, Jakarta
400/500/600 and compact12px groups/2px title gap. Reuse field errors, buttons,
badges, footer pagination and a labelled keyboard-focusable table scroll region.
Filter grid folds 4 → 2 → 1 columns; no new tokens or decorative statistics.
No-result and malformed-filter recovery use existing workspace messages.

Every XLSX cell is text, including prices, years and row numbers; text prices
do not implicitly become numeric cells for spreadsheet arithmetic. XLSX uses
wrapped text cells, frozen column headings, column widths and native filters.
No formulas, macros, external relationships or production sample rows.
Export produces all filtered items in 500-item database chunks within one read
transaction and splits at Excel's per-sheet row limit. Cells exceeding Excel's
text capacity fail the export instead of being truncated. Temporary files are
private and removed after send or failure. No new dependencies/schema required.

Finisher disposition: SHIP for the source-only implementation boundary. Source
inspection covers the shared query, saved references, scoped version selection,
native filters, responsive rules and XLSX package generation. MySQL/server-
rendered integration tests, native XLSX package/reader tests and the production
build supply implementation evidence; final run totals belong to the handoff.
Authenticated desktop/mobile visual and keyboard checks, actual spreadsheet-app
opening/layout QA and applicant UAT are not claimed as completed. DESIGN.md and
the design sidecar remain unchanged; their older prototype narrative is outside
this extension's documentation boundary.
