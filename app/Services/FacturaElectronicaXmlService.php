<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Product;
use DOMDocument;
use DOMElement;

use App\Services\CertificateService;

class FacturaElectronicaXmlService
{
    private CertificateService $certSvc;
    private ?SignerService $Signer = null;

    public function __construct(?CertificateService $certSvc = null)
    {
        $this->certSvc = $certSvc ?? new CertificateService();
    // Inicializar firmador alternativo sólo si el toggle está habilitado
        $useAlt = (bool) (config('services.hacienda.use_alt_signer') ?? filter_var(getenv('HACIENDA_USE_ALT_SIGNER') ?: 'false', FILTER_VALIDATE_BOOL));
        if ($useAlt) {
            $this->Signer = new SignerService();
        }
    }
    /**
     * Genera el XML de Factura Electrónica versión 4.4 para Hacienda CR.
     * @param Invoice $invoice
     * @return string XML generado (con <?xml ... ?> y UTF-8)
     */
    public function generarXml(Invoice $invoice): string
    {
        // --- Generar todo el XML con DOMDocument y createElementNS ---
    // Namespace específico de Tiquete Electrónico v4.4 según XSD oficial
    $ns = 'https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/tiqueteElectronico';
    $dom = new DOMDocument('1.0', 'UTF-8');
    // IMPORTANTE: No formatear (pretty-print); preservar los espacios en blanco para evitar alterar los bytes firmados
    $dom->preserveWhiteSpace = true;
    $dom->formatOutput = false; // sin pretty-print

    // Crear nodo raíz con namespace por defecto
    // Sólo generamos tiquete electrónico (document_type forzado a 04)
    $docType = '04';
    $root = $dom->createElementNS($ns, 'TiqueteElectronico');
        $dom->appendChild($root);

        // Asegurar namespace por defecto y declarar explícitamente los prefijos usados por atributos/elementos:
        // - xmlns (por defecto) para el documento
    // - xsi: para schemaLocation
    // - xsd: para compatibilidad con validadores que lo esperan declarado
    $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', $ns);
    $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');


        // Seleccionar XSD según tipo (ahora sólo tiquete). Permite configurar por env:
        // HACIENDA_SCHEMA_TIQUETE_LOCATION primero, luego schema_location genérico, fallback nombre local.
        $schemaLocation = (string) (
            config('services.hacienda.schema_tiquete_location')
            ?? getenv('HACIENDA_SCHEMA_TIQUETE_LOCATION')
            ?? config('services.hacienda.schema_location')
            ?? getenv('HACIENDA_SCHEMA_LOCATION')
            ?: 'TiqueteElectronico_V4.4.xsd'
        );
    $root->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', $ns . ' ' . $schemaLocation);

    // No attribute Id/id en el root: el XSD de tiquete no define atributos en el raíz.
    if ($root->hasAttribute('Id')) { $root->removeAttribute('Id'); }
    if ($root->hasAttribute('id')) { $root->removeAttribute('id'); }

        // 2) Encabezado
        $codigoPais = '506';
        $fechaClave = $invoice->date->format('dmy');
        // Permitir forzar Emisor por ENV para pruebas
        $forceTipo   = preg_replace('/\D/', '', (string) (getenv('HACIENDA_EMISOR_TIPO') ?: ''));
        $forceNumero = preg_replace('/\D/', '', (string) (getenv('HACIENDA_EMISOR_NUMERO') ?: ''));

        // Preferir SIEMPRE configuración/env para asegurar consistencia con el certificado; fallback a invoice
        $emisorNumeroCfg = (string) (config('services.hacienda.emisor_numero') ?? getenv('HACIENDA_EMISOR_NUMERO') ?: '');
        $emisorNumeroDoc = '';
        if (!empty($forceNumero)) {
            $emisorNumeroDoc = $forceNumero;
        } elseif (!empty($emisorNumeroCfg)) {
            $emisorNumeroDoc = preg_replace('/\D/', '', (string) $emisorNumeroCfg);
        } elseif (!empty($invoice->business_id_number)) {
            $emisorNumeroDoc = preg_replace('/\D/', '', (string) $invoice->business_id_number);
        }
        // Validar que realmente haya un número de identificación configurado; evitar fallback hardcodeado
        if ($emisorNumeroDoc === '') {
            throw new \InvalidArgumentException(
                'Emisor Numero no configurado. Defina HACIENDA_FORCE_EMISOR_NUMERO o HACIENDA_EMISOR_NUMERO en .env (o "business_id_number" en la factura).'
            );
        }
        $idEmisor = str_pad($emisorNumeroDoc, 12, '0', STR_PAD_LEFT);
        // Preferir IDs numéricos si están disponibles: sucursal_id / branch_id para sucursal,
        // cash_register_id / caja_id para terminal. Si no existen, mantener compatibilidad
        // con los campos legados $invoice->branch y $invoice->terminal.
        $sucursalId = null;
        if (isset($invoice->sucursal_id) && is_numeric($invoice->sucursal_id)) {
            $sucursalId = (int) $invoice->sucursal_id;
        } elseif (isset($invoice->branch_id) && is_numeric($invoice->branch_id)) {
            $sucursalId = (int) $invoice->branch_id;
        } elseif (isset($invoice->branch) && is_numeric($invoice->branch)) {
            $sucursalId = (int) $invoice->branch;
        }
        $sucursal = str_pad((string) ($sucursalId ?? ($invoice->branch ?? '003')), 3, '0', STR_PAD_LEFT);

        $terminalId = null;
        if (isset($invoice->cash_register_id) && is_numeric($invoice->cash_register_id)) {
            $terminalId = (int) $invoice->cash_register_id;
        } elseif (isset($invoice->caja_id) && is_numeric($invoice->caja_id)) {
            $terminalId = (int) $invoice->caja_id;
        } elseif (isset($invoice->terminal) && is_numeric($invoice->terminal)) {
            $terminalId = (int) $invoice->terminal;
        }
        // Terminal en XSD usa 5 dígitos
        $terminal = str_pad((string) ($terminalId ?? ($invoice->terminal ?? '002')), 5, '0', STR_PAD_LEFT);
        $tipoDoc = '04'; // Tiquete
        $numeroSec = str_pad((string) ((int) ($invoice->sequential ?? $invoice->id ?? 1)), 10, '0', STR_PAD_LEFT);
        $numeroConsecutivo = "{$sucursal}{$terminal}{$tipoDoc}{$numeroSec}";
        $situacionComprobante = '1';
        $codigoSeguridad = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $clave = "{$codigoPais}{$fechaClave}{$idEmisor}{$numeroConsecutivo}{$situacionComprobante}{$codigoSeguridad}";

    // 2.1) Clave (50 dígitos)
    $root->appendChild($dom->createElementNS($ns, 'Clave', $clave));

    // 2.2) Proveedor de sistemas y código de actividad (obligatorios en v4.4)
    $proveedorSistemas = (string) (config('services.hacienda.proveedor_sistemas') ?? getenv('HACIENDA_PROVEEDOR_SISTEMAS') ?: '0000000000');
    $root->appendChild($dom->createElementNS($ns, 'ProveedorSistemas', substr($proveedorSistemas, 0, 20)));

    $codigoActividadEmisor = (string) (config('services.hacienda.codigo_actividad_emisor') ?? getenv('HACIENDA_CODIGO_ACTIVIDAD_EMISOR') ?: '000000');
    $codigoActividadEmisor = str_pad(substr(preg_replace('/\D/', '', $codigoActividadEmisor), 0, 6), 6, '0', STR_PAD_LEFT);
    $root->appendChild($dom->createElementNS($ns, 'CodigoActividadEmisor', $codigoActividadEmisor));

    // 2.3) Numero consecutivo y fecha de emisión en el orden definido por el XSD
    $root->appendChild($dom->createElementNS($ns, 'NumeroConsecutivo', $numeroConsecutivo));
    $fechaEmision = $invoice->date->format('Y-m-d\TH:i:sP');
    $root->appendChild($dom->createElementNS($ns, 'FechaEmision', $fechaEmision));

        // 3) Emisor
    $emisor = $dom->createElementNS($ns, 'Emisor');
        $identEm = $dom->createElementNS($ns, 'Identificacion');
        // Tipo de identificación: forzar > invoice > config/env; fallback '01'
        $tipoIdEmisorCfg = (string) (config('services.hacienda.emisor_tipo') ?? getenv('HACIENDA_EMISOR_TIPO') ?: '01');
        // Preferir config/env para tipo; fallback a invoice
        $tipoIdEmisorRaw = !empty($forceTipo)
            ? $forceTipo
            : (string)($tipoIdEmisorCfg ?: ($invoice->business_id_type ?? '01'));
        $tipoIdEmisor = $this->sanitizeIdentType(preg_replace('/\D/', '', $tipoIdEmisorRaw), '01');
    // Nombre emisor: min 5, max 100. Para persona física, si no viene un nombre razonable, usar CN del certificado.
    $nombreEmisorRaw = trim((string)($invoice->business_name ?? ''));
    if ($nombreEmisorRaw === '' || mb_strlen($nombreEmisorRaw, 'UTF-8') < 5) {
        try {
            $cn = $this->certSvc->getCertificateSubjectCN();
            if ($cn && $tipoIdEmisor === '01') { // persona física
                $nombreEmisorRaw = $cn;
            }
        } catch (\Throwable $e) { /* ignore */ }
    }
    if ($nombreEmisorRaw === '') { $nombreEmisorRaw = 'Emisor'; }
    $nombreEmisor = $this->enforceLength($nombreEmisorRaw, 5, 100, ' ');
    $emisor->appendChild($dom->createElementNS($ns, 'Nombre', $nombreEmisor));
        
        // Anexar tipo ya calculado
        $identEm->appendChild($dom->createElementNS($ns, 'Tipo', $tipoIdEmisor));
        // Número: usar el valor ya resuelto arriba para garantizar consistencia con Clave
        $identEm->appendChild($dom->createElementNS($ns, 'Numero', $emisorNumeroDoc));
        $emisor->appendChild($identEm);
        
        if (!empty($invoice->business_legal_name)) {
            $emisor->appendChild($dom->createElementNS($ns, 'NombreComercial', substr($invoice->business_legal_name, 0, 80)));
        }

        $ubic = $dom->createElementNS($ns, 'Ubicacion');
        $ubic->appendChild($dom->createElementNS($ns, 'Provincia', is_numeric($invoice->province) ? (string)$invoice->province : '2'));
        $ubic->appendChild($dom->createElementNS($ns, 'Canton', is_numeric($invoice->canton) ? str_pad($invoice->canton, 2, '0', STR_PAD_LEFT) : '14'));
        $ubic->appendChild($dom->createElementNS($ns, 'Distrito', is_numeric($invoice->district) ? str_pad($invoice->district, 2, '0', STR_PAD_LEFT) : '04'));
        // Barrio es opcional y requiere minLength=5; solo incluir si cumple
        $barrio = trim((string)($invoice->neighborhood ?? ''));
        if (strlen($barrio) >= 5) {
            $ubic->appendChild($dom->createElementNS($ns, 'Barrio', substr($barrio, 0, 50)));
        }
        $ubic->appendChild($dom->createElementNS($ns, 'OtrasSenas', substr($invoice->address ?? 'Sin direccion', 0, 160)));
        $emisor->appendChild($ubic);
        
        if (!empty($invoice->business_phone)) {
            $tel = $dom->createElementNS($ns, 'Telefono');
            $tel->appendChild($dom->createElementNS($ns, 'CodigoPais', '506'));
            $tel->appendChild($dom->createElementNS($ns, 'NumTelefono', preg_replace('/\D/', '', $invoice->business_phone)));
            $emisor->appendChild($tel);
        }
        
        if (!empty($invoice->business_email)) {
            $emisor->appendChild($dom->createElementNS($ns, 'CorreoElectronico', substr($invoice->business_email, 0, 160)));
        }
        $root->appendChild($emisor);

        // 4) Receptor omitido para tiquete

        // 5) Condición de venta / Plazo crédito
        $condicionVenta = $invoice->condition_sale ?? '01';
        $plazoCredito = (int) ($invoice->credit_term ?? 0);
        $root->appendChild($dom->createElementNS($ns, 'CondicionVenta', $condicionVenta));
        
        if ($condicionVenta === '02' && $plazoCredito > 0) {
            $root->appendChild($dom->createElementNS($ns, 'PlazoCredito', (string)$plazoCredito));
        }

    // 6) Medios de pago (en v4.4 van dentro de ResumenFactura como nodos complejos)

        // 7) DetalleServicio (líneas)
        $detalle = $dom->createElementNS($ns, 'DetalleServicio');
        $totalVentaBruta = 0.0;
        $totalDescuentos = 0.0;
        $totalImpuesto = 0.0;
    $totalVentaNeta = 0.0;
    $impuestos_acumulados = [];
    // Acumuladores de servicios según CABYS (prefijos 5-9)
    $totalServGravados = 0.0;
    $totalServExentos = 0.0;
    $totalServExonerado = 0.0; // si luego se implementa Exoneracion por línea
    $totalServNoSujeto = 0.0;
    // Acumuladores de mercancías
    $totalMercanciasGravadas = 0.0;
    $totalMercanciasExentas = 0.0;
    $totalMercanciasExonerada = 0.0;

        // Preferir snapshot en invoice_items si existen
        $itemsSnapshot = method_exists($invoice, 'items') ? $invoice->items()->get() : collect();
        $usingSnapshot = $itemsSnapshot->count() > 0;
        $productosFuente = $usingSnapshot ? $itemsSnapshot : ($invoice->products ?? []);

        foreach ($productosFuente as $i => $prod) {
            $linea = $dom->createElementNS($ns, 'LineaDetalle');
            $linea->appendChild($dom->createElementNS($ns, 'NumeroLinea', (string)($i + 1)));

            // --- Cálculo de montos brutos y descuentos ---
            if ($usingSnapshot) {
                $cabys = $prod->codigo_cabys;
                $linea->appendChild($dom->createElementNS($ns, 'CodigoCABYS', $cabys));
                $cantidad = (float)$prod->cantidad;
                $precioUnitario = (float)$prod->precio_unitario;
                $montoBruto = $precioUnitario * $cantidad; // monto bruto antes de descuento
                $descuentoPct = (float)$prod->descuento_pct;
                $montoDescuento = $montoBruto * ($descuentoPct / 100.0);
                $unidadMedida = $prod->unidad_medida;
                $detalleTexto = $prod->descripcion;
            } else {
                $productModel = null;
                if ($prod instanceof \App\Models\Product) {
                    $productModel = $prod;
                } elseif (!empty($prod['codigo_producto'])) {
                    $productModel = \App\Models\Product::where('codigo_producto', $prod['codigo_producto'])->with('unit')->first();
                }

                $cabys = $prod['cabys']
                    ?? ($prod['codigo_cabys'] ?? null)
                    ?? ($productModel->codigo_cabys ?? null)
                    ?? null;
                $cabys = preg_replace('/[^0-9]/', '', (string)$cabys);
                if (!$cabys || strlen($cabys) !== 13) {
                    $cabys = $cabys ? str_pad($cabys, 13, '0', STR_PAD_RIGHT) : str_repeat('0', 13);
                }
                $linea->appendChild($dom->createElementNS($ns, 'CodigoCABYS', $cabys));

                $cantidad = (float) ($prod['quantity'] ?? 1);
                $precioUnitario = (float) ($prod['price'] ?? ($productModel->precio_venta ?? 0));
                $montoBruto = $precioUnitario * $cantidad;
                $descuentoPct = (float) ($prod['discount'] ?? 0.0);
                $montoDescuento = $montoBruto * ($descuentoPct / 100.0);
                $unidadMedida = null;
                if ($productModel && $productModel->relationLoaded('unit') && $productModel->unit) {
                    $unidadMedida = $productModel->unit->unidMedida;
                }
                $unidadMedida = $unidadMedida
                    ?? ($prod['unit'] ?? null)
                    ?? ($prod['unidad_medida'] ?? null)
                    ?? 'Unid';
                $detalleTexto = $prod['description']
                    ?? $prod['descripcion']
                    ?? ($productModel->descripcion ?? null)
                    ?? ($productModel->nombre_producto ?? 'Producto');
            }
            $montoTotal = $montoBruto; // para compatibilidad con el resto del código
            
            // Cantidad en XSD: hasta 3 decimales (sin ceros a la derecha)
            $linea->appendChild($dom->createElementNS($ns, 'Cantidad', $this->formatDecimal($cantidad, 3)));
            $linea->appendChild($dom->createElementNS($ns, 'UnidadMedida', substr($unidadMedida, 0, 20)));
            $linea->appendChild($dom->createElementNS($ns, 'Detalle', htmlspecialchars(substr((string)$detalleTexto, 0, 160), ENT_XML1 | ENT_COMPAT, 'UTF-8')));
            $linea->appendChild($dom->createElementNS($ns, 'PrecioUnitario', number_format($precioUnitario, 5, '.', '')));
            $linea->appendChild($dom->createElementNS($ns, 'MontoTotal', number_format($montoBruto, 5, '.', '')));

            if ($montoDescuento > 0.0) {
                $descNode = $dom->createElementNS($ns, 'Descuento');
                $descNode->appendChild($dom->createElementNS($ns, 'MontoDescuento', number_format($montoDescuento, 5, '.', '')));
                $descNode->appendChild($dom->createElementNS($ns, 'CodigoDescuento', '06'));
                $descNode->appendChild($dom->createElementNS($ns, 'NaturalezaDescuento', 'Descuento general'));
                $linea->appendChild($descNode);
                $totalDescuentos += $montoDescuento;
            }

            $subtotalLinea = $montoBruto - $montoDescuento;
            $linea->appendChild($dom->createElementNS($ns, 'SubTotal', number_format($subtotalLinea, 5, '.', '')));
            // BaseImponible es requerido por XSD en cada línea
            $linea->appendChild($dom->createElementNS($ns, 'BaseImponible', number_format($subtotalLinea, 5, '.', '')));

            $impuestoLinea = 0.0;
            // Variables de clasificación para totales de servicios
            $codigoImpuestoClasif = null; // '01'..'99'
            $ivaRateClasif = 0.0;        // porcentaje IVA aplicado si Codigo='01'
            if ($usingSnapshot) {
                $tarifaImpuesto = (float)$prod->impuesto_porcentaje;
                $impuestoCalculado = $subtotalLinea * ($tarifaImpuesto / 100.0);
                $imp = $dom->createElementNS($ns, 'Impuesto');
                // Código del Impuesto (default IVA '01')
                $codigoImpuesto = $this->extractImpuestoCodigo($prod) ?? '01';
                $imp->appendChild($dom->createElementNS($ns, 'Codigo', $codigoImpuesto));
                $codigoImpuestoClasif = $codigoImpuesto;
                // Permitir override explícito del código de tarifa IVA (01..11) solo si Codigo='01'
                $codigoTarifaIva = null;
                if ($codigoImpuesto === '01') {
                    $codigoTarifaIvaOverride = $this->extractIvaTariffCodeOverride($prod);
                    $codigoTarifaIva = $codigoTarifaIvaOverride ?? $this->mapCodigoTarifaIVA($tarifaImpuesto);
                    if ($codigoTarifaIva) {
                        $imp->appendChild($dom->createElementNS($ns, 'CodigoTarifaIVA', $codigoTarifaIva));
                    }
                    $ivaRateClasif = (float)$tarifaImpuesto;
                }
                $imp->appendChild($dom->createElementNS($ns, 'Tarifa', number_format($tarifaImpuesto, 2, '.', '')));
                $imp->appendChild($dom->createElementNS($ns, 'Monto', number_format($impuestoCalculado, 5, '.', '')));
                $linea->appendChild($imp);
                $impuestoLinea += $impuestoCalculado;
                // Acumular
                $key = $codigoImpuesto . '-' . ($codigoImpuesto === '01' ? ($codigoTarifaIva ?? number_format($tarifaImpuesto, 2, '.', '')) : number_format($tarifaImpuesto, 2, '.', ''));
                if (!isset($impuestos_acumulados[$key])) {
                    $impuestos_acumulados[$key] = [
                        'Codigo' => $codigoImpuesto,
                        'CodigoTarifaIVA' => $codigoImpuesto === '01' ? $codigoTarifaIva : null,
                        'Monto' => 0.0,
                        'BaseImponible' => 0.0,
                    ];
                }
                $impuestos_acumulados[$key]['Monto'] += $impuestoCalculado;
                $impuestos_acumulados[$key]['BaseImponible'] += $subtotalLinea;
            } else {
                // Obtener información de impuesto: prioridad payload tax -> campo 'impuesto' del modelo/product array -> derivado por CABYS
                $taxPayload = $prod['tax'] ?? null;
                $taxRateFromModel = null;
                if (isset($productModel) && $productModel && $productModel->impuesto !== null) {
                    $taxRateFromModel = (float)$productModel->impuesto; // asumimos porcentaje directo
                } elseif (isset($prod['impuesto'])) {
                    $taxRateFromModel = (float)$prod['impuesto'];
                }

                if (!empty($taxPayload)) {
                $imp = $dom->createElementNS($ns, 'Impuesto');
                $codigoImpuesto = $this->sanitizeImpuestoCodigo($prod['tax']['code'] ?? null) ?? '01'; // 01=IVA por defecto
                $tarifaImpuesto = (float) ($prod['tax']['rate'] ?? 13.0);
                $impuestoCalculado = $subtotalLinea * ($tarifaImpuesto / 100.0);
                
                $imp->appendChild($dom->createElementNS($ns, 'Codigo', $codigoImpuesto));
                $codigoImpuestoClasif = $codigoImpuesto;
                // Mapear la tarifa IVA porcentual al código de tarifa IVA del XSD
                // Permitir override por payload: tax.iva_code, codigo_tarifa_iva, iva_code
                $codigoTarifaIva = null;
                if ($codigoImpuesto === '01') {
                    $codigoTarifaIvaOverride = $this->extractIvaTariffCodeOverride($prod);
                    $codigoTarifaIva = $codigoTarifaIvaOverride ?? $this->mapCodigoTarifaIVA($tarifaImpuesto);
                }
                if ($codigoImpuesto === '01' && $codigoTarifaIva !== null) {
                    $imp->appendChild($dom->createElementNS($ns, 'CodigoTarifaIVA', $codigoTarifaIva));
                }
                if ($codigoImpuesto === '01') {
                    $ivaRateClasif = (float)$tarifaImpuesto;
                }
                $imp->appendChild($dom->createElementNS($ns, 'Tarifa', number_format($tarifaImpuesto, 2, '.', '')));
                $imp->appendChild($dom->createElementNS($ns, 'Monto', number_format($impuestoCalculado, 5, '.', '')));
                $linea->appendChild($imp);
                
                $impuestoLinea += $impuestoCalculado;

                // Acumular impuestos para el resumen
                $key = "{$codigoImpuesto}-" . ($codigoImpuesto === '01' ? ($codigoTarifaIva ?? number_format($tarifaImpuesto, 2, '.', '')) : number_format($tarifaImpuesto, 2, '.', ''));
                if (!isset($impuestos_acumulados[$key])) {
                    $impuestos_acumulados[$key] = [
                        'Codigo' => $codigoImpuesto,
                        'CodigoTarifaIVA' => $codigoImpuesto === '01' ? $codigoTarifaIva : null,
                        'Monto' => 0.0,
                        'BaseImponible' => 0.0,
                    ];
                }
                $impuestos_acumulados[$key]['Monto'] += $impuestoCalculado;
                $impuestos_acumulados[$key]['BaseImponible'] += $subtotalLinea;
                } else {
                // Si no hay tax payload, usar impuesto directo del modelo si existe, o derivar del CABYS
                $appliedRate = $taxRateFromModel;
                if ($appliedRate === null) {
                    $derivedRate = null;
                    try {
                        $cabysRow = \App\Models\Cabys::find($cabys);
                        if ($cabysRow && $cabysRow->tax_rate !== null) {
                            $derivedRate = (float) $cabysRow->tax_rate;
                        }
                    } catch (\Throwable $e) {
                        // Ignorar errores de DB
                    }
                    $appliedRate = $derivedRate ?? 0.0;
                }
                $imp = $dom->createElementNS($ns, 'Impuesto');
                $codigoImpuesto = $this->extractImpuestoCodigo($prod) ?? '01';
                $imp->appendChild($dom->createElementNS($ns, 'Codigo', $codigoImpuesto));
                $codigoImpuestoClasif = $codigoImpuesto;
                // Override si viene en el arreglo de producto, solo aplica para IVA
                $codigoTarifaIva = null;
                if ($codigoImpuesto === '01') {
                    $codigoTarifaIvaOverride = $this->extractIvaTariffCodeOverride($prod);
                    $codigoTarifaIva = $codigoTarifaIvaOverride ?? $this->mapCodigoTarifaIVA($appliedRate);
                    if ($codigoTarifaIva !== null) {
                        $imp->appendChild($dom->createElementNS($ns, 'CodigoTarifaIVA', $codigoTarifaIva));
                    }
                    $ivaRateClasif = (float)$appliedRate;
                }
                $imp->appendChild($dom->createElementNS($ns, 'Tarifa', number_format($appliedRate, 2, '.', '')));
                $montoImp = $subtotalLinea * ($appliedRate / 100.0);
                $imp->appendChild($dom->createElementNS($ns, 'Monto', number_format($montoImp, 5, '.', '')));
                $linea->appendChild($imp);
                $impuestoLinea += $montoImp;
                // Acumular también en esta rama
                $key = $codigoImpuesto . '-' . ($codigoImpuesto === '01' ? ($codigoTarifaIva ?? number_format($appliedRate, 2, '.', '')) : number_format($appliedRate, 2, '.', ''));
                if (!isset($impuestos_acumulados[$key])) {
                    $impuestos_acumulados[$key] = [
                        'Codigo' => $codigoImpuesto,
                        'CodigoTarifaIVA' => $codigoImpuesto === '01' ? $codigoTarifaIva : null,
                        'Monto' => 0.0,
                        'BaseImponible' => 0.0,
                    ];
                }
                $impuestos_acumulados[$key]['Monto'] += $montoImp;
                $impuestos_acumulados[$key]['BaseImponible'] += $subtotalLinea;
                }
            }
            // Campos requeridos por XSD
            // ImpuestoAsumidoEmisorFabrica desde payload/snapshot (boolean o monto); 0 si no aplica
            $impAsumido = $this->extractImpuestoAsumidoEmisorFabrica($prod);
            $linea->appendChild($dom->createElementNS($ns, 'ImpuestoAsumidoEmisorFabrica', number_format($impAsumido, 5, '.', '')));
            $linea->appendChild($dom->createElementNS($ns, 'ImpuestoNeto', number_format($impuestoLinea, 5, '.', '')));
            $linea->appendChild($dom->createElementNS($ns, 'MontoTotalLinea', number_format($subtotalLinea + $impuestoLinea, 5, '.', '')));

            // Clasificar totales de servicios por CABYS (prefijos 5-9) y condición de IVA
            $cabysStr = (string)$cabys;
            $esServicio = $cabysStr !== '' && in_array($cabysStr[0], ['5','6','7','8','9'], true);
            if ($esServicio) {
                if ($codigoImpuestoClasif === '01') {
                    if ($ivaRateClasif > 0) {
                        // Gravado con IVA (usar monto bruto)
                        $totalServGravados += $montoBruto;
                    } else {
                        // IVA 0% -> exento (usar monto bruto)
                        $totalServExentos += $montoBruto;
                    }
                } else {
                    // No sujeto a IVA (otros impuestos/ninguno)
                    $totalServNoSujeto += $montoBruto;
                }
            }

            // Clasificación de mercancías según CABYS prefijos 0-4 y IVA
            $cabysStr = (string)$cabys;
            $esMercancia = $cabysStr !== '' && in_array($cabysStr[0], ['0','1','2','3','4'], true);
            if ($esMercancia) {
                if (isset($codigoImpuesto)) {
                    $codigoImpTmp = $codigoImpuesto;
                } else {
                    $codigoImpTmp = null;
                }
                // Tomar IVA rate para exoneración
                $ivaRateTmp = null;
                if (isset($tarifaImpuesto)) {
                    $ivaRateTmp = (float)$tarifaImpuesto;
                } elseif (isset($appliedRate)) {
                    $ivaRateTmp = (float)$appliedRate;
                }
                if ($codigoImpTmp === '01') {
                    // Exoneración (si aplica): monto bruto multiplicado por (1 - porcentaje exoneración)
                    $exoPct = $this->extractExonerationPercent($prod, $ivaRateTmp);
                    $baseConsiderada = $montoBruto * (1.0 - $exoPct);
                    if ($impAsumido > 0.0) {
                        // Excluir mercancías con IVA cobrado a nivel de fábrica
                        // No sumar a mercancias gravadas
                    } else {
                        if ($ivaRateTmp > 0.0 && $baseConsiderada > 0) {
                            $totalMercanciasGravadas += $baseConsiderada;
                        } else {
                            // 0% -> exentas o exoneradas
                            if ($exoPct > 0.0 && $baseConsiderada < $montoBruto) {
                                $totalMercanciasExonerada += ($montoBruto - $baseConsiderada);
                                $totalMercanciasExentas += $baseConsiderada;
                            } else {
                                $totalMercanciasExentas += $montoBruto;
                            }
                        }
                    }
                } else {
                    // No sujeto a IVA por estar bajo otro impuesto
                    // No se incluye en mercancias gravadas ni exentas
                }
            }

            $detalle->appendChild($linea);
            $totalVentaBruta += $montoTotal;
            $totalImpuesto += $impuestoLinea;
        }
        $root->appendChild($detalle);

        // 8) Resumen de Totales
        $resumen = $dom->createElementNS($ns, 'ResumenFactura');
        // CodigoTipoMoneda (complejo)
        $codigoMoneda = strtoupper((string)($invoice->currency ?? 'CRC'));
        $codigoTipoMoneda = $dom->createElementNS($ns, 'CodigoTipoMoneda');
        $codigoTipoMoneda->appendChild($dom->createElementNS($ns, 'CodigoMoneda', $codigoMoneda));
        // TipoCambio es requerido por el XSD v4.4; si no se provee, usar 1.00000
        $tipoCambio = $invoice->exchange_rate ?? 1.0;
        $codigoTipoMoneda->appendChild($dom->createElementNS($ns, 'TipoCambio', number_format((float)$tipoCambio, 5, '.', '')));
        $resumen->appendChild($codigoTipoMoneda);
        $resumen->appendChild($dom->createElementNS($ns, 'TotalServGravados', number_format($totalServGravados, 5, '.', '')));
        $resumen->appendChild($dom->createElementNS($ns, 'TotalServExentos', number_format($totalServExentos, 5, '.', '')));
        $resumen->appendChild($dom->createElementNS($ns, 'TotalServExonerado', number_format($totalServExonerado, 5, '.', '')));
        // En v4.4 existe TotalServNoSujeto
        $resumen->appendChild($dom->createElementNS($ns, 'TotalServNoSujeto', number_format($totalServNoSujeto, 5, '.', '')));
        $resumen->appendChild($dom->createElementNS($ns, 'TotalMercanciasGravadas', number_format($totalMercanciasGravadas, 5, '.', '')));
        $resumen->appendChild($dom->createElementNS($ns, 'TotalMercanciasExentas', number_format($totalMercanciasExentas, 5, '.', '')));
    // En v4.4 el nombre es TotalMercExonerada
    $resumen->appendChild($dom->createElementNS($ns, 'TotalMercExonerada', number_format($totalMercanciasExonerada, 5, '.', '')));
    // TotalGravado = TotalServGravados (con IVA) + TotalMercanciasGravadas (con IVA)
    $resumen->appendChild($dom->createElementNS($ns, 'TotalGravado', number_format($totalServGravados + $totalMercanciasGravadas, 5, '.', '')));
        $resumen->appendChild($dom->createElementNS($ns, 'TotalExento', number_format($totalServExentos, 5, '.', '')));
        $resumen->appendChild($dom->createElementNS($ns, 'TotalExonerado', number_format($totalServExonerado, 5, '.', '')));
        $resumen->appendChild($dom->createElementNS($ns, 'TotalVenta', number_format($totalVentaBruta, 5, '.', '')));
        $resumen->appendChild($dom->createElementNS($ns, 'TotalDescuentos', number_format($totalDescuentos, 5, '.', '')));
        $resumen->appendChild($dom->createElementNS($ns, 'TotalVentaNeta', number_format($totalVentaBruta - $totalDescuentos, 5, '.', '')));
        // Desglose de Impuestos (en v4.4 es TotalDesgloseImpuesto por cada código)
        if (!empty($impuestos_acumulados)) {
            foreach ($impuestos_acumulados as $acumulado) {
                $tdi = $dom->createElementNS($ns, 'TotalDesgloseImpuesto');
                $tdi->appendChild($dom->createElementNS($ns, 'Codigo', $acumulado['Codigo']));
                if ($acumulado['Codigo'] === '01' && !empty($acumulado['CodigoTarifaIVA'])) {
                    $tdi->appendChild($dom->createElementNS($ns, 'CodigoTarifaIVA', $acumulado['CodigoTarifaIVA']));
                }
                $tdi->appendChild($dom->createElementNS($ns, 'TotalMontoImpuesto', number_format($acumulado['Monto'], 5, '.', '')));
                $resumen->appendChild($tdi);
            }
        }
        $resumen->appendChild($dom->createElementNS($ns, 'TotalImpuesto', number_format($totalImpuesto, 5, '.', '')));

        // Medios de pago dentro de ResumenFactura (deben ir antes de TotalComprobante)
        $medioPago = $this->mapMedioPagoCode($invoice->payment_method ?? null);
        if ($medioPago !== null) {
            $medioPagoNode = $dom->createElementNS($ns, 'MedioPago');
            $medioPagoNode->appendChild($dom->createElementNS($ns, 'TipoMedioPago', $medioPago));
            $resumen->appendChild($medioPagoNode);
        }

        $resumen->appendChild($dom->createElementNS($ns, 'TotalComprobante', number_format($totalVentaBruta - $totalDescuentos + $totalImpuesto, 5, '.', '')));
        $root->appendChild($resumen);

        // 9) (Se elimina nodo top-level "Impuestos" no existente en el XSD v4.4)

        // 10) Codigos de Referencia
        // En v4.4 se utiliza el nodo InformacionReferencia (0..10)
        if (!empty($invoice->reference) && is_array($invoice->reference)) {
            // Ejemplo mínimo si llega una referencia con claves: tipoDocIR, numero, fecha, codigo, razon
            $ir = $dom->createElementNS($ns, 'InformacionReferencia');
            if (!empty($invoice->reference['tipoDocIR'])) {
                $ir->appendChild($dom->createElementNS($ns, 'TipoDocIR', substr((string)$invoice->reference['tipoDocIR'], 0, 2)));
            }
            if (!empty($invoice->reference['numero'])) {
                $ir->appendChild($dom->createElementNS($ns, 'Numero', substr((string)$invoice->reference['numero'], 0, 50)));
            }
            $ir->appendChild($dom->createElementNS($ns, 'FechaEmisionIR', $fechaEmision));
            if (!empty($invoice->reference['codigo'])) {
                $ir->appendChild($dom->createElementNS($ns, 'Codigo', substr((string)$invoice->reference['codigo'], 0, 2)));
            }
            if (!empty($invoice->reference['razon'])) {
                $ir->appendChild($dom->createElementNS($ns, 'Razon', substr((string)$invoice->reference['razon'], 0, 180)));
            }
            $root->appendChild($ir);
        }

        // 11) Otros
        if (!empty($invoice->others)) {
            $otrosNode = $dom->createElementNS($ns, 'Otros');
            // ... agregar lógica para otros datos ...
            $root->appendChild($otrosNode);
        }

        // Sanity check: validar que Emisor coincida con el titular del certificado configurado
       

        // 12) Firma XML con firmador alterno (único camino soportado)
        try {
            if (!$this->Signer) { throw new \RuntimeException('HACIENDA_USE_ALT_SIGNER debe ser true (firmador propio eliminado).'); }
            $dom = $this->Signer->sign($dom);
            // Importante: NO modificar el DOM después de firmar.
            // Cualquier cambio (p. ej., remover atributos Id) invalida la firma.
        } catch (\Throwable $e) {
            // No insertar firma placeholder: el XSD exige exactamente una ds:Signature válida
            // y el placeholder provocaba múltiples firmas. Propaga el error para corregir configuración.
            if (function_exists('logger')) {
                logger()->error('Error firmando XML: ' . $e->getMessage());
            }
            throw $e;
        }

        // 13) (Opcional) Validación local de la firma: removida junto con el firmador propio

    // Asegurar que no reformateamos al serializar; devolver exactamente los bytes presentes en el DOM
        return $dom->saveXML();
    }

