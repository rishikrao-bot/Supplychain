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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Microsoft 365: one app registration in your company's Microsoft Entra ID gives the portal
    | Microsoft sign-in for your own staff, SharePoint (through Microsoft Graph) and Dataverse.
    | Set-up steps: MICROSOFT.md. The secret is typed into .env on the server only.
    */
    'microsoft' => [
        'tenant_id' => env('MICROSOFT_TENANT_ID'),
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),

        // "Sign in with Microsoft" on the login page, for your own staff only.
        'sign_in' => (bool) env('MICROSOFT_SIGN_IN', false),

        // SharePoint site that receives documents, e.g. yourcompany.sharepoint.com:/sites/Operations
        'sharepoint_site' => env('SHAREPOINT_SITE'),
        'sharepoint_library' => env('SHAREPOINT_LIBRARY', 'Documents'),
        'sharepoint_folder' => env('SHAREPOINT_FOLDER', 'Portal/Completion reports'),

        // Dataverse environment URL, e.g. https://yourorg.crm11.dynamics.com
        'dataverse_url' => env('DATAVERSE_URL'),
    ],

];
