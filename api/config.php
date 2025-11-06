<?php
/**
 * การตั้งค่าฐานข้อมูลสำหรับระบบจัดการตารางสอน
 */

//การตั้งค่า Error Reporting สำหรับ Debug
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
ini_set('display_errors', 1);
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

//การตั้งค่าฐานข้อมูล
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

//การตั้งค่า Calendarific API
if (!defined('CALENDARIFIC_API_KEY')) {
    define('CALENDARIFIC_API_KEY', 'I793hRTnfucCdWP5OOgKWgNCDfT0wdCH');
    define('CALENDARIFIC_BASE_URL', 'https://calendarific.com/api/v2/holidays');
}

//การตั้งค่าระบบ
date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding('UTF-8');

// ตั้งค่า error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

//ฟังก์ชันการเชื่อมต่อฐานข้อมูล

if (!function_exists('connectDB')) {
    function connectDB() {
        $servername = DB_HOST;
        $username = DB_USERNAME;
        $password = DB_PASSWORD;
        $dbname = DB_NAME;
        
        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            return $conn;
        } catch(PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                "status" => "error",
                "message" => "การเชื่อมต่อฐานข้อมูลล้มเหลว",
                "debug" => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
        }
    }
}

if (!function_exists('connectMySQLi')) {
    function connectMySQLi() {
        $servername = DB_HOST;
        $username = DB_USERNAME;
        $password = DB_PASSWORD;
        $dbname = DB_NAME;
        
        try {
            $conn = new mysqli($servername, $username, $password, $dbname);
            
            if ($conn->connect_error) {
                throw new Exception('การเชื่อมต่อฐานข้อมูลล้มเหลว: ' . $conn->connect_error);
            }
            
            $conn->set_charset("utf8mb4");
            
            // ทดสอบการเชื่อมต่อ
            if (!$conn->ping()) {
                throw new Exception('การเชื่อมต่อฐานข้อมูลไม่เสถียร');
            }
            
            return $conn;
        } catch (Exception $e) {
            error_log("MySQLi connection error: " . $e->getMessage());
            throw new Exception('การเชื่อมต่อฐานข้อมูลล้มเหลว: ' . $e->getMessage());
        }
    }
}

// Alias function สำหรับความเข้ากันได้
if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        return connectDB();
    }
}

//ฟังก์ชันวันที่และเวลาภาษาไทย

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
            0 => 'อาทิตย์', 1 => 'จันทร์', 2 => 'อังคาร', 3 => 'พุธ',
            4 => 'พฤหัสบดี', 5 => 'ศุกร์', 6 => 'เสาร์'
        ];
        return isset($days[$dayNumber]) ? $days[$dayNumber] : '';
    }
}

