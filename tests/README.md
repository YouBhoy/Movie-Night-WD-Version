# Booking regression checks

Run from PowerShell with XAMPP installed:

```powershell
.\tests\run-tests.ps1
```

Pass -XamppRoot if XAMPP is installed elsewhere. The runner starts a hidden MariaDB process bound to 127.0.0.1:33079 with data inside .test-runtime/mysql. It refuses to use an occupied port and stops the process after testing. It does not connect to the existing movie_night_db database.

The integration suite resets only movie_night_test on that isolated port and loads schema plus synthetic employees. Do not point it at a real database. Test sessions and logs stay under .test-runtime (ignored by git).

Coverage:
- Employee-assigned hall/shift, attendee limits, duplicate seats, inactive employees, and closed registration.
- Transaction rollback, cancellation, and repeated cancellation after a new booking.
- Unauthorized reads/writes, role restrictions, CSRF rejection, and POST-only mutations.
- Actual simultaneous MySQL requests for the same employee or seat.
- Booked layout protection, occupied-seat deletion/status changes, hall/shift deactivation, and legacy cancellation.

The in-memory tests alone do not establish concurrency safety; the isolated MySQL tests use independent PHP processes and real transactions. Browser styling and deployment configuration are outside these checks.

Seat warning regression checks (Node.js): `node tests/seat-warnings-test.cjs`. These exercise the shared selection engine and the page's confirmation and availability-refresh functions with a minimal DOM fixture. Coverage includes completable partial groups, stranded seats, recommendations, recorded aisle boundaries, remembered approvals, cancelling the last change, and preserving available seats after a conflict.
