# Teacher–Student Messaging — Design

## Problem

Teachers and students have no way to message each other directly inside the LMS today. Admin also has no visibility into any such communication. This adds a lightweight, class-scoped direct-messaging module, plus a read-only admin oversight view.

## Goals

- A teacher can message any student in a class/section they are assigned to teach, and vice versa.
- Admin can browse and read every teacher↔student conversation (oversight, not participation).
- New messages appear without a manual page reload while a conversation is open.
- Unread messages show as a badge count in the sidebar for teachers and students.

## Non-goals

- No group chats — every conversation is exactly one teacher and one student.
- No file/image attachments in this version — text only.
- No admin send/delete/moderation — admin's view is read-only.
- No cross-class messaging — a teacher cannot message a student outside their assigned classes, and a student cannot message a teacher who doesn't teach their class.
- No push notifications, email, or global cross-page live badge updates — the sidebar badge count is computed on page load like the rest of this app; live updates happen only within an open conversation via polling.

## Data model

One new table. A conversation is simply the unique pair `(teacher_id, student_id)` — no separate conversations table is needed.

### `messages`

| column | type | notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| teacher_id | INT | `users.id` of the teacher in this conversation |
| student_id | INT | `users.id` of the student in this conversation |
| sender_role | ENUM('teacher','student') | who sent this particular message |
| body | TEXT | message content |
| is_read | TINYINT DEFAULT 0 | set to 1 when the recipient views it |
| created_at | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | |

Index on `(teacher_id, student_id, created_at)` for fast thread loading.

Created via this codebase's standard auto-patcher convention (`CREATE TABLE IF NOT EXISTS` in a `try`/`catch` block at the top of the relevant pages), matching every other feature here.

## Authorization scoping

Reuses the exact class-assignment join already established in `award-list.php`/`teacher/award-list.php` and `student-report.php`:

- **Teacher's contact list** = active students where `student_details.class_id`/`section_id` matches a row in `teacher_class_assignments` for that teacher.
- **Student's contact list** = distinct teachers where `teacher_class_assignments.class_id`/`section_id` matches the student's own `student_details.class_id`/`section_id`.

Every send request re-validates the pair against this same join server-side before inserting — a client cannot message outside this scope even by crafting a direct request.

## Pages

- **`teacher/messages.php`** — two-pane layout: left = list of contactable students grouped by class, each with last-message preview and unread badge; right = the open thread with a send box. Reuses the sidebar/header include pattern already used by `teacher/award-list.php`.
- **`student/messages.php`** — mirrored layout: left = list of contactable teachers, right = open thread.
- **`messages.php`** (admin, root) — left = list of every teacher↔student pair that has at least one message (searchable by name), right = read-only thread view. No send box.
- **`ajax/send_message.php`** — validates the sender's session (`teacher_logged_in` or `student_logged_in`), re-derives the sender's own user id from session (never trusts client-supplied ids), validates the pair is an authorized class relationship, inserts the row.
- **`ajax/poll_messages.php`** — given a `teacher_id`+`student_id` pair and `after_id`, returns any new rows; when the caller is the recipient of returned rows, marks them `is_read = 1` in the same call. Used both for the ~5s auto-refresh while a thread is open and for the initial thread load.

## Sidebar unread badge

Each role's sidebar (`teacher/sidebar.php`, `student/sidebar.php`) computes an unread count on page load: `SELECT COUNT(*) FROM messages WHERE <own id column> = ? AND sender_role != <own role> AND is_read = 0`, displayed as a small badge next to the "Messages" link — same visual pattern as other badge counts already in this app (e.g. award/status badges).

## Testing plan

No automated test framework exists in this project — verification follows the same manual curl+mysql pattern used for prior features:

- A teacher sends a message to a student in one of their assigned classes; confirm it appears in the student's contact thread and increments the student's unread badge.
- The student replies; confirm it appears for the teacher and is marked read once the teacher opens/polls the thread.
- Attempt to send a message between a teacher and a student who are NOT in an assigned-class relationship (direct POST to `ajax/send_message.php`); confirm it's rejected server-side.
- Admin opens `messages.php`, confirms the conversation above is listed and its full message history is visible read-only (no send box rendered/accepted).
- Confirm an unauthenticated request to either ajax endpoint is rejected.
