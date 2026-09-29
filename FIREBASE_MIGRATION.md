# 🔥 FIREBASE_MIGRATION.md — Moving `movie_night_db` from local MySQL to Firestore

> Guide for migrating the **Movie-Night-WD-Version** app (plain PHP 8.2 + MySQL on XAMPP) to **Cloud Firestore on the free Spark plan**, so the backend can live on a free hosting tier (e.g. Firebase Hosting + PHP-compatible host, or any cheap VPS with outbound HTTPS).
>
> ⚠️ **Verify quotas first.** Spark-plan limits are quoted from Google's public docs as of Sep 2026 — always re-check the **Firebase console → Usage** page and [firebase.google.com/docs/firestore/quotas](https://firebase.google.com/docs/firestore/quotas) before and during the migration.

---

## 1. Why Firestore (and what stays behind)

| Concern | Decision |
|---|---|
| Database | Cloud Firestore, Spark (no-cost) plan. No MySQL server to manage. |
| PHP runtime | Unchanged — the app stays plain PHP. Firestore is reached either via the **Firebase Admin SDK for PHP** (composer, gRPC) or via the **Firestore REST API** (curl, no extensions). |
| Sessions / login | Stay in PHP sessions + bcrypt — only the `admin_users` storage moves to Firestore. |
| Uploaded logo | Keep on the web server disk (a config value `site_logo` already points at a path). Firebase Storage *may* consume Spark quota — avoid it initially. |
| Deleted | MySQL procedures, JSON functions, `JOIN`s, `AUTO_INCREMENT`. |

**Important architectural shift:** Firestore charges **per document read/write** and has **no SQL joins**, so the data model must be **denormalised** and hot seat-map data should be stored in **one document per hall+shift** instead of ~800 tiny documents (see §5 and §7 — this is the single biggest cost lever).

---

## 2. Firebase Spark plan limits to design against

| Metric | Spark quota (per project) |
|---|---|
| Document reads | **50,000 / day** |
| Document writes | **20,000 / day** |
| Document deletes | 20,000 / day |
| Stored data | **1 GiB** total (raw document bytes only, no indexes counted) |
| Network egress | ~10 GiB / month (outbound) |
| Max document size | 1 MiB per document *(use this to justify 1-doc-per-layout below)* |
| Transactions | Up to 500 document operations per transaction |

Every **query counts each returned document as a read**, and every batched/full bulk change counts each **operation** as a write. The existing SQL dump is tiny (~1,950 seat rows + ~34 registrations + ~250 log rows ≈ well under 1 GiB), so **storage is a non-issue**; the binding constraint is **daily reads** because each seat-map load must not cost ~800 reads per visitor.

---

## 3. Schema mapping: MySQL tables → Firestore collections

Firestore uses **collections** (≈ tables) of **documents** (≈ rows). Use the existing integer IDs as **string document IDs** to keep cross-references stable (`"0"` is the "Unassigned" sentinel). Timestamps become Firestore **Timestamp** fields.

