<?php

return [
    'api_key' => env('MAILAPI_KEY'),
    'endpoint' => env('MAILAPI_ENDPOINT'),
    'connection' => env('MAILAPI_CONNECTION', env('QUEUE_CONNECTION')),
    'queue' => env('MAILAPI_QUEUE', 'mailapi'),
];
