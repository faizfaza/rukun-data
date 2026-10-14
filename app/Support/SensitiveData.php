<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use RuntimeException;

// PRD §8/F6: satu-satunya tempat enkripsi kolom sensitif dan HMAC blind
// index No KK/NIK. Enkripsi memakai APP_KEY via Crypt; hash memakai kunci
// terpisah FIELD_HMAC_KEY agar bocornya satu kunci tidak membuka keduanya.
final class SensitiveData
{
    public static function encrypt(string $plain): string
    {
        return Crypt::encryptString($plain);
    }

    public static function decrypt(?string $cipher): ?string
    {
        if ($cipher === null || $cipher === '') {
            return null;
        }

        return Crypt::decryptString($cipher);
    }

    // Normalisasi sebelum hash: hanya digit, agar "3174-..." dan "3174..."
    // menghasilkan hash yang sama.
    public static function digits(string $value): string
    {
        return (string) preg_replace('/\D/', '', $value);
    }

    public static function hash(string $normalized): string
    {
        $key = config('sensitif.hmac_key');

        if (! is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('FIELD_HMAC_KEY belum dikonfigurasi (min. 32 karakter).');
        }

        return hash_hmac('sha256', $normalized, $key);
    }

    // Penyamaran untuk daftar (PRD §7.4, DESIGN.md §7.2): tampilkan N digit
    // terakhir saja, sisanya titik butir agar jelas terbaca sebagai tersamarkan.
    public static function mask(string $plain, int $visible = 6): string
    {
        $len = strlen($plain);

        if ($len <= $visible) {
            return str_repeat('•', $len);
        }

        return str_repeat('•', $len - $visible).substr($plain, -$visible);
    }

    // Indeks butir nama (blind index): setiap kata pada nama dinormalkan lalu
    // di-hash HMAC. Pencarian nama tetap bisa dijalankan di SQL tanpa pernah
    // menyimpan atau membandingkan nama dalam bentuk terbaca.
    /** @return array<int, string> */
    public static function nameTokens(string $value): array
    {
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($value)) ?? '';

        $tokens = array_map(
            fn (string $word): string => static::hash('nama:'.$word),
            explode(' ', $normalized),
        );

        return array_values(array_unique(array_filter($tokens)));
    }
}
