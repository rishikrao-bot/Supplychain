<?php

/*
| Branding. Set on the server in .env; nothing company-specific is kept in the code.
|   PORTAL_COMPANY="Your Company Ltd"     shown in pages, emails and reports
|   PORTAL_NAME="Your Company Portal"     page titles, authenticator app, Microsoft To Do list
|   PORTAL_LOGO=brand/logo.png            a file in public/brand/ (not in Git)
*/
return [
    'company' => env('PORTAL_COMPANY', 'Your Company'),
    'name' => env('PORTAL_NAME', 'Maintenance Portal'),
    'logo' => env('PORTAL_LOGO', 'img/logo.svg'),
];
