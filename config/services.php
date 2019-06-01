<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'forge' => [
        'token' => env('FORGE_TOKEN'),
    ],

    'aws' => [
        'key' => env('AWS_KEY'),
        'secret' => env('AWS_SECRET'),
    ],

    'logdna' => [
        'key' => env('LOGDNA_KEY'),
    ],

    'datadog' => [
        'key' => env('DATADOG_KEY'),
    ],

];
