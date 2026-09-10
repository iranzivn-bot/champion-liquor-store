<?php
declare(strict_types=1);

function validatePasswordStrength(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must contain at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one digit.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Password must contain at least one special character.';
    }
    return null;
}

function sanitizeInt(mixed $value, ?int $min = null, ?int $max = null): ?int
{
    if (!is_numeric($value)) {
        return null;
    }
    $int = (int) $value;
    if ($min !== null && $int < $min) {
        return null;
    }
    if ($max !== null && $int > $max) {
        return null;
    }
    return $int;
}

function sanitizeEmail(string $email): ?string
{
    $email = trim($email);
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    return $email;
}

function sanitizePhone(string $phone): ?string
{
    $phone = trim($phone);
    $phone = preg_replace('/[^0-9+\-\s()]/', '', $phone);
    if (strlen($phone) < 7) {
        return null;
    }
    return $phone;
}

function sanitizeUrl(string $url): ?string
{
    $url = trim($url);
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    $url = filter_var($url, FILTER_SANITIZE_URL);
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    return $url;
}
