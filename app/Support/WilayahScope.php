<?php

namespace App\Support;

use App\Models\User;

final class WilayahScope
{
    public static function label(?User $user): string
    {
        $wilayah = $user?->wilayah;

        if ($wilayah !== null) {
            return 'Cakupan: '.$wilayah->alamatLengkap();
        }

        return 'Cakupan: seluruh wilayah yang dapat Anda akses';
    }
}
