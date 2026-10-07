<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Run each endpoint in a separate PHP process; no database is needed for denied requests.
$root = dirname(__DIR__);
$runtime = $root . '/.test-runtime/sessions';
if (!is_dir($runtime)) mkdir($runtime, 0700, true);
$cases = [
    ['seat-layout-editor.php', 'POST', 'save_layout', false, '', 401],
    ['seat-layout-editor.php', 'POST', 'save_layout', true, 'admin', 403],
    ['seat-layout-editor.php', 'POST', 'delete_seat', true, 'viewer', 403],
    ['admin-hall-shift-api.php', 'GET', 'get_active_halls', false, '', 401],
    ['admin-hall-shift-api.php', 'POST', 'add_hall', false, '', 401],
    ['admin-hall-shift-api.php', 'GET', 'add_hall', true, 'admin', 405],
    ['admin-api.php', 'POST', 'add_employee', false, '', 401],
    ['admin-api.php', 'POST', 'add_employee', true, 'manager', 403],
    ['api.php', 'GET', 'get_registrations', false, '', 401],
    ['api.php', 'GET', 'search_registrations', false, '', 401],
    ['api.php', 'GET', 'register', false, '', 405],
    ['upload-handler.php', 'POST', 'logo', false, '', 401],
    ['upload-handler.php', 'POST', 'logo', true, 'admin', 403],
    ['admin.php', 'POST', 'update_seat_status', true, 'viewer', 403],
    ['admin-dashboard.php', 'POST', 'delete_registration', true, 'viewer', 403],
];
foreach ($cases as [$file, $method, $action, $loggedIn, $role, $expected]) {
    $sessionId = bin2hex(random_bytes(16));
    $code = 'session_save_path(' . var_export($runtime, true) . '); session_id(' . var_export($sessionId, true) . ');'
        . '$_SERVER["REQUEST_METHOD"]=' . var_export($method, true) . '; $_SERVER["REMOTE_ADDR"]="127.0.0.1";'
        . 'putenv(' . var_export('APP_LOG_PATH=' . $root . '/.test-runtime/test-errors.log', true) . ');'
        . 'require ' . var_export($root . '/app/bootstrap.php', true) . ';'
        . '$_SESSION["admin_logged_in"]=' . var_export($loggedIn, true) . '; $_SESSION["admin_role"]=' . var_export($role, true) . ';'
        . '$_GET["action"]=' . var_export($action, true) . '; $_POST["action"]=' . var_export($action, true) . ';'
        . 'register_shutdown_function(function(){echo "\nSTATUS=" . http_response_code();});'
        . 'require ' . var_export($root . '/public/' . $file, true) . ';';
    $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
    $sessionFile = $runtime . '/sess_' . $sessionId;
    if (is_file($sessionFile)) unlink($sessionFile);
    if ($exit !== 0 || !str_ends_with(trim($output), 'STATUS=' . $expected) || str_contains($output, 'Database connection') || $errors !== '') {
        throw new RuntimeException("$file ($method $action): expected $expected; got $output $errors");
    }
}
echo 'PASS: ' . count($cases) . " endpoint security checks\n";