    /**
     * Mapea una tarifa porcentual al código de tarifa IVA del XSD v4.4
     * 0 -> 01, 0.5 -> 09, 1 -> 02, 2 -> 03, 4 -> 04, 8 -> 07, 13 -> 08
     */
    private function mapCodigoTarifaIVA(float $tarifa): ?string
    {
        $map = [
            0.0 => '01',
            0.5 => '09',
            1.0 => '02',
            2.0 => '03',
            4.0 => '04',
            8.0 => '07',
            13.0 => '08',
        ];
        // Manejar floats con tolerancia
        foreach ($map as $rate => $code) {
            if (abs($tarifa - $rate) < 0.00001) return $code;
        }
        return null; // Desconocido o no aplica
    }

    /**
     * Mapea el método de pago de la factura a código v4.4
     * Cash -> 01, Card -> 02, SINPE -> 06; otros -> 99
     */
    private function mapMedioPagoCode(?string $paymentMethod): ?string
    {
        if (!$paymentMethod) return null;
        $normalized = strtolower($paymentMethod);
        return match ($normalized) {
            'cash', 'efectivo' => '01',
            'card', 'tarjeta' => '02',
            'sinpe', 'sinpe movil', 'sinpe_movil' => '06',
            default => '99',
        };
    }

    /**
     * DEPRECATED: No usar. Este método agregaba una firma placeholder (no criptográfica).
     * Mantener solo como referencia pero no llamar para evitar múltiples firmas inválidas.
     */
    private function appendPlaceholderSignature(DOMDocument $dom, DOMElement $parent): void
    {
        $dsNs = 'http://www.w3.org/2000/09/xmldsig#';
        $sig = $dom->createElementNS($dsNs, 'ds:Signature');
        $signedInfo = $dom->createElementNS($dsNs, 'ds:SignedInfo');
        $cm = $dom->createElementNS($dsNs, 'ds:CanonicalizationMethod');
        $cm->setAttribute('Algorithm', 'http://www.w3.org/2001/10/xml-exc-c14n#');
        $signedInfo->appendChild($cm);
        $sm = $dom->createElementNS($dsNs, 'ds:SignatureMethod');
        $sm->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');
        $signedInfo->appendChild($sm);
        $ref = $dom->createElementNS($dsNs, 'ds:Reference');
    // Referencia al documento completo (URI vacía) con transformada enveloped
    $ref->setAttribute('URI', '');
        $transforms = $dom->createElementNS($dsNs, 'ds:Transforms');
        $t1 = $dom->createElementNS($dsNs, 'ds:Transform');
        $t1->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($t1);
        $t2 = $dom->createElementNS($dsNs, 'ds:Transform');
        $t2->setAttribute('Algorithm', 'http://www.w3.org/2001/10/xml-exc-c14n#');
        $transforms->appendChild($t2);
        $ref->appendChild($transforms);
        $dm = $dom->createElementNS($dsNs, 'ds:DigestMethod');
        $dm->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $ref->appendChild($dm);
        $ref->appendChild($dom->createElementNS($dsNs, 'ds:DigestValue', base64_encode('placeholder')));
        $signedInfo->appendChild($ref);
        $sig->appendChild($signedInfo);
        $sig->appendChild($dom->createElementNS($dsNs, 'ds:SignatureValue', base64_encode('placeholder')));
        $keyInfo = $dom->createElementNS($dsNs, 'ds:KeyInfo');
        $x509 = $dom->createElementNS($dsNs, 'ds:X509Data');
        $x509->appendChild($dom->createElementNS($dsNs, 'ds:X509Certificate', base64_encode('placeholder')));
        $keyInfo->appendChild($x509);
        $sig->appendChild($keyInfo);
        $parent->appendChild($sig);
    }

