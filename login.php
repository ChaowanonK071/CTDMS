<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RMUTSV Teaching Schedule System - Login</title>
    <link rel="icon" href="../img/coe/CoE-LOGO.png" type="image/x-icon" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #333;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .logo p {
            color: #666;
            font-size: 14px;
        }

        .login-mode-selector {
            display: flex;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 5px;
            margin-bottom: 30px;
            border: 2px solid #e1e5e9;
        }

        .mode-option {
            flex: 1;
            padding: 12px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            user-select: none;
        }

        .mode-option.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .mode-option:not(.active) {
            color: #666;
        }

        .mode-option:not(.active):hover {
            background: #e9ecef;
        }

        .login-form {
            transition: all 0.3s ease;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .login-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .continue-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 15px;
        }

        .continue-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 184, 148, 0.3);
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .alert-error {
            background: #ffe6e6;
            color: #d63031;
            border: 1px solid #ff7675;
        }

        .alert-success {
            background: #e6ffe6;
            color: #00b894;
            border: 1px solid #00cec9;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alert-info {
            background: #e3f2fd;
            color: #0277bd;
            border: 1px solid #81d4fa;
        }

        .loading {
            display: none;
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }

        .spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .user-info {
            display: none;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .user-info h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .user-info p {
            color: #666;
            margin-bottom: 5px;
        }

        .logout-btn {
            background: #e17055;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 15px;
            margin-right: 10px;
        }

        .logout-btn:hover {
            background: #d63031;
        }

        .user-id-display {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-weight: bold;
        }

        .session-info {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            font-size: 12px;
        }

        .mode-description {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #495057;
        }

        .mode-description.elogin {
            background: #e3f2fd;
            border-color: #bbdefb;
        }

        .mode-description.admin {
            background: #fff3e0;
            border-color: #ffcc02;
        }

        .admin-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #856404;
        }

        .admin-warning strong {
            color: #d63031;
        }

        /* Enhanced Google Calendar Status Styles */
        .google-calendar-status {
            margin-top: 15px;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .google-calendar-status.not-connected {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .google-calendar-status.connected {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .google-calendar-status.expired {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .google-calendar-status.checking {
            background: #e3f2fd;
            border: 1px solid #81d4fa;
            color: #0277bd;
        }

        .google-calendar-status.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .google-btn {
            background: #db4437;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 8px;
            margin-right: 8px;
            transition: all 0.3s ease;
        }

        .google-btn:hover {
            background: #c23321;
            transform: translateY(-1px);
        }

        .google-btn.small {
            padding: 4px 8px;
            font-size: 11px;
        }

        .google-btn.refresh {
            background: #667eea;
        }

        .google-btn.refresh:hover {
            background: #5a67d8;
        }

        .token-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 8px;
        }

        .token-status.valid {
            background: #d4edda;
            color: #155724;
        }

        .token-status.expiring {
            background: #fff3cd;
            color: #856404;
        }

        .token-status.expired {
            background: #f8d7da;
            color: #721c24;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 500px;
            padding: 15px 20px;
            border-radius: 8px;
            font-size: 14px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            transform: translateX(100%);
            transition: transform 0.3s ease-in-out;
            word-wrap: break-word;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            background: #d4edda;
            border-left: 4px solid #c3e6cb;
            color: #155724;
        }

        .notification.error {
            background: #f8d7da;
            border-left: 4px solid #f5c6cb;
            color: #721c24;
        }

        .notification.warning {
            background: #fff3cd;
            border-left: 4px solid #ffeaa7;
            color: #856404;
        }

        .notification.info {
            background: #e3f2fd;
            border-left: 4px solid #81d4fa;
            color: #0277bd;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>RMUTSV</h1>
            <p>ระบบตารางสอนประจำภาคเรียน</p>
        </div>

        <!-- Login Mode Selector -->
        <div class="login-mode-selector">
            <div class="mode-option active" data-mode="elogin">
                eLogin
            </div>
            <div class="mode-option" data-mode="admin">
                Admin
            </div>
        </div>

        <div id="alert" class="alert"></div>

        <!-- eLogin Form -->
        <div id="eloginForm" class="login-form">
            <div class="mode-description elogin">
                <strong>eLogin Mode:</strong> เข้าสู่ระบบด้วยบัญชี ePassport ของมหาวิทยาลัย สำหรับอาจารย์และบุคลากร
            </div>

            <form id="eloginFormElement">
                <div class="form-group">
                    <label for="elogin_username">ePassport Username:</label>
                    <input type="text" id="elogin_username" name="username" required placeholder="กรุณาใส่ ePassport Username">
                </div>

                <div class="form-group">
                    <label for="elogin_password">ePassport Password:</label>
                    <input type="password" id="elogin_password" name="password" required placeholder="กรุณาใส่ ePassport Password">
                </div>

                <button type="submit" class="login-btn" id="eloginBtn">
                    เข้าสู่ระบบด้วย eLogin
                </button>
            </form>
        </div>

        <!-- Admin Form -->
        <div id="adminForm" class="login-form" style="display: none;">
            <div class="mode-description admin">
                <strong>Admin Mode:</strong> เข้าสู่ระบบด้วยบัญชีผู้ดูแลระบบที่เก็บในฐานข้อมูล
            </div>

            <div class="admin-warning">
                <strong>คำเตือน:</strong> โหมดนี้สำหรับผู้ดูแลระบบเท่านั้น กรุณาใช้ username และ password ที่ถูกต้อง
            </div>

            <form id="adminFormElement">
                <div class="form-group">
                    <label for="admin_username">Admin Username:</label>
                    <input type="text" id="admin_username" name="username" required placeholder="ชื่อผู้ใช้ระบบ">
                </div>

                <div class="form-group">
                    <label for="admin_password">Admin Password:</label>
                    <input type="password" id="admin_password" name="password" required placeholder="รหัสผ่านระบบ">
                </div>

                <button type="submit" class="login-btn" id="adminBtn">
                    เข้าสู่ระบบด้วยสิทธิ์ Admin
                </button>
            </form>
        </div>

        <div class="loading" id="loading">
            <span class="spinner"></span>
            กำลังตรวจสอบข้อมูล...
        </div>

        <div class="user-info" id="userInfo">
            <h3>ข้อมูลผู้ใช้</h3>
            <div id="userDetails"></div>
            <div id="userIdDisplay" class="user-id-display"></div>
            
            <!-- Enhanced Google Calendar Status -->
            <div id="googleCalendarStatus" class="google-calendar-status" style="display: none;">
                <div id="googleStatusContent"></div>
            </div>
            
            <div id="sessionInfo" class="session-info"></div>
            
            <button class="logout-btn" onclick="logout()">ออกจากระบบ</button>
            <button class="continue-btn" onclick="continueToSystem()">เข้าสู่ระบบตารางสอน</button>
        </div>
    </div>

    <script>
// ===== Enhanced Configuration =====
const ELOGIN_API_URL = '../api/elogin_auth.php';
const ADMIN_LOGIN_API_URL = './api/admin_login.php';
const LOGIN_PROCESS_URL = '../api/login_process.php';
const GOOGLE_CALENDAR_CHECK_URL = '../api/calendar/google_calendar_check.php';
const TOKEN_REFRESH_URL = '../api/calendar/token_refresh.php';
const SCHEDULE_PAGE_URL = '../index.php';

// ===== Enhanced Google Calendar Token Manager with Auto-refresh =====
class GoogleCalendarTokenManager {
    constructor() {
        this.apiBaseUrl = '../api/calendar';
        this.checkInterval = null;
        this.isInitialized = false;
        this.lastRefreshTime = null;
        this.refreshInProgress = false;
        this.autoRefreshOnLoginEnabled = true;
    }

    // Safe API call with comprehensive error handling
    async safeFetch(url, options = {}) {
        const defaultOptions = {
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        };

        const mergedOptions = { ...defaultOptions, ...options };

        try {
            
            const response = await fetch(url, mergedOptions);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const textContent = await response.text();
                console.warn(`Non-JSON response from ${url}:`, textContent);
                
                const jsonMatch = textContent.match(/\{.*\}/);
                if (jsonMatch) {
                    return JSON.parse(jsonMatch[0]);
                }
                
                throw new Error(`Invalid response type: ${contentType}. Expected JSON.`);
            }

            const responseText = await response.text();
            
            if (!responseText.trim()) {
                throw new Error('Empty response body');
            }

            const data = JSON.parse(responseText);
            return data;

        } catch (error) {
            console.error(`API Error for ${url}:`, error);
            
            return {
                status: 'error',
                message: error.message,
                has_google_auth: false,
                action_required: 'check_error'
            };
        }
    }

    // Initialize and test API connectivity
    async initialize() {
        if (this.isInitialized) return true;

        try {
            const testResult = await this.safeFetch(GOOGLE_CALENDAR_CHECK_URL);
        } catch (error) {
            console.error('Failed to initialize Google Calendar Token Manager:', error);
            return false;
        }
    }

    // ตรวจสอบสถานะ Token
    async checkTokenStatus() {
        try {
            if (!this.isInitialized) {
                const initSuccess = await this.initialize();
                if (!initSuccess) {
                    return {
                        status: 'api_unavailable',
                        message: 'Google Calendar API ไม่สามารถเข้าถึงได้'
                    };
                }
            }

            const data = await this.safeFetch(`${this.apiBaseUrl}/token_refresh.php?action=status`);
            
            if (data.status === "success") {
                return data.data;
            } else {
                return {
                    status: 'error',
                    message: data.message || 'ไม่สามารถตรวจสอบสถานะ Token ได้'
                };
            }
        } catch (error) {
            console.error("Error checking token status:", error);
            return { 
                status: 'error', 
                message: 'เกิดข้อผิดพลาดในการตรวจสอบ Token: ' + error.message 
            };
        }
    }

    // Enhanced Refresh Token (Fixed version)
    async refreshToken(force = true) {
        if (this.refreshInProgress) {
            
            let attempts = 0;
            while (this.refreshInProgress && attempts < 30) {
                await new Promise(resolve => setTimeout(resolve, 1000));
                attempts++;
            }
            
            if (this.refreshInProgress) {
                throw new Error('การ refresh token ใช้เวลานานเกินไป');
            }
            
            if (this.lastRefreshTime && Date.now() - this.lastRefreshTime < 5000) {
                return { success: true, message: 'Token ถูก refresh เรียบร้อยแล้ว' };
            }
        }

        this.refreshInProgress = true;
        
        try {
            
            const data = await this.safeFetch(`${this.apiBaseUrl}/token_refresh.php`, {
                method: "POST",
                body: JSON.stringify({ 
                    action: "refresh",
                    force: force 
                })
            });
            
            if (data.status === "success") {
                this.lastRefreshTime = Date.now();
                
                if (data.data && data.data.success) {
                    return {
                        success: true,
                        message: data.data.message || 'Refresh Token สำเร็จ',
                        data: data.data
                    };
                } else {
                    throw new Error(data.data ? data.data.error : 'การ refresh ไม่สำเร็จ');
                }
            } else {
                if (data.requires_reauth) {
                    throw new Error('ต้องเชื่อมต่อ Google Calendar ใหม่: ' + (data.message || 'Token หมดอายุ'));
                } else {
                    throw new Error(data.message || 'Refresh ไม่สำเร็จ');
                }
            }
        } catch (error) {
            console.error("Error refreshing token:", error);
            throw error;
        } finally {
            this.refreshInProgress = false;
        }
    }

    // ฟังก์ชัน Auto-refresh เมื่อ login (ใหม่)
    async autoRefreshOnLogin() {
        try {            
            const data = await this.safeFetch(`${this.apiBaseUrl}/token_refresh.php?action=auto_refresh_on_login`);
            
            if (data.status === "success") {
                const autoRefreshData = data.data;
                
                if (autoRefreshData.auto_refreshed) {
                    const refreshResult = autoRefreshData.refresh_result;
                    if (refreshResult && refreshResult.success) {
                        showNotification(
                            `Auto-refresh สำเร็จ!\n${refreshResult.message}`,
                            "success"
                        );
                        return {
                            success: true,
                            refreshed: true,
                            message: refreshResult.message
                        };
                    } else {
                        showNotification(
                            `Auto-refresh ล้มเหลว: ${refreshResult ? refreshResult.error : 'Unknown error'}`,
                            "warning"
                        );
                        return {
                            success: false,
                            refreshed: true,
                            error: refreshResult ? refreshResult.error : 'Unknown error'
                        };
                    }
                } else {
                    // ไม่ต้อง refresh
                    let message = autoRefreshData.message;
                    if (autoRefreshData.reason === 'no_google_auth') {
                        return { success: true, refreshed: false, reason: 'no_google_auth' };
                    } else if (autoRefreshData.reason === 'token_still_valid') {
                        showNotification(
                            `Google Calendar Token ยังใช้งานได้ (เหลืออีก ${autoRefreshData.time_to_expiry_hours} ชั่วโมง)`,
                            "info"
                        );
                        return { 
                            success: true, 
                            refreshed: false, 
                            reason: 'token_still_valid',
                            time_remaining: autoRefreshData.time_to_expiry_hours
                        };
                    }
                    
                    return { success: true, refreshed: false, message: message };
                }
            } else {
                throw new Error(data.message || 'Auto-refresh ล้มเหลว');
            }
        } catch (error) {
            console.error("Error in auto-refresh on login:", error);
            showNotification(`Auto-refresh error: ${error.message}`, "error");
            return { success: false, error: error.message };
        }
    }

    // Refresh Token พร้อมแสดงแจ้งเตือน (Enhanced version)
    async refreshTokenWithNotification() {
        try {
            showNotification("กำลัง Refresh Google Calendar Token...", "info");
            
            const result = await this.refreshToken(true); // Force refresh
            
            if (result.success) {
                let message = "Refresh Token สำเร็จ!";
                if (result.data) {
                    if (result.data.skipped_refresh) {
                        message = `Token ยังใช้งานได้ (เหลืออีก ${result.data.minutes_remaining} นาที)`;
                        showNotification(message, "info");
                    } else if (result.data.refreshed || result.data.hours_valid) {
                        if (result.data.hours_valid) {
                            message += `\nToken ใหม่ใช้งานได้อีก ${result.data.hours_valid} ชั่วโมง`;
                        }
                        showNotification(message, "success");
                        
                        // แสดงข้อความเพิ่มเติมสำหรับการ refresh สำเร็จ
                        setTimeout(() => {
                            showNotification("Token ใหม่พร้อมใช้งาน! การซิงค์ตารางสอนจะทำงานปกติ", "success");
                        }, 2000);
                    } else {
                        showNotification(message, "success");
                    }
                } else {
                    showNotification(message, "success");
                }
                
                // ทดสอบ Token ที่ refresh แล้ว - Fixed validateToken call
                setTimeout(async () => {
                    try {
                        const validation = await this.validateToken();
                        if (validation && validation.success) {
                            showNotification("Token ผ่านการตรวจสอบเรียบร้อย", "success");
                        }
                    } catch (error) {
                        console.warn("Token validation after refresh failed:", error);
                    }
                }, 3000);
                
                // อัพเดทสถานะใน UI
                await this.updateGoogleCalendarStatus();
                
            } else {
                throw new Error(result.message || 'Refresh ไม่สำเร็จ');
            }
        } catch (error) {
            console.error("Refresh with notification failed:", error);
            
            if (error.message.includes('ต้องเชื่อมต่อ') || 
                error.message.includes('หมดอายุ') || 
                error.message.includes('invalid_grant')) {
                    
                showNotification(`${error.message}\nต้องเชื่อมต่อ Google Calendar ใหม่`, "error");
                
                setTimeout(() => {
                    displayGoogleCalendarStatus({
                        status: 'token_expired_need_reconnect',
                        message: 'Google Calendar Token หมดอายุ - ต้องเชื่อมต่อใหม่',
                        has_google_auth: true,
                        can_refresh: false,
                        action_required: 'connect'
                    });
                }, 2000);
                
                setTimeout(() => {
                    try { connectGoogleCalendar(); } catch (e) { console.warn('Auto-connect failed', e); }
                }, 3500);
            } else {
                showNotification(`Refresh Token ไม่สำเร็จ: ${error.message}`, "error");
            }
        }
    }

    // ตรวจสอบและซ่อมแซม Token (Fixed version)
    async validateToken() {
        try {
            const data = await this.safeFetch(`${this.apiBaseUrl}/token_refresh.php?action=validate`);
            
            if (data.status === "success") {
                return data.data;
            } else {
                throw new Error(data.message || 'การตรวจสอบ Token ไม่สำเร็จ');
            }
        } catch (error) {
            console.error("Error validating token:", error);
            throw error;
        }
    }

    // เริ่มการตรวจสอบ Token อัตโนมัติ
    startAutoCheck() {
        if (!this.isInitialized) {
            console.warn('Cannot start auto check - Token Manager not initialized');
            return;
        }

        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }

        // ตรวจสอบทันทีหลังจากเริ่ม
        this.checkAndNotifyUser();

        this.checkInterval = setInterval(async () => {
            try {
                await this.checkAndNotifyUser();
            } catch (error) {
                console.error("Auto check error:", error);
            }
        }, 30 * 60 * 1000);

    }

    // หยุดการตรวจสอบอัตโนมัติ
    stopAutoCheck() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }
    }

    // ตรวจสอบ Token และแสดงแจ้งเตือนถ้าจำเป็น
    async checkAndNotifyUser() {
        try {
            const status = await this.checkTokenStatus();
            
            if (status.status === 'error' || status.status === 'api_unavailable') {
                console.warn('Token check failed:', status.message);
                return;
            }
            
            switch (status.status) {
                case "expiring_soon":
                    showNotification(
                        `Google Calendar Token จะหมดอายุเร็วๆ นี้ (เหลืออีก ${status.minutes_to_expiry} นาที)`,
                        "warning"
                    );
                    break;
                    
                case "expired":
                    showNotification(
                        "Google Calendar Token หมดอายุแล้ว - สามารถ Refresh ได้",
                        "warning"
                    );
                    break;
            }
            
            await this.updateGoogleCalendarStatus();
            
            return status;
            
        } catch (error) {
            console.error("Error checking token:", error);
        }
    }

    // อัพเดทสถานะ Google Calendar ใน UI
    async updateGoogleCalendarStatus() {
        try {
            const data = await this.safeFetch(GOOGLE_CALENDAR_CHECK_URL);
            displayGoogleCalendarStatus(data);
        } catch (error) {
            console.error("Error updating Google Calendar status:", error);
            displayGoogleCalendarStatus({
                status: 'error',
                message: 'ไม่สามารถตรวจสอบสถานะได้: ' + error.message,
                has_google_auth: false
            });
        }
    }

    // ทดสอบการเชื่อมต่อ
    async testConnection() {
        try {
            const data = await this.safeFetch(`${this.apiBaseUrl}/token_refresh.php`, {
                method: "POST",
                body: JSON.stringify({ action: "test_api" })
            });
            
            if (data.status === "success") {
                return { success: true, data: data };
            } else {
                throw new Error(data.message || 'การทดสอบเชื่อมต่อไม่สำเร็จ');
            }
        } catch (error) {
            console.error("Connection test failed:", error);
            return { success: false, error: error.message };
        }
    }

    // ทดสอบการเชื่อมต่อพร้อมแสดงผล
    async testConnectionWithNotification() {
        try {
            showNotification("กำลังทดสอบการเชื่อมต่อ Google Calendar...", "info");
            
            const result = await this.testConnection();
            
            if (result.success) {
                const testData = result.data;
                let message = "การเชื่อมต่อ Google Calendar ทำงานปกติ";
                
                if (testData.connectivity_test) {
                    const tests = testData.connectivity_test;
                    const testResults = [];
                    if (tests.database) testResults.push("ฐานข้อมูล");
                    if (tests.google_oauth) testResults.push("Google OAuth");
                    if (tests.user_auth) testResults.push("การยืนยันตัวตน");
                    
                    if (testResults.length > 0) {
                        message += `\n${testResults.join(", ")}`;
                    }
                }
                
                showNotification(message, "success");
                await this.updateGoogleCalendarStatus();
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            showNotification(`การทดสอบเชื่อมต่อไม่สำเร็จ: ${error.message}`, "error");
        }
    }
}

// สร้าง instance ของ Token Manager
const tokenManager = new GoogleCalendarTokenManager();

// ===== UI Elements =====
const modeOptions = document.querySelectorAll('.mode-option');
const eloginForm = document.getElementById('eloginForm');
const adminForm = document.getElementById('adminForm');
const eloginFormElement = document.getElementById('eloginFormElement');
const adminFormElement = document.getElementById('adminFormElement');
const loading = document.getElementById('loading');
const alert = document.getElementById('alert');
const userInfo = document.getElementById('userInfo');
const userDetails = document.getElementById('userDetails');
const userIdDisplay = document.getElementById('userIdDisplay');
const sessionInfo = document.getElementById('sessionInfo');
const googleCalendarStatus = document.getElementById('googleCalendarStatus');
const googleStatusContent = document.getElementById('googleStatusContent');

let currentMode = 'elogin';

// ===== Enhanced Notification System =====
function showNotification(message, type = "info") {
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    const notification = document.createElement("div");
    notification.className = `notification ${type}`;
    
    const icons = {
        success: '',
        error: '',
        warning: '',
        info: ''
    };
    
    notification.innerHTML = `
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center;">
                <span style="font-size: 18px; margin-right: 10px;">${icons[type] || icons.info}</span>
                <span style="white-space: pre-line;">${message}</span>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" 
                    style="background: none; border: none; font-size: 18px; cursor: pointer; margin-left: 15px;">
                ×
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }
    }, 7000);
}

// ===== Enhanced Google Calendar Functions =====
async function checkGoogleCalendarStatus() {
    try {
        displayGoogleCalendarStatus({
            status: 'checking',
            message: 'กำลังตรวจสอบสถานะ Google Calendar...'
        });

        const data = await tokenManager.safeFetch(GOOGLE_CALENDAR_CHECK_URL);
        
        // ถ้าเชื่อมต่อแล้ว ให้ตรวจสอบ token status เพิ่มเติม
        if (data.has_google_auth && data.status !== 'error') {
            const tokenStatus = await tokenManager.checkTokenStatus();
            if (tokenStatus.status !== 'error') {
                data.token_details = tokenStatus;
            }
        }
        
        displayGoogleCalendarStatus(data);
        
    } catch (error) {
        console.error('Error checking Google Calendar status:', error);
        displayGoogleCalendarStatus({
            has_google_auth: false,
            status: 'error',
            message: 'ไม่สามารถตรวจสอบสถานะ Google Calendar ได้: ' + error.message
        });
    }
}

// ===== Fixed Display Function for Google Calendar Status =====
function displayGoogleCalendarStatus(status) {
    let statusClass = 'not-connected';
    let statusIcon = '';
    let statusMessage = status.message || 'ไม่ทราบสถานะ';
    let actionButtons = '';
    let tokenStatusBadge = '';

    if (status.status === 'checking') {
        statusClass = 'checking';
        statusIcon = '';
        actionButtons = '<div class="spinner" style="width: 12px; height: 12px; margin-top: 8px;"></div>';
    } else if (status.status === 'error') {
        statusClass = 'error';
        statusIcon = '';
        statusMessage = `เกิดข้อผิดพลาด: ${status.message}`;
        actionButtons = `
            <button class="google-btn small" onclick="checkGoogleCalendarStatus()">
                ลองใหม่
            </button>`;
    } else if (status.has_google_auth) {
        // มีการเชื่อมต่อ Google Calendar แล้ว
        
        if (status.status === 'connected' || status.action_required === 'none') {
            // Token ใช้งานได้ปกติ
            statusClass = 'connected';
            statusIcon = '';
            statusMessage = status.message || 'Google Calendar เชื่อมต่อแล้ว';
            
            if (status.google_email) {
                statusMessage += ` (${status.google_email})`;
            }

            // เพิ่ม Token status badge
            if (status.token_details || status.minutes_to_expiry !== undefined) {
                let badgeClass = 'valid';
                let badgeText = 'ใช้งานได้';

                if (status.expires_soon || (status.minutes_to_expiry !== null && status.minutes_to_expiry <= 120)) {
                    badgeClass = 'expiring';
                    badgeText = `หมดอายุใน ${status.minutes_to_expiry} นาที`;
                }

                tokenStatusBadge = `<span class="token-status ${badgeClass}">${badgeText}</span>`;
            }

            actionButtons = `
                <button class="google-btn small refresh" onclick="tokenManager.refreshTokenWithNotification()">
                    Refresh Token
                </button>
                <button class="google-btn small" onclick="checkGoogleCalendarStatus()">
                    ตรวจสอบสถานะ
                </button>`;

        } else if (status.status === 'token_expired_can_refresh' || 
                   status.action_required === 'refresh' || 
                   status.can_refresh === true) {
            // Token หมดอายุแต่สามารถ Refresh ได้
            statusClass = 'expired-refreshable';
            statusIcon = '';
            statusMessage = status.message || 'Google Calendar Token หมดอายุ - สามารถ Refresh ได้';
            
            if (status.google_email) {
                statusMessage += ` (${status.google_email})`;
            }

            tokenStatusBadge = `<span class="token-status expired">หมดอายุ - สามารถ Refresh ได้</span>`;

            actionButtons = `
                <button class="google-btn refresh primary" onclick="tokenManager.refreshTokenWithNotification()">
                    Refresh Token
                </button>
                <button class="google-btn" onclick="connectGoogleCalendar()">
                    เชื่อมต่อใหม่
                </button>`;

        } else if (status.status === 'token_expired_need_reconnect' || 
                   status.can_refresh === false) {
            // Token หมดอายุและไม่สามารถ Refresh ได้ - ต้องเชื่อมต่อใหม่
            statusClass = 'expired';
            statusIcon = '';
            statusMessage = status.message || 'Google Calendar Token หมดอายุ - ต้องเชื่อมต่อใหม่';

            tokenStatusBadge = `<span class="token-status expired">หมดอายุ - ต้องเชื่อมต่อใหม่</span>`;

            actionButtons = `
                <button class="google-btn primary" onclick="connectGoogleCalendar()">
                    เชื่อมต่อ Google Calendar ใหม่
                </button>`;
        } else {
            // สถานะอื่นๆ ที่มีการเชื่อมต่อแต่ไม่ทราบสถานะชัดเจน
            statusClass = 'connected';
            statusIcon = '';
            statusMessage = status.message || 'Google Calendar เชื่อมต่อแล้ว - สถานะไม่ชัดเจน';
            
            actionButtons = `
                <button class="google-btn small refresh" onclick="tokenManager.refreshTokenWithNotification()">
                    Refresh Token
                </button>
                <button class="google-btn small" onclick="checkGoogleCalendarStatus()">
                    ตรวจสอบสถานะ
                </button>`;
        }
    } else {
        // ยังไม่ได้เชื่อมต่อ Google Calendar
        statusMessage = status.message || 'ยังไม่ได้เชื่อมต่อ Google Calendar';
        actionButtons = `
            <button class="google-btn primary" onclick="connectGoogleCalendar()">
                เชื่อมต่อ Google Calendar
            </button>`;
    }

    // แสดงผลใน UI
    const googleStatusContent = document.getElementById('googleStatusContent');
    if (googleStatusContent) {
        googleStatusContent.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    <strong>${statusIcon} Google Calendar ${tokenStatusBadge}</strong><br>
                    <small>${statusMessage}</small>
                </div>
                <div style="text-align: right; margin-top: 4px;">
                    ${actionButtons}
                </div>
            </div>
        `;
    }

    const googleCalendarStatus = document.getElementById('googleCalendarStatus');
    if (googleCalendarStatus) {
        googleCalendarStatus.className = `google-calendar-status ${statusClass}`;
        googleCalendarStatus.style.display = 'block';
    }

}

