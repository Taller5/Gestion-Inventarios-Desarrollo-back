<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

      'hacienda' => [
        // Número de identificación del proveedor de sistemas (hasta 20 chars)
        'proveedor_sistemas' => env('HACIENDA_PROVEEDOR_SISTEMAS', null),
        // Código de actividad económica del emisor (6 dígitos)
        'codigo_actividad_emisor' => env('HACIENDA_CODIGO_ACTIVIDAD_EMISOR', null),
        // Ubicación del XSD a usar en xsi:schemaLocation (URL pública para validadores externos o ruta local)
        // Ejemplo URL: https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/FacturaElectronica_V4.4.xsd
          'schema_location' => env('HACIENDA_SCHEMA_LOCATION', null),
          // Específico para Tiquete (si se desea diferenciar del genérico)
          'schema_tiquete_location' => env('HACIENDA_SCHEMA_TIQUETE_LOCATION', null),
          // Firma: ruta al archivo .p12/.pfx y contraseña
          'cert_p12_path' => env('HACIENDA_CERT_P12_PATH', ''),
          'cert_password' => env('HACIENDA_CERT_PASSWORD', ''),
          // Alternativa: archivos PEM (útil si OpenSSL 3 no soporta el .p12)
          'cert_cert_pem_path' => env('HACIENDA_CERT_CERT_PEM_PATH', ''), // ruta al certificado en PEM
          'cert_key_pem_path'  => env('HACIENDA_CERT_KEY_PEM_PATH', ''),   // ruta a la llave privada en PEM
          'cert_key_passphrase' => env('HACIENDA_CERT_KEY_PASSPHRASE', ''), // passphrase de la llave privada si aplica
          // Alternativa: PKCS#12 embebido en variable de entorno base64 (útil en CI/CD y repos públicos)
          'cert_p12_base64' => env('HACIENDA_CERT_P12_BASE64', ''),
          // Verificar firma local después de firmar (sanity check)
          'verify_signature_local' => env('HACIENDA_VERIFY_SIGNATURE_LOCAL', false),
          // Fallback de firma placeholder (solo para desarrollo). Por defecto desactivado para evitar múltiples firmas.
          'allow_placeholder_signature' => env('HACIENDA_ALLOW_PLACEHOLDER_SIGNATURE', false),

          // Entorno y credenciales API Hacienda
          'env' => env('HACIENDA_ENV', 'stag'), 
          'username' => env('HACIENDA_USERNAME', ''),
          'password' => env('HACIENDA_PASSWORD', ''),
          'client_id' => env('HACIENDA_CLIENT_ID', null), // opcional, se autodefine por env
          'client_id_stag' => env('HACIENDA_CLIENT_ID_STAG', 'api-stag'),
          'token_url' => env('HACIENDA_TOKEN_URL', null),
          'token_url_stag' => env('HACIENDA_TOKEN_URL_STAG', 'https://idp.comprobanteselectronicos.go.cr/auth/realms/rut/protocol/openid-connect/token'),
          'recepcion_url' => env('HACIENDA_RECEPCION_URL', null),
          'recepcion_url_stag' => env('HACIENDA_RECEPCION_URL_STAG', 'https://api.comprobanteselectronicos.go.cr/recepcion-sandbox/v1/'),
          'recepcion_base_stag' => env('HACIENDA_RECEPCION_BASE_STAG', 'https://api.comprobanteselectronicos.go.cr'),
          'emisor_tipo' => env('HACIENDA_EMISOR_TIPO', '01'),
          'emisor_numero' => env('HACIENDA_EMISOR_NUMERO', ''),
    ],
];
