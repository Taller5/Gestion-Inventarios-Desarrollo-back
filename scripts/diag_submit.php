<?php
// Diagnostic script for submit issues

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Invoice;
use App\Services\HaciendaTokenService;

function println($msg) { echo $msg . PHP_EOL; }

println('=== DIAGNOSTIC: Invoice 1 submit ===');
$invoice = Invoice::find(1);
if (!$invoice) {
    println('Invoice 1 not found');
    exit(2);
}
$xml = $invoice->xmls()->latest('id')->first();
if (!$xml) {
    println('No InvoiceXml record found. Generate via GET /api/v1/invoices/1/xml');
} else {
    println('InvoiceXml id=' . $xml->id);
    println(' - status=' . ($xml->status ?? ''));
    println(' - schema_valid=' . var_export($xml->schema_valid, true));
    println(' - signature_valid=' . var_export($xml->signature_valid, true));
    println(' - clave=' . ($xml->clave ?? 'NULL'));
    if (!empty($xml->error_message)) {
        println(' - error_message=' . $xml->error_message);
    }
}

println('=== Checking Hacienda token ===');
try {
    $svc = new HaciendaTokenService();
    $token = $svc->getAccessToken();
    println('Token OK (length=' . strlen($token) . ')');
} catch (\Throwable $e) {
    println('Token ERROR: ' . $e->getMessage());
}

println('=== ENV snapshot ===');
println('HACIENDA_ENV=' . (config('services.hacienda.env') ?? getenv('HACIENDA_ENV')));
println('HACIENDA_TOKEN_URL_STAG=' . (config('services.hacienda.token_url_stag') ?? getenv('HACIENDA_TOKEN_URL_STAG') ?? 'null'));
println('HACIENDA_RECEPCION_URL_STAG=' . (config('services.hacienda.recepcion_url_stag') ?? getenv('HACIENDA_RECEPCION_URL_STAG') ?? 'null'));
println('HACIENDA_USERNAME=' . (config('services.hacienda.username') ? '[set]' : '[empty]'));
println('HACIENDA_PASSWORD=' . (config('services.hacienda.password') ? '[set]' : '[empty]'));

println('=== CERT snapshot ===');
println('HACIENDA_CERT_P12_PATH=' . (config('services.hacienda.cert_p12_path') ?? getenv('HACIENDA_CERT_P12_PATH')));
println('HACIENDA_CERT_PASSWORD=' . (config('services.hacienda.cert_password') ? '[set]' : '[empty]'));
