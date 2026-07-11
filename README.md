# TimeBlock

Give a Kanboard task one planned start+end time block — a scheduling window
distinct from the task's `date_due` (deadline) and `date_started` (actual
start timestamp). A time block is *when you plan to work on it*, not *when
it's due* or *when you actually started*.

## What it does

- **Task-page panel** — an accordion section on the task view ("Time block")
  showing the current block (if any) and a form to set/edit it: a date plus
  start and end time. A separate Clear button removes the block. Both actions
  are write-ACL and CSRF gated.
- **Board card badge** — a small 🗓 chip on any board card that has a block
  set, showing a compact label (e.g. "Tue 14:00"). Hovering shows the full
  range (e.g. "Tue 14 Jul, 14:00–16:00").
- **Calendar integration (optional)** — contributes `timeblock-<task_id>`
  events to CalendarPlugin's week/day views via the shared
  `calendarEventSources` extension hook, so a task's planned working window
  shows up alongside its due date on the calendar.

Both surfaces read from the same underlying data (`TimeBlockModel::get()`),
so the task page and board badge always agree about whether — and when — a
block is set.

## Surfaces in detail

| Surface | Where | Shows |
|---|---|---|
| Task panel | `template:task:show:before-internal-links` | Current block (or "No time block set"), a set/edit form, and a clear form |
| Board badge | `template:board:private:task:before-title` | 🗓 + compact start label, per card |
| Calendar events | CalendarPlugin's `calendarEventSources` hook | One `timeblock-<task_id>` event per block, in the queried `[start, end)` range |

## Storage

The block is stored as two task metadata keys — no new database table:

- `timeblock_start` — unix timestamp (planned start)
- `timeblock_end` — unix timestamp (planned end), always strictly after start

If either key is absent, the task has no block. Setting a block writes both
keys together; clearing removes both. `TimeBlockModel::set()` rejects (and
does not persist) an end time that isn't strictly later than the start time —
the same rule is enforced server-side by the controller's date/time parser
(`TimeBlockController::parseTimes()`) before the model is ever called.

## Calendar integration

TimeBlock **recommends CalendarPlugin >= 1.2.0** (see `plugin.json`), which
introduced the generic `calendarEventSources` extension point. When
CalendarPlugin is present at that version or later, TimeBlock registers a
closure that CalendarPlugin's query layer calls to fetch time-block events
for the current user, filters (project/assignee/category/column/hide-completed),
and requested date range. Events are:

- namespaced as `timeblock-<task_id>` so they don't collide with other
  sources' event ids,
- range-filtered to `[rangeStart, rangeEnd)`,
- access-filtered to the requesting user's own accessible projects (TimeBlock
  does its own access check — it does not rely on CalendarPlugin to have
  already filtered).

**TimeBlock works standalone without CalendarPlugin.** If CalendarPlugin is
absent or older than 1.2.0, the task-page panel and board badge are fully
functional on their own — you simply won't see time blocks reflected on a
calendar view.

## Install

**Option A — manual:**

1. Download/copy the `TimeBlock` directory.
2. Unzip/place it into your Kanboard installation's `plugins/` directory, so
   the path is `plugins/TimeBlock/Plugin.php`.
3. Reload Kanboard — no configuration step is required.

**Option B — via ModMenu:** install from the plugin directory listing if
you're running the ModMenu plugin manager.

## Requirements

- Kanboard >= 1.2.47
- PHP >= 8.4
- **Calendar events require CalendarPlugin >= 1.2.0** (the
  `calendarEventSources` extension hook was added in that release). Older or
  missing CalendarPlugin installs still get the full task panel and board
  badge — only the calendar view is affected.

## Non-goals

This release intentionally does not support:

- Multiple time blocks per task (one block, replacing any previous one).
- Overlap warnings or conflict detection between tasks' blocks.
- All-day blocks — a block always has a specific start and end time.
- Dragging or resizing a block directly on the calendar view.
- Recurring blocks.

See `CHANGELOG.md` for release history.
