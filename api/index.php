<?php

// Trik Vercel: Paksa request jadi JSON agar Laravel tidak redirect ke 'login' route
if (isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] === '*/*') {
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
}

require __DIR__ . '/../public/index.php';