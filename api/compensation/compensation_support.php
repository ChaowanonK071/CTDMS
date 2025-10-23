<?php
/**
 * ฟังก์ชันสนับสนุนสำหรับระบบการชดเชย
 */

/**
 * ดึงข้อมูลห้องเรียนทั้งหมด
 */
function getClassrooms() {
    try {
        $mysqli = connectMySQLi();
        
        $sql = "SELECT classroom_id, room_number, building, capacity 
                FROM classrooms 
                ORDER BY 
                    CASE WHEN building = 'ในสาขา' THEN 1 ELSE 2 END,
                    room_number";
        
        $result = $mysqli->query($sql);
        $classrooms = [];
        
        while ($row = $result->fetch_assoc()) {
            $classrooms[] = $row;
        }
        
        $mysqli->close();
        
        jsonSuccess('ดึงข้อมูลห้องเรียนสำเร็จ', $classrooms);
        
    } catch (Exception $e) {
        error_log("Error in getClassrooms: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาดในการดึงข้อมูลห้องเรียน: ' . $e->getMessage());
    }
}

/**
 * ดึงข้อมูลช่วงเวลาทั้งหมด
 */
function getTimeSlots() {
    try {
        $mysqli = connectMySQLi();
        
        $sql = "SELECT time_slot_id, slot_number, start_time, end_time 
                FROM time_slots 
                ORDER BY slot_number";
        
        $result = $mysqli->query($sql);
        $timeSlots = [];
        
        while ($row = $result->fetch_assoc()) {
            $timeSlots[] = $row;
        }
        
        $mysqli->close();
        
        jsonSuccess('ดึงข้อมูลช่วงเวลาสำเร็จ', $timeSlots);
        
    } catch (Exception $e) {
        error_log("Error in getTimeSlots: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาดในการดึงข้อมูลช่วงเวลา: ' . $e->getMessage());
    }
}

/**
 * ดึงสถิติการชดเชย
 */
function getCompensationStats() {
    global $user_id;
    
    $academic_year_id = $_POST['academic_year_id'] ?? $_GET['academic_year_id'] ?? 0;
    
    if (!$academic_year_id) {
        jsonError('ไม่ได้ระบุ academic_year_id');
    }
    
    try {
        $mysqli = connectMySQLi();
        
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'รอดำเนินการ' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'รอยืนยัน' THEN 1 ELSE 0 END) as waiting_approval,
                    SUM(CASE WHEN status = 'ดำเนินการแล้ว' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'ยกเลิก' THEN 1 ELSE 0 END) as cancelled
                FROM compensation_logs cl
                JOIN teaching_schedules ts ON cl.schedule_id = ts.schedule_id
                WHERE ts.academic_year_id = ?";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $academic_year_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $mysqli->close();
        
        jsonSuccess('ดึงสถิติการชดเชยสำเร็จ', $result);
        
    } catch (Exception $e) {
        error_log("Error in getCompensationStats: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาดในการดึงสถิติ: ' . $e->getMessage());
    }
}

/**
 * ดึงข้อมูลความพร้อมของห้องเรียน
 */
function getRoomAvailability() {
    global $user_id;
    
    $date = $_POST['date'] ?? '';
    
    if (!$date) {
        jsonError('ไม่ได้ระบุวันที่');
    }
    
    try {
        $mysqli = connectMySQLi();
        
        // รายการห้องเรียนพื้นฐาน
        $sql = "SELECT classroom_id, room_number, building, capacity 
                FROM classrooms 
                ORDER BY room_number";
        
        $result = $mysqli->query($sql);
        $rooms = [];
        
        while ($row = $result->fetch_assoc()) {
            $rooms[] = array_merge($row, [
                'availability_status' => 'available',
                'total_slots' => 13,
                'available_slots' => 13
            ]);
        }
        
        $mysqli->close();
        
        jsonSuccess('ดึงข้อมูลความพร้อมสำเร็จ', $rooms);
        
    } catch (Exception $e) {
        error_log("Error in getRoomAvailability: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาดในการดึงข้อมูลความพร้อม: ' . $e->getMessage());
    }
}

function getAcademicYearRange() {
    $academic_year_id = $_POST['academic_year_id'] ?? $_GET['academic_year_id'] ?? 0;
    if (!$academic_year_id) {
        jsonError('ไม่ได้ระบุ academic_year_id');
    }
    try {
        $mysqli = connectMySQLi();
        $sql = "SELECT start_date, end_date FROM academic_years WHERE academic_year_id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $academic_year_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $mysqli->close();
        if (!$result) {
            jsonError('ไม่พบปีการศึกษานี้');
        }
        jsonSuccess('ดึงช่วงวันที่สำเร็จ', $result);
    } catch (Exception $e) {
        jsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}
/**
 * ดึงรายชื่ออาจารย์ที่มีการชดเชย
 */
function getTeachersWithCompensations() {
    $academic_year_id = $_POST['academic_year_id'] ?? $_GET['academic_year_id'] ?? 0;
    
    if (!$academic_year_id) {
        jsonError('ไม่ได้ระบุ academic_year_id');
    }
    
    try {
        $mysqli = connectMySQLi();
        
        $query = "
            SELECT 
                u.user_id,
                CONCAT(u.title, u.name, ' ', u.lastname) as teacher_name,
                COUNT(cl.cancellation_id) as compensation_count,
                COUNT(CASE WHEN cl.status = 'ดำเนินการแล้ว' THEN 1 END) as confirmed_count
            FROM users u
            JOIN teaching_schedules ts ON u.user_id = ts.user_id
            JOIN compensation_logs cl ON ts.schedule_id = cl.schedule_id
            WHERE ts.academic_year_id = ?
            AND ts.is_active = 1
            GROUP BY u.user_id, u.name, u.lastname, u.title
            HAVING compensation_count > 0
            ORDER BY u.name, u.lastname
        ";
        
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("i", $academic_year_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $teachers = [];
        while ($row = $result->fetch_assoc()) {
            $teachers[] = $row;
        }
        
        jsonSuccess('ดึงรายชื่ออาจารย์ที่มีการชดเชยสำเร็จ', $teachers);
        
    } catch (Exception $e) {
        error_log("Error in getTeachersWithCompensations: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาดในการดึงรายชื่ออาจารย์: ' . $e->getMessage());
    }
}
?>

