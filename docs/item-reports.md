# Rekap usulan dan ekspor Excel

K-20 implements `/rekap` and `/rekap/excel` from the cached agreed PSE criteria.
The live PSE card remains unchanged during the outage. This is developer
implementation evidence, not applicant acceptance.

## Data and permission contract

`ResourceAccess::reportedItemIds` pairs own/all/assigned `reports.*` action
permission with the matching proposal resource permission, using the same scope
helper as monitoring. Broader read permission from another role does not expand
a narrow report grant. Admin has no default report grant. Assignment membership
is evaluated on each request; no client organization/assignment ID grants access.

`ItemReport` uses one query for the list and export, with a single latest
permitted version per item. A private draft is eligible only when the actor
has both `reports.own` and `proposals.view_own` and the proposal belongs to the
actor's SKPD. Assigned/global report grants alone select the latest submitted
version, including for an actor who also has own-SKPD read permission. Code,
unit and account fields come from the saved reference snapshot, not current
master codes. One item remains one row even when it has multiple account codes;
the codes are joined with `; `. Item links open the selected version. No
internal review results, notes or KKR contents are included in this proposal
report.

Filters are literal code/description search, budget year, SHS/SBU, item status
and allowed SKPD. Literal percent, underscore and backslash are escaped in LIKE
searches. Dropdown options derive only from scoped report items. A foreign SKPD
parameter is rejected without returning that organization's name or contents.
Year selection with no data returns a valid empty result. List pages contain
20 items; applying/resetting filters begins at page one.

Filters submit through a native GET form. The download link retains the applied
filters, not unsubmitted edits; the page tells users to apply new choices before
exporting. The displayed export count represents the full result, not the
current page. The result table has a labelled, keyboard-focusable scroll region
and reuses the workspace pagination and field-error components.

## XLSX contract

The export ignores list pagination and applies the same validated filters and
scope. A transaction keeps its multi-chunk reads consistent. `lazyById` reads
500 items at a time; a value-only OOXML writer spools worksheet XML to private
temporary files before packaging and sending the attachment. Files are cleaned
after send or on failure, including failed packaging or transaction commit.
No export archive, new schema or new dependency is introduced.

Columns: No., Nomor paket, Tahun anggaran, Jenis, SKPD, Kode barang, Uraian,
Spesifikasi, Satuan, Rekening, Harga usulan (Rp), Status item, Versi usulan.
All cells are explicitly text, including row numbers, years and price. This
preserves leading zeros and prices with 18 whole-number digits plus two decimal
digits without conversion to floating point. Price uses exact decimal string
formatting with Indonesian separators; it is not silently rounded. Text prices
are not numeric cells for implicit spreadsheet calculations. The page states:
“Harga berupa teks dan tidak langsung dijumlahkan di Excel.” No formulas are
written even when a user value starts with `=`, `+`, `-` or `@`; the native OOXML
package contains no macros or external relationships.

Each sheet repeats its header, freezes the top row, sets column widths and
enables native column filters. More than 1,048,575 data rows continue on a new
sheet. Empty results produce a header-only workbook. A cell over Excel's 32,767
UTF-16-unit text capacity is rejected instead of silently truncated. Literal
OOXML escape-like strings and forbidden XML controls are encoded for Excel.

## Verification boundary

Feature coverage checks combined filters, literal searches, own/assigned/global
scope, private revisions and mixed-role report grants, scoped options,
all-page/all-chunk exports, saved reference stability, leading zeros and exact
long prices, revoked membership,
revoked grants, disabled sessions, empty output and malformed-filter recovery.
Package checks validate each XML part, multiple sheets, repeated/frozen headers,
text types, no formulas/external links, and readability by the existing XLSX
reader. The final developer run on 5 November 2026 passed all 183 PHP tests
with 2431 assertions; the production asset build also passed. Authenticated desktop/mobile and keyboard checks,
actual spreadsheet-app opening/layout inspection and applicant UAT remain
pending. Existing UAT cards in the cached PSE context are not duplicated.
