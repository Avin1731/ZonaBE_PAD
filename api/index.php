<?php

// Paksa error reporting ke stderr secara manual sebelum Laravel booting
ini_set('display_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/../public/index.php';