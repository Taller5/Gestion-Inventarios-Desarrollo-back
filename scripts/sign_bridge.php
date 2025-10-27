<?php
// Bridge script to sign an XML using the alternate signer in isolation.
// Usage: php scripts/sign_bridge.php <alt_dir> <p12_path> <pin> <in_xml_path> <out_xml_path>

// Ensure we are running under CLI; if not, fail with a clear message.
if (php_sapi_name() !== 'cli') {
    echo "This script must be executed via PHP CLI.\n";
    exit(97);
}

if (!isset($argc) || $argc < 6) {
    fwrite(STDERR, "Usage: php sign_bridge.php <alt_dir> <p12_path> <pin> <in_xml_path> <out_xml_path>\n");
    exit(2);
}

[$script, $altDir, $p12Path, $pin, $inPath, $outPath] = $argv;

$altDir = rtrim($altDir, "\\/\r\n ");
$inPath = rtrim($inPath, "\r\n ");
$outPath = rtrim($outPath, "\r\n ");

$firmadorPhp = $altDir . DIRECTORY_SEPARATOR . 'hacienda' . DIRECTORY_SEPARATOR . 'firmador.php';
if (!is_file($firmadorPhp)) {
    fwrite(STDERR, "firmador.php not found at: {$firmadorPhp}\n");
    exit(3);
}

if (!is_file($p12Path)) {
    fwrite(STDERR, "P12 not found at: {$p12Path}\n");
    exit(4);
}
if (!is_file($inPath)) {
    fwrite(STDERR, "Input XML not found at: {$inPath}\n");
    exit(5);
}

require $firmadorPhp; // define la clase \Hacienda\Firmador obtiene su propia configuración interna

try {
    $firmador = new \Hacienda\Firmador();
    // Realizar la firma
    $res = $firmador->firmarXml($p12Path, $pin, $inPath, \Hacienda\Firmador::TO_XML_FILE, $outPath);
    if ($res === false || !is_file($outPath)) {
        fwrite(STDERR, "Signing failed (no output).\n");
        exit(6);
    }
    echo "OK\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(7);
}
