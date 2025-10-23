<?php
/**
 * ฟังก์ชันหลักสำหรับจัดการการชดเชย
 */

// ป้องกันการ redeclare functions
if (!function_exists('jsonSuccess')) {
    /**
     * ฟังก์ชันช่วยเหลือสำหรับส่ง JSON response
     */
    function jsonSuccess($message, $data = null) {
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

if (!function_exists('jsonError')) {
    function jsonError($message, $code = 400, $data = null) {
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

if (!function_exists('connectMySQLi')) {
    /**
     * ฟังก์ชันเชื่อมต่อฐานข้อมูล
     */
    function connectMySQLi() {
        global $conn, $host, $user, $pass, $db;
        
        if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
            return $conn;
        }
        
        $conn = new mysqli($host, $user, $pass, $db);
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            throw new Exception('Database connection failed: ' . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
    }
}

if (!function_exists('testDatabase')) {
    /**
     * ทดสอบการเชื่อมต่อฐานข้อมูล
     */
    function testDatabase() {
        try {
            $mysqli = connectMySQLi();
            $result = $mysqli->query("SELECT 1 as test");
            
            if (!$result) {
                throw new Exception("Test query failed: " . $mysqli->error);
            }
            
            $row = $result->fetch_assoc();
            jsonSuccess('เชื่อมต่อฐานข้อมูลสำเร็จ', [
                'test_result' => $row['test'],
                'server_info' => $mysqli->server_info,
                'charset' => $mysqli->character_set_name()
            ]);
            
        } catch (Exception $e) {
            jsonError('เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . $e->getMessage());
        }
    }
}

/**
 * ดึงข้อมูลการชดเชยทั้งหมดพร้อมสถิติ Workflow
 */
function getAllCompensations() {
    global $user_id;

    $academic_year_id = $_POST['academic_year_id'] ?? $_GET['academic_year_id'] ?? 0;

    if (!$academic_year_id) {
        jsonError('ไม่ได้ระบุ academic_year_id');
    }

    try {
        $mysqli = connectMySQLi();

        $sql = "
            SELECT 
                cl.cancellation_id,
                cl.cancellation_date,
                cl.cancellation_type,
                cl.reason,
                cl.status,
                cl.is_makeup_required,

                cl.makeup_date,
                cl.makeup_classroom_id,
                cl.makeup_start_time_slot_id,
                cl.makeup_end_time_slot_id,

                cl.proposed_makeup_date,
                cl.proposed_makeup_classroom_id,
                cl.proposed_makeup_start_time_slot_id,
                cl.proposed_makeup_end_time_slot_id,
                cl.change_reason,

                cl.approval_notes,
                cl.approved_by,
                cl.approved_at,
                cl.rejected_reason,

                s.subject_code,
                s.subject_name,

                u.user_id,
                CONCAT(u.title, u.name, ' ', u.lastname) as teacher_name,

                co_u.user_id as co_user_id,
                CONCAT(co_u.title, co_u.name, ' ', co_u.lastname) as co_teacher_name,

                co_u2.user_id as co_user_id_2,
                CONCAT(co_u2.title, co_u2.name, ' ', co_u2.lastname) as co_teacher_name_2,

                ts.max_teachers,
                ts.current_teachers,

                ts.is_module_subject,
                ts.group_id,
                mg.group_name,
                m.module_name,

                yl.class_year,
                yl.department,
                yl.curriculum,

                cr.room_number,
                ts.day_of_week,
                tstart.start_time,
                tend.end_time,
                ts.start_time_slot_id,
                ts.end_time_slot_id,

                mcr.room_number as makeup_room_number,
                mstart.start_time as makeup_start_time,
                mend.end_time as makeup_end_time,

                pcr.room_number as proposed_room_number,
                pstart.start_time as proposed_start_time,
                pend.end_time as proposed_end_time,

                CONCAT(approver.title, approver.name, ' ', approver.lastname) as approved_by_name,

                cl.created_at,
                cl.updated_at

            FROM compensation_logs cl
            LEFT JOIN teaching_schedules ts ON cl.schedule_id = ts.schedule_id
            LEFT JOIN subjects s ON ts.subject_id = s.subject_id
            LEFT JOIN users u ON ts.user_id = u.user_id
            LEFT JOIN users co_u ON ts.co_user_id = co_u.user_id
            LEFT JOIN users co_u2 ON ts.co_user_id_2 = co_u2.user_id
            LEFT JOIN year_levels yl ON ts.year_level_id = yl.year_level_id
            LEFT JOIN module_groups mg ON ts.group_id = mg.group_id
            LEFT JOIN modules m ON mg.module_id = m.module_id
            LEFT JOIN classrooms cr ON ts.classroom_id = cr.classroom_id
            LEFT JOIN time_slots tstart ON ts.start_time_slot_id = tstart.time_slot_id
            LEFT JOIN time_slots tend ON ts.end_time_slot_id = tend.time_slot_id

            LEFT JOIN classrooms mcr ON cl.makeup_classroom_id = mcr.classroom_id
            LEFT JOIN time_slots mstart ON cl.makeup_start_time_slot_id = mstart.time_slot_id
            LEFT JOIN time_slots mend ON cl.makeup_end_time_slot_id = mend.time_slot_id

            LEFT JOIN classrooms pcr ON cl.proposed_makeup_classroom_id = pcr.classroom_id
            LEFT JOIN time_slots pstart ON cl.proposed_makeup_start_time_slot_id = pstart.time_slot_id
            LEFT JOIN time_slots pend ON cl.proposed_makeup_end_time_slot_id = pend.time_slot_id

            LEFT JOIN users approver ON cl.approved_by = approver.user_id

            WHERE ts.academic_year_id = ? 
            AND (ts.is_active = 1 OR ts.is_active IS NULL)
            ORDER BY cl.cancellation_date DESC, cl.created_at DESC
        ";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception('ไม่สามารถเตรียม SQL statement ได้: ' . $mysqli->error);
        }

        $stmt->bind_param("i", $academic_year_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $compensations = [];
        while ($row = $result->fetch_assoc()) {
            // จัดรูปแบบข้อมูลอาจารย์
            $teachers = [];
            if ($row['teacher_name']) {
                $teachers[] = $row['teacher_name'];
            }
            if ($row['co_teacher_name']) {
                $teachers[] = $row['co_teacher_name'];
            }
            if ($row['co_teacher_name_2']) {
                $teachers[] = $row['co_teacher_name_2'];
            }

            $row['all_teachers'] = implode(', ', $teachers);
            $row['teachers_count'] = count($teachers);

            // เพิ่ม department, curriculum ให้แน่ใจว่ามีใน array
            $row['department'] = $row['department'] ?? '';
            $row['curriculum'] = $row['curriculum'] ?? '';

            // ถ้าเป็นโมดูล ให้ดึง year_levels ทั้งหมดในกลุ่ม
            if ($row['is_module_subject'] == 1 && $row['group_id']) {
                $row['year_levels_in_group'] = [];
                $group_id = $row['group_id'];
                $yl_sql = "SELECT yl.department, yl.class_year, yl.curriculum 
                        FROM module_group_year_levels mgyl
                        JOIN year_levels yl ON mgyl.year_level_id = yl.year_level_id
                        WHERE mgyl.group_id = ?";
                $yl_stmt = $mysqli->prepare($yl_sql);
                $yl_stmt->bind_param("i", $group_id);
                $yl_stmt->execute();
                $yl_result = $yl_stmt->get_result();
                while ($yl_row = $yl_result->fetch_assoc()) {
                    $row['year_levels_in_group'][] = [
                        'department' => $yl_row['department'],
                        'class_year' => $yl_row['class_year'],
                        'curriculum' => $yl_row['curriculum']
                    ];
                }
                $yl_stmt->close();
            }

            $compensations[] = $row;
        }

        $statistics = calculateCompensationStatistics($compensations);

        jsonSuccess('ดึงข้อมูลการชดเชยสำเร็จ', [
            'compensations' => $compensations,
            'statistics' => $statistics
        ]);

    } catch (Exception $e) {
        error_log("Error in getAllCompensations: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage());
    }
}

/**
 * คำนวณสถิติการชดเชย
 */
function calculateCompensationStatistics($compensations) {
    $total = count($compensations);
    $pending = count(array_filter($compensations, function($comp) {
        return $comp['status'] === 'รอดำเนินการ';
    }));
    $waiting_approval = count(array_filter($compensations, function($comp) {
        return $comp['status'] === 'รอยืนยัน';
    }));
    $completed = count(array_filter($compensations, function($comp) {
        return $comp['status'] === 'ดำเนินการแล้ว';
    }));
    $cancelled = count(array_filter($compensations, function($comp) {
        return $comp['status'] === 'ยกเลิก';
    }));
    
    return [
        'total' => $total,
        'pending' => $pending,
        'waiting_approval' => $waiting_approval,
        'completed' => $completed,
        'cancelled' => $cancelled,
        'workflow_efficiency' => $total > 0 ? round(($completed / $total) * 100, 2) : 0
    ];
}

/**
 * ดึงรายละเอียดการชดเชยแต่ละรายการ
 */
function getCompensationDetails() {
    global $user_id;

    $cancellation_id = $_POST['cancellation_id'] ?? $_GET['cancellation_id'] ?? 0;

    if (!$cancellation_id) {
        jsonError('ไม่ได้ระบุ cancellation_id');
    }

    try {
        $mysqli = connectMySQLi();

        $sql = "
            SELECT 
                cl.*,
                s.subject_code,
                s.subject_name,

                CONCAT(u.title, u.name, ' ', u.lastname) as teacher_name,
                u.user_id as main_teacher_id,

                co_u.user_id as co_user_id,
                CONCAT(co_u.title, co_u.name, ' ', co_u.lastname) as co_teacher_name,

                co_u2.user_id as co_user_id_2,
                CONCAT(co_u2.title, co_u2.name, ' ', co_u2.lastname) as co_teacher_name_2,

                ts.max_teachers,
                ts.current_teachers,

                ts.is_module_subject,
                ts.group_id,
                mg.group_name,
                m.module_name,

                yl.class_year,
                yl.department,
                yl.curriculum,

                cr.room_number,
                ts.day_of_week,
                tstart.start_time,
                tend.end_time,
                ts.start_time_slot_id,
                ts.end_time_slot_id,

                mcr.room_number as makeup_room_number,
                mstart.start_time as makeup_start_time,
                mend.end_time as makeup_end_time,

                pcr.room_number as proposed_room_number,
                pstart.start_time as proposed_start_time,
                pend.end_time as proposed_end_time,

                CONCAT(approver.title, approver.name, ' ', approver.lastname) as approved_by_name

            FROM compensation_logs cl
            LEFT JOIN teaching_schedules ts ON cl.schedule_id = ts.schedule_id
            LEFT JOIN subjects s ON ts.subject_id = s.subject_id
            LEFT JOIN users u ON ts.user_id = u.user_id
            LEFT JOIN users co_u ON ts.co_user_id = co_u.user_id
            LEFT JOIN users co_u2 ON ts.co_user_id_2 = co_u2.user_id
            LEFT JOIN year_levels yl ON ts.year_level_id = yl.year_level_id
            LEFT JOIN module_groups mg ON ts.group_id = mg.group_id
            LEFT JOIN modules m ON mg.module_id = m.module_id
            LEFT JOIN classrooms cr ON ts.classroom_id = cr.classroom_id
            LEFT JOIN time_slots tstart ON ts.start_time_slot_id = tstart.time_slot_id
            LEFT JOIN time_slots tend ON ts.end_time_slot_id = tend.time_slot_id

            LEFT JOIN classrooms mcr ON cl.makeup_classroom_id = mcr.classroom_id
            LEFT JOIN time_slots mstart ON cl.makeup_start_time_slot_id = mstart.time_slot_id
            LEFT JOIN time_slots mend ON cl.makeup_end_time_slot_id = mend.time_slot_id

            LEFT JOIN classrooms pcr ON cl.proposed_makeup_classroom_id = pcr.classroom_id
            LEFT JOIN time_slots pstart ON cl.proposed_makeup_start_time_slot_id = pstart.time_slot_id
            LEFT JOIN time_slots pend ON cl.proposed_makeup_end_time_slot_id = pend.time_slot_id

            LEFT JOIN users approver ON cl.approved_by = approver.user_id

            WHERE cl.cancellation_id = ?
        ";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $cancellation_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $compensation = $result->fetch_assoc();

        if (!$compensation) {
            jsonError('ไม่พบข้อมูลการชดเชยนี้');
        }

        // จัดรูปแบบข้อมูลอาจารย์
        $teachers = [];
        $teacher_ids = [];

        if ($compensation['teacher_name']) {
            $teachers[] = $compensation['teacher_name'] . ' (หลัก)';
            $teacher_ids[] = $compensation['main_teacher_id'];
        }
        if ($compensation['co_teacher_name']) {
            $teachers[] = $compensation['co_teacher_name'] . ' (ร่วม)';
            $teacher_ids[] = $compensation['co_user_id'];
        }
        if ($compensation['co_teacher_name_2']) {
            $teachers[] = $compensation['co_teacher_name_2'] . ' (ร่วม)';
            $teacher_ids[] = $compensation['co_user_id_2'];
        }

        $compensation['all_teachers'] = implode(', ', $teachers);
        $compensation['all_teacher_ids'] = $teacher_ids;
        $compensation['teachers_count'] = count($teachers);

        $compensation['department'] = $compensation['department'] ?? '';
        $compensation['curriculum'] = $compensation['curriculum'] ?? '';

        // ถ้าเป็นโมดูล ให้ดึง year_levels ทั้งหมดในกลุ่ม
        if ($compensation['is_module_subject'] == 1 && $compensation['group_id']) {
            $compensation['year_levels_in_group'] = [];
            $group_id = $compensation['group_id'];
            $yl_sql = "SELECT yl.department, yl.class_year, yl.curriculum 
                    FROM module_group_year_levels mgyl
                    JOIN year_levels yl ON mgyl.year_level_id = yl.year_level_id
                    WHERE mgyl.group_id = ?";
            $yl_stmt = $mysqli->prepare($yl_sql);
            $yl_stmt->bind_param("i", $group_id);
            $yl_stmt->execute();
            $yl_result = $yl_stmt->get_result();
            while ($yl_row = $yl_result->fetch_assoc()) {
                $compensation['year_levels_in_group'][] = [
                    'department' => $yl_row['department'],
                    'class_year' => $yl_row['class_year'],
                    'curriculum' => $yl_row['curriculum']
                ];
            }
            $yl_stmt->close();
        }

        jsonSuccess('ดึงข้อมูลรายละเอียดสำเร็จ', $compensation);

    } catch (Exception $e) {
        error_log("Error in getCompensationDetails: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาดในการดึงรายละเอียด: ' . $e->getMessage());
    }
}


if (!function_exists('logStatusChange')) {
    /**
     * บันทึกประวัติการเปลี่ยนสถานะ
     */
    function logStatusChange($mysqli, $cancellation_id, $old_status, $new_status, $action_by, $action_reason = null) {
        try {
            $log_sql = "
                INSERT INTO compensation_status_history 
                (cancellation_id, old_status, new_status, action_by, action_reason, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ";
            
            $stmt = $mysqli->prepare($log_sql);
            $stmt->bind_param("issis", $cancellation_id, $old_status, $new_status, $action_by, $action_reason);
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Error logging status change: " . $e->getMessage());
        }
    }
}
/**
 * ดึงรายชื่ออาจารย์สำหรับการจัดตารางอัตโนมัติ
 */
function getTeachersForAutoSchedule() {
    global $user_id;
    
    $academic_year_id = $_POST['academic_year_id'] ?? $_GET['academic_year_id'] ?? 0;
    
    if (!$academic_year_id) {
        jsonError('ไม่ได้ระบุ academic_year_id');
    }
    
    try {
        $mysqli = connectMySQLi();
        
        // ตรวจสอบสิทธิ์อย่างเข้มงวด
        $is_admin = isAdmin($user_id);
        $user_role = $is_admin ? 'admin' : 'teacher';
        
        // Log การเข้าถึงฟังก์ชันนี้
        logUserAction($user_id, 'get_teachers_for_auto_schedule', json_encode([
            'academic_year_id' => $academic_year_id,
            'detected_role' => $user_role,
            'is_admin' => $is_admin
        ]));
        
        // ดึงข้อมูลอาจารย์ตามสิทธิ์
        if ($is_admin) {
            // Admin: ดูได้ทุกคน
            $sql = "SELECT 
                        u.user_id,
                        CONCAT(COALESCE(u.title, ''), u.name, ' ', u.lastname) as teacher_name,
                        COUNT(cl.cancellation_id) as pending_compensation_count,
                        GROUP_CONCAT(DISTINCT s.subject_code ORDER BY s.subject_code SEPARATOR ', ') as subjects
                    FROM users u
                    JOIN teaching_schedules ts ON u.user_id = ts.user_id
                    JOIN compensation_logs cl ON ts.schedule_id = cl.schedule_id
                    JOIN subjects s ON ts.subject_id = s.subject_id
                    WHERE ts.academic_year_id = ?
                    AND cl.status = 'รอดำเนินการ'
                    AND cl.is_makeup_required = 1
                    GROUP BY u.user_id, u.title, u.name, u.lastname
                    HAVING pending_compensation_count > 0
                    ORDER BY 
                        CASE WHEN u.user_id = ? THEN 0 ELSE 1 END,
                        teacher_name";
                        
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("ii", $academic_year_id, $user_id);
            
        } else {
            // อาจารย์ทั่วไป: ดูได้เฉพาะของตัวเอง
            $sql = "SELECT 
                        u.user_id,
                        CONCAT(COALESCE(u.title, ''), u.name, ' ', u.lastname) as teacher_name,
                        COUNT(cl.cancellation_id) as pending_compensation_count,
                        GROUP_CONCAT(DISTINCT s.subject_code ORDER BY s.subject_code SEPARATOR ', ') as subjects
                    FROM users u
                    JOIN teaching_schedules ts ON u.user_id = ts.user_id
                    JOIN compensation_logs cl ON ts.schedule_id = cl.schedule_id
                    JOIN subjects s ON ts.subject_id = s.subject_id
                    WHERE ts.academic_year_id = ?
                    AND cl.status = 'รอดำเนินการ'
                    AND cl.is_makeup_required = 1
                    AND u.user_id = ?
                    GROUP BY u.user_id, u.title, u.name, u.lastname
                    HAVING pending_compensation_count > 0
                    ORDER BY teacher_name";
                    
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("ii", $academic_year_id, $user_id);
        }
        
        if (!$stmt) {
            throw new Exception('Database error: ' . $mysqli->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $teachers = [];
        while ($row = $result->fetch_assoc()) {
            $teachers[] = $row;
        }
        
        // ข้อมูลสิทธิ์และตัวเลือกที่ชัดเจน
        $permission_info = [
            'is_admin' => $is_admin,
            'can_schedule_for_others' => $is_admin,
            'can_select_all' => $is_admin,
            'available_selections' => $is_admin ? ['self', 'other', 'all'] : ['self'],
            'restriction_message' => $is_admin ? 
                'Admin สามารถจัดตารางให้อาจารย์ทุกคนได้' : 
                'อาจารย์สามารถจัดตารางได้เฉพาะของตัวเองเท่านั้น',
            'ui_options' => [
                'show_all_option' => $is_admin,
                'show_other_teachers' => $is_admin,
                'show_self_only' => !$is_admin || count($teachers) === 1,
                'default_selection' => $is_admin ? 'all' : 'self'
            ]
        ];
        
        error_log("User {$user_id} role check - Admin: " . ($is_admin ? 'YES' : 'NO') . " - Teachers accessible: " . count($teachers));
        
        jsonSuccess('ดึงรายชื่ออาจารย์สำหรับจัดตารางอัตโนมัติสำเร็จ', [
            'teachers' => $teachers,
            'user_role' => $user_role,
            'can_select_all' => $is_admin,
            'current_user_id' => $user_id,
            'permission_info' => $permission_info,
            'debug_info' => [
                'user_id' => $user_id,
                'academic_year_id' => $academic_year_id,
                'query_result_count' => count($teachers),
                'is_admin_detected' => $is_admin,
                'role_detection_method' => $is_admin ? 'admin_detected' : 'regular_teacher',
                'filter_applied' => $is_admin ? 'all_users' : 'current_user_only',
                'sql_used' => $is_admin ? 'admin_query' : 'teacher_query'
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error in getTeachersForAutoSchedule: " . $e->getMessage());
        logUserAction($user_id, 'get_teachers_for_auto_schedule_error', $e->getMessage());
        jsonError('เกิดข้อผิดพลาดในการดึงรายชื่ออาจารย์: ' . $e->getMessage());
    }
}

if (!function_exists('isAdmin')) {
    /**
     * ตรวจสอบสิทธิ์ admin อย่างเข้มงวด
     */
    function isAdmin($user_id) {
        global $conn;
        
        try {
            $mysqli = $conn ?? connectMySQLi();
            
            // วิธีหลัก: ตรวจสอบจาก user_type field
            $user_type_check_sql = "SELECT user_type FROM users WHERE user_id = ? AND is_active = 1";
            $stmt = $mysqli->prepare($user_type_check_sql);
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                
                if ($result && $result['user_type'] === 'admin') {
                    error_log("User {$user_id} is admin via user_type field");
                    return true;
                }
            }
            
            // วิธีที่ 2: ตรวจสอบจาก user_id ที่กำหนดไว้ล่วงหน้า (ตามฐานข้อมูลที่แสดง)
            $predefined_admins = [1, 23]; // Admin IDs จากฐานข้อมูล
            if (in_array($user_id, $predefined_admins)) {
                error_log("User {$user_id} is admin via predefined list");
                return true;
            }
            
            // วิธีสำรอง: ตรวจสอบจาก role field (หากมี)
            $role_check_sql = "SELECT role FROM users WHERE user_id = ?";
            $stmt = $mysqli->prepare($role_check_sql);
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                
                if ($result && isset($result['role'])) {
                    $role = strtolower($result['role']);
                    if (in_array($role, ['admin', 'administrator', 'director', 'head'])) {
                        error_log("User {$user_id} is admin via role field: {$role}");
                        return true;
                    }
                }
            }
            
            // วิธีที่ 3: ตรวจสอบจาก admin_users table (หากมี)
            $admin_check_sql = "SELECT user_id FROM admin_users WHERE user_id = ? AND is_active = 1";
            $stmt = $mysqli->prepare($admin_check_sql);
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    error_log("User {$user_id} is admin via admin_users table");
                    return true;
                }
            }
            
            // วิธีที่ 4: ตรวจสอบจาก email pattern
            $email_check_sql = "SELECT email FROM users WHERE user_id = ?";
            $stmt = $mysqli->prepare($email_check_sql);
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                
                if ($result && $result['email']) {
                    $email = strtolower($result['email']);
                    $admin_patterns = ['admin', 'director', 'head', 'manager'];
                    
                    foreach ($admin_patterns as $pattern) {
                        if (strpos($email, $pattern) !== false) {
                            error_log("User {$user_id} is admin via email pattern: {$pattern}");
                            return true;
                        }
                    }
                }
            }
            
            error_log("User {$user_id} is NOT admin - all checks failed");
            return false;
            
        } catch (Exception $e) {
            error_log("Error checking admin status for user {$user_id}: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('logUserAction')) {
    /**
     * บันทึก log การดำเนินการของผู้ใช้
     */
    function logUserAction($user_id, $action, $details = null, $target_user_id = null) {
        try {
            $mysqli = connectMySQLi();
            
            $log_sql = "
                INSERT INTO user_action_logs 
                (user_id, action, details, target_user_id, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ";
            
            $stmt = $mysqli->prepare($log_sql);
            if ($stmt) {
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                
                $stmt->bind_param("ississ", 
                    $user_id, 
                    $action, 
                    $details, 
                    $target_user_id, 
                    $ip_address, 
                    $user_agent
                );
                $stmt->execute();
            }
            
        } catch (Exception $e) {
            error_log("Error logging user action: " . $e->getMessage());
        }
    }
}
?>

