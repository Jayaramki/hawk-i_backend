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
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'azure_devops' => [
        'organization' => env('AZURE_DEVOPS_ORG'),
        'project' => env('AZURE_DEVOPS_PROJECT', null),
        'pat' => env('AZURE_DEVOPS_PAT'),
        'base_url' => env('AZURE_DEVOPS_BASE_URL', 'https://dev.azure.com'),
        'graph_api_url' => env('AZURE_DEVOPS_GRAPH_API_URL', 'https://vssps.dev.azure.com'),
        'api_version' => env('AZURE_DEVOPS_API_VERSION', '7.0'),
        'graph_api_version' => env('AZURE_DEVOPS_GRAPH_API_VERSION', '7.0-preview.1'),
    ],

];
