<?php

return [

    'name' => env('APP_NAME', 'Facial Attendance System'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    // The server always runs and stores dates in UTC. The company operational
    // timezone (kiosk, tardiness rules) and each user's display timezone are
    // configured inside the application (Settings and user preferences).
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    // Fallback display timezone used before the settings table exists
    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'America/Lima'),

    'locale' => env('APP_LOCALE', 'es'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'es_PE'),

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
