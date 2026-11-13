# KKR Word export

K-18 adds explicit selected-version routes
`/kkr/{document}/versi/{version}/preview` and
`/kkr/{document}/versi/{version}/word`. `KkrPrint` is the shared presentation model;
`KkrWord` builds static OOXML using PHP ZipArchive, without new dependencies,
external relationships, macros or embedded client source documents.

## Template contract

The implemented template contains an identity block, purpose, work steps, review
table, conclusion, recommendation, lead/technical supervision and team identity.
The review table has these eight columns, in order: No., Kode, Uraian,
Spesifikasi, Satuan, Harga, Pengusul, Catatan Hasil Reviu. The implemented
A4 portrait geometry is 11907 × 16839 twips; margins top 964, left/right/bottom 851 and
header/footer distances 709. The proportional eight-column grid is fitted to
the 10205-twip usable width. The Word layout follows Form. 5 with a ruled form heading, a paired identity block,
gray section bands, a light green repeating header and three team signature columns.
The form uses Arial 11pt. The findings table uses Calibri 11pt with an 8pt header
and row numbers. Legal basis, scope, narratives and names come from the selected
version. The template contains no reference-document identities or sample findings.

`instansi-shs-v1` names the implemented template contract. Preparation now
captures template, purpose and work steps alongside existing narrative and
frozen assignment/item/review source. Existing `supervision-v1` versions use
explicit missing-narrative fallbacks without rewriting their source. Unsupported
template versions and failed source integrity checks block output.

HTML and DOCX consume the same eight cell strings and narratives; exact decimal
money uses `ProposalMoney`, never floating-point conversion. Notes include
aspect results, comparison/recommended prices, responses and latest submitted
version context without treating a newer proposal as re-reviewed. Fields left
empty (comparison price, recommended price, item recommendation) are omitted
from the notes cell instead of printing "Belum diisi"; archived final exports
keep their retained text. XML escaping
and shared removal of invalid XML control characters preserve safe text.
Word repeats the header row, permits rows to split over pages, has no fixed
row height, and uses a PAGE footer field starting at one. Fields refresh on open.

## Final retention and access

View and download are separately authorized before generation, with explicit
document/version matching. Finalization stores the exact DOCX bytes and shared
presentation snapshot together on private local storage in the existing
`kkr_exports` table. The K-18 migration adds nullable JSON `render_snapshot`,
nullable CHAR(64) `render_sha256` and a unique constraint on `kkr_version_id`;
it adds no tables or foreign keys. Version locks serialize first-generation of
legacy final documents. Draft exports are generated on demand and not retained
as final.

The archived model includes saved-source identity and same-version supervision
decisions. Preview after final uses that retained model. Downloads verify model
hash, source hash, version identity, private path, byte size and file hash; a
missing/corrupt archive fails closed and is never silently regenerated or
overwritten. Responses are private/no-store attachments with nosniff.

## Workspace presentation

The form records template, purpose and work steps for the saved version.
The version detail and preview offer explicit selected-version links; download
is shown only when its separate policy allows it. The preview states that its
content matches Word while page breaks follow the Word application. A non-final
version warns that later supervision decisions can change its supervision
section. A final version identifies the shared retained archive.

Both screens inherit the existing Hearth workspace and Jakarta UI. KKR heading
actions wrap within their scoped workspace. The preview's eight-column table
has its own labelled, keyboard-focusable scroll region, a row/version caption,
wrapping cell text and 1100px minimum width. Document geometry and Calibri remain
part of the Word output contract and do not replace application design tokens.

## Verification boundary

Automatic checks cover shared content, eight cells, exact prices, malicious
text, selected historical versions, external roles, revoked download/membership,
package structure, repeating headers, PAGE fields, deterministic bytes, and
immutability after live reference/source changes. The reported K-18 checkpoint
passed 159 PHP tests / 2016 assertions after scoped heading-action wrapping.
The source-only design review permits shipment within this evidence boundary.
The Form. 5 revision was also rendered locally in desktop Word with synthetic
multiline findings to inspect page continuation and the closing signature block.
Real-account UI QA and PSE acceptance remain separate.
