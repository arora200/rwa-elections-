<?php

/**
 * Sanitizes a string by removing HTML tags and encoding special characters.
 *
 * @param string $input The string to sanitize.
 * @return string The sanitized string.
 */
function sanitize_string(string $input): string
{
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitizes an email address.
 *
 * @param string $input The email address to sanitize.
 * @return string The sanitized email address.
 */
function sanitize_email(string $input): string
{
    return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
}

/**
 * Validates if an email address is in a valid format.
 *
 * @param string $email The email address to validate.
 * @return bool True if valid, false otherwise.
 */
function validate_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validates if a date string is in a valid 'YYYY-MM-DD' format.
 *
 * @param string $date The date string to validate.
 * @return bool True if valid, false otherwise.
 */
function validate_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Validates if a string represents a valid integer.
 *
 * @param string $input The string to validate.
 * @return bool True if valid, false otherwise.
 */
function validate_int(string $input): bool
{
    return filter_var($input, FILTER_VALIDATE_INT) !== false;
}

/**
 * Validates if a string represents a valid float.
 *
 * @param string $input The string to validate.
 * @return bool True if valid, false otherwise.
 */
function validate_float(string $input): bool
{
    return filter_var($input, FILTER_VALIDATE_FLOAT) !== false;
}

/**
 * Validates if a string contains only alphabetic characters and spaces.
 *
 * @param string $input The string to validate.
 * @return bool True if valid, false otherwise.
 */
function validate_alpha_space(string $input): bool
{
    return preg_match('/^[a-zA-Z\s]+$/', $input);
}

/**
 * Validates if a string contains only alphanumeric characters, spaces, and common punctuation.
 * Suitable for addresses.
 *
 * @param string $input The string to validate.
 * @return bool True if valid, false otherwise.
 */
function validate_address(string $input): bool
{
    return preg_match('/^[a-zA-Z0-9\s,.\-/#]+$/', $input);
}

/**
 * Validates if a string contains only digits (for contact numbers).
 *
 * @param string $input The string to validate.
 * @return bool True if valid, false otherwise.
 */
function validate_digits(string $input): bool
{
    return preg_match('/^\d+$/', $input);
}

?>