<?php

return [
    // Set this to the backend subdomain assigned to this deployment (host only).
    'api_host' => env('TENANT_API_HOST'),

    // A four-digit POS PIN is not suitable for a public Internet login endpoint.
    'allow_pin_login' => env('ALLOW_PIN_LOGIN', env('APP_ENV') !== 'production'),
];
