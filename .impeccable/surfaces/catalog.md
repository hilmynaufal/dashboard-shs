# Periode dan referensi — K-9

Mode: Operate. Extend the existing workspace; preserve DESIGN.md and sidecar.
Routes: /periode (+ create/edit), /referensi/{barang,rekening,satuan} (+create/edit).

Task: Admin/BKAD creates/edits a period and maintains official codes/units.
Existing sidebar gains two permission-controlled links. Same Jakarta, Hearth
palette, 2px title/subtitle,12px groups,16px panels, >=40px controls. Lists use
shared toolbar, localized status badges, contained table scrolling and pagination.
Empty tables drop minimum width and hide column headers so recovery actions stay
visible on phones. Reference kind links are ordinary navigation, not ARIA tabs.

Period form shows timezone (WIB), dates and status, and explains that open status
only works inside the date range. Reference form emphasizes text codes, immutable
historical snapshots and active/inactive state. Stored master data is not presented
as a submitted-version snapshot. No sample records or logos are invented.

States: empty/search-empty, invalid date/order/code/status, duplicate code,
stale edit/delete with reload, used-reference deletion denied, authorization403,
save success. All error field associations inherit accounts.js; action target
labels include record context. Delete uses explicit UI confirmation.

Evidence boundary: browser session was stale and redirected to login on actual
navigation. No current authenticated captures/keyboard verification. Actual MySQL
endpoint tests and source review supply this phase's evidence; don't claim UAT or
end-to-end operator submission/final archive export, which belong to K-10/K-18.
