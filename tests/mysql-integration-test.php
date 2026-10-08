<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/repositories/MysqlBookingRepository.php';
// This suite only creates and changes its dedicated synthetic test database.
function connection(): PDO {
    return new PDO('mysql:host=127.0.0.1;port=33079;dbname=movie_night_test', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
}
if (($argv[1] ?? '') === 'worker') {
    try {
        $booking = bookingService(connection())->register(json_decode(base64_decode($argv[2]), true, 512, JSON_THROW_ON_ERROR));
        echo json_encode(['success' => true, 'id' => $booking['id']]);
    } catch (BookingValidationException $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
    exit;
}
$server = new PDO('mysql:host=127.0.0.1;port=33079', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('DROP DATABASE IF EXISTS movie_night_test');
$server->exec('CREATE DATABASE IF NOT EXISTS movie_night_test CHARACTER SET utf8mb4');
$pdo = connection();
foreach (explode(';', file_get_contents(__DIR__ . '/../database/schema.sql')) as $sql) {
    if (trim($sql) !== '') $pdo->exec($sql);
}
$pdo->exec("INSERT INTO cinema_halls (id,hall_name,max_attendees_per_booking,total_seats,is_active) VALUES (1,'Synthetic Hall',3,4,1)");
$pdo->exec("INSERT INTO shifts (id,hall_id,shift_name,shift_code,seat_prefix,seat_count,start_time,end_time,is_active) VALUES (1,1,'Synthetic Shift','TEST','A',4,'19:00:00','22:00:00',1)");
$pdo->exec("INSERT INTO employees (emp_number,full_name,is_active,shift_id) VALUES ('TEST001','Test One',1,1),('TEST002','Test Two',1,1)");
$pdo->exec("INSERT INTO event_settings (setting_key,setting_value,setting_type,is_public) VALUES ('registration_enabled','true','boolean',1),('max_attendees','3','number',1)");
foreach (['A1','A2','A3','A4'] as $i => $seat) {
    $stmt = $pdo->prepare("INSERT INTO seats (hall_id,shift_id,seat_number,row_letter,seat_position,status) VALUES (1,1,?,'A',?,'available')");
    $stmt->execute([$seat,$i+1]);
}
$passed = 0;
function verify(bool $condition, string $message): void {
    global $passed;
    if (!$condition) throw new RuntimeException($message);
    $passed++;
}
function request(string $employee, string $seat): array {
    return ['emp_number' => $employee, 'staff_name' => $employee === 'TEST001' ? 'Test One' : 'Test Two', 'attendee_count' => 1, 'selected_seats' => [$seat]];
}
function race(array $requests): array {
    global $pdo;
    // Hold the hall lock while both separate connections enter their transactions.
    $pdo->beginTransaction(); $pdo->query('SELECT id FROM cinema_halls WHERE id = 1 FOR UPDATE')->fetchAll();
    $workers = [];
    foreach ($requests as $request) {
        $proc = proc_open([PHP_BINARY, __FILE__, 'worker', base64_encode(json_encode($request))], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
        $workers[] = [$proc,$pipes];
    }
    usleep(250000); $pdo->commit();
    $results = [];
    foreach ($workers as [$proc,$pipes]) {
        $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($proc);
        if ($exit !== 0 || $error !== '') throw new RuntimeException('Worker failed: ' . $output . $error);
        $results[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
    return $results;
}
$results = race([request('TEST001','A1'),request('TEST001','A2')]);
verify(count(array_filter($results, fn($r)=>$r['success'])) === 1, 'Only one booking for simultaneous submissions by one employee');
verify((int)$pdo->query("SELECT COUNT(*) FROM registrations WHERE status='active'")->fetchColumn() === 1, 'No duplicate active registration');
$winner = current(array_filter($results, fn($r)=>$r['success']));
bookingService($pdo)->cancel($winner['id']);
verify((int)$pdo->query("SELECT COUNT(*) FROM seats WHERE status='occupied'")->fetchColumn() === 0, 'Cancellation frees actual database seats');
$results = race([request('TEST001','A3'),request('TEST002','A3')]);
verify(count(array_filter($results, fn($r)=>$r['success'])) === 1, 'Only one employee can reserve a shared seat concurrently');
$winner = current(array_filter($results, fn($r)=>$r['success']));
$winnerBooking = $pdo->query('SELECT * FROM registrations WHERE id=' . (int)$winner['id'])->fetch();
bookingService($pdo)->cancel($winner['id']);
$other = $winnerBooking['emp_number'] === 'TEST001' ? 'TEST002' : 'TEST001';
$new = bookingService($pdo)->register(request($other,'A3'));
bookingService($pdo)->cancel($winner['id']);
verify($pdo->query("SELECT status FROM seats WHERE seat_number='A3'")->fetchColumn() === 'occupied', 'Repeated cancellation cannot free a newer booking');
bookingService($pdo)->cancel($new['id']);
// Missing seat after a successful first update must roll the entire transaction back.
try {
    bookingService($pdo)->register(['emp_number'=>'TEST001','staff_name'=>'Test One','attendee_count'=>2,'selected_seats'=>['A1','Z99']]);
    throw new LogicException('Expected unavailable-seat rejection');
} catch (BookingValidationException $e) {}
verify($pdo->query("SELECT status FROM seats WHERE seat_number='A1'")->fetchColumn() === 'available', 'Partial seat writes roll back in MySQL');

function endpoint(string $file, array $post, string $method = 'POST'): array {
    $root = dirname(__DIR__); $runtime = $root . '/.test-runtime/sessions';
    if (!is_dir($runtime)) mkdir($runtime, 0700, true);
    $id = bin2hex(random_bytes(16));
    $post['admin_csrf_token'] = 'integration-token';
    $code = 'putenv("DB_HOST=127.0.0.1");putenv("DB_PORT=33079");putenv("DB_NAME=movie_night_test");'
        . 'session_save_path(' . var_export($runtime,true) . ');session_id(' . var_export($id,true) . ');'
        . '$_SERVER["REQUEST_METHOD"]=' . var_export($method,true) . ';$_SERVER["REMOTE_ADDR"]="127.0.0.1";'
        . 'putenv(' . var_export('APP_LOG_PATH=' . $root . '/.test-runtime/test-errors.log', true) . ');'
        . 'require ' . var_export($root . '/app/bootstrap.php',true) . ';'
        . '$_SESSION["admin_logged_in"]=true;$_SESSION["admin_role"]="admin";$_SESSION["admin_username"]="test-admin";'
        . '$_SESSION["admin_csrf_token"]="integration-token";$_SESSION["admin_csrf_token_time"]=time();'
        . ($method === 'GET' ? '$_GET=' : '$_POST=') . var_export($post,true) . ';require ' . var_export($root . '/public/' . $file,true) . ';';
    $proc = proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
    $output = stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);
    if (is_file($runtime . '/sess_' . $id)) unlink($runtime . '/sess_' . $id);
    if ($exit !== 0 || $error !== '') throw new RuntimeException('Endpoint failed: ' . $output . $error);
    return json_decode($output,true,512,JSON_THROW_ON_ERROR);
}
$booking = bookingService($pdo)->register(request('TEST001','A1'));
$layout = ['action'=>'save_layout','hall_id'=>1,'shift_id'=>1,'seats'=>json_encode([['row_letter'=>'A','seat_position'=>1,'seat_number'=>'A1','status'=>'available']])];
$page = endpoint('admin-api.php', ['action'=>'get_registrations', 'page'=>0], 'GET');
verify($page['success'] && $page['page'] === 1 && count($page['registrations']) === 1, 'Admin pagination binds numeric limits and clamps negative pages');
$stats = endpoint('admin-api.php', ['action'=>'get_statistics'], 'GET');
verify($stats['success'] && (int)$stats['statistics']['hall1_count'] === 1, 'Hall statistics report actual active bookings');
$search = endpoint('admin-api.php', ['action'=>'get_registrations', 'search'=>'no-match'], 'GET');
verify($search['success'] && $search['registrations'] === [], 'Admin paginated search accepts string filters with numeric limits');
$created = endpoint('admin-api.php', ['action'=>'add_employee', 'emp_number'=>'TEST003', 'full_name'=>'Test <Three>', 'shift_id'=>1]);
verify($created['success'], 'Shared employee creation accepts names as text');
$duplicate = endpoint('admin.php', ['action'=>'add_employee', 'emp_number'=>'TEST003', 'full_name'=>'Duplicate', 'shift_id'=>1]);
verify(!$duplicate['success'] && str_contains($duplicate['message'], 'already exists'), 'Both employee creation routes enforce duplicate protection');
$invalidShift = endpoint('admin-api.php', ['action'=>'add_employee', 'emp_number'=>'TEST004', 'full_name'=>'Invalid shift', 'shift_id'=>999]);
verify(!$invalidShift['success'] && str_contains($invalidShift['message'], 'active shift'), 'Shared employee creation rejects invalid shifts');
$result = endpoint('seat-layout-editor.php',$layout);
verify(!$result['success'] && str_contains($result['message'],'active bookings'), 'Layout replacement rejects active bookings');
verify($pdo->query("SELECT status FROM seats WHERE seat_number='A1'")->fetchColumn() === 'occupied', 'Rejected layout save preserves reservations');
$seatId = (int)$pdo->query("SELECT id FROM seats WHERE seat_number='A1'")->fetchColumn();
$result = endpoint('seat-layout-editor.php',['action'=>'delete_seat','seat_id'=>$seatId]);
verify(!$result['success'], 'Occupied seat cannot be individually deleted');
$result = endpoint('admin.php',['action'=>'update_seat_status','seat_id'=>$seatId,'status'=>'available']);
verify(!$result['success'] && $pdo->query("SELECT status FROM seats WHERE id=$seatId")->fetchColumn() === 'occupied', 'Legacy seat status action cannot release a booked seat');
$result = endpoint('admin-hall-shift-api.php',['action'=>'deactivate_shift','shift_id'=>1]);
verify(!$result['success'] && (int)$pdo->query('SELECT shift_id FROM registrations WHERE id=' . $booking['id'])->fetchColumn() === 1, 'Shift deactivation cannot detach active bookings');
$result = endpoint('admin-hall-shift-api.php',['action'=>'deactivate_hall','hall_id'=>1]);
verify(!$result['success'] && (int)$pdo->query('SELECT is_active FROM cinema_halls WHERE id=1')->fetchColumn() === 1, 'Hall deactivation rejects active bookings');
$result = endpoint('admin.php',['action'=>'delete_registration','reg_id'=>$booking['id']]);
verify($result['success'] && $pdo->query("SELECT status FROM seats WHERE seat_number='A1'")->fetchColumn() === 'available', 'Legacy cancellation releases seats');
$result = endpoint('seat-layout-editor.php',$layout);
verify($result['success'] && (int)$pdo->query('SELECT COUNT(*) FROM seats')->fetchColumn() === 1, 'Valid layout replacement works after cancellation');
$result = endpoint('seat-layout-editor.php',['action'=>'add_seat','hall_id'=>1,'shift_id'=>1,'row_letter'=>'A','seat_position'=>2,'status'=>'available']);
verify($result['success'], 'Admin seat addition accepts the consistent CSRF field');

// Exercise the real settings endpoint and verify failed requests never write.
foreach (['', '0', '11', '-1', '1.5', '1e1', 'abc', ['3']] as $invalid) {
    $response = endpoint('admin.php', ['action'=>'update_event_setting', 'setting_key'=>'max_attendees', 'setting_value'=>$invalid]);
    verify(!$response['success'] && $pdo->query("SELECT setting_value FROM event_settings WHERE setting_key='max_attendees'")->fetchColumn() === '3', 'Invalid attendee limit preserves the saved value');
}
foreach (['1', '10', ' 3 '] as $valid) {
    $response = endpoint('admin.php', ['action'=>'update_event_setting', 'setting_key'=>'max_attendees', 'setting_value'=>$valid]);
    verify($response['success'] && $pdo->query("SELECT setting_value FROM event_settings WHERE setting_key='max_attendees'")->fetchColumn() === trim($valid), 'Valid attendee limits include both boundaries and trim surrounding spaces');
}
$response = endpoint('admin.php', ['action'=>'update_event_setting', 'setting_key'=>'venue_name', 'setting_value'=>'  Test Cinema  ']);
verify($response['success'] && $pdo->query("SELECT setting_value FROM event_settings WHERE setting_key='movie_location'")->fetchColumn() === 'Test Cinema', 'Legacy venue alias saves the canonical location as trimmed text');
foreach (['   ', str_repeat('x', 256)] as $invalid) {
    $response = endpoint('admin.php', ['action'=>'update_event_setting', 'setting_key'=>'movie_location', 'setting_value'=>$invalid]);
    verify(!$response['success'] && $pdo->query("SELECT setting_value FROM event_settings WHERE setting_key='movie_location'")->fetchColumn() === 'Test Cinema', 'Invalid event text preserves the saved location');
}
$response = endpoint('admin.php', ['action'=>'update_event_setting', 'setting_key'=>'unknown_setting', 'setting_value'=>'value']);
verify(!$response['success'] && !$pdo->query("SELECT setting_value FROM event_settings WHERE setting_key='unknown_setting'")->fetchColumn(), 'Unknown editor settings cannot create arbitrary keys');
$response = endpoint('admin.php', ['action'=>'update_event_setting', 'setting_key'=>['max_attendees'], 'setting_value'=>'3']);
verify(!$response['success'], 'Malformed setting keys return a validation error');

echo "PASS: $passed MySQL integration checks\n";
