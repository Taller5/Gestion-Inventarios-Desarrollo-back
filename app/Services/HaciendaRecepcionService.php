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
     * Resolve the base Recepcion endpoint (ending with .../recepcion) according to env/config.
     * Accepts flexible configuration values:
     *  - Full endpoint: https://.../recepcion-sandbox/v1/recepcion or https://.../recepcion/v1/recepcion
     *  - Base v1 path:  https://.../recepcion-sandbox/v1/ or https://.../recepcion/v1/
     */
    private function resolveRecepcionEndpoint(): string
    {
        $env = strtolower((string) (config('services.hacienda.env') ?? env('HACIENDA_ENV', 'stag')));
        // Prefer explicit service config, then env fallback, then safe defaults
        $configured = (string) (config('services.hacienda.recepcion_url')
            ?? ($env === 'prod' ? config('services.hacienda.recepcion_url_prod') : config('services.hacienda.recepcion_url_stag'))
            ?? env('HACIENDA_RECEPCION_URL', $env === 'prod'
                ? 'https://api.comprobanteselectronicos.go.cr/recepcion/v1/recepcion'
                : 'https://api.comprobanteselectronicos.go.cr/recepcion-sandbox/v1/recepcion'));

        $u = rtrim($configured, '/');
        // If user configured the legacy sandbox path under main domain, rewrite to canonical api-sandbox domain
        if ($env === 'stag' && preg_match('#^https?://api\.comprobanteselectronicos\.go\.cr/recepcion-sandbox#i', $u)) {
            // Normalize to https://api-sandbox.comprobanteselectronicos.go.cr/recepcion/v1/recepcion
            $u = 'https://api-sandbox.comprobanteselectronicos.go.cr/recepcion/v1/recepcion';
        }
        // If it points to the v1 base but is missing the trailing /recepcion, append it
        if (!preg_match('#/recepcion$#i', $u)) {
            if (preg_match('#/(recepcion|recepcion-sandbox)/v1$#i', $u)) {
                $u .= '/recepcion';
            }
        }
        return $u;
    }

    public function submit(Invoice $invoice, InvoiceXml $invoiceXml): HaciendaResponse
    {
        $env = strtolower((string) (config('services.hacienda.env') ?? env('HACIENDA_ENV', 'stag')));
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
            // ignore, fallback to config/env/invoice
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

        $http = new Client([ 'timeout' => 30 ]);
        $resp = $http->post($url, [
            'headers' => [
                // Hacienda expects a standard Bearer token (capital B)
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => $json,
        ]);

        $body = (string) $resp->getBody();
        $data = json_decode($body, true) ?: [];

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
