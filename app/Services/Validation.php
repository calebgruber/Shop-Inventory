<?php
declare(strict_types=1);
namespace App\Services;

final class Validation
{
    public static function passwordInput(mixed $value): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > 1024) {
            throw new HttpException(422,'Enter a valid password of no more than 1024 bytes.');
        }
        return $value;
    }

    public static function text(mixed $value, string $label, int $max = 200, bool $required = true): string
    {
        if (!is_string($value) || mb_strlen(trim($value)) > $max || ($required && trim($value) === '')) {
            throw new HttpException(422, "$label is required and must be no longer than $max characters.");
        }
        return trim($value);
    }

    public static function positive(mixed $value, string $label): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
        if ($number === false) {
            throw new HttpException(422, "$label must be a positive integer.");
        }
        return $number;
    }

    public static function date(mixed $value, string $label): string
    {
        if (!is_string($value)) {
            throw new HttpException(422, "$label must be a date.");
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new HttpException(422, "$label must be a valid date.");
        }
        return $value;
    }

    public static function choice(mixed $value, array $choices, string $label): string
    {
        if (!is_string($value) || !in_array($value, $choices, true)) {
            throw new HttpException(422, "Invalid $label.");
        }
        return $value;
    }
}
