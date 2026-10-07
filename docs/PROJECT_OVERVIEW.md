# Project overview

## How the application connects

Every HTTP request enters through a thin PHP file in public. It loads a controller under app/controllers, which loads app/bootstrap.php. The bootstrap establishes settings, error handling, secure session cookies, security headers, and shared support functions. Controllers check authorization before protected database access and load the matching view for HTML pages.

The registration page loads active halls, shifts, event settings, and a public CSRF token. public/assets/js/index.js handles employee lookup, seat availability, attendee selection, warnings, and submission through api.php. public/assets/js/seat-selection.js supplies the independent grouping, gap, and recommendation rules. PHP injects page-specific configuration through safely encoded JSON.

BookingService validates booking rules through BookingRepository. MysqlBookingRepository implements that contract with PDO transactions and locks. Registration, cancellation, and employee deactivation share the booking service so they preserve reservation history and release only the seats owned by the cancelled booking. Browser warnings advise the user; availability and booking rules are checked again by the backend.

Admin login verifies password hashes and regenerates the session ID. The dashboard displays registrations and aggregate statistics. admin.php manages event settings, employees, exports, and administrator accounts. admin-api.php provides authenticated reads, while admin-hall-shift-api.php and seat-layout-editor.php handle hall, shift, and layout operations. Mutations require POST, an authorized role, and an admin CSRF token. EmployeeService handles creation consistently across both admin entry points.

Templates live under app/views. Static page styling and browser behavior live under public/assets/css and public/assets/js. Uploaded images remain under public/uploads, while filesystem writes use an absolute PUBLIC_ROOT path. Database files, tests, logs, and sessions are kept outside the public document root.

## Cleanup completed

- Split mixed PHP/CSS/JavaScript pages into controllers, views, and static assets while preserving public PHP URLs.
- Split the shared configuration into bootstrap and focused database, security, authentication, and logging modules.
- Removed unused helpers, the fake email implementation, the unreachable admin login screen, duplicate employee creation, duplicate status handlers, and disconnected seat controls in the old admin script.
- Replaced the demo about page and duplicate hall/shift editor with authenticated redirects to their maintained pages. Removed the old browser diagnostic covered by CLI regression tests.
- Made the dashboard read-only; mutations use the existing admin action routes and booking service.
- Fixed admin pagination parameter types and minimum page number, replaced zero-valued hall statistics with database aggregates, and avoided unnecessary table-existence queries in normal admin reads.
- Fixed dashboard search restoration and safe highlighting; employee list values are escaped before HTML rendering.
- Indexed seat positions and computed recommendation scores once per candidate, preserving group and gap decisions.
- Reused settings queries and CSRF/rate-limit helpers, populated event settings on initial rendering, and connected the location editor to the setting used by registration.
- Moved generated logs and sessions to ignored private storage, fixed icon-CDN permissions in the content security policy, and made logout clear the session cookie.
- Added a reproducible PowerShell development entry point and updated tests for the new structure.

## Firebase boundary

The database has not been migrated. BookingService is independent of PDO through its repository interface, but other controllers and EmployeeService still contain MySQL queries. A Firebase migration must replace those reads/writes and implement equivalent transactional reservation ownership and cancellation behavior. Folder organization alone does not make SQL compatible with Firestore.

The Firebase guide in this folder is a planning document. Its provider quotas, SDK choices, and hosting assumptions must be verified before implementation. The original long project analysis is retained in archive/PROJECT_OVERVIEW_PRE_CLEANUP.md as historical context, not as a description of the current code.
