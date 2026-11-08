<?php
declare(strict_types=1);

function normalizeWhatsAppPhone(string $value): ?string
{
    $value = trim($value);
    if ($value === '' || preg_match('/^[+0-9().\s-]+$/', $value) !== 1) {
        return null;
    }
    $digits = preg_replace('/\D+/', '', $value);
    if (!is_string($digits) || preg_match('/^[1-9][0-9]{7,14}$/', $digits) !== 1) {
        return null;
    }
    return $digits;
}
