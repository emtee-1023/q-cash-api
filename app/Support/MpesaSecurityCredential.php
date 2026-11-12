<?php

namespace App\Support;

use RuntimeException;

final class MpesaSecurityCredential
{
    public static function encrypt(string $password, string $certificatePath): string
    {
        if (! is_file($certificatePath) || ! is_readable($certificatePath)) {
            throw new RuntimeException(
                'The M-Pesa B2C certificate file could not be read at ['.$certificatePath.'].'
            );
        }

        $certificate = file_get_contents($certificatePath);

        if ($certificate === false || $certificate === '') {
            throw new RuntimeException('The M-Pesa B2C certificate file is empty.');
        }

        if (! str_contains($certificate, 'BEGIN CERTIFICATE')) {
            $certificate = "-----BEGIN CERTIFICATE-----\n"
                .chunk_split(base64_encode($certificate), 64, "\n")
                ."-----END CERTIFICATE-----\n";
        }

        $encrypted = '';

        if (! openssl_public_encrypt($password, $encrypted, $certificate, OPENSSL_PKCS1_PADDING)) {
            $error = openssl_error_string();

            throw new RuntimeException(
                'The M-Pesa B2C security credential could not be encrypted.'
                .($error !== false ? ' '.$error : '')
            );
        }

        return base64_encode($encrypted);
    }
}