function connectGoogleCalendar() {
    const width = 600;
    const height = 700;
    const left = (screen.width / 2) - (width / 2);
    const top = (screen.height / 2) - (height / 2);
    
    let popup = null;
    let checkClosedInterval = null;
    let messageListenerAdded = false;
    
    try {
        popup = window.open(
            "/api/calendar/google_calendar_oauth.php?action=start",
            "google_oauth",
            `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`
        );
        
        if (!popup) {
            showNotification("กรุณาอนุญาตให้เปิด popup window", "error");
            return;
        }
        
        showNotification("กำลังเปิดหน้าต่างเชื่อมต่อ Google Calendar...", "info");
        
        function checkIfPopupClosed() {
            try {
                if (popup && popup.closed) {
                    cleanupPopupHandlers();
                    showNotification("การเชื่อมต่อถูกยกเลิก", "warning");
                    return true;
                }
                return false;
            } catch (error) {
                console.log("Cannot check popup.closed due to CORS policy");
                return false;
            }
        }
        
        checkClosedInterval = setInterval(() => {
            checkIfPopupClosed();
        }, 1000);
        
        const popupTimeout = setTimeout(() => {
            if (popup && !popup.closed) {
                try {
                    popup.close();
                } catch (e) {
                }
            }
            cleanupPopupHandlers();
            showNotification("การเชื่อมต่อใช้เวลานานเกินไป", "warning");
        }, 300000);
        
        function handlePopupMessage(event) {
            const allowedOrigins = [
                window.location.origin,
                'http://localhost',
                'https://localhost'
            ];
            
            if (!allowedOrigins.some(origin => event.origin === origin || event.origin.startsWith(origin))) {
                console.log("Message from unauthorized origin:", event.origin);
                return;
            }
            
            if (event.data && event.data.type) {
                if (event.data.type === "google_auth_success") {
                    
                    cleanupPopupHandlers();
                    clearTimeout(popupTimeout);
                    
                    try {
                        if (popup && !popup.closed) {
                            popup.close();
                        }
                    } catch (e) {
                        console.log("Cannot close popup after success");
                    }
                    
                    showNotification("เชื่อมต่อ Google Calendar สำเร็จ!", "success");
                    
                    // เริ่ม auto check และ update status
                    tokenManager.startAutoCheck();
                    setTimeout(() => {
                        checkGoogleCalendarStatus();
                    }, 1000);
                    
                } else if (event.data.type === "google_auth_error") {
                    console.log("Google Auth Error:", event.data);
                    
                    cleanupPopupHandlers();
                    clearTimeout(popupTimeout); 
                    
                    try {
                        if (popup && !popup.closed) {
                            popup.close();
                        }
                    } catch (e) {
                        console.log("Cannot close popup after error");
                    }
                    
                    const errorMessage = event.data.data && event.data.data.error 
                        ? event.data.data.error 
                        : "เกิดข้อผิดพลาดในการเชื่อมต่อ";
                    showNotification("เกิดข้อผิดพลาด: " + errorMessage, "error");
                }
            }
        }
        
        if (!messageListenerAdded) {
            window.addEventListener("message", handlePopupMessage);
            messageListenerAdded = true;
        }
        
        function cleanupPopupHandlers() {
            if (checkClosedInterval) {
                clearInterval(checkClosedInterval);
                checkClosedInterval = null;
            }
            
            if (messageListenerAdded) {
                window.removeEventListener("message", handlePopupMessage);
                messageListenerAdded = false;
            }
            
            popup = null;
        }
        
    } catch (error) {
        console.error("Error opening Google OAuth popup:", error);
        showNotification("ไม่สามารถเปิดหน้าต่างเชื่อมต่อได้", "error");
    }
}

