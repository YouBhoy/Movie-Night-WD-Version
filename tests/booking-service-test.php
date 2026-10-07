<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../services/BookingService.php';

final class MemoryBookings implements BookingRepository {
    public array $employees = ['WD001' => ['full_name' => 'Test Employee', 'is_active' => true, 'shift_id' => 1]];
    public array $seats = ['A1' => 'available', 'A2' => 'available', 'A3' => 'available'];
    public array $bookings = [];
    public array $event = ['registration_enabled' => 'true', 'max_attendees' => '3'];
    public bool $failCreate = false;
    public function transaction(callable $operation) {
        $snapshot = [$this->seats, $this->bookings];
        try { return $operation(); } catch (Throwable $e) {
            [$this->seats, $this->bookings] = $snapshot;
            throw $e;
        }
    }
    public function employee(string $number): ?array { return $this->employees[$number] ?? null; }
    public function screening(int $shiftId): ?array {
        return $shiftId === 1 ? ['hall_id' => 1, 'hall_name' => 'Hall 1', 'shift_name' => 'Crew', 'max_attendees_per_booking' => 2] : null;
    }
    public function activeBooking(string $number): bool {
        foreach ($this->bookings as $booking) if ($booking['emp_number'] === $number && $booking['status'] === 'active') return true;
        return false;
    }
    public function settings(): array { return $this->event; }
    public function reserve(int $hallId, int $shiftId, array $seats): void {
        foreach ($seats as $seat) {
            if (($this->seats[$seat] ?? null) !== 'available') throw new BookingValidationException('Unavailable seat');
            $this->seats[$seat] = 'occupied';
        }
    }
    public function create(array $booking): int {
        if ($this->failCreate) throw new RuntimeException('Simulated write failure');
        $id = count($this->bookings) + 1;
        $this->bookings[$id] = $booking + ['status' => 'active'];
        return $id;
    }
    public function booking(int $id): ?array { return $this->bookings[$id] ?? null; }
    public function release(array $booking): void { foreach ($booking['selected_seats'] as $seat) $this->seats[$seat] = 'available'; }
    public function cancel(int $id): void { $this->bookings[$id]['status'] = 'cancelled'; }
}

$passed = 0;
function check(bool $condition, string $message): void {
    global $passed;
    if (!$condition) throw new RuntimeException($message);
    $passed++;
}
function rejects(callable $operation, string $message): void {
    try { $operation(); } catch (BookingValidationException $e) { check(true, $message); return; }
    throw new RuntimeException($message);
}
function input(array $changes = []): array {
    return array_replace(['emp_number' => 'wd001', 'staff_name' => 'Test Employee', 'attendee_count' => 2, 'selected_seats' => ['A2', 'A1'], 'hall_id' => 999, 'shift_id' => 999], $changes);
}
$r = new MemoryBookings(); $s = new BookingService($r);
$b = $s->register(input());
check($b['hall_id'] === 1 && $b['shift_id'] === 1, 'Client hall/shift must not override employee assignment');
check($r->seats['A1'] === 'occupied' && count($r->bookings) === 1, 'Successful booking reserves seats');
rejects(fn() => $s->register(input(['attendee_count' => 1, 'selected_seats' => ['A3']])), 'Duplicate employee booking must fail');
$s->cancel($b['id']);
check($r->seats['A1'] === 'available' && $r->bookings[1]['status'] === 'cancelled', 'Cancellation releases seats and retains history');
$r->seats['A1'] = 'occupied'; $s->cancel(1);
check($r->seats['A1'] === 'occupied', 'Repeated cancellation must not release a newer booking');
$r = new MemoryBookings(); $s = new BookingService($r);
rejects(fn() => $s->register(input(['selected_seats' => ['A1', 'A1']])), 'Repeated seat must fail');
rejects(fn() => $s->register(input(['staff_name' => 'Wrong Name'])), 'Mismatched employee identity must fail');
rejects(fn() => $s->register(input(['attendee_count' => 3, 'selected_seats' => ['A1', 'A2', 'A3']])), 'Hall limit must apply');
rejects(fn() => $s->register(input(['selected_seats' => ['A1']])), 'Seat count mismatch must fail');
$r->employees['WD001']['is_active'] = false;
rejects(fn() => $s->register(input()), 'Inactive employee must fail');
$r->employees['WD001']['is_active'] = true; $r->event['registration_enabled'] = 'false';
rejects(fn() => $s->register(input()), 'Closed registration must fail');
$r->event['registration_enabled'] = '1';
$r->seats['A2'] = 'occupied';
rejects(fn() => $s->register(input()), 'Unavailable seat must fail');
check($r->seats['A1'] === 'available' && !$r->bookings, 'Partial reservation must roll back');
$r->seats['A2'] = 'available'; $r->failCreate = true;
try { $s->register(input()); throw new LogicException('Expected failure'); } catch (RuntimeException $e) {
    if ($e instanceof LogicException) throw $e;
    check($r->seats['A1'] === 'available' && !$r->bookings, 'Failed booking insert must roll back seats');
}
check($s->cancel(999) === null, 'Unknown cancellation must be harmless');
echo "PASS: $passed booking checks\n";
