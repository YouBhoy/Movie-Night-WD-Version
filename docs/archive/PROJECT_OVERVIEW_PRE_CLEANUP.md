# 🎬 WD Movie Night — Project Overview (Movie-Night-WD-Version)

> **Implementation update — October 7, 2026:** The sections below describe the original baseline and include findings that have since been fixed. Booking creation and cancellation now live in services/BookingService.php behind BookingRepository, with services/MysqlBookingRepository.php providing the current MySQL implementation. Admin write guards and CSRF handling were corrected; public registration lists require admin authentication; layout replacement and destructive hall/shift changes reject active bookings. See tests/README.md for the regression suite.
>
> **Behavior changes:** Admin “delete registration” actions now cancel and retain history while releasing seats. Occupied status is controlled by bookings. Public seat-map reads no longer create layouts; an administrator must configure seats first. Layout replacement requires no active registrations or occupied seats. Viewer accounts cannot mutate data. Legacy admin login redirects to admin-login.php.
>
> **Validation:** 15 booking-service checks, 15 endpoint security checks, and 15 integration checks passed against an isolated MariaDB instance with synthetic data, including concurrent requests. PHP lint and syntax checks of modified inline JavaScript passed. Production deployment and visual browser verification have not been performed.
>
> **Seat selection update:** Recommendations now evaluate the full attendee count and preserve existing choices where possible. Partial selections that can become a complete contiguous group without orphan seats are allowed without premature warnings. Warnings identify stranded seats or separated groups, remember accepted problems, and offer a complete recommended group. Cancelling restores the preceding selection. Availability is refreshed immediately before booking, while the server transaction remains the final reservation authority. Recorded missing positions are treated as boundaries; explicit physical aisle metadata is not yet part of the current MySQL layout editor. The Node regression suite passes 28 checks.
>
> **Next Firebase work:** Design explicit event/screening IDs and a deterministic active-booking key; implement a Firestore repository and transaction retries; extract remaining employee, settings, and layout persistence. The current database still represents one event, so uniqueness is one active booking per employee. Shared rate limiting, session expiry/revocation, employee identity verification, and deployment restrictions for logs/dumps remain separate hardening tasks. Firebase is not connected yet.
>
> **Configuration:** DB_HOST, DB_PORT, DB_NAME, DB_USER, and DB_PASS may be supplied through environment variables. Existing local defaults remain compatible with XAMPP. Browser debug errors are disabled unless APP_DEBUG=1. APP_LOG_PATH overrides the default log destination. Upload callers must send admin_csrf_token.


> An internal company **cinema / movie-night booking system** built with **plain PHP 8.2 + MySQL (MariaDB)** on a classic **XAMPP / Apache** stack. Employees register for a movie screening using their employee number; the system auto-assigns their work shift (which maps to a cinema hall), lets them pick seats on an interactive seat map, and stores the booking. A separate admin console manages halls, shifts, seat layouts, employees, event settings, admin accounts, and CSV exports.

This document is generated from a full read of the codebase in `c:\xampp\htdocs\Use this for the Movie Night\Movie-Night-WD-Version`.

---

## 1. Tech stack & runtime environment

| Layer | Technology |
|---|---|
| Language | PHP 8.2 (XAMPP), Apache SAPI (`header()`, `http_response_code()`) |
| Database | MySQL/MariaDB 10.4 (`movie_night_db`) accessed via **PDO** with prepared statements (`ATTR_EMULATE_PREPARES = false`) |
| DB tooling | Stored procedures + JSON columns (`selected_seats`, `details`) |
| Front-end | Vanilla JS + inline CSS (no build step), Font Awesome, Google Fonts |
| Timezone | `Asia/Singapore` (config.php) |
| Sessions | Server-side PHP sessions, HttpOnly + SameSite=Strict, CSRF tokens |
| Auth | bcrypt (`password_hash` / `password_verify`) against `admin_users` table |
| Asset storage | Local files (`uploads/` logo); CSS in 3 files: `styles.css`, `manage-halls.css`, `seat-layout.css` |

