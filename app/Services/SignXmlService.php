<?php

namespace App\Services;

use DOMDocument;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

class SignXmlService
{
    /**
     * Sign an XML DOMDocument using a .p12/.pfx certificate (RSA-SHA256, enveloped signature).
     * - Looks up HACIENDA_CERT_P12_PATH and HACIENDA_CERT_PASSWORD from config/services or env.
     * - Appends ds:Signature to the document element.
     * - Uses c14n (20010315) canonicalization and SHA-256 digest.
     *
     * @param DOMDocument $dom The XML document to sign (root must be the comprobante).
     * @param string|null $referenceId Optional Id value of the root element. If provided, ensure the root has attribute Id=$referenceId.
     * @return DOMDocument The same DOM instance with ds:Signature appended.
     * @throws \RuntimeException On any IO/crypto/signing error.
     */
    public function sign(DOMDocument $dom, ?string $referenceId = null): DOMDocument
    {
    [$certPem, $pkeyPem, $extraCerts] = $this->loadCertificateAndKey();

    // Prepare certificate details (DER, subject, issuer, serial, digest)
    $certX = @openssl_x509_read($certPem);
    if ($certX === false) {
        throw new \RuntimeException('Unable to parse X509 certificate from .p12');
    }
    $certInfo = @openssl_x509_parse($certX) ?: [];
    $subjectName = isset($certInfo['name']) ? $certInfo['name'] : ($this->buildDn($certInfo['subject'] ?? []) ?? '');
    $issuerName = isset($certInfo['issuer']) ? $this->buildDn($certInfo['issuer']) : '';
    $serialHex = $certInfo['serialNumberHex'] ?? null;
    $serialDec = $certInfo['serialNumber'] ?? null;
    if (!$serialDec && $serialHex) {
        // Convert hex to decimal string (avoid overflow using BCMath if available)
        $serialDec = $this->hexToDecString($serialHex);
    }
    // Get DER bytes for digest and KeyInfo/X509Certificate
    $der = $this->pemToDer($certPem);
    $certDigestSha1B64 = base64_encode(hash('sha1', $der, true));
    $certDigestB64 = base64_encode(hash('sha256', $der, true));
    $certB64 = base64_encode($der);

    // Initialize DSig: prefer Exclusive C14N for SignedInfo (common requirement for Hacienda)
    $dsig = new XMLSecurityDSig();
    $dsig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);

    $root = $dom->documentElement;
    // Ensure root element has no generic Id attribute (not allowed by Hacienda XSD for many docs)
    // and also to force Reference URI="" style instead of #Id
    if ($root instanceof \DOMElement && $root->hasAttribute('Id')) {
        $root->removeAttribute('Id');
    }

    // Prepare namespaces and IDs
    $dsNs = 'http://www.w3.org/2000/09/xmldsig#';
    $xadesNs = 'http://uri.etsi.org/01903/v1.3.2#';
    // Use IDs similar to common Hacienda examples
    $idBase = $this->uuid4();
    $sigId = 'id-' . $idBase;
    $signedPropsId = 'xades-id-' . $idBase;

