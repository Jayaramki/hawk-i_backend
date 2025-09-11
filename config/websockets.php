<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the connections below you wish to use as
    | your default connection for all work. Of course, you may use many
    | connections at once using the manager class.
    |
    */

    'default' => env('WEBSOCKETS_CONNECTION', 'main'),

    /*
    |--------------------------------------------------------------------------
    | WebSocket Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the connections setup for your application. Example
    | configuration has been included, but you may add as many connections as
    | you would like.
    |
    */

    'connections' => [

        'main' => [
            'host' => env('WEBSOCKETS_HOST', '127.0.0.1'),
            'port' => env('WEBSOCKETS_PORT', 6001),
            'scheme' => env('WEBSOCKETS_SCHEME', 'http'),
            'ssl' => [
                'local_cert' => env('WEBSOCKETS_SSL_LOCAL_CERT', null),
                'local_pk' => env('WEBSOCKETS_SSL_LOCAL_PK', null),
                'verify_peer' => false,
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | SSL Context
    |--------------------------------------------------------------------------
    |
    | See: http://php.net/manual/en/context.ssl.php
    |
    */

    'ssl' => [
        'local_cert' => env('WEBSOCKETS_SSL_LOCAL_CERT', null),
        'local_pk' => env('WEBSOCKETS_SSL_LOCAL_PK', null),
        'verify_peer' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Handlers
    |--------------------------------------------------------------------------
    |
    | Here you may specify the route handlers that will take over WebSocket
    | connections. You may add as many handlers as you would like.
    |
    */

    'handlers' => [
        'sync-progress' => \App\WebSocket\Handlers\SyncProgressHandler::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    |
    | By default, WebSocket statistics are enabled. This will track various
    | metrics about your WebSocket connections and usage.
    |
    */

    'statistics' => [
        'enabled' => env('WEBSOCKETS_STATISTICS_ENABLED', true),
        'model' => \BeyondCode\LaravelWebSockets\Statistics\Models\WebSocketsStatisticsEntry::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Request Size
    |--------------------------------------------------------------------------
    |
    | The maximum request size in bytes that is allowed for an incoming WebSocket
    | request. If a request is larger than this value, the connection will be
    | rejected.
    |
    */

    'max_request_size_in_kb' => 250,

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Here you may specify the route information, including the URI and methods,
    | that are used to handle WebSocket connections.
    |
    */

    'routes' => [
        'prefix' => 'ws',
        'middleware' => ['web'],
    ],

];