**Application settings (config.php):** `MAX_ATTENDEES_PER_BOOKING = 3`, `CSRF_TOKEN_EXPIRY = 3600s`, `MAX_LOGIN_ATTEMPTS = 5`, `LOGIN_LOCKOUT_TIME = 900s`, `RATE_LIMIT_REQUESTS = 30/60s`, `APP_NAME = "WD Movie Night"`, `APP_VERSION = 2.0.0`.

---

## 2. End-to-end behaviour

### 2.1 Public booking flow (employee side)

1. **index.php** (landing + registration) reads active halls & shifts and public `event_settings`; renders the hero (movie name, date, time, location) and the registration form. If `registration_enabled` is false, the CTA is replaced with "Registration Closed".
2. The employee types an **employee number** → JS calls `api.php?action=check_employee` → the server looks up `employees` (active) and returns `full_name` and the employee's `shift_name`. The name field (readonly) and the shift dropdown are auto-filled, and the hall is derived from `shifts.hall_id`.
3. JS calls `api.php?action=get_seats` (hall_id, shift_id). If the hall/shift has **no seats yet**, PHP runs `CALL createSeatsForHallShift(...)` which inserts the canned layout. Seats are rendered as a row/column grid.
4. The employee picks `1..N` attendees (N = `event_settings.max_attendees`, default 3) and clicks seats. The JS enforces adjacency (suggests neighbouring seats), warns on single-seat gaps, and requires confirmation for non-adjacent groups.
5. Submit → `api.php?action=register`. The server re-validates everything (**server-side, ignoring the client-sent hall/shift**):
   - employee number ≥ 2 chars, name matches the employee record (case-insensitive),
   - registration enabled, employee active AND exists, no existing active registration (checked twice),
   - hall + shift combination valid and active,
   - `attendee_count` in `1..max_attendees`,
   - exactly N `selected_seats`, and all seats still `available`.
   Inside a **PDO transaction** it marks each seat `occupied` (guarding `rowCount()` per seat) and inserts a row into `registrations` (stores `selected_seats` as JSON, plus `movie_name`, `screening_time`, IP and user agent). On success it writes booking data to `$_SESSION` and returns a redirect.
6. **confirmation.php** prints the booking summary and immediately clears the session data to prevent refresh re-display. Page is printable.
7. Employees who forget their seats can use **find-registration.php** (employee number lookup, rate-limited).

> Design note: the employee's hall/shift is **always taken from the employee record**, not from the form, so employees cannot self-assign to another hall or shift.

### 2.2 Admin panel flow

1. **admin-login.php** – username + password, CSRF-token protected, rate-limited (max 5 failures / 15 min with extra delays), bcrypt verification against `admin_users`, session ID regenerated on success, success/failure logged to `login_attempts` and `security_audit_log`. Redirects to **admin-dashboard.php**.
   *(Legacy duplicate: `admin.php` also contains its own inline login + logout via `?logout=1`.)*
2. **admin-dashboard.php** – the modern home page:
   - Stats: total active registrations, total attendees, halls used, per-hall registration counts.
   - Recent 50 active registrations with client-side search (debounced, Ctrl+K) and term highlighting.
   - POST actions: **delete registration** (frees seats in a transaction, then deletes) and **update event settings** (movie_name/date/time/location) — both CSRF-protected.
   - Links onward: manage-halls.php, seat-layout-editor.php, export.php, admin.php tabs.
3. **admin.php** – the large legacy control panel with tabs:
   - **Event Settings** – save individual `event_settings` keys (AJAX, admin CSRF + rate-limited).
   - **Employee Settings** – list employees with shift + "has active registration" badge; add / edit / delete; **deactivate** an employee also cancels their active registration and frees seats (`CALL freeSeatsByRegistration(@id)`); hard-delete requires the employee be deactivated first.
   - **Export** – links to `export.php?type={registrations|attendees|seats|employees}`.
   - **Admin Users** (admin role only) – add / edit / delete admin accounts (bcrypt), self-delete prevented.
   - Legacy disabled tabs: dashboard, registrations, seats.
