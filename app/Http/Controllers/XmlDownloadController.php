<?php

namespace App\Http\Controllers;

use App\Models\InvoiceXml;
use App\Models\HaciendaResponse;
use Illuminate\Http\Response;

class XmlDownloadController extends Controller
{
    // Descargar XML generado
    public function downloadXml($id)
    {
        $xml = InvoiceXml::findOrFail($id);
        $filename = 'comprobante_' . $xml->clave . '.xml';
        $content = $xml->xml;
        // Si está en base64, decodificarlo
        $decoded = base64_decode($content, true);
        if ($decoded !== false && (strpos(trim($decoded), '<?xml') === 0 || strpos(trim($decoded), '<') === 0)) {
            $body = $decoded;
        } else {
            $body = $content;
        }

        return response($body, 200)
            ->header('Content-Type', 'application/xml')
            ->header('Content-Disposition', 'attachment; filename=' . $filename);
    }

    // Descargar XML de respuesta Hacienda
    public function downloadResponseXml($id)
    {
        $response = HaciendaResponse::findOrFail($id);
        $filename = 'respuesta_' . ($response->clave ?? $response->id) . '.xml';
        $content = $response->respuesta_xml;
        // Intentar decodificar base64 si aplica
        $decoded = base64_decode($content, true);
        if ($decoded !== false && (strpos(trim($decoded), '<?xml') === 0 || strpos(trim($decoded), '<') === 0)) {
            $body = $decoded;
        } else {
            $body = $content;
        }

        return response($body, 200)
            ->header('Content-Type', 'application/xml')
            ->header('Content-Disposition', 'attachment; filename=' . $filename);
    }
}
