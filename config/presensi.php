<?php

return [

    'api_url' => env(
        'PRESENSI_SHOLAT_API_URL',
        'http://vps1.smartpayment.co.id:8888/Data/Batu_Alizzah_PresensiSholat/WebAPI.php'
    ),

    'jwt_secret' => env('PRESENSI_SHOLAT_JWT_SECRET', 'a7c2a8a9b3c4a5a6a7a8a9b0c1a2a3'),

    /*
     * Guzzle/cURL ke WebAPI. Jika SSL error di lokal (mis. sertifikat), set
     * PRESENSI_HTTP_VERIFY_SSL=false di .env (hanya untuk dev).
     */
    'http' => [
        'connect_timeout' => (int) env('PRESENSI_HTTP_CONNECT_TIMEOUT', 10),
        'verify_ssl' => filter_var(env('PRESENSI_HTTP_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
    ],

    /*
     * Menu & route presensi haid. false = disembunyikan (aktifkan dengan PRESENSI_SHOW_HAID=true).
     */
    'show_haid' => filter_var(env('PRESENSI_SHOW_HAID', false), FILTER_VALIDATE_BOOL),

];

