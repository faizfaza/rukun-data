<?php

namespace App\Enums;

// Keputusan 4: tiga peran saja. Peran desa dan rt_rw dihapus dari kode.
// Enum ini menjadi satu tempat kebenaran untuk label, level wilayah yang
// diwajibkan, dan peran apa yang boleh dibuat oleh peran ini.
enum Role: string
{
    case Superadmin = 'superadmin';
    case AdminRw = 'admin_rw';
    case AdminRt = 'admin_rt';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::AdminRw => 'Admin RW',
            self::AdminRt => 'Admin RT',
        };
    }

    // Level wilayah yang wajib dimiliki pengguna peran ini (null = tanpa
    // wilayah, yaitu superadmin).
    public function levelWilayah(): ?string
    {
        return match ($this) {
            self::Superadmin => null,
            self::AdminRw => 'rw',
            self::AdminRt => 'rt',
        };
    }

    // Keputusan 6: akun hanya dibuat oleh peran di atasnya.
    /** @return list<Role> */
    public function bolehMembuat(): array
    {
        return match ($this) {
            self::Superadmin => [self::AdminRw, self::AdminRt],
            self::AdminRw => [self::AdminRt],
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