    // Enforce min/max length on strings; padRight with a char if needed for min.
    private function enforceLength(string $value, int $min, int $max, string $padChar = ' '): string
    {
        $v = mb_substr($value, 0, $max, 'UTF-8');
        if (mb_strlen($v, 'UTF-8') < $min) {
            $v = str_pad($v, $min, $padChar);
        }
        return $v;
    }

    // Sanitize identification type to allowed set {'01','02','03','04','05','06'}
    private function sanitizeIdentType(?string $tipo, string $fallback = '02'): string
    {
        $tipo = $tipo ?: '';
        $allowed = ['01','02','03','04','05','06'];
        if (!in_array($tipo, $allowed, true)) {
            return $fallback;
        }
        return $tipo;
    }

    /**
     * Valida un código de tarifa IVA permitido por el XSD (01..11).
     */
    private function sanitizeIvaTariffCode(?string $code): ?string
    {
        if ($code === null) return null;
        $c = trim($code);
        // normalizar: aceptar 1..11 y convertir a 2 dígitos
        if (ctype_digit($c)) {
            $n = (int)$c;
            if ($n >= 1 && $n <= 11) {
                return str_pad((string)$n, 2, '0', STR_PAD_LEFT);
            }
        }
        // aceptar directamente cadenas '01'..'11'
        $allowed = ['01','02','03','04','05','06','07','08','09','10','11'];
        return in_array($c, $allowed, true) ? $c : null;
    }

