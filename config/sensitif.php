<?php

// PRD §9.1: kunci HMAC untuk blind index No KK/NIK, terpisah dari APP_KEY.
// Nilai diambil dari env FIELD_HMAC_KEY; tidak ada default agar kegagalan
// konfigurasi terdengar (exception) alih-alih diam memakai kunci lemah.
return [
    'hmac_key' => env('FIELD_HMAC_KEY'),
];