// ===== Mode Selector Events =====
modeOptions.forEach(option => {
    option.addEventListener('click', () => {
        const mode = option.dataset.mode;
        switchMode(mode);
    });
});

function switchMode(mode) {
    currentMode = mode;
    
    // Update active state
    modeOptions.forEach(opt => {
        opt.classList.toggle('active', opt.dataset.mode === mode);
    });

    // Show/hide forms
    if (mode === 'elogin') {
        eloginForm.style.display = 'block';
        adminForm.style.display = 'none';
    } else {
        eloginForm.style.display = 'none';
        adminForm.style.display = 'block';
    }

    // Clear alert
    alert.style.display = 'none';
}

// ===== Alert Functions =====
function showAlert(message, type = 'error') {
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    alert.style.display = 'block';
    
    setTimeout(() => {
        alert.style.display = 'none';
    }, 5000);
}

// ===== Login Functions =====
async function loginWithElogin(username, password) {
    try {
        const response = await fetch(ELOGIN_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                username: username,
                password: password
            }),
            // Add timeout and retry logic
            timeout: 30000
        });

        const data = await response.json();
        if (data.status === 'ok') {
            const userDataForSession = {
                ...data,
                user_id: data.user_id || null,
                username: data.username || username,
                name: data.name || '',
                title: data.title || '',
                email: data.email || '',
                facname: data.facname || '',
                depname: data.depname || '',
                secname: data.secname || '',
                faccode: data.faccode || '',
                depcode: data.depcode || '',
                seccode: data.seccode || '',
                cid: data.cid || '',
                token: data.token || '',
                type: data.type || 'staff',
                database_saved: data.database_saved || false,
                login_method: 'elogin'
            };

            const sessionResponse = await fetch(LOGIN_PROCESS_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({
                    user_data: userDataForSession
                })
            });

            const sessionData = await sessionResponse.json();

            if (sessionData.status === 'success') {
                return {
                    success: true,
                    data: userDataForSession,
                    session_data: sessionData.user_data
                };
            } else {
                return {
                    success: false,
                    message: 'ไม่สามารถสร้าง session ได้: ' + (sessionData.message || 'Unknown error')
                };
            }
        } else {
            // Enhanced error handling for specific error codes
            let errorMessage = data.message || 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ';
            
            if (data.message && data.message.includes('502')) {
                errorMessage = 'ระบบ eLogin ของมหาวิทยาลัยขัดข้อง (Error 502)\n' +
                             'กรุณาลองใหม่อีกครั้งในอีกสักครู่ หรือติดต่อฝ่าย IT ของมหาวิทยาลัย\n\n' +
                             'หากต้องการใช้งานด่วน สามารถลองใช้โหมด Admin ได้';
            } else if (data.message && data.message.includes('503')) {
                errorMessage = 'ระบบ eLogin กำลังปรับปรุง (Error 503)\n' +
                             'กรุณาลองใหม่ภายหลัง';
            } else if (data.message && data.message.includes('504')) {
                errorMessage = 'ระบบ eLogin ตอบสนองช้า (Error 504)\n' +
                             'กรุณาลองใหม่อีกครั้ง';
            } else if (data.message && (data.message.includes('401') || data.message.includes('403'))) {
                errorMessage = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง\n' +
                             'กรุณาตรวจสอบข้อมูลและลองใหม่อีกครั้ง';
            }
            
            return {
                success: false,
                message: errorMessage
            };
        }
    } catch (error) {
        console.error('eLogin error:', error);
        
        let errorMessage = 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้';
        
        if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
            errorMessage = 'ไม่สามารถเชื่อมต่อกับระบบ eLogin ได้\n' +
                          'กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ตและลองใหม่อีกครั้ง\n\n' +
                          'หากปัญหายังคงอยู่ อาจเป็นเพราะระบบ eLogin ขัดข้อง';
        } else if (error.name === 'AbortError') {
            errorMessage = 'การเชื่อมต่อใช้เวลานานเกินไป\n' +
                          'กรุณาลองใหม่อีกครั้ง';
        }
        
        return {
            success: false,
            message: errorMessage
        };
    }
}