    /**
     * Extrae un override de CódigoTarifaIVA desde distintos campos comunes.
     * Prioridad: $prod['tax']['iva_code'] -> $prod['codigo_tarifa_iva'] -> $prod['iva_code']
     * En snapshots (objetos), busca propiedades similares si existen.
     */
    private function extractIvaTariffCodeOverride($prod): ?string
    {
        $candidate = null;
        // Arrays de payload
        if (is_array($prod)) {
            if (isset($prod['tax']) && is_array($prod['tax']) && isset($prod['tax']['iva_code'])) {
                $candidate = (string)$prod['tax']['iva_code'];
            } elseif (isset($prod['codigo_tarifa_iva'])) {
                $candidate = (string)$prod['codigo_tarifa_iva'];
            } elseif (isset($prod['iva_code'])) {
                $candidate = (string)$prod['iva_code'];
            }
        } else {
            // Objetos (snapshots u otros modelos)
            try {
                if (isset($prod->codigo_tarifa_iva)) {
                    $candidate = (string)$prod->codigo_tarifa_iva;
                } elseif (isset($prod->iva_code)) {
                    $candidate = (string)$prod->iva_code;
                }
            } catch (\Throwable $e) {
                // ignorar
            }
        }
        return $this->sanitizeIvaTariffCode($candidate);
    }

