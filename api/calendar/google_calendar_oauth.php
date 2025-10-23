<?php
/**
 * Google OAuth Callback Handler - ปรับปรุงแล้ว
 * ไฟล์: /api/calendar/google_calendar_oauth.php
 * จัดการการเชื่อมต่อ Google Calendar
 */

// เพิ่มการ debug เพื่อดูปัญหา redirect
ini_set('display_errors', 1);
error_log("Google OAuth Callback started - " . date('Y-m-d H:i:s'));
error_log("REQUEST: " . json_encode($_REQUEST));

// เริ่ม session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("SESSION before start: " . json_encode(array_keys($_SESSION ?? [])));

// โหลด config และ Google Calendar Integration
$configPaths = [
    __DIR__ . '/../config.php'
];

$configLoaded = false;
foreach ($configPaths as $configPath) {
    if (file_exists($configPath)) {
        require_once $configPath;
        $configLoaded = true;
        error_log("Config loaded from: " . $configPath);
        break;
    }
}

if (!$configLoaded) {
    error_log("Config file not found in any of the expected paths");
    die('Configuration file not found');
}

// โหลด Google Calendar Integration
$integrationPaths = [
    __DIR__ . '/google_calendar_integration.php'
];

$integrationLoaded = false;
foreach ($integrationPaths as $integrationPath) {
    if (file_exists($integrationPath)) {
        require_once $integrationPath;
        $integrationLoaded = true;
        error_log("Google Calendar Integration loaded from: " . $integrationPath);
        break;
    }
}

if (!$integrationLoaded) {
    error_log("Google Calendar Integration file not found");
    die('Google Calendar Integration not found');
}

// Log ข้อมูล session เพื่อตรวจสอบ
error_log("SESSION DATA: " . json_encode(array_keys($_SESSION)));

// ตรวจสอบการเข้าสู่ระบบ
if (empty($_SESSION['user_id'])) {
    error_log("No user_id in session. Available session keys: " . json_encode(array_keys($_SESSION)));
    
    // ถ้าไม่มี user_id ใน session ให้ redirect ไปหน้า login
    header('Content-Type: text/html; charset=utf-8');
    echo '<script>
        if (window.opener) {
            window.opener.postMessage({
                type: "google_auth_error",
                data: {
                    error: "กรุณาเข้าสู่ระบบก่อนเชื่อมต่อ Google Calendar"
                }
            }, "*");
        }
        window.close();
    </script>';
    exit;
}

$user_id = $_SESSION['user_id'];
error_log("User ID from session: $user_id");

// ตรวจสอบ parameters และ log เพื่อ debug
$action = $_GET['action'] ?? '';
$code = $_GET['code'] ?? null;
$error = $_GET['error'] ?? null;
$state = $_GET['state'] ?? null;

error_log("OAuth Parameters - Action: $action, Code: " . ($code ? 'present' : 'not present') . 
          ", Error: $error, State: " . ($state ? 'present' : 'not present'));

// แสดงค่า GOOGLE_REDIRECT_URI เพื่อตรวจสอบ
if (defined('GOOGLE_REDIRECT_URI')) {
    error_log("GOOGLE_REDIRECT_URI: " . GOOGLE_REDIRECT_URI);
} else {
    error_log("GOOGLE_REDIRECT_URI not defined");
}

if ($action === 'start') {
    // เริ่มกระบวนการ OAuth
    startOAuthProcess();
} else if (isset($code)) {
    // ได้รับ authorization code จาก Google
    handleOAuthCallback();
} else if (isset($error)) {
    // เกิดข้อผิดพลาดในการยืนยันตัวตน
    handleOAuthError();
} else {
    // ไม่มี parameters ที่จำเป็น - เริ่มกระบวนการ OAuth
    error_log("No parameters, starting OAuth process");
    startOAuthProcess();
}

/**
 * เริ่มกระบวนการ OAuth
 */
