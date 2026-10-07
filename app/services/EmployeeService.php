<?php

/** Shared employee creation used by both admin HTTP entry points. */
final class EmployeeService {
    public function __construct(private PDO $pdo) {}

    public function add(string $number, string $name, int $shiftId): int {
        $number = trim($number);
        $name = trim($name);
        if ($number === '' || $name === '' || $shiftId < 1) {
            throw new DomainException('Employee number, name, and shift are required.');
        }
        if (mb_strlen($number) > 20 || mb_strlen($name) > 255) {
            throw new DomainException('Employee number or name is too long.');
        }
        $shift = $this->pdo->prepare('SELECT s.id FROM shifts s JOIN cinema_halls h ON h.id = s.hall_id WHERE s.id = ? AND s.is_active = 1 AND h.is_active = 1');
        $shift->execute([$shiftId]);
        if (!$shift->fetchColumn()) throw new DomainException('Choose an active shift and hall.');

        try {
            $statement = $this->pdo->prepare('INSERT INTO employees (emp_number, full_name, shift_id, is_active) VALUES (?, ?, ?, 1)');
            $statement->execute([$number, $name, $shiftId]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') throw new DomainException('Employee number already exists.');
            throw $exception;
        }
        return (int)$this->pdo->lastInsertId();
    }
}
