<?php

namespace App\Rules;

use App\Models\Wilayah;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// Validasi induk wilayah di form Filament (Keputusan 1). Terima level berupa
// string atau closure agar bisa mengikuti pilihan level yang berubah-ubah.
class IndukWilayah implements ValidationRule
{
    public function __construct(private readonly string|Closure|null $level) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $level = $this->level instanceof Closure ? ($this->level)() : $this->level;

        if (blank($level)) {
            return;
        }

        $pesan = Wilayah::pesanIndukTidakValid(
            (string) $level,
            blank($value) ? null : (int) $value,
        );

        if ($pesan !== null) {
            $fail($pesan);
        }
    }
}