async function loginWithAdmin(username, password) {
    try {
        const response = await fetch(ADMIN_LOGIN_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                username: username,
                password: password
            })
        });

        const data = await response.json();

        if (data.status === 'success') {
            const userDataForSession = {
                ...data.user_data,
                login_method: 'admin'
            };

            const sessionResponse = await fetch(LOGIN_PROCESS_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'include', 
                body: JSON.stringify({
                    user_data: userDataForSession
                })
            });

            const sessionData = await sessionResponse.json();

            if (sessionData.status === 'success') {
                return {
                    success: true,
                    data: userDataForSession,
                    session_data: sessionData.user_data
                };
            } else {
                return {
                    success: false,
                    message: 'ไม่สามารถสร้าง session ได้: ' + (sessionData.message || 'Unknown error')
                };
            }
        } else {
            return {
                success: false,
                message: data.message || 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'
            };
        }
    } catch (error) {
        console.error('Admin Login error:', error);
        return {
            success: false,
            message: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้'
        };
    }
}
// Add retry mechanism for eLogin
async function loginWithEloginRetry(username, password, maxRetries = 2) {
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
        try {
            
            const result = await loginWithElogin(username, password);
            
            if (result.success) {
                return result;
            }
            
            // If it's a server error (502, 503, 504) and we have retries left, try again
            if (attempt < maxRetries && 
                result.message && 
                (result.message.includes('502') || 
                 result.message.includes('503') || 
                 result.message.includes('504'))) {
                
                showNotification(`ครั้งที่ ${attempt} ไม่สำเร็จ กำลังลองใหม่...`, "warning");
                
                // Wait before retry (exponential backoff)
                await new Promise(resolve => setTimeout(resolve, attempt * 2000));
                continue;
            }
            
            return result;
            
        } catch (error) {
            if (attempt === maxRetries) {
                return {
                    success: false,
                    message: `ลองเชื่อมต่อ ${maxRetries} ครั้งแล้วไม่สำเร็จ: ${error.message}`
                };
            }
            
            await new Promise(resolve => setTimeout(resolve, attempt * 2000));
        }
    }
}
// ===== Form Handlers =====
eloginFormElement.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const username = document.getElementById('elogin_username').value.trim();
    const password = document.getElementById('elogin_password').value;

    if (!username || !password) {
        showAlert('กรุณากรอกข้อมูลให้ครบถ้วน');
        return;
    }

    await handleLogin(() => loginWithEloginRetry(username, password), 'eloginBtn');
});

