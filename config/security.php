<?php
declare(strict_types=1);

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function csrfToken(): string
{
    startSecureSession();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function isValidCsrfToken(?string $token): bool
{
    startSecureSession();
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function signInUser(array $user): void
{
    startSecureSession();
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id' => (int) $user['id'],
        'nombre' => (string) $user['nombre'],
        'email' => (string) $user['email'],
        'rol' => (string) $user['rol'],
    ];
}
