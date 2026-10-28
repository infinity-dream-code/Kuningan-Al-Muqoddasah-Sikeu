<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class CyberKey extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = "cyber_key";

    protected $primaryKey = "urut";

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        "users",
        "kunci",
        "fid",
        "ket",
        "kel",
        "gender",
        "urut",
        "password",
    ];

    protected $hidden = ["password"];

    public function getAuthIdentifierName(): string
    {
        return "urut";
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password;
    }

    public function getIdAttribute(): int
    {
        return (int) $this->urut;
    }

    public function getNameAttribute(): string
    {
        return $this->ket ?: (string) $this->users;
    }

    /**
     * Unit scope dari cyber_key.fid (CODE01).
     * fid kosong/null = akses semua unit.
     */
    public function getUnitAttribute(): ?string
    {
        $fid = trim((string) ($this->attributes['fid'] ?? ''));

        return $fid !== '' ? $fid : null;
    }

    /**
     * Kode sekolah (scctcust.CODE01) dari cyber_key.fid.
     * fid kosong/null = akses semua sekolah.
     */
    public function getSchoolCodeAttribute(): ?string
    {
        return $this->unit;
    }

    public function getSekolahAttribute(): ?string
    {
        return $this->unit;
    }

    public function hasRole(string $role): bool
    {
        if ($role === "siswa") {
            return false;
        }

        if (in_array($role, ["admin", "super-admin"], true)) {
            return true;
        }

        return $this->kel === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
    }
}