adminFormElement.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const username = document.getElementById('admin_username').value.trim();
    const password = document.getElementById('admin_password').value;

    if (!username || !password) {
        showAlert('กรุณากรอกข้อมูลให้ครบถ้วน');
        return;
    }

    await handleLogin(() => loginWithAdmin(username, password), 'adminBtn');
});

// ===== Enhanced Login Handler with Auto-refresh =====
async function handleLogin(loginFunction, buttonId) {
    const loginBtn = document.getElementById(buttonId);
    const originalText = loginBtn.textContent;

    // Show loading
    loginBtn.disabled = true;
    loginBtn.textContent = 'กำลังเข้าสู่ระบบ...';
    loading.style.display = 'block';
    alert.style.display = 'none';

    // Call login function
    const result = await loginFunction();

    // Hide loading
    loading.style.display = 'none';
    loginBtn.disabled = false;
    loginBtn.textContent = originalText;

    if (result.success) {
        let successMessage = 'เข้าสู่ระบบสำเร็จ!';
        let alertType = 'success';
        
        if (result.data.database_saved === false) {
            successMessage += ' (แต่ไม่สามารถบันทึกข้อมูลในฐานข้อมูลได้)';
            alertType = 'warning';
        }
        
        showAlert(successMessage, alertType);
        showNotification(successMessage, alertType);
        
        // Display user info
        displayUserInfo(result.data, result.session_data);
        
        // Hide forms
        eloginForm.style.display = 'none';
        adminForm.style.display = 'none';
        document.querySelector('.login-mode-selector').style.display = 'none';
        userInfo.style.display = 'block';
        
        // Store session data
        const dataToStore = { 
            ...result.session_data,
            login_time: new Date().toISOString()
        };
        localStorage.setItem('user_session', JSON.stringify(dataToStore));
        
        // ===== เพิ่มส่วนใหม่: Auto-refresh Google Calendar Token เมื่อ login =====
        setTimeout(async () => {
            const initSuccess = await tokenManager.initialize();
            
            if (initSuccess) {
                // ทำ auto-refresh ทันทีหลัง login
                const autoRefreshResult = await tokenManager.autoRefreshOnLogin();
                
                // อัพเดทสถานะใน UI
                await checkGoogleCalendarStatus();
                
                // เริ่ม auto check
                tokenManager.startAutoCheck();
            } else {
                displayGoogleCalendarStatus({
                    status: 'error',
                    message: 'Google Calendar API ไม่สามารถเข้าถึงได้ในขณะนี้',
                    has_google_auth: false
                });
            }
        }, 1000);
        
    } else {
        showAlert(result.message);
        showNotification(result.message, "error");
    }
}

