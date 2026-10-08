<?php

/** Validate the settings exposed by the event editor before any write. */
final class EventSettingsService {
    private const TEXT_FIELDS = [
        'movie_name' => ['Movie name', 255],
        'movie_time' => ['Movie time', 100],
        'movie_location' => ['Movie location', 255],
    ];

    public function __construct(private PDO $pdo) {}

    public function update($key, $value): string {
        if (!is_string($key) || !is_string($value)) {
            throw new DomainException('Setting name and value must be text.');
        }
        if ($key === 'venue_name') $key = 'movie_location';
        $value = trim($value);
        if ($key === 'max_attendees') {
            if (!preg_match('/^[0-9]+$/D', $value) || (int)$value < 1 || (int)$value > 10) {
                throw new DomainException('Attendee limit must be a whole number between 1 and 10.');
            }
            $value = (string)(int)$value;
            $type = 'number';
        } elseif (isset(self::TEXT_FIELDS[$key])) {
            [$label, $limit] = self::TEXT_FIELDS[$key];
            if ($value === '') throw new DomainException($label . ' is required.');
            if (mb_strlen($value) > $limit) {
                throw new DomainException($label . ' must be ' . $limit . ' characters or fewer.');
            }
            $type = 'text';
        } else {
            throw new DomainException('This setting cannot be changed in the event editor.');
        }

        $statement = $this->pdo->prepare('INSERT INTO event_settings (setting_key, setting_value, setting_type, is_public) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $statement->execute([$key, $value, $type]);
        return $value;
    }
}
