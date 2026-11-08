<?php
declare(strict_types=1);

/**
 * Comprueba el formato del correo y que su dominio tenga DNS utilizable para correo.
 * Esto descarta dominios inventados, aunque no confirma que exista el buzón concreto.
 */
function isValidRegistrationEmail(string $email): bool
{
    if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }

    $separator = strrpos($email, '@');
    if ($separator === false) {
        return false;
    }

    $domain = substr($email, $separator + 1);
    if ($domain === '' || strlen($domain) > 253 || !function_exists('checkdnsrr')) {
        return false;
    }

    return checkdnsrr($domain, 'MX')
        || checkdnsrr($domain, 'A')
        || checkdnsrr($domain, 'AAAA');
}
