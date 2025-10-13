<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Smoke test: insert a dummy HaciendaResponse row to verify the table accepts data
Artisan::command('hacienda:response:smoke', function () {
    // 1) Ensure there's at least one invoice to satisfy the FK
    $invoice = \App\Models\Invoice::first();
    if (!$invoice) {
        $invoice = \App\Models\Invoice::create([
            'customer_name' => null,
            'customer_identity_number' => null,
            'branch_name' => 'Sucursal Central',
            'business_name' => 'Empresa Demo S.A.',
            'business_legal_name' => 'Empresa Demo S.A.',
            'business_phone' => '22223333',
            'business_email' => 'demo@example.com',
            'branches_phone' => '22223333',
            'province' => '1',
            'canton' => '01',
            'business_id_type' => '02',
            'business_id_number' => '3012345678',
            'cashier_name' => 'Cajero Demo',
            'date' => now(),
            'products' => [
                [
                    'codigo_producto' => 'P001',
                    'descripcion' => 'Producto demo',
                    'quantity' => 1,
                    'price' => 1000,
                    'discount' => 0,
                ],
            ],
            'subtotal' => 1000,
            'total_discount' => 0,
            'taxes' => 130,
            'total' => 1130,
            'amount_paid' => 1130,
            'change' => 0,
            'payment_method' => 'Cash',
            'receipt' => 'RCPT-TEST',
        ]);
        $this->info("Created demo invoice ID={$invoice->id}");
    }

    // 2) Insert a HaciendaResponse linked to this invoice
    $resp = \App\Models\HaciendaResponse::create([
        'invoice_id' => $invoice->id,
        'invoice_xml_id' => null, // nullable FK
        'clave' => str_pad((string)random_int(0, 9), 50, '9'),
        'estado' => 'recibido',
        'numero_consecutivo' => '00100001040000000001',
        'ind_ambiente' => 'stag',
        'fecha_recepcion' => now(),
        'fecha_resolucion' => null,
        'respuesta_xml' => null,
        'detalle' => [ 'smoke' => true, 'msg' => 'insert ok' ],
        'error_message' => null,
    ]);

    $this->info('Inserted HaciendaResponse ID=' . $resp->id . ' for invoice ID=' . $invoice->id);
    $count = \App\Models\HaciendaResponse::count();
    $this->info('Total HaciendaResponse rows: ' . $count);
})->purpose('Insert a dummy HaciendaResponse to verify DB writes');