            // Build XAdES as DOM nodes (in the same $dom), initially detached
            $objNode = $dom->createElementNS($dsNs, 'ds:Object');
            // Ensure xades prefix is declared in this subtree for stable C14N when detached/attached
            $objNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xades', $xadesNs);
            $qual = $dom->createElementNS($xadesNs, 'xades:QualifyingProperties');
            $qual->setAttribute('Target', '#' . $sigId);
            $signedProps = $dom->createElementNS($xadesNs, 'xades:SignedProperties');
            $signedProps->setAttribute('Id', $signedPropsId);
            // Declare ds prefix in SignedProperties subtree as it's used by child elements
            $signedProps->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', $dsNs);
            $ssp = $dom->createElementNS($xadesNs, 'xades:SignedSignatureProperties');
            $signingTime = (new \DateTimeImmutable('now'))->format('c');
            $ssp->appendChild($dom->createElementNS($xadesNs, 'xades:SigningTime', $signingTime));
            $sc = $dom->createElementNS($xadesNs, 'xades:SigningCertificate');
            $certEl = $dom->createElementNS($xadesNs, 'xades:Cert');
            $cd = $dom->createElementNS($xadesNs, 'xades:CertDigest');
            // Many validators (including Hacienda) expect SHA-1 for xades:SigningCertificate/CertDigest
            $cd->appendChild($this->el($dom, $dsNs, 'ds:DigestMethod', null, ['Algorithm' => 'http://www.w3.org/2000/09/xmldsig#sha1']));
            $cd->appendChild($dom->createElementNS($dsNs, 'ds:DigestValue', $certDigestSha1B64));
            $certEl->appendChild($cd);
            $issSer = $dom->createElementNS($xadesNs, 'xades:IssuerSerial');
            if ($issuerName !== '') {
                    $issSer->appendChild($dom->createElementNS($dsNs, 'ds:X509IssuerName', $issuerName));
            }
            if ($serialDec) {
                    $issSer->appendChild($dom->createElementNS($dsNs, 'ds:X509SerialNumber', $serialDec));
            }
            $certEl->appendChild($issSer);
            $sc->appendChild($certEl);
            $ssp->appendChild($sc);
            // XAdES-BES does not require an explicit policy; many CR implementations use SignaturePolicyImplied
            $spi = $dom->createElementNS($xadesNs, 'xades:SignaturePolicyIdentifier');
            $spi->appendChild($dom->createElementNS($xadesNs, 'xades:SignaturePolicyImplied'));
            $ssp->appendChild($spi);
            $signedProps->appendChild($ssp);
            $qual->appendChild($signedProps);
            $objNode->appendChild($qual);
            // Attach Object under root BEFORE adding references so SignedProperties is part of the document
            $root->appendChild($objNode);

