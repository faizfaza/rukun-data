<?php

namespace App\Enums;

// Keputusan 4: tiga peran saja. Peran desa dan rt_rw dihapus dari kode.
// Enum ini menjadi satu tempat kebenaran untuk label, level wilayah yang
// diwajibkan, dan peran apa yang boleh dibuat oleh peran ini.
enum Role: string
{
    case Superadmin = 'superadmin';
    case Admin = 'admin';
    case AdminRw = 'admin_rw';
    case AdminRt = 'admin_rt';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::Admin => 'Admin',
            self::AdminRw => 'Admin RW',
            self::AdminRt => 'Admin RT',
        };
    }

    // Level wilayah yang wajib dimiliki pengguna peran ini (null = tanpa
    // wilayah, yaitu superadmin dan admin).
    public function levelWilayah(): ?string
    {
        return match ($this) {
            self::Superadmin, self::Admin => null,
            self::AdminRw => 'rw',
            self::AdminRt => 'rt',
        };
    }

    // Superadmin: bisa buat akun untuk Petugas RT, RW, Admin, dan Superadmin.
    // Admin: bisa buat apa yang superadmin lakukan kecuali tidak dapat membuat admin lagi.
    /** @return list<Role> */
    public function bolehMembuat(): array
    {
        return match ($this) {
            self::Superadmin => [self::Superadmin, self::Admin, self::AdminRw, self::AdminRt],
            self::Admin => [self::AdminRw, self::AdminRt],
            self::AdminRw => [],
            self::AdminRt => [],
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (Role $role): array => [$role->value => $role->label()])
            ->all();
    }
}
