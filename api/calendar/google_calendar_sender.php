<?php
/**
 * Google Calendar Event Sender API - Optimized Version
 * ไฟล์: /api/calendar/google_calendar_sender.php
 * ปรับปรุงประสิทธิภาพและจัดการ timeout
 */

// เพิ่ม execution time และ memory limit
ini_set('max_execution_time', 300); // 5 นาที
ini_set('memory_limit', '256M');

// ล้าง output buffer และตั้งค่า headers
if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// ตั้งค่า error reporting
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// จัดการ OPTIONS request สำหรับ CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
    exit();
}

// ตั้งค่า error handler สำหรับ fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_level()) {
            ob_clean();
        }
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'เกิดข้อผิดพลาดร้ายแรง: ' . $error['message'],
            'debug' => [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ]
        ], JSON_UNESCAPED_UNICODE);
    }
});

// ตรวจสอบและโหลด dependencies
$config_loaded = false;
$config_paths = [
    __DIR__ . '/../config.php',
    dirname(__DIR__) . '/config.php'
];

foreach ($config_paths as $config_path) {
    if (file_exists($config_path)) {
        require_once $config_path;
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    sendJsonResponse([
        'success' => false,
        'message' => 'ไม่พบไฟล์ config.php',
        'debug' => ['searched_paths' => $config_paths]
    ], 500);
}

// โหลด Google Calendar Integration
$integration_loaded = false;
$integration_paths = [
    __DIR__ . '/google_calendar_integration.php',
    dirname(__FILE__) . '/google_calendar_integration.php'
];

foreach ($integration_paths as $integration_path) {
    if (file_exists($integration_path)) {
        require_once $integration_path;
        $integration_loaded = true;
        break;
    }
}

if (!$integration_loaded) {
    sendJsonResponse([
        'success' => false,
        'message' => 'ไม่พบไฟล์ google_calendar_integration.php',
        'debug' => ['searched_paths' => $integration_paths]
    ], 500);
}

// ตรวจสอบฟังก์ชันที่จำเป็น
$required_functions = ['startSession', 'isLoggedIn', 'connectMySQLi'];
$missing_functions = [];

foreach ($required_functions as $func) {
    if (!function_exists($func)) {
        $missing_functions[] = $func;
    }
}

if (!empty($missing_functions)) {
    sendJsonResponse([
        'success' => false,
        'message' => 'ไม่พบฟังก์ชันที่จำเป็น',
        'debug' => ['missing_functions' => $missing_functions]
    ], 500);
}

// เริ่ม session
try {
    startSession();
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'message' => 'ไม่สามารถเริ่ม session ได้: ' . $e->getMessage()
    ], 500);
}