if (!function_exists('getThaiMonth')) {
    function getThaiMonth($monthNumber) {
        $months = [
            1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
            5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
            9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
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

//ฟังก์ชัน Session Management

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
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get current user error: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('getCurrentAcademicYear')) {
    function getCurrentAcademicYear() {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1");
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get current academic year error: " . $e->getMessage());
            return null;
        }
    }
}

//ฟังก์ชัน JSON Response

if (!function_exists('jsonResponse')) {
    function jsonResponse($success, $message = '', $data = null) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('jsonError')) {
    function jsonError($message, $code = 400) {
        http_response_code($code);
        jsonResponse(false, $message);
    }
}

if (!function_exists('jsonSuccess')) {
    function jsonSuccess($message = '', $data = null) {
        jsonResponse(true, $message, $data);
    }
}

//ฟังก์ชัน cURL สำหรับการเรียก API

if (!function_exists('callAPIWithCurl')) {
    function callAPIWithCurl($url, $options = []) {
        $default_options = [
            'method' => 'GET',
            'timeout' => 30,
            'connect_timeout' => 10,
            'user_agent' => 'Teaching Schedule System/1.0',
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

//ฟังก์ชันแปลวันหยุดเป็นภาษาไทย

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
            "Second Day of Chinese New Year" => "วันที่สองของตรุษจีน",
            "Third Day of Chinese New Year" => "วันที่สามของตรุษจีน",
            "Lunar New Year" => "วันขึ้นปีใหม่จีน",
            "Spring Festival" => "เทศกาลตรุษจีน",
            "Thai New Year" => "วันปีใหม่ไทย",
            "Thai Traditional New Year" => "วันปีใหม่ไทย",
            "Songkran" => "วันสงกรานต์",
            "Songkran Festival" => "เทศกาลสงกรานต์",
            "Water Festival" => "เทศกาลสงกรานต์",

            // 🕯️ วันพระพุทธศาสนา
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

            // 👑 วันสำคัญราชวงศ์
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

            // ⚙️ วันแรงงาน
            "Labour Day" => "วันแรงงานแห่งชาติ",
            "Labor Day" => "วันแรงงานแห่งชาติ",
            "International Labour Day" => "วันแรงงานสากล",
            "International Labor Day" => "วันแรงงานสากล",
            "May Day" => "วันแรงงานแห่งชาติ",
            "Workers' Day" => "วันแรงงานแห่งชาติ",

            // ⚖️ วันรัฐธรรมนูญ / วันชาติ
            "Constitution Day" => "วันรัฐธรรมนูญ",
            "National Constitution Day" => "วันรัฐธรรมนูญแห่งชาติ",
            "National Day" => "วันชาติไทย",
            "Thailand National Day" => "วันชาติไทย",

            // 👩‍🏫 วันครู / วันเด็ก
            "National Children's Day" => "วันเด็กแห่งชาติ",
            "Children's Day" => "วันเด็กแห่งชาติ",
            "Teachers' Day" => "วันครู",
            "Teacher's Day" => "วันครู",
            "National Teachers' Day" => "วันครูแห่งชาติ",

            // 💘 วันพิเศษสากล
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

            // 🌍 วันสากล / วันอนุรักษ์
            "Earth Day" => "วันคุ้มครองโลก",
            "World Environment Day" => "วันสิ่งแวดล้อมโลก",
            "World Oceans Day" => "วันมหาสมุทรโลก",
            "International Women's Day" => "วันสตรีสากล",
            "World Health Day" => "วันอนามัยโลก",
            "World AIDS Day" => "วันเอดส์โลก",
            "Human Rights Day" => "วันสิทธิมนุษยชนสากล",
            "United Nations Day" => "วันสหประชาชาติ",
            "World Food Day" => "วันอาหารโลก",
            "World Animal Day" => "วันสัตว์โลก",
            "International Day of Peace" => "วันสันติภาพสากล",
            "World Tourism Day" => "วันท่องเที่ยวโลก",

            // 🌞 ฤดูกาล / ปรากฏการณ์ธรรมชาติ
            "March Equinox" => "วันวสันตวิษุวัต",
            "Spring Equinox" => "วันวสันตวิษุวัต",
            "June Solstice" => "วันครีษมายัน",
            "Summer Solstice" => "วันครีษมายัน",
            "September Equinox" => "วันศารทวิษุวัต",
            "Autumn Equinox" => "วันศารทวิษุวัต",
            "December Solstice" => "วันเหมายัน",
            "Winter Solstice" => "วันเหมายัน",

            // 🇹🇭 วันเฉพาะของไทย
            "Loy Krathong" => "วันลอยกระทง",
            "Loy Kratong" => "วันลอยกระทง",
            "Elephant Day" => "วันช้างไทย",
            "National Elephant Day" => "วันช้างแห่งชาติ",
            "Thai Elephant Day" => "วันช้างไทย",
            "King Naresuan Day" => "วันสมเด็จพระนเรศวรมหาราช",
            "King Naresuan the Great Day" => "วันสมเด็จพระนเรศวรมหาราช",
            "Thai Armed Forces Day" => "วันกองทัพไทย",
            "Royal Thai Armed Forces Day" => "วันกองทัพไทย",
            "Veterans Day" => "วันทหารผ่านศึก",
            "National Remembrance Day" => "วันรำลึก",
            "National Science Day" => "วันวิทยาศาสตร์แห่งชาติ",
            "National Flag Day" => "วันพระราชทานธงชาติไทย",
            "National Sports Day" => "วันกีฬาแห่งชาติ",
            "National Police Day" => "วันตำรวจ",
            "National Public Health Day" => "วันสาธารณสุขแห่งชาติ",
            "National Energy Day" => "วันพลังงานแห่งชาติ",
            "National Technology Day" => "วันเทคโนโลยีแห่งชาติ",

            // 💤 วันหยุดชดเชยและพิเศษ
            "Day off for" => "วันหยุดชดเชย",
            "Substituted Day" => "วันหยุดชดเชย",
            "Substitute Holiday" => "วันหยุดชดเชย",
            "Holiday in lieu" => "วันหยุดชดเชย",
            "Additional Holiday" => "วันหยุดเพิ่มเติม",
            "Bridge Public Holiday" => "วันหยุดชดเชย",
            "Asalha Bucha Bridge" => "วันหยุดชดเชยวันอาสาฬหบูชา",
            "Public Holiday" => "วันหยุดราชการ",
            "Anniversary of the Death of King Bhumibol" => "วันคล้ายวันสวรรคต รัชกาลที่ 9",
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

//ฟังก์ชันพิเศษ ตรวจจับรูปแบบ
if (!function_exists('translateSpecialPatterns')) {
    function translateSpecialPatterns($englishName) {
        $name = trim($englishName);
        
        // Birthday pattern
        if (preg_match("/^(.+)'s Birthday$/i", $name, $matches)) {
            $person = trim($matches[1]);
            $personTranslations = [
                'Queen Suthida' => 'สมเด็จพระนางเจ้าสุทิดา พัชรสุธาพิมลลักษณ พระบรมราชินี',
                'Queen Sirikit' => 'สมเด็จพระนางเจ้าสิริกิติ์ พระบรมราชินีนาถ พระบรมราชชนนีพันปีหลวง',
                'King Vajiralongkorn' => 'พระบาทสมเด็จพระวชิรเกล้าเจ้าอยู่หัว',
                'King Maha Vajiralongkorn' => 'พระบาทสมเด็จพระวชิรเกล้าเจ้าอยู่หัว',
                'King Bhumibol' => 'พระบาทสมเด็จพระบรมชนกาธิเบศร มหาภูมิพลอดุลยเดชมหาราช',
                'King Chulalongkorn' => 'พระบาทสมเด็จพระจุลจอมเกล้าเจ้าอยู่หัว',
            ];
            foreach ($personTranslations as $eng => $thai) {
                if (stripos($person, $eng) !== false) {
                    return "วันคล้ายวันพระราชสมภพ {$thai}";
                }
            }
            return "วันเฉลิมพระชนมพรรษา {$person}";
        }

        // Memorial Day pattern
        if (preg_match("/^(.+) Memorial Day$/i", $name, $matches)) {
            $person = trim($matches[1]);
            if (stripos($person, 'King Bhumibol') !== false) {
                return "วันคล้ายวันสวรรคต พระบาทสมเด็จพระบรมชนกาธิเบศร มหาภูมิพลอดุลยเดชมหาราช";
            }
            return "วันรำลึก {$person}";
        }

        // Bridge Holiday
        if (stripos($name, 'bridge') !== false && stripos($name, 'holiday') !== false) {
            return "วันหยุดชดเชย";
        }

        // Public Holiday
        if (stripos($name, 'public holiday') !== false) {
            return "วันหยุดราชการ";
        }

        // Anniversary of the Death
        if (preg_match("/Anniversary of the Death of King Bhumibol/i", $name)) {
            return "วันคล้ายวันสวรรคต พระบาทสมเด็จพระบรมชนกาธิเบศร มหาภูมิพลอดุลยเดชมหาราช";
        }

        return $englishName;
    }
}

//ฟังก์ชันจำแนกประเภทวันหยุด
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

//แปลประเภทวันหยุดเป็นไทย
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

//ฟังก์ชันหลักสำหรับเรียก Calendarific API

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
        
        error_log("Calendarific API URL: " . $url);
        
        $result = callAPIWithCurl($url, [
            'timeout' => 30,
            'headers' => [
                'Accept: application/json',
                'Content-Type: application/json'
            ]
        ]);
        
        if (!$result['success']) {
            error_log("Calendarific API call failed: " . $result['error']);
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
        
        error_log("Calendarific API returned " . count($formattedHolidays) . " holidays for {$country} {$year} (translated to Thai)");
        
        return $formattedHolidays;
    }
}

//ฟังก์ชันช่วยเหลือฐานข้อมูล

if (!function_exists('executeQuery')) {
    function executeQuery($sql, $params = []) {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (Exception $e) {
            error_log("Database query error: " . $e->getMessage());
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
        return getDBConnection()->lastInsertId();
    }
}

if (!function_exists('updateRecord')) {
    function updateRecord($table, $data, $where, $whereParams = []) {
        $fields = array_keys($data);
        $setClause = implode(' = ?, ', $fields) . ' = ?';
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        
        $params = array_merge(array_values($data), $whereParams);
        $stmt = executeQuery($sql, $params);
        return $stmt->rowCount();
    }
}

if (!function_exists('deleteRecord')) {
    function deleteRecord($table, $where, $whereParams = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = executeQuery($sql, $whereParams);
        return $stmt->rowCount();
    }
}

//ฟังก์ชันช่วยเหลือทั่วไป

if (!function_exists('logDebug')) {
    function logDebug($message, $data = null) {
        $logMessage = date('Y-m-d H:i:s') . ' - ' . $message;
        if ($data !== null) {
            $logMessage .= ' - Data: ' . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        error_log($logMessage);
    }
}

if (!function_exists('validateDate')) {
    function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
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

//ฟังก์ชันเพิ่มเติม

if (!function_exists('getTranslationQuality')) {
    function getTranslationQuality($originalName, $translatedName) {
        if ($originalName === $translatedName) {
            return 'no_translation';
        }
        
        if (preg_match('/[\x{0E00}-\x{0E7F}]/u', $translatedName)) {
            return 'translated';
        }
        
        return 'partial';
    }
}

if (!function_exists('getHolidayDescription')) {
    function getHolidayDescription($originalName, $translatedName, $holidayType) {
        $description = '';
        
        if ($originalName !== $translatedName) {
            $description .= "ชื่อภาษาอังกฤษ: {$originalName}";
        }
        
        switch ($holidayType) {
            case 'religious':
                $description .= ($description ? ' | ' : '') . 'วันสำคัญทางพระพุทธศาสนา';
                break;
            case 'royal':
                $description .= ($description ? ' | ' : '') . 'วันเกี่ยวกับพระมหากษัตริย์';
                break;
            case 'national':
                $description .= ($description ? ' | ' : '') . 'วันชาติ/วันแรงงาน';
                break;
            case 'substitute':
                $description .= ($description ? ' | ' : '') . 'วันหยุดชดเชย';
                break;
        }
        
        return $description;
    }
}

if (!function_exists('determineHolidayTypeFromCalendarific')) {
    function determineHolidayTypeFromCalendarific($holiday) {
        $name = mb_strtolower($holiday['name']);
        $type = isset($holiday['type'][0]) ? mb_strtolower($holiday['type'][0]) : 'national';
        
        $religious_keywords = ['bucha', 'buddhist', 'วิสาขบูชา', 'มาฆบูชา', 'อาสาฬหบูชา', 'เข้าพรรษา'];
        $royal_keywords = ['king', 'queen', 'royal', 'coronation', 'พระราชา', 'พระราชินี', 'วันเฉลิม'];
        
        foreach ($religious_keywords as $keyword) {
            if (strpos($name, $keyword) !== false || strpos($type, $keyword) !== false) {
                return 'religious';
            }
        }
        
        foreach ($royal_keywords as $keyword) {
            if (strpos($name, $keyword) !== false || strpos($type, $keyword) !== false) {
                return 'royal';
            }
        }
        
        return 'national';
    }
}

//Google Calendar Integration Helper

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

//Compatibility Aliases

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

if (!function_exists('getMySQLiConnection')) {
    function getMySQLiConnection() {
        return connectMySQLi();
    }
}

//เริ่ม Session อัตโนมัติ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// โหลด Google Calendar Integration (ถ้ามี)
if (function_exists('loadGoogleCalendarIntegration')) {
    loadGoogleCalendarIntegration();
}

// Log ว่าไฟล์ config ถูกโหลดเรียบร้อยแล้ว
error_log("Config.php loaded successfully - " . date('Y-m-d H:i:s'));

?>