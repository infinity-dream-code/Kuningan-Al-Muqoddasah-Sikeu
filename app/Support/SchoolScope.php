<?php

namespace App\Support;

use App\Models\scctcust;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Scope sekolah dari cyber_key.fid (= scctcust.CODE01).
 * fid kosong = akses semua sekolah.
 * Scope gender dari cyber_key.gender (L/P). gender kosong = lihat semua gender.
 */
class SchoolScope
{
    public static function codeFromUser(mixed $user = null): ?string
    {
        $user ??= Auth::user();
        if (!$user) {
            return null;
        }

        $code = $user->sekolah ?? $user->unit ?? null;
        if ($code === null) {
            return null;
        }

        $code = trim((string) $code);

        return $code !== '' ? $code : null;
    }

    public static function codesFromUser(mixed $user = null): array
    {
        $code = self::codeFromUser($user);

        return $code !== null ? [$code] : [];
    }

    /** L atau P dari cyber_key.gender; null jika tidak dibatasi. */
    public static function genderFromUser(mixed $user = null): ?string
    {
        $user ??= Auth::user();
        if (!$user) {
            return null;
        }

        return self::parseGender($user->gender ?? null);
    }

    /**
     * Normalisasi gender ke L / P.
     * Menerima L, P, 1/0, Laki-laki, Perempuan, dll.
     */
    public static function parseGender(mixed $value): ?string
    {
        $raw = strtoupper(trim((string) $value));
        if ($raw === '') {
            return null;
        }

        $normalized = preg_replace('/[\s\-_]+/', '', $raw) ?? $raw;

        if (in_array($normalized, ['L', '1', 'LAKI', 'LAKILAKI', 'MALE', 'PRIA', 'COWOK'], true)) {
            return 'L';
        }
        if (in_array($normalized, ['P', '0', 'PEREMPUAN', 'WANITA', 'FEMALE', 'CEWEK'], true)) {
            return 'P';
        }

        return null;
    }

    public static function genderLabel(?string $gender): string
    {
        return match (self::parseGender($gender)) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            default => trim((string) $gender),
        };
    }

    /** @return list<string> */
    public static function genderMatchValues(string $gender): array
    {
        return $gender === 'P'
            ? ['P', '0', 'PEREMPUAN', 'WANITA', 'FEMALE', 'CEWEK']
            : ['L', '1', 'LAKI', 'LAKILAKI', 'MALE', 'PRIA', 'COWOK'];
    }

    public static function applyGender($query, string $column = 'scctcust.CODE04'): void
    {
        $gender = self::genderFromUser();
        if ($gender === null) {
            return;
        }

        $normalized = "UPPER(REPLACE(REPLACE(TRIM(COALESCE({$column}, '')), '-', ''), ' ', ''))";
        $query->whereIn(DB::raw($normalized), self::genderMatchValues($gender));
    }

    public static function apply($query, string $table = 'scctcust', ?string $code = null): void
    {
        $code ??= self::codeFromUser();
        if (!blank($code)) {
            $query->where($table . '.CODE01', $code);
        }

        self::applyGender($query, $table . '.CODE04');
    }

    /**
     * Scope Master Kelas per sekolah admin.
     * mst_kelas.kelompok = mst_sekolah.CODE01 (bukan mst_kelas.unit).
     */
    public static function applyKelas($query, ?string $code = null, string $column = 'kelompok'): void
    {
        $code ??= self::codeFromUser();
        if (blank($code)) {
            return;
        }

        $query->where($column, $code);
    }

    /**
     * Scope siswa: CODE01 sekolah + kelas (CODE03) harus milik sekolah yang sama (mst_kelas.kelompok).
     */
    public static function applyStudent($query, string $table = 'scctcust', ?string $code = null): void
    {
        $code ??= self::codeFromUser();
        if (!blank($code)) {
            $query->where($table . '.CODE01', $code)
                ->where(function ($scoped) use ($table, $code) {
                    $scoped->whereNull($table . '.CODE03')
                        ->orWhereExists(function ($exists) use ($table, $code) {
                            $exists->select(DB::raw(1))
                                ->from('mst_kelas')
                                ->whereColumn('mst_kelas.id', $table . '.CODE03')
                                ->where('mst_kelas.kelompok', $code);
                        });
                });
        }

        self::applyGender($query, $table . '.CODE04');
    }

    public static function studentAccessible(?scctcust $siswa, ?string $code = null): bool
    {
        $code ??= self::codeFromUser();
        $gender = self::genderFromUser();

        if (!$siswa) {
            return blank($code) && $gender === null;
        }

        if (!blank($code)) {
            if (trim((string) ($siswa->CODE01 ?? '')) !== $code) {
                return false;
            }

            $kelasId = trim((string) ($siswa->CODE03 ?? ''));
            if ($kelasId !== '') {
                $kelasOk = DB::connection('DATA_MYSQL')
                    ->table('mst_kelas')
                    ->where('id', $kelasId)
                    ->where('kelompok', $code)
                    ->exists();
                if (!$kelasOk) {
                    return false;
                }
            }
        }

        if ($gender !== null) {
            return self::parseGender($siswa->CODE04) === $gender;
        }

        return true;
    }

    public static function denyStudentMessage(?scctcust $siswa, string $label = 'Siswa'): ?string
    {
        if (self::studentAccessible($siswa)) {
            return null;
        }

        $authCode = self::codeFromUser();
        $authGender = self::genderFromUser();
        $suffix = $authGender ? ', gender admin: ' . $authGender : '';

        return "{$label} bukan dari unit Anda (CODE01: " . ($siswa->CODE01 ?? '-') . ', kelas: ' . ($siswa->CODE03 ?? '-') . ', unit admin: ' . ($authCode ?? '-') . $suffix . ')';
    }
}
