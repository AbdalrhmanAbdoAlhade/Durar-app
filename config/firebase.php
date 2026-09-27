<?php

return [
    // Path to the Firebase service account JSON (Project Settings > Service Accounts > Generate new private key)
    'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase/service-account.json')),
];
