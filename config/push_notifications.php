<?php

return [
    'enabled' => env('PUSH_NOTIFICATIONS_ENABLED', env('APP_ENV') === 'local'),
];
