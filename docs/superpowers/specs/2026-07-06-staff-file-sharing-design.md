# Staff Panel + Cross-Role File Sharing — Design

## Problem

Admin and teachers have no way to share files with staff, and there is no staff panel at all today — no `staff/` directory exists. The legacy `employee/` portal (a separate login system backed by an `employees` table) is broken: that table doesn't exist in the live database, so both employee login and "Add Employee" currently error out. Meanwhile the modern `users`/`roles` system already has a working `Staff` role with one real active account (`STF-67767`, "usaama").

## Goals

- A new, minimal staff panel built on the existing `users`/`roles` auth system (Staff role), not the broken `employee/` system.
- A file-sharing feature usable by admin, teacher, and staff — any of the three can send a file to one or more specific individuals among those three roles.
- Sent and received files are both visible to their respective owners.

## Non-goals

- Students are excluded entirely — not senders, not recipients, no student-facing UI touched.
- The legacy `employee/`/`employees` system is left untouched (not fixed, not migrated, not used).
- No role-wide broadcast sending (e.g., "send to all Staff") — every share targets specific named individuals.
- No read/unread tracking or notifications.
- No staff-side notices/announcements page, no editable profile beyond the basics — panel stays minimal (login, dashboard, file-sharing, profile, logout).
- Recipients cannot delete/hide their own copy independently — only the original sender can delete a shared file, which removes it for everyone it was shared with.

## Data model

Two new tables. Because admin identity lives in a completely separate `admin` table (id, username, password, full_name — no `role_id`, unlike teacher/staff which are both rows in `users` distinguished by `role_id`), both tables use a polymorphic `type`+`ref_id` pair instead of a single foreign key, so a sender or recipient of either kind can be recorded without a broken reference.

### `shared_files`

| column | type | notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| title | VARCHAR(255) | |
| file_name | VARCHAR(255) | original uploaded filename, for display |
| file_path | VARCHAR(255) | e.g. `uploads/shared_files/sf_<uniqid>.<ext>` |
| sender_type | ENUM('admin','user') | 'admin' → the single row in `admin`; 'user' → a row in `users` (teacher or staff, distinguished by that row's `role_id`) |
| sender_ref_id | INT | `admin.id` or `users.id` depending on `sender_type` |
| uploaded_at | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | |

### `shared_file_recipients`

| column | type | notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| shared_file_id | INT | references `shared_files.id` |
| recipient_type | ENUM('admin','user') | same polymorphic convention as sender |
| recipient_ref_id | INT | `admin.id` or `users.id` |

One row per recipient — a file shared with 3 people produces 3 rows here, all pointing at the same `shared_files.id`.

Both tables created via the existing auto-patcher convention (`CREATE TABLE IF NOT EXISTS` in a `try`/`catch` block), matching every other feature in this codebase.

## Staff panel (new `staff/` directory)

Mirrors the minimal shape of `teacher/`/`student/`, following identical session/auth conventions:

- `staff/login.php` — authenticates against `users` where `role_id` = the Staff role's id and `user_id_string`/password match (same pattern as `student/login.php`/teacher login). Sets `$_SESSION['staff_logged_in']`, `$_SESSION['user_db_id']`, `$_SESSION['staff_id']` (the `user_id_string`), `$_SESSION['staff_name']`.
- `staff/logout.php` — destroys session, matches `teacher/logout.php`.
- `staff/dashboard.php` — minimal welcome page: staff member's name and a simple stat showing their total count of received shared files.
- `staff/profile.php` — read-only display of the staff member's own `users`/`teacher_details`-equivalent info (staff doesn't have a dedicated details table today — just show `users` columns: full_name, email, status).
- `staff/sidebar.php`, `staff/header.php` — copied/adapted from `teacher/sidebar.php`/`teacher/header.php` structure, with only Dashboard, Shared Files, Profile, Logout links.
- `staff/shared-files.php` — the file-sharing page itself (see below).

## Shared-files page (added to admin, teacher, staff)

Same UI/logic pattern, one page per role root (`shared-files.php` at the admin root, `teacher/shared-files.php`, `staff/shared-files.php`) — matching this codebase's existing convention of triplicating similar functionality per role directory rather than sharing one file across roots.

**Upload form:** title, file picker, recipient checkboxes. File validation reuses the exact convention already established in `teacher/study-materials.php`: 100MB max size, allowed extensions `pdf, doc, docx, xls, xlsx, txt, ppt, pptx, jpg, jpeg, png, mp4, webm, avi, mkv`, stored at `uploads/shared_files/sf_<uniqid>_<time>.<ext>`.

**Recipient picker:** a checkbox list built from a combined query — one query for `admin` (the single row) unioned with one query for `users WHERE role_id IN (Teacher, Staff)` — excluding whichever account is currently logged in. Each checkbox value is `type|ref_id` (e.g. `admin|1` or `user|184`), following this codebase's established pipe-joined combo-value convention.

**Two views on the same page:**
- **Received** — join `shared_file_recipients` (matching the current user's `type`+`ref_id`) to `shared_files`, showing title, sender's display name (resolved via a small helper that looks up `admin.full_name` or `users.full_name` depending on `sender_type`), upload date, and a download link.
- **Sent** — `shared_files` where `sender_type`+`sender_ref_id` matches the current user, showing title, a comma-joined list of recipient display names, upload date, download link, and a delete button (sender-only).

**Delete:** removes the `shared_files` row, its `shared_file_recipients` rows, and the physical file — mirrors `study-materials.php`'s `delete_material` handler exactly (look up `file_path`, `unlink()` if it exists, then delete DB rows).

## Testing plan

No automated test framework exists — verification is the same manual curl+mysql pattern used throughout this project. Specifically:

- Admin uploads a file addressed to a specific teacher and the real staff account; confirm both appear in their respective "Received" views and the file is downloadable, and it appears in admin's own "Sent" view with both recipient names listed.
- Staff account uploads a file addressed to admin; confirm it appears in admin's "Received" view (exercising the `sender_type='user'` / `recipient_type='admin'` polymorphic path in both directions).
- Teacher deletes a file they sent; confirm it disappears from both their own "Sent" view and the recipient's "Received" view, and the physical file is removed from disk.
- Confirm a user who is NOT a recipient of a given file cannot see it in their "Received" view (basic access-scoping check, analogous to the cross-section leak checks done for earlier features in this project).
- Confirm the staff login/dashboard/profile/logout flow works end-to-end using the real `STF-67767` account.
