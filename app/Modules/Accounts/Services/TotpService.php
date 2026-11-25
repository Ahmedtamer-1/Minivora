<?php

namespace App\Modules\Accounts\Services;

use InvalidArgumentException;

/**
 * Pure-PHP RFC 6238 TOTP (HMAC-SHA1, 6 digits, 30-second period) for FR-ACC-06.
 */
class TotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const PERIOD = 30;

    private const DIGITS = 6;

    public function generateSecret(int $bytes = 20): string
    {
        if ($bytes < 1) {
            throw new InvalidArgumentException('Secret byte length must be positive.');
        }

        return $this->base32Encode(random_bytes($bytes));
    }

    public function provisioningUri(string $email, string $secret, string $issuer = 'Minivora'): string
    {
        $label = rawurlencode($issuer).':'.rawurlencode($email);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);

        return "otpauth://totp/{$label}?{$query}";
    }

    public function currentCode(string $secret, ?int $timestamp = null): string
    {
        $timeStep = intdiv($timestamp ?? time(), self::PERIOD);
        $counter = pack('N2', ($timeStep >> 32) & 0xFFFFFFFF, $timeStep & 0xFFFFFFFF);
        $key = $this->base32Decode($secret);
        $hash = hash_hmac('sha1', $counter, $key, true);

        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        $code = $binary % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    public function verify(string $secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        $normalized = preg_replace('/\s+/', '', $code) ?? '';
        if (strlen($normalized) !== self::DIGITS || ! ctype_digit($normalized)) {
            return false;
        }

        $now = $timestamp ?? time();

        for ($offset = -$window; $offset <= $window; $offset++) {
            $expected = $this->currentCode($secret, $now + ($offset * self::PERIOD));
            if (hash_equals($expected, $normalized)) {
                return true;
            }
        }

        return false;
    }

    private function base32Encode(string $data): string
    {
        $encoded = '';
        $buffer = 0;
        $bitsLeft = 0;
        $length = strlen($data);

        for ($i = 0; $i < $length; $i++) {
            $buffer = ($buffer << 8) | ord($data[$i]);
            $bitsLeft += 8;

            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $encoded .= self::BASE32_ALPHABET[($buffer >> $bitsLeft) & 0x1F];
            }
        }

        if ($bitsLeft > 0) {
            $encoded .= self::BASE32_ALPHABET[($buffer << (5 - $bitsLeft)) & 0x1F];
        }

        return $encoded;
    }

    private function base32Decode(string $encoded): string
    {
        $clean = strtoupper(rtrim(preg_replace('/\s+/', '', $encoded) ?? '', '='));
        if ($clean === '') {
            throw new InvalidArgumentException('TOTP secret cannot be empty.');
        }

        $decoded = '';
        $buffer = 0;
        $bitsLeft = 0;
        $length = strlen($clean);

        for ($i = 0; $i < $length; $i++) {
            $value = strpos(self::BASE32_ALPHABET, $clean[$i]);
            if ($value === false) {
                throw new InvalidArgumentException('Invalid Base32 character in TOTP secret.');
            }

            $buffer = ($buffer << 5) | $value;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $decoded .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $decoded;
    }
}
