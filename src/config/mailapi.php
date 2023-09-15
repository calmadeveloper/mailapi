<?php

return [
    'api_key' => env('MAILAPI_KEY'),
    'endpoint' => env('MAILAPI_ENDPOINT'),
    'connection' => env('MAILAPI_CONNECTION', env('QUEUE_CONNECTION')),
    'queue' => env('MAILAPI_QUEUE', 'mailapi'),

    'dev_environments' => ['dev', 'local'],

    'dev_force_enabled' => env('MAILAPI_DEV_FORCE_ENABLED', false),
    'dev_force_to' => env('MAILAPI_DEV_FORCE_TO'),
    'dev_force_cc' => env('MAILAPI_DEV_FORCE_CC'),
    'dev_force_bcc' => env('MAILAPI_DEV_FORCE_BCC'),
];