// Check Hacienda configuration and readiness
Artisan::command('hacienda:check {--token} {--sign}', function () {
    $this->info('Hacienda configuration check');

    $env = config('services.hacienda.env', env('HACIENDA_ENV', 'stag'));
    $this->line('  HACIENDA_ENV: ' . $env);

    $prov = config('services.hacienda.proveedor_sistemas');
    $act = config('services.hacienda.codigo_actividad_emisor');
    $tipo = config('services.hacienda.emisor_tipo');
    $num  = config('services.hacienda.emisor_numero');
    $this->line('  ProveedorSistemas: ' . ($prov ?: '(vacío)'));
    $this->line('  CodigoActividadEmisor: ' . ($act ?: '(vacío)'));
    $this->line('  Emisor Tipo: ' . ($tipo ?: '(vacío)'));
    $this->line('  Emisor Numero: ' . ($num ?: '(vacío)'));

    $p12 = config('services.hacienda.cert_p12_path', env('HACIENDA_CERT_P12_PATH'));
    $pwdRaw = config('services.hacienda.cert_password');
    $pwd = $pwdRaw ? '*** (len=' . strlen((string)$pwdRaw) . ')' : '(vacío)';
    $this->line('  Cert .p12 path: ' . ($p12 ?: '(vacío)'));
    $this->line('  Cert password: ' . $pwd);
    $p12b64 = config('services.hacienda.cert_p12_base64', env('HACIENDA_CERT_P12_BASE64'));
    if ($p12b64) {
        $this->line('  Cert .p12 base64: (configurado, longitud=' . strlen((string)$p12b64) . ')');
    }
    $pemCert = config('services.hacienda.cert_cert_pem_path', env('HACIENDA_CERT_CERT_PEM_PATH'));
    $pemKey  = config('services.hacienda.cert_key_pem_path', env('HACIENDA_CERT_KEY_PEM_PATH'));
    $pemPass = config('services.hacienda.cert_key_passphrase');
    if ($pemCert || $pemKey) {
        $this->line('  PEM cert path: ' . ($pemCert ?: '(vacío)'));
        $this->line('  PEM key  path: ' . ($pemKey ?: '(vacío)'));
        $this->line('  PEM key pass: ' . ($pemPass ? '*** (len=' . strlen((string)$pemPass) . ')' : '(vacío)'));
    }
    $resolved = null;
    if (!empty($p12)) {
        // Show resolved path; if already absolute, base_path will likely return the same
        $resolved = function_exists('base_path') ? base_path($p12) : $p12;
        $this->line('  Resolved path: ' . $resolved);
        $exists = is_file($resolved);
        $this->line('  Exists: ' . ($exists ? 'YES' : 'NO'));
        if ($exists) {
            $this->line('  File size: ' . @filesize($resolved) . ' bytes');
        }
    }
    if (!empty($pemCert)) {
        $resCert = function_exists('base_path') ? base_path($pemCert) : $pemCert;
        $this->line('  PEM cert resolved: ' . $resCert . ' (exists: ' . (is_file($resCert) ? 'YES' : 'NO') . ')');
    }
    if (!empty($pemKey)) {
        $resKey = function_exists('base_path') ? base_path($pemKey) : $pemKey;
        $this->line('  PEM key  resolved: ' . $resKey . ' (exists: ' . (is_file($resKey) ? 'YES' : 'NO') . ')');
    }

    $tokenUrl = config('services.hacienda.token_url')
        ?? ($env === 'prod' ? config('services.hacienda.token_url_prod') : config('services.hacienda.token_url_stag'));
    $clientId = config('services.hacienda.client_id')
        ?? ($env === 'prod' ? config('services.hacienda.client_id_prod') : config('services.hacienda.client_id_stag'));
    $recepUrl = config('services.hacienda.recepcion_url')
        ?? ($env === 'prod' ? config('services.hacienda.recepcion_url_prod') : config('services.hacienda.recepcion_url_stag'));

    $this->line('  Token URL: ' . $tokenUrl);
    $this->line('  Client ID: ' . $clientId);
    $this->line('  Recepcion URL: ' . $recepUrl);

    // Basic validations
    $ok = true;
    if (!in_array($env, ['stag','prod'], true)) {
        $this->error('  HACIENDA_ENV debe ser stag|prod');
        $ok = false;
    }
    if (empty($prov) || empty($act)) {
        $this->warn('  ProveedorSistemas o CodigoActividadEmisor vacíos (requeridos por v4.4)');
    }
    if (empty($tipo) || empty($num)) {
        $this->warn('  Emisor Tipo/Numero vacíos; se recomienda configurarlos y que coincidan con el certificado');
    }
    if (empty($p12) && (empty($pemCert) || empty($pemKey)) && empty($p12b64)) {
        $this->warn('  No has configurado ni .p12 ni PEM ni .p12-base64 (HACIENDA_CERT_P12_PATH o HACIENDA_CERT_CERT_PEM_PATH/HACIENDA_CERT_KEY_PEM_PATH o HACIENDA_CERT_P12_BASE64)');
        $ok = false;
    } elseif (!empty($p12)) {
        if (!is_file($resolved)) {
            $this->warn('  Archivo .p12 no encontrado en: ' . $resolved);
            $ok = false;
        }
    } elseif (!empty($p12b64)) {
        $this->line('  Usarás .p12 desde variable base64 (no se requiere archivo).');
    } else {
        $resCert = function_exists('base_path') ? base_path($pemCert) : $pemCert;
        $resKey  = function_exists('base_path') ? base_path($pemKey) : $pemKey;
        if (!is_file($resCert)) { $this->warn('  PEM cert no encontrado: ' . $resCert); $ok = false; }
        if (!is_file($resKey))  { $this->warn('  PEM key no encontrada: ' . $resKey);  $ok = false; }
    }
    if (empty(config('services.hacienda.username')) || empty(config('services.hacienda.password'))) {
        $this->warn('  HACIENDA_USERNAME/HACIENDA_PASSWORD no configurados');
    }

    if (!$ok) {
        $this->warn('Revisa las advertencias/errores anteriores.');
    } else {
        $this->info('Configuración básica OK.');
    }

    // Optional: attempt signing a tiny XML
    if ($this->option('sign')) {
        $this->line('  Probando firma XML con el certificado configurado...');
        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->loadXML('<Test xmlns="urn:test"><Data>ok</Data></Test>');
            $signer = new \App\Services\SignXmlService();
            $signer->sign($dom, null);
            $this->info('  Firma OK.');
        } catch (\Throwable $e) {
            $this->error('  Firma FALLÓ: ' . get_class($e) . ': ' . $e->getMessage());
            $trace = collect(explode("\n", $e->getTraceAsString()))->take(5)->implode("\n");
            $this->line('  Trace (top): ' . $trace);
            $this->warn('  Sugerencias:');
            $this->line('   - Si la contraseña tiene #, !, %, etc., envuélvela entre comillas en .env (p. ej., "Mi#Clave!")');
            $this->line('   - Verifica que el .p12 corresponde al certificado del emisor y que no está corrupto');
            $this->line('   - Si usas ruta relativa, prueba con ruta absoluta; valida permisos de lectura');
        }
    }

    // Optional: attempt to get token (will call remote)
    if ($this->option('token')) {
        $this->line('  Probando obtención de token...');
        try {
            $svc = new \App\Services\HaciendaTokenService();
            $token = $svc->getAccessToken();
            $this->info('  Token OK (longitud=' . strlen($token) . ').');
        } catch (\Throwable $e) {
            $this->error('  Token FALLÓ: ' . $e->getMessage());
        }
    }
})->purpose('Validate Hacienda configuration, certificate, and optionally test signing/token');