function displayUserInfo(userData, sessionData) {
    let userHtml = '';
    
    // Display User ID and type
    if (sessionData.user_id) {
        let userTypeDisplay = '';
        let alertClass = '';
        let loginMethodBadge = '';
        
        // Login method badge
        if (userData.login_method === 'elogin') {
            loginMethodBadge = '<span style="background: #667eea; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 8px;">eLogin</span>';
        } else if (userData.login_method === 'admin') {
            loginMethodBadge = '<span style="background: #e17055; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 8px;">Admin DB</span>';
        }
        
        if (sessionData.is_temporary_access) {
            userTypeDisplay = 'อาจารย์ ชั่วคราว';
            alertClass = 'style="background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; padding: 5px; border-radius: 5px;"';
        } else if (sessionData.user_type === 'admin') {
            userTypeDisplay = 'ผู้ดูแลระบบ';
        } else if (sessionData.user_type === 'teacher') {
            userTypeDisplay = 'อาจารย์';
        } else {
            userTypeDisplay = 'นักศึกษา';
        }
        
        userIdDisplay.innerHTML = `
            <strong>User ID:</strong> ${sessionData.user_id} | 
            <strong>ประเภท:</strong> <span ${alertClass}>${userTypeDisplay}</span>
            ${loginMethodBadge}
        `;
        userIdDisplay.style.display = 'block';
    }
    
    // Display user data
    if (userData.name) {
        userHtml += `<p><strong>ชื่อ-นามสกุล:</strong> ${userData.title} ${userData.name}</p>`;
    }
    
    if (userData.username) {
        userHtml += `<p><strong>Username:</strong> ${userData.username}</p>`;
    }
    
    if (userData.email) {
        userHtml += `<p><strong>อีเมล:</strong> ${userData.email}</p>`;
    }
    
    if (userData.facname) {
        userHtml += `<p><strong>คณะ:</strong> ${userData.facname}</p>`;
    }
    
    if (userData.depname) {
        userHtml += `<p><strong>สาขา:</strong> ${userData.depname}</p>`;
    }
    
    if (userData.secname) {
        userHtml += `<p><strong>ชื่อปริญญา:</strong> ${userData.secname}</p>`;
    }
    
    // // Temporary access warning
    // if (sessionData.is_temporary_access) {
    //     userHtml += `<p style="background: #fff3cd; padding: 10px; border-radius: 5px; margin-top: 10px;">
    //         <strong>หมายเหตุ:</strong> คุณเข้าสู่ระบบในฐานะนักศึกษา แต่ได้รับสิทธิ์อาจารย์ชั่วคราว<br>
    //         สามารถเข้าถึงฟังก์ชันของอาจารย์ได้ทั้งหมด
    //     </p>`;
    // }
    
    userDetails.innerHTML = userHtml;
    
    // Session info
    let sessionStatusText = '';
    if (sessionData.is_temporary_access) {
        sessionStatusText = 'เชื่อมต่อแล้ว (โหมดอาจารย์ชั่วคราว)';
    } else {
        sessionStatusText = 'เชื่อมต่อแล้ว';
    }
    
    sessionInfo.innerHTML = `
        <strong>Session Info:</strong><br>
        เข้าสู่ระบบเมื่อ: ${new Date().toLocaleString('th-TH')}<br>
        สถานะ: ${sessionStatusText}<br>
        วิธีการ: ${userData.login_method === 'elogin' ? 'eLogin (มหาวิทยาลัย)' : 'Admin Database'}
    `;
}

