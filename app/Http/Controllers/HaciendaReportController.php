<?php

namespace App\Http\Controllers;

use App\Models\Invoice;

class HaciendaReportController extends Controller
{
    /**
     * Devuelve una lista combinada de facturas, XML y respuestas de Hacienda, filtrable por negocio, tipo, estado y fecha.
     */
    public function index()
    {
        $request = request();
        $query = Invoice::with([
            'business',
            'xmls',
            'responses',
        ]);

        // Filtros
        if ($request->filled('business_id')) {
            $query->where('business_id', $request->business_id);
        }
        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }
        if ($request->filled('estado')) {
            $query->whereHas('responses', function ($q) use ($request) {
                $q->where('estado', $request->estado);
            });
        }
        if ($request->filled('fecha_inicio')) {
            $query->where('date', '>=', $request->fecha_inicio);
        }
        if ($request->filled('fecha_fin')) {
            $query->where('date', '<=', $request->fecha_fin);
        }

        $invoices = $query->orderByDesc('date')->paginate(50);

        // Formatear respuesta para frontend
        $result = $invoices->map(function ($invoice) {
            $xml = $invoice->xmls->last(); // último XML generado
            $response = $invoice->responses->last(); // última respuesta Hacienda
            // Preferir relación Business.nombre_comercial, si no existe usar campos en la factura
            $businessName = null;
            if ($invoice->business) {
                $businessName = $invoice->business->nombre_comercial ?? $invoice->business->nombre_legal ?? null;
            }
            $businessName = $businessName ?? ($invoice->business_name ?? $invoice->business_legal_name ?? null);

            return [
                'id' => $invoice->id,
                'business' => $businessName,
                'document_type' => $invoice->document_type,
                'date' => $invoice->date,
                'clave' => $xml ? $xml->clave : null,
                'xml_download_url' => $xml ? route('xml.download', ['id' => $xml->id]) : null,
                'response_xml' => $response ? $response->respuesta_xml : null,
                'response_xml_download_url' => $response ? route('responsexml.download', ['id' => $response->id]) : null,
                'estado' => $response ? $response->estado : null,
                'tipo' => $invoice->document_type == '04' ? 'Tiquete' : 'Factura',
            ];
        });

        return response()->json([
            'data' => $result,
            'pagination' => [
                'total' => $invoices->total(),
                'per_page' => $invoices->perPage(),
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
            ],
        ]);
    }
}
