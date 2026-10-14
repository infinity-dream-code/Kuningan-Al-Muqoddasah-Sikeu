<?php

namespace App\Support;

use App\Models\CyberKey;
use Illuminate\Support\Facades\Cookie;

class PersistentLogin
{
    public const COOKIE = 'al-muqoddasah-sikeu';

    /** Legacy cookie names from previous client branding (still readable, migrated on next request). */
    public const LEGACY_COOKIES = ['aisyiyah_jatinom', 'arruhama_stay'];

    public static function queue(CyberKey $user): void
    {
        $payload = json_encode([
            'urut' => (int) $user->urut,
            'exp' => now()->addYears(5)->timestamp,
        ]);

        Cookie::queue(cookie(
            self::COOKIE,
            $payload,
            5256000,
            '/',
            null,
            request()->isSecure(),
            true,
            false,
            'lax'
        ));

        foreach (self::LEGACY_COOKIES as $legacy) {
            if (request()->cookie($legacy)) {
                Cookie::queue(Cookie::forget($legacy));
            }
        }
    }

    public static function forget(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE));
        foreach (self::LEGACY_COOKIES as $legacy) {
            Cookie::queue(Cookie::forget($legacy));
        }
    }

    public static function hasAnyCookie(): bool
    {
        foreach (array_merge([self::COOKIE], self::LEGACY_COOKIES) as $name) {
            if (request()->cookie($name)) {
                return true;
            }
        }

        return false;
    }

    public static function rawFromRequest(): ?string
    {
        foreach (array_merge([self::COOKIE], self::LEGACY_COOKIES) as $name) {
            $raw = request()->cookie($name);
            if (is_string($raw) && $raw !== '') {
                return $raw;
            }
        }

        return null;
    }

    public static function needsRefresh(?string $raw): bool
    {
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return true;
        }

        return (int) ($data['exp'] ?? 0) < now()->addYear()->timestamp;
    }

    public static function userFromCookie(?string $raw): ?CyberKey
    {
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        $urut = (int) ($data['urut'] ?? 0);
        $exp = (int) ($data['exp'] ?? 0);

        if ($urut <= 0 || $exp < time()) {
            return null;
        }

        return CyberKey::query()->where('urut', $urut)->first();
    }
}