    /**
     * Valida y normaliza Código del Impuesto según tabla oficial.
     * Permitidos: 01,02,03,04,05,06,07,08,12,99
     */
    private function sanitizeImpuestoCodigo(?string $code): ?string
    {
        if ($code === null) return null;
        $c = trim($code);
        // aceptar dígitos crudos
        if (ctype_digit($c)) {
            // 1..9 -> pad a 2; 12 -> '12'; 99 -> '99'
            $n = (int)$c;
            if (in_array($n, [1,2,3,4,5,6,7,8,12,99], true)) {
                if ($n >= 1 && $n <= 9) return '0' . $n;
                return (string)$n;
            }
        }
        // aceptar strings '01','02',...,'12','99'
        $allowed = ['01','02','03','04','05','06','07','08','12','99'];
        return in_array($c, $allowed, true) ? $c : null;
    }

    /**
     * Extrae Código del Impuesto desde varias formas de payload/snapshot.
     * Prioridad: prod['tax']['code'] -> prod['impuesto_codigo'] -> prod['tax_code'] -> propiedades del objeto
     */
    private function extractImpuestoCodigo($prod): ?string
    {
        $candidate = null;
        if (is_array($prod)) {
            if (isset($prod['tax']) && is_array($prod['tax']) && isset($prod['tax']['code'])) {
                $candidate = (string)$prod['tax']['code'];
            } elseif (isset($prod['impuesto_codigo'])) {
                $candidate = (string)$prod['impuesto_codigo'];
            } elseif (isset($prod['tax_code'])) {
                $candidate = (string)$prod['tax_code'];
            }
        } else {
            try {
                if (isset($prod->impuesto_codigo)) {
                    $candidate = (string)$prod->impuesto_codigo;
                } elseif (isset($prod->tax_code)) {
                    $candidate = (string)$prod->tax_code;
                } elseif (isset($prod->codigo_impuesto)) {
                    $candidate = (string)$prod->codigo_impuesto;
                }
            } catch (\Throwable $e) {
                // ignorar
            }
        }
        return $this->sanitizeImpuestoCodigo($candidate);
    }

