<?php
/**
 * Fixed Google Calendar Token Refresh Implementation - Final Version
 * ไฟล์: /api/calendar/token_refresh.php
 * แก้ไขให้ refresh token ทำงานจริงๆ และเพิ่ม validate action
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// ป้องกันการเรียกไฟล์โดยตรง
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USERNAME', 'root');
    define('DB_PASSWORD', '');
    define('DB_NAME', 'teachingscheduledb');
}

// Google OAuth Configuration
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', '545614412274-1dpi138qggqtboor377ein8g2h43k7ra.apps.googleusercontent.com');
    define('GOOGLE_CLIENT_SECRET', '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');
    define('GOOGLE_REDIRECT_URI', 'http://localhost/api/calendar/google_calendar_oauth.php');
}

// Set headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
    exit();
}

function sendResponse($data, $httpCode = 200) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function handleError($message, $code = 500, $details = null) {
    error_log("Token Refresh API Error: " . $message . " | Details: " . json_encode($details));
    sendResponse([
        'status' => 'error',
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
    ], $code);
}

function getSecureDbConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
        if ($conn->connect_error) {
            throw new Exception('Database connection failed: ' . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        handleError('Database connection error', 500, $e->getMessage());
    }
}

function checkSessionSafely() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        sendResponse([
            'status' => 'error',
            'message' => 'กรุณาเข้าสู่ระบบ',
            'requires_login' => true
        ], 401);
    }
    
    return (int)$_SESSION['user_id'];
}

/**
 * ตรวจสอบสถานะ Token with enhanced details
 */
function getTokenExpiryStatusAdvanced($user_id) {
    try {
        $conn = getSecureDbConnection();
        
        $query = "
            SELECT 
                token_expiry,
                TIMESTAMPDIFF(MINUTE, NOW(), token_expiry) as minutes_to_expiry,
                google_refresh_token IS NOT NULL as has_refresh_token,
                google_email,
                google_name,
                created_at,
                updated_at,
                last_checked
            FROM google_auth 
            WHERE user_id = ? AND is_active = 1
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Prepare statement failed: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        if (!$result) {
            return [
                'status' => 'no_auth',
                'message' => 'ไม่พบข้อมูล Google Authentication',
                'has_google_auth' => false,
                'action_required' => 'connect'
            ];
        }
        
        $minutesToExpiry = $result['minutes_to_expiry'];
        $hasRefreshToken = (bool)$result['has_refresh_token'];
        
        $baseInfo = [
            'google_email' => $result['google_email'],
            'google_name' => $result['google_name'],
            'connected_at' => $result['created_at'],
            'last_updated' => $result['updated_at'],
            'last_checked' => $result['last_checked'],
            'has_refresh_token' => $hasRefreshToken,
            'has_google_auth' => true
        ];
        
        if ($minutesToExpiry === null) {
            return array_merge($baseInfo, [
                'status' => 'no_expiry',
                'message' => 'Token ไม่มีวันหมดอายุ',
                'can_refresh' => $hasRefreshToken,
                'action_required' => 'none'
            ]);
        }
        
        if ($minutesToExpiry <= 0) {
            if ($hasRefreshToken) {
                return array_merge($baseInfo, [
                    'status' => 'expired_can_refresh',
                    'message' => 'Token หมดอายุแล้ว - สามารถ Refresh ได้',
                    'expired_minutes_ago' => abs($minutesToExpiry),
                    'can_refresh' => true,
                    'needs_refresh' => true,
                    'action_required' => 'refresh'
                ]);
            } else {
                return array_merge($baseInfo, [
                    'status' => 'expired_need_reconnect',
                    'message' => 'Token หมดอายุแล้ว - ต้องเชื่อมต่อใหม่',
                    'expired_minutes_ago' => abs($minutesToExpiry),
                    'can_refresh' => false,
                    'action_required' => 'reconnect'
                ]);
            }
        }
        
        if ($minutesToExpiry <= 120) { // หมดอายุภายใน 2 ชั่วโมง
            return array_merge($baseInfo, [
                'status' => 'expiring_soon',
                'message' => 'Token จะหมดอายุเร็วๆ นี้ - แนะนำให้ Refresh',
                'minutes_to_expiry' => $minutesToExpiry,
                'can_refresh' => $hasRefreshToken,
                'should_refresh' => true,
                'action_required' => 'refresh_recommended'
            ]);
        }
        
        return array_merge($baseInfo, [
            'status' => 'valid',
            'message' => 'Token ยังใช้งานได้',
            'minutes_to_expiry' => $minutesToExpiry,
            'hours_to_expiry' => round($minutesToExpiry / 60, 1),
            'can_refresh' => $hasRefreshToken,
            'action_required' => 'none'
        ]);
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาดในการตรวจสอบสถานะ Token: ' . $e->getMessage(),
            'action_required' => 'check_error',
            'has_google_auth' => false
        ];
    }
}

