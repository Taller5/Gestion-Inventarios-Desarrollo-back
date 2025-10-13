<?php

use App\Models\Invoice;
use App\Services\FacturaElectronicaXmlService;
use App\Services\XmlValidatorService;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Crear invoice en memoria (no persiste) simulando estructura de DB
$invoice = new Invoice([
    'customer_name' => 'Cliente Prueba',
    'customer_identity_number' => '123456789',
    'customer_id_type' => '01',
    'branch_name' => 'Sucursal Centro',
    'business_name' => 'Mi Empresa S.A.',
    'business_legal_name' => 'Mi Empresa Sociedad Anonima',
    'business_phone' => '88888888',
    'business_email' => 'info@miempresa.cr',
    'province' => '1',
    'canton' => '01',
    'district' => '01',
    'address' => '300m norte, 50m este',
    'business_id_type' => '02',
    'business_id_number' => '3012345678',
    'cashier_name' => 'Cajero',
    'date' => now(),
    'products' => [
        [
            'cabys' => '1010101010101',
            'quantity' => 1,
            'unit' => 'Unid',
            'description' => 'Producto A',
            'price' => 1000,
            'discount' => 0,
            'tax' => ['code' => '01', 'rate' => 13]
        ]
    ],
    'currency' => 'CRC',
    'payment_method' => 'Cash',
]);

$xmlService = new FacturaElectronicaXmlService();
$xml = $xmlService->generarXml($invoice);

$validator = new XmlValidatorService();
$xsdPath = base_path('FacturaElectronica_V4.4.xsd');
[$isValid, $errors] = $validator->validateXmlAgainstXsd($xml, $xsdPath);

echo $isValid ? "VALIDO\n" : "INVALIDO\n";
if (!$isValid) {
    foreach ($errors as $e) echo "- $e\n";
}