4. **manage-halls.php** – modern hall & shift management: active/deactivated tabs, add / rename / seat-count update, deactivate / restore / hard delete for halls and shifts. All actions go through **admin-hall-shift-api.php**.
5. **seat-layout-editor.php** – visual grid editor for one hall+shift combo: render seats, click a seat to cycle `available → occupied → blocked → reserved`, add/delete seats, add rows/columns, bulk **Save Layout** (delete + re-insert all seats in a transaction), plus mini-dialogs to add/deactivate halls and shifts via admin-hall-shift-api.php.
6. **export.php** – CSV downloads with UTF-8 BOM: registrations (or attendees, employees, seats).
7. **upload-handler.php** – admin logo upload (`event_settings.site_logo`), admin-auth only.

---

## 3. Page / module inventory

| File | Responsibility | Auth |
|---|---|---|
| `index.php` | Public landing + registration flow, seat map UI | public (CSRF on POST) |
| `api.php` | Public JSON API: `get_seats`, `register`, `check_employee`, `get_registrations`, `search_registrations`, `get_smart_suggestions` | public (rate-limited + CSRF on POST) |
| `confirmation.php` | Booking confirmation screen (session-guarded, one-shot) | session flag |
| `find-registration.php` | Public "find my seats" lookup by employee number | public (rate-limited) |
| `admin-login.php` | Admin login (CSRF, rate-limit, bcrypt) | n/a |
| `admin.php` | Legacy admin panel: settings / employees / admin users / export links | admin session |
| `admin-dashboard.php` | Modern dashboard: stats, recent registrations, delete reg, update settings | admin session |
| `admin-api.php` | Admin JSON endpoints: `get_registrations` (paged+searchable), `get_seat_layout`, `get_event_settings`, `get_employees`, `add_employee`, `get_statistics` | admin session + admin CSRF (POST) |
| `admin-hall-shift-api.php` | Hall/shift CRUD endpoints (add/update/deactivate/restore/delete) and `get_active_halls`, `get_shifts_by_hall` | **auth commented out!** (CSRF still required on writes) |
| `manage-halls.php` | Hall & shift management UI | admin session |
| `edit-halls-shifts.php` | Older duplicate of manage-halls.php (CSRF field mismatch — save buttons broken) | admin session |
| `seat-layout-editor.php` | Seat layout grid editor + hall/shift mini-management | admin session, **but `save_layout` runs before auth** |
| `export.php` | CSV exporters | admin session (no CSRF) |
| `upload-handler.php` | Logo image upload | admin session (no CSRF) |
| `logout.php` | Admin logout, `session_destroy()`, redirect to login | session |
| `about.php` | **Demo/stub** dashboard with hardcoded stats (checks obsolete `$_SESSION['admin']`) | legacy key |
| `test-employee-deactivation.php` | **Leftover test harness** for the seat-freeing feature (no auth guard) | none |
| `config.php` | DB connection (PDO singleton), sessions, CSRF, rate limiting, auth helpers, logging helpers, error/exception/shutdown handlers, security headers | n/a |
| `styles.css`, `manage-halls.css`, `seat-layout.css` | Stylesheets for public page / hall management / seat editor | n/a |
| `movie_night_db_current.sql` | Full database dump (tables, SPs, indexes, data) | n/a |
| `ADMIN_PANEL_GUIDE.md` | Human-guide to the admin panel | n/a |
---

## 4. Database schema (`movie_night_db_current.sql`, MariaDB 10.4)

### 4.1 Tables

