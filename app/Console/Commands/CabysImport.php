<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Cabys;

class CabysImport extends Command
{
    protected $signature = 'cabys:import {file} {--truncate} {--limit=} {--debug}';
    protected $description = 'Importa el catálogo CABYS desde CSV (soporta --limit y --debug)';

    public function handle(): int
    {
        $file = $this->argument('file');
        $limit = $this->option('limit') ? (int)$this->option('limit') : null;
        $debug = $this->option('debug');
        $start = microtime(true);

        if (!file_exists($file)) {
            $this->error("Archivo no encontrado: $file");
            return 1;
        }
        if ($this->option('truncate')) {
            DB::table('cabys')->truncate();
            $this->info('Tabla cabys truncada.');
        }

        $fh = fopen($file, 'r');
        if (!$fh) {
            $this->error('No se pudo abrir el archivo.');
            return 1;
        }

        $sanitize = function ($value) {
            if ($value === null) return null;
            $value = trim($value);
            if ($value === '') return null;
            // Si ya es UTF-8 válido, no intentar recodificar (evita producir bÃº, etc.)
            if (!mb_check_encoding($value, 'UTF-8')) {
                $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
                if ($converted !== false && $converted !== '') {
                    $value = $converted;
                }
            }
            // Reparar mojibake solo si detectamos patrones 'Ã' + vocal/minúscula típica
            if (preg_match('/Ã[¡Â°©®¼½¾µº±¢£§¨©ª«¬®¯²³´µ¶·¸»¼½¾]/u', $value) || str_contains($value, 'Ãº') || str_contains($value,'Ã±')) {
                // Intento de “deshacer” doble codificación: convertir UTF-8 interpretado como ISO-8859-1 nuevamente a UTF-8
                $decoded = utf8_decode($value); // ahora bytes ISO-8859-1
                $value = mb_convert_encoding($decoded, 'UTF-8', 'ISO-8859-1');
            }
            return $value;
        };

        $inserted = 0; $updated = 0; $skipped = 0; $line = 0; $batch = []; $batchSize = 500; $headerSkipped = false;
        while (($row = fgetcsv($fh, 0, ';')) !== false) {
            $line++;
            // Strip BOM on first cell (UTF-8 BOM = EF BB BF) if present
            if ($line === 1 && isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
            }
            // Saltar fila si está totalmente vacía o solo delimitadores
            if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) { continue; }
            $first = trim($row[0] ?? '');
            // Detectar y saltar encabezado (primer campo contiene 'Categoría 1')
            if (!$headerSkipped && preg_match('/^Categor[íi]a 1$/u', $first)) {
                $headerSkipped = true;
                if ($debug) { $this->info('Encabezado detectado y saltado.'); }
                continue;
            }
            // Si aún no se ha visto encabezado y primer campo no es dígito, probablemente basura previa
            if (!$headerSkipped && !ctype_digit($first)) { if ($debug) { $this->warn('Fila previa a encabezado ignorada (#'.$line.')'); } continue; }
            if (count($row) < 19) { $skipped++; if ($debug) $this->warn('Fila '.$line.' con columnas insuficientes ('.count($row).')'); continue; }

            // Buscar código más profundo (último código no vacío entre columnas pares <= 16)
            $code = $description = null;
            for ($i = 16; $i >= 0; $i -= 2) {
                if (!empty($row[$i])) {
                    $candidate = trim($row[$i]);
                    if ($candidate !== '') {
                        $code = $sanitize($candidate);
                        $description = $sanitize($row[$i + 1] ?? '');
                        break;
                    }
                }
            }
            if (!$code) { $skipped++; if ($debug) $this->warn('Fila '.$line.' sin código derivado'); continue; }
            // Validar que el código sea numérico (CABYS es sólo dígitos)
            if (!ctype_digit($code)) { $skipped++; if ($debug) $this->warn('Fila '.$line.' código no numérico: '.$code); continue; }

            $taxRaw = trim($row[18] ?? '');
            $tax_rate = $taxRaw !== '' ? (float) str_replace([',', '%'], ['.', ''], $taxRaw) : null;
            $note_include = $sanitize($row[19] ?? null);
            $note_exclude = $sanitize($row[20] ?? null);
            $category_main_name = $sanitize($row[1] ?? null);
            $category_2 = $sanitize($row[3] ?? null);
            $category_3 = $sanitize($row[5] ?? null);
            $category_4 = $sanitize($row[7] ?? null);
            $category_5 = $sanitize($row[9] ?? null);
            $category_6 = $sanitize($row[11] ?? null);
            $category_7 = $sanitize($row[13] ?? null);
            $category_8 = $sanitize($row[15] ?? null);
            $category_main = substr($code,0,1);

            $exists = Cabys::where('code', $code)->exists();
            $data = [
                'code' => $code,
                'description' => $description,
                'tax_rate' => $tax_rate,
                'category_main' => $category_main,
                'category_main_name' => $category_main_name,
                'category_2' => $category_2,
                'category_3' => $category_3,
                'category_4' => $category_4,
                'category_5' => $category_5,
                'category_6' => $category_6,
                'category_7' => $category_7,
                'category_8' => $category_8,
                'note_include' => $note_include,
                'note_exclude' => $note_exclude,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($exists) {
                Cabys::where('code', $code)->update($data);
                $updated++;
            } else {
                $batch[] = $data;
                $inserted++;
                if ($debug) { $this->line('Fila '.$line.' preparada para insertar código '.$code); }
            }

            if ($debug && ($inserted + $updated + $skipped) === 0) {
                $this->info('DEBUG Primera fila category_2 RAW: ' . ($row[3] ?? 'NULL'));
                $this->info('DEBUG Primera fila category_2 SANITIZED: ' . ($category_2 ?? 'NULL'));
            }

            if (count($batch) >= $batchSize) {
                Cabys::insert($batch);
                $batch = [];
            }
            if (($inserted + $updated) % 1000 === 0) {
                $this->info('Procesadas ' . ($inserted + $updated) . ' filas...');
            }
            if ($limit && ($inserted + $updated) >= $limit) { break; }
        }
    if ($batch) { Cabys::insert($batch); if ($debug) $this->info('Flush final batch de '.count($batch).' filas'); }
        fclose($fh);
        $elapsed = number_format(microtime(true) - $start, 2);
        $this->info("Importación CABYS finalizada. Insertados: $inserted, Actualizados: $updated, Omitidos: $skipped. Tiempo: {$elapsed}s");
        return 0;
    }
}