| MySQL table (movie_night_db) | Firestore collection | Document shape (fields) |
|---|---|---|
| `cinema_halls` | `halls` | `hall_name`, `max_attendees_per_booking`, `total_seats`, `is_active`, `created_at`, `updated_at` |
| `shifts` | `shifts` | `hall_id` (ref), `hall_name` (denormalised), `shift_name`, `shift_code`, `seat_prefix`, `seat_count`, `start_time` ("19:00:00"), `end_time`, `is_active`, `created_at` |
| `employees` | `employees` | `emp_number` (also a **lowercased search field** `emp_number_lc`), `full_name`, `full_name_lc`, `is_active`, `shift_id` (ref) |
| `seats` | ⭐ **`seat_layouts`** (one doc per hall+shift) | Document ID `"{hall_id}_{shift_id}"`; fields: `hall_id`, `shift_id`, `seats: [{id, seat_number, row_letter, seat_position, status}, ...]` (array), `updated_at` |
| `registrations` | `registrations` | `emp_number`, `staff_name`, `staff_name_lc`, `attendee_count`, `hall_id` (ref), `hall_name` (denorm), `shift_id` (ref), `shift_name` (denorm), `selected_seats: ["A12","B12",...]` (array), `movie_name`, `screening_time`, `registration_date`, `ip_address`, `user_agent`, `status` ("active"\|"cancelled"\|"completed"), `notes`, `created_at`, `updated_at` |
| `event_settings` | `app_settings` (**single document** `"public"`**)** | `movie_name`, `movie_date`, `movie_time`, `movie_location`, `event_description`, `primary_color`, `secondary_color`, `background_theme`, `hero_background_image`, `footer_text`, `site_logo`, `max_attendees`, … plus a second document `"private"` for `registration_enabled`, `allow_temp_registration`, `custom_css`, `max_registrations`, `admin_session_timeout`, … |
| `admin_users` | `admin_users` | Doc ID = username; `password_hash` (bcrypt, unchanged), `role` ("admin"\|"manager"\|"viewer"), `is_active`, `last_login`, `created_at`, `updated_at` |
| `login_attempts` | `login_attempts` | `ip_address`, `username`, `success`, `message`, `created_at` |
| `admin_activity_log` | `admin_activity_log` | `admin_user`, `action`, `target_type`, `target_id`, `details` (map/array), `ip_address`, `user_agent`, `created_at` |
| `security_audit_log` | `security_audit_log` | `event_type`, `user_id`, `ip_address`, `user_agent`, `details`, `risk_level`, `created_at` |
| `rate_limits` | **drop** — session-based rate limiting already works in PHP | n/a |

> **Why `seat_layouts` as one document:** loading ~800 seats with the old `seats` table costs **~800 reads per seat-map open**. As one `seat_layouts` document it costs **1 read** and stays well under the 1 MiB doc limit (≈800 seats × ~120 B ≈ 96 KB). Seat status changes update the same single document. This mirrors how `seat-layout-editor.php` already saves the whole layout in one go.

### Stored procedures → application code

| MySQL procedure | Firestore replacement |
|---|---|
| `createSeatsForHallShift` | PHP builds the canned layout array (copy the same A–L/A–M, CREW A/B seat maths) and writes the `seat_layouts` doc. |
| `getSeatsForHallShift` | Read `seat_layouts/{hallId_shiftId}`; if missing, create it first. |
| `findSmartSeatSuggestions` | PHP loads the layout doc, groups by `row_letter`, runs the "consecutive run ≥ N, ranked by distance from preferred row" logic in a loop. |
| `validateSeatGaps` / `validateBookingRequest` | PHP re-implementation (same gap rules) inside the booking transaction. |
| `reserveSeats` | Firestore **transaction**: read layout doc → verify seats available → update their status → create `registrations/{id}`. |
| `freeSeatsByRegistration` | Firestore **transaction**: read registration → set seats available → set `status="cancelled"`. |
---

## 4. How the PHP backend talks to Firestore

### 4.1 Option A — Firebase Admin SDK for PHP (recommended if you can install extensions)

```bash
composer require kreait/firebase-php google/cloud-firestore
```
- Needs the **gRPC PHP extension** (`pecL install grpc`; on shared hosts this is the hard part).
- Service account JSON → `Factory` → `$db = (new Kreait\Firebase\Factory)->withServiceAccount('/path/firebase-credentials.json')->createFirestore()->database();`
- Example reads/writes:

```php
$db = getFirestore();

// Read one doc (1 read)
$layoutDoc = $db->document('seat_layouts/1_1')->snapshot();
$seats = $layoutDoc->exists() ? $layoutDoc->data()['seats'] : createDefaultLayout($db, 1, 1);

// Simple upsert (1 write)
$db->document('halls/7')->set([
    'hall_name' => 'Cinema Hall 3',
    'max_attendees_per_booking' => 3,
    'total_seats' => 72,
    'is_active' => true,
]);

// Query: active employees whose shift is 2 (array membership)
$snap = $db->collection('employees')
    ->where('shift_id', '=', 2)
    ->where('is_active', '=', true)
    ->documents();
```

