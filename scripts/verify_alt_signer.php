<?php
/*
 * Verificación del firmador alternativo.
 * - Comprueba que HACIENDA_ALT_SIGNER_DIR esté definido.
 * - Verifica la existencia de hacienda/firmador.php.
 * - Opcional: imprime versión/commit si el directorio es un repo Git.
 *
 * Uso: php scripts/verify_alt_signer.php
 */

if (php_sapi_name() !== 'cli') {
    echo "Ejecuta este script por CLI (php).\n";
    exit(97);
}

$altDir = getenv('HACIENDA_ALT_SIGNER_DIR') ?: '';
if ($altDir === '') {
    fwrite(STDERR, "HACIENDA_ALT_SIGNER_DIR no está definido.\n");
    exit(2);
}
$altDir = rtrim($altDir, "\\/\r\n ");
$entry = $altDir . DIRECTORY_SEPARATOR . 'hacienda' . DIRECTORY_SEPARATOR . 'firmador.php';
if (!is_file($entry)) {
    fwrite(STDERR, "No se encontró hacienda/firmador.php en: {$entry}\n");
    exit(3);
}

echo "OK: firmador encontrado en {$entry}\n";

// Si es un repo git, intentar imprimir el commit actual
$gitDir = $altDir . DIRECTORY_SEPARATOR . '.git';
if (is_dir($gitDir)) {
    // Buscar git en PATH
    $which = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where git' : 'which git';
    @exec($which, $out, $ret);
    if ($ret === 0) {
        $cmd = 'git -C ' . escapeshellarg($altDir) . ' rev-parse --short HEAD';
        @exec($cmd, $revOut, $revRet);
        if ($revRet === 0 && !empty($revOut[0])) {
            echo "Commit actual del submódulo: " . trim($revOut[0]) . "\n";
        }
    }
}

exit(0);
