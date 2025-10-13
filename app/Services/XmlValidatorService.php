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
        $dom->loadXML($xmlString);

        libxml_use_internal_errors(true);
        $isValid = $dom->schemaValidate($xsdPath);
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
