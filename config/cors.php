<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],

    'allowed_origins' => ['*'],

    'allowed_originsTemp' => [
        'http://localhost:3000',
        '*.devxdao.com',
        'https://devxdao.com',
        'https://portal.devxdao.com',
        'https://backend.devxdao.com',
        'https://pm.devxdao.com',
        'https://compliance.devxdao.com',
        'https://discourse.devxdao.com',
        'https://dxd.stage.ledgerleap.com',
        'https://dxd-backend.stage.ledgerleap.com',
        'https://dxd-pm.stage.ledgerleap.com',
        'https://dxd-compliance.stage.ledgerleap.com',
        'https://dxd-discourse.stage.ledgerleap.com',
        'https://stage.kyckangaroo.com',
        'https://stagebackend.kyckangaroo.com',
        'https://eta.kyckangaroo.com',
        'https://etabackend.kyckangaroo.com',
        'https://smtp.sendgrid.net',
        'https://stripe.com',
        'https://api.stripe.com',
        'https://coinmarketcap.com',
        'https://pro-api.coinmarketcap.com',
        'https://hellosign.com',
        'https://api.hellosign.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
