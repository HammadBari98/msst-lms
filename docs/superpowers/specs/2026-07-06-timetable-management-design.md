# Timetable Management — Design

## Problem

Admin has no way to build or edit a real class timetable. The teacher and student panels currently show `timetable.php` pages, but both are static placeholder mockups with hardcoded dummy data — no real schedule exists anywhere in the system.

## Goals

- Admin can define the school's shared period structure (periods, break/lunch slots, active school days).
- Admin can build/edit a weekly timetable for any class+section: for each (day, period) cell, assign a subject + teacher (+ optional room).
- Teacher can view their own personal weekly schedule (read-only), assembled from every class+section they're timetabled into.
- Student can view their own class+section's weekly timetable (read-only).

## Non-goals

- No auto-generation / conflict-avoidance algorithm — admin fills every cell manually.
- No per-class custom period times — one shared period structure for the whole school.
- No timetable versioning/history — one always-current timetable per class+section, edits overwrite in place.
- No room double-booking checks — room is a free-text convenience field only.
- No hard-blocking of teacher double-booking — conflicts are warned about, not prevented.

## Data model

### `timetable_periods` (new table)
Shared, school-wide period structure. One row per period.

| column | type | notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| period_number | INT | display order |
| start_time | TIME | |
| end_time | TIME | |
| is_break | TINYINT(1) DEFAULT 0 | true for lunch/break rows |
| label | VARCHAR(100) | e.g. "Period 3" or "Lunch Break" |

### `school_days` (new table)
Which weekdays are active, admin-configurable.

| column | type | notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| day_name | VARCHAR(20) | e.g. "Monday" |
| day_order | INT | display order (1=Monday..7=Sunday, or admin-defined sequence) |
| is_active | TINYINT(1) DEFAULT 1 | whether this day currently has classes |

### `timetable_entries` (new table)
The actual grid content. One row per (class_id, section_id, day_name, period_id) that has been filled in. Empty cells simply have no row.

| column | type | notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| class_id | INT | FK-ish to `classes.id` |
| section_id | INT NULL | FK-ish to `sections.id`; NULL-safe comparisons follow existing `IFNULL(x,0)` convention used elsewhere in this codebase |
| day_name | VARCHAR(20) | matches a `school_days.day_name` |
| period_id | INT | FK-ish to `timetable_periods.id` |
| subject_id | INT | FK-ish to `subjects.id` |
| teacher_user_id | INT | FK-ish to `users.id` |
| room | VARCHAR(100) NULL | free text, optional |
| UNIQUE KEY | (class_id, section_id, day_name, period_id) | one entry per cell; `ON DUPLICATE KEY UPDATE` on save |

All three tables are created via the existing auto-patcher convention (`CREATE TABLE IF NOT EXISTS` in a `try { $pdo->exec(...) } catch (PDOException $e) {}` block at the top of the admin page), consistent with every other table added elsewhere in this codebase.

## Admin page: `timetable-management.php` (new)

Two panels:

1. **Period Settings** (top of page, collapsible): a small table listing periods in order, each editable inline (period number, start/end time, is_break toggle, label), plus add/delete row. A separate small "School Days" widget lists all 7 weekdays with an active/inactive toggle. Both save via simple POST actions (`save_period`, `delete_period`, `toggle_day`) mirroring the CRUD pattern already used in `class-management.php`.

2. **Timetable Grid**: a class+section picker (same nested combo pattern — `class_id|section_id` — used in `teacher-management.php`/`manage-users.php`). Selecting a class+section loads a grid: rows = periods (in `period_number` order), columns = active school days (in `day_order`). Break rows render as a single merged, non-editable label cell spanning all day columns. Regular cells are editable: on click/inline form, show a subject `<select>` (populated from `program_subjects` for that section) and a teacher `<select>` (populated from `teacher_class_assignments` filtered to that class+section+subject), plus a room text input.

Save flow (`save_entry` POST action):
- Upsert into `timetable_entries` via `INSERT ... ON DUPLICATE KEY UPDATE` keyed on (class_id, section_id, day_name, period_id).
- Before saving, query `timetable_entries` for any other row with the same `teacher_user_id`, `day_name`, `period_id` but a different (class_id, section_id). If found, return a warning message (not a hard failure) naming the conflicting class/section, and still perform the save.
- A `delete_entry` action clears a single cell (removes the row).

## Teacher page: `teacher/timetable.php` (replace dummy)

Resolve teacher's `user_id` the same way other teacher pages do (`user_db_id` session / `teacher_id` string lookup, same helper pattern already used in `attendance-management.php` etc.).

Query: `SELECT te.*, c.class_name, s.section_name, sub.subject_name, tp.period_number, tp.start_time, tp.end_time FROM timetable_entries te JOIN classes c ... JOIN timetable_periods tp ... WHERE te.teacher_user_id = ? ORDER BY day_order, tp.period_number`, grouped by day for the weekly view (same day-tabs UI shape the old dummy page already had). Read-only — no forms.

## Student page: `student/timetable.php` (replace dummy)

Resolve the student's `class_id`/`section_id` from `student_details` (same pattern as `student/study-materials.php`). Query `timetable_entries` filtered to that class_id + `IFNULL(section_id,0) = IFNULL(?,0)`, joined to periods/subjects/teacher name, grouped by day. Read-only.

## Sidebar changes

- `student/sidebar.php`: un-comment the existing `timetable.php` link (currently commented out at line 209).
- `teacher/sidebar.php`: un-comment the existing `timetable.php` link (currently commented out at line 197, grouped with `performance-reports.php` — only the timetable link needs uncommenting, performance-reports stays commented since it's out of scope).

## Testing plan

Since this codebase has no automated test framework, verification follows the same manual pattern used throughout this project: PHP built-in server / XAMPP Apache + curl + direct MySQL queries against real `msst_db` data, using disposable test records that get cleaned up afterward. Specifically:
- Admin: create periods + school days, verify via DB query.
- Admin: fill a timetable cell for a real class+section+subject+teacher combo, verify row in `timetable_entries`.
- Admin: trigger the double-booking warning path (same teacher, same day+period, two different sections) and confirm the warning appears but the save still succeeds.
- Teacher: confirm their page shows exactly the cells where they're the assigned teacher, across multiple classes.
- Student: confirm their page shows exactly their own class+section's grid, and does not leak another section's entries (mirrors the cross-section leak class of bug found earlier in this project).
