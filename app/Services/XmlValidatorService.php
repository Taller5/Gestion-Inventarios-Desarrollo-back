<?php

namespace App\Services;

class XmlValidatorService
{
    /**
     * Valida un XML contra un XSD y retorna true/false y los errores.
     * @param string $xmlString
     * @param string $xsdPath
     * @return array [bool $isValid, string[] $errors]
     */
    public function validateXmlAgainstXsd(string $xmlString, string $xsdPath): array
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        // Evitar que warnings pasen a excepciones: usar libxml internal errors
        libxml_use_internal_errors(true);
        // loadXML puede emitir warnings; silenciarlos y usar libxml_get_errors
        @$dom->loadXML($xmlString);

        // schemaValidate a veces emite warnings que pueden convertirse
        // en errores según la configuración; silenciamos la llamada y
        // recogemos errores vía libxml_get_errors().
        $isValid = @($dom->schemaValidate($xsdPath));
        $errors = [];
        if (!$isValid) {
            foreach (libxml_get_errors() as $error) {
                $errors[] = trim($error->message);
            }
        }
        libxml_clear_errors();
        return [$isValid, $errors];
    }
}
