<?php

// JAWABAN LANGSUNG UNTUK PREFLIGHT (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: https://zona-fe-pad.vercel.app');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN');
    header('Access-Control-Allow-Credentials: true');
    header('HTTP/1.1 200 OK');
    exit();
}

require __DIR__ . '/../public/index.php';