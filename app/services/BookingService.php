<?php
/** Persistence boundary shared by booking flows and the future Firestore adapter. */
interface BookingRepository {
    public function transaction(callable $operation);
    public function employee(string $number): ?array;
    public function screening(int $shiftId): ?array;
    public function activeBooking(string $number): bool;
    public function settings(): array;
    public function reserve(int $hallId, int $shiftId, array $seats): void;
    public function create(array $booking): int;
    public function booking(int $id): ?array;
    public function release(array $booking): void;
    public function cancel(int $id): void;
}

final class BookingValidationException extends RuntimeException {}

final class BookingService {
    public function __construct(private BookingRepository $repository) {}

    public function register(array $input): array {
        $number = strtoupper(trim((string)($input['emp_number'] ?? '')));
        $name = trim((string)($input['staff_name'] ?? ''));
        $count = filter_var($input['attendee_count'] ?? null, FILTER_VALIDATE_INT);
        $seats = $input['selected_seats'] ?? null;
        if (strlen($number) < 2 || strlen($name) < 2) {
            throw new BookingValidationException('Please enter a valid employee number and name.');
        }
        if (!is_array($seats) || !$count || $count < 1 || count($seats) !== $count) {
            throw new BookingValidationException('Please select exactly one seat per attendee.');
        }
        foreach ($seats as $seat) {
            if (!is_string($seat) || $seat === '' || strlen($seat) > 50) {
                throw new BookingValidationException('Invalid seat selection.');
            }
        }
        if (count(array_unique($seats)) !== count($seats)) {
            throw new BookingValidationException('Each selected seat must be different.');
        }
        sort($seats, SORT_STRING);
        return $this->repository->transaction(function () use ($input, $number, $name, $count, $seats) {
            // This read locks the employee, serializing bookings across separate sessions.
            $employee = $this->repository->employee($number);
            if (!$employee || !$employee['is_active']) {
                throw new BookingValidationException('Employee not found or inactive.');
            }
            if (strcasecmp(trim($employee['full_name']), $name) !== 0) {
                throw new BookingValidationException('Employee name does not match our records.');
            }
            $screening = $this->repository->screening((int)$employee['shift_id']);
            if (!$screening) {
                throw new BookingValidationException('No active hall and shift assigned to this employee.');
            }
            $settings = $this->repository->settings();
            if (!in_array((string)($settings['registration_enabled'] ?? 'false'), ['1', 'true'], true)) {
                throw new BookingValidationException('Registration is currently disabled.');
            }
            $limit = min((int)($settings['max_attendees'] ?? 3), (int)$screening['max_attendees_per_booking']);
            if ($count > $limit) {
                throw new BookingValidationException('The attendee count exceeds the booking limit.');
            }
            if ($this->repository->activeBooking($number)) {
                throw new BookingValidationException('This employee already has an active registration.');
            }
            $booking = [
                'emp_number' => $number, 'staff_name' => $employee['full_name'],
                'attendee_count' => $count, 'hall_id' => (int)$screening['hall_id'],
                'shift_id' => (int)$employee['shift_id'], 'selected_seats' => $seats,
                'hall_name' => $screening['hall_name'], 'shift_name' => $screening['shift_name'],
                'movie_name' => $settings['movie_name'] ?? 'WD Movie Night',
                'screening_time' => $settings['screening_time'] ?? 'TBA',
                'ip_address' => (string)($input['ip_address'] ?? ''),
                'user_agent' => substr((string)($input['user_agent'] ?? ''), 0, 500),
                'registration_date' => date('Y-m-d H:i:s')
            ];
            $this->repository->reserve($booking['hall_id'], $booking['shift_id'], $seats);
            $booking['id'] = $this->repository->create($booking);
            return $booking;
        });
    }

    public function cancel(int $id): ?array {
        return $this->repository->transaction(function () use ($id) {
            $booking = $this->repository->booking($id);
            if (!$booking) return null;
            // Repeated cancellation must never release seats assigned to a newer booking.
            if ($booking['status'] === 'active') {
                $this->repository->release($booking);
                $this->repository->cancel($id);
            }
            return $booking;
        });
    }
}
