<?php

// PRD §9.1: kunci HMAC untuk blind index No KK/NIK, terpisah dari APP_KEY.
// Nilai diambil dari env FIELD_HMAC_KEY; tidak ada default agar kegagalan
// konfigurasi terdengar (exception) alih-alih diam memakai kunci lemah.
return [
    'hmac_key' => env('FIELD_HMAC_KEY'),

    // Keputusan 5: admin_rw boleh melihat data pribadi penuh bila saklar ini
    // dinyalakan. Default false agar data pribadi tersamarkan bagi admin_rw.
    'rw_lihat_penuh' => (bool) env('RW_LIHAT_PENUH', false),
];