function startOAuthProcess() {
    global $user_id;
    
    try {
        // ตรวจสอบว่า constants ถูกกำหนดแล้วหรือไม่
        if (!defined('GOOGLE_CLIENT_ID') || !defined('GOOGLE_CLIENT_SECRET') || !defined('GOOGLE_REDIRECT_URI')) {
            throw new Exception('Google OAuth constants not defined');
        }
        
        // สร้าง state parameter เพื่อความปลอดภัย
        $state = base64_encode(json_encode([
            'user_id' => $user_id,
            'timestamp' => time(),
            'random' => bin2hex(random_bytes(16))
        ]));
        
        // สร้าง OAuth URL
        $params = [
            'client_id' => GOOGLE_CLIENT_ID,
            'redirect_uri' => GOOGLE_REDIRECT_URI,
            'scope' => implode(' ', [
                'https://www.googleapis.com/auth/calendar',
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile'
            ]),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state
        ];
        
        $oauth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        
        // บันทึก state ใน session
        $_SESSION['oauth_state'] = $state;
        $_SESSION['oauth_user_id'] = $user_id; // เพิ่มการเก็บ user_id สำหรับความปลอดภัย
        
        // Log the OAuth URL for debugging
        error_log("Redirecting to OAuth URL: " . $oauth_url);
        
        // Redirect ไปยัง Google
        header('Location: ' . $oauth_url);
        exit;
        
    } catch (Exception $e) {
        error_log("OAuth Start Error: " . $e->getMessage());
        showError('เกิดข้อผิดพลาดในการเริ่มกระบวนการเชื่อมต่อ: ' . $e->getMessage());
    }
}

/**
 * จัดการ OAuth callback จาก Google
 */