/**
 * ฟังก์ชัน Refresh Google Access Token ที่ทำงานจริง (Final Version)
 */
function performActualTokenRefresh($user_id, $forceRefresh = false) {
    try {
        error_log("=== Starting ACTUAL token refresh for user $user_id ===");
        
        $conn = getSecureDbConnection();
        
        // ดึงข้อมูล auth ปัจจุบัน
        $query = "SELECT * FROM google_auth WHERE user_id = ? AND is_active = 1";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $auth = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$auth) {
            $conn->close();
            error_log("No auth data found for user $user_id");
            return [
                'success' => false,
                'error' => 'ไม่พบข้อมูล Google Authentication',
                'requires_reauth' => true,
                'action_required' => 'connect'
            ];
        }
        
        if (empty($auth['google_refresh_token'])) {
            $conn->close();
            error_log("No refresh token for user $user_id");
            return [
                'success' => false,
                'error' => 'ไม่มี Refresh Token - ต้องเชื่อมต่อใหม่',
                'requires_reauth' => true,
                'action_required' => 'connect'
            ];
        }
        
        error_log("Found refresh token for user $user_id, proceeding with refresh...");
        
        // ตรวจสอบว่าต้อง refresh หรือไม่ (ถ้าไม่ force)
        if (!$forceRefresh && $auth['token_expiry']) {
            $expiry_time = strtotime($auth['token_expiry']);
            $current_time = time();
            $time_to_expiry = $expiry_time - $current_time;
            
            // ถ้าเหลือเวลามากกว่า 30 นาที ไม่ต้อง refresh
            if ($time_to_expiry > 1800) {
                $conn->close();
                error_log("Token still valid for user $user_id, skipping refresh");
                return [
                    'success' => true,
                    'message' => 'Token ยังใช้งานได้ ไม่ต้อง refresh',
                    'access_token' => $auth['google_access_token'],
                    'expires_in' => $time_to_expiry,
                    'token_expiry' => $auth['token_expiry'],
                    'skipped_refresh' => true,
                    'minutes_remaining' => round($time_to_expiry / 60),
                    'hours_remaining' => round($time_to_expiry / 3600, 1)
                ];
            }
        }
        
        // ทำการ refresh token จริงๆ
        error_log("Calling Google OAuth API to refresh token...");
        
        $postData = [
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'refresh_token' => $auth['google_refresh_token'],
            'grant_type' => 'refresh_token'
        ];
        
        error_log("Refresh token request data prepared");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
            'User-Agent: Teaching-Schedule-System/1.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);
        
        error_log("Google API Response - HTTP Code: $httpCode");
        
        if ($curlError) {
            $conn->close();
            error_log("cURL error during token refresh: $curlError");
            return [
                'success' => false,
                'error' => 'การเชื่อมต่อ Google ล้มเหลว: ' . $curlError,
                'curl_info' => $curlInfo
            ];
        }
        
        if ($httpCode !== 200) {
            $conn->close();
            error_log("Token refresh failed. HTTP Code: $httpCode, Response: $response");
            
            $responseData = json_decode($response, true);
            if (isset($responseData['error'])) {
                if ($responseData['error'] === 'invalid_grant') {
                    return [
                        'success' => false,
                        'error' => 'Refresh Token หมดอายุ - ต้องเชื่อมต่อ Google Calendar ใหม่',
                        'requires_reauth' => true,
                        'action_required' => 'connect',
                        'google_error' => $responseData
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Google API Error: ' . $responseData['error'] . ' - ' . ($responseData['error_description'] ?? ''),
                        'google_error' => $responseData
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'error' => "HTTP Error $httpCode: ไม่สามารถ refresh token ได้",
                    'response' => $response
                ];
            }
        }
        
        $newTokenData = json_decode($response, true);
        error_log("Google API returned: " . json_encode(array_keys($newTokenData)));
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $conn->close();
            return [
                'success' => false,
                'error' => 'JSON decode error: ' . json_last_error_msg(),
                'response' => $response
            ];
        }
        
        if (isset($newTokenData['error'])) {
            $conn->close();
            error_log("Google API returned error: " . json_encode($newTokenData));
            
            if ($newTokenData['error'] === 'invalid_grant') {
                return [
                    'success' => false,
                    'error' => 'Refresh Token หมดอายุ - ต้องเชื่อมต่อ Google Calendar ใหม่',
                    'requires_reauth' => true,
                    'action_required' => 'connect'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Google API Error: ' . $newTokenData['error']
                ];
            }
        }
        
        if (!isset($newTokenData['access_token'])) {
            $conn->close();
            return [
                'success' => false,
                'error' => 'ไม่ได้รับ Access Token ใหม่จาก Google',
                'response' => $newTokenData
            ];
        }
        
        // คำนวณเวลาหมดอายุใหม่
        $expires_in = $newTokenData['expires_in'] ?? 3600;
        $new_expiry = date('Y-m-d H:i:s', time() + $expires_in);
        
        error_log("New token expires in $expires_in seconds, expiry: $new_expiry");
        
        // อัพเดทข้อมูลในฐานข้อมูล
        $update_query = "
            UPDATE google_auth 
            SET google_access_token = ?, 
                token_expiry = ?, 
                updated_at = NOW(),
                last_checked = NOW()
            WHERE user_id = ?
        ";
        
        $stmt = $conn->prepare($update_query);
        if (!$stmt) {
            $conn->close();
            return [
                'success' => false,
                'error' => 'Update prepare failed: ' . $conn->error
            ];
        }
        
        $stmt->bind_param("ssi", $newTokenData['access_token'], $new_expiry, $user_id);
        
        if (!$stmt->execute()) {
            $stmt->close();
            $conn->close();
            return [
                'success' => false,
                'error' => 'ไม่สามารถบันทึก Token ใหม่ได้: ' . $stmt->error
            ];
        }
        
        $stmt->close();
        $conn->close();
        
        error_log("=== Token refresh completed successfully for user $user_id ===");
        
        return [
            'success' => true,
            'access_token' => $newTokenData['access_token'],
            'expires_in' => $expires_in,
            'token_expiry' => $new_expiry,
            'message' => 'Refresh Token สำเร็จ! Token ใหม่ใช้งานได้เป็นเวลา ' . round($expires_in / 3600, 1) . ' ชั่วโมง',
            'refresh_time' => date('Y-m-d H:i:s'),
            'hours_valid' => round($expires_in / 3600, 1),
            'minutes_valid' => round($expires_in / 60),
            'refreshed' => true,
            'old_expiry' => $auth['token_expiry']
        ];
        
    } catch (Exception $e) {
        if (isset($conn)) $conn->close();
        error_log("Exception during token refresh for user $user_id: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => 'เกิดข้อผิดพลาดในการ refresh token: ' . $e->getMessage(),
            'exception' => $e->getTraceAsString()
        ];
    }
}

