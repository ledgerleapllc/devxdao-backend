<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'hellosign' => [
        'api_key' => env('HELLOSIGN_API_KEY'),
        'client_id' => env('HELLOSIGN_CLIENT_ID'),
        'associate_template_id' => env('HELLOSIGN_ASSOCIATE_TEMPLATE_ID'),
        'membership_template_id' => env('HELLOSIGN_MEMBERSHIP_TEMPLATE_ID'),
    ],

    'stripe' => [
        'sk_live' => env('STRIPE_SK_LIVE'),
        'sk_test' => env('STRIPE_SK_TEST'),
    ],

    'x_cmc_pro' => [
        'api_key' => env('X_CMC_PRO_API_KEY'),
    ],

    'shuftipro' => [
        'client_id_prod' => env('SHUFTIPRO_CLIENTID_PROD'),
        'client_id_test' => env('SHUFTIPRO_CLIENTID_TEST'),
        'client_secret_prod' => env('SHUFTIPRO_CLIENTSECRET_PROD'),
        'client_secret_test' => env('SHUFTIPRO_CLIENTSECRET_TEST'),
        'pass' => env('SHUFTI_PASS'),
    ],

    'kyc_kangaroo' => [
        'url' => env('KYC_KANGAROO_URL'),
        'token' => env('KYC_KANGAROO_TOKEN'),
    ],

    'external_api' => [
        'token' => env('EXTERNAL_API_TOKEN'),
    ],

    'crypto' => [
        'eth' => [
            'secret_code' => env('ETH_SECRET_CODE')
        ]
    ]
];