// Print configured certificate subject/issuer/serial and a guessed identification number
Artisan::command('hacienda:cert:info', function () {
    $this->info('Hacienda certificate info');
    try {
        $svc = new \App\Services\SignXmlService();
        $ref = new \ReflectionClass($svc);
        $m = $ref->getMethod('loadCertificateAndKey');
        $m->setAccessible(true);
        [$certPem, $pkeyPem] = $m->invoke($svc);

        $x = @openssl_x509_read($certPem);
        if ($x === false) { throw new \RuntimeException('Unable to read certificate'); }
        $info = @openssl_x509_parse($x) ?: [];
        $subject = $info['subject'] ?? [];
        $issuer = $info['issuer'] ?? [];
        $serialHex = $info['serialNumberHex'] ?? null;
        $serialDec = $info['serialNumber'] ?? null;
        $subjectName = $info['name'] ?? null;
        $issuerName = null;
        // Build readable DNs
        $buildDn = function(array $parts) {
            if (empty($parts)) return '(n/a)';
            $order = ['CN','OU','O','C','L','ST','E','serialNumber','SN','GIVENNAME'];
            $seen = [];
            $pairs = [];
            foreach ($order as $k) {
                if (isset($parts[$k])) { $pairs[] = $k . '=' . (is_array($parts[$k]) ? reset($parts[$k]) : $parts[$k]); $seen[$k]=true; }
            }
            foreach ($parts as $k=>$v) {
                if (!isset($seen[$k])) { $pairs[] = $k . '=' . (is_array($v) ? reset($v) : $v); }
            }
            return implode(',', $pairs);
        };
        $subjectDn = $subjectName ?: $buildDn($subject);
        $issuerDn  = $buildDn($issuer);

        // Guess identification number from subject fields (serialNumber or CN or email)
        $candidates = [];
        if (!empty($subject['serialNumber'])) { $candidates[] = (string)$subject['serialNumber']; }
        if (!empty($subject['CN'])) { $candidates[] = (string)$subject['CN']; }
        if (!empty($subject['emailAddress'])) { $candidates[] = (string)$subject['emailAddress']; }
        $idGuess = null;
        foreach ($candidates as $c) {
            if (preg_match('/(\d{9,12})/', $c, $mch)) { $idGuess = $mch[1]; break; }
        }

        $this->line('  Subject DN: ' . $subjectDn);
        $this->line('  Issuer  DN: ' . $issuerDn);
        $this->line('  Cert Serial (dec): ' . ($serialDec ?? '(n/a)'));
        $this->line('  Cert Serial (hex): ' . ($serialHex ?? '(n/a)'));
        $this->line('  Guessed ID from cert: ' . ($idGuess ?: '(no encontrado)'));

        $emisorTipo = config('services.hacienda.emisor_tipo');
        $emisorNumero = config('services.hacienda.emisor_numero');
        $this->line('  Config Emisor Tipo/Numero: ' . ($emisorTipo ?: '(vacío)') . ' / ' . ($emisorNumero ?: '(vacío)'));
        $this->warn('Compare el "Guessed ID" con Emisor Numero y con el Emisor del XML generado. Deben coincidir.');
    } catch (\Throwable $e) {
        $this->error('Error leyendo certificado: ' . $e->getMessage());
    }
})->purpose('Mostrar sujeto/emisor del certificado y posible cédula a validar con Emisor');
