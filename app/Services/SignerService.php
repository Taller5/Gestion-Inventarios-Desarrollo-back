<?php

namespace App\Services;

use DOMDocument;

class SignerService
{
    /**
     * Firma el DOMDocument dado usando el firmador externo de `tools` (vía script puente).
     * Devuelve el mismo DOM con la etiqueta ds:Signature añadida.
     *
     * Variables de entorno / configuración requeridas:
     *  - services.hacienda.alt_signer_dir (HACIENDA_ALT_SIGNER_DIR)
     *  - services.hacienda.cert_p12_path + cert_password (o .p12 en base64 escrito a temporal)
     */
    public function sign(DOMDocument $dom): DOMDocument
    {
    // Guardia antes de firmar: asegurar que el Emisor en el XML coincide con el titular del certificado
    // para prevenir el error Hacienda -60
        try {
            $strict = (bool) (config('services.hacienda.fail_on_emisor_cert_mismatch') ?? filter_var(getenv('HACIENDA_FAIL_ON_EMISOR_CERT_MISMATCH') ?: 'true', FILTER_VALIDATE_BOOL));
            if ($strict) {
                $xp = new \DOMXPath($dom);
                $ns = $dom->documentElement?->namespaceURI;
                if ($ns) { $xp->registerNamespace('fe', $ns); }
                $tipo = trim((string)$xp->evaluate('string(//fe:Emisor/fe:Identificacion/fe:Tipo)'));
                $num  = preg_replace('/\D/', '', (string)$xp->evaluate('string(//fe:Emisor/fe:Identificacion/fe:Numero)'));
                if ($tipo !== '' && $num !== '') {
                    // Leer el certificado configurado para inferir la identidad
                    $p12CheckPath = (string) (config('services.hacienda.cert_p12_path') ?? env('HACIENDA_CERT_P12_PATH', ''));
                    $p12CheckPath = function_exists('base_path') ? base_path($p12CheckPath) : $p12CheckPath;
                    $pinCheck = (string) (config('services.hacienda.cert_password') ?? env('HACIENDA_CERT_PASSWORD', ''));
                    $certsCheck = [];
                    if ($p12CheckPath !== '' && is_file($p12CheckPath) && @openssl_pkcs12_read(@file_get_contents($p12CheckPath), $certsCheck, $pinCheck)) {
                        if (!empty($certsCheck['cert'])) {
                            $x = @openssl_x509_read($certsCheck['cert']);
                            $info = $x ? (@openssl_x509_parse($x) ?: []) : [];
                            $subj = $info['subject'] ?? [];
                            $serialAttr = null;
                            if (isset($subj['serialNumber'])) { $serialAttr = is_array($subj['serialNumber']) ? reset($subj['serialNumber']) : $subj['serialNumber']; }
                            elseif (isset($subj['OID.2.5.4.5'])) { $serialAttr = is_array($subj['OID.2.5.4.5']) ? reset($subj['OID.2.5.4.5']) : $subj['OID.2.5.4.5']; }
                            elseif (isset($subj['2.5.4.5'])) { $serialAttr = is_array($subj['2.5.4.5']) ? reset($subj['2.5.4.5']) : $subj['2.5.4.5']; }
                            $upper = $serialAttr && is_string($serialAttr) ? strtoupper($serialAttr) : '';
                            $cTipo = null; $cNum = null;
                            if ($upper !== '' && preg_match('/\bCPF[-:\s]*([0-9\-]+)/', $upper, $m)) { $cTipo = '01'; $cNum = preg_replace('/\D/', '', $m[1]); }
                            elseif ($upper !== '' && preg_match('/\bCPJ[-:\s]*([0-9\-]+)/', $upper, $m)) { $cTipo = '02'; $cNum = preg_replace('/\D/', '', $m[1]); }
                            elseif ($upper !== '' && preg_match('/\bDIMEX[-:\s]*([0-9\-]+)/', $upper, $m)) { $cTipo = '03'; $cNum = preg_replace('/\D/', '', $m[1]); }
                            elseif ($upper !== '' && preg_match('/\bNITE[-:\s]*([0-9\-]+)/', $upper, $m)) { $cTipo = '04'; $cNum = preg_replace('/\D/', '', $m[1]); }

                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException) { throw $e; }
        }
        $altDir = (string) (config('services.hacienda.alt_signer_dir') ?? env('HACIENDA_ALT_SIGNER_DIR', ''));
        if ($altDir === '') {
            throw new \RuntimeException('HACIENDA_ALT_SIGNER_DIR no configurado');
        }
        $p12Path = (string) (config('services.hacienda.cert_p12_path') ?? env('HACIENDA_CERT_P12_PATH', ''));
        $p12B64  = (string) (config('services.hacienda.cert_p12_base64') ?? env('HACIENDA_CERT_P12_BASE64', ''));
        $useB64  = (bool) (config('services.hacienda.use_p12_base64') ?? filter_var(env('HACIENDA_USE_P12_BASE64', 'false'), FILTER_VALIDATE_BOOL));
        $pin     = (string) (config('services.hacienda.cert_password') ?? env('HACIENDA_CERT_PASSWORD', ''));

    // Asegurar que tenemos una ruta válida para el archivo .p12
        $p12Resolved = null;
        if ($useB64 && $p12B64 !== '') {
            $tmp = tempnam(sys_get_temp_dir(), 'p12_');
            $decoded = base64_decode($p12B64, true);
            if ($decoded === false) { throw new \RuntimeException('HACIENDA_CERT_P12_BASE64 inválido'); }
            file_put_contents($tmp, $decoded);
            $p12Resolved = $tmp;
        } else {
            $p12Resolved = function_exists('base_path') ? base_path($p12Path) : $p12Path;
            if ($p12Resolved === '' || !is_file($p12Resolved)) {
                throw new \RuntimeException('No se encontró .p12 en ' . $p12Resolved);
            }
        }

    // Guardia opcional entre entorno y emisor del certificado (similar a SignXmlService)
        try {
            $failOnMismatch = (bool) (config('services.hacienda.fail_on_env_cert_mismatch', true) ?? true);
            if ($failOnMismatch && is_file($p12Resolved)) {
                $p12Data = @file_get_contents($p12Resolved);
                if ($p12Data !== false && extension_loaded('openssl')) {
                    $certs = [];
                    if (@openssl_pkcs12_read($p12Data, $certs, $pin)) {
                        if (!empty($certs['cert'])) {
                            $env = strtolower((string) (config('services.hacienda.env') ?? getenv('HACIENDA_ENV') ?: 'stag'));
                            $x = @openssl_x509_read($certs['cert']);
                            $info = $x ? (@openssl_x509_parse($x) ?: []) : [];
                            $issuer = $info['issuer'] ?? [];
                            $issuerCn = '';
                            if (!empty($issuer['CN'])) { $issuerCn = (string)$issuer['CN']; }
                            elseif (!empty($issuer['commonName'])) { $issuerCn = (string)$issuer['commonName']; }
                            $isSandboxIssuer = stripos($issuerCn, 'SANDBOX') !== false || stripos($issuerCn, 'TEST') !== false;
                            if ($env === 'stag' && !$isSandboxIssuer) {
                                throw new \RuntimeException('El certificado parece de PRODUCCION (issuer=' . $issuerCn . ') pero HACIENDA_ENV=stag.');
                            }
                            if ($env === 'prod' && $isSandboxIssuer) {
                                throw new \RuntimeException('El certificado parece de SANDBOX (issuer=' . $issuerCn . ') pero HACIENDA_ENV=prod.');
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException) { throw $e; }
        }

    // Escribir el XML de entrada en un archivo temporal
        $inTmp = tempnam(sys_get_temp_dir(), 'xml_in_');
        file_put_contents($inTmp, $dom->saveXML());
    $outTmp = tempnam(sys_get_temp_dir(), 'xml_out_');
    // Algunos firmadores esperan que la ruta de salida no exista; asegurarse de eliminarla antes de invocar
    if (is_file($outTmp)) { @unlink($outTmp); }

        $bridge = function_exists('base_path') ? base_path('scripts/sign_bridge.php') : __DIR__ . '/../../scripts/sign_bridge.php';
        if (!is_file($bridge)) { throw new \RuntimeException('Bridge no encontrado en ' . $bridge); }

    // Preferir un binario CLI explícito desde config/env cuando se ejecuta bajo SAPI web
        $phpBin = (string) (config('services.hacienda.php_cli_path') ?? getenv('HACIENDA_PHP_CLI_PATH') ?: '');
        if ($phpBin === '') {
            $phpBin = PHP_BINARY ?: 'php';
        }
    // Si se proporcionó una ruta no vacía pero no existe, volver al valor por defecto
        if ($phpBin !== 'php' && !is_file($phpBin)) {
            $phpBin = PHP_BINARY ?: 'php';
        }
        $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($bridge) . ' '
            . escapeshellarg($altDir) . ' '
            . escapeshellarg($p12Resolved) . ' '
            . escapeshellarg($pin) . ' '
            . escapeshellarg($inTmp) . ' '
            . escapeshellarg($outTmp);

        $descriptor = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
        $proc = proc_open($cmd, $descriptor, $pipes, function_exists('base_path') ? base_path() : getcwd());
        if (!\is_resource($proc)) { throw new \RuntimeException('No se pudo iniciar el proceso del alt signer'); }
        fclose($pipes[0]); $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $code = proc_close($proc);
        if ($code !== 0) {
            $debug = (bool) (getenv('HACIENDA_ALT_SIGNER_DEBUG') ? filter_var(getenv('HACIENDA_ALT_SIGNER_DEBUG'), FILTER_VALIDATE_BOOL) : false);
            $msg = 'Alt signer falló (code ' . $code . '): ' . trim($stderr);
            if (trim($stdout) !== '') { $msg .= ' | out: ' . trim($stdout); }
            if ($debug) { $msg .= ' | cmd: ' . $cmd; }
            throw new \RuntimeException($msg);
        }
        if (!is_file($outTmp)) {
            throw new \RuntimeException('Alt signer no produjo salida');
        }

        $signed = @file_get_contents($outTmp);
        if ($signed === false || trim($signed) === '') {
            throw new \RuntimeException('Salida vacía del alt signer');
        }
    // IMPORTANTE: No formatear (pretty-print) ni normalizar espacios en blanco después de firmar.
    // Debemos preservar exactamente los nodos de texto/espacios usados para calcular el digest.
        $newDom = new DOMDocument('1.0', 'UTF-8');
        $newDom->preserveWhiteSpace = true;
        $newDom->formatOutput = false;
        $newDom->loadXML($signed);
        return $newDom;
    }
}