function handleOAuthCallback() {
    global $user_id;
    
    try {
        $code = $_GET['code'];
        $state = $_GET['state'] ?? '';
        
        error_log("Handling OAuth callback - Code received, State: " . $state);
        
        // ตรวจสอบ state parameter
        if (!validateOAuthState($state)) {
            throw new Exception('State parameter ไม่ถูกต้อง - อาจมีการโจมตี CSRF');
        }
        
        // ลบ state จาก session
        unset($_SESSION['oauth_state']);
        unset($_SESSION['oauth_user_id']);
        
        // แลกเปลี่ยน authorization code กับ access token
        $tokenData = exchangeCodeForToken($code);
        
        if (!$tokenData) {
            error_log("Failed to exchange code for token");
            throw new Exception('ไม่สามารถได้รับ Access Token จาก Google');
        }
        
        error_log("Token received successfully");
        
        // ดึงข้อมูลผู้ใช้จาก Google
        $userInfo = getUserInfoFromGoogle($tokenData['access_token']);
        
        if (!$userInfo) {
            error_log("Failed to get user info from Google");
            throw new Exception('ไม่สามารถดึงข้อมูลผู้ใช้จาก Google');
        }
        
        error_log("User info received: " . json_encode($userInfo));
        
        // บันทึกข้อมูลลงฐานข้อมูล
        $saveResult = saveGoogleAuthData($user_id, $tokenData, $userInfo);
        
        if (!$saveResult) {
            error_log("Failed to save auth data");
            throw new Exception('ไม่สามารถบันทึกข้อมูลการยืนยันตัวตนได้');
        }
        
        // อัพเดท session ด้วยข้อมูล Google Auth
        updateSessionWithGoogleAuth($userInfo, $saveResult['operation']);
        
        // แสดงผลสำเร็จ
        showSuccess($userInfo, $saveResult['operation']);
        
    } catch (Exception $e) {
        error_log("OAuth Callback Error: " . $e->getMessage());
        showError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

/**
 * ตรวจสอบ OAuth state parameter
 */
function validateOAuthState($receivedState) {
    global $user_id;
    
    // เช็คว่ามี state ใน session หรือไม่
    if (!isset($_SESSION['oauth_state'])) {
        error_log("No oauth_state in session");
        return false;
    }
    
    // ตรวจสอบ state parameter
    if (empty($receivedState) || $receivedState !== $_SESSION['oauth_state']) {
        error_log("State mismatch - Received: $receivedState, Session: " . $_SESSION['oauth_state']);
        return false;
    }
    
    // ตรวจสอบ user_id ใน state
    try {
        $stateData = json_decode(base64_decode($receivedState), true);
        if (!$stateData || $stateData['user_id'] != $user_id) {
            error_log("User ID mismatch in state - Expected: $user_id, State: " . ($stateData['user_id'] ?? 'none'));
            return false;
        }
        
        // ตรวจสอบอายุของ state (ไม่เกิน 10 นาที)
        $stateAge = time() - $stateData['timestamp'];
        if ($stateAge > 600) {
            error_log("State too old: $stateAge seconds");
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error validating state: " . $e->getMessage());
        return false;
    }
}

/**
 * จัดการข้อผิดพลาดจาก Google OAuth
 */
function handleOAuthError() {
    $error = $_GET['error'] ?? 'unknown_error';
    $error_description = $_GET['error_description'] ?? '';
    
    error_log("OAuth Error: $error - $error_description");
    
    $error_messages = [
        'access_denied' => 'ผู้ใช้ยกเลิกการให้สิทธิ์',
        'invalid_request' => 'คำขอไม่ถูกต้อง - อาจเกิดจาก redirect URI ไม่ตรงกับที่ลงทะเบียนใน Google Console',
        'invalid_client' => 'Client ID ไม่ถูกต้องหรือ URL ที่ระบุไม่ตรงกับที่ลงทะเบียนไว้',
        'redirect_uri_mismatch' => 'URI ที่ระบุไม่ตรงกับที่ลงทะเบียนไว้ใน Google Console',
        'unsupported_response_type' => 'ประเภทการตอบกลับไม่ได้รับการสนับสนุน',
        'invalid_scope' => 'ขอบเขตการเข้าถึงไม่ถูกต้อง',
        'unauthorized_client' => 'ไคลเอ็นต์นี้ไม่ได้รับอนุญาตให้ใช้ OAuth2'
    ];
    
    $message = $error_messages[$error] ?? 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ';
    if ($error_description) {
        $message .= ': ' . $error_description;
    }
    
    showError($message);
}

/**
 * แลกเปลี่ยน authorization code กับ access token
 */
function exchangeCodeForToken($code) {
    error_log("Exchanging code for token");
    
    if (!defined('GOOGLE_CLIENT_ID') || !defined('GOOGLE_CLIENT_SECRET') || !defined('GOOGLE_REDIRECT_URI')) {
        error_log("Google OAuth constants not defined");
        return null;
    }
    
    $postData = [
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => GOOGLE_REDIRECT_URI
    ];
    
    // Log request data for debugging (without sensitive info)
    error_log("Token request data: " . json_encode(array_merge($postData, ['client_secret' => '[HIDDEN]'])));
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // สำหรับการทดสอบ
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    if ($curlError) {
        error_log("cURL Error: $curlError");
        curl_close($ch);
        return null;
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Token exchange failed. HTTP Code: $httpCode, Response: $response");
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        return null;
    }
    
    if (isset($data['error'])) {
        error_log("Token exchange error: " . $data['error'] . " - " . ($data['error_description'] ?? ''));
        return null;
    }
    
    error_log("Token exchange successful");
    return $data;
}

/**
 * ดึงข้อมูลผู้ใช้จาก Google
 */
function getUserInfoFromGoogle($accessToken) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // สำหรับการทดสอบ
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    if ($curlError) {
        error_log("User info cURL Error: $curlError");
        curl_close($ch);
        return null;
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("User info fetch failed. HTTP Code: $httpCode, Response: $response");
        return null;
    }
    
    $userInfo = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("User info JSON decode error: " . json_last_error_msg());
        return null;
    }
    
    return $userInfo;
}

/**
 * บันทึกข้อมูลการยืนยันตัวตน Google
 */
function saveGoogleAuthData($user_id, $tokenData, $userInfo) {
    try {
        // ใช้ฟังก์ชันจาก config.php
        if (function_exists('connectMySQLi')) {
            $conn = connectMySQLi();
        } else {
            throw new Exception('Database connection function not available');
        }
        
        $conn->begin_transaction();
        
        // สร้างตารางถ้ายังไม่มี
        createGoogleAuthTableIfNotExists($conn);
        
        $access_token = $tokenData['access_token'];
        $refresh_token = $tokenData['refresh_token'] ?? null;
        $id_token = $tokenData['id_token'] ?? null;
        $expires_in = $tokenData['expires_in'] ?? 3600;
        $token_expiry = date('Y-m-d H:i:s', time() + $expires_in);
        
        $google_email = $userInfo['email'] ?? '';
        $google_name = $userInfo['name'] ?? '';
        
        // ตรวจสอบว่ามีข้อมูลอยู่แล้วหรือไม่
        $checkQuery = "SELECT google_auth_id FROM google_auth WHERE user_id = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        
        if ($existing) {
            // อัพเดทข้อมูล
            $updateQuery = "
                UPDATE google_auth 
                SET google_access_token = ?, 
                    google_refresh_token = COALESCE(?, google_refresh_token),
                    google_id_token = ?,
                    token_expiry = ?, 
                    google_email = ?, 
                    google_name = ?,
                    is_active = 1,
                    updated_at = NOW()
                WHERE user_id = ?
            ";
            
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ssssssi", 
                $access_token, $refresh_token, $id_token, 
                $token_expiry, $google_email, $google_name, $user_id
            );
            
            $operation = 'อัพเดท';
        } else {
            // เพิ่มข้อมูลใหม่
            $insertQuery = "
                INSERT INTO google_auth 
                (user_id, google_access_token, google_refresh_token, google_id_token,
                 token_expiry, google_email, google_name, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ";
            
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("issssss", 
                $user_id, $access_token, $refresh_token, $id_token,
                $token_expiry, $google_email, $google_name
            );
            
            $operation = 'เพิ่ม';
        }
        
        if (!$stmt->execute()) {
            throw new Exception('ไม่สามารถบันทึกข้อมูลได้: ' . $conn->error);
        }
        
        $conn->commit();
        $conn->close();
        
        error_log("Successfully saved Google auth data for user $user_id ($operation)");
        
        return ['operation' => $operation, 'success' => true];
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
            $conn->close();
        }
        error_log("Save auth data error: " . $e->getMessage());
        return false;
    }
}