    // Add References with diagnostics
    try {
        // 1) Reference to the full document, enveloped-signature + EXC-C14N, SHA-256, empty URI
        $dsig->addReference(
            $root,
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N],
            ['uri' => '']
        );
        if (function_exists('logger')) { logger()->debug('SignXmlService: added reference to root'); }
    } catch (\Throwable $e) {
        if (function_exists('logger')) { logger()->error('addReference(root) failed: ' . $e->getMessage()); }
        throw $e;
    }
    try {
        // 2) Reference to SignedProperties (XAdES). Use Exclusive C14N
        $dsig->addReference(
            $signedProps,
            XMLSecurityDSig::SHA256,
            [XMLSecurityDSig::EXC_C14N],
            [
                'id_name' => 'Id',
                'overwrite' => false,
                'type' => 'http://uri.etsi.org/01903#SignedProperties',
                'uri' => '#' . $signedPropsId,
            ]
        );
        if (function_exists('logger')) { logger()->debug('SignXmlService: added reference to SignedProperties #' . $signedPropsId); }
    } catch (\Throwable $e) {
        if (function_exists('logger')) { logger()->error('addReference(SignedProperties) failed: ' . $e->getMessage()); }
        throw $e;
    }

    // Private key
    $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
    $objKey->loadKey($pkeyPem, false);

    // Sign SignedInfo (this creates signature node in memory)
    try {
        $dsig->sign($objKey);
    } catch (\Throwable $e) {
        if (function_exists('logger')) { logger()->error('XMLDSig sign() failed: ' . $e->getMessage()); }
        throw $e;
    }
    // Append signature to document first, so sigNode belongs to $dom
    try {
        $dsig->appendSignature($root);
    } catch (\Throwable $e) {
        if (function_exists('logger')) { logger()->error('appendSignature() failed: ' . $e->getMessage()); }
        throw $e;
    }
    // Include certificate (basic X509Data) now that sigNode is in $dom
    try {
        // Add leaf certificate
        $dsig->add509Cert($certPem);
        // Add chain certificates if available to help Hacienda validate trust
        if (is_array($extraCerts) && !empty($extraCerts)) {
            foreach ($extraCerts as $caPem) {
                if (is_string($caPem) && trim($caPem) !== '') {
                    $dsig->add509Cert($caPem);
                }
            }
        }
    } catch (\Throwable $e) {
        if (function_exists('logger')) { logger()->error('add509Cert() failed: ' . $e->getMessage()); }
        throw $e;
    }
    // Assign Signature Id to match xades:QualifyingProperties/@Target
    // Important: set on the inserted Signature element in the DOM (not internal template)
    $sigNodes = $root->getElementsByTagNameNS($dsNs, 'Signature');
    $sigEl = $sigNodes->item($sigNodes->length - 1);
    if ($sigEl instanceof \DOMElement) {
    $sigEl->setAttribute('Id', $sigId);
    }
    // Move ds:Object (with xades:QualifyingProperties) under the actual Signature element
    try {
        if (!$sigEl instanceof \DOMElement) {
            throw new \RuntimeException('Signature element not found after appendSignature');
        }
        $ownerDoc = $sigEl->ownerDocument ?? $dom;
        // Prefer adoptNode to transfer ownership without cloning; fallback to importNode if needed
        $moved = method_exists($ownerDoc, 'adoptNode') ? $ownerDoc->adoptNode($objNode) : false;
        if (!$moved) {
            // If adoptNode failed or not supported, import a deep clone and remove the original later
            $moved = $ownerDoc->importNode($objNode, true);
            // Remove original Object from root to keep a single ds:Object
            if ($objNode->parentNode) {
                $objNode->parentNode->removeChild($objNode);
            }
        }
    $sigEl->appendChild($moved);
    } catch (\Throwable $e) {
        if (function_exists('logger')) { logger()->error('Moving XAdES Object under Signature failed: ' . $e->getMessage()); }
        throw $e;
    }

    // Set xades:DataObjectFormat MimeType to text/xml if present
    try {
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('ds', $dsNs);
        $xp->registerNamespace('xades', $xadesNs);
        foreach ($xp->query('//xades:DataObjectFormat/xades:MimeType') as $mt) {
            if ($mt instanceof \DOMElement) {
                $mt->nodeValue = 'text/xml';
            }
        }
    } catch (\Throwable $e) {
        // ignore optional
    }
    return $dom;
    }

    /**
     * Optional local verification of the signature using the X509 cert embedded or from the .p12.
     * Returns true when signature and references are valid.
     */
    public function verify(DOMDocument $dom): bool
    {
        try {
            $dsig = new XMLSecurityDSig();
            $signatureNode = $dsig->locateSignature($dom);
            if (!$signatureNode) return false;
            $dsig->canonicalizeSignedInfo();

            // Validate referenced nodes digests
            if (!$dsig->validateReference()) return false;

            // Load public key from embedded KeyInfo if available; fall back to .p12 cert
            $key = $dsig->locateKey();
            if (!$key) return false;

            // Try to load key from embedded cert. If that fails, use configured cert
            $isVerified = false;
            try {
                $isVerified = $dsig->verify($key);
            } catch (\Throwable $e) {
                $isVerified = false;
            }
            if ($isVerified) return true;

            // Fallback to configured cert
            [$cert] = $this->loadCertificateAndKey();
            $key->loadKey($cert, false, true); // load as certificate
            return $dsig->verify($key);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Load certificate and private key from configured .p12/.pfx
    * @return array{0:string,1:string,2:array} [cert, pkey, extraCerts]
     */
    private function loadCertificateAndKey(): array
    {
        // Prefer explicit PEM cert+key if configured
        $certPemPath = (string) (config('services.hacienda.cert_cert_pem_path') ?? getenv('HACIENDA_CERT_CERT_PEM_PATH') ?: '');
        $keyPemPath  = (string) (config('services.hacienda.cert_key_pem_path') ?? getenv('HACIENDA_CERT_KEY_PEM_PATH') ?: '');
        $keyPass     = (string) (config('services.hacienda.cert_key_passphrase') ?? getenv('HACIENDA_CERT_KEY_PASSPHRASE') ?: '');

        if ($certPemPath !== '' && $keyPemPath !== '') {
            $certResolved = $this->resolvePath($certPemPath);
            $keyResolved  = $this->resolvePath($keyPemPath);
            if (!is_file($certResolved)) {
                throw new \RuntimeException('PEM certificate file not found at: ' . $certResolved);
            }
            if (!is_file($keyResolved)) {
                throw new \RuntimeException('PEM private key file not found at: ' . $keyResolved);
            }
            $certPem = @file_get_contents($certResolved);
            $keyPem  = @file_get_contents($keyResolved);
            if ($certPem === false || $keyPem === false) {
                throw new \RuntimeException('Unable to read PEM cert/key files.');
            }
            // If key is encrypted, try to decrypt/export to unencrypted PEM in-memory
            if (str_contains($keyPem, 'ENCRYPTED') || str_contains($keyPem, 'BEGIN ENCRYPTED PRIVATE KEY')) {
                $pk = @openssl_pkey_get_private($keyPem, $keyPass ?: null);
                if ($pk === false) {
                    throw new \RuntimeException('Failed to read encrypted private key. Check HACIENDA_CERT_KEY_PASSPHRASE.');
                }
                $exported = null;
                if (!@openssl_pkey_export($pk, $exported)) {
                    throw new \RuntimeException('Failed to export private key from PEM.');
                }
                $keyPem = $exported;
            }
            return [$certPem, $keyPem, []];
        }

    // Otherwise use PKCS#12 (.p12/.pfx)
    $p12Path = (string) (config('services.hacienda.cert_p12_path') ?? getenv('HACIENDA_CERT_P12_PATH') ?: '');
    $password = (string) (config('services.hacienda.cert_password') ?? getenv('HACIENDA_CERT_PASSWORD') ?: '');
    $p12Base64 = (string) (config('services.hacienda.cert_p12_base64') ?? getenv('HACIENDA_CERT_P12_BASE64') ?: '');

        $p12Data = null;
        if ($p12Path !== '') {
            $resolvedPath = $this->resolvePath($p12Path);
            if (!is_file($resolvedPath)) {
                throw new \RuntimeException('Hacienda certificate file not found at: ' . $resolvedPath);
            }
            $p12Data = @file_get_contents($resolvedPath);
            if ($p12Data === false) {
                throw new \RuntimeException('Unable to read certificate file: ' . $resolvedPath);
            }
        } elseif ($p12Base64 !== '') {
            $decoded = base64_decode($p12Base64, true);
            if ($decoded === false) {
                throw new \RuntimeException('Invalid HACIENDA_CERT_P12_BASE64 (base64 decode failed).');
            }
            $p12Data = $decoded;
        } else {
            throw new \RuntimeException('Hacienda certificate not configured. Provide HACIENDA_CERT_P12_PATH or HACIENDA_CERT_P12_BASE64, or PEM variables.');
        }

        if (!extension_loaded('openssl')) {
            throw new \RuntimeException('The OpenSSL extension is required to read the .p12 certificate.');
        }

        $certs = [];
        // Clear any lingering OpenSSL errors before attempting to read
        while (\function_exists('openssl_error_string') && openssl_error_string()) {}
        if (!@openssl_pkcs12_read($p12Data, $certs, $password)) {
            // Collect OpenSSL errors, if any, to aid troubleshooting (e.g., "mac verify failure" for wrong password)
            $errs = [];
            if (\function_exists('openssl_error_string')) {
                while ($e = openssl_error_string()) {
                    $errs[] = $e;
                }
            }
            $msg = 'Failed to parse .p12/.pfx. Check HACIENDA_CERT_PASSWORD and file integrity.';
            if (!empty($errs)) {
                $msg .= ' OpenSSL: ' . implode(' | ', $errs);
            }
            // Common on OpenSSL 3 when legacy ciphers are used in the PKCS#12 file
            if (stripos($msg, 'unsupported') !== false) {
                $msg .= ' Hint: Your PHP/OpenSSL may not support legacy PKCS#12 ciphers. Convert the .p12 to PEM and set HACIENDA_CERT_CERT_PEM_PATH and HACIENDA_CERT_KEY_PEM_PATH.';
            }
            throw new \RuntimeException($msg);
        }
        if (empty($certs['cert']) || empty($certs['pkey'])) {
            throw new \RuntimeException('The .p12 does not contain both certificate and private key.');
        }
        $extra = [];
        if (!empty($certs['extracerts']) && is_array($certs['extracerts'])) {
            // Normalize to array of PEM strings
            foreach ($certs['extracerts'] as $ex) {
                if (is_string($ex) && trim($ex) !== '') { $extra[] = $ex; }
            }
        }
        return [$certs['cert'], $certs['pkey'], $extra];
    }

    private function resolvePath(string $path): string
    {
        // Absolute path (Windows drive, UNC path, or Unix style) without regex to avoid PCRE issues
        $isWindowsDrive = strlen($path) >= 3
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && ($path[2] === '\\' || $path[2] === '/');
        $isUnc = str_starts_with($path, '\\');
        $isUnixAbs = str_starts_with($path, '/');
        if ($isWindowsDrive || $isUnc || $isUnixAbs) {
            return $path;
        }
        // Try Laravel base_path if available
        if (function_exists('base_path')) {
            return base_path($path);
        }
        return $path;
    }

    private function pemToDer(string $pem): string
    {
        $pem = trim($pem);
        if (str_starts_with($pem, '-----BEGIN')) {
            $pem = preg_replace('/-----BEGIN[^-]+-----|-----END[^-]+-----|\s+/', '', $pem);
        }
        $der = base64_decode($pem, true);
        if ($der === false) {
            throw new \RuntimeException('Failed to decode PEM certificate to DER');
        }
        return $der;
    }

    private function buildDn(array $parts): ?string
    {
        if (empty($parts)) return null;
        // Build RFC2253-ish DN: cn=...,ou=...,o=...,c=CR
        $order = ['cn','OU','ou','O','o','C','c','givenName','sn','serialNumber'];
        $flat = [];
        foreach ($order as $k) {
            if (isset($parts[$k])) {
                $label = strtolower($k);
                $flat[] = $label . '=' . (is_array($parts[$k]) ? reset($parts[$k]) : $parts[$k]);
            }
        }
        // include any remaining fields
        foreach ($parts as $k => $v) {
            if (is_int(array_search(strtolower($k), array_map('strtolower', $order), true))) continue;
            $label = strtolower($k);

            $flat[] = $label . '=' . (is_array($v) ? reset($v) : $v);
        }
        return implode(',', $flat);
    }

    private function hexToDecString(string $hex): string
    {
        $hex = preg_replace('/^0x/i', '', $hex);
        if (function_exists('gmp_init')) {
            return gmp_strval(gmp_init($hex, 16), 10);
        }
        if (function_exists('bcadd')) {
            $dec = '0';
            $len = strlen($hex);
            for ($i = 0; $i < $len; $i++) {
                $dec = bcmul($dec, '16');
                $dec = bcadd($dec, (string) hexdec($hex[$i]));
            }
            return ltrim($dec, '0') ?: '0';
        }
        // Fallback manual conversion (risk of overflow for very large numbers)
        $dec = '0';
        foreach (str_split($hex) as $char) {
            $dec = (string) ((int)$dec * 16 + hexdec($char));
        }
        return $dec;
    }

    private function el(DOMDocument $dom, string $ns, string $name, ?string $text = null, array $attrs = []): \DOMElement
    {
        $el = $dom->createElementNS($ns, $name, $text);
        foreach ($attrs as $k => $v) { $el->setAttribute($k, $v); }
        return $el;
    }

    private function uuid4(): string
    {
        $data = random_bytes(16);
        // Set version to 0100
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        // Set variant to 10xx
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
