<?php

return [
    // Address of the React site. Used to build the link in the "reset password" e-mail:
    //   <frontend_url>/password/reset?token=...&email=...
    // Local development: http://localhost:5173   Online: https://your-site.vercel.app
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
];
