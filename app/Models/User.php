<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'id_wilayah',
        'is_active',
        'created_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'id_wilayah');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bawahan(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function isSuperadmin(): bool
    {
        return $this->role === Role::Superadmin;
    }

    public function isAdminRw(): bool
    {
        return $this->role === Role::AdminRw;
    }

    public function isAdminRt(): bool
    {
        return $this->role === Role::AdminRt;
    }

    // Keputusan 5/2.4: satu tempat keputusan boleh-tidaknya melihat data
    // pribadi penuh (NIK/No KK utuh). admin_rw mengikuti saklar config.
    public function bolehLihatDataPenuh(): bool
    {
        return match ($this->role) {
            Role::Superadmin, Role::AdminRt => true,
            Role::AdminRw => (bool) config('sensitif.rw_lihat_penuh', false),
        };
    }

    // Level wilayah yang wajib dimiliki peran ini.
    public function levelWilayahWajib(): ?string
    {
        return $this->role->levelWilayah();
    }

    // Benar bila peran ini boleh membuat akun peran lain (admin_rt tidak).
    public function bolehMembuatAkun(): bool
    {
        return $this->role->bolehMembuat() !== [];
    }

    // Semua akun dibuat superadmin/admin, tanpa registrasi mandiri (§3).
    // Akun nonaktif tidak boleh masuk panel.
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active === true;
    }
}
