<?php
/**
 * Enhanced Google Calendar Integration with Auto-refresh
 * ไฟล์: google_calendar_integration.php
 * เพิ่มฟังก์ชัน auto-refresh เมื่อ login และปรับปรุงการจัดการ token
 */

// ป้องกันการเรียกไฟล์โดยตรง
if (!defined('DB_HOST')) {
    die('Direct access not allowed');
}

// ===== การตั้งค่า Google API =====
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', '545614412274-1dpi138qggqtboor377ein8g2h43k7ra.apps.googleusercontent.com');
    define('GOOGLE_CLIENT_SECRET', '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');
    define('GOOGLE_REDIRECT_URI', 'http://localhost/api/calendar/google_calendar_oauth.php');
}

// ===== ฟังก์ชันสำหรับ Google Calendar Client =====

if (!function_exists('createGoogleCalendarClient')) {
    function createGoogleCalendarClient() {
        $vendorPath = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($vendorPath)) {
            throw new Exception('Google Client Library not found. Please run: composer require google/apiclient');
        }
        
        require_once $vendorPath;
        
        $client = new Google_Client();
        $client->setClientId(GOOGLE_CLIENT_ID);
        $client->setClientSecret(GOOGLE_CLIENT_SECRET);
        $client->setRedirectUri(GOOGLE_REDIRECT_URI);
        $client->addScope('https://www.googleapis.com/auth/calendar');
        $client->addScope('https://www.googleapis.com/auth/userinfo.email');
        $client->addScope('https://www.googleapis.com/auth/userinfo.profile');
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        
        return $client;
    }
}

// ===== ฟังก์ชันตรวจสอบการเชื่อมต่อ =====

if (!function_exists('isGoogleCalendarConnected')) {
    function isGoogleCalendarConnected($user_id) {
        try {
            $conn = connectMySQLi();
            
            $query = "SELECT google_auth_id FROM google_auth WHERE user_id = ? AND is_active = 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            $conn->close();
            
            return !empty($result);
        } catch (Exception $e) {
            error_log("Error checking Google Calendar connection: " . $e->getMessage());
            return false;
        }
    }
}

// ===== ฟังก์ชัน Auto-refresh เมื่อ Login (ใหม่) =====

