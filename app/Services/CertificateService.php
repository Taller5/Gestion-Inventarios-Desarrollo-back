<?php

namespace App\Services;

class CertificateService
{
    /**
     * Devuelve el Common Name (CN) del sujeto del certificado si está disponible.
     */
    public function getCertificateSubjectCN(): ?string
    {
        try {
            [$certPem] = $this->loadCertificateAndKey();
            $certX = @openssl_x509_read($certPem);
            if ($certX === false) return null;
            $info = @openssl_x509_parse($certX) ?: [];
            $subject = $info['subject'] ?? [];
            if (isset($subject['CN'])) {
                return is_array($subject['CN']) ? (string)reset($subject['CN']) : (string)$subject['CN'];
            }
            if (isset($subject['commonName'])) {
                return is_array($subject['commonName']) ? (string)reset($subject['commonName']) : (string)$subject['commonName'];
            }
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Devuelve el Common Name (CN) del emisor (ISSUER) del certificado.
     */
    public function getCertificateIssuerCN(): ?string
    {
        try {
            [$certPem] = $this->loadCertificateAndKey();
            $certX = @openssl_x509_read($certPem);
            if ($certX === false) return null;
            $info = @openssl_x509_parse($certX) ?: [];
            $issuer = $info['issuer'] ?? [];
            if (isset($issuer['CN'])) {
                return is_array($issuer['CN']) ? (string)reset($issuer['CN']) : (string)$issuer['CN'];
            }
            if (isset($issuer['commonName'])) {
                return is_array($issuer['commonName']) ? (string)reset($issuer['commonName']) : (string)$issuer['commonName'];
            }
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Devuelve un arreglo parseado de información del certificado similar a openssl_x509_parse,
     * incluyendo 'subject', 'issuer', 'serialNumber', 'serialNumberHex' y el 'cert' crudo.
     */
    public function getParsedCertificateInfo(): ?array
    {
        try {
            [$certPem] = $this->loadCertificateAndKey();
            $certX = @openssl_x509_read($certPem);
            if ($certX === false) return null;
            $info = @openssl_x509_parse($certX) ?: [];
            $info['cert'] = $certPem;
            return $info;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Extrae la identificación del titular del certificado (tipo/número) desde el certificado configurado.
     */
    public function getCertificateIdInfo(): ?array
    {
        try {
            [$certPem] = $this->loadCertificateAndKey();
            $certX = @openssl_x509_read($certPem);
            if ($certX === false) return null;
            $info = @openssl_x509_parse($certX) ?: [];
            $subject = $info['subject'] ?? [];
            $serialAttr = null;
            if (isset($subject['serialNumber'])) {
                $serialAttr = is_array($subject['serialNumber']) ? reset($subject['serialNumber']) : $subject['serialNumber'];
            } elseif (isset($subject['OID.2.5.4.5']) || isset($subject['2.5.4.5']) || isset($subject['X520SerialNumber'])) {
                foreach (['OID.2.5.4.5','2.5.4.5','X520SerialNumber'] as $oidKey) {
                    if (isset($subject[$oidKey])) {
                        $serialAttr = is_array($subject[$oidKey]) ? reset($subject[$oidKey]) : $subject[$oidKey];
                        break;
                    }
                }
            }
            $upper = $serialAttr && is_string($serialAttr) ? strtoupper($serialAttr) : '';
            $tipo = null; $numero = null;
            if ($upper !== '' && preg_match('/\bCPF[-:\s]*([0-9\-]+)/', $upper, $m)) {
                $tipo = '01';
                $numero = preg_replace('/\D/', '', $m[1]);
            } elseif ($upper !== '' && preg_match('/\bCPJ[-:\s]*([0-9\-]+)/', $upper, $m)) {
                $tipo = '02';
                $numero = preg_replace('/\D/', '', $m[1]);
            } elseif ($upper !== '' && preg_match('/\bDIMEX[-:\s]*([0-9\-]+)/', $upper, $m)) {
                $tipo = '03';
                $numero = preg_replace('/\D/', '', $m[1]);
            } elseif ($upper !== '' && preg_match('/\bNITE[-:\s]*([0-9\-]+)/', $upper, $m)) {
                $tipo = '04';
                $numero = preg_replace('/\D/', '', $m[1]);
            }
            // En caso de ausencia, derivar a partir del prefijo HACIENDA_USERNAME
            if (!$tipo || !$numero) {
                $user = (string) (config('services.hacienda.username') ?? getenv('HACIENDA_USERNAME') ?: '');
                if ($user !== '') {
                    $u = strtolower($user);
                    if (preg_match('/^cpf-([0-9\-]+)/', $u, $m)) { $tipo = '01'; $numero = preg_replace('/\D/', '', $m[1]); }
                    elseif (preg_match('/^cpj-([0-9\-]+)/', $u, $m)) { $tipo = '02'; $numero = preg_replace('/\D/', '', $m[1]); }
                    elseif (preg_match('/^dimex-([0-9\-]+)/', $u, $m)) { $tipo = '03'; $numero = preg_replace('/\D/', '', $m[1]); }
                    elseif (preg_match('/^nite-([0-9\-]+)/', $u, $m)) { $tipo = '04'; $numero = preg_replace('/\D/', '', $m[1]); }
                }
            }
            if ($tipo && $numero) { return ['tipo' => $tipo, 'numero' => $numero]; }
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Carga el certificado y la clave privada desde las rutas configuradas (.p12/.pfx o PEM).
     * Devuelve [certPem, pkeyPem, extraCertsPem[]]
     */
    private function loadCertificateAndKey(): array
    {
    // Preferir certificado+clave en formato PEM explícito si está configurado
        $certPemPath = (string) (config('services.hacienda.cert_cert_pem_path') ?? getenv('HACIENDA_CERT_CERT_PEM_PATH') ?: '');
        $keyPemPath  = (string) (config('services.hacienda.cert_key_pem_path') ?? getenv('HACIENDA_CERT_KEY_PEM_PATH') ?: '');
        $keyPass     = (string) (config('services.hacienda.cert_key_passphrase') ?? getenv('HACIENDA_CERT_KEY_PASSPHRASE') ?: '');

        if ($certPemPath !== '' && $keyPemPath !== '') {
            $certResolved = $this->resolvePath($certPemPath);
            $keyResolved  = $this->resolvePath($keyPemPath);
            if (!is_file($certResolved)) { throw new \RuntimeException('PEM certificate file not found at: ' . $certResolved); }
            if (!is_file($keyResolved)) { throw new \RuntimeException('PEM private key file not found at: ' . $keyResolved); }
            $certPem = @file_get_contents($certResolved);
            $keyPem  = @file_get_contents($keyResolved);
            if ($certPem === false || $keyPem === false) { throw new \RuntimeException('Unable to read PEM cert/key files.'); }
            if (str_contains($keyPem, 'ENCRYPTED')) {
                $pk = @openssl_pkey_get_private($keyPem, $keyPass ?: null);
                if ($pk === false) { throw new \RuntimeException('Failed to read encrypted private key.'); }
                $exported = null; if (!@openssl_pkey_export($pk, $exported)) { throw new \RuntimeException('Failed to export private key.'); }
                $keyPem = $exported;
            }
            return [$certPem, $keyPem, []];
        }

        $p12Path = (string) (config('services.hacienda.cert_p12_path') ?? getenv('HACIENDA_CERT_P12_PATH') ?: '');
        $password = (string) (config('services.hacienda.cert_password') ?? getenv('HACIENDA_CERT_PASSWORD') ?: '');
        $p12Base64 = (string) (config('services.hacienda.cert_p12_base64') ?? getenv('HACIENDA_CERT_P12_BASE64') ?: '');
        $useP12B64 = (bool) (config('services.hacienda.use_p12_base64') ?? filter_var(getenv('HACIENDA_USE_P12_BASE64') ?: 'false', FILTER_VALIDATE_BOOL));

        $p12Data = null;
        if ($p12Path !== '') {
            $resolvedPath = $this->resolvePath($p12Path);
            if (!is_file($resolvedPath)) { throw new \RuntimeException('Hacienda certificate file not found at: ' . $resolvedPath); }
            $p12Data = @file_get_contents($resolvedPath);
            if ($p12Data === false) { throw new \RuntimeException('Unable to read certificate file: ' . $resolvedPath); }
        } elseif ($p12Base64 !== '' && $useP12B64) {
            $decoded = base64_decode($p12Base64, true);
            if ($decoded === false) { throw new \RuntimeException('Invalid HACIENDA_CERT_P12_BASE64.'); }
            $p12Data = $decoded;
        } else {
            throw new \RuntimeException('Hacienda certificate not configured.');
        }

        if (!extension_loaded('openssl')) { throw new \RuntimeException('OpenSSL extension required.'); }

        $certs = [];
        while (\function_exists('openssl_error_string') && openssl_error_string()) {}
        if (!@openssl_pkcs12_read($p12Data, $certs, $password) || empty($certs['cert']) || empty($certs['pkey'])) {
            throw new \RuntimeException('Failed to parse .p12/.pfx.');
        }
        $extra = [];
        if (!empty($certs['extracerts']) && is_array($certs['extracerts'])) {
            foreach ($certs['extracerts'] as $ex) { if (is_string($ex) && trim($ex) !== '') { $extra[] = $ex; } }
        }
    // Guardia opcional entre entorno y emisor del certificado
        $failOnMismatch = (bool) (config('services.hacienda.fail_on_env_cert_mismatch', true) ?? true);
        if ($failOnMismatch) {
            try {
                $env = strtolower((string) (config('services.hacienda.env') ?? getenv('HACIENDA_ENV') ?: 'stag'));
                $x = @openssl_x509_read($certs['cert']);
                $info = $x ? (@openssl_x509_parse($x) ?: []) : [];
                $issuer = $info['issuer'] ?? [];
                $issuerCn = '';
                if (!empty($issuer['CN'])) { $issuerCn = (string)$issuer['CN']; }
                elseif (!empty($issuer['commonName'])) { $issuerCn = (string)$issuer['commonName']; }
                $isSandboxIssuer = stripos($issuerCn, 'SANDBOX') !== false || stripos($issuerCn, 'TEST') !== false;
                if ($env === 'stag' && !$isSandboxIssuer) { throw new \RuntimeException('Configured certificate appears to be PRODUCCION (issuer=' . $issuerCn . ') but HACIENDA_ENV=stag.'); }
                if ($env === 'prod' && $isSandboxIssuer) { throw new \RuntimeException('Configured certificate appears to be SANDBOX (issuer=' . $issuerCn . ') but HACIENDA_ENV=prod.'); }
            } catch (\Throwable $e) { if ($e instanceof \RuntimeException) { throw $e; } }
        }

        return [$certs['cert'], $certs['pkey'], $extra];
    }

    private function resolvePath(string $path): string
    {
        $isWindowsDrive = strlen($path) >= 3 && ctype_alpha($path[0]) && $path[1] === ':' && ($path[2] === '\\' || $path[2] === '/');
        $isUnc = str_starts_with($path, '\\');
        $isUnixAbs = str_starts_with($path, '/');
        if ($isWindowsDrive || $isUnc || $isUnixAbs) { return $path; }
        if (function_exists('base_path')) { return base_path($path); }
        return $path;
    }
}
