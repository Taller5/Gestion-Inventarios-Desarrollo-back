<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceXml;
use App\Models\HaciendaResponse;
use GuzzleHttp\Client;

class HaciendaRecepcionService
{
    public function __construct(
        private readonly HaciendaTokenService $tokenService = new HaciendaTokenService(),
    ) {}

    /**
     * Resuelve el endpoint base de Recepcion (terminando en .../recepcion) según env/config.
     * Acepta valores de configuración flexibles:
     *  - Endpoint completo: https://.../recepcion-sandbox/v1/recepcion o https://.../recepcion/v1/recepcion
     *  - Ruta base v1:  https://.../recepcion-sandbox/v1/ o https://.../recepcion/v1/
     */
    private function resolveRecepcionEndpoint(): string
    {
    // Solo STAG: preferir config explícita, si no usar endpoint canónico sandbox
        $configured = (string) (config('services.hacienda.recepcion_url')
            ?? config('services.hacienda.recepcion_url_stag')
            ?? env('HACIENDA_RECEPCION_URL', 'https://api-sandbox.comprobanteselectronicos.go.cr/recepcion/v1/recepcion'));

        $u = rtrim($configured, '/');
    // Asegurar que termina con /recepcion cuando se provee la ruta base v1
        if (!preg_match('#/recepcion$#i', $u)) {
            if (preg_match('#/(recepcion|recepcion-sandbox)/v1$#i', $u)) {
                $u .= '/recepcion';
            }
        }
        return $u;
    }

    public function submit(Invoice $invoice, InvoiceXml $invoiceXml): HaciendaResponse
    {
    // Solo STAG
    $env = 'stag';
        $url = $this->resolveRecepcionEndpoint();

        $token = $this->tokenService->getAccessToken();

    // Extraer Emisor desde el XML para garantizar consistencia con el documento firmado
        $emisorTipo = null; $emisorNumero = null;
        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->loadXML($invoiceXml->xml);
            $ns = $dom->documentElement?->namespaceURI;
            $xp = new \DOMXPath($dom);
            if ($ns) { $xp->registerNamespace('fe', $ns); }
            $tipoNode = $xp->evaluate('string(//fe:Emisor/fe:Identificacion/fe:Tipo)');
            $numNode  = $xp->evaluate('string(//fe:Emisor/fe:Identificacion/fe:Numero)');
            $tipoEval = trim((string)$tipoNode);
            $numEval  = preg_replace('/\D/', '', (string)$numNode);
            if ($tipoEval !== '' && $numEval !== '') {
                $emisorTipo = $tipoEval;
                $emisorNumero = $numEval;
            }
        } catch (\Throwable $e) {
            // ignorar, hacer fallback a config/env/invoice
        }
        if ($emisorTipo === null) {
            $emisorTipo = (string) (config('services.hacienda.emisor_tipo') ?? env('HACIENDA_EMISOR_TIPO', '02'));
        }
        if ($emisorNumero === null || $emisorNumero === '') {
            $emisorNumero = (string) (config('services.hacienda.emisor_numero') ?? env('HACIENDA_EMISOR_NUMERO', ''));
        }
        if ($emisorNumero === '') {
                // fallback desde invoice si aún vacío
            $emisorNumero = preg_replace('/\D/', '', (string) ($invoice->business_id_number ?? ''));
        }

        if (empty($invoiceXml->clave)) {
            throw new \RuntimeException('El XML no tiene clave. Genere el XML primero y vuelva a intentar.');
        }
        $fecha = $invoice->date?->format('c') ?? now()->format('c');

    // Preflight: verificar que el Emisor en el XML coincide con el titular del certificado (previene Hacienda -60)
       
        $json = [
            'clave' => $invoiceXml->clave,
            'fecha' => $fecha,
            'emisor' => [
                'tipoIdentificacion' => $emisorTipo,
                'numeroIdentificacion' => $emisorNumero,
            ],
            // Receptor omitido en tiquete
            'comprobanteXml' => base64_encode($invoiceXml->xml),
        ];

    // Registrar diagnósticos sobre emisor e identidad del certificado
        try {
            if (function_exists('logger')) {
                $certInfo = (new \App\Services\CertificateService())->getCertificateIdInfo();
                logger()->info('Hacienda submit payload (diag)', [
                    'clave' => $invoiceXml->clave,
                    'emisor_tipo' => $emisorTipo,
                    'emisor_numero' => $emisorNumero,
                    'cert_id' => $certInfo,
                ]);
            }
        } catch (\Throwable $e) { /* ignore */ }

        $http = new Client([ 'timeout' => 30, 'http_errors' => false ]);
        $resp = $http->post($url, [
            'headers' => [
                // Hacienda espera un token Bearer estándar (B mayúscula)
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => $json,
        ]);

        $statusCode = $resp->getStatusCode();
        $body = (string) $resp->getBody();
        $data = json_decode($body, true) ?: [];
        if ($statusCode >= 400) {
            // Error de Surface Hacienda con detalles de la persona que llama y registros
            if (function_exists('logger')) {
                logger()->warning('Hacienda recepcion returned HTTP ' . $statusCode, [ 'body' => $body, 'payload' => $json ]);
            }
            // Extraer mensaje de error estructurado si existe
            $msg = isset($data['mensaje']) ? (string)$data['mensaje'] : (isset($data['detail']) ? (string)$data['detail'] : '');
            // En fallback, usar un fragmento del body crudo si no hay mensaje estructurado
            if ($msg === '' && trim($body) !== '') {
                $snippet = trim($body);
                if (strlen($snippet) > 800) { $snippet = substr($snippet, 0, 800) . '...'; }
                $msg = $snippet;
            }
            throw new \RuntimeException('Hacienda recepcion error HTTP ' . $statusCode . ($msg !== '' ? (': ' . $msg) : ''));
        }

        $estado = $data['ind-estado'] ?? ($data['estado'] ?? 'recibido');
        $indAmbiente = $env;
        return HaciendaResponse::create([
            'invoice_id' => $invoice->id,
            'invoice_xml_id' => $invoiceXml->id,
            'clave' => $invoiceXml->clave,
            'estado' => $estado,
            'fecha_recepcion' => now(),
            'respuesta_xml' => $data['respuesta-xml'] ?? null,
            'ind_ambiente' => $indAmbiente,
            'numero_consecutivo' => $this->extractConsecutivoFromXml($invoiceXml->xml),
            'detalle' => !empty($data) ? $data : null,
        ]);
    }

    private function extractConsecutivoFromXml(string $xml): ?string
    {
        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->loadXML($xml);
            $ns = $dom->documentElement?->namespaceURI;
            $xp = new \DOMXPath($dom);
            if ($ns) { $xp->registerNamespace('fe', $ns); }
            $val = trim((string)$xp->evaluate('string(//fe:NumeroConsecutivo)'));
            return $val !== '' ? $val : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function checkStatus(string $clave): array
    {
        $url = $this->resolveRecepcionEndpoint() . '/' . urlencode($clave);

        $token = $this->tokenService->getAccessToken();
        $http = new Client([ 'timeout' => 20 ]);
        $resp = $http->get($url, [ 'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ] ]);
        $data = json_decode((string) $resp->getBody(), true) ?: [];
        return $data;
    }
}
