# Changelog

## 1.0.0 — 2026-07-10

- Initial release.
- One planned start+end time block per task, stored as task metadata
  (`timeblock_start` / `timeblock_end`) — distinct from `date_due` and
  `date_started`.
- Task-page panel to set / edit / clear the block (write-ACL + CSRF gated).
- Board-card badge showing the block start.
- Contributes `timeblock-<id>` events to CalendarPlugin's week/day views via
  the shared `calendarEventSources` hook (recommends CalendarPlugin >= 1.2.0).
