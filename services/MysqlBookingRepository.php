<?php
require_once __DIR__ . '/BookingService.php';

final class MysqlBookingRepository implements BookingRepository {
    public function __construct(private PDO $pdo) {}
    public function transaction(callable $operation) {
        $owner = !$this->pdo->inTransaction();
        if ($owner) $this->pdo->beginTransaction();
        try {
            $result = $operation();
            if ($owner) $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($owner && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
    private function one(string $sql, array $parameters): ?array {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetch() ?: null;
    }
    public function employee(string $number): ?array {
        return $this->one('SELECT full_name, shift_id, is_active FROM employees WHERE emp_number = ? FOR UPDATE', [$number]);
    }
    public function screening(int $shiftId): ?array {
        // Shared lock order with layout edits: hall, then shift.
        $shift = $this->one('SELECT hall_id FROM shifts WHERE id = ?', [$shiftId]);
        if (!$shift) return null;
        $hall = $this->one('SELECT id, hall_name, max_attendees_per_booking FROM cinema_halls WHERE id = ? AND is_active = 1 FOR UPDATE', [$shift['hall_id']]);
        $current = $this->one('SELECT hall_id, shift_name FROM shifts WHERE id = ? AND is_active = 1 FOR UPDATE', [$shiftId]);
        if (!$hall || !$current || (int)$current['hall_id'] !== (int)$hall['id']) return null;
        return array_merge($hall, $current);
    }
    public function activeBooking(string $number): bool {
        return $this->one("SELECT id FROM registrations WHERE emp_number = ? AND status = 'active' LIMIT 1 FOR UPDATE", [$number]) !== null;
    }
    public function settings(): array {
        $result = [];
        foreach ($this->pdo->query('SELECT setting_key, setting_value FROM event_settings') as $row) {
            $result[$row['setting_key']] = $row['setting_value'];
        }
        return $result;
    }
    public function reserve(int $hallId, int $shiftId, array $seats): void {
        $statement = $this->pdo->prepare("UPDATE seats SET status = 'occupied', updated_at = NOW() WHERE hall_id = ? AND shift_id = ? AND seat_number = ? AND status = 'available'");
        foreach ($seats as $seat) {
            $statement->execute([$hallId, $shiftId, $seat]);
            if ($statement->rowCount() !== 1) {
                throw new BookingValidationException('One or more seats are no longer available. Please choose again.');
            }
        }
    }
    public function create(array $booking): int {
        $statement = $this->pdo->prepare("INSERT INTO registrations (emp_number, staff_name, attendee_count, hall_id, shift_id, selected_seats, movie_name, screening_time, ip_address, user_agent, status, registration_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
        $statement->execute([$booking['emp_number'], $booking['staff_name'], $booking['attendee_count'], $booking['hall_id'], $booking['shift_id'], json_encode($booking['selected_seats'], JSON_THROW_ON_ERROR), $booking['movie_name'], $booking['screening_time'], $booking['ip_address'], $booking['user_agent']]);
        return (int)$this->pdo->lastInsertId();
    }
    public function booking(int $id): ?array {
        $initial = $this->one('SELECT hall_id, shift_id FROM registrations WHERE id = ?', [$id]);
        if (!$initial) return null;
        $this->one('SELECT id FROM cinema_halls WHERE id = ? FOR UPDATE', [$initial['hall_id']]);
        $this->one('SELECT id FROM shifts WHERE id = ? FOR UPDATE', [$initial['shift_id']]);
        $booking = $this->one('SELECT * FROM registrations WHERE id = ? FOR UPDATE', [$id]);
        if ($booking) $booking['selected_seats'] = json_decode($booking['selected_seats'], true, 512, JSON_THROW_ON_ERROR);
        return $booking;
    }
    public function release(array $booking): void {
        $statement = $this->pdo->prepare("UPDATE seats SET status = 'available', updated_at = NOW() WHERE hall_id = ? AND shift_id = ? AND seat_number = ? AND status = 'occupied'");
        foreach ($booking['selected_seats'] as $seat) {
            $statement->execute([$booking['hall_id'], $booking['shift_id'], $seat]);
        }
    }
    public function cancel(int $id): void {
        $statement = $this->pdo->prepare("UPDATE registrations SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND status = 'active'");
        $statement->execute([$id]);
    }
}

function bookingService(PDO $pdo): BookingService {
    return new BookingService(new MysqlBookingRepository($pdo));
}