/**
 * ฟังก์ชัน Auto-refresh เมื่อ login (Enhanced)
 */
function autoRefreshOnLoginEnhanced($user_id) {
    try {
        error_log("Auto-refresh on login for user $user_id");
        
        $conn = getSecureDbConnection();
        
        // ตรวจสอบว่ามี Google Auth หรือไม่
        $query = "
            SELECT 
                token_expiry, 
                google_refresh_token IS NOT NULL as has_refresh_token,
                google_email,
                google_name,
                TIMESTAMPDIFF(MINUTE, NOW(), token_expiry) as minutes_to_expiry
            FROM google_auth 
            WHERE user_id = ? AND is_active = 1
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        if (!$result) {
            error_log("No Google auth found for user $user_id");
            return [
                'auto_refreshed' => false,
                'reason' => 'no_google_auth',
                'message' => 'ยังไม่ได้เชื่อมต่อ Google Calendar',
                'has_google_auth' => false
            ];
        }
        
        if (!$result['has_refresh_token']) {
            error_log("No refresh token for user $user_id");
            return [
                'auto_refreshed' => false,
                'reason' => 'no_refresh_token',
                'message' => 'ไม่มี Refresh Token - ต้องเชื่อมต่อใหม่',
                'has_google_auth' => true,
                'google_email' => $result['google_email'],
                'requires_reauth' => true
            ];
        }
        
        // ตรวจสอบว่า token หมดอายุหรือจะหมดอายุในเร็วๆ นี้
        $should_refresh = false;
        $reason = '';
        $minutes_to_expiry = $result['minutes_to_expiry'];
        
        if (!$result['token_expiry']) {
            $should_refresh = true;
            $reason = 'no_expiry_date';
        } else if ($minutes_to_expiry === null) {
            $should_refresh = true;
            $reason = 'null_expiry';
        } else if ($minutes_to_expiry <= 0) {
            $should_refresh = true;
            $reason = 'expired';
        } else if ($minutes_to_expiry <= 120) { // หมดอายุภายใน 2 ชั่วโมง
            $should_refresh = true;
            $reason = 'expiring_soon';
        }
        
        if ($should_refresh) {
            error_log("Auto-refreshing token for user $user_id (reason: $reason, minutes to expiry: $minutes_to_expiry)");
            
            // ใช้ฟังก์ชัน refresh ที่แก้ไขแล้ว
            $refreshResult = performActualTokenRefresh($user_id, true);
            
            return [
                'auto_refreshed' => true,
                'reason' => $reason,
                'refresh_result' => $refreshResult,
                'message' => $refreshResult['success'] ? 'Auto-refresh สำเร็จ' : 'Auto-refresh ล้มเหลว',
                'has_google_auth' => true,
                'google_email' => $result['google_email'],
                'old_expiry_minutes' => $minutes_to_expiry
            ];
        } else {
            error_log("Token still valid for user $user_id, no auto-refresh needed (minutes to expiry: $minutes_to_expiry)");
            return [
                'auto_refreshed' => false,
                'reason' => 'token_still_valid',
                'message' => 'Token ยังใช้งานได้ ไม่ต้อง refresh',
                'has_google_auth' => true,
                'google_email' => $result['google_email'],
                'time_to_expiry_hours' => round($minutes_to_expiry / 60, 1),
                'time_to_expiry_minutes' => $minutes_to_expiry
            ];
        }
        
    } catch (Exception $e) {
        error_log("Error in autoRefreshOnLoginEnhanced for user $user_id: " . $e->getMessage());
        return [
            'auto_refreshed' => false,
            'reason' => 'error',
            'message' => 'เกิดข้อผิดพลาดในการ auto-refresh: ' . $e->getMessage(),
            'has_google_auth' => false
        ];
    }
}