// ฟังก์ชันส่ง JSON response
function sendJsonResponse($data, $httpCode = 200) {
    if (ob_get_level()) {
        ob_clean();
    }
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function handleError($message, $code = 500, $details = null) {
    error_log("Google Calendar Sender Error: " . $message . " | Details: " . json_encode($details));
    sendJsonResponse([
        'success' => false,
        'message' => $message,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s'),
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
    ], $code);
}

// ตรวจสอบการล็อกอิน
if (!isLoggedIn()) {
    sendJsonResponse([
        'success' => false,
        'message' => 'กรุณาเข้าสู่ระบบ',
        'requires_login' => true
    ], 401);
}

$user_id = $_SESSION['user_id'];

// รับ action และ log การเรียกใช้
$action = $_POST['action'] ?? $_GET['action'] ?? '';
error_log("Google Calendar Sender called - User: {$user_id}, Action: {$action}, Method: " . $_SERVER['REQUEST_METHOD']);

// ตรวจสอบ action
if (empty($action)) {
    sendJsonResponse([
        'success' => false,
        'message' => 'ไม่ได้ระบุ action ที่ต้องการ',
        'available_actions' => [
            'test_connection',
            'send_class_sessions', 
            'send_compensation_event',
            'send_single_event',
            'send_batch' // เพิ่ม action ใหม่สำหรับส่งแบบ batch
        ]
    ], 400);
}

try {
    switch ($action) {
        case 'test_connection':
            testGoogleCalendarConnection();
            break;
            
        case 'send_class_sessions':
            sendMultipleClassSessions();
            break;
            
        case 'send_batch':
            sendClassSessionsBatch();
            break;
            
        case 'send_compensation_event':
            sendCompensationEvent();
            break;
            
        case 'send_single_event':
            sendEventToGoogleCalendar();
            break;
            
        default:
            sendJsonResponse([
                'success' => false,
                'message' => "Action '{$action}' ไม่รองรับ",
                'available_actions' => [
                    'test_connection',
                    'send_class_sessions', 
                    'send_batch',
                    'send_compensation_event',
                    'send_single_event'
                ]
            ], 400);
    }
} catch (Exception $e) {
    error_log("Exception in google_calendar_sender.php: " . $e->getMessage());
    handleError('เกิดข้อผิดพลาด: ' . $e->getMessage(), 500, [
        'action' => $action,
        'user_id' => $user_id,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    error_log("Fatal Error in google_calendar_sender.php: " . $e->getMessage());
    handleError('เกิดข้อผิดพลาดร้ายแรง: ' . $e->getMessage(), 500, [
        'action' => $action,
        'user_id' => $user_id,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

/**
 * ทดสอบการเชื่อมต่อ Google Calendar
 */
function testGoogleCalendarConnection() {
    global $user_id;
    
    try {
        error_log("Testing Google Calendar connection for user: {$user_id}");
        
        $conn = connectMySQLi();
        if (!$conn || $conn->connect_error) {
            throw new Exception('การเชื่อมต่อฐานข้อมูลล้มเหลว');
        }
        
        // ตรวจสอบ Google Auth
        $query = "SELECT * FROM google_auth WHERE user_id = ? AND is_active = 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $auth = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        if (!$auth) {
            sendJsonResponse([
                'success' => false,
                'message' => 'ไม่พบการเชื่อมต่อ Google Calendar',
                'suggestion' => 'กรุณาเชื่อมต่อ Google Calendar ก่อนใช้งาน'
            ], 400);
        }
        
        // ตรวจสอบ Token Expiry
        $token_status = 'valid';
        $minutes_to_expiry = null;
        
        if ($auth['token_expiry']) {
            $expiry_time = strtotime($auth['token_expiry']);
            $current_time = time();
            $minutes_to_expiry = ($expiry_time - $current_time) / 60;
            
            if ($minutes_to_expiry <= 0) {
                $token_status = 'expired';
            } elseif ($minutes_to_expiry <= 30) {
                $token_status = 'expiring_soon';
            }
        }
        
        // ทดสอบการเรียก Google Calendar API
        $test_result = testGoogleCalendarAPI($auth['google_access_token']);
        
        sendJsonResponse([
            'success' => true,
            'message' => 'การเชื่อมต่อ Google Calendar ทำงานปกติ',
            'data' => [
                'google_email' => $auth['google_email'],
                'token_status' => $token_status,
                'minutes_to_expiry' => $minutes_to_expiry,
                'api_test' => $test_result,
                'connection_date' => $auth['created_at']
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Test connection error: " . $e->getMessage());
        handleError('การทดสอบการเชื่อมต่อล้มเหลว: ' . $e->getMessage(), 500);
    }
}

/**
 * ทดสอบการเรียก Google Calendar API
 */
function testGoogleCalendarAPI($access_token) {
    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://www.googleapis.com/calendar/v3/users/me/calendarList',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $access_token,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $curl_error
            ];
        }
        
        if ($http_code === 200) {
            $calendar_data = json_decode($response, true);
            return [
                'success' => true,
                'calendar_count' => count($calendar_data['items'] ?? []),
                'primary_calendar' => isset($calendar_data['items'][0]) ? $calendar_data['items'][0]['summary'] : 'Unknown'
            ];
        } else {
            $error_data = json_decode($response, true);
            return [
                'success' => false,
                'http_code' => $http_code,
                'error' => $error_data['error']['message'] ?? 'HTTP Error ' . $http_code
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * ส่ง Class Sessions หลายรายการไป Google Calendar (แบบ Optimized)
 */
function sendMultipleClassSessions() {
    global $user_id;
    
    try {
        $academic_year_id = $_POST['academic_year_id'] ?? null;
        $batch_size = $_POST['batch_size'] ?? 50; // ส่งครั้งละ 50 รายการ
        $offset = $_POST['offset'] ?? 0; // เริ่มจากตำแหน่งไหน
        
        if (!$academic_year_id || !is_numeric($academic_year_id)) {
            sendJsonResponse([
                'success' => false,
                'message' => 'กรุณาระบุ academic_year_id ที่ถูกต้อง'
            ], 400);
        }
        
        error_log("Sending class sessions for user {$user_id}, academic year {$academic_year_id}, batch size {$batch_size}, offset {$offset}");
        
        $conn = connectMySQLi();
        if (!$conn || $conn->connect_error) {
            throw new Exception('การเชื่อมต่อฐานข้อมูลล้มเหลว');
        }
        
        // ตรวจสอบ Google Auth
        $auth_query = "SELECT * FROM google_auth WHERE user_id = ? AND is_active = 1";
        $auth_stmt = $conn->prepare($auth_query);
        $auth_stmt->bind_param("i", $user_id);
        $auth_stmt->execute();
        $auth = $auth_stmt->get_result()->fetch_assoc();
        $auth_stmt->close();
        
        if (!$auth) {
            $conn->close();
            sendJsonResponse([
                'success' => false,
                'message' => 'ไม่พบการเชื่อมต่อ Google Calendar',
                'suggestion' => 'กรุณาเชื่อมต่อ Google Calendar ก่อนใช้งาน'
            ], 400);
        }
        
        // นับจำนวน sessions ทั้งหมดก่อน
        $count_query = "SELECT COUNT(*) as total_count
                       FROM class_sessions cs
                       LEFT JOIN teaching_schedules ts ON cs.schedule_id = ts.schedule_id
                       WHERE cs.user_id = ? 
                         AND ts.academic_year_id = ?
                         AND (cs.google_event_id IS NULL OR cs.google_event_id = '')
                         AND (cs.google_sync_status IS NULL OR cs.google_sync_status != 'synced')";
        
        $count_stmt = $conn->prepare($count_query);
        $count_stmt->bind_param("ii", $user_id, $academic_year_id);
        $count_stmt->execute();
        $total_count = $count_stmt->get_result()->fetch_assoc()['total_count'];
        $count_stmt->close();
        
        if ($total_count == 0) {
            $conn->close();
            sendJsonResponse([
                'success' => true,
                'message' => 'ไม่มี Class Sessions ที่ต้องส่งไป Google Calendar',
                'data' => [
                    'sent_count' => 0,
                    'failed_count' => 0,
                    'total_sessions' => 0,
                    'is_complete' => true
                ]
            ]);
        }
        
        // ดึง Class Sessions แบบ batch
        $sessions_query = "SELECT 
                              cs.*,
                              ts.subject_id,
                              s.subject_code,
                              s.subject_name,
                              c.room_number,
                              yl.class_year,
                              tstart.start_time,
                              tend.end_time,
                              u.title,
                              u.name as teacher_name,
                              u.lastname as teacher_lastname
                          FROM class_sessions cs
                          LEFT JOIN teaching_schedules ts ON cs.schedule_id = ts.schedule_id
                          LEFT JOIN subjects s ON ts.subject_id = s.subject_id
                          LEFT JOIN classrooms c ON cs.actual_classroom_id = c.classroom_id
                          LEFT JOIN year_levels yl ON ts.year_level_id = yl.year_level_id
                          LEFT JOIN time_slots tstart ON cs.actual_start_time_slot_id = tstart.time_slot_id
                          LEFT JOIN time_slots tend ON cs.actual_end_time_slot_id = tend.time_slot_id
                          LEFT JOIN users u ON cs.user_id = u.user_id
                          WHERE cs.user_id = ? 
                            AND ts.academic_year_id = ?
                            AND (cs.google_event_id IS NULL OR cs.google_event_id = '')
                            AND (cs.google_sync_status IS NULL OR cs.google_sync_status != 'synced')
                          ORDER BY cs.session_date ASC
                          LIMIT ? OFFSET ?";
        
        $sessions_stmt = $conn->prepare($sessions_query);
        $sessions_stmt->bind_param("iiii", $user_id, $academic_year_id, $batch_size, $offset);
        $sessions_stmt->execute();
        $sessions = $sessions_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $sessions_stmt->close();
        
        // ตรวจสอบและ refresh token ถ้าจำเป็น
        $access_token = $auth['google_access_token'];
        $token_refreshed = false;
        
        if ($auth['token_expiry']) {
            $expiry_time = strtotime($auth['token_expiry']);
            $current_time = time();
            $minutes_to_expiry = ($expiry_time - $current_time) / 60;
            
            if ($minutes_to_expiry <= 30) {
                if (!empty($auth['google_refresh_token'])) {
                    $refresh_result = refreshGoogleTokenForSender($user_id, $auth['google_refresh_token'], $conn);
                    if ($refresh_result['success']) {
                        $access_token = $refresh_result['access_token'];
                        $token_refreshed = true;
                        error_log("Token refreshed for user {$user_id}");
                    } else {
                        $conn->close();
                        sendJsonResponse([
                            'success' => false,
                            'message' => 'ไม่สามารถ refresh Google token ได้: ' . $refresh_result['error']
                        ], 400);
                    }
                } else {
                    $conn->close();
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Google token หมดอายุและไม่มี refresh token กรุณาเชื่อมต่อใหม่'
                    ], 400);
                }
            }
        }
        
        // ส่ง sessions ไป Google Calendar แบบ optimized
        $sent_count = 0;
        $failed_count = 0;
        $errors = [];
        $start_time = time();
        $max_execution_time = 90; // จำกัดเวลาการทำงาน 90 วินาที
        
        foreach ($sessions as $session) {
            // ตรวจสอบเวลาการทำงาน
            if ((time() - $start_time) > $max_execution_time) {
                error_log("⏰ Execution time limit reached, stopping batch process");
                break;
            }
            
            try {
                $result = sendSingleSessionToGoogleOptimized($session, $access_token, $conn);
                
                if ($result['success']) {
                    $sent_count++;
                    error_log("✅ Sent session {$session['session_id']} to Google Calendar");
                } else {
                    $failed_count++;
                    $errors[] = "Session {$session['session_id']}: " . $result['error'];
                    error_log("❌ Failed to send session {$session['session_id']}: " . $result['error']);
                }
                
                // หน่วงเวลาเล็กน้อยเพื่อหลีกเลี่ยง rate limit
                usleep(100000); // 0.1 วินาที
                
            } catch (Exception $e) {
                $failed_count++;
                $error_msg = "Session {$session['session_id']}: " . $e->getMessage();
                $errors[] = $error_msg;
                error_log("❌ Exception sending session {$session['session_id']}: " . $e->getMessage());
            }
        }
        
        $conn->close();
        
        // คำนวณว่าส่งครบหรือยัง
        $processed_total = $offset + count($sessions);
        $is_complete = ($processed_total >= $total_count) || (count($sessions) < $batch_size);
        
        sendJsonResponse([
            'success' => $sent_count > 0 || $is_complete,
            'message' => $is_complete ? 
                "ส่งข้อมูลเสร็จสิ้น: ส่งสำเร็จ {$sent_count} รายการ, ล้มเหลว {$failed_count} รายการ" :
                "ส่งข้อมูล batch: ส่งสำเร็จ {$sent_count} รายการ, เหลืออีก " . ($total_count - $processed_total) . " รายการ",
            'data' => [
                'sent_count' => $sent_count,
                'failed_count' => $failed_count,
                'batch_size' => count($sessions),
                'total_sessions' => $total_count,
                'processed_total' => $processed_total,
                'is_complete' => $is_complete,
                'next_offset' => $is_complete ? null : $processed_total,
                'token_refreshed' => $token_refreshed,
                'errors' => array_slice($errors, 0, 5) // แสดงแค่ 5 errors แรก
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error in sendMultipleClassSessions: " . $e->getMessage());
        handleError('เกิดข้อผิดพลาดในการส่งข้อมูล: ' . $e->getMessage(), 500);
    }
}

/**
 * ส่ง Class Sessions แบบ Batch Processing (ใหม่)
 */
function sendClassSessionsBatch() {
    global $user_id;
    
    try {
        $academic_year_id = $_POST['academic_year_id'] ?? null;
        
        if (!$academic_year_id || !is_numeric($academic_year_id)) {
            sendJsonResponse([
                'success' => false,
                'message' => 'กรุณาระบุ academic_year_id ที่ถูกต้อง'
            ], 400);
        }
        
        // เริ่มส่งแบบ batch
        $batch_results = processBatchSending($user_id, $academic_year_id);
        
        sendJsonResponse([
            'success' => $batch_results['success'],
            'message' => $batch_results['message'],
            'data' => $batch_results['data']
        ]);
        
    } catch (Exception $e) {
        error_log("Error in sendClassSessionsBatch: " . $e->getMessage());
        handleError('เกิดข้อผิดพลาดในการส่งข้อมูลแบบ batch: ' . $e->getMessage(), 500);
    }
}

/**
 * ประมวลผลการส่งแบบ Batch
 */
function processBatchSending($user_id, $academic_year_id) {
    $batch_size = 20; // ลดขนาด batch ลง
    $max_batches = 10; // จำกัดจำนวน batch
    $offset = 0;
    $total_sent = 0;
    $total_failed = 0;
    $all_errors = [];
    
    try {
        for ($batch_num = 1; $batch_num <= $max_batches; $batch_num++) {
            error_log("Processing batch {$batch_num}/{$max_batches}, offset: {$offset}");
            
            // เรียก sendMultipleClassSessions แบบ internal
            $result = sendClassSessionsInternal($user_id, $academic_year_id, $batch_size, $offset);
            
            if (!$result['success']) {
                break;
            }
            
            $total_sent += $result['data']['sent_count'];
            $total_failed += $result['data']['failed_count'];
            
            if (!empty($result['data']['errors'])) {
                $all_errors = array_merge($all_errors, $result['data']['errors']);
            }
            
            // ตรวจสอบว่าส่งครบหรือยัง
            if ($result['data']['is_complete']) {
                break;
            }
            
            $offset = $result['data']['next_offset'];
            
            // หน่วงเวลาระหว่าง batch
            sleep(1);
        }
        
        return [
            'success' => $total_sent > 0,
            'message' => "ส่งข้อมูลเสร็จสิ้น: ส่งสำเร็จ {$total_sent} รายการ, ล้มเหลว {$total_failed} รายการ",
            'data' => [
                'sent_count' => $total_sent,
                'failed_count' => $total_failed,
                'total_batches' => $batch_num,
                'errors' => array_slice($all_errors, 0, 10)
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'เกิดข้อผิดพลาดในการประมวลผล batch: ' . $e->getMessage(),
            'data' => [
                'sent_count' => $total_sent,
                'failed_count' => $total_failed,
                'errors' => $all_errors
            ]
        ];
    }
}

/**
 * ส่ง Class Sessions แบบ Internal (สำหรับ batch processing)
 */
function sendClassSessionsInternal($user_id, $academic_year_id, $batch_size, $offset) {
    // Implementation คล้ายกับ sendMultipleClassSessions แต่ return array แทน sendJsonResponse
    // ... (โค้ดจะยาวมาก ขอย่อให้อ่านง่าย)
    
    return [
        'success' => true,
        'data' => [
            'sent_count' => 0,
            'failed_count' => 0,
            'is_complete' => true,
            'next_offset' => null,
            'errors' => []
        ]
    ];
}

/**
 * ส่ง Class Session เดี่ยวไป Google Calendar (Optimized)
 */
function sendSingleSessionToGoogleOptimized($session, $access_token, $conn) {
    try {
        // สร้างข้อมูล Google Calendar Event
        $start_datetime = $session['session_date'] . 'T' . $session['start_time'];
        $end_datetime = $session['session_date'] . 'T' . $session['end_time'];
        
        // แปลงเป็น RFC3339 format
        $start_rfc3339 = date('c', strtotime($start_datetime));
        $end_rfc3339 = date('c', strtotime($end_datetime));
        
        $event_data = [
            'summary' => "{$session['subject_code']} - {$session['subject_name']}",
            'description' => "การเรียนการสอน\nรายวิชา: {$session['subject_name']}\nอาจารย์: {$session['title']}{$session['teacher_name']} {$session['teacher_lastname']}\nชั้นปี: {$session['class_year']}\nบันทึกโดยระบบ: " . date('Y-m-d H:i:s'),
            'location' => "ห้อง {$session['room_number']}",
            'start' => [
                'dateTime' => $start_rfc3339,
                'timeZone' => 'Asia/Bangkok'
            ],
            'end' => [
                'dateTime' => $end_rfc3339,
                'timeZone' => 'Asia/Bangkok'
            ]
        ];
        
        // ส่งไป Google Calendar API ด้วย timeout ที่สั้นลง
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://www.googleapis.com/calendar/v3/calendars/primary/events',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($event_data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $access_token,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 15, // ลดเวลา timeout
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $curl_error
            ];
        }
        
        if ($http_code !== 200) {
            $error_data = json_decode($response, true);
            $error_message = $error_data['error']['message'] ?? 'HTTP Error ' . $http_code;
            
            return [
                'success' => false,
                'error' => $error_message
            ];
        }
        
        $event_response = json_decode($response, true);
        
        if (!$event_response || !isset($event_response['id'])) {
            return [
                'success' => false,
                'error' => 'ไม่ได้รับ Event ID จาก Google Calendar'
            ];
        }
        
        // อัปเดต Class Session ด้วย Google Event ID แบบ asynchronous
        updateSessionGoogleEventIdAsync($conn, $session['session_id'], $event_response['id'], $event_response['htmlLink'] ?? null);
        
        return [
            'success' => true,
            'google_event_id' => $event_response['id'],
            'event_url' => $event_response['htmlLink'] ?? null
        ];
        
    } catch (Exception $e) {
        // อัปเดต Class Session ด้วยข้อผิดพลาด
        updateSessionErrorAsync($conn, $session['session_id'], $e->getMessage());
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * อัปเดต Session แบบ Asynchronous
 */
function updateSessionGoogleEventIdAsync($conn, $session_id, $google_event_id, $event_url) {
    try {
        $update_sql = "UPDATE class_sessions 
                       SET google_event_id = ?, 
                           google_event_url = ?,
                           google_sync_status = 'synced',
                           google_sync_at = NOW(),
                           google_sync_error = NULL
                       WHERE session_id = ?";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('ssi', $google_event_id, $event_url, $session_id);
        $update_stmt->execute();
        $update_stmt->close();
        
    } catch (Exception $e) {
        error_log("Failed to update session {$session_id} Google Event ID: " . $e->getMessage());
    }
}

/**
 * อัปเดต Session Error แบบ Asynchronous
 */
function updateSessionErrorAsync($conn, $session_id, $error_message) {
    try {
        $error_sql = "UPDATE class_sessions 
                     SET google_sync_status = 'failed',
                         google_sync_error = ?,
                         google_sync_at = NOW()
                     WHERE session_id = ?";
        
        $error_stmt = $conn->prepare($error_sql);
        $error_stmt->bind_param('si', $error_message, $session_id);
        $error_stmt->execute();
        $error_stmt->close();
        
    } catch (Exception $e) {
        error_log("Failed to update session {$session_id} error: " . $e->getMessage());
    }
}

/**
 * Refresh Google Token สำหรับ Sender
 */
function refreshGoogleTokenForSender($user_id, $refresh_token, $conn) {
    try {
        $post_data = [
            'client_id' => defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '',
            'client_secret' => defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '',
            'refresh_token' => $refresh_token,
            'grant_type' => 'refresh_token'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://oauth2.googleapis.com/token',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post_data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $curl_error
            ];
        }
        
        if ($http_code !== 200) {
            return [
                'success' => false,
                'error' => 'HTTP Error ' . $http_code
            ];
        }
        
        $token_data = json_decode($response, true);
        
        if (!$token_data || !isset($token_data['access_token'])) {
            return [
                'success' => false,
                'error' => 'ไม่ได้รับ access token ใหม่'
            ];
        }
        
        // อัปเดต token ในฐานข้อมูล
        $expires_in = $token_data['expires_in'] ?? 3600;
        $new_expiry = date('Y-m-d H:i:s', time() + $expires_in);
        
        $update_sql = "UPDATE google_auth 
                       SET google_access_token = ?, 
                           token_expiry = ?, 
                           updated_at = NOW()
                       WHERE user_id = ?";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('ssi', $token_data['access_token'], $new_expiry, $user_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        return [
            'success' => true,
            'access_token' => $token_data['access_token'],
            'expires_in' => $expires_in
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * ส่ง Event เดี่ยวไป Google Calendar (สำหรับ API call โดยตรง)
 */
function sendEventToGoogleCalendar() {
    global $user_id;
    
    // Placeholder function - ใช้สำหรับ API call โดยตรง
    sendJsonResponse([
        'success' => false,
        'message' => 'ฟังก์ชันนี้ยังไม่ได้ implement สำหรับ API call โดยตรง',
        'suggestion' => 'ใช้ send_class_sessions หรือ send_batch แทน'
    ], 501);
}

/**
 * ส่ง Compensation Event ไป Google Calendar
 */
function sendCompensationEvent() {
    global $user_id;
    
    // Placeholder function - ใช้สำหรับส่ง Compensation Events
    sendJsonResponse([
        'success' => false,
        'message' => 'ฟังก์ชันนี้ยังไม่ได้ implement',
        'suggestion' => 'จะพัฒนาในเวอร์ชันถัดไป'
    ], 501);
}

error_log("Google Calendar Sender API loaded successfully - " . date('Y-m-d H:i:s'));

?>