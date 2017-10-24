<?php

return [

    /**
     * Client ID.
     */
    'client_id' => env('GOOGLE_CLIENT_ID', null),

    /**
     * Client Secret.
     */
    'client_secret' => env('GOOGLE_CLIENT_SECRET', null),

    /**
     * Scopes.
     */
    'scopes' => [
        'https://www.googleapis.com/auth/youtube',
        'https://www.googleapis.com/auth/youtubepartner'
    ],

    /**
     * Route URI's
     */
    'routes' => [

        /**
         * Determine if the Routes should be disabled.
         * Note: We recommend this to be set to "false" immediately after authentication.
         */
        'enabled' => true,

        /**
         * The prefix for the below URI's
         */
        'prefix' => 'youtube',

        /**
         * Redirect URI
         */
        'redirect_uri' => 'callback',

        /**
         * The autentication URI
         */
        'authentication_uri' => 'login/{client}',

        /**
         * The redirect back URI
         */
        'redirect_back_uri' => '/clients',

    ]

];
