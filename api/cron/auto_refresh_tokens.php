#!/usr/bin/php
<?php
/**
 * Auto Refresh Google Calendar Tokens - Cron Job
 * ไฟล์: /cron/auto_refresh_tokens.php
 * 
 * การใช้งาน:
 * 1. chmod +x /path/to/cron/auto_refresh_tokens.php
 * 2. เพิ่มใน crontab: */

// ตั้งค่า timezone
date_default_timezone_set('Asia/Bangkok');

// ป้องกันการเรียกผ่าน web browser
if (isset($_SERVER['HTTP_HOST'])) {
    die('This script can only be run from command line.');
}

// Log function
function cronLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
    
    // เขียน log ลงไฟล์ (ถ้าต้องการ)
    $logFile = __DIR__ . '/../logs/google_calendar_cron.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

// เริ่มต้น script
cronLog("🚀 Auto Refresh Google Calendar Tokens - Started");

try {
    // หาตำแหน่งไฟล์ config
    $configPaths = [
        __DIR__ . '/../api/config.php'
    ];
    
    $configLoaded = false;
    foreach ($configPaths as $configPath) {
        if (file_exists($configPath)) {
            require_once $configPath;
            $configLoaded = true;
            cronLog("✅ Config loaded from: $configPath");
            break;
        }
    }
    
    if (!$configLoaded) {
        throw new Exception('Config file not found');
    }
    
    // โหลด Google Calendar Integration
    $integrationPaths = [
        __DIR__ . '/../api/calendar/google_calendar_integration.php',
        __DIR__ . '/../google_calendar_integration.php'
    ];
    
    $integrationLoaded = false;
    foreach ($integrationPaths as $integrationPath) {
        if (file_exists($integrationPath)) {
            require_once $integrationPath;
            $integrationLoaded = true;
            cronLog("✅ Google Calendar Integration loaded from: $integrationPath");
            break;
        }
    }
    
    if (!$integrationLoaded) {
        throw new Exception('Google Calendar Integration not found');
    }
    
    // ตรวจสอบว่าฟังก์ชันที่จำเป็นมีอยู่หรือไม่
    if (!function_exists('batchRefreshExpiredTokens')) {
        throw new Exception('batchRefreshExpiredTokens function not found');
    }
    
    cronLog("🔍 Checking for tokens that need refresh...");
    
    // กำหนดจำนวนชั่วโมงก่อนหมดอายุที่จะทำการ refresh
    $hoursBeforeExpiry = 2; // Refresh tokens ที่จะหมดอายุภายใน 2 ชั่วโมง
    
    // เรียกใช้ batch refresh
    $result = batchRefreshExpiredTokens($hoursBeforeExpiry);
    
    // แสดงผลลัพธ์
    cronLog("📊 Batch Refresh Results:");
    cronLog("   - Total checked: " . $result['total_checked']);
    cronLog("   - Successfully refreshed: " . $result['success_count']);
    cronLog("   - Failed to refresh: " . $result['failed_count']);
    
    if (!empty($result['success_users'])) {
        cronLog("✅ Successfully refreshed tokens for:");
        foreach ($result['success_users'] as $user) {
            cronLog("   - User ID: {$user['user_id']} ({$user['email']})");
            cronLog("     Old expiry: {$user['old_expiry']}");
            cronLog("     New expiry: {$user['new_expiry']}");
        }
    }
    
    if (!empty($result['failed_users'])) {
        cronLog("❌ Failed to refresh tokens for:");
        foreach ($result['failed_users'] as $user) {
            cronLog("   - User ID: {$user['user_id']} ({$user['email']})");
            cronLog("     Error: {$user['error']}");
        }
        
        // ส่งอีเมลแจ้งเตือน admin (ถ้าต้องการ)
        if (count($result['failed_users']) > 0) {
            cronLog("📧 Should notify admin about failed token refreshes");
        }
    }
    
    // สถิติการใช้งาน
    $memoryUsage = memory_get_peak_usage(true);
    $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
    
    cronLog("📈 Performance stats:");
    cronLog("   - Memory usage: " . formatBytes($memoryUsage));
    cronLog("   - Execution time: " . number_format($executionTime, 2) . " seconds");
    
    cronLog("✅ Auto Refresh Google Calendar Tokens - Completed successfully");
    
    // Exit code 0 = success
    exit(0);
    
} catch (Exception $e) {
    cronLog("❌ ERROR: " . $e->getMessage());
    cronLog("📍 Stack trace: " . $e->getTraceAsString());
    
    // ส่งอีเมลแจ้งเตือน admin (ถ้าต้องการ)
    cronLog("📧 Should notify admin about cron job failure");
    
    // Exit code 1 = error
    exit(1);
}

/**
 * Helper function - Format bytes
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * ฟังก์ชัน batchRefreshExpiredTokens (backup ในกรณีที่ไม่โหลดจาก integration file)
 */
if (!function_exists('batchRefreshExpiredTokens')) {
    function batchRefreshExpiredTokens($hoursBeforeExpiry = 1) {
        try {
            $conn = connectMySQLi();
            
            $query = "
                SELECT user_id, google_email, token_expiry 
                FROM google_auth 
                WHERE is_active = 1 
                AND google_refresh_token IS NOT NULL 
                AND (
                    token_expiry <= DATE_ADD(NOW(), INTERVAL ? HOUR)
                    OR token_expiry IS NULL
                )
                ORDER BY token_expiry ASC
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $hoursBeforeExpiry);
            $stmt->execute();
            $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $conn->close();
            
            $summary = [
                'total_checked' => count($results),
                'success_count' => 0,
                'failed_count' => 0,
                'failed_users' => [],
                'success_users' => []
            ];
            
            foreach ($results as $authData) {
                $user_id = $authData['user_id'];
                $refreshResult = refreshGoogleAccessToken($user_id);
                
                if ($refreshResult['success']) {
                    $summary['success_count']++;
                    $summary['success_users'][] = [
                        'user_id' => $user_id,
                        'email' => $authData['google_email'],
                        'old_expiry' => $authData['token_expiry'],
                        'new_expiry' => $refreshResult['token_expiry']
                    ];
                } else {
                    $summary['failed_count']++;
                    $summary['failed_users'][] = [
                        'user_id' => $user_id,
                        'email' => $authData['google_email'],
                        'error' => $refreshResult['error']
                    ];
                }
                
                usleep(500000); // 0.5 วินาที
            }
            
            return $summary;
            
        } catch (Exception $e) {
            return [
                'total_checked' => 0,
                'success_count' => 0,
                'failed_count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
}

?>