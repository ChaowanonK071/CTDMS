<?php
/**
 * การตั้งค่าฐานข้อมูลสำหรับระบบจัดการตารางสอน (ปรับปรุงใหม่)
 * ไฟล์นี้ใช้สำหรับกำหนดค่าการเชื่อมต่อฐานข้อมูลในทุกส่วนของระบบ
 * อัปเดต: ใช้ config.php เป็นแบบอย่าง พร้อมเพิ่มฟีเจอร์ครบถ้วน
 * เวอร์ชัน: 3.0 - Unified Configuration
 */

// ===== การตั้งค่า Error Reporting สำหรับ Debug =====
ini_set('display_errors', 0); // ปิดการแสดง error บนหน้าเว็บ (production)
error_reporting(E_ALL);
ini_set('log_errors', 1);

// สร้างโฟลเดอร์ logs ถ้ายังไม่มี
$log_dir = __DIR__ . '/../logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}
ini_set('error_log', $log_dir . '/php_errors.log');

// ===== การตั้งค่าฐานข้อมูล =====
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USERNAME', 'root');
    define('DB_PASSWORD', '');
    define('DB_NAME', 'teachingscheduledb');
    define('DB_CHARSET', 'utf8mb4');
    
    // Aliases for compatibility
    define('DB_USER', DB_USERNAME);
    define('DB_PASS', DB_PASSWORD);
}

// ===== การตั้งค่า Calendarific API =====
if (!defined('CALENDARIFIC_API_KEY')) {
    define('CALENDARIFIC_API_KEY', 'I793hRTnfucCdWP5OOgKWgNCDfT0wdCH');
    define('CALENDARIFIC_BASE_URL', 'https://calendarific.com/api/v2/holidays');
}

// ===== การตั้งค่าระบบ =====
date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding('UTF-8');

// ===== ตัวแปรการเชื่อมต่อฐานข้อมูล =====
$conn = null; // MySQLi connection
$pdo = null;  // PDO connection

// ===== ฟังก์ชันการเชื่อมต่อฐานข้อมูล PDO =====

if (!function_exists('connectDB')) {
    function connectDB() {
        global $pdo;
        
        if ($pdo !== null) {
            return $pdo;
        }
        
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ]);
            
            error_log("PDO Database connected successfully - " . date('Y-m-d H:i:s'));
            return $pdo;
            
        } catch(PDOException $e) {
            error_log("PDO Database connection error: " . $e->getMessage());
            throw new Exception("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
        }
    }
}

// ===== การเชื่อมต่อฐานข้อมูล MySQLi =====
if (!function_exists('connectMySQLi')) {
    function connectMySQLi() {
        global $conn;
        
        if ($conn !== null && $conn->ping()) {
            return $conn;
        }
        
        $servername = DB_HOST;
        $username = DB_USERNAME;
        $password = DB_PASSWORD;
        $dbname = DB_NAME;
        
        try {
            $conn = new mysqli($servername, $username, $password, $dbname);
            
            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }
            
            // ตั้งค่า charset
            $conn->set_charset(DB_CHARSET);
            
            // ทดสอบการเชื่อมต่อ
            if (!$conn->ping()) {
                throw new Exception('การเชื่อมต่อฐานข้อมูลไม่เสถียร');
            }
            
            return $conn;
        } catch (Exception $e) {
            error_log("MySQLi Connection Error: " . $e->getMessage());
            throw new Exception("ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . $e->getMessage());
        }
    }
}

// เชื่อมต่อฐานข้อมูลทั้งสองแบบ
try {
    $conn = connectMySQLi();
    error_log("Database connected successfully");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
}

// ===== Compatibility Functions =====

if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        return connectDB();
    }
}

if (!function_exists('getMySQLiConnection')) {
    function getMySQLiConnection() {
        return connectMySQLi();
    }
}

// Alias function สำหรับความเข้ากันได้
if (!function_exists('connectDatabase')) {
    function connectDatabase() {
        return connectDB();
    }
}

if (!function_exists('getConnection')) {
    function getConnection() {
        return getDBConnection();
    }
}

// ===== ฟังก์ชันช่วยเหลือฐานข้อมูล PDO =====