    /**
     * Extrae el valor de ImpuestoAsumidoEmisorFabrica (IVA cobrado a nivel de fábrica) por línea.
     * Retorna un float >= 0 (monto) o 0.0 si no aplica.
     */
    private function extractImpuestoAsumidoEmisorFabrica($prod): float
    {
        $val = 0.0;
        if (is_array($prod)) {
            if (isset($prod['impuesto_asumido_emisor_fabrica'])) {
                $val = (float)$prod['impuesto_asumido_emisor_fabrica'];
            } elseif (isset($prod['factory_vat']) && is_numeric($prod['factory_vat'])) {
                $val = (float)$prod['factory_vat'];
            } elseif (isset($prod['factory_vat']) && $prod['factory_vat'] === true) {
                $val = 1.0; // marcar como aplicado si bool
            }
        } else {
            try {
                if (isset($prod->impuesto_asumido_emisor_fabrica)) {
                    $val = (float)$prod->impuesto_asumido_emisor_fabrica;
                } elseif (isset($prod->factory_vat)) {
                    $val = is_bool($prod->factory_vat) ? ($prod->factory_vat ? 1.0 : 0.0) : (float)$prod->factory_vat;
                }
            } catch (\Throwable $e) {
                // ignorar
            }
        }
        return max(0.0, $val);
    }

