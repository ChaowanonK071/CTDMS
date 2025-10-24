<?php
/**
 * ฟังก์ชัน Workflow สำหรับการอนุมัติ
 */

/**
 * อนุมัติตารางชดเชย
 */
function approveCompensationSchedule() {
    global $user_id;
    
    $cancellation_id = $_POST['cancellation_id'] ?? 0;
    $approval_notes = $_POST['approval_notes'] ?? 'อนุมัติตารางชดเชย';
    
    if (!$cancellation_id) {
        jsonError('ไม่ได้ระบุ cancellation_id');
    }
    
    try {
        $mysqli = connectMySQLi();
        $mysqli->begin_transaction();
        
        // ดึงข้อมูลการชดเชยที่รอยืนยัน
        $compensation_query = "
            SELECT cl.*, s.subject_code, s.subject_name, ts.user_id as teacher_id
            FROM compensation_logs cl
            JOIN teaching_schedules ts ON cl.schedule_id = ts.schedule_id
            JOIN subjects s ON ts.subject_id = s.subject_id
            WHERE cl.cancellation_id = ? AND cl.status = 'รอยืนยัน'
        ";
        
        $stmt = $mysqli->prepare($compensation_query);
        if (!$stmt) {
            throw new Exception('ไม่สามารถเตรียม SQL statement ได้: ' . $mysqli->error);
        }
        
        $stmt->bind_param("i", $cancellation_id);
        $stmt->execute();
        $compensation = $stmt->get_result()->fetch_assoc();
        
        if (!$compensation) {
            throw new Exception('ไม่พบข้อมูลการชดเชยหรือรายการนี้ไม่อยู่ในสถานะ "รอยืนยัน"');
        }
        
        // ตรวจสอบว่ามีข้อมูลการเสนอตารางหรือไม่
        if (!$compensation['proposed_makeup_date']) {
            throw new Exception('ไม่พบข้อมูลตารางที่เสนอ');
        }
        
        // สร้างข้อมูลตารางจากที่เสนอ
        $suitable_schedule = [
            'date' => $compensation['proposed_makeup_date'],
            'classroom_id' => $compensation['proposed_makeup_classroom_id'],
            'start_slot_id' => $compensation['proposed_makeup_start_time_slot_id'],
            'end_slot_id' => $compensation['proposed_makeup_end_time_slot_id']
        ];
        
        // ตรวจสอบความขัดแย้งอีกครั้ง
        require_once 'compensation_schedule.php';
        $conflicts = checkScheduleConflicts($mysqli, $suitable_schedule['date'], $suitable_schedule['classroom_id'], 
                                          $suitable_schedule['start_slot_id'], $suitable_schedule['end_slot_id'], $cancellation_id);
        
        if ($conflicts['has_conflict']) {
            throw new Exception('มีความขัดแย้งในตารางเรียน: ' . implode(', ', $conflicts['conflicts']));
        }
        
        // สร้าง Class Session
        $result = createCompensationClassSession($mysqli, $compensation, $suitable_schedule, $user_id);
        
        if (!$result['success']) {
            throw new Exception($result['message']);
        }
        
        // อัปเดตสถานะเป็น "ดำเนินการแล้ว" (แก้ไข SQL - ลดจำนวน bind parameters)
        $update_sql = "
            UPDATE compensation_logs 
            SET status = 'ดำเนินการแล้ว',
                makeup_date = ?,
                makeup_classroom_id = ?,
                makeup_start_time_slot_id = ?,
                makeup_end_time_slot_id = ?,
                approved_by = ?,
                approved_at = NOW(),
                approval_notes = ?,
                updated_at = NOW()
            WHERE cancellation_id = ?
        ";
        
        $stmt = $mysqli->prepare($update_sql);
        if (!$stmt) {
            throw new Exception('ไม่สามารถเตรียม SQL statement สำหรับอัปเดต: ' . $mysqli->error);
        }
        
        $stmt->bind_param("siiiisi", 
            $compensation['proposed_makeup_date'],           
            $compensation['proposed_makeup_classroom_id'],   
            $compensation['proposed_makeup_start_time_slot_id'], 
            $compensation['proposed_makeup_end_time_slot_id'],   
            $user_id,                                       
            $approval_notes,                               
            $cancellation_id                                
        );
        
        if (!$stmt->execute()) {
            throw new Exception('ไม่สามารถอัปเดตสถานะการชดเชยได้: ' . $stmt->error);
        }
        
        // บันทึกประวัติการเปลี่ยนสถานะ
        logStatusChange($mysqli, $cancellation_id, 'รอยืนยัน', 'ดำเนินการแล้ว', $user_id, 'อนุมัติและสร้างการสอนชดเชย: ' . $approval_notes);
        
        $mysqli->commit();
        
        jsonSuccess('อนุมัติตารางชดเชยและสร้างการสอนชดเชยเสร็จสิ้น', [
            'cancellation_id' => $cancellation_id,
            'status' => 'ดำเนินการแล้ว',
            'session_id' => $result['session_id'],
            'approved_by' => $user_id,
            'approval_notes' => $approval_notes,
            'makeup_date' => $compensation['proposed_makeup_date'],
            'makeup_classroom_id' => $compensation['proposed_makeup_classroom_id']
        ]);
        
    } catch (Exception $e) {
        if (isset($mysqli)) {
            $mysqli->rollback();
        }
        error_log("Error in approveCompensationSchedule: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาดในการอนุมัติ: ' . $e->getMessage());
    }
}

/**
 * ปฏิเสธตารางชดเชย
 */
function rejectCompensationSchedule() {
    global $user_id;
    
    $cancellation_id = $_POST['cancellation_id'] ?? 0;
    $rejection_reason = $_POST['rejection_reason'] ?? '';
    
    if (!$cancellation_id || !$rejection_reason) {
        jsonError('ข้อมูลไม่ครบถ้วน');
    }
    
    try {
        $mysqli = connectMySQLi();
        $mysqli->begin_transaction();
        
        // ตรวจสอบสถานะ
        $check_sql = "SELECT status FROM compensation_logs WHERE cancellation_id = ?";
        $stmt = $mysqli->prepare($check_sql);
        if (!$stmt) {
            throw new Exception('ไม่สามารถเตรียม SQL statement ได้: ' . $mysqli->error);
        }
        
        $stmt->bind_param("i", $cancellation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_row = $result->fetch_assoc();
        
        if (!$current_row) {
            throw new Exception('ไม่พบข้อมูลการชดเชย');
        }
        
        $current_status = $current_row['status'];
        
        if ($current_status !== 'รอยืนยัน') {
            throw new Exception('รายการนี้ไม่อยู่ในสถานะ "รอยืนยัน"');
        }
        
        // ส่งกลับสู่สถานะ "รอดำเนินการ" และเคลียร์ข้อมูลที่เสนอ
        $update_sql = "
            UPDATE compensation_logs 
            SET status = 'รอดำเนินการ',
                proposed_makeup_date = NULL,
                proposed_makeup_classroom_id = NULL,
                proposed_makeup_start_time_slot_id = NULL,
                proposed_makeup_end_time_slot_id = NULL,
                rejected_reason = ?,
                change_reason = NULL,
                updated_at = NOW()
            WHERE cancellation_id = ?
        ";
        
        $stmt = $mysqli->prepare($update_sql);
        if (!$stmt) {
            throw new Exception('ไม่สามารถเตรียม SQL statement สำหรับอัปเดต: ' . $mysqli->error);
        }
        
        $stmt->bind_param("si", $rejection_reason, $cancellation_id);
        
        if (!$stmt->execute()) {
            throw new Exception('ไม่สามารถอัปเดตสถานะการชดเชยได้: ' . $stmt->error);
        }
        
        // บันทึกประวัติการเปลี่ยนสถานะ
        logStatusChange($mysqli, $cancellation_id, 'รอยืนยัน', 'รอดำเนินการ', $user_id, 'ปฏิเสธตารางชดเชย: ' . $rejection_reason);
        
        $mysqli->commit();
        
        jsonSuccess('ปฏิเสธตารางชดเชยเรียบร้อยแล้ว รายการได้ถูกส่งกลับสู่สถานะ "รอดำเนินการ"', [
            'cancellation_id' => $cancellation_id,
            'status' => 'รอดำเนินการ',
            'rejected_reason' => $rejection_reason
        ]);
        
    } catch (Exception $e) {
        if (isset($mysqli)) {
            $mysqli->rollback();
        }
        error_log("Error in rejectCompensationSchedule: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาดในการปฏิเสธ: ' . $e->getMessage());
    }
}

/**
 * ขอเปลี่ยนวันที่ชดเชย
 */
function requestDateChange() {
    global $user_id;
    
    $cancellation_id = $_POST['cancellation_id'] ?? 0;
    $requested_date = $_POST['requested_date'] ?? '';
    $change_reason = $_POST['change_reason'] ?? '';
    
    if (!$cancellation_id || !$requested_date || !$change_reason) {
        jsonError('ข้อมูลไม่ครบถ้วน');
    }
    
    try {
        $mysqli = connectMySQLi();
        $mysqli->begin_transaction();
        
        // ดึงข้อมูลการชดเชย
        $compensation_query = "
            SELECT cl.*, ts.classroom_id, ts.start_time_slot_id, ts.end_time_slot_id
            FROM compensation_logs cl
            JOIN teaching_schedules ts ON cl.schedule_id = ts.schedule_id
            WHERE cl.cancellation_id = ? AND cl.status IN ('รอยืนยัน', 'ดำเนินการแล้ว')
        ";
        
        $stmt = $mysqli->prepare($compensation_query);
        if (!$stmt) {
            throw new Exception('ไม่สามารถเตรียม SQL statement ได้: ' . $mysqli->error);
        }
        
        $stmt->bind_param("i", $cancellation_id);
        $stmt->execute();
        $compensation = $stmt->get_result()->fetch_assoc();
        
        if (!$compensation) {
            throw new Exception('ไม่พบข้อมูลการชดเชยหรือรายการนี้ไม่สามารถขอเปลี่ยนแปลงได้');
        }
        
        // ใช้ห้องและเวลาเดิม หรือที่เสนอ (ถ้ามี)
        $classroom_id = $compensation['proposed_makeup_classroom_id'] ?: $compensation['classroom_id'];
        $start_slot = $compensation['proposed_makeup_start_time_slot_id'] ?: $compensation['start_time_slot_id'];
        $end_slot = $compensation['proposed_makeup_end_time_slot_id'] ?: $compensation['end_time_slot_id'];
        
        // ตรวจสอบความขัดแย้งในวันใหม่
        require_once 'compensation_schedule.php';
        $conflicts = checkScheduleConflicts($mysqli, $requested_date, $classroom_id, $start_slot, $end_slot, $cancellation_id);
        
        if ($conflicts['has_conflict']) {
            throw new Exception('วันที่ที่ขอมีความขัดแย้งในตารางเรียน: ' . implode(', ', $conflicts['conflicts']));
        }
        
        // อัปเดตข้อมูลการขอเปลี่ยน
        if ($compensation['status'] === 'ดำเนินการแล้ว') {
            // ถ้าดำเนินการแล้ว ต้องส่งกลับสู่สถานะ "รอยืนยัน"
            $update_sql = "
                UPDATE compensation_logs 
                SET status = 'รอยืนยัน',
                    proposed_makeup_date = ?,
                    proposed_makeup_classroom_id = ?,
                    proposed_makeup_start_time_slot_id = ?,
                    proposed_makeup_end_time_slot_id = ?,
                    change_reason = ?,
                    updated_at = NOW()
                WHERE cancellation_id = ?
            ";
            
            $stmt = $mysqli->prepare($update_sql);
            if (!$stmt) {
                throw new Exception('ไม่สามารถเตรียม SQL statement สำหรับอัปเดต: ' . $mysqli->error);
            }
            
            $stmt->bind_param("siiisi", $requested_date, $classroom_id, $start_slot, $end_slot, $change_reason, $cancellation_id);
            
            // บันทึกประวัติการเปลี่ยนสถานะ
            logStatusChange($mysqli, $cancellation_id, 'ดำเนินการแล้ว', 'รอยืนยัน', $user_id, 'ขอเปลี่ยนวันที่ชดเชย: ' . $change_reason);
            
        } else {
            // ถ้ารอยืนยันอยู่แล้ว เพียงอัปเดตข้อมูล
            $update_sql = "
                UPDATE compensation_logs 
                SET proposed_makeup_date = ?,
                    change_reason = ?,
                    updated_at = NOW()
                WHERE cancellation_id = ?
            ";
            
            $stmt = $mysqli->prepare($update_sql);
            if (!$stmt) {
                throw new Exception('ไม่สามารถเตรียม SQL statement สำหรับอัปเดต: ' . $mysqli->error);
            }
            
            $stmt->bind_param("ssi", $requested_date, $change_reason, $cancellation_id);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('ไม่สามารถบันทึกการขอเปลี่ยนแปลงได้: ' . $stmt->error);
        }
        
        $mysqli->commit();
        
        jsonSuccess('ส่งคำขอเปลี่ยนวันที่ชดเชยเรียบร้อยแล้ว', [
            'cancellation_id' => $cancellation_id,
            'status' => 'รอยืนยัน',
            'requested_date' => $requested_date,
            'change_reason' => $change_reason
        ]);
        
    } catch (Exception $e) {
        if (isset($mysqli)) {
            $mysqli->rollback();
        }
        error_log("Error in requestDateChange: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาดในการขอเปลี่ยนแปลง: ' . $e->getMessage());
    }
}
/**
 * ขอการแก้ไขรายการที่เสร็จแล้ว
 */
function requestRevision() {
    global $user_id;
    
    $cancellation_id = $_POST['cancellation_id'] ?? 0;
    $revision_reason = $_POST['revision_reason'] ?? '';
    
    if (!$cancellation_id || !$revision_reason) {
        jsonError('ข้อมูลไม่ครบถ้วน');
    }
    
    try {
        $mysqli = connectMySQLi();
        $mysqli->begin_transaction();
        
        // ตรวจสอบสถานะ
        $check_sql = "SELECT status FROM compensation_logs WHERE cancellation_id = ?";
        $stmt = $mysqli->prepare($check_sql);
        if (!$stmt) {
            throw new Exception('ไม่สามารถเตรียม SQL statement ได้: ' . $mysqli->error);
        }
        
        $stmt->bind_param("i", $cancellation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_row = $result->fetch_assoc();
        
        if (!$current_row) {
            throw new Exception('ไม่พบข้อมูลการชดเชย');
        }
        
        $current_status = $current_row['status'];
        
        if ($current_status !== 'ดำเนินการแล้ว') {
            throw new Exception('รายการนี้ไม่อยู่ในสถานะ "ดำเนินการแล้ว"');
        }
        
        // ส่งกลับสู่สถานะ "รอยืนยัน"
        $update_sql = "
            UPDATE compensation_logs 
            SET status = 'รอยืนยัน',
                change_reason = ?,
                updated_at = NOW()
            WHERE cancellation_id = ?
        ";
        
        $stmt = $mysqli->prepare($update_sql);
        if (!$stmt) {
            throw new Exception('ไม่สามารถเตรียม SQL statement สำหรับอัปเดต: ' . $mysqli->error);
        }
        
        $stmt->bind_param("si", $revision_reason, $cancellation_id);
        
        if (!$stmt->execute()) {
            throw new Exception('ไม่สามารถส่งคำขอแก้ไขได้: ' . $stmt->error);
        }
        
        // บันทึกประวัติการเปลี่ยนสถานะ
        logStatusChange($mysqli, $cancellation_id, 'ดำเนินการแล้ว', 'รอยืนยัน', $user_id, 'ขอแก้ไขการชดเชย: ' . $revision_reason);
        
        $mysqli->commit();
        
        jsonSuccess('ส่งคำขอแก้ไขเรียบร้อยแล้ว รายการได้ถูกส่งกลับสู่สถานะ "รอยืนยัน"', [
            'cancellation_id' => $cancellation_id,
            'status' => 'รอยืนยัน',
            'revision_reason' => $revision_reason
        ]);
        
    } catch (Exception $e) {
        if (isset($mysqli)) {
            $mysqli->rollback();
        }
        error_log("Error in requestRevision: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาดในการขอแก้ไข: ' . $e->getMessage());
    }
}
?>