| Table | Columns | Notes |
|---|---|---|
| `cinema_halls` | `id`, `hall_name`, `max_attendees_per_booking`, `total_seats`, `is_active`, `created_at`, `updated_at` | Physical halls; soft-delete via `is_active`. Data: ids 1,2,3,7 (3 & 7 inactive). |
| `shifts` | `id`, `hall_id`, `shift_name`, `shift_code`, `seat_prefix`, `seat_count`, `start_time`, `end_time`, `is_active`, `created_at` | One row per screening/crew group. **`id=0`/`hall_id=0` is the "Unassigned" sentinel** (inactive). Data: 12 rows incl. Unassigned, active Normal Shift, Crew C, Crew A/B, plus test shifts. |
| `employees` | `id`, `emp_number` (UNIQUE), `full_name`, `is_active`, `shift_id` (FK→`shifts.id`) | Each employee belongs to one shift. Data: 27 rows. `shift_id` may be NULL. |
| `seats` | `id`, `hall_id`, `shift_id`, `seat_number`, `row_letter`, `seat_position`, `status` enum(`available`,`occupied`,`blocked`,`reserved`), `created_at`, `updated_at` | **UNIQUE (`hall_id`,`shift_id`,`seat_number`)**. One seat per hall+shift combination; an employee's booking only touches the rows of their hall/shift. Data: ~1,950 seats across combos. |
| `registrations` | `id`, `emp_number`, `staff_name`, `email`, `attendee_count`, `hall_id`, `shift_id`, `selected_seats` (JSON array string), `movie_name`, `screening_time`, `registration_date`, `ip_address`, `user_agent`, `status` enum(`active`,`cancelled`,`completed`), `notes`, `created_at`, `updated_at` | A booking. No FK constraints (IDs are held as plain ints and joined in app code). Data: 34 rows. |
| `event_settings` | `id`, `setting_key` (UNIQUE), `setting_value`, `setting_type` enum(`text`,`number`,`boolean`,`json`,`url`,`color`), `description`, `is_public`, `created_at`, `updated_at` | Key/value app config (movie name, screening time, colors, max_attendees, registration_enabled, site_logo …). ~25 keys. |
| `admin_users` | `id`, `username` (UNIQUE), `password_hash` (bcrypt), `role` enum(`admin`,`manager`,`viewer`), `is_active`, `last_login`, `created_at`, `updated_at` | Data: 3 accounts (`admin`, `manager`, `WD-Admin`). |
| `admin_activity_log` | `id`, `admin_user`, `action`, `target_type`, `target_id`, `details` (JSON), `ip_address`, `user_agent`, `created_at` | Audit of admin actions (add/update/deactivate hall & shift, seat layout saves, employee deactivation, delete registration, logout…). ~183 rows. |
| `security_audit_log` | `id`, `event_type`, `user_id`, `ip_address`, `user_agent`, `details`, `risk_level` enum(`low`,`medium`,`high`,`critical`), `created_at` | Login success/failure security events. ~63 rows. |
| `login_attempts` | `id`, `ip_address`, `username`, `success`, `message`, `created_at` | Read/written only by `admin-login.php` (cleanup of rows older than 1h on each visit). |
| `rate_limits` | `id`, `identifier`, `request_count`, `created_at` | **Table exists but the PHP code uses in-session rate limiting** (`checkRateLimit()` in config.php) — this table is not queried by any page today. |

### 4.2 Relationships (as used by the app)

```
employees.shift_id ──▶ shifts.id
shifts.hall_id     ──▶ cinema_halls.id   (0 = "Unassigned" sentinel, no row in cinema_halls)
registrations.hall_id  ──▶ cinema_halls.id
registrations.shift_id ──▶ shifts.id
seats (hall_id, shift_id) ──▶ cinema_halls/shifts  (UNIQUE combo per seat_number)
event_settings / admin_users / logs: standalone
```
There are **no FK constraints** on registrations, seats, or shifts→halls (only `employees.shift_id` has a real FK). Integrity is enforced in application code (queries join "active" only, seat updates guard `rowCount()`).

