<?php
/**
 * API สำหรับประมวลผลวันหยุดจาก Calendarific API - Updated Version
 * ไฟล์: /api/api_holiday_processor.php
 * เวอร์ชัน: 2.3 - แก้ไข HTTP 500 Error
 */

// ตั้งค่า Error Reporting และ Output Buffer
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// เริ่ม Output Buffer
if (!ob_get_level()) {
    ob_start();
}

// ตั้งค่า Headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// ตั้งค่า error handler สำหรับ fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'PHP Fatal Error: ' . $error['message'],
            'error' => 'Internal Server Error',
            'file' => basename($error['file']),
            'line' => $error['line'],
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    }
});

// ตรวจสอบและโหลด config file
$config_loaded = false;
$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config/database.php',
    __DIR__ . '/../includes/config.php',
    dirname(__DIR__) . '/config.php'
];

foreach ($config_paths as $config_path) {
    if (file_exists($config_path)) {
        try {
            require_once $config_path;
            $config_loaded = true;
            error_log("Holiday Processor - Config loaded from: " . $config_path);
            break;
        } catch (Exception $e) {
            error_log("Error loading config from {$config_path}: " . $e->getMessage());
            continue;
        }
    }
}

if (!$config_loaded) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'ไม่พบไฟล์ config หรือโหลดไม่สำเร็จ',
        'error' => 'Configuration Error',
        'searched_paths' => $config_paths,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ตรวจสอบฟังก์ชันที่จำเป็น
$required_functions = ['connectMySQLi', 'isLoggedIn', 'startSession', 'getThaiDay', 'translateHolidayToThai', 'determineDetailedHolidayType'];
$missing_functions = [];

foreach ($required_functions as $func) {
    if (!function_exists($func)) {
        $missing_functions[] = $func;
    }
}

if (!empty($missing_functions)) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'ฟังก์ชันที่จำเป็นขาดหายไป: ' . implode(', ', $missing_functions),
        'error' => 'Missing Functions',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// เริ่ม session
try {
    startSession();
} catch (Exception $e) {
    error_log("Session start error: " . $e->getMessage());
}