/**
 * ตรวจสอบและทดสอบ Token ที่ refresh แล้ว (Fixed version)
 */
function validateRefreshedTokenEnhanced($user_id) {
    try {
        $conn = getSecureDbConnection();
        
        $query = "SELECT google_access_token FROM google_auth WHERE user_id = ? AND is_active = 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        if (!$result) {
            return [
                'success' => false,
                'error' => 'ไม่พบ Token ที่ refresh แล้ว'
            ];
        }
        
        error_log("Testing refreshed token by calling Google Calendar API...");
        
        // ทดสอบ Token โดยเรียก Google Calendar API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/calendar/v3/users/me/calendarList?maxResults=1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $result['google_access_token'],
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            return [
                'success' => false,
                'error' => 'cURL Error during validation: ' . $curlError
            ];
        }
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            error_log("Token validation successful - Calendar API responded normally");
            return [
                'success' => true,
                'message' => 'Token ผ่านการตรวจสอบ ใช้งานได้ปกติ',
                'calendar_count' => isset($data['items']) ? count($data['items']) : 0,
                'validated_at' => date('Y-m-d H:i:s')
            ];
        } else if ($httpCode === 401) {
            return [
                'success' => false,
                'error' => 'Token ไม่ถูกต้องหรือหมดอายุ'
            ];
        } else {
            return [
                'success' => false,
                'error' => "HTTP Error $httpCode during token validation"
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// ===== Main Request Handler =====
try {
    // ตรวจสอบ session
    $user_id = checkSessionSafely();
    
    // ตรวจสอบ method
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'status';
        
        switch ($action) {
            case 'status':
                // GET status - ตรวจสอบสถานะ Token
                error_log("=== GET STATUS REQUEST for user $user_id ===");
                
                $statusResult = getTokenExpiryStatusAdvanced($user_id);
                
                sendResponse([
                    'status' => 'success',
                    'data' => $statusResult,
                    'user_id' => $user_id,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
                
            case 'refresh':
                // GET refresh - Legacy support
                error_log("=== GET REFRESH TOKEN REQUEST for user $user_id ===");
                
                $result = performActualTokenRefresh($user_id, true);
                
                if ($result['success'] && !isset($result['skipped_refresh'])) {
                    // ทดสอบ token ที่ refresh แล้ว
                    error_log("Validating refreshed token...");
                    $validation = validateRefreshedTokenEnhanced($user_id);
                    $result['validation'] = $validation;
                }
                
                sendResponse([
                    'status' => $result['success'] ? 'success' : 'error',
                    'message' => $result['success'] ? $result['message'] : $result['error'],
                    'data' => $result,
                    'user_id' => $user_id,
                    'requires_reauth' => $result['requires_reauth'] ?? false,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
                
            case 'validate':
                // GET validate - ตรวจสอบ Token (ใหม่)
                error_log("=== GET VALIDATE TOKEN REQUEST for user $user_id ===");
                
                $validation = validateRefreshedTokenEnhanced($user_id);
                
                sendResponse([
                    'status' => $validation['success'] ? 'success' : 'error',
                    'message' => $validation['success'] ? $validation['message'] : $validation['error'],
                    'data' => $validation,
                    'user_id' => $user_id,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
                
            case 'auto_refresh_on_login':
                // Auto-refresh เมื่อ login
                $autoRefreshResult = autoRefreshOnLoginEnhanced($user_id);
                sendResponse([
                    'status' => 'success',
                    'data' => $autoRefreshResult,
                    'user_id' => $user_id,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
                
            case 'test_api':
                // ทดสอบ API connectivity
                $connectivity_test = [
                    'database' => false,
                    'google_oauth' => false,
                    'user_auth' => false
                ];
                
                try {
                    $conn = getSecureDbConnection();
                    $conn->close();
                    $connectivity_test['database'] = true;
                } catch (Exception $e) {
                    // Database test failed
                }
                
                $connectivity_test['user_auth'] = true;
                
                try {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($ch, CURLOPT_NOBODY, true);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode === 400 || $httpCode === 405) {
                        $connectivity_test['google_oauth'] = true;
                    }
                } catch (Exception $e) {
                    // OAuth test failed
                }
                
                sendResponse([
                    'status' => 'success',
                    'message' => 'API Test Completed',
                    'user_id' => $user_id,
                    'connectivity_test' => $connectivity_test,
                    'session_data' => [
                        'username' => $_SESSION['username'] ?? 'unknown',
                        'user_type' => $_SESSION['user_type'] ?? 'unknown'
                    ],
                    'server_info' => [
                        'php_version' => phpversion(),
                        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                        'request_time' => date('Y-m-d H:i:s'),
                        'curl_available' => function_exists('curl_init'),
                        'mysqli_available' => class_exists('mysqli')
                    ],
                    'google_config' => [
                        'client_id_configured' => defined('GOOGLE_CLIENT_ID') && !empty(GOOGLE_CLIENT_ID),
                        'client_secret_configured' => defined('GOOGLE_CLIENT_SECRET') && !empty(GOOGLE_CLIENT_SECRET),
                        'redirect_uri_configured' => defined('GOOGLE_REDIRECT_URI') && !empty(GOOGLE_REDIRECT_URI)
                    ]
                ]);
                break;
                
            case 'debug_info':
                // Debug information
                $conn = getSecureDbConnection();
                $query = "SELECT user_id, google_email, token_expiry, google_refresh_token IS NOT NULL as has_refresh_token, 
                         TIMESTAMPDIFF(MINUTE, NOW(), token_expiry) as minutes_to_expiry 
                         FROM google_auth WHERE user_id = ? AND is_active = 1";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $debug_data = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $conn->close();
                
                sendResponse([
                    'status' => 'success',
                    'debug_data' => $debug_data,
                    'user_id' => $user_id,
                    'session_info' => [
                        'user_id' => $_SESSION['user_id'] ?? null,
                        'username' => $_SESSION['username'] ?? null
                    ],
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
                
            default:
                sendResponse([
                    'status' => 'error',
                    'message' => 'Invalid GET action: ' . $action,
                    'available_actions' => ['status', 'refresh', 'validate', 'auto_refresh_on_login', 'test_api', 'debug_info']
                ], 400);
        }
        
    } else if ($method === 'POST') {
        // Parse JSON input for POST requests
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'refresh':
                // POST Refresh Access Token - ใช้ฟังก์ชันที่ทำงานจริง
                $forceRefresh = $input['force'] ?? true; // Default เป็น force refresh
                
                error_log("=== POST REFRESH TOKEN REQUEST for user $user_id ===");
                error_log("Force refresh: " . ($forceRefresh ? 'true' : 'false'));
                
                $result = performActualTokenRefresh($user_id, $forceRefresh);
                
                error_log("Refresh result: " . json_encode([
                    'success' => $result['success'],
                    'message' => $result['success'] ? $result['message'] : $result['error']
                ]));
                
                if ($result['success'] && !isset($result['skipped_refresh'])) {
                    // ทดสอบ token ที่ refresh แล้ว
                    error_log("Validating refreshed token...");
                    $validation = validateRefreshedTokenEnhanced($user_id);
                    $result['validation'] = $validation;
                    
                    if ($validation['success']) {
                        error_log("Token validation passed");
                    } else {
                        error_log("Token validation failed: " . $validation['error']);
                    }
                }
                
                sendResponse([
                    'status' => $result['success'] ? 'success' : 'error',
                    'message' => $result['success'] ? $result['message'] : $result['error'],
                    'data' => $result,
                    'user_id' => $user_id,
                    'requires_reauth' => $result['requires_reauth'] ?? false,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
                
            case 'validate':
                // POST validate - ตรวจสอบ Token (ใหม่)
                error_log("=== POST VALIDATE TOKEN REQUEST for user $user_id ===");
                
                $validation = validateRefreshedTokenEnhanced($user_id);
                
                sendResponse([
                    'status' => $validation['success'] ? 'success' : 'error',
                    'message' => $validation['success'] ? $validation['message'] : $validation['error'],
                    'data' => $validation,
                    'user_id' => $user_id,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
                
            case 'auto_refresh_on_login':
                // Auto-refresh เมื่อ login
                $autoRefreshResult = autoRefreshOnLoginEnhanced($user_id);
                sendResponse([
                    'status' => 'success',
                    'data' => $autoRefreshResult,
                    'user_id' => $user_id,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
                
            case 'test_api':
                // ทดสอบ API connectivity (POST version)
                $connectivity_test = [
                    'database' => false,
                    'google_oauth' => false,
                    'user_auth' => false
                ];
                
                try {
                    $conn = getSecureDbConnection();
                    $conn->close();
                    $connectivity_test['database'] = true;
                } catch (Exception $e) {
                    // Database test failed
                }
                
                $connectivity_test['user_auth'] = true;
                
                try {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($ch, CURLOPT_NOBODY, true);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode === 400 || $httpCode === 405) {
                        $connectivity_test['google_oauth'] = true;
                    }
                } catch (Exception $e) {
                    // OAuth test failed
                }
                
                sendResponse([
                    'status' => 'success',
                    'message' => 'API Test Completed',
                    'user_id' => $user_id,
                    'connectivity_test' => $connectivity_test,
                    'session_data' => [
                        'username' => $_SESSION['username'] ?? 'unknown',
                        'user_type' => $_SESSION['user_type'] ?? 'unknown'
                    ],
                    'server_info' => [
                        'php_version' => phpversion(),
                        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                        'request_time' => date('Y-m-d H:i:s'),
                        'curl_available' => function_exists('curl_init'),
                        'mysqli_available' => class_exists('mysqli')
                    ],
                    'google_config' => [
                        'client_id_configured' => defined('GOOGLE_CLIENT_ID') && !empty(GOOGLE_CLIENT_ID),
                        'client_secret_configured' => defined('GOOGLE_CLIENT_SECRET') && !empty(GOOGLE_CLIENT_SECRET),
                        'redirect_uri_configured' => defined('GOOGLE_REDIRECT_URI') && !empty(GOOGLE_REDIRECT_URI)
                    ]
                ]);
                break;
                
            default:
                sendResponse([
                    'status' => 'error',
                    'message' => 'Invalid POST action: ' . $action,
                    'available_actions' => ['refresh', 'validate', 'auto_refresh_on_login', 'test_api']
                ], 400);
        }
        
    } else {
        sendResponse([
            'status' => 'error',
            'message' => 'Method not allowed: ' . $method,
            'allowed_methods' => ['GET', 'POST', 'OPTIONS']
        ], 405);
    }
    
} catch (Exception $e) {
    error_log("=== FATAL ERROR in token_refresh.php ===");
    error_log("Error: " . $e->getMessage());
    error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
    error_log("Trace: " . $e->getTraceAsString());
    
    handleError('Unexpected error: ' . $e->getMessage(), 500, [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    error_log("=== FATAL PHP ERROR in token_refresh.php ===");
    error_log("Error: " . $e->getMessage());
    error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
    
    handleError('Fatal error: ' . $e->getMessage(), 500, [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}

?>