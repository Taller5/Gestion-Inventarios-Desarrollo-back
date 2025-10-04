<?php
// filepath: c:\laragon\www\Gestion-Inventarios-Desarrollo-back\config\cors.php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['http://localhost:5173', 'https://gestion-inventarios-desarrollo-fron-rosy.vercel.app/'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];