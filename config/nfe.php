<?php

return [
    /*
    |--------------------------------------------------------------------------
    | NFe Fiscal Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for SEFAZ environment, issuer details, and A1 digital cert.
    |
    */

    'environment' => (int) env('NFE_ENVIRONMENT', 2), // 1: Production, 2: Homologation
    'company_name' => env('NFE_COMPANY_NAME', 'Empresa Teste LTDA'),
    'company_cnpj' => env('NFE_COMPANY_CNPJ', '00000000000000'),
    'company_uf' => env('NFE_COMPANY_UF', 'PI'),

    /*
    |--------------------------------------------------------------------------
    | Digital Certificate (A1 .pfx / .p12)
    |--------------------------------------------------------------------------
    |
    | Absolute path to .pfx file or base64 encoded content.
    |
    */
    'cert_path' => env('NFE_CERT_PATH'),
    'cert_password' => env('NFE_CERT_PASSWORD', ''),
];