if (!function_exists('autoRefreshTokenOnLogin')) {
    /**
     * Auto-refresh Google Calendar Token เมื่อ user login
     * @param int $user_id ID ของผู้ใช้
     * @return array ผลลัพธ์การ auto-refresh
     */
    function autoRefreshTokenOnLogin($user_id) {
        try {
            error_log("Auto-refresh on login for user $user_id");
            
            $conn = connectMySQLi();
            
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
                    'message' => 'ยังไม่ได้เชื่อมต่อ Google Calendar'
                ];
            }
            
            if (!$result['has_refresh_token']) {
                error_log("No refresh token for user $user_id");
                return [
                    'auto_refreshed' => false,
                    'reason' => 'no_refresh_token',
                    'message' => 'ไม่มี Refresh Token - ต้องเชื่อมต่อใหม่'
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
                
                // ใช้ฟังก์ชัน refresh ที่มีอยู่แล้ว
                $refreshResult = refreshGoogleAccessTokenEnhanced($user_id, true);
                
                return [
                    'auto_refreshed' => true,
                    'reason' => $reason,
                    'refresh_result' => $refreshResult,
                    'message' => $refreshResult['success'] ? 'Auto-refresh สำเร็จ' : 'Auto-refresh ล้มเหลว',
                    'google_email' => $result['google_email'],
                    'old_expiry_minutes' => $minutes_to_expiry
                ];
            } else {
                error_log("Token still valid for user $user_id, no auto-refresh needed (minutes to expiry: $minutes_to_expiry)");
                return [
                    'auto_refreshed' => false,
                    'reason' => 'token_still_valid',
                    'message' => 'Token ยังใช้งานได้ ไม่ต้อง refresh',
                    'time_to_expiry_hours' => round($minutes_to_expiry / 60, 1),
                    'time_to_expiry_minutes' => $minutes_to_expiry,
                    'google_email' => $result['google_email']
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error in autoRefreshTokenOnLogin for user $user_id: " . $e->getMessage());
            return [
                'auto_refreshed' => false,
                'reason' => 'error',
                'message' => 'เกิดข้อผิดพลาดในการ auto-refresh: ' . $e->getMessage()
            ];
        }
    }
}

// ===== ฟังก์ชัน Refresh Token ที่ปรับปรุงแล้ว =====

if (!function_exists('refreshGoogleAccessTokenEnhanced')) {
    /**
     * Enhanced Refresh Google Access Token
     * @param int $user_id ID ของผู้ใช้
     * @param bool $forceRefresh บังคับ refresh แม้ token ยังไม่หมดอายุ
     * @return array ผลลัพธ์การ refresh token
     */
    function refreshGoogleAccessTokenEnhanced($user_id, $forceRefresh = false) {
        try {
            $conn = connectMySQLi();
            
            // ดึงข้อมูล auth ปัจจุบัน
            $query = "SELECT * FROM google_auth WHERE user_id = ? AND is_active = 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $auth = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$auth) {
                $conn->close();
                return [
                    'success' => false,
                    'error' => 'ไม่พบข้อมูล Google Authentication',
                    'requires_reauth' => true
                ];
            }
            
            if (empty($auth['google_refresh_token'])) {
                $conn->close();
                return [
                    'success' => false,
                    'error' => 'ไม่มี Refresh Token - ต้องเชื่อมต่อใหม่',
                    'requires_reauth' => true
                ];
            }
            
            // ตรวจสอบว่าต้อง refresh หรือไม่ (ถ้าไม่ force)
            if (!$forceRefresh && $auth['token_expiry']) {
                $expiry_time = strtotime($auth['token_expiry']);
                $current_time = time();
                $time_to_expiry = $expiry_time - $current_time;
                
                // ถ้าเหลือเวลามากกว่า 30 นาที ไม่ต้อง refresh
                if ($time_to_expiry > 1800) {
                    $conn->close();
                    return [
                        'success' => true,
                        'message' => 'Token ยังใช้งานได้ ไม่ต้อง refresh',
                        'access_token' => $auth['google_access_token'],
                        'expires_in' => $time_to_expiry,
                        'token_expiry' => $auth['token_expiry'],
                        'skipped_refresh' => true,
                        'minutes_remaining' => round($time_to_expiry / 60)
                    ];
                }
            }
            
            error_log("Starting token refresh for user $user_id");
            
            // ใช้ cURL เพื่อ refresh token
            $postData = [
                'client_id' => GOOGLE_CLIENT_ID,
                'client_secret' => GOOGLE_CLIENT_SECRET,
                'refresh_token' => $auth['google_refresh_token'],
                'grant_type' => 'refresh_token'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                $conn->close();
                error_log("cURL error during token refresh for user $user_id: $curlError");
                return [
                    'success' => false,
                    'error' => 'การเชื่อมต่อ Google ล้มเหลว: ' . $curlError
                ];
            }
            
            if ($httpCode !== 200) {
                $conn->close();
                error_log("Token refresh failed for user $user_id. HTTP Code: $httpCode, Response: $response");
                
                $responseData = json_decode($response, true);
                if (isset($responseData['error'])) {
                    if ($responseData['error'] === 'invalid_grant') {
                        return [
                            'success' => false,
                            'error' => 'Refresh Token หมดอายุหรือไม่ถูกต้อง - ต้องเชื่อมต่อใหม่',
                            'requires_reauth' => true
                        ];
                    } else {
                        return [
                            'success' => false,
                            'error' => 'Google API Error: ' . $responseData['error'] . ' - ' . ($responseData['error_description'] ?? '')
                        ];
                    }
                } else {
                    return [
                        'success' => false,
                        'error' => "HTTP Error $httpCode: ไม่สามารถ refresh token ได้"
                    ];
                }
            }
            
            $newTokenData = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $conn->close();
                return [
                    'success' => false,
                    'error' => 'JSON decode error: ' . json_last_error_msg()
                ];
            }
            
            if (isset($newTokenData['error'])) {
                $conn->close();
                error_log("Refresh token error for user $user_id: " . json_encode($newTokenData));
                
                if ($newTokenData['error'] === 'invalid_grant') {
                    return [
                        'success' => false,
                        'error' => 'Refresh Token หมดอายุหรือไม่ถูกต้อง - ต้องเชื่อมต่อใหม่',
                        'requires_reauth' => true
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
                    'error' => 'ไม่ได้รับ Access Token ใหม่จาก Google'
                ];
            }
            
            // คำนวณเวลาหมดอายุใหม่
            $expires_in = $newTokenData['expires_in'] ?? 3600;
            $new_expiry = date('Y-m-d H:i:s', time() + $expires_in);
            
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
            
            error_log("Successfully refreshed access token for user $user_id. New expiry: $new_expiry");
            
            return [
                'success' => true,
                'access_token' => $newTokenData['access_token'],
                'expires_in' => $expires_in,
                'token_expiry' => $new_expiry,
                'message' => 'Refresh Token สำเร็จ! Token ใหม่ใช้งานได้เป็นเวลา ' . round($expires_in / 3600, 1) . ' ชั่วโมง',
                'refresh_time' => date('Y-m-d H:i:s'),
                'hours_valid' => round($expires_in / 3600, 1)
            ];
            
        } catch (Exception $e) {
            if (isset($conn)) $conn->close();
            error_log("Exception in refreshGoogleAccessTokenEnhanced for user $user_id: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'เกิดข้อผิดพลาดในการ refresh token: ' . $e->getMessage()
            ];
        }
    }
}

