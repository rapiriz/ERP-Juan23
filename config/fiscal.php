<?php

return [
    // Preparación de homologación. No habilita emisión ni convierte PDFs internos en fiscales.
    'environment' => env('FISCAL_ENVIRONMENT', 'homologacion'),
    'cuit' => env('FISCAL_CUIT'),
    'point_of_sale' => env('FISCAL_POINT_OF_SALE'),
    'certificate_path' => env('FISCAL_CERTIFICATE_PATH'),
    'private_key_path' => env('FISCAL_PRIVATE_KEY_PATH'),
    'enabled' => false,
];