if (!function_exists('executeQuery')) {
    function executeQuery($sql, $params = []) {
        try {
            $pdo = connectDB();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (Exception $e) {
            error_log("Database query error: " . $e->getMessage() . " SQL: " . $sql);
            throw $e;
        }
    }
}

if (!function_exists('fetchOne')) {
    function fetchOne($sql, $params = []) {
        $stmt = executeQuery($sql, $params);
        return $stmt->fetch();
    }
}

if (!function_exists('fetchAll')) {
    function fetchAll($sql, $params = []) {
        $stmt = executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
}

if (!function_exists('insertRecord')) {
    function insertRecord($table, $data) {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES ({$placeholders})";
        
        $stmt = executeQuery($sql, $data);
        return connectDB()->lastInsertId();
    }
}

if (!function_exists('updateRecord')) {
    function updateRecord($table, $data, $where, $whereParams = []) {
        $fields = array_keys($data);
        $setClause = [];
        foreach ($fields as $field) {
            $setClause[] = "{$field} = :{$field}";
        }
        
        // รวม parameters
        $allParams = $data;
        if (!empty($whereParams)) {
            if (is_array($whereParams) && array_keys($whereParams) === range(0, count($whereParams) - 1)) {
                // ถ้าเป็น indexed array - แปลง ? เป็น named parameters
                $paramCounter = 0;
                $processedWhere = preg_replace_callback('/\?/', function($matches) use (&$paramCounter, $whereParams, &$allParams) {
                    $paramName = "where_param_{$paramCounter}";
                    $allParams[$paramName] = $whereParams[$paramCounter];
                    $paramCounter++;
                    return ":{$paramName}";
                }, $where);
                $where = $processedWhere;
            } else {
                // ถ้าเป็น associative array
                $allParams = array_merge($allParams, $whereParams);
            }
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $setClause) . " WHERE {$where}";
        $stmt = executeQuery($sql, $allParams);
        return $stmt->rowCount();
    }
}

if (!function_exists('deleteRecord')) {
    function deleteRecord($table, $where, $whereParams = []) {
        // จัดการ parameters เช่นเดียวกับ updateRecord
        $allParams = [];
        if (!empty($whereParams)) {
            if (is_array($whereParams) && array_keys($whereParams) === range(0, count($whereParams) - 1)) {
                // ถ้าเป็น indexed array - แปลง ? เป็น named parameters
                $paramCounter = 0;
                $processedWhere = preg_replace_callback('/\?/', function($matches) use (&$paramCounter, $whereParams, &$allParams) {
                    $paramName = "where_param_{$paramCounter}";
                    $allParams[$paramName] = $whereParams[$paramCounter];
                    $paramCounter++;
                    return ":{$paramName}";
                }, $where);
                $where = $processedWhere;
            } else {
                // ถ้าเป็น associative array
                $allParams = $whereParams;
            }
        }
        
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = executeQuery($sql, $allParams);
        return $stmt->rowCount();
    }
}

// ===== ฟังก์ชัน MySQLi แบบเดิม (สำหรับ backward compatibility) =====

if (!function_exists('executeQueryMySQLi')) {
    function executeQueryMySQLi($sql, $params = []) {
        $conn = connectMySQLi();
        
        try {
            if (empty($params)) {
                $result = $conn->query($sql);
                if (!$result) {
                    throw new Exception("Query failed: " . $conn->error);
                }
                return $result;
            } else {
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                if (!empty($params)) {
                    $types = '';
                    foreach ($params as $param) {
                        if (is_int($param)) {
                            $types .= 'i';
                        } elseif (is_float($param)) {
                            $types .= 'd';
                        } else {
                            $types .= 's';
                        }
                    }
                    $stmt->bind_param($types, ...$params);
                }
                
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                
                return $stmt->get_result();
            }
        } catch (Exception $e) {
            error_log("MySQLi Database query error: " . $e->getMessage() . " SQL: " . $sql);
            throw $e;
        }
    }
}

// ===== ฟังก์ชัน Session Management =====

if (!function_exists('startSession')) {
    function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        startSession();
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        startSession();
        if (!isLoggedIn()) return null;
        
        try {
            $sql = "SELECT * FROM users WHERE user_id = ?";
            return fetchOne($sql, [$_SESSION['user_id']]);
        } catch (Exception $e) {
            error_log("Get current user error: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('getCurrentAcademicYear')) {
    function getCurrentAcademicYear() {
        try {
            $sql = "SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1";
            return fetchOne($sql);
        } catch (Exception $e) {
            error_log("Get current academic year error: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('getUserData')) {
    function getUserData() {
        return getCurrentUser();
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin($redirectTo = 'login.php') {
        if (!isLoggedIn()) {
            header("Location: " . $redirectTo);
            exit;
        }
    }
}

// ===== ฟังก์ชันวันที่และเวลาภาษาไทย =====

if (!function_exists('getDayNumber')) {
    function getDayNumber($thaiDay) {
        $days = [
            'จ.' => 1, 'อ.' => 2, 'พ.' => 3, 'พฤ.' => 4,
            'ศ.' => 5, 'ส.' => 6, 'อา.' => 0
        ];
        return isset($days[$thaiDay]) ? $days[$thaiDay] : null;
    }
}

if (!function_exists('getThaiDay')) {
    function getThaiDay($dayNumber) {
        $days = [
            0 => 'อา.', 1 => 'จ.', 2 => 'อ.', 3 => 'พ.',
            4 => 'พฤ.', 5 => 'ศ.', 6 => 'ส.'
        ];
        return isset($days[$dayNumber]) ? $days[$dayNumber] : '';
    }
}

if (!function_exists('getThaiWeekDay')) {
    function getThaiWeekDay($dayNumber) {
        $days = [
            0 => 'อาทิตย์',
            1 => 'จันทร์',
            2 => 'อังคาร', 
            3 => 'พุธ',
            4 => 'พฤหัสบดี',
            5 => 'ศุกร์',
            6 => 'เสาร์'
        ];
        return isset($days[$dayNumber]) ? $days[$dayNumber] : '';
    }
}

if (!function_exists('getThaiMonth')) {
    function getThaiMonth($monthNumber) {
        $months = [
            1 => 'มกราคม',
            2 => 'กุมภาพันธ์',
            3 => 'มีนาคม',
            4 => 'เมษายน',
            5 => 'พฤษภาคม',
            6 => 'มิถุนายน',
            7 => 'กรกฎาคม',
            8 => 'สิงหาคม',
            9 => 'กันยายน',
            10 => 'ตุลาคม',
            11 => 'พฤศจิกายน',
            12 => 'ธันวาคม'
        ];
        return isset($months[$monthNumber]) ? $months[$monthNumber] : '';
    }
}

if (!function_exists('thaiDate')) {
    function thaiDate($date) {
        if (empty($date)) return '';
        
        $timestamp = strtotime($date);
        if (!$timestamp) return $date;
        
        return date('d/m/Y', $timestamp);
    }
}

if (!function_exists('thaiTime')) {
    function thaiTime($time) {
        if (empty($time)) return '';
        
        $timestamp = strtotime($time);
        if (!$timestamp) return $time;
        
        return date('H:i', $timestamp);
    }
}

if (!function_exists('formatFullThaiDate')) {
    function formatFullThaiDate($date) {
        if (empty($date)) return '';
        
        $timestamp = strtotime($date);
        if (!$timestamp) return $date;
        
        $day = date('j', $timestamp);
        $month = getThaiMonth((int)date('n', $timestamp));
        $year = date('Y', $timestamp) + 543;
        $weekday = getThaiWeekDay(date('w', $timestamp));
        
        return "วัน{$weekday}ที่ {$day} {$month} พ.ศ. {$year}";
    }
}

// ===== ฟังก์ชัน cURL สำหรับการเรียก API =====

if (!function_exists('callAPIWithCurl')) {
    function callAPIWithCurl($url, $options = []) {
        $default_options = [
            'method' => 'GET',
            'timeout' => 30,
            'connect_timeout' => 10,
            'user_agent' => 'Teaching Schedule System/3.0',
            'headers' => [
                'Accept: application/json',
                'Content-Type: application/json'
            ],
            'ssl_verify' => true,
            'follow_redirects' => true,
            'max_retries' => 3,
            'retry_delay' => 2
        ];
        
        $options = array_merge($default_options, $options);
        $retry_count = 0;
        
        while ($retry_count < $options['max_retries']) {
            try {
                $ch = curl_init();
                
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => $options['follow_redirects'],
                    CURLOPT_TIMEOUT => $options['timeout'],
                    CURLOPT_CONNECTTIMEOUT => $options['connect_timeout'],
                    CURLOPT_SSL_VERIFYPEER => $options['ssl_verify'],
                    CURLOPT_USERAGENT => $options['user_agent'],
                    CURLOPT_HTTPHEADER => $options['headers']
                ]);
                
                if (strtoupper($options['method']) === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                    if (isset($options['data'])) {
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $options['data']);
                    }
                }
                
                $response = curl_exec($ch);
                
                if (curl_error($ch)) {
                    $error = curl_error($ch);
                    curl_close($ch);
                    throw new Exception('cURL Error: ' . $error);
                }
                
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $info = curl_getinfo($ch);
                curl_close($ch);
                
                if ($http_code >= 400) {
                    throw new Exception('HTTP Error ' . $http_code . ': ' . $response);
                }
                
                return [
                    'success' => true,
                    'data' => $response,
                    'http_code' => $http_code,
                    'info' => $info
                ];
                
            } catch (Exception $e) {
                $retry_count++;
                
                if ($retry_count >= $options['max_retries']) {
                    error_log("API call failed after {$retry_count} retries: " . $e->getMessage());
                    return [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'retry_count' => $retry_count
                    ];
                }
                
                sleep($options['retry_delay']);
            }
        }
        
        return [
            'success' => false,
            'error' => 'Maximum retries exceeded',
            'retry_count' => $retry_count
        ];
    }
}

// ===== ฟังก์ชัน JSON Response =====

if (!function_exists('jsonResponse')) {
    function jsonResponse($success, $message = '', $data = null) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('jsonError')) {
    function jsonError($message, $code = 400) {
        http_response_code($code);
        jsonResponse(false, $message, null);
        exit;
    }
}

if (!function_exists('jsonSuccess')) {
    function jsonSuccess($message = '', $data = null) {
        jsonResponse(true, $message, $data);
        exit;
    }
}

// ===== ฟังก์ชันแปลวันหยุดเป็นภาษาไทย =====

if (!function_exists('translateHolidayToThai')) {
    function translateHolidayToThai($english_name) {
        // ทำความสะอาดชื่อวันหยุด
        $english_name = trim($english_name);
        
        // พจนานุกรมการแปลวันหยุด - ฉบับสมบูรณ์
        $holiday_translations = [
            // 🎉 วันขึ้นปีใหม่ / ตรุษจีน
            "New Year's Day" => "วันขึ้นปีใหม่",
            "New Year Day" => "วันขึ้นปีใหม่",
            "New Year" => "วันขึ้นปีใหม่",
            "New Year's Eve" => "วันสิ้นปี",
            "Chinese New Year's Day" => "วันตรุษจีน",
            "Chinese New Year" => "วันตรุษจีน",
            "Lunar New Year" => "วันขึ้นปีใหม่จีน",
            "Songkran" => "วันสงกรานต์",
            "Songkran Festival" => "เทศกาลสงกรานต์",

            // 🕯️ วันพระพุทธศาสนา
            "Makha Bucha" => "วันมาฆบูชา",
            "Magha Puja" => "วันมาฆบูชา",
            "Makha Bucha Day" => "วันมาฆบูชา",
            "Visakha Bucha" => "วันวิสาขบูชา",
            "Vesak" => "วันวิสาขบูชา",
            "Buddha's Birthday" => "วันวิสาขบูชา",
            "Asahna Bucha" => "วันอาสาฬหบูชา",
            "Asanha Bucha" => "วันอาสาฬหบูชา",
            "Buddhist Lent Day" => "วันเข้าพรรษา",
            "Khao Phansa" => "วันเข้าพรรษา",

            // 👑 วันสำคัญราชวงศ์
            "Chakri Day" => "วันจักรี",
            "Coronation Day" => "วันฉัตรมงคล",
            "King's Birthday" => "วันเฉลิมพระชนมพรรษา",
            "Queen's Birthday" => "วันเฉลิมพระชนมพรรษา",
            "Royal Ploughing Ceremony" => "วันพืชมงคล",
            "Father's Day" => "วันพ่อแห่งชาติ",
            "Mother's Day" => "วันแม่แห่งชาติ",
            "Chulalongkorn Day" => "วันปิยมหาราช",

            // ⚙️ วันแรงงาน
            "Labour Day" => "วันแรงงานแห่งชาติ",
            "Labor Day" => "วันแรงงานแห่งชาติ",

            // ⚖️ วันรัฐธรรมนูญ
            "Constitution Day" => "วันรัฐธรรมนูญ"
        ];
        
        // การแปลตรงตัว
        if (isset($holiday_translations[$english_name])) {
            return $holiday_translations[$english_name];
        }
        
        // การจับบางส่วน
        foreach ($holiday_translations as $eng => $thai) {
            if (stripos($english_name, $eng) !== false) {
                return $thai;
            }
        }

        // คีย์เวิร์ดสำคัญ
        $keywords = [
            'New Year' => 'วันขึ้นปีใหม่',
            'Christmas' => 'วันคริสต์มาส',
            'Buddha' => 'วันพระพุทธเจ้า',
            'King' => 'วันพระราชา',
            'Queen' => 'วันพระราชินี',
            'Birthday' => 'วันเฉลิมพระชนมพรรษา',
            'Memorial' => 'วันรำลึก',
            'Bridge' => 'วันหยุดชดเชย',
            'Public Holiday' => 'วันหยุดราชการ',
            'Labour' => 'วันแรงงาน',
            'Constitution' => 'วันรัฐธรรมนูญ',
            'Children' => 'วันเด็ก',
            'Teacher' => 'วันครู',
            'Mother' => 'วันแม่',
            'Father' => 'วันพ่อ',
            'Valentine' => 'วันวาเลนไทน์',
            'Songkran' => 'วันสงกรานต์',
            'Chakri' => 'วันจักรี',
            'Coronation' => 'วันฉัตรมงคล',
        ];
        
        foreach ($keywords as $keyword => $translation) {
            if (stripos($english_name, $keyword) !== false) {
                return $translation;
            }
        }

        return $english_name;
    }
}

// ===== ฟังก์ชันจำแนกประเภทวันหยุด =====
if (!function_exists('determineDetailedHolidayType')) {
    function determineDetailedHolidayType($english_name, $original_type = '') {
        $name_lower = strtolower($english_name);
        $type_patterns = [
            'national' => ['new year','songkran','labour','labor','constitution','national','republic'],
            'religious' => ['buddha','makha','visakha','asahna','vesak','dharma','lent','christmas','easter'],
            'royal' => ['king','queen','chakri','coronation','birthday','chulalongkorn','father\'s day','mother\'s day','royal'],
            'observance' => ['children','teacher','women','environment','health','aids','elephant','veterans','remembrance'],
            'seasonal' => ['equinox','solstice','spring','summer','autumn','winter']
        ];
        foreach ($type_patterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($name_lower, $pattern) !== false) return $type;
            }
        }
        return $original_type ?: 'observance';
    }
}

// ===== แปลประเภทวันหยุดเป็นไทย =====
if (!function_exists('translateHolidayType')) {
    function translateHolidayType($type) {
        $typeTranslations = [
            'national' => 'วันหยุดราชการ',
            'religious' => 'วันสำคัญทางศาสนา',
            'royal' => 'วันเกี่ยวกับพระมหากษัตริย์',
            'substitute' => 'วันหยุดชดเชย',
            'observance' => 'วันสำคัญ',
            'season' => 'วันตามฤดูกาล',
            'other' => 'อื่นๆ'
        ];
        return $typeTranslations[strtolower($type)] ?? $type;
    }
}

// ===== ฟังก์ชันหลักสำหรับเรียก Calendarific API =====

if (!function_exists('callCalendarificAPI')) {
    function callCalendarificAPI($country = 'TH', $year = null) {
        $api_key = CALENDARIFIC_API_KEY;
        $base_url = CALENDARIFIC_BASE_URL;
        
        if (empty($api_key) || $api_key === 'YOUR_CALENDARIFIC_API_KEY') {
            throw new Exception('กรุณาตั้งค่า CALENDARIFIC_API_KEY ในไฟล์ config.php (สมัครฟรีที่ calendarific.com)');
        }
        
        if ($year === null) {
            $year = date('Y');
        }
        
        $params = [
            'api_key' => $api_key,
            'country' => $country,
            'year' => $year,
            'type' => 'national'
        ];
        
        $url = $base_url . '?' . http_build_query($params);
        
        error_log("🌐 Calendarific API URL: " . $url);
        
        $result = callAPIWithCurl($url, [
            'timeout' => 30,
            'headers' => [
                'Accept: application/json',
                'Content-Type: application/json'
            ]
        ]);
        
        if (!$result['success']) {
            error_log("❌ Calendarific API call failed: " . $result['error']);
            throw new Exception('API call failed: ' . $result['error']);
        }
        
        $data = json_decode($result['data'], true);
        
        if (isset($data['meta']['code']) && $data['meta']['code'] !== 200) {
            $error_code = $data['meta']['code'];
            $error_detail = $data['meta']['error_detail'] ?? 'ไม่ทราบรายละเอียด';
            
            $error_messages = [
                401 => 'API Key ไม่ถูกต้อง',
                402 => 'เกินจำนวนการเรียก API ที่อนุญาต',
                403 => 'การเข้าถึงถูกปฏิเสธ',
                404 => 'ไม่พบข้อมูลสำหรับประเทศ/ปีที่ระบุ',
                429 => 'เรียก API บ่อยเกินไป',
                500 => 'เซิร์ฟเวอร์ API มีปัญหา'
            ];
            
            $friendly_message = $error_messages[$error_code] ?? "API Error Code {$error_code}";
            throw new Exception("API Error: {$friendly_message} - {$error_detail}");
        }
        
        if (!isset($data['response']['holidays'])) {
            throw new Exception('No holidays data received from API');
        }
        
        $holidays = $data['response']['holidays'];
        $formattedHolidays = [];
        
        foreach ($holidays as $holiday) {
            $date = $holiday['date']['iso'] ?? '';
            $englishName = $holiday['name'] ?? 'ไม่ระบุชื่อ';
            $originalType = isset($holiday['type'][0]) ? $holiday['type'][0] : 'National';
            
            $thaiName = translateHolidayToThai($englishName);
            $detailedType = determineDetailedHolidayType($englishName, $originalType);
            
            $formattedHolidays[] = [
                'date' => $date,
                'name' => $thaiName,
                'name_en' => $englishName,
                'name_local' => $thaiName,
                'country' => $country,
                'location' => ($country === 'TH') ? 'ประเทศไทย' : $country,
                'type' => $detailedType,
                'type_thai' => translateHolidayType($detailedType),
                'description' => $holiday['description'] ?? $thaiName,
                'date_year' => date('Y', strtotime($date)),
                'date_month' => date('m', strtotime($date)),
                'date_day' => date('d', strtotime($date)),
                'week_day' => date('l', strtotime($date)),
                'week_day_thai' => getThaiDay(date('w', strtotime($date)))
            ];
        }
        
        error_log("✅ Calendarific API returned " . count($formattedHolidays) . " holidays for {$country} {$year} (translated to Thai)");
        
        return $formattedHolidays;
    }
}

// ===== ฟังก์ชันช่วยเหลือทั่วไป =====

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map('sanitizeInput', $input);
        }
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('validateDate')) {
    function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}

if (!function_exists('validateRequired')) {
    function validateRequired($fields, $data) {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $missing[] = $field;
            }
        }
        return $missing;
    }
}