### 4.3 Stored procedures (defined in the dump)

| Procedure | Purpose |
|---|---|
| `createSeatsForHallShift(hall_id, shift_id, shift_name)` | Deletes existing seats for the combo, then inserts canned layouts: Hall 1 = 11 rows (A–L skipping I) × 6/5 seats depending on shift; Hall 2 = 12 rows (A–M skipping I), left block for "CREW A" shifts, right block for "CREW B". |
| `getSeatsForHallShift(hall_id, shift_id)` | Creates seats if missing, then returns the seat list. |
| `findSmartSeatSuggestions(hall_id, shift_id, preferred_row, count)` | Ranks consecutive-available seat runs by distance from a preferred row (temporary table + window functions). |
| `validateBookingRequest(emp_number, attendee_count, hall_id, shift_id, selected_seats)` | Pre-booking sanity checks. |
| `validateSeatGaps(hall_id, shift_id, selected_seats, attendee_count)` | Detects "orphan seat" gaps and suggests an alternative consecutive block. |
| `reserveSeats(emp_number, staff_name, hall_id, shift_id, attendee_count, selected_seats, ip, ua)` | Transactional insert + seat reservation. |
| `freeSeatsByRegistration(registration_id)` | Transactional seat release + registration → `cancelled`. |

> **Important:** only two procedures are actually called by PHP today — `createSeatsForHallShift` (from `api.php get_seats`) and `freeSeatsByRegistration` (from `admin.php` employee deactivate/delete). The other five are legacy; the current booking flow in `api.php handleRegistration()` re-implements their logic in PHP. Keep this in mind before trusting the SPs as the source of truth.

---

## 5. Authentication & session handling

- **Session bootstrap (config.php):** `session_start()` is called once with
  `session.cookie_httponly=1`, `session.use_only_cookies=1`, `session.cookie_samesite=Strict`,
  and `session.cookie_secure` when HTTPS is on. A global `setSecurityHeaders()` adds CSP, no-sniff, frame-deny, referrer-policy, permissions-policy, and (on HTTPS) HSTS.
- **Admin login (admin-login.php + `adminLogin()` in config.php):**
  1. cleans `login_attempts` older than 1 hour,
  2. counts recent failed attempts for the client IP (lockout at `MAX_LOGIN_ATTEMPTS`), sleeps to slow brute force,
  3. validates the CSRF token,
  4. verifies bcrypt hash against `admin_users` (`is_active = 1` only),
  5. on success: `session_regenerate_id(true)`, sets session keys `admin_logged_in`, `admin_username`, `admin_role` (from DB), `admin_login_time`, updates `last_login`, logs to `login_attempts` + `security_audit_log`, redirects to `admin-dashboard.php`.
