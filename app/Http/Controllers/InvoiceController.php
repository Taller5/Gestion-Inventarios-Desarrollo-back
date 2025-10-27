<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Services\FacturaElectronicaXmlService;

class InvoiceController extends Controller
{
       // Endpoint para validar el XML de una factura contra el XSD
    public function validateXml($id)
    {
        $invoice = \App\Models\Invoice::findOrFail($id);
        $xmlService = new \App\Services\FacturaElectronicaXmlService();
        $validator = new \App\Services\XmlValidatorService();
        $xml = $xmlService->generarXml($invoice);
        // Ruta absoluta al XSD (ajusta si es necesario)
        $xsdPath = base_path('TiqueteElectronico_V4.4.xsd');
        if (!file_exists($xsdPath)) {
            return response()->json(['error' => 'No se encontró el archivo XSD en ' . $xsdPath], 500);
        }
        list($isValid, $errors) = $validator->validateXmlAgainstXsd($xml, $xsdPath);
        return response()->json([
            'is_valid' => $isValid,
            'errors' => $errors,
        ]);
    }
    // List all invoices
    public function index()
    {
        return response()->json(Invoice::all());
    }

    // Get a single invoice
    public function show($id)
    {
        $invoice = Invoice::findOrFail($id);
        return response()->json($invoice);
    }

        // Enviar el último XML generado a Hacienda
    public function submit($id)
    {
        $invoice = Invoice::findOrFail($id);
        $xmlRecord = $invoice->xmls()->latest('id')->first();
        if (!$xmlRecord) return response()->json(['error' => 'No hay XML generado para esta factura'], 400);

        try {
            $svc = new \App\Services\HaciendaRecepcionService();
            $resp = $svc->submit($invoice, $xmlRecord);
            $xmlRecord->update(['status' => 'submitted', 'submitted_at' => now()]);
            return response()->json(['ok' => true, 'response' => $resp]);
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            $errorBody = null;
            if ($e instanceof \GuzzleHttp\Exception\BadResponseException && $e->hasResponse()) {
                $errorBody = (string) $e->getResponse()->getBody();
            }
            $xmlRecord->update(['status' => 'error', 'error_message' => $errorMsg . ($errorBody ? (' | ' . $errorBody) : '')]);
            $payload = ['ok' => false, 'error' => $errorMsg];
            if ($errorBody) {
                $decoded = json_decode($errorBody, true);
                $payload['hacienda'] = $decoded ?: $errorBody;
            }
            return response()->json($payload, 500);
        }
    }