function logout() {
    // หยุดการตรวจสอบ Token อัตโนมัติ
    tokenManager.stopAutoCheck();
    
    localStorage.removeItem('user_session');
    
    fetch('../api/logout.php', {
        method: 'POST',
        credentials: 'include'
    }).then(() => {
        resetInterface();
        showAlert('ออกจากระบบแล้ว', 'success');
        showNotification("ออกจากระบบแล้ว", "success");
    }).catch(() => {
        resetInterface();
        showAlert('ออกจากระบบแล้ว', 'success');
    });
}

function resetInterface() {
    eloginForm.style.display = currentMode === 'elogin' ? 'block' : 'none';
    adminForm.style.display = currentMode === 'admin' ? 'block' : 'none';
    document.querySelector('.login-mode-selector').style.display = 'flex';
    userInfo.style.display = 'none';
    googleCalendarStatus.style.display = 'none';
    alert.style.display = 'none';
    
    // Clear forms
    eloginFormElement.reset();
    adminFormElement.reset();
}

async function continueToSystem() {
    const sessionData = localStorage.getItem('user_session');
    if (sessionData) {
        try {
            showNotification("ตรวจสอบสถานะ Google Calendar...", "info");
            
            const data = await tokenManager.safeFetch(GOOGLE_CALENDAR_CHECK_URL);
            
            if (data.status === 'error') {
                showNotification("ไม่สามารถตรวจสอบ Google Calendar ได้ กำลังเข้าสู่ระบบ...", "warning");
            } else if (!data.has_google_auth || (data.action_required !== 'none' && data.action_required !== 'refresh')) {
                // แสดงคำเตือนแต่อนุญาตให้ดำเนินการต่อ
                const shouldConnect = await new Promise((resolve) => {
                    setTimeout(() => {
                        const result = confirm('คุณยังไม่ได้เชื่อมต่อ Google Calendar\nจะไม่สามารถซิงค์ตารางสอนได้\n\nต้องการเชื่อมต่อก่อนไหม?');
                        resolve(result);
                    }, 100);
                });
                
                if (shouldConnect) {
                    connectGoogleCalendar();
                    return;
                } else {
                    showNotification("ดำเนินการต่อโดยไม่เชื่อมต่อ Google Calendar", "warning");
                }
            } else {
                showNotification("Google Calendar พร้อมใช้งาน กำลังเข้าสู่ระบบ...", "success");
            }
            
            // ไปยังระบบหลัก
            setTimeout(() => {
                window.location.href = SCHEDULE_PAGE_URL;
            }, 1000);
            
        } catch (error) {
            console.error('Error checking Google Calendar status:', error);
            showNotification("ไม่สามารถตรวจสอบ Google Calendar ได้ กำลังเข้าสู่ระบบ...", "warning");
            setTimeout(() => {
                window.location.href = SCHEDULE_PAGE_URL;
            }, 1000);
        }
    } else {
        showAlert('ไม่พบข้อมูล session กรุณาเข้าสู่ระบบใหม่', 'error');
        showNotification("ไม่พบข้อมูล session กรุณาเข้าสู่ระบบใหม่", "error");
    }
}