- **Session variables used:** `admin_logged_in`, `admin_username`, `admin_role`, `admin_login_time`, plus CSRF tokens `csrf_token` (public) and `admin_csrf_token` (admin).
- **Guards:** admin pages call `isAdminLoggedIn()` / check `$_SESSION['admin_logged_in'] === true` (with `secureAdminSession()` in some flows). `admin.php` restricts Admin-Users management to role `admin`. `about.php` checks an **obsolete** key `$_SESSION['admin']` and is effectively dead.
- **CSRF:** two independent token systems — public (`generateCSRFToken`/`validateCSRFToken`) and admin (`generateAdminCSRFToken`/`validateAdminCSRFToken`), both 1-hour window, `hash_equals` comparison.
- **Logout (logout.php):** logs an `admin_activity_log` 'logout' entry, then `session_destroy()` and redirects to `admin-login.php?logged_out=1`. (Cookie isn't explicitly invalidated; fine on localhost, minor hardening gap.)
- **Session timeout:** `event_settings.admin_session_timeout` exists (default 3600s) **but is never enforced** — an admin session stays valid indefinitely until logout.
- **Rate limiting:** public API (`api.php`) 30 req/min per IP; admin API 10 req/min per action; login 5/15min — all stored **in-session** (`$_SESSION['rate_limit_*']`), so the lockout resets if the attacker clears cookies.

---

## 6. Known issues & suggested improvements

### 6.1 Security (highest priority)

| # | Issue | Where | Fix |
|---|---|---|---|
| S1 | **`admin-hall-shift-api.php` authentication is commented out** — the endpoint answers hall/shift GETs to anyone; write actions are only guarded by the admin CSRF token. | `admin-hall-shift-api.php:6-11` | Re-enable `isAdminLoggedIn()` guard. |
| S2 | **`seat-layout-editor.php` processes `action=save_layout` before the auth check and with no CSRF validation at all** → unauthenticated users can overwrite any hall/shift's seat layout by POSTing `hall_id`, `shift_id`, `seats`. | `seat-layout-editor.php:6-16` | Move the auth+CSRF check before the handler. |
| S3 | Debug logging dumps raw `$_GET`/`$_POST` (including CSRF tokens) into `logs/php_errors.log` on every call. | `admin-hall-shift-api.php:31-33` | Remove; log only action + status. |
| S4 | `display_errors = 1` and the exception handler prints `$exception->getTraceAsString()` to the browser → paths/DB detail leaks. | `config.php:36-38`, `config.php:470-486` | Set `display_errors=0` in production; keep error_log. |
| S5 | `api.php` error responses embed raw `$e->getMessage()` → internal detail leaks to clients. | `api.php:58-64`, `handleRegistration` | Map to generic messages; log details server-side. |
| S6 | `upload-handler.php` trusts the browser-supplied MIME (`$file['type']`) and file extension; no CSRF check. | `upload-handler.php:28-41` | Use `finfo` + whitelist (config already has a `validateFileUpload()` helper!), add CSRF. |
| S7 | Hardcoded secrets / no env config: `ADMIN_KEY`, `ADMIN_EMAIL`, `DB_USER=root`, empty DB password. | `config.php:3-6,32-33` | Move to environment variables / `.env`, least-privilege DB user. |
| S8 | `export.php` and `about.php`+`test-employee-deactivation.php` — export has no CSRF; the two PHP test/demo pages have **no auth guard at all** and expose test output. | `export.php`, `test-employee-deactivation.php`, `about.php` | Add auth (export), delete the leftover/demo files. |

**SQL injection:** overall the code is clean — all user input goes through PDO prepared statements (native prepares) or integer-cast `filter_var`/`(int)`. The only dynamic SQL is `$pdo->query("SET @reg_id = " . intval($reg_id))` (safe) and `LIMIT ? OFFSET ?` with bound ints in `admin-api.php`. Keep using prepared statements; never interpolate `$_GET/$_POST` into SQL.
### 6.2 CSRF token misuse / broken buttons (regressions to verify)

- `edit-halls-shifts.php` sends `csrf_token=` to `admin-hall-shift-api.php`, which only accepts `admin_csrf_token` → **save/add hall & shift buttons always fail** ("Security validation failed"). The page is superseded by `manage-halls.php` and should be retired.
- `seat-layout-editor.php` defines `csrfToken = generateAdminCSRFToken()` but then:
  - `delete_seat` POSTs `admin_csrf_token=…` while the server only reads `csrf_token` → **delete always rejected**;
  - `add_seat` POSTs `csrf_token=<admin token value>` while the server validates it against the *regular* `csrf_token` session key → **add always rejected**;
  - `save_layout` (line 6-16) skips CSRF entirely (see S2).
  The `admin_activity_log` shows these actions worked in earlier builds, so these are likely regressions after the public/admin token split. Unify on one token per endpoint and test each action.

### 6.3 Duplication, dead code & inconsistent behaviour

- **Two admin UIs overlap:** `admin.php` (legacy, huge) vs `admin-dashboard.php` + `manage-halls.php` + `seat-layout-editor.php` (modern). Both allow registration deletion and event-setting edits — pick one, or split admin.php into focused controllers.
- **Delete registration has two behaviours:** `admin-dashboard.php` frees seats in a transaction; `admin.php delete_registration` deletes the row **without freeing seats** (stale `occupied` seats afterwards).
- **Two login flows:** `admin-login.php` (CSRF + rate-limit) and `admin.php` inline login (no CSRF, no rate limiting).
- **Unused endpoints/helpers:** `api.php` `get_registrations`, `search_registrations`, `get_smart_suggestions`; `rate_limits` table; `event_settings` keys `allow_temp_registration`, `venue_name`, `shift_labels`, `max_registrations`, `custom_css`, `background_theme`.
- **`logActivity()`** writes to a non-existent `activity_logs` table (silently falls back to the error log) — while `logSecurityEvent()`/`logAdminActivity()` target tables that do exist.
- **Leftover files:** `test-employee-deactivation.php` (test harness, no guard), `about.php` (demo shell), `edit-halls-shifts.php` (superseded + broken).
- **Sentinel rows:** "Unassigned" hall/shift are `id=0` rows with no matching `cinema_halls` row; `is_active=0` — a data-model smell (nullable FK or a real `unassigned` flag would be cleaner).

### 6.4 Correctness / race conditions

- **Duplicate registration race:** `handleRegistration` does check-then-insert (`isEmployeeRegistered` + a second COUNT) with no unique constraint on `(emp_number, status='active')` — two simultaneous submits can both pass the check. Add a unique index (e.g. a generated column `active_emp` = `IF(status='active', emp_number, NULL)` with a UNIQUE index) or use a lock.
- **`seat-layout-editor` seat data isn't validated on save** (`seat_number`, `row_letter`, `seat_position`, `status` are taken verbatim from POST); a malformed payload can insert junk rows. Validate like `handleAddSeat` does.
- **Gap/adjacency rules are client-side only** — a crafted `selected_seats` payload can bypass them. The legacy SP `validateSeatGaps` (or a PHP equivalent) should run server-side.
- **`get_smart_suggestions`** has a `LIMIT ?` bound directly and is unused; fine, but remove or wire it up.

### 6.5 Code organization & error handling

- Move SQL out of pages into a small data layer (repositories / DAO) — would also make a future Firestore migration (see `FIREBASE_MIGRATION.md`) far easier.
- Extract the ~600 lines of inline CSS/JS in `admin.php` and `index.php` into asset files, matching `manage-halls.css`/`seat-layout.css`.
- Centralise admin-auth + CSRF into a tiny "middleware" helper (`requireAdminLogin()` exists; apply it everywhere, including all `*-api.php`).
- Purge old `admin_activity_log` / `security_audit_log` / `login_attempts` rows on a schedule; the log is already ~450 KB+ and grows on every action.
- Resist the "safe" on-error fallback in `adminLogin()`/`getEventSetting()` — failures are swallowed and logged; surface them to the operator through logs/monitoring.
- Add basic automated tests for the booking transaction (concurrent seat reservation) and the export CSV outputs.

### 6.6 Housekeeping suggestions

- `event_settings.screening_time` is a free-text "Friday | 16 May '25 | 8.30 PM" — keep in sync with `movie_date`/`movie_time` or drop it.
- Consider adding a partial / soft-delete for registrations (`status=cancelled` already exists) instead of hard `DELETE` in `delete_hall_full` (which currently wipes registrations).
- `find-registration.php` intentionally exposes booking details to anyone with an employee number — that's fine for an intranet app, but be aware it is unauthenticated (only rate-limited).

---

*Generated from a full read of the Movie-Night-WD-Version codebase (PHP 8.2 / MariaDB) — September 2026.*