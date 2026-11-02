# Riwayat item — K-16

Mode: Operate. Ordinary extension of proposal/version workflow; preserve
DESIGN.md and sidecar. Route GET /usulan/{proposal}/item/{item}/riwayat.

Task: inspect ordered version changes with actor/time and before/after values,
open original evidence after later uploads, and trace sent/returned/resubmitted
events with reasons. No mutation action belongs on the history surface.

First viewport: existing compact page heading and version table, newest first,
actor/save time, submitted/private state and explicit selected version. Following
section compares saved fields and private evidence links against the previous
visible version. Events are a plain chronological list with actor/time/reason,
links to permitted notes, and independent pagination. Both tables use the shared
scroll container. No new sidebar item, charts, counters or identity assets.

Reuse Hearth cream canvas, warm panels, charcoal400/active500 sidebar,
terracotta actions, Jakarta400/500/600, title/subtitle2px, groups12px, panels16px,
table vertical8px, topbar56px, desktop gutter28px and >=40px actions. Desktop is
primary; shared table overflow handles narrow devices. No new tokens.

States: selected/default latest, first visible version, missing timestamp/actor,
legacy submission without audit actor, private owner draft, external published
versions, before/after changes, missing/retained documents, empty/no permitted
version, paginated versions/events, inaccessible or mismatched ID. Internal notes
and raw audit payloads stay hidden. Labels explicitly state newest-first and
previous permitted version; never infer sender from editor.

Source/MySQL regression checks precede delivery. Authenticated visual/keyboard
inspection and UAT remain pending per the developer's implementation-first
instruction. This surface does not change template/institutional identity.