// ===== Page Load Handler =====
window.addEventListener('load', () => {
    const savedSession = localStorage.getItem('user_session');
    if (savedSession) {
        try {
            const sessionData = JSON.parse(savedSession);
            const loginTime = new Date(sessionData.login_time);
            const now = new Date();
            const hoursDiff = (now - loginTime) / (1000 * 60 * 60);
            
            if (hoursDiff < 1) {
                // Session is still valid
                eloginForm.style.display = 'none';
                adminForm.style.display = 'none';
                document.querySelector('.login-mode-selector').style.display = 'none';
                userInfo.style.display = 'block';
                
                let userTypeDisplay = sessionData.user_type === 'admin' ? 'ผู้ดูแลระบบ' : 'อาจารย์';
                let loginMethodBadge = '';
                
                if (sessionData.login_method === 'elogin') {
                    loginMethodBadge = '<span style="background: #667eea; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 8px;">eLogin</span>';
                } else if (sessionData.login_method === 'admin') {
                    loginMethodBadge = '<span style="background: #e17055; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 8px;">Admin DB</span>';
                }
                
                userIdDisplay.innerHTML = `
                    <strong>User ID:</strong> ${sessionData.user_id} | 
                    <strong>ประเภท:</strong> ${userTypeDisplay}
                    ${loginMethodBadge}
                `;
                userIdDisplay.style.display = 'block';
                
                userDetails.innerHTML = `
                    <p><strong>ชื่อ-นามสกุล:</strong> ${sessionData.name}</p>
                    <p><strong>Username:</strong> ${sessionData.username}</p>
                `;
                
                sessionInfo.innerHTML = `
                    <strong>Session Info:</strong><br>
                    เข้าสู่ระบบเมื่อ: ${loginTime.toLocaleString('th-TH')}<br>
                    สถานะ: เชื่อมต่อแล้ว (จาก Session เดิม)<br>
                    วิธีการ: ${sessionData.login_method === 'elogin' ? 'eLogin (มหาวิทยาลัย)' : 'Admin Database'}
                `;
                
                // Start Google Calendar management สำหรับ existing session
                setTimeout(async () => {
                    const initSuccess = await tokenManager.initialize();
                    
                    if (initSuccess) {
                        // ทำ auto-refresh สำหรับ session เดิมด้วย
                        const autoRefreshResult = await tokenManager.autoRefreshOnLogin();
                        
                        await checkGoogleCalendarStatus();
                        tokenManager.startAutoCheck();
                    } else {
                        displayGoogleCalendarStatus({
                            status: 'error',
                            message: 'Google Calendar API ไม่สามารถเข้าถึงได้ในขณะนี้',
                            has_google_auth: false
                        });
                    }
                }, 1000);
                
            } else {
                // Session expired
                localStorage.removeItem('user_session');
                showAlert('Session หมดอายุ กรุณาเข้าสู่ระบบใหม่', 'warning');
                showNotification("Session หมดอายุ กรุณาเข้าสู่ระบบใหม่", "warning");
            }
        } catch (error) {
            localStorage.removeItem('user_session');
            showNotification("Session ข้อมูลไม่ถูกต้อง", "error");
        }
    }
});

// ===== Global Functions =====
window.getCurrentUserSession = function() {
    const sessionData = localStorage.getItem('user_session');
    if (sessionData) {
        try {
            return JSON.parse(sessionData);
        } catch (error) {
            return null;
        }
    }
    return null;
};

// Export enhanced functions
window.GoogleCalendarTokenManager = GoogleCalendarTokenManager;
window.tokenManager = tokenManager;
    </script>
</body>
</html>