    /**
     * Extrae el porcentaje de exoneración de IVA, como valor entre 0 y 1.
     * Fuentes: exoneracion.porcentaje (0..100) o ratio tarifa_exonerada/tarifa_iva.
     */
    private function extractExonerationPercent($prod, ?float $ivaRate): float
    {
        $pct = 0.0;
        $rate = $ivaRate ?? null;
        if (is_array($prod)) {
            if (isset($prod['exoneracion']) && is_array($prod['exoneracion'])) {
                $exo = $prod['exoneracion'];
                if (isset($exo['porcentaje'])) {
                    $pct = max(0.0, min(100.0, (float)$exo['porcentaje'])) / 100.0;
                } elseif (isset($exo['tarifa_exonerada']) && isset($exo['tarifa_iva'])) {
                    $tExo = (float)$exo['tarifa_exonerada'];
                    $tIva = (float)$exo['tarifa_iva'];
                    if ($tIva > 0) $pct = max(0.0, min(1.0, $tExo / $tIva));
                }
            }
        } else {
            try {
                if (isset($prod->exoneracion_porcentaje)) {
                    $pct = max(0.0, min(100.0, (float)$prod->exoneracion_porcentaje)) / 100.0;
                } elseif (isset($prod->tarifa_exonerada) && isset($prod->tarifa_iva)) {
                    $tExo = (float)$prod->tarifa_exonerada;
                    $tIva = (float)$prod->tarifa_iva;
                    if ($tIva > 0) $pct = max(0.0, min(1.0, $tExo / $tIva));
                }
            } catch (\Throwable $e) {
                // ignorar
            }
        }
        // si no vino tarifa_iva explícita, usar ivaRate pasada
        return max(0.0, min(1.0, $pct));
    }

    /**
     * Formatea un número con máximo N decimales, eliminando ceros a la derecha.
     * Siempre usa el punto como separador decimal, y retorna al menos '0' si el valor es 0.
     */
    private function formatDecimal(float $value, int $maxDecimals = 3): string
    {
        // Primero limitar a la precisión deseada para evitar arrastre binario
        $formatted = number_format($value, $maxDecimals, '.', '');
        // Remover ceros a la derecha y el punto si queda al final
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    }
}