/**
 * สร้างตาราง google_auth ถ้ายังไม่มี
 */
function createGoogleAuthTableIfNotExists($conn) {
    $createTable = "CREATE TABLE IF NOT EXISTS google_auth (
        google_auth_id INT(11) PRIMARY KEY AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        google_access_token TEXT NOT NULL,
        google_refresh_token TEXT,
        google_id_token TEXT,
        token_expiry DATETIME,
        google_email VARCHAR(255),
        google_name VARCHAR(255),
        calendar_id VARCHAR(255) DEFAULT 'primary',
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (is_active),
        UNIQUE KEY unique_user_google (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($createTable)) {
        error_log("Error creating google_auth table: " . $conn->error);
    }
}

/**
 * อัพเดท session ด้วยข้อมูล Google Auth
 */
function updateSessionWithGoogleAuth($userInfo, $operation) {
    $_SESSION['google_auth_status'] = [
        'is_connected' => true,
        'status' => 'connected',
        'message' => 'เชื่อมต่อ Google Calendar สำเร็จ',
        'google_email' => $userInfo['email'] ?? '',
        'google_name' => $userInfo['name'] ?? '',
        'connected_at' => date('Y-m-d H:i:s'),
        'operation' => $operation
    ];
    
    error_log("Updated session with Google auth status");
}

/**
 * แสดงผลสำเร็จ
 */