### 4.2 Option B — Firestore REST API (no extensions; best for shared PHP hosts)

Two concerns: (1) the REST endpoint, (2) auth for service accounts (Google's OAuth2 JWT dance).

**Base URL**
```
https://firestore.googleapis.com/v1/projects/{PROJECT_ID}/databases/(default)/documents/
```

**Auth** — exchange the service account private key for an OAuth2 access token, then send `Authorization: Bearer <token>` (reuse the token until it expires; cache it in a session/file):

```php
// 1. Build & sign a JWT (RS256) with the service account, scope:
//    https://www.googleapis.com/auth/datastore
// 2. POST https://oauth2.googleapis.com/token  (grant_type=JWT_BEARER) → access_token
```

**Examples with `curl`**

```php
function fsGet(string $path): array {
    return json_decode(curlRequest(
        'GET', 'https://firestore.googleapis.com/v1/projects/P/databases/(default)/documents/' . $path), true);
}

function fsSet(string $path, array $fields): void {
    $body = ['fields' => arrayMapForFirestore($fields)];
    curlRequest('PATCH', '.../documents/' . $path . '?updateMask.fieldPaths=hall_name&updateMask.fieldPaths=is_active',
        json_encode($body));
}
```

Firestore wire format recap: strings → `{"stringValue": "..."}`, ints → `{"integerValue": "5"}`, bools → `{"booleanValue": true}`, arrays → `{"arrayValue": {"values": [...]}}`, timestamps → `{"timestampValue": "2026-09-22T12:00:00Z"}`.

Queries use a `structuredQuery` body against the `:runQuery` endpoint:

```php
POST .../documents/:runQuery
{
  "structuredQuery": {
    "from": [{ "collectionId": "registrations" }],
    "where": {
      "fieldFilter": { "field": { "fieldPath": "status" },
                       "op": "EQUAL", "value": { "stringValue": "active" } }
    },
    "orderBy": [{ "field": { "fieldPath": "registration_date" }, "direction": "DESCENDING" }],
    "limit": 50
  }
}
```

Multi-document writes: use **`BatchWrite`** (`POST .../documents:batchWrite`) or Firestore **transactions** (`:commit` with `singleWriteTransaction` running `precondition` reads) — PHP `curl` is enough to implement both.

### 4.3 Keep the rest of the app unchanged

Create a small adapter so the pages barely notice the swap:

| Existing helper (config.php) | New implementation |
|---|---|
| `getDBConnection()` / PDO | `getFirestore()` returning a thin `FirestoreDb` wrapper (Admin SDK client, or a REST client class) |
| `getEventSetting($key, $default)` | `$settings = $db->doc('app_settings/public')->data(); return $settings[$key] ?? $default;` |
| `isEmployeeRegistered($empNumber)` | query `registrations where emp_number = X and status = active` (1 doc = 1 read; also enforce uniqueness app-side, see §6) |
| `insert/update …` | `$db->doc('collection/docId')->set([...])` |

Keep `sanitizeInput()`, CSRF, sessions, rate limiting, and the HTML exactly as they are — only the persistence layer changes.
---

## 5. Handling relational data without SQL joins

Firestore has no `JOIN`. The principle is: **write the display fields down on the child documents** at the moment they matter, and pay the extra write instead of the extra read.

### 5.1 Denormalisation rules mapped from the current SQL joins

| SQL join you use today | Firestore substitute |
|---|---|
| registrations `JOIN` cinema_halls / `JOIN` shifts (dashboard, api get_registrations) | Store `hall_name` + `shift_name` **on the registration doc** (write once at booking / hall-rename time) |
| employees `JOIN` shifts (export, admin list) | Store `shift_name` (+`shift_id`) on the employee doc |
| find-registration: employees → registrations | Derived from the registration doc itself (`emp_number` lookup) |
| per-hall stats `GROUP BY hall` | Maintain counters on the `halls` doc (`active_registrations`, `total_attendees`) — **or** compute by querying registrations filtered by `hall_id` (bounded reads if registrations are few). |
| admin registrations search (`LIKE`) | Add `staff_name_lc`/`emp_number_lc` and use **range filters**: `where 'staff_name_lc' >= 'mike'` and `<= 'mike\uf8ff'` (the `\uf8ff` trick) — works with Admin SDK & REST. |
| seat list for hall+shift | Single `seat_layouts/{hallId_shiftId}` document → no query, no join |

### 5.2 Keep "live" data out of documents that are read often

`seats` status changes through bookings are the hot path. With the 1-doc-per-layout model, an 3-seat booking that now writes 3 seat rows becomes **1 write** (rewrite the layout doc) — cheaper, not more expensive. Refresh the seat map after every booking (it already re-fetches seats after each `register`).

### 5.3 Recommended composite indexes (create in the Firebase console)

- `registrations`: `status ASC, registration_date DESC` (dashboard + exports)
- `registrations`: `emp_number ASC, status ASC` (active-registration check, find-registration)
- `registrations`: `staff_name_lc ASC, status ASC` (search)
- `employees`: `shift_id ASC, is_active ASC`
- `admin_activity_log`: `action ASC, created_at DESC`
- `login_attempts`: `ip_address ASC, created_at DESC`

(Every query needs a matching index; Firestore tells you the exact index name when a query fails — just click "Create index".)

---
## 6. Booking transaction in Firestore (replacing `reserveSeats`)

Firestore transactions are **serialisable**: Perfect for the seat race condition that the MySQL version only defends via `rowCount()`.

```php
// Pseudocode (Admin SDK). With REST, use commit+tombstone or the
// documentExists precondition to guard concurrent writers.
$db->runTransaction(function (Transaction $tx) use ($empNumber, $selectedSeats) {
    $layoutRef  = $db->document("seat_layouts/{$hallId}_{$shiftId}");
    $layoutSnap = $tx->snapshot($layoutRef);
    $seats = &$layoutSnap['seats'];

    // 1. Verify every requested seat_number exists and status === 'available'
    foreach ($selectedSeats as $num) {
        $idx = seatIndex($seats, $num);
        if ($idx < 0 || $seats[$idx]['status'] !== 'available') {
            throw new \Exception("Seat $num is no longer available");
        }
    }
    // 2. Mark occupied
    foreach ($selectedSeats as $num) {
        $seats[seatIndex($seats, $num)]['status'] = 'occupied';
    }
    $tx->set($layoutRef, ['seats' => $seats, 'updated_at' => now()], ['merge' => true]);

    // 3. Write the registration (id = auto or emp-number-based)
    $regRef = $db->collection('registrations')->newDocument();
    $tx->set($regRef, [
        'emp_number' => $empNumber, 'status' => 'active',
        'selected_seats' => $selectedSeats, /* … hall/shift denorm, times … */
    ]);

    // 4. Counters on the hall doc (optional, keeps dashboard cheap)
    $hallRef = $db->document("halls/$hallId");
    $tx->update($hallRef, ['active_registrations' => FieldValue::increment(1)]);
});
```

Cancellations (`freeSeatsByRegistration`): the same shape — transaction sets seats back to `available` and flips `registrations/{id}.status` to `cancelled`.

Uniqueness of "one active registration per employee": Firestore can't do a partial unique index. Use the **employee number as part of the registration document ID** (`registrations/{empNumber}` for the active one; store a separate `registration_history` doc or collection for cancellations), **or** accept the transaction read + app check (the transaction guarantees the two-write race is serialised) **or** a per-day `registration_locks` doc.

---

## 7. Free-tier budget vs. expected booking volume

Base assumptions (from the current dump + realistic usage): ~1,900 seats across 5–8 hall+shift combos, ~50–150 active bookings/event, ~3–4 events/year, admin work light.

| Operation | Firestore cost | Notes |
|---|---|---|
| Open seat map (1 employee) | **1 read** (layout doc) | Why the single-doc layout matters — the old per-seat model would cost ~800 reads |
| `check_employee` | 1 read (employee doc by id) | |
| Booking (3 seats) | 1 transaction read + 1 layout write + 1 registration write + optional hall counter write ≈ **3–4 writes** | Well under 20k/day |
| Free seats (admin cancels) | 1 layout write + 1 registration write | |
| Dashboard load | admin_users + halls + registrations list (say 30 docs) + layout reads ≈ **~40–60 reads** | Cache the stats doc if needed |
| 100 employees × full flow | ≈ 100 × (1 layout + 1 emp + …) ≈ **200–300 reads, 350–450 writes** per day | |
| Worst storm day (500 employees in 1 h) | ≈ 1,500 reads + ~2,000 writes | **Still ~33× under the 50k read quota and ~10× under 20k writes** |

Guidelines to stay safely inside Spark:

1. **Never** render the seat map from anything other than `seat_layouts/{hallId_shiftId}`.
2. Cache `app_settings/public` for 60 s (a PHP file/APCu cache) — it's read on **every** page load.
3. Cache dashboard statistics in a `stats` doc refreshed every 5–10 min.
4. Trim log writes: `admin_activity_log` can be 20+ writes per admin action; either skip `success`-only reads or purge old docs after each event.
5. Keep an eye on the **Usage** tab; set a **budget alert** in Google Cloud billing.

**Storage estimate:** 10 layout docs ≈ 1 MB + ~200 registrations ≈ <1 MB + logs ≈ a few MB → **<< 1 GiB**. Storage will never be the constraint.

---

## 8. Migration steps (export → transform → import → test → cutover)

### Phase 0 — Prepare
- `composer require`/fork nothing in production yet; create a **Firebase project** (Spark), download the **service-account JSON** from *Project settings → Service accounts*.
- In **Firestore Database**, choose a single-region location (e.g. `asia-southeast1` — closest to Singapore) — single-region is cheaper if you ever go Blaze.
- Create the collections pointed at in §3 and the composite indexes in §5.3.
- Add `DATA_SOURCE=mysql|firestore` to `config.php` so both backends can coexist behind one switch.

### Phase 1 — Export MySQL
- Use `mysqldump` (or phpMyAdmin) for a raw backup: `mysqldump -u root movie_night_db > movie_night_db_2026.sql`.
- Export **machine-readable** data to JSON (this is what the importer consumes):
  - Quick route: phpMyAdmin *Export → JSON* per table, or
  - a small PHP one-off script using the existing PDO connection dumping every `SELECT *` to `data/*.json`.

### Phase 2 — Transform (the only step with real work)
Map the SQL rows to Firestore documents (§3). Do this in PHP (it already has `json_decode`/`json_encode`):
- IDs → strings; keep the same values so `hall_id`/`shift_id` references still point at the right docs.
- `selected_seats` (longtext JSON) → native Firestore **array**.
- `details` in logs → native map/array.
- `created_at`/`updated_at`/`registration_date` `TIMESTAMP` → ISO-8601 strings (or Firestore timestamps).
- `is_active` tinyint → boolean.
- `seat rows` → group by `(hall_id, shift_id)` into the `seat_layouts` **single-document** shape.
- `event_settings` rows → flatten into `app_settings/public` and `app_settings/private` documents.
- Lowercased search fields (`emp_number_lc`, `staff_name_lc`) added during transform.
- Keep the "Unassigned" sentinel: `halls/"0"` and `shifts/"0"` documents, `is_active=false`.

### Phase 3 — Import
- **Order matters:** `halls` → `shifts` → `seat_layouts` → `employees` → `registrations` → `app_settings` → `admin_users` → logs. (No FKs, so order is mostly cosmetic, but it keeps references valid in logs.)
- Batch import in chunks of **≤ 500 operations per batch** using `BatchWrite` (REST) or the Admin SDK bulk writes; a run of ~2,500 total docs ≈ 5–10 batches — instant for a day's worth of quota.
- Verify with counts: `SELECT COUNT(*)` per table vs. Firestore query counts / console document counts.

### Phase 4 — Parallel testing (dual run)
- Keep MySQL running and the old site untouched at a test URL.
- Point a **copy** of the app at Firestore (`DATA_SOURCE=firestore`) and run a scripted scenario:
  1. open seat map for hall 1 / shift 1 → layout renders,
  2. register WD001 (3 seats) → seats occupied, registration visible, confirmation works,
  3. cancel/free via admin → seats return,
  4. admin: halls/shifts CRUD, seat-editor save layout, export CSV, employee deactivate,
  5. compare **active seat per hall+shift** and **registration list** between MySQL and Firestore — they must match.
- Fix index-missing errors, denormalisation gaps, and date/timezone differences (store everything in UTC, render in `Asia/Singapore`).

### Phase 5 — Cutover
- Freeze writes for 5–10 min (`registration_enabled=false`).
- Run a final delta sync (new registrations/logs since Phase 3 import).
- Flip `DATA_SOURCE=firestore` in `config.php`, deploy the new code/config.
- Re-enable registration and watch the Firebase **Usage** tab + error log for the first hour.

---

## 9. Rollback plan (if cutover fails)

**Keep MySQL as the source of truth until you are happy for a full event cycle.**

1. **Feature flag:** as long as both backends exist behind `DATA_SOURCE`, rollback is:
   1. Set `registration_enabled=false` (stops new writes),
   2. flip `DATA_SOURCE=mysql`,
   3. re-run the MySQL copy of the app (old URL/old files untouched).
2. If **new Firestore writes happened during the experiment** (test bookings, admin changes), replay them into MySQL: script that reads Firestore (registrations/`status=cancelled`, seat statuses, hall/shift renames, employee toggles) since the last MySQL snapshot and performs the same `UPDATE`s/`INSERT`s (or re-import the raw dump + replay a changes log).
3. **For a clean quick revert:** keep nightly `mysqldump` snapshots. Restore = drop + reload snapshot + replay any Firestore-only deltas.
4. **Decision checkpoint (roll-forward vs roll-back):** after the first full event with Firestore, if every booking round succeeded and the hourly usage stayed < 10% of quota, archive the MySQL instance; otherwise fall back to step 1.
5. Keep `movie_night_db_current.sql` as a permanent, dated artifact for audits.

---

## 10. Risks & gotchas to plan for

- **gRPC on PHP shared hosts** is often unavailable → prefer Option B (REST) and write a clean REST client wrapper from the start.
- **Quota surprise from queries:** any dashboard that fetches 200 registrations = 200 reads; keep pagination (`limit 50`) and cursor-based pages (Firestore has no `OFFSET`).
- **No `LIKE`:** search must use the `\uf8ff` range trick or an array-of-keywords field (`array_contains`).
- **No transactions across collections unless in a single transaction object** — keep all seat+registration changes in one `runTransaction`.
- **Time zones:** MySQL dumps store local `Asia/Singapore` timestamps; Firestore timestamps are UTC. Store UTC, format locally.
- **`site_logo`, uploaded files:** they remain on the web server; if the web server dies, the logo dies too — acceptable for an intranet tool, or move to Firebase Storage later (check its Spark quota).
- **Security:** the Admin SDK / service account bypasses Firestore security rules — the service account JSON must be kept **outside** the web root (e.g. `C:\xampp\movie-night\service-account.json`, referenced by absolute path) and never committed to source control.
- **Legacy MySQL artefacts to retire in the same sweep:** the unused stored procedures, `rate_limits` table, and the JSON-`selected_seats` string handling all become dead code once the Firestore adapter is in.

---

*Migration guide prepared from the Movie-Night-WD-Version codebase and Firestore public docs — September 2026.*