if (!function_exists('logDebug')) {
    function logDebug($message, $data = null) {
        $logMessage = date('Y-m-d H:i:s') . ' - ' . $message;
        if ($data !== null) {
            $logMessage .= ' - Data: ' . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        error_log($logMessage);
    }
}

// ===== การโหลดไฟล์เพิ่มเติม =====

if (!function_exists('loadCalendarificFunctions')) {
    function loadCalendarificFunctions() {
        $calendarificFile = __DIR__ . '/calendarific_functions.php';
        if (file_exists($calendarificFile)) {
            require_once $calendarificFile;
            return true;
        }
        return false;
    }
}

if (!function_exists('loadGoogleCalendarIntegration')) {
    function loadGoogleCalendarIntegration() {
        $integrationFile = __DIR__ . '/google_calendar_integration.php';
        if (file_exists($integrationFile)) {
            require_once $integrationFile;
            return true;
        } else {
            error_log("Google Calendar Integration file not found: " . $integrationFile);
            return false;
        }
    }
}

// ===== เริ่ม Session อัตโนมัติ =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// โหลดไฟล์เพิ่มเติม (ถ้ามี)
loadCalendarificFunctions();
loadGoogleCalendarIntegration();

// Log ว่าไฟล์ config ถูกโหลดเรียบร้อยแล้ว
error_log("Database config loaded successfully (v3.0 - Unified) - " . date('Y-m-d H:i:s'));

?>