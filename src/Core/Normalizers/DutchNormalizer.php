<?php

namespace Blaspsoft\Blasp\Core\Normalizers;

class DutchNormalizer implements StringNormalizer
{
    public function normalize(string $string): string
    {
        $dutchMappings = [
            'ë' => 'e', 'Ë' => 'E',
            'ï' => 'i', 'Ï' => 'I',
            'ü' => 'u', 'Ü' => 'U',
            'ö' => 'o', 'Ö' => 'O',
            'é' => 'e', 'É' => 'E',
            'è' => 'e', 'È' => 'E',
            'ê' => 'e', 'Ê' => 'E',
            'á' => 'a', 'Á' => 'A',
            'à' => 'a', 'À' => 'A',
            'â' => 'a', 'Â' => 'A',
            'ó' => 'o', 'Ó' => 'O',
            'ú' => 'u', 'Ú' => 'U',
            'û' => 'u', 'Û' => 'U',
            'í' => 'i', 'Í' => 'I',
        ];

        return strtr($string, $dutchMappings);
    }
}
