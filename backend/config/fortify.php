<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web', 'passwords' => 'users', 'username' => 'email', 'email' => 'email',
    'lowercase_usernames' => true, 'home' => '/', 'prefix' => 'auth', 'domain' => null,
    'middleware' => ['web', 'demo.access', 'throttle:account-entry'], 'limiters' => ['login' => 'login'], 'views' => false,
    'features' => [Features::registration(), Features::resetPasswords(), Features::emailVerification()],
];
