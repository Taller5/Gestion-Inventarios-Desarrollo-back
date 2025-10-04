<?php
// filepath: c:\laragon\www\Gestion-Inventarios-Desarrollo-back\config\cors.php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['https://gestion-inventarios-desarrollo-back.vercel.app', 'http://localhost:5173'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];