
---

# iH4x OS — Development Plan
## From Single-User Note App → Team Knowledge Platform

---

## Overview & Architecture Decisions

Before the phases, these are the foundational shifts that apply across everything:

**Storage:** Migrate from JSON files to a single `database.sqlite` file per workspace. SQLite works perfectly with NextCloud sync — it's a single file that syncs like any other. PHP's built-in `PDO_SQLite` handles it with zero server dependencies.

**Stack stays:** PHP, vanilla JS, plain CSS. No frameworks, no build tools, no Node dependency.

**Deployment model:** The project folder sits inside a NextCloud-synced directory. When users are on the local network, they hit the PHP server directly (your PC as host via Apache/Nginx or PHP's built-in server). When remote, NextCloud keeps the SQLite file and all attachments in sync across all instances. This means each remote user's machine can also run its own PHP server pointed at the same synced folder — or a single server is always on and NextCloud keeps it fed.

**Internal links format:** `ih4x://note/{id}` resolved to relative URLs at render time, so links stay valid regardless of deployment URL.

---

## Phase 1 — Foundation Rebuild

*Refactor the existing codebase to be structurally ready for everything that follows. No new user-facing features yet except folders — but the UI/UX gets a full upgrade.*

### 1.1 — Database Migration (JSON → SQLite)

Replace the current JSON file storage with a structured SQLite schema:

```
notes          (id, folder_id, title, content, color, pinned, favorited, archived, deleted_at, created_at, updated_at)
folders        (id, parent_id, name, color, icon, position, created_at, updated_at)
note_versions  (id, note_id, content, snapshot_at)
tags           (id, name)
note_tags      (note_id, tag_id)
attachments    (id, note_id, filename, mime, size, created_at)
settings       (key, value)
```

Write a one-time `migrate.php` script that reads existing JSON and populates SQLite. The `database.sqlite` file lives in a `data/` subfolder. All `api.php` queries switch to PDO with prepared statements.

### 1.2 — Unlimited Nested Folders

The `folders` table uses a `parent_id` self-reference (NULL = root). The sidebar folder tree renders recursively in JS. Capabilities:

- Create, rename, delete, recolor, set emoji icon per folder
- Drag notes into folders; drag folders into other folders (reorder by `position` integer)
- Collapse/expand folder tree nodes, persisted in `localStorage`
- Breadcrumb trail shown above note list when inside a folder
- "Move to folder" option in note card context menu and bulk actions
- Folder counts (notes inside, including subfolder notes, shown as badge)
- Right-click context menu on folder items: Rename, Add subfolder, Change color, Delete

The sidebar layout changes: **Folders** section replaces the flat tag list on the left. Tags move to a panel or filter bar. The tree is indented with connecting lines, collapse arrows, and drag handles.

### 1.3 — UI/UX Overhaul

Current pain points resolved:

**Layout:** Switch to a true 3-column layout — sidebar (folders/nav) | note list | editor — all on one page using a SPA-style panel system instead of separate HTML files. `index.html` and `editor.html` merge into a single `app.html`. URL hash or query params track the active note: `app.html#note/abc123`.

**Sidebar:** Fixed-width resizable sidebar with a splitter handle. Folder tree with icons, colors, drag-and-drop. Collapsible sections for Smart Views (All, Pinned, Favorites, Archive, Trash) and Folders.

**Note list panel:** Header shows current folder/view title + note count. Grid/list toggle. Sort and filter controls. New note button contextually creates inside current folder.

**Editor panel:** Full-height, no navigation needed. Breadcrumb at top showing folder path. All existing toolbar features preserved. Focus mode hides both panels.

**Keyboard shortcuts updated:** `Cmd/Ctrl+\` toggle sidebar, `Cmd/Ctrl+Shift+N` new note in current folder, folder navigation with arrow keys.

**Mobile:** Drawer-based navigation — swipe right to open sidebar, swipe left to go back from editor.

**Animations:** Subtle slide-in for panels, fade for modals, no janky repaints.

### 1.4 — API Restructure

`api.php` becomes a clean front controller routing to action handlers:

```
GET  api.php?r=notes              → list notes (with folder filter)
GET  api.php?r=notes/{id}         → single note
POST api.php?r=notes              → create
PUT  api.php?r=notes/{id}         → update
DEL  api.php?r=notes/{id}         → trash/delete
GET  api.php?r=folders            → full folder tree
POST api.php?r=folders            → create folder
PUT  api.php?r=folders/{id}       → rename/move/recolor
DEL  api.php?r=folders/{id}       → delete folder
POST api.php?r=bulk               → bulk operations
GET  api.php?r=search             → full-text search across all notes
GET  api.php?r=attachments/{id}   → serve attachment file
POST api.php?r=attachments        → upload attachment
```

All responses are JSON. Errors include a `code` and `message` field. CSRF token required for all mutating requests (token stored in session, sent as header `X-CSRF-Token`).

### 1.5 — Internal Links

Introduce `[[Note Title]]` wiki-style linking in the editor. On save, titles resolve to IDs and stored as `ih4x://note/{id}`. At render time, these convert to `app.html#note/{id}`. A link picker (type `[[` to trigger autocomplete dropdown) lets users search and select notes. Broken links (note deleted) shown with a visual indicator.

---

## Phase 2 — Auth, Users, Roles & Permissions

*Add a complete multi-user authentication and authorization system. The same project folder works for a single host serving a local network.*

### 2.1 — Authentication System

A `login.php` page guards all access. Sessions managed by PHP native sessions with `session_regenerate_id()` on login. Passwords hashed with `password_hash(BCRYPT)`.

New SQLite tables:

```
users          (id, username, email, display_name, avatar, password_hash, role_id, is_active, last_seen, created_at)
roles          (id, name, is_system, created_at)
permissions    (id, key, label, group)
role_permissions (role_id, permission_id)
user_permissions (user_id, permission_id, granted)   ← custom overrides per user
sessions       (id, user_id, token, ip, user_agent, expires_at, created_at)
```

**Session token** stored as a cookie AND in the sessions table for server-side invalidation (force logout). The `user_id` is attached to every note, folder, version, and attachment row created.

Login page is RTL/LTR aware, bilingual, and matches the iH4x dark aesthetic. Supports "remember me" (30-day session vs 24-hour).

Password reset via a `reset_tokens` table — no email dependency needed; admin can generate a reset link manually and share it via any channel.

### 2.2 — Role System

**System roles (cannot be deleted):**

| Role | Intent |
|---|---|
| `owner` | Full access to everything including system settings |
| `admin` | Full access except cannot change owner or delete the workspace |
| `editor` | Create, edit, delete own notes; view shared notes |
| `viewer` | Read-only access to shared content |

**Custom roles** can be created by admins. Each custom role starts from a permission template (clone of a system role) and then individual permissions are toggled.

### 2.3 — Permission Map

Permissions are grouped by feature area. Every API action checks the active user's effective permissions (role permissions merged with user-level overrides, with user overrides taking priority):

**Notes**
- `notes.create` — create new notes
- `notes.edit.own` — edit own notes
- `notes.edit.any` — edit any user's notes
- `notes.delete.own` — trash/delete own notes
- `notes.delete.any` — trash/delete any note
- `notes.view.private` — view notes marked private

**Folders**
- `folders.create` — create folders
- `folders.manage` — rename, recolor, move any folder
- `folders.delete` — delete folders

**Sharing & Collaboration** (Phase 3 features, permission keys defined here)
- `share.create` — create share links
- `share.manage` — manage others' share links
- `collab.invite` — invite collaborators to notes/folders

**Admin**
- `users.view` — view user list
- `users.invite` — invite new users
- `users.edit` — change user roles and profile
- `users.deactivate` — deactivate users
- `roles.manage` — create/edit/delete custom roles
- `system.settings` — access system-wide settings

### 2.4 — Admin Panel (`admin.php`)

Accessible only to `owner` and `admin` roles. Sections:

**Users tab:** Table of all users showing avatar, name, role badge, last seen, status (active/inactive). Actions: Edit, Deactivate, Reset password link. "Invite user" button generates a registration token link (no email server needed).

**Roles tab:** List of roles with permission count. Click to expand and see the permission grid. Toggle individual permissions per role. "New custom role" button. Cannot delete system roles.

**User detail modal:** Override individual permissions for a specific user — checkboxes showing inherited (from role) vs explicitly granted/denied, with a clear visual distinction.

**System settings tab:** Workspace name, default language, default role for new users, max upload size, allowed file types.

### 2.5 — HQ Dashboard (`hq.php`)

A Company HQ view accessible to admins, showing:

- Active users widget (who's online / last seen within 15 min)
- Recent activity feed (note created/edited, who, when — pulling from an `activity_log` table)
- Storage usage breakdown
- Quick links to admin panel, all shared notes, all folders

The `activity_log` table: `(id, user_id, action, entity_type, entity_id, meta_json, created_at)`. Every mutating API action writes a log entry.

### 2.6 — Profile & Settings

Each user gets a `/profile.php` page (or in-app modal panel) to: change display name, avatar (upload or initials-based auto-avatar), change password, set preferred language, set preferred timezone for timestamps.

---

## Phase 3 — Team Collaboration, Sharing & Live Updates

*The app becomes a real-time collaborative workspace. This phase also hardens the NextCloud sync story and adds the internal link sharing system.*

### 3.1 — Note & Folder Sharing

**Share with specific users:** On any note or folder, an owner/editor can open a "Share" panel listing workspace users. Assign per-collaborator access: Can view / Can edit. Stored in:

```
note_shares   (note_id, user_id, access_level, created_by, created_at)
folder_shares (folder_id, user_id, access_level, created_by, created_at)
```

Shared folders grant the same access level to all notes inside (inherited, can be overridden per note).

**Internal share links:** A "Copy link" button generates an `ih4x://note/{id}` link. Inside the app these route directly. Optionally generate a `share_tokens` entry that lets someone open a note in their browser without being a workspace member (view-only public share, toggleable per note).

**Shared with me view:** New sidebar section showing all notes/folders explicitly shared with the current user by others.

### 3.2 — Live Collaboration & Real-Time Updates

PHP doesn't have native WebSockets, so use **Server-Sent Events (SSE)** for push from server to clients — zero dependencies, works over HTTP, compatible with NextCloud proxy setups.

**Architecture:**

`stream.php` — an SSE endpoint the client connects to on login. Keeps the connection alive with periodic heartbeats. When any change happens, a `changes` table entry is written with `(id, type, entity_id, user_id, created_at)`. The SSE stream polls this table every 1.5 seconds and pushes any events newer than the last seen ID to the connected client.

```
changes  (id, type, entity_type, entity_id, actor_id, payload_json, created_at)
```

Event types pushed: `note.updated`, `note.created`, `note.deleted`, `folder.updated`, `user.online`, `user.typing`.

**Client-side:** `shared.js` gets an `EventSource` singleton. When `note.updated` arrives for the currently open note, the editor shows a "Updated by [Name]" banner with options to reload or keep editing. The note list auto-refreshes when `note.created` or `note.deleted` arrives.

**Typing indicators:** When a user opens a note for editing, a `POST api.php?r=presence` ping is sent every 10 seconds. Other users viewing the same note see an avatar/name badge in the editor toolbar ("Mohammed is editing"). Presence expires after 15 seconds of no ping.

**Conflict handling:** Last-write-wins by default (simplest for SQLite). Optionally in a future iteration, the version history (already built in Phase 1) captures every save so nothing is ever truly lost.

### 3.3 — NextCloud Integration Hardening

**The sync story works like this:**

1. The `iH4x OS` project folder lives inside the NextCloud synced directory on the host machine
2. `database.sqlite` and the `data/attachments/` folder sync via NextCloud to remote users
3. Remote users can either: (a) point their own local PHP server at the synced folder for offline-capable use, or (b) access the single always-on instance over HTTPS

**SQLite + sync caveat:** Concurrent writes from multiple PHP instances to the same SQLite file cause lock contention. Solve this with a `config.php` setting `SINGLE_INSTANCE_MODE` that, when true (for local-network shared server), uses normal SQLite. When false (each user has their own synced copy), writes go to a local queue file, and a lightweight `sync_merge.php` script (triggered by NextCloud's post-sync hook or a cron) merges changes using the `changes` log as the conflict-resolution source. This is the offline-first pattern.

**`config.php`:**
```php
define('DB_PATH',           __DIR__ . '/data/database.sqlite');
define('SINGLE_INSTANCE',   true);   // true = one server, LAN mode
define('APP_URL',           'http://192.168.1.10/ih4x');
define('SESSION_LIFETIME',  86400);
define('MAX_UPLOAD_MB',     20);
```

A `setup.php` wizard runs on first access if no DB exists: sets workspace name, creates the owner account, detects single vs multi-instance mode.

### 3.4 — UI/UX Additions for Phase 3

**Collaboration indicators in note list:** Avatar stacks on note cards showing who's currently viewing/editing. Color-coded border pulse on actively-edited notes.

**Shared folder view:** Folder tree shows a person-icon badge on shared folders. Hovering shows who it's shared with.

**Activity sidebar panel:** A collapsible "Activity" panel on the right side of the editor showing the note's full activity log: created by, all edits with user + timestamp, version restore events. Pulls from `activity_log`.

**Mention system:** Type `@username` in note content to mention a team member. Mentions stored in a `mentions` table and surfaced in a "Mentions" view in the sidebar. No email — purely in-app.

**Internal link backlinks:** At the bottom of every note, a "Linked from" section lists all notes that link to this one (reverse lookup from `note_links` table populated on save).

### 3.5 — Export & Backup

**Per-note export:** Markdown, HTML, plain text (already exists), plus PDF via browser print stylesheet.

**Workspace backup:** Admin can trigger `export.php?format=zip` which produces a ZIP containing: `database.sqlite`, all attachments, and a full Markdown export of every note organized in folders. This ZIP is the portable backup that also works as a NextCloud manual sync artifact.

---

## File Structure (Final)

```
ih4x-os/
├── app.html              ← merged SPA (was index.html + editor.html)
├── login.php
├── admin.php
├── hq.php
├── profile.php
├── setup.php             ← first-run wizard
├── api.php               ← front controller
├── stream.php            ← SSE endpoint
├── export.php
├── migrate.php           ← one-time JSON→SQLite migrator
├── config.php
├── config.sample.php
│
├── lib/
│   ├── db.php            ← PDO singleton + query helpers
│   ├── auth.php          ← session, login, permission check
│   ├── router.php        ← API route dispatcher
│   └── actions/
│       ├── notes.php
│       ├── folders.php
│       ├── users.php
│       ├── sharing.php
│       ├── bulk.php
│       └── attachments.php
│
├── assets/
│   ├── app.js            ← main SPA logic (was index page script)
│   ├── editor.js         ← editor logic
│   ├── shared.js         ← utilities, i18n, SSE client
│   ├── admin.js
│   └── style.css         ← single stylesheet
│
└── data/
    ├── database.sqlite   ← syncs via NextCloud
    └── attachments/      ← syncs via NextCloud
```

---

## Summary

| Phase | Core Outcome |
|---|---|
| **Phase 1** | SQLite DB, unlimited nested folders, SPA layout, cleaner API, internal links |
| **Phase 2** | Login/auth, roles, custom permissions, user management, HQ dashboard, activity log |
| **Phase 3** | SSE live updates, team sharing, typing presence, mentions, backlinks, NextCloud sync hardening, workspace backup |