    // Consultar estado en Hacienda por clave (usa la más reciente si no se envía)
    public function status($id)
    {
        $invoice = Invoice::findOrFail($id);
        $xmlRecord = $invoice->xmls()->latest('id')->first();
        if (!$xmlRecord || !$xmlRecord->clave) return response()->json(['error' => 'No hay clave asociada a esta factura'], 400);

        try {
            $svc = new \App\Services\HaciendaRecepcionService();
            $data = $svc->checkStatus($xmlRecord->clave);
            // Persistir un snapshot de la respuesta
            \App\Models\HaciendaResponse::create([
                'invoice_id' => $invoice->id,
                'invoice_xml_id' => $xmlRecord->id,
                'clave' => $xmlRecord->clave,
                'estado' => $data['ind-estado'] ?? ($data['estado'] ?? null),
                'respuesta_xml' => $data['respuesta-xml'] ?? null,
                'detalle' => $data,
            ]);
            // Marcar xml según estado
            if (($data['ind-estado'] ?? '') === 'aceptado') {
                $xmlRecord->update(['status' => 'accepted', 'accepted_at' => now()]);
            } elseif (($data['ind-estado'] ?? '') === 'rechazado') {
                $xmlRecord->update(['status' => 'rejected', 'rejected_at' => now()]);
            }
            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

        // Ver el XML de respuesta de Hacienda para la última respuesta guardada
    public function responseXml($id)
    {
        $invoice = Invoice::findOrFail($id);
        $resp = $invoice->responses()->latest('id')->first();
        if (!$resp || empty($resp->respuesta_xml)) {
            return response()->json(['error' => 'No hay XML de respuesta almacenado para esta factura'], 404);
        }
        $xml = $resp->respuesta_xml;
        $maybeDecoded = base64_decode($xml, true);
        if ($maybeDecoded !== false && str_starts_with(trim($maybeDecoded), '<')) {
            $xml = $maybeDecoded;
        }
        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    // Generar y mostrar el XML de factura electrónica para un Invoice
     public function xml($id)
    {
        $invoice = Invoice::findOrFail($id);
        $service = new FacturaElectronicaXmlService();
        $xml = $service->generarXml($invoice);

        // Guardar XML generado y su estado de validación
        try {
            $xsdPath = base_path('TiqueteElectronico_V4.4.xsd');
            $validator = new \App\Services\XmlValidatorService();

            $schemaValid = null; $errors = [];
            if (file_exists($xsdPath)) {
                [$schemaValid, $errors] = $validator->validateXmlAgainstXsd($xml, $xsdPath);
            }

            // Extraer Clave del XML 
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->loadXML($xml);
            $ns = $dom->documentElement?->namespaceURI;
            $xpath = new \DOMXPath($dom);
            if ($ns) { $xpath->registerNamespace('fe', $ns); }
            $clave = trim((string)$xpath->evaluate('string(//fe:Clave)'));

            // Verificación de firma 
            $signatureValid = null; // verificación local removida junto al firmador propio

            $invoice->xmls()->create([
                'clave' => $clave ?: null,
                'document_type' => '04',
                'schema_version' => '4.4',
                'xml' => $xml,
                'schema_valid' => $schemaValid,
                'signature_valid' => $signatureValid,
                'validation_errors' => !empty($errors) ? json_encode($errors, JSON_UNESCAPED_UNICODE) : null,
                'status' => 'generated',
            ]);
        } catch (\Throwable $e) {
            // No bloquear la entrega del XML si falla guardado/validación
            if (function_exists('logger')) { logger()->warning('No se pudo persistir Invoice XML: ' . $e->getMessage()); }
        }

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    // Estado del último XML  para verificar que toma valores reales
    public function xmlStatus($id)
    {
        $invoice = Invoice::findOrFail($id);
        $xmlRecord = $invoice->xmls()->latest('id')->first();
        if (!$xmlRecord) {
            return response()->json(['error' => 'No hay XML generado para esta factura'], 404);
        }

        $xml = $xmlRecord->xml;
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($xml);
        $ns = $dom->documentElement?->namespaceURI;
    $xpath = new \DOMXPath($dom);
    if ($ns) { $xpath->registerNamespace('fe', $ns); }
    $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

    $proveedor = trim((string)$xpath->evaluate('string(//fe:ProveedorSistemas)'));
    $codigoAct = trim((string)$xpath->evaluate('string(//fe:CodigoActividadEmisor)'));
    $emisorTipo = trim((string)$xpath->evaluate('string(//fe:Emisor/fe:Identificacion/fe:Tipo)'));
    $emisorNumero = trim((string)$xpath->evaluate('string(//fe:Emisor/fe:Identificacion/fe:Numero)'));
        $schemaLocation = trim((string)$dom->documentElement->getAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'schemaLocation'));
        $hasSignature = (bool)$xpath->evaluate('count(//ds:Signature)') > 0;

        // Extract embedded signing certificate identity (subject serialNumber) for diagnostics
        $sigCert = [ 'subject' => null, 'issuer' => null, 'serial' => null, 'tipo' => null, 'numero' => null ];
        try {
            $certNode = $xpath->query('//ds:Signature//ds:X509Data/ds:X509Certificate')->item(0);
            if ($certNode instanceof \DOMElement) {
                $b64 = trim($certNode->textContent);
                $der = base64_decode($b64, true);
                if ($der !== false) {
                    // Re-wrap to PEM for openssl parser
                    $pem = "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END CERTIFICATE-----\n";
                    $x = @openssl_x509_read($pem);
                    if ($x) {
                        $info = @openssl_x509_parse($x) ?: [];
                        $subj = $info['subject'] ?? [];
                        $issuer = $info['issuer'] ?? [];
                        $sigCert['subject'] = is_array($subj) ? json_encode($subj, JSON_UNESCAPED_UNICODE) : (string)$subj;
                        $sigCert['issuer'] = is_array($issuer) ? json_encode($issuer, JSON_UNESCAPED_UNICODE) : (string)$issuer;
                        $sigCert['serial'] = $info['serialNumber'] ?? ($info['serialNumberHex'] ?? null);
                        // Infer tipo/numero from subject.serialNumber (CPF/CPJ/DIMEX/NITE)
                        $serialAttr = null;
                        if (isset($subj['serialNumber'])) {
                            $serialAttr = is_array($subj['serialNumber']) ? reset($subj['serialNumber']) : $subj['serialNumber'];
                        } elseif (isset($subj['OID.2.5.4.5'])) {
                            $serialAttr = is_array($subj['OID.2.5.4.5']) ? reset($subj['OID.2.5.4.5']) : $subj['OID.2.5.4.5'];
                        } elseif (isset($subj['2.5.4.5'])) {
                            $serialAttr = is_array($subj['2.5.4.5']) ? reset($subj['2.5.4.5']) : $subj['2.5.4.5'];
                        }
                        $upper = $serialAttr && is_string($serialAttr) ? strtoupper($serialAttr) : '';
                        if ($upper !== '' && preg_match('/\bCPF[-:\s]*([0-9\-]+)/', $upper, $m)) {
                            $sigCert['tipo'] = '01';
                            $sigCert['numero'] = preg_replace('/\D/', '', $m[1]);
                        } elseif ($upper !== '' && preg_match('/\bCPJ[-:\s]*([0-9\-]+)/', $upper, $m)) {
                            $sigCert['tipo'] = '02';
                            $sigCert['numero'] = preg_replace('/\D/', '', $m[1]);
                        } elseif ($upper !== '' && preg_match('/\bDIMEX[-:\s]*([0-9\-]+)/', $upper, $m)) {
                            $sigCert['tipo'] = '03';
                            $sigCert['numero'] = preg_replace('/\D/', '', $m[1]);
                        } elseif ($upper !== '' && preg_match('/\bNITE[-:\s]*([0-9\-]+)/', $upper, $m)) {
                            $sigCert['tipo'] = '04';
                            $sigCert['numero'] = preg_replace('/\D/', '', $m[1]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore diag errors
        }

        return response()->json([
            'id' => $xmlRecord->id,
            'clave' => $xmlRecord->clave,
            'status' => $xmlRecord->status,
            'error_message' => $xmlRecord->error_message,
            'schema_valid' => $xmlRecord->schema_valid,
            'signature_valid' => $xmlRecord->signature_valid,
            'embedded' => [
                'ProveedorSistemas' => $proveedor,
                'CodigoActividadEmisor' => $codigoAct,
                'EmisorTipo' => $emisorTipo,
                'EmisorNumero' => $emisorNumero,
                'xsi:schemaLocation' => $schemaLocation,
                'hasSignatureNode' => $hasSignature,
                'SignatureCert' => $sigCert,
            ],
        ]);
    }



    
   // Create a new invoice
    public function store(Request $request)
    {
        $data = $request->validate([
            // Receptor no obligatorio para tiquete
            'customer_name' => 'nullable|string',
            'customer_identity_number' => 'nullable|string',
           
            // Branch / Business info
            'branch_name' => 'required|string',
            'business_name' => 'required|string',
            'business_legal_name' => 'nullable|string',
            'business_phone' => 'nullable|string',
            'business_email' => 'nullable|string',
            'branches_phone' => 'nullable|string',
            'province' => 'nullable|string',
            'canton' => 'nullable|string',
            'business_id_type' => 'nullable|string',
            'business_id_number' => 'nullable|string',

            // Cashier
            'cashier_name' => 'required|string',

            // Products
            'products' => 'required|array',
            'products.*.code' => 'required|string',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0|max:100',

            // Payment
            'payment_method' => 'required|in:Cash,Card,SINPE',
            'amount_paid' => 'nullable|numeric|min:0',
            'receipt' => 'nullable|string',
        ]);
        $documentType = '04'; 

        // Calcular subtotal y total de descuento
        $subtotal = 0;
        $totalDiscount = 0;

        foreach ($data['products'] as $p) {
            $subtotal += $p['price'] * $p['quantity'];
            $totalDiscount += ($p['price'] * $p['quantity'] * ($p['discount'] ?? 0) / 100);
        }

        // Calcular impuestos sobre subtotal - descuento
        $taxes = ($subtotal - $totalDiscount) * 0.13;

        // Calcular total final
        $total = $subtotal - $totalDiscount + $taxes;

        // Crear factura
        $invoice = Invoice::create([
            'customer_name' => $data['customer_name'] ?? null,
            'customer_identity_number' => $data['customer_identity_number'] ?? null,

            'branch_name' => $data['branch_name'],
            'business_name' => $data['business_name'],
            'business_legal_name' => $data['business_legal_name'] ?? null,
            'business_phone' => $data['business_phone'] ?? null,
            'business_email' => $data['business_email'] ?? null,
            'branches_phone' => $data['branches_phone'] ?? null,
            'province' => $data['province'] ?? null,
            'canton' => $data['canton'] ?? null,
            'business_id_type' => $data['business_id_type'] ?? null,
            'business_id_number' => $data['business_id_number'] ?? null,
            'cashier_name' => $data['cashier_name'],
            'date' => now(),
            'document_type' => $documentType,
            'products' => $data['products'],
            'subtotal' => $subtotal,
            'total_discount' => $totalDiscount,
            'taxes' => $taxes,
            'total' => $total,
            'amount_paid' => $data['amount_paid'] ?? 0,
            'change' => max(0, ($data['amount_paid'] ?? 0) - $total),
            'payment_method' => $data['payment_method'],
            'receipt' => $data['receipt'] ?? ($data['payment_method'] === 'Cash' ? 'N/A' : ''),
        ]);
        // Crear items snapshot
        foreach ($data['products'] as $p) {
            // Buscar producto catálogo para snapshot enriquecido
            $productModel = \App\Models\Product::where('codigo_producto', $p['code'])->with('unit')->first();
            $codigoCabys = null;
            if ($productModel && $productModel->codigo_cabys) {
                $codigoCabys = preg_replace('/[^0-9]/', '', $productModel->codigo_cabys);
            }
            if (!$codigoCabys || strlen($codigoCabys) !== 13) {
                $codigoCabys = str_pad((string)$codigoCabys, 13, '0', STR_PAD_RIGHT);
            }
            $unidadMedida = $productModel && $productModel->unit ? $productModel->unit->unidMedida : 'Unid';
            $cantidad = (float)$p['quantity'];
            $precioUnitario = (float)$p['price'];
            $montoBruto = $cantidad * $precioUnitario;
            $descuentoPct = (float)($p['discount'] ?? 0);
            $subtotalLinea = $montoBruto - ($montoBruto * $descuentoPct / 100.0);
            // Impuesto: usar campo impuesto del producto si existe, sino derivar CABYS, sino 13%
            $impuestoPorcentaje = null;
            if ($productModel && $productModel->impuesto !== null) {
                $impuestoPorcentaje = (float)$productModel->impuesto;
            } elseif ($codigoCabys && $codigoCabys !== str_repeat('0',13)) {
                $cabysRow = \App\Models\Cabys::find($codigoCabys);
                if ($cabysRow && $cabysRow->tax_rate !== null) {
                    $impuestoPorcentaje = (float)$cabysRow->tax_rate;
                }
            }
            if ($impuestoPorcentaje === null) {
                $impuestoPorcentaje = 13.0; // fallback general
            }
            $impuestoMonto = $subtotalLinea * ($impuestoPorcentaje / 100.0);
            $totalLinea = $subtotalLinea + $impuestoMonto;

            $invoice->items()->create([
                'product_id' => $productModel?->id,
                'codigo_producto' => $p['code'],
                'descripcion' => $productModel->descripcion ?? ($productModel->nombre_producto ?? 'Producto'),
                'codigo_cabys' => $codigoCabys,
                'unidad_medida' => $unidadMedida,
                'impuesto_porcentaje' => $impuestoPorcentaje,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'descuento_pct' => $descuentoPct,
                'subtotal_linea' => $subtotalLinea,
                'impuesto_monto' => $impuestoMonto,
                'total_linea' => $totalLinea,
            ]);
        }

        return response()->json($invoice->load('items'), 201);
    }

    // Update an invoice
    public function update(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $data = $request->validate([
            'payment_method' => 'sometimes|in:Cash,Card,SINPE',
            'amount_paid' => 'sometimes|numeric|min:0',
            'receipt' => 'sometimes|string',
        ]);

        $invoice->update($data);
        return response()->json($invoice);
    }

    // Delete an invoice
    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->delete();
        return response()->json(null, 204);
    }

    // Obtener facturas agrupadas por negocio
public function reportByBusiness(Request $request)
{
    // Filtrado opcional por rango de fechas
    $startDate = $request->query('start_date'); // formato: Y-m-d
    $endDate   = $request->query('end_date');

    $query = Invoice::query();

    if ($startDate) {
        $query->whereDate('date', '>=', $startDate);
    }
    if ($endDate) {
        $query->whereDate('date', '<=', $endDate);
    }

    // Obtener facturas y agrupar por negocio
    $invoices = $query->orderBy('date', 'desc')->get()->groupBy('business_name');

    $result = [];

    foreach ($invoices as $business => $businessInvoices) {
        $totalSales = $businessInvoices->sum('total');
        $count = $businessInvoices->count();

        $result[] = [
            'business_name' => $business,
            'total_sales' => $totalSales,
            'invoice_count' => $count,
            'invoices' => $businessInvoices->map(function($inv) {
                return [
                    'id' => $inv->id,
                    'branch_name' => $inv->branch_name,
                    'date' => $inv->date,
                    'customer_name' => $inv->customer_name,
                    'total' => $inv->total,
                    'payment_method' => $inv->payment_method,
                ];
            }),
        ];
    }

    return response()->json($result);
}

}