function showSuccess($userInfo, $operation) {
    $google_email = htmlspecialchars($userInfo['email'] ?? 'ไม่ระบุ');
    $google_name = htmlspecialchars($userInfo['name'] ?? 'ไม่ระบุ');
    
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>เชื่อมต่อ Google Calendar สำเร็จ</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            .success-container {
                max-width: 500px;
                margin: 50px auto;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                background: #fff;
            }
            .success-icon {
                font-size: 4rem;
                color: #28a745;
                margin-bottom: 20px;
            }
            .info-item {
                margin-bottom: 10px;
                padding: 8px 12px;
                background: #f8f9fa;
                border-radius: 5px;
                border-left: 4px solid #007bff;
            }
        </style>
    </head>
    <body class="bg-light">
        <div class="container">
            <div class="success-container text-center">
                <div class="success-icon">✅</div>
                <h2 class="text-success mb-4">เชื่อมต่อ Google Calendar สำเร็จ!</h2>
                
                <div class="text-start mb-4">
                    <div class="info-item">
                        <strong>การดำเนินการ:</strong> ' . $operation . 'ข้อมูลการเชื่อมต่อ
                    </div>
                    <div class="info-item">
                        <strong>Google Email:</strong> ' . $google_email . '
                    </div>
                    <div class="info-item">
                        <strong>ชื่อผู้ใช้:</strong> ' . $google_name . '
                    </div>
                    <div class="info-item">
                        <strong>เวลา:</strong> ' . date('Y-m-d H:i:s') . '
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <small>
                        <strong>หมายเหตุ:</strong> ตอนนี้ระบบสามารถส่งตารางสอนและการชดเชยไปยัง Google Calendar ของคุณได้แล้ว
                    </small>
                </div>
                
                <button onclick="closeWindow()" class="btn btn-primary btn-lg">
                    ปิดหน้าต่างนี้
                </button>
            </div>
        </div>
        
        <script>
            function closeWindow() {
                // ส่งข้อความไปยังหน้าต่างหลัก
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({
                        type: "google_auth_success",
                        data: {
                            email: "' . addslashes($google_email) . '",
                            name: "' . addslashes($google_name) . '",
                            operation: "' . addslashes($operation) . '",
                            timestamp: "' . date('Y-m-d H:i:s') . '"
                        }
                    }, "*");
                    
                    // รอสักครู่แล้วปิดหน้าต่าง
                    setTimeout(function() {
                        window.close();
                    }, 500);
                } else {
                    // ถ้าไม่มี opener ให้ redirect กลับไปหน้าหลัก
                    window.location.href = "../../index.php";
                }
            }
            
            // ปิดหน้าต่างอัตโนมัติหลังจาก 10 วินาที
            setTimeout(function() {
                closeWindow();
            }, 10000);
            
            // เรียก closeWindow เมื่อโหลดหน้าเสร็จ
            window.addEventListener("load", function() {
                setTimeout(closeWindow, 2000);
            });
        </script>
    </body>
    </html>';
}

/**
 * แสดงข้อผิดพลาด
 */
function showError($message) {
    $error_message = htmlspecialchars($message);
    
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>เกิดข้อผิดพลาด</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            .error-container {
                max-width: 500px;
                margin: 50px auto;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                background: #fff;
            }
            .error-icon {
                font-size: 4rem;
                color: #dc3545;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body class="bg-light">
        <div class="container">
            <div class="error-container text-center">
                <div class="error-icon">❌</div>
                <h2 class="text-danger mb-4">เกิดข้อผิดพลาด</h2>
                
                <div class="alert alert-danger">
                    ' . $error_message . '
                </div>
                
                <div class="alert alert-warning mt-3">
                    <strong>คำแนะนำ:</strong> ตรวจสอบว่า Google Client ID และ Redirect URI ที่ใช้ถูกต้อง<br>
                    และได้ตั้งค่า Authorized redirect URIs ในหน้า Google API Console แล้ว
                </div>
                
                <button onclick="closeWindow()" class="btn btn-secondary">
                    ปิดหน้าต่างนี้
                </button>
            </div>
        </div>
        
        <script>
            function closeWindow() {
                // ส่งข้อความข้อผิดพลาดไปยังหน้าต่างหลัก
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({
                        type: "google_auth_error",
                        data: {
                            error: "' . addslashes($error_message) . '"
                        }
                    }, "*");
                    
                    setTimeout(function() {
                        window.close();
                    }, 500);
                } else {
                    window.location.href = "../../index.php";
                }
            }
            
            // ปิดหน้าต่างอัตโนมัติหลังจาก 15 วินาที
            setTimeout(function() {
                closeWindow();
            }, 15000);
            
            window.addEventListener("load", function() {
                setTimeout(closeWindow, 3000);
            });
        </script>
    </body>
    </html>';
}
?>