// ===== ฟังก์ชันเดิมที่ปรับปรุงแล้ว =====

if (!function_exists('refreshGoogleAccessToken')) {
    function refreshGoogleAccessToken($user_id, $forceRefresh = false) {
        // ใช้ฟังก์ชันที่ปรับปรุงแล้ว
        return refreshGoogleAccessTokenEnhanced($user_id, $forceRefresh);
    }
}

// ===== ฟังก์ชันส่ง Event ไป Google Calendar =====

if (!function_exists('sendEventToGoogleCalendar')) {
    function sendEventToGoogleCalendar($user_id, $eventData) {
        try {
            $conn = connectMySQLi();
            
            $query = "SELECT * FROM google_auth WHERE user_id = ? AND is_active = 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $auth = $stmt->get_result()->fetch_assoc();
            
            if (!$auth) {
                throw new Exception('ไม่พบการเชื่อมต่อ Google Calendar');
            }
            
            $client = createGoogleCalendarClient();
            $client->setAccessToken([
                'access_token' => $auth['google_access_token'],
                'refresh_token' => $auth['google_refresh_token'],
                'expires_in' => $auth['token_expiry'] ? (strtotime($auth['token_expiry']) - time()) : 3600,
                'created' => time()
            ]);
            
            // ตรวจสอบและ refresh token ถ้าหมดอายุ
            if ($client->isAccessTokenExpired()) {
                if (!empty($auth['google_refresh_token'])) {
                    $refreshResult = refreshGoogleAccessTokenEnhanced($user_id, true);
                    
                    if ($refreshResult['success']) {
                        $client->setAccessToken([
                            'access_token' => $refreshResult['access_token'],
                            'refresh_token' => $auth['google_refresh_token'],
                            'expires_in' => $refreshResult['expires_in'],
                            'created' => time()
                        ]);
                    } else {
                        throw new Exception('ไม่สามารถ refresh token ได้');
                    }
                } else {
                    throw new Exception('ไม่มี refresh token');
                }
            }
            
            $service = new Google_Service_Calendar($client);
            
            // สร้าง Event
            $event = new Google_Service_Calendar_Event([
                'summary' => $eventData['title'],
                'description' => $eventData['description'] ?? '',
                'location' => $eventData['location'] ?? '',
                'start' => [
                    'dateTime' => $eventData['start_datetime'],
                    'timeZone' => 'Asia/Bangkok'
                ],
                'end' => [
                    'dateTime' => $eventData['end_datetime'],
                    'timeZone' => 'Asia/Bangkok'
                ]
            ]);
            
            $createdEvent = $service->events->insert('primary', $event);
            
            $conn->close();
            
            return [
                'success' => true,
                'google_event_id' => $createdEvent->id,
                'event_url' => $createdEvent->htmlLink
            ];
            
        } catch (Exception $e) {
            error_log("Error sending event to Google Calendar: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// ===== ฟังก์ชันแปลงข้อมูล =====

if (!function_exists('convertDateTimeToISO8601')) {
    function convertDateTimeToISO8601($datetime, $timezone = 'Asia/Bangkok') {
        try {
            $dt = new DateTime($datetime, new DateTimeZone($timezone));
            return $dt->format('Y-m-d\TH:i:sP');
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('createEventDataFromSession')) {
    function createEventDataFromSession($session_data) {
        $start_datetime = $session_data['session_date'] . ' ' . $session_data['start_time'];
        $end_datetime = $session_data['session_date'] . ' ' . $session_data['end_time'];
        
        return [
            'title' => $session_data['subject_code'] . ' - ' . $session_data['subject_name'],
            'description' => "การเรียนการสอน\nรายวิชา: {$session_data['subject_name']}\nอาจารย์: {$session_data['teacher_name']}\nชั้นปี: {$session_data['class_year']}",
            'location' => "ห้อง {$session_data['room_number']}",
            'start_datetime' => convertDateTimeToISO8601($start_datetime),
            'end_datetime' => convertDateTimeToISO8601($end_datetime),
            'session_id' => $session_data['session_id'] ?? null,
            'classroom_id' => $session_data['classroom_id'] ?? null,
            'event_type' => 'regular'
        ];
    }
}

if (!function_exists('createEventDataFromCompensation')) {
    function createEventDataFromCompensation($compensation_data) {
        $start_datetime = $compensation_data['makeup_date'] . ' ' . $compensation_data['makeup_start_time'];
        $end_datetime = $compensation_data['makeup_date'] . ' ' . $compensation_data['makeup_end_time'];
        
        return [
            'title' => '[ชดเชย] ' . $compensation_data['subject_code'] . ' - ' . $compensation_data['subject_name'],
            'description' => "การเรียนชดเชย\nรายวิชา: {$compensation_data['subject_name']}\nอาจารย์: {$compensation_data['teacher_name']}\nชั้นปี: {$compensation_data['class_year']}\nเหตุผลการชดเชย: {$compensation_data['reason']}",
            'location' => "ห้อง {$compensation_data['makeup_room_number']}",
            'start_datetime' => convertDateTimeToISO8601($start_datetime),
            'end_datetime' => convertDateTimeToISO8601($end_datetime),
            'compensation_id' => $compensation_data['cancellation_id'] ?? null,
            'classroom_id' => $compensation_data['makeup_classroom_id'] ?? null,
            'event_type' => 'compensation'
        ];
    }
}

// ===== ฟังก์ชันจัดการสถานะ (ปรับปรุงแล้ว) =====

if (!function_exists('getGoogleAuthStatus')) {
    function getGoogleAuthStatus($user_id = null) {
        if (!$user_id && isLoggedIn()) {
            $user_id = $_SESSION['user_id'];
        }
        
        if (!$user_id) {
            return [
                'is_connected' => false,
                'status' => 'not_logged_in',
                'message' => 'ไม่ได้เข้าสู่ระบบ'
            ];
        }
        
        try {
            $conn = connectMySQLi();
            
            $query = "
                SELECT 
                    google_auth_id, google_email, google_name, 
                    created_at, updated_at,
                    CASE 
                        WHEN token_expiry IS NULL THEN 'no_expiry'
                        WHEN token_expiry > NOW() THEN 'valid'
                        WHEN token_expiry > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'expired'
                        ELSE 'long_expired'
                    END as token_status,
                    token_expiry,
                    TIMESTAMPDIFF(MINUTE, NOW(), token_expiry) as minutes_to_expiry
                FROM google_auth 
                WHERE user_id = ? AND is_active = 1
                ORDER BY updated_at DESC
                LIMIT 1
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $conn->close();
            
            if (!$result) {
                return [
                    'is_connected' => false,
                    'status' => 'not_connected',
                    'message' => 'ยังไม่ได้เชื่อมต่อ Google Calendar'
                ];
            }
            
            $token_status = $result['token_status'];
            $minutes_to_expiry = $result['minutes_to_expiry'];
            
            $authStatus = [
                'is_connected' => true,
                'google_auth_id' => (int)$result['google_auth_id'],
                'google_email' => $result['google_email'],
                'google_name' => $result['google_name'],
                'connected_at' => $result['created_at'],
                'last_updated' => $result['updated_at'],
                'token_status' => $token_status,
                'expiry_date' => $result['token_expiry'],
                'minutes_to_expiry' => $minutes_to_expiry
            ];
            
            switch ($token_status) {
                case 'valid':
                case 'no_expiry':
                    $authStatus['status'] = 'connected';
                    $authStatus['message'] = 'เชื่อมต่อ Google Calendar สำเร็จ';
                    $authStatus['action_required'] = 'none';
                    break;
                    
                case 'expired':
                    $authStatus['status'] = 'token_expired';
                    $authStatus['message'] = 'Google Calendar Token หมดอายุ - สามารถ Refresh ได้';
                    $authStatus['action_required'] = 'refresh';
                    break;
                    
                case 'long_expired':
                    $authStatus['status'] = 'token_long_expired';
                    $authStatus['message'] = 'Google Calendar Token หมดอายุนาน - แนะนำให้ Refresh';
                    $authStatus['action_required'] = 'refresh';
                    break;
            }
            
            // เพิ่มข้อมูลสำหรับการแสดงผล
            if ($minutes_to_expiry !== null) {
                if ($minutes_to_expiry <= 0) {
                    $authStatus['needs_refresh'] = true;
                    $authStatus['expired_minutes_ago'] = abs($minutes_to_expiry);
                } else if ($minutes_to_expiry <= 120) {
                    $authStatus['should_refresh'] = true;
                    $authStatus['expires_soon'] = true;
                } else {
                    $authStatus['hours_remaining'] = round($minutes_to_expiry / 60, 1);
                }
            }
            
            return $authStatus;
            
        } catch (Exception $e) {
            error_log("Error getting Google Auth status: " . $e->getMessage());
            return [
                'is_connected' => false,
                'status' => 'error',
                'message' => 'เกิดข้อผิดพลาดในการตรวจสอบ Google Authentication'
            ];
        }
    }
}

if (!function_exists('getGoogleCalendarWarning')) {
    function getGoogleCalendarWarning($user_id = null) {
        $googleAuth = getGoogleAuthStatus($user_id);
        
        if (!$googleAuth['is_connected']) {
            return [
                'type' => 'warning',
                'icon' => 'fas fa-exclamation-triangle',
                'title' => 'ยังไม่ได้เชื่อมต่อ Google Calendar',
                'message' => 'คุณจะไม่ได้รับการแจ้งเตือนตารางสอนและการชดเชย',
                'action' => [
                    'text' => 'เชื่อมต่อเลย',
                    'url' => '/api/calendar/google_calendar_oauth.php?action=start',
                    'class' => 'btn-warning'
                ]
            ];
        }
        
        if (in_array($googleAuth['status'], ['token_expired', 'token_long_expired'])) {
            return [
                'type' => 'info',
                'icon' => 'fas fa-refresh',
                'title' => 'Google Calendar Token สามารถ Refresh ได้',
                'message' => 'Token หมดอายุแล้ว แต่สามารถ Refresh เพื่อใช้งานต่อได้',
                'action' => [
                    'text' => 'Refresh Token',
                    'url' => 'javascript:tokenManager.refreshTokenWithNotification()',
                    'class' => 'btn-info'
                ]
            ];
        }
        
        if (isset($googleAuth['expires_soon']) && $googleAuth['expires_soon']) {
            return [
                'type' => 'warning',
                'icon' => 'fas fa-clock',
                'title' => 'Google Calendar Token จะหมดอายุเร็วๆ นี้',
                'message' => 'แนะนำให้ Refresh Token เพื่อป้องกันการหยุดการซิงค์',
                'action' => [
                    'text' => 'Refresh Token',
                    'url' => 'javascript:tokenManager.refreshTokenWithNotification()',
                    'class' => 'btn-warning'
                ]
            ];
        }
        
        return null; // ไม่มีการเตือน
    }
}

if (!function_exists('renderGoogleCalendarStatus')) {
    function renderGoogleCalendarStatus($showDetails = false, $user_id = null) {
        $googleAuth = getGoogleAuthStatus($user_id);
        $warning = getGoogleCalendarWarning($user_id);
        
        $html = '<div class="google-calendar-status">';
        
        if ($warning) {
            $html .= '
            <div class="alert alert-' . $warning['type'] . ' alert-dismissible fade show" role="alert">
                <i class="' . $warning['icon'] . ' me-2"></i>
                <strong>' . $warning['title'] . '</strong><br>
                <small>' . $warning['message'] . '</small>';
            
            if (isset($warning['action'])) {
                $html .= '
                <div class="mt-2">
                    <a href="' . $warning['action']['url'] . '" class="btn btn-sm ' . $warning['action']['class'] . '">
                        ' . $warning['action']['text'] . '
                    </a>
                </div>';
            }
            
            $html .= '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        } else {
            $html .= '
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Google Calendar เชื่อมต่อแล้ว</strong>';
            
            if ($showDetails && isset($googleAuth['google_email'])) {
                $html .= '<br><small>บัญชี: ' . htmlspecialchars($googleAuth['google_email']) . '</small>';
                
                if (isset($googleAuth['hours_remaining'])) {
                    $html .= '<br><small>Token เหลืออีก: ' . $googleAuth['hours_remaining'] . ' ชั่วโมง</small>';
                } else if (isset($googleAuth['expiry_date']) && $googleAuth['expiry_date']) {
                    if (function_exists('formatFullThaiDate')) {
                        $html .= '<br><small>Token หมดอายุ: ' . formatFullThaiDate($googleAuth['expiry_date']) . '</small>';
                    } else {
                        $html .= '<br><small>Token หมดอายุ: ' . $googleAuth['expiry_date'] . '</small>';
                    }
                }
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}

// ===== ฟังก์ชันสร้างตารางฐานข้อมูล =====

if (!function_exists('createGoogleAuthTable')) {
    function createGoogleAuthTable() {
        try {
            $conn = connectMySQLi();
            
            $createTable = "CREATE TABLE IF NOT EXISTS google_auth (
                google_auth_id INT(11) PRIMARY KEY AUTO_INCREMENT,
                user_id INT(11) NOT NULL,
                google_access_token TEXT,
                google_refresh_token TEXT,
                google_id_token TEXT,
                token_expiry DATETIME DEFAULT NULL,
                google_email VARCHAR(255) DEFAULT NULL,
                google_name VARCHAR(255) DEFAULT NULL,
                calendar_id VARCHAR(255) DEFAULT 'primary',
                is_active TINYINT(1) DEFAULT 1,
                last_checked DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (user_id),
                INDEX (is_active),
                UNIQUE KEY unique_user_google (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if (!$conn->query($createTable)) {
                error_log("Error creating google_auth table: " . $conn->error);
            }
            
            $conn->close();
            
        } catch (Exception $e) {
            error_log("Error in createGoogleAuthTable: " . $e->getMessage());
        }
    }
}

// ===== ฟังก์ชันเริ่มต้นฐานข้อมูล =====

if (!function_exists('initializeGoogleCalendarDatabase')) {
    function initializeGoogleCalendarDatabase() {
        try {
            createGoogleAuthTable();
            error_log("Google Calendar database table initialized successfully");
        } catch (Exception $e) {
            error_log("Error initializing Google Calendar database: " . $e->getMessage());
        }
    }
}

// ===== ฟังก์ชันช่วยเหลือเพิ่มเติม =====

if (!function_exists('deleteGoogleCalendarEvent')) {
    function deleteGoogleCalendarEvent($user_id, $google_event_id) {
        try {
            $conn = connectMySQLi();
            
            $query = "SELECT * FROM google_auth WHERE user_id = ? AND is_active = 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $auth = $stmt->get_result()->fetch_assoc();
            
            if (!$auth) {
                throw new Exception('ไม่พบการเชื่อมต่อ Google Calendar');
            }
            
            $client = createGoogleCalendarClient();
            $client->setAccessToken([
                'access_token' => $auth['google_access_token'],
                'refresh_token' => $auth['google_refresh_token'],
                'expires_in' => $auth['token_expiry'] ? (strtotime($auth['token_expiry']) - time()) : 3600,
                'created' => time()
            ]);
            
            if ($client->isAccessTokenExpired()) {
                if (!empty($auth['google_refresh_token'])) {
                    $refreshResult = refreshGoogleAccessTokenEnhanced($user_id, true);
                    
                    if ($refreshResult['success']) {
                        $client->setAccessToken([
                            'access_token' => $refreshResult['access_token'],
                            'refresh_token' => $auth['google_refresh_token'],
                            'expires_in' => $refreshResult['expires_in'],
                            'created' => time()
                        ]);
                    }
                }
            }
            
            $service = new Google_Service_Calendar($client);
            $service->events->delete('primary', $google_event_id);
            
            $conn->close();
            
            return ['success' => true, 'message' => 'ลบ Event สำเร็จ'];
            
        } catch (Exception $e) {
            error_log("Error deleting Google Calendar event: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

// ===== ฟังก์ชันสำหรับการจัดการ Login Integration =====

if (!function_exists('checkAndRefreshOnLogin')) {
    /**
     * ตรวจสอบและ refresh token เมื่อ user login
     * เรียกใช้ฟังก์ชันนี้หลังจาก user login สำเร็จ
     * @param int $user_id ID ของผู้ใช้
     * @return array ผลลัพธ์การตรวจสอบและ refresh
     */
    function checkAndRefreshOnLogin($user_id) {
        try {
            error_log("Checking Google Calendar token on login for user $user_id");
            
            // ใช้ฟังก์ชัน auto-refresh ที่สร้างไว้
            $autoRefreshResult = autoRefreshTokenOnLogin($user_id);
            
            // ตรวจสอบสถานะหลัง auto-refresh
            $statusResult = getGoogleAuthStatus($user_id);
            
            return [
                'success' => true,
                'auto_refresh' => $autoRefreshResult,
                'current_status' => $statusResult,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Error in checkAndRefreshOnLogin for user $user_id: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'auto_refresh' => [
                    'auto_refreshed' => false,
                    'reason' => 'error',
                    'message' => $e->getMessage()
                ]
            ];
        }
    }
}

if (!function_exists('handleAutoRefreshRequest')) {
    /**
     * จัดการ request สำหรับ auto-refresh จาก JavaScript
     * @param int $user_id ID ของผู้ใช้
     * @return array response สำหรับ JavaScript
     */
    function handleAutoRefreshRequest($user_id) {
        try {
            $result = autoRefreshTokenOnLogin($user_id);
            
            return [
                'status' => 'success',
                'data' => $result,
                'user_id' => $user_id,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Error in handleAutoRefreshRequest for user $user_id: " . $e->getMessage());
            
            return [
                'status' => 'error',
                'message' => 'เกิดข้อผิดพลาดในการ auto-refresh: ' . $e->getMessage(),
                'user_id' => $user_id,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
}

if (!function_exists('getTokenTimeRemaining')) {
    /**
     * คำนวณเวลาที่เหลือของ token
     * @param int $user_id ID ของผู้ใช้
     * @return array ข้อมูลเวลาที่เหลือ
     */
    function getTokenTimeRemaining($user_id) {
        try {
            $conn = connectMySQLi();
            
            $query = "
                SELECT 
                    token_expiry,
                    TIMESTAMPDIFF(SECOND, NOW(), token_expiry) as seconds_to_expiry,
                    TIMESTAMPDIFF(MINUTE, NOW(), token_expiry) as minutes_to_expiry,
                    TIMESTAMPDIFF(HOUR, NOW(), token_expiry) as hours_to_expiry
                FROM google_auth 
                WHERE user_id = ? AND is_active = 1
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $conn->close();
            
            if (!$result || !$result['token_expiry']) {
                return [
                    'has_token' => false,
                    'message' => 'ไม่พบ token หรือไม่มีวันหมดอายุ'
                ];
            }
            
            $seconds = $result['seconds_to_expiry'];
            $minutes = $result['minutes_to_expiry'];
            $hours = $result['hours_to_expiry'];
            
            return [
                'has_token' => true,
                'token_expiry' => $result['token_expiry'],
                'seconds_to_expiry' => $seconds,
                'minutes_to_expiry' => $minutes,
                'hours_to_expiry' => $hours,
                'is_expired' => $seconds <= 0,
                'is_expiring_soon' => $minutes <= 120, // 2 ชั่วโมง
                'formatted_time_remaining' => formatTimeRemaining($seconds)
            ];
            
        } catch (Exception $e) {
            error_log("Error getting token time remaining for user $user_id: " . $e->getMessage());
            return [
                'has_token' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

if (!function_exists('formatTimeRemaining')) {
    /**
     * แปลงวินาทีเป็นรูปแบบที่อ่านง่าย
     * @param int $seconds จำนวนวินาที
     * @return string เวลาในรูปแบบที่อ่านง่าย
     */
    function formatTimeRemaining($seconds) {
        if ($seconds <= 0) {
            return 'หมดอายุแล้ว';
        }
        
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $remainingSeconds = $seconds % 60;
        
        $parts = [];
        
        if ($hours > 0) {
            $parts[] = $hours . ' ชั่วโมง';
        }
        
        if ($minutes > 0) {
            $parts[] = $minutes . ' นาที';
        }
        
        if ($hours == 0 && $minutes < 5 && $remainingSeconds > 0) {
            $parts[] = $remainingSeconds . ' วินาที';
        }
        
        return implode(' ', $parts);
    }
}

// เรียกใช้ฟังก์ชันเริ่มต้นฐานข้อมูล
initializeGoogleCalendarDatabase();

// Log การโหลด Google Calendar Integration
error_log("Enhanced Google Calendar Integration with Auto-refresh loaded successfully - " . date('Y-m-d H:i:s'));

// เพิ่มฟังก์ชันสำหรับ Auto-send Class Sessions
if (!function_exists('sendMultipleClassSessionsToGoogle')) {
    /**
     * ส่ง Class Sessions หลายรายการไป Google Calendar พร้อมกัน
     * @param int $user_id ID ของผู้ใช้
     * @param array $sessions_data ข้อมูล Class Sessions
     * @return array ผลลัพธ์การส่ง
     */
    function sendMultipleClassSessionsToGoogle($user_id, $sessions_data) {
        try {
            $conn = connectMySQLi();
            
            // ตรวจสอบ Google Auth
            $query = "SELECT * FROM google_auth WHERE user_id = ? AND is_active = 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $auth = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$auth) {
                return [
                    'success' => false,
                    'error' => 'ไม่พบการเชื่อมต่อ Google Calendar สำหรับ user ' . $user_id
                ];
            }
            
            // ตรวจสอบและ refresh token ถ้าจำเป็น
            $minutes_to_expiry = null;
            if ($auth['token_expiry']) {
                $expiry_time = strtotime($auth['token_expiry']);
                $current_time = time();
                $minutes_to_expiry = ($expiry_time - $current_time) / 60;
            }
            
            $access_token = $auth['google_access_token'];
            
            if ($minutes_to_expiry !== null && $minutes_to_expiry <= 30) {
                if (!empty($auth['google_refresh_token'])) {
                    $refresh_result = refreshGoogleAccessTokenEnhanced($user_id, true);
                    if ($refresh_result['success']) {
                        $access_token = $refresh_result['access_token'];
                    } else {
                        return [
                            'success' => false,
                            'error' => 'ไม่สามารถ refresh token ได้: ' . $refresh_result['error']
                        ];
                    }
                } else {
                    return [
                        'success' => false,
                        'error' => 'Google token หมดอายุและไม่มี refresh token'
                    ];
                }
            }
            
            $sent_count = 0;
            $failed_count = 0;
            $errors = [];
            
            foreach ($sessions_data as $session) {
                try {
                    // สร้างข้อมูล event
                    $event_data = createEventDataFromSession($session);
                    
                    // ส่งไป Google Calendar
                    $result = sendSingleEventToGoogle($access_token, $event_data);
                    
                    if ($result['success']) {
                        $sent_count++;
                        
                        // อัปเดต session ด้วย Google Event ID
                        if (isset($session['session_id']) && !empty($result['google_event_id'])) {
                            updateSessionGoogleEventId($conn, $session['session_id'], $result['google_event_id'], $result['event_url']);
                        }
                        
                    } else {
                        $failed_count++;
                        $errors[] = "Session {$session['session_id']}: " . $result['error'];
                    }
                    
                    // หน่วงเวลาเล็กน้อยเพื่อหลีกเลี่ยง rate limit
                    usleep(200000); // 0.2 วินาที
                    
                } catch (Exception $e) {
                    $failed_count++;
                    $errors[] = "Session {$session['session_id']}: " . $e->getMessage();
                }
            }
            
            $conn->close();
            
            return [
                'success' => $sent_count > 0,
                'sent_count' => $sent_count,
                'failed_count' => $failed_count,
                'total_sessions' => count($sessions_data),
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            error_log("Error in sendMultipleClassSessionsToGoogle: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

if (!function_exists('sendSingleEventToGoogle')) {
    /**
     * ส่ง Event เดี่ยวไป Google Calendar
     * @param string $access_token Google Access Token
     * @param array $event_data ข้อมูล Event
     * @return array ผลลัพธ์การส่ง
     */
    function sendSingleEventToGoogle($access_token, $event_data) {
        try {
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
            
            return [
                'success' => true,
                'google_event_id' => $event_response['id'],
                'event_url' => $event_response['htmlLink'] ?? null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

if (!function_exists('updateSessionGoogleEventId')) {
    /**
     * อัปเดต Class Session ด้วย Google Event ID
     * @param mysqli $conn Database connection
     * @param int $session_id ID ของ Class Session
     * @param string $google_event_id Google Event ID
     * @param string $event_url Google Event URL
     * @return bool สำเร็จหรือไม่
     */
    function updateSessionGoogleEventId($conn, $session_id, $google_event_id, $event_url = null) {
        try {
            $update_sql = "UPDATE class_sessions 
                          SET google_event_id = ?, 
                              google_event_url = ?,
                              google_sync_status = 'synced',
                              google_sync_at = NOW(),
                              google_sync_error = NULL
                          WHERE session_id = ?";
            
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param('ssi', $google_event_id, $event_url, $session_id);
            $success = $stmt->execute();
            $stmt->close();
            
            return $success;
            
        } catch (Exception $e) {
            error_log("Error updating session Google Event ID: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('createEventDataFromSession')) {
    /**
     * สร้างข้อมูล Google Calendar Event จาก Class Session
     * @param array $session_data ข้อมูล Class Session
     * @return array ข้อมูล Event สำหรับ Google Calendar
     */
    function createEventDataFromSession($session_data) {
        // สร้าง datetime strings
        $start_datetime = $session_data['session_date'] . 'T' . $session_data['start_time'];
        $end_datetime = $session_data['session_date'] . 'T' . $session_data['end_time'];
        
        // แปลงเป็น RFC3339 format
        $start_rfc3339 = date('c', strtotime($start_datetime));
        $end_rfc3339 = date('c', strtotime($end_datetime));
        
        return [
            'summary' => "{$session_data['subject_code']} - {$session_data['subject_name']}",
            'description' => "การเรียนการสอน\nรายวิชา: {$session_data['subject_name']}\nอาจารย์: {$session_data['teacher_name']}\nชั้นปี: {$session_data['class_year']}\nบันทึกโดยระบบ: " . date('Y-m-d H:i:s'),
            'location' => "ห้อง {$session_data['room_number']}",
            'start' => [
                'dateTime' => $start_rfc3339,
                'timeZone' => 'Asia/Bangkok'
            ],
            'end' => [
                'dateTime' => $end_rfc3339,
                'timeZone' => 'Asia/Bangkok'
            ]
        ];
    }
}
?>