// ฟังก์ชันช่วยเหลือสำหรับส่ง JSON response
if (!function_exists('processorJsonSuccess')) {
    function processorJsonSuccess($message, $data = null) {
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('processorJsonError')) {
    function processorJsonError($message, $code = 400, $data = null) {
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'error' => 'API Error',
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ตรวจสอบการเข้าสู่ระบบ
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id || !isLoggedIn()) {
    processorJsonError('ไม่ได้รับอนุญาต - กรุณาล็อกอินใหม่', 401);
}

// รับ action ที่ต้องการทำ
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ล้าง buffer ก่อนประมวลผล
if (ob_get_length()) {
    ob_clean();
}

// Log การเรียกใช้ API
error_log("API Holiday Processor called - Action: {$action}, User: {$user_id}");

try {
    switch ($action) {
        case 'fetch_and_process':
            fetchHolidaysAndProcess();
            break;
        case 'generate_class_sessions':
            generateClassSessions();
            break;
        case 'get_stats':
            getProcessorStats();
            break;
        case 'test_api':
            testAPIConnection();
            break;
        default:
            processorJsonError('Action ไม่ถูกต้อง: ' . htmlspecialchars($action));
            break;
    }
} catch (Exception $e) {
    error_log('Holiday Processor API Exception: ' . $e->getMessage());
    processorJsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
}

/**
 * ดึงและประมวลผลวันหยุดจาก Calendarific API
 */
function fetchHolidaysAndProcess() {
    global $user_id;
    
    try {
        // รับพารามิเตอร์
        $academic_year_id = $_POST['academic_year_id'] ?? null;
        
        if (!$academic_year_id || !is_numeric($academic_year_id)) {
            processorJsonError('กรุณาระบุปีการศึกษาที่ถูกต้อง');
        }
        
        // ตรวจสอบการเชื่อมต่อฐานข้อมูล
        $conn = connectMySQLi();
        if (!$conn || $conn->connect_error) {
            throw new Exception('การเชื่อมต่อฐานข้อมูลล้มเหลว: ' . ($conn->connect_error ?? 'Unknown error'));
        }
        
        // ดึงข้อมูลปีการศึกษา
        $academic_sql = "SELECT * FROM academic_years WHERE academic_year_id = ?";
        $stmt = $conn->prepare($academic_sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param('i', $academic_year_id);
        $stmt->execute();
        $academic_year_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$academic_year_data) {
            throw new Exception('ไม่พบข้อมูลปีการศึกษาที่ระบุ');
        }
        
        $thai_year = (int)$academic_year_data['academic_year'];
        $christian_year = $thai_year - 543;
        
        // ตรวจสอบ API Key
        $api_key = defined('CALENDARIFIC_API_KEY') ? CALENDARIFIC_API_KEY : null;
        if (!$api_key || $api_key === 'YOUR_CALENDARIFIC_API_KEY') {
            throw new Exception('กรุณาตั้งค่า CALENDARIFIC_API_KEY ในไฟล์ config.php');
        }
        
        // เรียก Calendarific API
        $holidays = fetchHolidaysFromCalendarific($christian_year, 'TH', $api_key);
        
        if (empty($holidays)) {
            throw new Exception('ไม่สามารถดึงข้อมูลวันหยุดจาก API ได้ หรือไม่มีวันหยุดในปีที่ระบุ');
        }
        
        // ลบวันหยุดจาก API เก่าก่อนเพิ่มใหม่
        $delete_sql = "DELETE FROM public_holidays WHERE academic_year = ? AND api_source IS NOT NULL AND api_source != ''";
        $stmt = $conn->prepare($delete_sql);
        if (!$stmt) {
            throw new Exception('Prepare delete failed: ' . $conn->error);
        }
        
        $stmt->bind_param('i', $thai_year);
        $stmt->execute();
        $deleted_count = $stmt->affected_rows;
        $stmt->close();
        
        // เพิ่มวันหยุดใหม่
        $insert_sql = "INSERT INTO public_holidays 
                      (academic_year, holiday_date, holiday_name, holiday_type, api_source, api_response_data, created_by, created_at) 
                      VALUES (?, ?, ?, ?, 'calendarific', ?, ?, NOW())";
        
        $inserted_count = 0;
        $errors = [];
        
        foreach ($holidays as $holiday) {
            try {
                $api_data = json_encode($holiday, JSON_UNESCAPED_UNICODE);
                
                $stmt = $conn->prepare($insert_sql);
                if (!$stmt) {
                    throw new Exception('Prepare insert failed: ' . $conn->error);
                }
                
                $stmt->bind_param('issssi', 
                    $thai_year,
                    $holiday['date'],
                    $holiday['name'],
                    $holiday['type'],
                    $api_data,
                    $user_id
                );
                
                if ($stmt->execute()) {
                    $inserted_count++;
                } else {
                    $errors[] = "Error inserting {$holiday['name']}: " . $stmt->error;
                }
                $stmt->close();
            } catch (Exception $e) {
                $errors[] = "Error processing {$holiday['name']}: " . $e->getMessage();
            }
        }
        
        $conn->close();
        
        // สรุปผล
        $result_data = [
            'academic_year' => $thai_year,
            'christian_year' => $christian_year,
            'total_fetched' => count($holidays),
            'deleted_old' => $deleted_count,
            'total_imported' => $inserted_count,
            'errors' => $errors,
            'error_count' => count($errors)
        ];
        
        if ($inserted_count > 0) {
            $message = "ดึงข้อมูลวันหยุดสำเร็จ! นำเข้า {$inserted_count} วันหยุด";
            if (count($errors) > 0) {
                $message .= " (มีข้อผิดพลาด " . count($errors) . " รายการ)";
            }
            processorJsonSuccess($message, $result_data);
        } else {
            processorJsonError('ไม่สามารถนำเข้าวันหยุดได้', 500, $result_data);
        }
        
    } catch (Exception $e) {
        error_log('fetchHolidaysAndProcess Error: ' . $e->getMessage());
        processorJsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}
/**
 * ===== ฟังก์ชันแปลวันหยุดเป็นภาษาไทย (ย้ายมาจาก config.php) =====
 */

if (!function_exists('translateHolidayToThai')) {
    function translateHolidayToThai($english_name) {
        // ทำความสะอาดชื่อวันหยุด
        $english_name = trim($english_name);
        
        // พจนานุกรมการแปลวันหยุด - ฉบับสมบูรณ์
        $holiday_translations = [
            //วันขึ้นปีใหม่ / ตรุษจีน
            "New Year's Day" => "วันขึ้นปีใหม่",
            "New Year Day" => "วันขึ้นปีใหม่",
            "New Year" => "วันขึ้นปีใหม่",
            "New Year's Eve" => "วันสิ้นปี",
            "Chinese New Year's Day" => "วันตรุษจีน",
            "Chinese New Year" => "วันตรุษจีน",
            "Second Day of Chinese New Year" => "วันที่สองของตรุษจีน",
            "Third Day of Chinese New Year" => "วันที่สามของตรุษจีน",
            "Lunar New Year" => "วันขึ้นปีใหม่จีน",
            "Spring Festival" => "เทศกาลตรุษจีน",
            "Thai New Year" => "วันปีใหม่ไทย",
            "Thai Traditional New Year" => "วันปีใหม่ไทย",
            "Songkran" => "วันสงกรานต์",
            "Songkran Festival" => "เทศกาลสงกรานต์",
            "Water Festival" => "เทศกาลสงกรานต์",

            //วันพระพุทธศาสนา
            "Makha Bucha" => "วันมาฆบูชา",
            "Magha Puja" => "วันมาฆบูชา",
            "Makha Bucha Day" => "วันมาฆบูชา",
            "Visakha Bucha" => "วันวิสาขบูชา",
            "Vesak" => "วันวิสาขบูชา",
            "Visakha Puja" => "วันวิสาขบูชา",
            "Buddha's Birthday" => "วันวิสาขบูชา",
            "Buddha Day" => "วันวิสาขบูชา",
            "Asahna Bucha" => "วันอาสาฬหบูชา",
            "Asanha Bucha" => "วันอาสาฬหบูชา",
            "Dharma Day" => "วันอาสาฬหบูชา",
            "Buddhist Lent Day" => "วันเข้าพรรษา",
            "Buddhist Lent" => "วันเข้าพรรษา",
            "Khao Phansa" => "วันเข้าพรรษา",
            "End of Buddhist Lent" => "วันออกพรรษา",
            "Ok Phansa" => "วันออกพรรษา",
            "Kathina Day" => "วันทอดกฐิน",

            //วันสำคัญราชวงศ์
            "Chakri Day" => "วันจักรี",
            "Chakri Memorial Day" => "วันจักรี",
            "Coronation Day" => "วันฉัตรมงคล",
            "King's Birthday" => "วันเฉลิมพระชนมพรรษา",
            "Queen's Birthday" => "วันเฉลิมพระชนมพรรษา",
            "His Majesty the King's Birthday" => "วันเฉลิมพระชนมพรรษาพระบาทสมเด็จพระเจ้าอยู่หัว",
            "Her Majesty the Queen's Birthday" => "วันเฉลิมพระชนมพรรษาสมเด็จพระนางเจ้า",
            "Royal Ploughing Ceremony" => "วันพืชมงคล",
            "Royal Ploughing Day" => "วันพืชมงคล",
            "Father's Day" => "วันพ่อแห่งชาติ",
            "Mother's Day" => "วันแม่แห่งชาติ",
            "National Father's Day" => "วันพ่อแห่งชาติ",
            "National Mother's Day" => "วันแม่แห่งชาติ",
            "Chulalongkorn Day" => "วันปิยมหาราช",
            "King Chulalongkorn Memorial Day" => "วันปิยมหาราช",
            "Memorial Day of King Chulalongkorn" => "วันปิยมหาราช",
            "King Bhumibol Memorial Day" => "วันคล้ายวันสวรรคต รัชกาลที่ 9",
            "King Rama IX Memorial Day" => "วันคล้ายวันสวรรคต รัชกาลที่ 9",
            "King Vajiralongkorn's Birthday" => "วันเฉลิมพระชนมพรรษาพระบาทสมเด็จพระเจ้าอยู่หัว",
            "Queen Suthida's Birthday" => "วันเฉลิมพระชนมพรรษาสมเด็จพระนางเจ้าสุทิดา",
            "Queen Sirikit's Birthday" => "วันแม่แห่งชาติ",
            "King Maha Vajiralongkorn Birthday" => "วันเฉลิมพระชนมพรรษาพระบาทสมเด็จพระเจ้าอยู่หัว",

            //วันแรงงาน
            "Labour Day" => "วันแรงงานแห่งชาติ",
            "Labor Day" => "วันแรงงานแห่งชาติ",
            "International Labour Day" => "วันแรงงานสากล",
            "International Labor Day" => "วันแรงงานสากล",
            "May Day" => "วันแรงงานแห่งชาติ",
            "Workers' Day" => "วันแรงงานแห่งชาติ",

            //วันรัฐธรรมนูญ / วันชาติ
            "Constitution Day" => "วันรัฐธรรมนูญ",
            "National Constitution Day" => "วันรัฐธรรมนูญแห่งชาติ",
            "National Day" => "วันชาติไทย",
            "Thailand National Day" => "วันชาติไทย",

            //วันครู / วันเด็ก
            "National Children's Day" => "วันเด็กแห่งชาติ",
            "Children's Day" => "วันเด็กแห่งชาติ",
            "Teachers' Day" => "วันครู",
            "Teacher's Day" => "วันครู",
            "National Teachers' Day" => "วันครูแห่งชาติ",

            //วันพิเศษสากล
            "Valentine's Day" => "วันวาเลนไทน์",
            "Saint Valentine's Day" => "วันวาเลนไทน์",
            "All Saints' Day" => "วันนักบุญ",
            "All Souls' Day" => "วันอุทิศแด่วิญญาณผู้ล่วงลับ",
            "Christmas Day" => "วันคริสต์มาส",
            "Christmas" => "วันคริสต์มาส",
            "Good Friday" => "วันศุกร์ประเสริฐ",
            "Easter Sunday" => "วันอีสเตอร์",
            "Easter" => "วันอีสเตอร์",
            "Palm Sunday" => "วันอาทิตย์ใบลาน",
            "Holy Saturday" => "วันเสาร์ศักดิ์สิทธิ์",

            //วันหยุดชดเชยและพิเศษ
            "Day off for" => "วันหยุดชดเชย",
            "Substituted Day" => "วันหยุดชดเชย",
            "Substitute Holiday" => "วันหยุดชดเชย",
            "Holiday in lieu" => "วันหยุดชดเชย",
            "Additional Holiday" => "วันหยุดเพิ่มเติม",
            "Bridge Public Holiday" => "วันหยุดชดเชย",
            "Asalha Bucha Bridge" => "วันหยุดชดเชยวันอาสาฬหบูชา",
            "Public Holiday" => "วันหยุดราชการ",
            "Anniversary of the Death of King Bhumibol" => "วันคล้ายวันสวรรคต พระบาทสมเด็จพระบรมชนกาธิเบศร มหาภูมิพลอดุลยเดชมหาราช",
            "Anniversary of the Death of King Rama IX" => "วันคล้ายวันสวรรคต รัชกาลที่ 9",
            "Anniversary of King Bhumibol's Passing" => "วันคล้ายวันสวรรคต รัชกาลที่ 9",
            "King Bhumibol Adulyadej Memorial Day" => "วันคล้ายวันสวรรคต รัชกาลที่ 9",
        ];
        
        // การแปลตรงตัว
        if (isset($holiday_translations[$english_name])) {
            return $holiday_translations[$english_name];
        }
        
        // การจับบางส่วน
        foreach ($holiday_translations as $eng => $thai) {
            if (stripos($english_name, $eng) !== false || stripos($eng, $english_name) !== false) {
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
/**
 * ดึงวันหยุดจาก Calendarific API
 */
function fetchHolidaysFromCalendarific($year, $country = 'TH', $api_key = null) {
    // ใช้ API Key จากพารามิเตอร์หรือ config
    if (!$api_key) {
        $api_key = defined('CALENDARIFIC_API_KEY') ? CALENDARIFIC_API_KEY : null;
    }
    
    if (!$api_key) {
        throw new Exception('ไม่พบ Calendarific API Key');
    }
    
    $url = "https://calendarific.com/api/v2/holidays?" . http_build_query([
        'api_key' => $api_key,
        'country' => $country,
        'year' => $year,
        'type' => 'national,religious,observance'
    ]);
    
    error_log("Calling Calendarific API: " . $url);
    
    // ใช้ cURL แทน file_get_contents เพื่อ error handling ที่ดีกว่า
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'Teaching Schedule Management System/2.3',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        throw new Exception('cURL Error: ' . $curl_error);
    }
    
    if ($http_code !== 200) {
        throw new Exception("HTTP Error {$http_code}: การเชื่อมต่อ API ล้มเหลว");
    }
    
    if ($response === false) {
        throw new Exception('ไม่สามารถเชื่อมต่อ Calendarific API ได้');
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('ข้อมูลจาก API ไม่ถูกต้อง: ' . json_last_error_msg());
    }
    
    // ตรวจสอบ API response
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
    $formatted_holidays = [];
    
    foreach ($holidays as $holiday) {
        $date = $holiday['date']['iso'] ?? '';
        $english_name = $holiday['name'] ?? 'ไม่ระบุชื่อ';
        $original_type = isset($holiday['type'][0]) ? $holiday['type'][0] : 'National';
        
        // ใช้ฟังก์ชันแปลที่อยู่ในไฟล์นี้
        $thai_name = translateHolidayToThai($english_name);
        $detailed_type = determineDetailedHolidayType($english_name, $original_type);
        
        $formatted_holidays[] = [
            'date' => $date,
            'name' => $thai_name,
            'name_en' => $english_name,
            'name_local' => $thai_name,
            'country' => $country,
            'location' => ($country === 'TH') ? 'ประเทศไทย' : $country,
            'type' => $detailed_type,
            'type_thai' => translateHolidayType($detailed_type),
            'description' => $holiday['description'] ?? $thai_name,
            'date_year' => date('Y', strtotime($date)),
            'date_month' => date('m', strtotime($date)),
            'date_day' => date('d', strtotime($date)),
            'week_day' => date('l', strtotime($date)),
            'week_day_thai' => getThaiDay(date('w', strtotime($date)))
        ];
    }
    
    error_log("✅ Calendarific API returned " . count($formatted_holidays) . " holidays for {$country} {$year}");
    
    return $formatted_holidays;
}

/**
 * สร้าง Class Sessions อัตโนมัติตามตารางสอนและวันหยุด (ปรับปรุงแล้ว)
 * รองรับการสร้าง compensation_logs สำหรับรายวิชาที่ตรงกับวันหยุด
 * รองรับสิทธิ์ admin (สร้างทุกคน) และ teacher (สร้างตัวเอง)
 * รองรับการส่งไป Google Calendar อัตโนมัติ
 */
function generateClassSessions() {
    global $user_id;
    
    try {
        // รับพารามิเตอร์
        $academic_year_id = $_POST['academic_year_id'] ?? null;
        $date_from = $_POST['date_from'] ?? null;
        $date_to = $_POST['date_to'] ?? null;
        $send_to_google = $_POST['send_to_google'] ?? false; // เพิ่มตัวเลือกส่งไป Google Calendar
        
        if (!$academic_year_id || !is_numeric($academic_year_id)) {
            processorJsonError('กรุณาระบุปีการศึกษาที่ถูกต้อง');
        }
        
        if (!$date_from || !$date_to) {
            processorJsonError('กรุณาระบุช่วงวันที่');
        }
        
        // ตรวจสอบรูปแบบวันที่
        if (!validateDate($date_from) || !validateDate($date_to)) {
            processorJsonError('รูปแบบวันที่ไม่ถูกต้อง');
        }
        
        $conn = connectMySQLi();
        if (!$conn || $conn->connect_error) {
            throw new Exception('การเชื่อมต่อฐานข้อมูลล้มเหลว');
        }
        
        // ตรวจสอบสิทธิ์ของผู้ใช้
        $user_query = "SELECT user_type FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($user_query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$user_data) {
            throw new Exception('ไม่พบข้อมูลผู้ใช้');
        }
        
        $user_type = $user_data['user_type'];
        $is_admin = ($user_type === 'admin');
        
        $conn->begin_transaction(); // เริ่ม transaction
        
        // ดึงข้อมูลตารางสอน - ตามสิทธิ์ผู้ใช้
        if ($is_admin) {
            $schedule_sql = "SELECT 
                                ts.*,
                                s.subject_code,
                                s.subject_name,
                                c.room_number as original_room,
                                yl.class_year,
                                yl.department,
                                yl.curriculum,
                                u.title,
                                u.name as teacher_name,
                                u.lastname as teacher_lastname,
                                u.email as teacher_email
                            FROM teaching_schedules ts
                            LEFT JOIN subjects s ON ts.subject_id = s.subject_id
                            LEFT JOIN classrooms c ON ts.classroom_id = c.classroom_id
                            LEFT JOIN year_levels yl ON ts.year_level_id = yl.year_level_id
                            LEFT JOIN users u ON ts.user_id = u.user_id
                            WHERE ts.academic_year_id = ? AND ts.is_active = 1
                            ORDER BY ts.user_id, ts.day_of_week";
            $stmt = $conn->prepare($schedule_sql);
            if (!$stmt) {
                throw new Exception('Prepare schedule query failed: ' . $conn->error);
            }
            $stmt->bind_param('i', $academic_year_id);
        } else {
            $schedule_sql = "SELECT 
                                ts.*,
                                s.subject_code,
                                s.subject_name,
                                c.room_number as original_room,
                                yl.class_year,
                                yl.department,
                                yl.curriculum,
                                u.title,
                                u.name as teacher_name,
                                u.lastname as teacher_lastname,
                                u.email as teacher_email
                            FROM teaching_schedules ts
                            LEFT JOIN subjects s ON ts.subject_id = s.subject_id
                            LEFT JOIN classrooms c ON ts.classroom_id = c.classroom_id
                            LEFT JOIN year_levels yl ON ts.year_level_id = yl.year_level_id
                            LEFT JOIN users u ON ts.user_id = u.user_id
                            WHERE ts.academic_year_id = ? AND ts.user_id = ? AND ts.is_active = 1
                            ORDER BY ts.day_of_week";
            $stmt = $conn->prepare($schedule_sql);
            if (!$stmt) {
                throw new Exception('Prepare schedule query failed: ' . $conn->error);
            }
            $stmt->bind_param('ii', $academic_year_id, $user_id);
        }
        
        $stmt->execute();
        $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (empty($schedules)) {
            throw new Exception($is_admin ? 'ไม่พบตารางสอนในระบบ' : 'ไม่พบตารางสอนของคุณ');
        }
        
        // ดึงข้อมูล Google Auth ของ users ที่มีตารางสอน (ถ้าต้องการส่งไป Google Calendar)
        $google_auth_data = [];
        if ($send_to_google) {
            $unique_user_ids = array_unique(array_column($schedules, 'user_id'));
            
            if (!empty($unique_user_ids)) {
                $placeholders = str_repeat('?,', count($unique_user_ids) - 1) . '?';
                $google_auth_sql = "SELECT user_id, google_access_token, google_refresh_token, 
                                          token_expiry, google_email, google_name,
                                          TIMESTAMPDIFF(MINUTE, NOW(), token_expiry) as minutes_to_expiry
                                   FROM google_auth 
                                   WHERE user_id IN ($placeholders) AND is_active = 1";
                
                $stmt = $conn->prepare($google_auth_sql);
                $stmt->bind_param(str_repeat('i', count($unique_user_ids)), ...$unique_user_ids);
                $stmt->execute();
                $google_auths = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                // จัดเก็บข้อมูล Google Auth แยกตาม user_id
                foreach ($google_auths as $auth) {
                    $google_auth_data[$auth['user_id']] = $auth;
                }
                
                error_log("Found Google Auth for " . count($google_auth_data) . " teachers");
            }
        }
        
        // ดึงข้อมูลวันหยุดพร้อมรายละเอียด
        $holiday_sql = "SELECT 
                           holiday_date, 
                           holiday_name, 
                           holiday_type
                       FROM public_holidays 
                       WHERE holiday_date BETWEEN ? AND ? AND is_active = 1";
        $stmt = $conn->prepare($holiday_sql);
        if (!$stmt) {
            throw new Exception('Prepare holiday query failed: ' . $conn->error);
        }
        
        $stmt->bind_param('ss', $date_from, $date_to);
        $stmt->execute();
        $holidays_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // สร้าง array ของวันหยุดเพื่อค้นหาง่าย
        $holidays = [];
        foreach ($holidays_result as $holiday) {
            $holidays[$holiday['holiday_date']] = $holiday;
        }
        
        $thai_days = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];
        $generated_count = 0;
        $skipped_count = 0;
        $compensation_created = 0;
        $compensation_details = [];
        $teachers_processed = [];
        $google_calendar_results = [
            'sent_count' => 0,
            'failed_count' => 0,
            'errors' => [],
            'no_auth_users' => []
        ];
        
        // วนลูปตามวันที่
        $current_date = new DateTime($date_from);
        $end_date = new DateTime($date_to);
        
        while ($current_date <= $end_date) {
            $date_string = $current_date->format('Y-m-d');
            $day_of_week = $thai_days[$current_date->format('w')];
            
            // ตรวจสอบว่าเป็นวันหยุดหรือไม่
            $is_holiday = isset($holidays[$date_string]);
            $holiday_info = $is_holiday ? $holidays[$date_string] : null;
            
            // หาตารางสอนในวันนี้
            foreach ($schedules as $schedule) {
                if ($schedule['day_of_week'] === $day_of_week) {
                    $schedule_teacher_id = $schedule['user_id'];
                    
                    // เก็บข้อมูลอาจารย์ที่ประมวลผล
                    if (!isset($teachers_processed[$schedule_teacher_id])) {
                        $teachers_processed[$schedule_teacher_id] = [
                            'name' => $schedule['title'] . $schedule['teacher_name'] . ' ' . $schedule['teacher_lastname'],
                            'email' => $schedule['teacher_email'],
                            'schedules' => 0,
                            'sessions_created' => 0,
                            'compensations' => 0,
                            'google_calendar_sent' => 0,
                            'google_calendar_failed' => 0
                        ];
                    }
                    $teachers_processed[$schedule_teacher_id]['schedules']++;
                    
                    // ตรวจสอบว่ามี session แล้วหรือไม่
                    $check_sql = "SELECT session_id FROM class_sessions 
                                 WHERE schedule_id = ? AND session_date = ?";
                    $check_stmt = $conn->prepare($check_sql);
                    if (!$check_stmt) {
                        continue;
                    }
                    
                    $check_stmt->bind_param('is', $schedule['schedule_id'], $date_string);
                    $check_stmt->execute();
                    $existing_session = $check_stmt->get_result()->fetch_assoc();
                    $check_stmt->close();
                    
                    if ($is_holiday) {
                        // วันนี้เป็นวันหยุด - สร้าง compensation log
                        
                        // ตรวจสอบว่ามี compensation log แล้วหรือไม่
                        $check_compensation_sql = "SELECT cancellation_id FROM compensation_logs 
                                                  WHERE schedule_id = ? AND cancellation_date = ?";
                        $check_comp_stmt = $conn->prepare($check_compensation_sql);
                        if ($check_comp_stmt) {
                            $check_comp_stmt->bind_param('is', $schedule['schedule_id'], $date_string);
                            $check_comp_stmt->execute();
                            $existing_compensation = $check_comp_stmt->get_result()->fetch_assoc();
                            $check_comp_stmt->close();
                            
                            if (!$existing_compensation) {
                                // สร้าง compensation log ใหม่
                                $reason =  $holiday_info['holiday_name'];
                                
                                $insert_compensation_sql = "INSERT INTO compensation_logs 
                                    (schedule_id, cancellation_date, cancellation_type, reason, 
                                     is_makeup_required, status, user_id, created_at)
                                    VALUES (?, ?, 'วันหยุดราชการ', ?, 1, 'รอดำเนินการ', ?, NOW())";
                                
                                $comp_stmt = $conn->prepare($insert_compensation_sql);
                                if ($comp_stmt) {
                                    $comp_stmt->bind_param('issi', 
                                        $schedule['schedule_id'],
                                        $date_string,
                                        $reason,
                                        $schedule_teacher_id
                                    );
                                    
                                    if ($comp_stmt->execute()) {
                                        $compensation_created++;
                                        $teachers_processed[$schedule_teacher_id]['compensations']++;
                                        $compensation_details[] = [
                                            'date' => $date_string,
                                            'teacher_name' => $teachers_processed[$schedule_teacher_id]['name'],
                                            'subject_code' => $schedule['subject_code'],
                                            'subject_name' => $schedule['subject_name'],
                                            'holiday_name' => $holiday_info['holiday_name'],
                                            'reason' => $reason
                                        ];
                                        
                                        error_log("✅ Created compensation log for {$schedule['subject_code']} on {$date_string} (Teacher: {$teachers_processed[$schedule_teacher_id]['name']})");
                                    } else {
                                        error_log("❌ Failed to create compensation log: " . $comp_stmt->error);
                                    }
                                    $comp_stmt->close();
                                }
                            }
                        }
                        
                        // ลบ class session ที่มีอยู่ (ถ้ามี) เพราะวันนี้เป็นวันหยุด
                        if ($existing_session) {
                            $delete_sql = "DELETE FROM class_sessions WHERE session_id = ?";
                            $delete_stmt = $conn->prepare($delete_sql);
                            if ($delete_stmt) {
                                $delete_stmt->bind_param('i', $existing_session['session_id']);
                                $delete_stmt->execute();
                                $delete_stmt->close();
                                error_log("🗑️ Deleted class session on holiday: {$date_string}");
                            }
                        }
                        
                        $skipped_count++;
                        
                    } else {
                        // วันธรรมดา - สร้าง class session ปกติ
                        if (!$existing_session) {
                            $insert_sql = "INSERT INTO class_sessions 
                                          (schedule_id, session_date, actual_classroom_id, 
                                           actual_start_time_slot_id, actual_end_time_slot_id,
                                           user_id, created_at) 
                                          VALUES (?, ?, ?, ?, ?, ?, NOW())";
                            $insert_stmt = $conn->prepare($insert_sql);
                            if ($insert_stmt) {
                                $insert_stmt->bind_param('isiiii', 
                                    $schedule['schedule_id'], 
                                    $date_string,
                                    $schedule['classroom_id'],
                                    $schedule['start_time_slot_id'],
                                    $schedule['end_time_slot_id'],
                                    $schedule_teacher_id
                                );
                                
                                if ($insert_stmt->execute()) {
                                    $generated_count++;
                                    $teachers_processed[$schedule_teacher_id]['sessions_created']++;
                                    $new_session_id = $conn->insert_id;
                                    
                                    // ส่งไป Google Calendar (ถ้าเปิดใช้งาน)
                                    if ($send_to_google && isset($google_auth_data[$schedule_teacher_id])) {
                                        $google_result = sendSessionToGoogleCalendar(
                                            $schedule_teacher_id,
                                            $google_auth_data[$schedule_teacher_id],
                                            $schedule,
                                            $date_string,
                                            $new_session_id,
                                            $conn
                                        );
                                        
                                        if ($google_result['success']) {
                                            $google_calendar_results['sent_count']++;
                                            $teachers_processed[$schedule_teacher_id]['google_calendar_sent']++;
                                        } else {
                                            $google_calendar_results['failed_count']++;
                                            $teachers_processed[$schedule_teacher_id]['google_calendar_failed']++;
                                            $google_calendar_results['errors'][] = [
                                                'teacher' => $teachers_processed[$schedule_teacher_id]['name'],
                                                'subject' => $schedule['subject_code'],
                                                'date' => $date_string,
                                                'error' => $google_result['error']
                                            ];
                                        }
                                    } else if ($send_to_google && !isset($google_auth_data[$schedule_teacher_id])) {
                                        // บันทึกผู้ใช้ที่ไม่มี Google Auth
                                        if (!in_array($schedule_teacher_id, $google_calendar_results['no_auth_users'])) {
                                            $google_calendar_results['no_auth_users'][] = [
                                                'user_id' => $schedule_teacher_id,
                                                'name' => $teachers_processed[$schedule_teacher_id]['name'],
                                                'email' => $teachers_processed[$schedule_teacher_id]['email']
                                            ];
                                        }
                                    }
                                }
                                $insert_stmt->close();
                            }
                        }
                    }
                }
            }
            
            $current_date->add(new DateInterval('P1D'));
        }
        
        $conn->commit(); // Commit transaction
        $conn->close();
        
        // สร้างข้อความสรุปผลลัพธ์
        $summary_message = "สร้าง Class Sessions เสร็จสิ้น!\n\n";
        $summary_message .= "📊 สรุปผลลัพธ์:\n";
        
        if ($is_admin) {
            $summary_message .= "• สิทธิ์: ผู้ดูแลระบบ (สร้างทุกคน)\n";
            $summary_message .= "• อาจารย์ที่ประมวลผล: " . count($teachers_processed) . " คน\n";
        } else {
            $summary_message .= "• สิทธิ์: อาจารย์ (สร้างตัวเอง)\n";
        }
        
        $summary_message .= "• สร้าง Class Sessions ปกติ: {$generated_count} รายการ\n";
        $summary_message .= "• ข้ามวันหยุด: {$skipped_count} วัน\n";
        $summary_message .= "• สร้าง Compensation Logs: {$compensation_created} รายการ\n";
        $summary_message .= "• ตารางสอนทั้งหมด: " . count($schedules) . " รายการ\n";
        
        // เพิ่มข้อมูล Google Calendar
        if ($send_to_google) {
            $summary_message .= "\n📅 Google Calendar Integration:\n";
            $summary_message .= "• ส่งไป Google Calendar สำเร็จ: {$google_calendar_results['sent_count']} รายการ\n";
            $summary_message .= "• ส่งไป Google Calendar ล้มเหลว: {$google_calendar_results['failed_count']} รายการ\n";
            $summary_message .= "• อาจารย์ที่ไม่มี Google Auth: " . count($google_calendar_results['no_auth_users']) . " คน\n";
            
            if (!empty($google_calendar_results['no_auth_users'])) {
                $summary_message .= "\n👤 อาจารย์ที่ต้องเชื่อมต่อ Google Calendar:\n";
                foreach ($google_calendar_results['no_auth_users'] as $no_auth) {
                    $summary_message .= "• {$no_auth['name']} ({$no_auth['email']})\n";
                }
            }
            
            if (!empty($google_calendar_results['errors'])) {
                $summary_message .= "\n❌ ข้อผิดพลาด Google Calendar:\n";
                foreach (array_slice($google_calendar_results['errors'], 0, 5) as $error) {
                    $summary_message .= "• {$error['teacher']} - {$error['subject']} ({$error['date']}): {$error['error']}\n";
                }
                if (count($google_calendar_results['errors']) > 5) {
                    $summary_message .= "• และอีก " . (count($google_calendar_results['errors']) - 5) . " ข้อผิดพลาด...\n";
                }
            }
        }
        
        if ($is_admin && count($teachers_processed) > 0) {
            $summary_message .= "\n👥 รายละเอียดตามอาจารย์:\n";
            foreach ($teachers_processed as $teacher_id => $info) {
                $google_info = '';
                if ($send_to_google) {
                    $google_info = " (📅 Google: {$info['google_calendar_sent']} ส่งสำเร็จ, {$info['google_calendar_failed']} ล้มเหลว)";
                }
                $summary_message .= "• {$info['name']}: {$info['sessions_created']} sessions, {$info['compensations']} compensations{$google_info}\n";
            }
        }
        
        if ($compensation_created > 0) {
            $summary_message .= "\n🔄 รายการที่ต้องชดเชย:\n";
            foreach ($compensation_details as $comp) {
                if ($is_admin) {
                    $summary_message .= "• {$comp['teacher_name']} - {$comp['subject_code']} วันที่ " . 
                        date('d/m/Y', strtotime($comp['date'])) . 
                        " ({$comp['holiday_name']})\n";
                } else {
                    $summary_message .= "• {$comp['subject_code']} วันที่ " . 
                        date('d/m/Y', strtotime($comp['date'])) . 
                        " ({$comp['holiday_name']})\n";
                }
            }
            $summary_message .= "\n💡 กรุณาไปที่หน้า 'จัดการการชดเชย' เพื่อกำหนดวันที่ชดเชย";
        }
        
        processorJsonSuccess($summary_message, [
            'generated_count' => $generated_count,
            'skipped_holidays' => $skipped_count,
            'compensation_created' => $compensation_created,
            'compensation_details' => $compensation_details,
            'total_schedules' => count($schedules),
            'teachers_processed' => $teachers_processed,
            'is_admin' => $is_admin,
            'user_type' => $user_type,
            'google_calendar_enabled' => $send_to_google,
            'google_calendar_results' => $google_calendar_results,
            'date_range' => [
                'from' => $date_from,
                'to' => $date_to
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($conn)) {
            $conn->rollback();
        }
        
        error_log('generateClassSessions Error: ' . $e->getMessage());
        processorJsonError('เกิดข้อผิดพลาดในการสร้าง Class Sessions: ' . $e->getMessage());
    }
}

/**
 * ส่ง Class Session ไป Google Calendar
 */
function sendSessionToGoogleCalendar($teacher_id, $google_auth, $schedule, $session_date, $session_id, $conn) {
    try {
        // ตรวจสอบ Token expiry และ refresh ถ้าจำเป็น
        $minutes_to_expiry = $google_auth['minutes_to_expiry'];
        $access_token = $google_auth['google_access_token'];
        
        if ($minutes_to_expiry !== null && $minutes_to_expiry <= 30) {
            // Token จะหมดอายุใน 30 นาทีหรือหมดอายุแล้ว
            if (!empty($google_auth['google_refresh_token'])) {
                $refresh_result = refreshGoogleTokenForUser($teacher_id, $google_auth['google_refresh_token'], $conn);
                if ($refresh_result['success']) {
                    $access_token = $refresh_result['access_token'];
                    error_log("✅ Refreshed token for teacher {$teacher_id}");
                } else {
                    return [
                        'success' => false,
                        'error' => 'ไม่สามารถ refresh Google token ได้: ' . $refresh_result['error']
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'error' => 'Google token หมดอายุและไม่มี refresh token'
                ];
            }
        }
        
        // ดึงข้อมูล time slots
        $time_query = "SELECT start_time, end_time FROM time_slots WHERE time_slot_id = ?";
        
        $start_stmt = $conn->prepare($time_query);
        $start_stmt->bind_param('i', $schedule['start_time_slot_id']);
        $start_stmt->execute();
        $start_time_result = $start_stmt->get_result()->fetch_assoc();
        $start_stmt->close();
        
        $end_stmt = $conn->prepare($time_query);
        $end_stmt->bind_param('i', $schedule['end_time_slot_id']);
        $end_stmt->execute();
        $end_time_result = $end_stmt->get_result()->fetch_assoc();
        $end_stmt->close();
        
        if (!$start_time_result || !$end_time_result) {
            return [
                'success' => false,
                'error' => 'ไม่พบข้อมูลเวลาสำหรับตารางสอน'
            ];
        }
        
        // สร้างข้อมูล Google Calendar Event
        $start_datetime = $session_date . 'T' . $start_time_result['start_time'];
        $end_datetime = $session_date . 'T' . $end_time_result['end_time'];
        
        // แปลงเป็น RFC3339 format สำหรับ Google Calendar
        $start_rfc3339 = date('c', strtotime($start_datetime));
        $end_rfc3339 = date('c', strtotime($end_datetime));
        
        $event_data = [
            'summary' => "{$schedule['subject_code']} - {$schedule['subject_name']}",
            'description' => "การเรียนการสอน\nรายวิชา: {$schedule['subject_name']}\nอาจารย์: {$schedule['title']}{$schedule['teacher_name']} {$schedule['teacher_lastname']}\nชั้นปี: {$schedule['class_year']}\nบันทึกโดยระบบ: " . date('Y-m-d H:i:s'),
            'location' => "ห้อง {$schedule['original_room']}",
            'start' => [
                'dateTime' => $start_rfc3339,
                'timeZone' => 'Asia/Bangkok'
            ],
            'end' => [
                'dateTime' => $end_rfc3339,
                'timeZone' => 'Asia/Bangkok'
            ]
        ];
        
        // ส่งไป Google Calendar API
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
            CURLOPT_TIMEOUT => 30,
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
        
        // อัปเดต Class Session ด้วย Google Event ID
        $update_sql = "UPDATE class_sessions 
                       SET google_event_id = ?, 
                           google_event_url = ?,
                           google_sync_status = 'synced',
                           google_sync_at = NOW(),
                           google_sync_error = NULL
                       WHERE session_id = ?";
        
        $update_stmt = $conn->prepare($update_sql);
        $event_url = $event_response['htmlLink'] ?? null;
        $update_stmt->bind_param('ssi', $event_response['id'], $event_url, $session_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        error_log("✅ Sent to Google Calendar: {$schedule['subject_code']} on {$session_date} for teacher {$teacher_id}");
        
        return [
            'success' => true,
            'google_event_id' => $event_response['id'],
            'event_url' => $event_url
        ];
        
    } catch (Exception $e) {
        error_log("❌ Error sending to Google Calendar for teacher {$teacher_id}: " . $e->getMessage());
        
        // อัปเดต Class Session ด้วยข้อผิดพลาด
        try {
            $error_sql = "UPDATE class_sessions 
                         SET google_sync_status = 'failed',
                             google_sync_error = ?,
                             google_sync_at = NOW()
                         WHERE session_id = ?";
            
            $error_stmt = $conn->prepare($error_sql);
            $error_stmt->bind_param('si', $e->getMessage(), $session_id);
            $error_stmt->execute();
            $error_stmt->close();
        } catch (Exception $update_error) {
            error_log("Failed to update error status: " . $update_error->getMessage());
        }
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Refresh Google Token สำหรับผู้ใช้
 */
function refreshGoogleTokenForUser($user_id, $refresh_token, $conn) {
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
            CURLOPT_TIMEOUT => 30,
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
 * ดึงสถิติการประมวลผล
 */
function getProcessorStats() {
    global $user_id;
    
    try {
        $conn = connectMySQLi();
        if (!$conn || $conn->connect_error) {
            throw new Exception('การเชื่อมต่อฐานข้อมูลล้มเหลว');
        }
        
        $stats = [];
        
        // นับวันหยุดจาก API
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM public_holidays WHERE api_source IS NOT NULL AND api_source != ''");
        $stmt->execute();
        $stats['api_holidays'] = (int)$stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        // นับวันหยุดที่เพิ่มเอง
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM public_holidays WHERE api_source IS NULL OR api_source = ''");
        $stmt->execute();
        $stats['custom_holidays'] = (int)$stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        // นับ Class Sessions ที่สร้างโดย user
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM class_sessions WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stats['generated_sessions'] = (int)$stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        // วันหยุดล่าสุดที่ดึงจาก API
        $stmt = $conn->prepare("SELECT MAX(created_at) as last_fetch FROM public_holidays WHERE api_source IS NOT NULL AND api_source != ''");
        $stmt->execute();
        $last_fetch = $stmt->get_result()->fetch_assoc()['last_fetch'];
        $stats['last_api_fetch'] = $last_fetch;
        $stmt->close();
        
        $conn->close();
        
        processorJsonSuccess('ดึงสถิติการประมวลผลสำเร็จ', $stats);
        
    } catch (Exception $e) {
        error_log('getProcessorStats Error: ' . $e->getMessage());
        processorJsonError('เกิดข้อผิดพลาดในการดึงสถิติ: ' . $e->getMessage());
    }
}

/**
 * ทดสอบการเชื่อมต่อ API
 */
function testAPIConnection() {
    try {
        $test_year = date('Y');
        $api_key = defined('CALENDARIFIC_API_KEY') ? CALENDARIFIC_API_KEY : null;
        
        if (!$api_key) {
            throw new Exception('ไม่พบ API Key');
        }
        
        $url = "https://calendarific.com/api/v2/holidays?" . http_build_query([
            'api_key' => $api_key,
            'country' => 'TH',
            'year' => $test_year,
            'type' => 'national'
        ]);
        
        $start_time = microtime(true);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'Teaching Schedule Management System/2.3'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        $response_time = round((microtime(true) - $start_time) * 1000, 2);
        
        if ($curl_error) {
            throw new Exception('cURL Error: ' . $curl_error);
        }
        
        if ($http_code !== 200) {
            throw new Exception("HTTP Error {$http_code}");
        }
        
        if ($response === false) {
            throw new Exception('ไม่สามารถเชื่อมต่อ Calendarific API ได้');
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('ข้อมูลจาก API ไม่ถูกต้อง');
        }
        
        if (!isset($data['response']['holidays'])) {
            $error_detail = $data['meta']['error_detail'] ?? 'Unknown error';
            throw new Exception('API Error: ' . $error_detail);
        }
        
        processorJsonSuccess('เชื่อมต่อ Calendarific API สำเร็จ', [
            'response_time_ms' => $response_time,
            'test_year' => $test_year,
            'holidays_found' => count($data['response']['holidays']),
            'api_status' => 'online',
            'api_key_valid' => true
        ]);
        
    } catch (Exception $e) {
        error_log('testAPIConnection Error: ' . $e->getMessage());
        processorJsonError('การทดสอบ API ล้มเหลว: ' . $e->getMessage(), 500, [
            'api_status' => 'offline',
            'api_key_valid' => false
        ]);
    }
}

?>