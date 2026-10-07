<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
try {
    new PDO('mysql:host=127.0.0.1;port=33079', 'root', '');
    exit(0);
} catch (Throwable $e) { exit(1); }
