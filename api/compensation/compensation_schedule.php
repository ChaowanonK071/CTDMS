<?php
/**
 * ฟังก์ชันจัดการตารางการชดเชย
 */

/**
 * Preview การจัดตารางอัตโนมัติรายการเดียว
 */
function previewAutoScheduleSingle() {
    global $user_id;

    $cancellation_id = $_POST['cancellation_id'] ?? 0;

    if (!$cancellation_id) {
        jsonError('ไม่ได้ระบุ cancellation_id');
    }
    
    try {
        $mysqli = connectMySQLi();

        // ดึงข้อมูลการชดเชย
        $compensation_query = "
            SELECT 
                cl.*,
                ts.schedule_id,
                ts.classroom_id as original_classroom_id,
                ts.start_time_slot_id as original_start_slot,
                ts.end_time_slot_id as original_end_slot,
                ts.day_of_week as original_day,
                ts.academic_year_id,
                ts.user_id,
                ts.is_module_subject,
                ts.group_id,
                s.subject_name,
                s.subject_code,
                c.room_number as original_room_number,
                yl.class_year,
                yl.department,
                yl.curriculum,
                CONCAT(u.title, u.name, ' ', u.lastname) as teacher_name,
                ts.co_user_id,
                ts.co_user_id_2,
                (SELECT CONCAT(u1.title, u1.name, ' ', u1.lastname) FROM users u1 WHERE u1.user_id = ts.co_user_id) as co_teacher_name,
                (SELECT CONCAT(u2.title, u2.name, ' ', u2.lastname) FROM users u2 WHERE u2.user_id = ts.co_user_id_2) as co_teacher_name_2,
                tstart.start_time as original_start_time,
                tend.end_time as original_end_time
            FROM compensation_logs cl
            JOIN teaching_schedules ts ON cl.schedule_id = ts.schedule_id
            JOIN subjects s ON ts.subject_id = s.subject_id
            JOIN classrooms c ON ts.classroom_id = c.classroom_id
            LEFT JOIN year_levels yl ON ts.year_level_id = yl.year_level_id
            JOIN users u ON ts.user_id = u.user_id
            JOIN time_slots tstart ON ts.start_time_slot_id = tstart.time_slot_id
            JOIN time_slots tend ON ts.end_time_slot_id = tend.time_slot_id
            WHERE cl.cancellation_id = ? AND cl.status = 'รอดำเนินการ'
        ";

        $stmt = $mysqli->prepare($compensation_query);
        $stmt->bind_param("i", $cancellation_id);
        $stmt->execute();
        $compensation = $stmt->get_result()->fetch_assoc();

        if (!$compensation) {
            jsonError('ไม่พบข้อมูลการชดเชยหรือรายการนี้ไม่อยู่ในสถานะ "รอดำเนินการ"');
        }

        if ($compensation['is_module_subject'] == 1 && !empty($compensation['group_id'])) {
            $compensation['year_levels_in_group'] = [];
            $stmt_yl = $mysqli->prepare("SELECT yl.department, yl.class_year, yl.curriculum
                FROM module_group_year_levels mgyl
                JOIN year_levels yl ON mgyl.year_level_id = yl.year_level_id
                WHERE mgyl.group_id = ?");
            $stmt_yl->bind_param("i", $compensation['group_id']);
            $stmt_yl->execute();
            $result_yl = $stmt_yl->get_result();
            while ($row_yl = $result_yl->fetch_assoc()) {
                $compensation['year_levels_in_group'][] = $row_yl;
            }
            $stmt_yl->close();
        }

        // ใช้ฟังก์ชันเดียวกับการลงข้อมูลจริง
        $found_schedule = findSuitableScheduleWithConflictCheck($mysqli, $compensation);

        if (!$found_schedule) {
            jsonSuccess('Preview การจัดตารางอัตโนมัติ', [
                'cancellation_id' => $cancellation_id,
                'subject_code' => $compensation['subject_code'],
                'subject_name' => $compensation['subject_name'],
                'teacher_name' => $compensation['teacher_name'],
                'co_teacher_name' => $compensation['co_teacher_name'],
                'co_teacher_name_2' => $compensation['co_teacher_name_2'],
                'class_year' => $compensation['class_year'],
                'department' => $compensation['department'],
                'curriculum' => $compensation['curriculum'],
                'year_levels_in_group' => $compensation['year_levels_in_group'] ?? [],
                'is_module_subject' => $compensation['is_module_subject'],
                'group_id' => $compensation['group_id'],
                'cancellation_date' => $compensation['cancellation_date'],
                'cancellation_reason' => $compensation['reason'],
                'original_schedule' => [
                    'day' => $compensation['original_day'],
                    'start_slot' => $compensation['original_start_slot'],
                    'end_slot' => $compensation['original_end_slot'],
                    'start_time' => $compensation['original_start_time'],
                    'end_time' => $compensation['original_end_time'],
                    'room' => $compensation['original_room_number'],
                    'classroom_id' => $compensation['original_classroom_id']
                ],
                'suggested_schedule' => null,
                'strategy_used' => null,
                'message' => 'ไม่พบช่วงเวลาที่เหมาะสมสำหรับการชดเชย'
            ]);
        }

        // ดึงข้อมูลห้องและเวลาที่เสนอ
        $room_query = "SELECT room_number FROM classrooms WHERE classroom_id = ?";
        $stmt = $mysqli->prepare($room_query);
        $stmt->bind_param("i", $found_schedule['classroom_id']);
        $stmt->execute();
        $room_data = $stmt->get_result()->fetch_assoc();

        $time_query = "
            SELECT 
                start_slot.start_time,
                end_slot.end_time 
            FROM time_slots start_slot, time_slots end_slot
            WHERE start_slot.time_slot_id = ? 
            AND end_slot.time_slot_id = ?
        ";
        $stmt = $mysqli->prepare($time_query);
        $stmt->bind_param("ii", $found_schedule['start_slot_id'], $found_schedule['end_slot_id']);
        $stmt->execute();
        $time_data = $stmt->get_result()->fetch_assoc();

        // ตรวจสอบความขัดแย้ง
        $conflicts = checkScheduleConflicts($mysqli, $found_schedule['date'], 
                                          $found_schedule['classroom_id'],
                                          $found_schedule['start_slot_id'], 
                                          $found_schedule['end_slot_id']);

        // คำนวณการเปลี่ยนแปลงจากตารางเดิม
        $changes = [];

        if ($found_schedule['classroom_id'] != $compensation['original_classroom_id']) {
            $changes[] = [
                'type' => 'room',
                'description' => "เปลี่ยนห้องจาก {$compensation['original_room_number']} เป็น {$room_data['room_number']}",
                'from' => $compensation['original_room_number'],
                'to' => $room_data['room_number']
            ];
        }

        if ($found_schedule['start_slot_id'] != $compensation['original_start_slot'] || 
            $found_schedule['end_slot_id'] != $compensation['original_end_slot']) {
            $changes[] = [
                'type' => 'time',
                'description' => "เปลี่ยนเวลา",
                'from' => $compensation['original_start_time'] . '-' . $compensation['original_end_time'],
                'to' => $time_data['start_time'] . '-' . $time_data['end_time']
            ];
        }

        // หาวันในสัปดาห์ของวันที่เสนอ
        $day_of_week_names = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
        $day_of_week_index = date('w', strtotime($found_schedule['date']));
        $suggested_day_name = $day_of_week_names[$day_of_week_index];

        jsonSuccess('Preview การจัดตารางอัตโนมัติสำเร็จ', [
            'cancellation_id' => $cancellation_id,
            'subject_code' => $compensation['subject_code'],
            'subject_name' => $compensation['subject_name'],
            'teacher_name' => $compensation['teacher_name'],
            'co_teacher_name' => $compensation['co_teacher_name'],
            'co_teacher_name_2' => $compensation['co_teacher_name_2'],
            'class_year' => $compensation['class_year'],
            'department' => $compensation['department'],
            'curriculum' => $compensation['curriculum'],
            'year_levels_in_group' => $compensation['year_levels_in_group'] ?? [],
            'is_module_subject' => $compensation['is_module_subject'],
            'group_id' => $compensation['group_id'],
            'cancellation_date' => $compensation['cancellation_date'],
            'cancellation_reason' => $compensation['reason'],
            'original_schedule' => [
                'day' => $compensation['original_day'],
                'start_slot' => $compensation['original_start_slot'],
                'end_slot' => $compensation['original_end_slot'],
                'start_time' => $compensation['original_start_time'],
                'end_time' => $compensation['original_end_time'],
                'room' => $compensation['original_room_number'],
                'classroom_id' => $compensation['original_classroom_id']
            ],
            'suggested_schedule' => [
                'date' => $found_schedule['date'],
                'day_of_week' => $suggested_day_name,
                'start_slot' => $found_schedule['start_slot_id'],
                'end_slot' => $found_schedule['end_slot_id'],
                'start_time' => $time_data['start_time'],
                'end_time' => $time_data['end_time'],
                'room_number' => $room_data['room_number'],
                'classroom_id' => $found_schedule['classroom_id']
            ],
            'strategy_used' => $found_schedule['strategy_used'],
            'changes' => $changes,
            'conflicts' => $conflicts['conflicts'] ?? [],
            'has_conflicts' => $conflicts['has_conflict'] ?? false
        ]);

    } catch (Exception $e) {
        error_log("Error in previewAutoScheduleSingle: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาดในการ Preview: ' . $e->getMessage());
    }
}

/**
 * ยืนยันการจัดตารางอัตโนมัติรายการเดียว
 */
function confirmAutoScheduleSingle() {
    global $user_id;

    $cancellation_id = $_POST['cancellation_id'] ?? 0;
    $require_approval = $_POST['require_approval'] ?? true;

    if (!$cancellation_id) {
        jsonError('ไม่ได้ระบุ cancellation_id');
    }

    try {
        $mysqli = connectMySQLi();
        $mysqli->begin_transaction();


        // ดึงข้อมูลการชดเชย
        $compensation_query = "
            SELECT 
                cl.*,
                ts.schedule_id,
                ts.classroom_id as original_classroom_id,
                ts.start_time_slot_id as original_start_slot,
                ts.end_time_slot_id as original_end_slot,
                ts.academic_year_id,
                ts.is_module_subject,
                ts.group_id,
                s.subject_name,
                s.subject_code
            FROM compensation_logs cl
            JOIN teaching_schedules ts ON cl.schedule_id = ts.schedule_id
            JOIN subjects s ON ts.subject_id = s.subject_id
            WHERE cl.cancellation_id = ? AND cl.status = 'รอดำเนินการ'
        ";

        $stmt = $mysqli->prepare($compensation_query);
        $stmt->bind_param("i", $cancellation_id);
        $stmt->execute();
        $compensation = $stmt->get_result()->fetch_assoc();

        if (!$compensation) {
            throw new Exception('ไม่พบข้อมูลการชดเชยหรือรายการนี้ไม่อยู่ในสถานะ "รอดำเนินการ"');
        }

        if ($compensation['is_module_subject'] == 1 && !empty($compensation['group_id'])) {
            $compensation['year_levels_in_group'] = [];
            $stmt_yl = $mysqli->prepare("SELECT yl.department, yl.class_year, yl.curriculum
                FROM module_group_year_levels mgyl
                JOIN year_levels yl ON mgyl.year_level_id = yl.year_level_id
                WHERE mgyl.group_id = ?");
            $stmt_yl->bind_param("i", $compensation['group_id']);
            $stmt_yl->execute();
            $result_yl = $stmt_yl->get_result();
            while ($row_yl = $result_yl->fetch_assoc()) {
                $compensation['year_levels_in_group'][] = $row_yl;
            }
            $stmt_yl->close();
        }

        // ค้นหาตารางที่เหมาะสม
        $suitable_schedule = findSuitableScheduleWithConflictCheck($mysqli, $compensation);

        if (!$suitable_schedule) {
            throw new Exception('ไม่พบช่วงเวลาที่เหมาะสมสำหรับการชดเชย');
        }

        if ($require_approval) {
            // ส่งเข้าสู่สถานะ "รอยืนยัน"
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

            $strategy_notes = "จัดตารางอัตโนมัติ";

            $stmt = $mysqli->prepare($update_sql);
            $stmt->bind_param("siiisi", 
                $suitable_schedule['date'],
                $suitable_schedule['classroom_id'],
                $suitable_schedule['start_slot_id'],
                $suitable_schedule['end_slot_id'],
                $strategy_notes,
                $cancellation_id
            );

            if (!$stmt->execute()) {
                throw new Exception('ไม่สามารถอัปเดตสถานะการชดเชยได้');
            }

            // บันทึกประวัติการเปลี่ยนสถานะ
            logStatusChange($mysqli, $cancellation_id, 'รอดำเนินการ', 'รอยืนยัน', $user_id, $strategy_notes);

            $mysqli->commit();

            jsonSuccess('ส่งตารางชดเชยเข้าสู่ระบบอนุมัติเรียบร้อยแล้ว', [
                'cancellation_id' => $cancellation_id,
                'status' => 'รอยืนยัน',
                'proposed_date' => $suitable_schedule['date'],
                'strategy_used' => $suitable_schedule['strategy_used']
            ]);

        } else {
            // อนุมัติทันทีและสร้าง Class Session
            $result = createCompensationClassSession($mysqli, $compensation, $suitable_schedule, $user_id);

            if (!$result['success']) {
                throw new Exception($result['message']);
            }

            // อัปเดตสถานะเป็น "ดำเนินการแล้ว"
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

            $approval_notes = 'อนุมัติอัตโนมัติโดยระบบ';

            $stmt = $mysqli->prepare($update_sql);
            $stmt->bind_param("siiiisi", 
                $suitable_schedule['date'],
                $suitable_schedule['classroom_id'],
                $suitable_schedule['start_slot_id'],
                $suitable_schedule['end_slot_id'],
                $user_id,
                $approval_notes,
                $cancellation_id
            );

            if (!$stmt->execute()) {
                throw new Exception('ไม่สามารถอัปเดตสถานะการชดเชยได้');
            }

            // บันทึกประวัติการเปลี่ยนสถานะ
            logStatusChange($mysqli, $cancellation_id, 'รอดำเนินการ', 'ดำเนินการแล้ว', $user_id, $approval_notes);

            $mysqli->commit();

            jsonSuccess('จัดตารางชดเชยเสร็จสิ้น', [
                'cancellation_id' => $cancellation_id,
                'status' => 'ดำเนินการแล้ว',
                'session_id' => $result['session_id']
            ]);
        }

    } catch (Exception $e) {
        if (isset($mysqli)) {
            $mysqli->rollback();
        }
        error_log("Error in confirmAutoScheduleSingle: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

/**
 * ยืนยันการจัดตารางแบบกำหนดเอง
 */
function confirmManualSchedule() {
    global $user_id;

    $cancellation_id = $_POST['cancellation_id'] ?? 0;
    $makeup_date = $_POST['makeup_date'] ?? '';
    $makeup_classroom_id = $_POST['makeup_classroom_id'] ?? 0;
    $makeup_start_time_slot_id = $_POST['makeup_start_time_slot_id'] ?? 0;
    $makeup_end_time_slot_id = $_POST['makeup_end_time_slot_id'] ?? 0;
    $require_approval = $_POST['require_approval'] ?? false;

    if (!$cancellation_id || !$makeup_date || !$makeup_classroom_id || 
        !$makeup_start_time_slot_id || !$makeup_end_time_slot_id) {
        jsonError('ข้อมูลไม่ครบถ้วน');
    }

    try {
        $mysqli = connectMySQLi();
        $mysqli->begin_transaction();

        // ดึงข้อมูลการชดเชย
        $compensation_query = "
            SELECT cl.*, ts.schedule_id, ts.is_module_subject, ts.group_id, s.subject_code, s.subject_name
            FROM compensation_logs cl
            JOIN teaching_schedules ts ON cl.schedule_id = ts.schedule_id
            JOIN subjects s ON ts.subject_id = s.subject_id
            WHERE cl.cancellation_id = ? AND cl.status = 'รอดำเนินการ'
        ";

        $stmt = $mysqli->prepare($compensation_query);
        $stmt->bind_param("i", $cancellation_id);
        $stmt->execute();
        $compensation = $stmt->get_result()->fetch_assoc();

        if (!$compensation) {
            throw new Exception('ไม่พบข้อมูลการชดเชยหรือรายการนี้ไม่อยู่ในสถานะ "รอดำเนินการ"');
        }

        if ($compensation['is_module_subject'] == 1 && !empty($compensation['group_id'])) {
            $compensation['year_levels_in_group'] = [];
            $stmt_yl = $mysqli->prepare("SELECT yl.department, yl.class_year, yl.curriculum
                FROM module_group_year_levels mgyl
                JOIN year_levels yl ON mgyl.year_level_id = yl.year_level_id
                WHERE mgyl.group_id = ?");
            $stmt_yl->bind_param("i", $compensation['group_id']);
            $stmt_yl->execute();
            $result_yl = $stmt_yl->get_result();
            while ($row_yl = $result_yl->fetch_assoc()) {
                $compensation['year_levels_in_group'][] = $row_yl;
            }
            $stmt_yl->close();
        }

        // ตรวจสอบความพร้อม slot ด้วย getDetailedRoomAvailabilityArray
        $room_availability = getDetailedRoomAvailabilityArray($mysqli, $makeup_date, $cancellation_id);
        $found = false;
        foreach ($room_availability as $room_data) {
            if ($room_data['classroom']['classroom_id'] == $makeup_classroom_id) {
                $available_slots = array_column($room_data['available_slots'], 'slot_number');
                $slot_range = range($makeup_start_time_slot_id, $makeup_end_time_slot_id);
                $is_continuous = !array_diff($slot_range, $available_slots);
                if ($is_continuous) {
                    $found = true;
                    break;
                }
            }
        }
        if (!$found) {
            throw new Exception('ช่วงเวลาหรือห้องที่เลือกไม่ว่าง กรุณาตรวจสอบใหม่');
        }

        // ตรวจสอบความขัดแย้ง
        $conflicts = checkScheduleConflicts($mysqli, $makeup_date, $makeup_classroom_id, 
                                          $makeup_start_time_slot_id, $makeup_end_time_slot_id);

        if ($conflicts['has_conflict']) {
            throw new Exception('มีความขัดแย้งในตารางเรียน: ' . implode(', ', $conflicts['conflicts']));
        }

        if ($require_approval) {
            // ส่งเข้าสู่สถานะ "รอยืนยัน"
            $update_sql = "
                UPDATE compensation_logs 
                SET status = 'รอยืนยัน',
                    proposed_makeup_date = ?,
                    proposed_makeup_classroom_id = ?,
                    proposed_makeup_start_time_slot_id = ?,
                    proposed_makeup_end_time_slot_id = ?,
                    change_reason = 'กำหนดตารางเอง',
                    updated_at = NOW()
                WHERE cancellation_id = ?
            ";

            $stmt = $mysqli->prepare($update_sql);
            $stmt->bind_param("siiii", $makeup_date, $makeup_classroom_id, 
                            $makeup_start_time_slot_id, $makeup_end_time_slot_id, $cancellation_id);

            if (!$stmt->execute()) {
                throw new Exception('ไม่สามารถอัปเดตสถานะการชดเชยได้');
            }

            logStatusChange($mysqli, $cancellation_id, 'รอดำเนินการ', 'รอยืนยัน', $user_id, 'กำหนดตารางเอง');
            $mysqli->commit();

            jsonSuccess('ส่งตารางชดเชยเข้าสู่ระบบอนุมัติเรียบร้อยแล้ว', [
                'cancellation_id' => $cancellation_id,
                'status' => 'รอยืนยัน'
            ]);

        } else {
            // อนุมัติทันทีและสร้าง Class Session
            $suitable_schedule = [
                'date' => $makeup_date,
                'classroom_id' => $makeup_classroom_id,
                'start_slot_id' => $makeup_start_time_slot_id,
                'end_slot_id' => $makeup_end_time_slot_id
            ];

            $result = createCompensationClassSession($mysqli, $compensation, $suitable_schedule, $user_id);

            if (!$result['success']) {
                throw new Exception($result['message']);
            }

            $update_sql = "
                UPDATE compensation_logs 
                SET status = 'ดำเนินการแล้ว',
                    makeup_date = ?,
                    makeup_classroom_id = ?,
                    makeup_start_time_slot_id = ?,
                    makeup_end_time_slot_id = ?,
                    approved_by = ?,
                    approved_at = NOW(),
                    approval_notes = 'อนุมัติทันทีจากการกำหนดเอง',
                    updated_at = NOW()
                WHERE cancellation_id = ?
            ";

            $stmt = $mysqli->prepare($update_sql);
            $stmt->bind_param("siiiii", $makeup_date, $makeup_classroom_id, 
                            $makeup_start_time_slot_id, $makeup_end_time_slot_id, $user_id, $cancellation_id);

            if (!$stmt->execute()) {
                throw new Exception('ไม่สามารถอัปเดตสถานะการชดเชยได้');
            }

            logStatusChange($mysqli, $cancellation_id, 'รอดำเนินการ', 'ดำเนินการแล้ว', $user_id, 'อนุมัติทันทีจากการกำหนดเอง');
            $mysqli->commit();

            jsonSuccess('จัดตารางชดเชยเสร็จสิ้น', [
                'cancellation_id' => $cancellation_id,
                'status' => 'ดำเนินการแล้ว',
                'session_id' => $result['session_id']
            ]);
        }

    } catch (Exception $e) {
        if (isset($mysqli)) {
            $mysqli->rollback();
        }
        error_log("Error in confirmManualSchedule: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

/**
 * จัดตารางชดเชยอัตโนมัติทั้งหมด
 */
function autoScheduleAllCompensations() {
    global $user_id;

    $academic_year_id = $_POST['academic_year_id'] ?? 0;
    $selection_type = $_POST['selection_type'] ?? 'self';
    $selected_teacher_id = $_POST['selected_teacher_id'] ?? null;

    if (!$academic_year_id) {
        jsonError('ไม่ได้ระบุ academic_year_id');
    }

    try {
        $mysqli = connectMySQLi();
        $mysqli->begin_transaction();

        $is_admin = isAdmin($user_id);

        $teacher_filter = "";
        $bind_params = [$academic_year_id];
        $bind_types = "i";
        $scope_message = "";
        $target_teacher_id = null;

        if (!$is_admin) {
            if ($selection_type !== 'self') {
                throw new Exception('คุณไม่มีสิทธิ์จัดตารางให้อาจารย์ท่านอื่น');
            }
            $selection_type = 'self';
            $target_teacher_id = $user_id;
            $teacher_filter = "AND ts.user_id = ?";
            $bind_params[] = $user_id;
            $bind_types .= "i";
            $scope_message = "รายการของตัวเอง";
        } else {
            switch ($selection_type) {
                case 'self':
                    $target_teacher_id = $user_id;
                    $teacher_filter = "AND ts.user_id = ?";
                    $bind_params[] = $user_id;
                    $bind_types .= "i";
                    $scope_message = "รายการของตัวเอง";
                    break;
                case 'other':
                    if (!$selected_teacher_id) {
                        throw new Exception('ไม่ได้ระบุอาจารย์ที่ต้องการจัดตาราง');
                    }
                    $target_teacher_id = $selected_teacher_id;
                    $teacher_filter = "AND ts.user_id = ?";
                    $bind_params[] = $selected_teacher_id;
                    $bind_types .= "i";
                    $teacher_name_query = "SELECT CONCAT(COALESCE(title, ''), name, ' ', lastname) as teacher_name FROM users WHERE user_id = ?";
                    $stmt = $mysqli->prepare($teacher_name_query);
                    $stmt->bind_param("i", $selected_teacher_id);
                    $stmt->execute();
                    $teacher_info = $stmt->get_result()->fetch_assoc();
                    $scope_message = "รายการของ " . ($teacher_info['teacher_name'] ?? 'อาจารย์ที่เลือก');
                    break;
                case 'all':
                    $scope_message = "รายการของอาจารย์ทุกคน";
                    break;
                default:
                    throw new Exception('ประเภทการเลือกไม่ถูกต้อง');
            }
        }

        $compensations_query = "
            SELECT 
                cl.cancellation_id,
                cl.cancellation_date,
                cl.schedule_id,
                ts.classroom_id as original_classroom_id,
                ts.start_time_slot_id as original_start_slot,
                ts.end_time_slot_id as original_end_slot,
                ts.day_of_week as original_day,
                ts.academic_year_id,
                ts.user_id,
                ts.is_module_subject,
                ts.group_id,
                s.subject_name,
                s.subject_code,
                CONCAT(COALESCE(u.title, ''), u.name, ' ', u.lastname) as teacher_name
            FROM compensation_logs cl
            JOIN teaching_schedules ts ON cl.schedule_id = ts.schedule_id
            JOIN subjects s ON ts.subject_id = s.subject_id
            JOIN users u ON ts.user_id = u.user_id
            WHERE ts.academic_year_id = ? 
            AND cl.status = 'รอดำเนินการ'
            AND cl.is_makeup_required = 1
            {$teacher_filter}
            ORDER BY cl.cancellation_date ASC, u.name ASC
        ";

        $stmt = $mysqli->prepare($compensations_query);
        if (!$stmt) {
            throw new Exception('Database error: ' . $mysqli->error);
        }

        $stmt->bind_param($bind_types, ...$bind_params);
        $stmt->execute();
        $compensations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (count($compensations) === 0) {
            $mysqli->rollback();
            jsonSuccess("ไม่มีรายการที่ต้องจัดตารางชดเชย ({$scope_message})", [
                'total' => 0,
                'successful' => 0,
                'failed' => 0,
                'details' => [],
                'scope' => $scope_message,
                'selection_type' => $selection_type,
                'target_teacher_id' => $target_teacher_id,
                'message' => 'ไม่พบรายการที่อยู่ในสถานะ "รอดำเนินการ" ที่ต้องการการชดเชย',
                'permission_info' => [
                    'is_admin' => $is_admin,
                    'can_schedule_all' => $is_admin,
                    'current_user_id' => $user_id
                ]
            ]);
            return;
        }

        $scheduled_slots = [];
        $results = [
            'successful' => 0,
            'failed' => 0,
            'total' => count($compensations),
            'scope' => $scope_message,
            'selection_type' => $selection_type,
            'target_teacher_id' => $target_teacher_id,
            'details' => [],
            'permission_info' => [
                'is_admin' => $is_admin,
                'can_schedule_all' => $is_admin,
                'current_user_id' => $user_id
            ]
        ];

        foreach ($compensations as &$compensation) {
            try {
                if ($compensation['is_module_subject'] == 1 && !empty($compensation['group_id'])) {
                    $compensation['year_levels_in_group'] = [];
                    $stmt_yl = $mysqli->prepare("SELECT yl.department, yl.class_year, yl.curriculum
                        FROM module_group_year_levels mgyl
                        JOIN year_levels yl ON mgyl.year_level_id = yl.year_level_id
                        WHERE mgyl.group_id = ?");
                    $stmt_yl->bind_param("i", $compensation['group_id']);
                    $stmt_yl->execute();
                    $result_yl = $stmt_yl->get_result();
                    while ($row_yl = $result_yl->fetch_assoc()) {
                        $compensation['year_levels_in_group'][] = $row_yl;
                    }
                    $stmt_yl->close();
                }

                $suitable_schedule = findSuitableScheduleWithConflictCheck($mysqli, $compensation, $scheduled_slots);

                if ($suitable_schedule) {
                    // ตรวจสอบความพร้อม slot ด้วย getDetailedRoomAvailabilityArray
                    $room_availability = getDetailedRoomAvailabilityArray($mysqli, $suitable_schedule['date'], $compensation['cancellation_id']);
                    $found = false;
                    foreach ($room_availability as $room_data) {
                        if ($room_data['classroom']['classroom_id'] == $suitable_schedule['classroom_id']) {
                            $available_slots = array_column($room_data['available_slots'], 'slot_number');
                            $slot_range = range($suitable_schedule['start_slot_id'], $suitable_schedule['end_slot_id']);
                            $is_continuous = !array_diff($slot_range, $available_slots);
                            if ($is_continuous) {
                                $found = true;
                                break;
                            }
                        }
                    }
                    if (!$found) {
                        throw new Exception('ช่วงเวลาหรือห้องที่เลือกไม่ว่าง กรุณาตรวจสอบใหม่');
                    }

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

                    $strategy_notes = "จัดตารางอัตโนมัติ ({$scope_message}) - กลยุทธ์: " . $suitable_schedule['strategy_used'];

                    $stmt = $mysqli->prepare($update_sql);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $mysqli->error);
                    }

                    $stmt->bind_param("siiisi", 
                        $suitable_schedule['date'],
                        $suitable_schedule['classroom_id'],
                        $suitable_schedule['start_slot_id'],
                        $suitable_schedule['end_slot_id'],
                        $strategy_notes,
                        $compensation['cancellation_id']
                    );

                    if ($stmt->execute()) {
                        logStatusChange($mysqli, $compensation['cancellation_id'], 'รอดำเนินการ', 'รอยืนยัน', $user_id, $strategy_notes);

                        $slot_key = $suitable_schedule['date'] . '_' . 
                                   $suitable_schedule['classroom_id'] . '_' . 
                                   $suitable_schedule['start_slot_id'] . '_' . 
                                   $suitable_schedule['end_slot_id'];
                        $scheduled_slots[$slot_key] = true;

                        $results['successful']++;
                        $results['details'][] = [
                            'cancellation_id' => $compensation['cancellation_id'],
                            'subject_code' => $compensation['subject_code'],
                            'teacher_name' => $compensation['teacher_name'],
                            'teacher_id' => $compensation['user_id'],
                            'is_module_subject' => $compensation['is_module_subject'],
                            'group_id' => $compensation['group_id'],
                            'year_levels_in_group' => $compensation['year_levels_in_group'] ?? [],
                            'status' => 'success',
                            'strategy_used' => $suitable_schedule['strategy_used'],
                            'makeup_date' => $suitable_schedule['date'],
                            'message' => 'จัดตารางสำเร็จ - ส่งเข้าสู่ระบบอนุมัติ'
                        ];
                    } else {
                        throw new Exception('ไม่สามารถอัปเดตสถานะได้: ' . $mysqli->error);
                    }
                } else {
                    $results['failed']++;
                    $results['details'][] = [
                        'cancellation_id' => $compensation['cancellation_id'],
                        'subject_code' => $compensation['subject_code'],
                        'teacher_name' => $compensation['teacher_name'],
                        'teacher_id' => $compensation['user_id'],
                        'is_module_subject' => $compensation['is_module_subject'],
                        'group_id' => $compensation['group_id'],
                        'year_levels_in_group' => $compensation['year_levels_in_group'] ?? [],
                        'status' => 'failed',
                        'reason' => 'ไม่พบช่วงเวลาที่เหมาะสม',
                        'message' => 'ระบบไม่สามารถหาช่วงเวลาที่ว่างเหมาะสำหรับการชดเชยได้'
                    ];
                }

            } catch (Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'cancellation_id' => $compensation['cancellation_id'],
                    'subject_code' => $compensation['subject_code'],
                    'teacher_name' => $compensation['teacher_name'],
                    'teacher_id' => $compensation['user_id'],
                    'is_module_subject' => $compensation['is_module_subject'],
                    'group_id' => $compensation['group_id'],
                    'year_levels_in_group' => $compensation['year_levels_in_group'] ?? [],
                    'status' => 'error',
                    'reason' => $e->getMessage(),
                    'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
                ];
            }
        }
        unset($compensation);

        $mysqli->commit();

        $message = "จัดตารางชดเชยอัตโนมัติเสร็จสิ้น";
        $message .= "\nขอบเขต: {$scope_message}";
        $message .= "\nสถิติ: ทั้งหมด {$results['total']} รายการ";
        $message .= "\nสำเร็จ: {$results['successful']} รายการ";
        $message .= "\nล้มเหลว: {$results['failed']} รายการ";

        if ($results['successful'] > 0) {
            $message .= "\n\nรายการที่จัดสำเร็จจะถูกส่งเข้าสู่ระบบอนุมัติ (สถานะ: รอยืนยัน)";
            $message .= "\nคุณสามารถตรวจสอบและยืนยันได้ในหน้าจัดการการชดเชย";
        }

        if ($results['failed'] > 0) {
            $message .= "\n\nรายการที่ล้มเหลวต้องจัดตารางด้วยตนเอง";
        }

        jsonSuccess($message, $results);

    } catch (Exception $e) {
        if (isset($mysqli)) {
            $mysqli->rollback();
        }
        error_log("Error in autoScheduleAllCompensations: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาดในการจัดตารางอัตโนมัติ: ' . $e->getMessage());
    }
}

/**
 * ตรวจสอบความขัดแย้งในตารางสำหรับ user เฉพาะ
 */
function checkScheduleConflictsForUser($mysqli, $date, $classroom_id, $start_slot, $end_slot, $teacher_id) {
    $conflicts = [];
    $has_conflict = false;
    
    // ตรวจสอบ class sessions ที่มีอยู่แล้วในห้องเดียวกัน
    $session_conflict = "
        SELECT COUNT(*) as conflict_count,
               GROUP_CONCAT(DISTINCT s.subject_code) as conflicting_subjects
        FROM class_sessions cs
        JOIN teaching_schedules ts ON cs.schedule_id = ts.schedule_id
        JOIN subjects s ON ts.subject_id = s.subject_id
        WHERE cs.session_date = ? 
        AND cs.actual_classroom_id = ?
        AND (
            (cs.actual_start_time_slot_id <= ? AND cs.actual_end_time_slot_id >= ?) OR
            (cs.actual_start_time_slot_id <= ? AND cs.actual_end_time_slot_id >= ?) OR
            (cs.actual_start_time_slot_id >= ? AND cs.actual_end_time_slot_id <= ?)
        )
    ";
    
    $stmt = $mysqli->prepare($session_conflict);
    $stmt->bind_param("siiiiiii", $date, $classroom_id,
                     $start_slot, $start_slot, $end_slot, $end_slot,
                     $start_slot, $end_slot);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['conflict_count'] > 0) {
        $conflicts[] = "ห้องถูกใช้แล้ว: " . $result['conflicting_subjects'];
        $has_conflict = true;
    }
    
    // ตรวจสอบตารางปกติของอาจารย์ในวันนั้น
    $day_of_week_names = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
    $day_of_week_index = date('w', strtotime($date));
    $thai_day = $day_of_week_names[$day_of_week_index];
    
    $teacher_conflict = "
        SELECT COUNT(*) as conflict_count,
               GROUP_CONCAT(DISTINCT s.subject_code) as conflicting_subjects
        FROM teaching_schedules ts
        JOIN subjects s ON ts.subject_id = s.subject_id
        WHERE ts.user_id = ? 
        AND ts.day_of_week = ?
        AND ts.is_active = 1
        AND (
            (ts.start_time_slot_id <= ? AND ts.end_time_slot_id >= ?) OR
            (ts.start_time_slot_id <= ? AND ts.end_time_slot_id >= ?) OR
            (ts.start_time_slot_id >= ? AND ts.end_time_slot_id <= ?)
        )
    ";
    
    $stmt = $mysqli->prepare($teacher_conflict);
    $stmt->bind_param("isiiiiii", $teacher_id, $thai_day,
                     $start_slot, $start_slot, $end_slot, $end_slot,
                     $start_slot, $end_slot);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['conflict_count'] > 0) {
        $conflicts[] = "อาจารย์มีตารางสอน: " . $result['conflicting_subjects'];
        $has_conflict = true;
    }
    
    // ตรวจสอบ class sessions อื่นของอาจารย์ในวันนั้น
    $teacher_session_conflict = "
        SELECT COUNT(*) as conflict_count,
               GROUP_CONCAT(DISTINCT s.subject_code) as conflicting_subjects
        FROM class_sessions cs
        JOIN teaching_schedules ts ON cs.schedule_id = ts.schedule_id
        JOIN subjects s ON ts.subject_id = s.subject_id
        WHERE cs.session_date = ? 
        AND ts.user_id = ?
        AND (
            (cs.actual_start_time_slot_id <= ? AND cs.actual_end_time_slot_id >= ?) OR
            (cs.actual_start_time_slot_id <= ? AND cs.actual_end_time_slot_id >= ?) OR
            (cs.actual_start_time_slot_id >= ? AND cs.actual_end_time_slot_id <= ?)
        )
    ";
    
    $stmt = $mysqli->prepare($teacher_session_conflict);
    $stmt->bind_param("siiiiiii", $date, $teacher_id,
                     $start_slot, $start_slot, $end_slot, $end_slot,
                     $start_slot, $end_slot);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['conflict_count'] > 0) {
        $conflicts[] = "อาจารย์มีการสอนชดเชย: " . $result['conflicting_subjects'];
        $has_conflict = true;
    }
    
    // ตรวจสอบวันหยุดราชการ
    $holiday_conflict = "
        SELECT COUNT(*) as holiday_count, holiday_name
        FROM public_holidays 
        WHERE holiday_date = ? AND is_active = 1
    ";
    
    $stmt = $mysqli->prepare($holiday_conflict);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['holiday_count'] > 0) {
        $conflicts[] = "วันหยุดราชการ: " . $result['holiday_name'];
        $has_conflict = true;
    }
    
    return [
        'has_conflict' => $has_conflict,
        'conflicts' => $conflicts
    ];
}

/**
 * ค้นหาช่วงเวลาที่เหมาะสมสำหรับการชดเชย
 */
function findSuitableScheduleWithConflictCheck($mysqli, $compensation, $already_scheduled = []) {
    // ดึงข้อมูลปีการศึกษา
    $academic_query = "SELECT start_date, end_date FROM academic_years WHERE academic_year_id = ?";
    $stmt = $mysqli->prepare($academic_query);
    $stmt->bind_param("i", $compensation['academic_year_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $academic = $result->fetch_assoc();
    if (!$academic) return null;

    $start_search_date = $academic['start_date'];
    $end_search_date = $academic['end_date'];

    $teacher_id = $compensation['user_id'] ?? null;
    if (!$teacher_id) {
        $teacher_query = "SELECT user_id FROM teaching_schedules WHERE schedule_id = ?";
        $stmt = $mysqli->prepare($teacher_query);
        $stmt->bind_param("i", $compensation['schedule_id']);
        $stmt->execute();
        $teacher_result = $stmt->get_result();
        $teacher_data = $teacher_result->fetch_assoc();
        if (!$teacher_data) return null;
        $teacher_id = $teacher_data['user_id'];
    }

    $required_slots = $compensation['original_end_slot'] - $compensation['original_start_slot'] + 1;
    $original_classroom_id = $compensation['original_classroom_id'] ?? null;

    $current_date = new DateTime($start_search_date);
    $end_date = new DateTime($end_search_date);

    while ($current_date <= $end_date) {
        $check_date = $current_date->format('Y-m-d');
        $day_of_week = $current_date->format('w');


        // ดึงข้อมูลความพร้อมห้องทั้งหมด
        $room_availability = getDetailedRoomAvailabilityArray($mysqli, $check_date, $compensation['cancellation_id']);

        // 1. พยายามหาช่วงเวลาว่างใน "ห้องเดิม" ก่อน
        if ($original_classroom_id) {
            foreach ($room_availability as $room_data) {
                if ($room_data['classroom']['classroom_id'] != $original_classroom_id) continue;
                if ($room_data['availability_status'] === 'holiday' || $room_data['availability_status'] === 'occupied') continue;

                $available_slots = array_column($room_data['available_slots'], 'slot_number');
                $slot_count = count($available_slots);
                for ($i = 0; $i <= $slot_count - $required_slots; $i++) {
                    $slot_range = array_slice($available_slots, $i, $required_slots);
                    $is_continuous = true;
                    for ($j = 1; $j < count($slot_range); $j++) {
                        if ($slot_range[$j] != $slot_range[$j-1] + 1) {
                            $is_continuous = false;
                            break;
                        }
                    }
                    if ($is_continuous) {
                        $slot_key = $check_date . '_' . $room_data['classroom']['classroom_id'] . '_' . $slot_range[0] . '_' . end($slot_range);
                        if (!isset($already_scheduled[$slot_key])) {
                            return [
                                'date' => $check_date,
                                'classroom_id' => $room_data['classroom']['classroom_id'],
                                'start_slot_id' => $slot_range[0],
                                'end_slot_id' => end($slot_range),
                                'strategy_used' => 'original_room_first'
                            ];
                        }
                    }
                }
            }
        }

        // 2. ถ้าไม่เจอในห้องเดิม ให้หาห้องอื่น
        foreach ($room_availability as $room_data) {
            if ($original_classroom_id && $room_data['classroom']['classroom_id'] == $original_classroom_id) continue;
            if ($room_data['availability_status'] === 'holiday' || $room_data['availability_status'] === 'occupied') continue;

            $available_slots = array_column($room_data['available_slots'], 'slot_number');
            $slot_count = count($available_slots);
            for ($i = 0; $i <= $slot_count - $required_slots; $i++) {
                $slot_range = array_slice($available_slots, $i, $required_slots);
                $is_continuous = true;
                for ($j = 1; $j < count($slot_range); $j++) {
                    if ($slot_range[$j] != $slot_range[$j-1] + 1) {
                        $is_continuous = false;
                        break;
                    }
                }
                if ($is_continuous) {
                    $slot_key = $check_date . '_' . $room_data['classroom']['classroom_id'] . '_' . $slot_range[0] . '_' . end($slot_range);
                    if (!isset($already_scheduled[$slot_key])) {
                        return [
                            'date' => $check_date,
                            'classroom_id' => $room_data['classroom']['classroom_id'],
                            'start_slot_id' => $slot_range[0],
                            'end_slot_id' => end($slot_range),
                            'strategy_used' => 'other_room'
                        ];
                    }
                }
            }
        }

        $current_date->add(new DateInterval('P1D'));
    }
    return null;
}

function getDetailedRoomAvailabilityArray($mysqli, $date, $cancellation_id, $academic_year_id = null)
{
    $result = [];

    // ดึงข้อมูลการชดเชยและ schedule_id พร้อม academic_year_id
    $yl_sql = "SELECT ts.schedule_id, ts.year_level_id, ts.user_id, ts.co_user_id, ts.co_user_id_2, ts.is_module_subject, ts.group_id, ts.academic_year_id
               FROM compensation_logs cl
               JOIN teaching_schedules ts ON cl.schedule_id = ts.schedule_id
               WHERE cl.cancellation_id = ?";
    $yl_stmt = $mysqli->prepare($yl_sql);
    $yl_stmt->bind_param("i", $cancellation_id);
    $yl_stmt->execute();
    $yl_result = $yl_stmt->get_result();
    $yl_row = $yl_result->fetch_assoc();
    $year_level_id = $yl_row['year_level_id'] ?? null;
    $teacher_id = $yl_row['user_id'] ?? null;
    $co_user_id = $yl_row['co_user_id'] ?? null;
    $co_user_id_2 = $yl_row['co_user_id_2'] ?? null;
    $is_module_subject = $yl_row['is_module_subject'] ?? 0;
    $group_id = $yl_row['group_id'] ?? null;
    $schedule_academic_year_id = $yl_row['academic_year_id'] ?? null;
    $yl_stmt->close();

    // ถ้าไม่ส่ง academic_year_id ให้ใช้จาก schedule
    if ($academic_year_id === null) {
        $academic_year_id = $schedule_academic_year_id;
    }

    // กรณีโมดูล: ดึง year_level_id ทั้งหมดในกลุ่ม
    $related_year_level_ids = [];
    if ($is_module_subject == 1 && $group_id) {
        $stmt_yls = $mysqli->prepare("SELECT year_level_id FROM module_group_year_levels WHERE group_id = ?");
        $stmt_yls->bind_param("i", $group_id);
        $stmt_yls->execute();
        $res_yls = $stmt_yls->get_result();
        while ($row = $res_yls->fetch_assoc()) {
            $related_year_level_ids[] = $row['year_level_id'];
        }
        $stmt_yls->close();
    } elseif ($year_level_id) {
        $related_year_level_ids[] = $year_level_id;
    }

    // ตรวจสอบวันหยุด
    $holiday_sql = "SELECT holiday_name FROM public_holidays WHERE holiday_date = ? AND is_active = 1";
    $holiday_stmt = $mysqli->prepare($holiday_sql);
    $holiday_stmt->bind_param("s", $date);
    $holiday_stmt->execute();
    $holiday_result = $holiday_stmt->get_result();
    $holiday = $holiday_result->fetch_assoc();
    $holiday_stmt->close();

    // ดึงข้อมูล time slots ทั้งหมด
    $timeslots_sql = "SELECT time_slot_id, slot_number, start_time, end_time FROM time_slots ORDER BY slot_number";
    $timeslots_stmt = $mysqli->prepare($timeslots_sql);
    $timeslots_stmt->execute();
    $timeslots_result = $timeslots_stmt->get_result();
    $timeslots = [];
    while ($row = $timeslots_result->fetch_assoc()) {
        $timeslots[] = $row;
    }
    $timeslots_stmt->close();

    // ดึงข้อมูลห้องเรียนทั้งหมด
    $classrooms_sql = "SELECT classroom_id, room_number, building, capacity FROM classrooms ORDER BY room_number";
    $classrooms_stmt = $mysqli->prepare($classrooms_sql);
    $classrooms_stmt->execute();
    $classrooms_result = $classrooms_stmt->get_result();
    $classrooms = [];
    while ($row = $classrooms_result->fetch_assoc()) {
        $classrooms[] = $row;
    }
    $classrooms_stmt->close();

    // เตรียม array อาจารย์ร่วม
    $teacher_ids = [];
    if ($teacher_id) $teacher_ids[] = $teacher_id;
    if ($co_user_id) $teacher_ids[] = $co_user_id;
    if ($co_user_id_2) $teacher_ids[] = $co_user_id_2;

    // เตรียม prepared statements สำหรับตรวจสอบ compensation_logs (proposed และ makeup)
    $proposed_sql = "SELECT COUNT(*) AS cnt FROM compensation_logs WHERE cancellation_id != ? AND proposed_makeup_date = ? AND proposed_makeup_classroom_id = ? AND status = 'รอยืนยัน' AND proposed_makeup_start_time_slot_id <= ? AND proposed_makeup_end_time_slot_id >= ?";
    $stmt_proposed = $mysqli->prepare($proposed_sql);

    $makeup_sql = "SELECT COUNT(*) AS cnt FROM compensation_logs WHERE cancellation_id != ? AND makeup_date = ? AND makeup_classroom_id = ? AND status = 'ดำเนินการแล้ว' AND makeup_start_time_slot_id <= ? AND makeup_end_time_slot_id >= ?";
    $stmt_makeup = $mysqli->prepare($makeup_sql);

    foreach ($classrooms as $classroom) {
        $room_data = [
            'classroom' => $classroom,
            'availability_status' => 'available',
            'available_slots' => [],
            'occupied_slots' => [],
            'holiday' => $holiday ? $holiday : null
        ];

        foreach ($timeslots as $slot) {
            $conflicts = [];

            // 1. เช็คห้องว่างจาก class_sessions
            $sql_room = "SELECT COUNT(*) AS cnt
                         FROM class_sessions cs
                         JOIN teaching_schedules ts ON cs.schedule_id = ts.schedule_id
                         WHERE cs.actual_classroom_id = ?
                         AND cs.session_date = ?
                         AND ts.academic_year_id = ?
                         AND cs.actual_start_time_slot_id <= ? AND cs.actual_end_time_slot_id >= ?";
            $stmt_room = $mysqli->prepare($sql_room);
            $stmt_room->bind_param("isiii", $classroom['classroom_id'], $date, $academic_year_id, $slot['slot_number'], $slot['slot_number']);
            $stmt_room->execute();
            $room_busy = $stmt_room->get_result()->fetch_assoc();
            $stmt_room->close();
            if ($room_busy['cnt'] > 0) {
                $conflicts[] = 'ห้องมีการใช้งานใน class_sessions';
            }

            // 2. เช็ค year_level_id ทั้งหมดในกลุ่ม 
            if (!empty($related_year_level_ids)) {
                $in_year_levels = implode(',', array_map('intval', $related_year_level_ids));
                $sql_year_multi = "SELECT COUNT(*) AS cnt
                                    FROM class_sessions cs
                                    JOIN teaching_schedules ts ON cs.schedule_id = ts.schedule_id
                                    WHERE ts.year_level_id IN ($in_year_levels)
                                    AND ts.academic_year_id = ?
                                    AND cs.session_date = ?
                                    AND cs.actual_start_time_slot_id <= ? AND cs.actual_end_time_slot_id >= ?";
                $stmt_year_multi = $mysqli->prepare($sql_year_multi);
                $stmt_year_multi->bind_param("isii", $academic_year_id, $date, $slot['slot_number'], $slot['slot_number']);
                $stmt_year_multi->execute();
                $year_busy_multi = $stmt_year_multi->get_result()->fetch_assoc();
                $stmt_year_multi->close();
                if ($year_busy_multi['cnt'] > 0) {
                    $conflicts[] = 'ชั้นปีในกลุ่มมีการใช้งานใน class_sessions';
                }
            }

            // 3. เช็คอาจารย์หลักและอาจารย์ร่วมว่างจาก class_sessions
            foreach ($teacher_ids as $tid) {
                $sql_teacher = "SELECT COUNT(*) AS cnt
                    FROM class_sessions cs
                    JOIN teaching_schedules ts ON cs.schedule_id = ts.schedule_id
                    WHERE (ts.user_id = ? OR ts.co_user_id = ? OR ts.co_user_id_2 = ?)
                    AND ts.academic_year_id = ?
                    AND cs.session_date = ?
                    AND cs.actual_start_time_slot_id <= ? AND cs.actual_end_time_slot_id >= ?";
                $stmt_teacher = $mysqli->prepare($sql_teacher);
                $stmt_teacher->bind_param("iiiisii", $tid, $tid, $tid, $academic_year_id, $date, $slot['slot_number'], $slot['slot_number']);
                $stmt_teacher->execute();
                $teacher_busy = $stmt_teacher->get_result()->fetch_assoc();
                $stmt_teacher->close();
                if ($teacher_busy['cnt'] > 0) {
                    $conflicts[] = "อาจารย์ร่วม (user_id: $tid) มีการใช้งานใน class_sessions";
                }
            }

            // 3.5 ตรวจสอบการเสนอ/การอนุมัติชดเชยจาก compensation_logs (proposed / makeup) - ป้องกันเลือกซ้ำกับที่เคยเสนอ/อนุมัติแล้ว
            if ($stmt_proposed) {
                $stmt_proposed->bind_param("isiii", $cancellation_id, $date, $classroom['classroom_id'], $slot['slot_number'], $slot['slot_number']);
                $stmt_proposed->execute();
                $comp_proposed = $stmt_proposed->get_result()->fetch_assoc();
                if ($comp_proposed['cnt'] > 0) {
                    $conflicts[] = 'มีการเสนอการชดเชยในช่วงเวลานี้ (proposed)';
                }
            }
            if ($stmt_makeup) {
                $stmt_makeup->bind_param("isiii", $cancellation_id, $date, $classroom['classroom_id'], $slot['slot_number'], $slot['slot_number']);
                $stmt_makeup->execute();
                $comp_makeup = $stmt_makeup->get_result()->fetch_assoc();
                if ($comp_makeup['cnt'] > 0) {
                    $conflicts[] = 'มีการอนุมัติการชดเชยในช่วงเวลานี้ (makeup)';
                }
            }

            // 4. ตรวจสอบวันหยุด
            if ($holiday) {
                $conflicts[] = 'วันหยุดราชการ: ' . $holiday['holiday_name'];
            }

            // 5. สรุป
            if (count($conflicts) > 0) {
                $room_data['occupied_slots'][] = array_merge($slot, [
                    'is_available' => false,
                    'conflicts' => $conflicts
                ]);
            } else {
                $room_data['available_slots'][] = array_merge($slot, [
                    'is_available' => true,
                    'conflicts' => []
                ]);
            }
        }

        // กำหนดสถานะห้อง
        $total_slots = count($timeslots);
        $available_count = count($room_data['available_slots']);
        if ($holiday) {
            $room_data['availability_status'] = 'holiday';
        } elseif ($available_count === 0) {
            $room_data['availability_status'] = 'occupied';
        } elseif ($available_count === $total_slots) {
            $room_data['availability_status'] = 'available';
        } else {
            $room_data['availability_status'] = 'partially_available';
        }

        $result[] = $room_data;
    }

    // ปิด prepared statements หากเปิดไว้
    if ($stmt_proposed) $stmt_proposed->close();
    if ($stmt_makeup) $stmt_makeup->close();

    return $result;
}

/**
 * ตรวจสอบความขัดแย้งในตาราง
 */
function checkScheduleConflicts($mysqli, $date, $classroom_id, $start_slot, $end_slot, $exclude_cancellation_id = 0) {
    $conflicts = [];
    $has_conflict = false;
    
    try {
        // หาวันในสัปดาห์เป็นภาษาไทย
        $day_of_week_num = date('w', strtotime($date));
        $thai_days = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
        $thai_day = $thai_days[$day_of_week_num];
        
        // 1. ตรวจสอบตารางเรียนปกติ
        $regular_conflict = "
            SELECT s.subject_code, s.subject_name,
                   CONCAT(u.title, u.name, ' ', u.lastname) as teacher_name
            FROM teaching_schedules ts
            JOIN subjects s ON ts.subject_id = s.subject_id
            JOIN users u ON ts.user_id = u.user_id
            WHERE ts.classroom_id = ?
            AND ts.day_of_week = ?
            AND ts.is_active = 1
            AND (
                (ts.start_time_slot_id <= ? AND ts.end_time_slot_id >= ?) OR
                (ts.start_time_slot_id <= ? AND ts.end_time_slot_id >= ?) OR
                (ts.start_time_slot_id >= ? AND ts.end_time_slot_id <= ?)
            )
        ";
        
        $stmt = $mysqli->prepare($regular_conflict);
        $stmt->bind_param("isiiiiii", $classroom_id, $thai_day,
                         $start_slot, $start_slot, $end_slot, $end_slot,
                         $start_slot, $end_slot);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $conflicts[] = "มีการเรียน: {$row['subject_code']} - {$row['teacher_name']}";
            $has_conflict = true;
        }
        
        // 2. ตรวจสอบการชดเชยที่อนุมัติแล้วและรอยืนยัน (ใช้ proposed_*)
        $compensation_conflict = "
            SELECT s.subject_code, s.subject_name,
                CONCAT(u.title, u.name, ' ', u.lastname) as teacher_name
            FROM compensation_logs cl
            JOIN teaching_schedules ts ON cl.schedule_id = ts.schedule_id
            JOIN subjects s ON ts.subject_id = s.subject_id
            JOIN users u ON ts.user_id = u.user_id
            WHERE (
                (cl.proposed_makeup_date = ? AND cl.proposed_makeup_classroom_id = ? AND cl.status = 'รอยืนยัน')
                OR
                (cl.makeup_date = ? AND cl.makeup_classroom_id = ? AND cl.status = 'ดำเนินการแล้ว')
            )
            AND cl.cancellation_id != ?
            AND (
                (cl.proposed_makeup_start_time_slot_id <= ? AND cl.proposed_makeup_end_time_slot_id >= ?) OR
                (cl.proposed_makeup_start_time_slot_id <= ? AND cl.proposed_makeup_end_time_slot_id >= ?) OR
                (cl.proposed_makeup_start_time_slot_id >= ? AND cl.proposed_makeup_end_time_slot_id <= ?)
                OR
                (cl.makeup_start_time_slot_id <= ? AND cl.makeup_end_time_slot_id >= ?) OR
                (cl.makeup_start_time_slot_id <= ? AND cl.makeup_end_time_slot_id >= ?) OR
                (cl.makeup_start_time_slot_id >= ? AND cl.makeup_end_time_slot_id <= ?)
            )
        ";
        $stmt = $mysqli->prepare($compensation_conflict);
        $stmt->bind_param(
            "sisisiiiiiiiiiiii",
            $date, $classroom_id, $date, $classroom_id, $exclude_cancellation_id,
            $start_slot, $start_slot, $end_slot, $end_slot, $start_slot, $end_slot,
            $start_slot, $start_slot, $end_slot, $end_slot, $start_slot, $end_slot
        );
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $conflicts[] = "การชดเชยอื่น: {$row['subject_code']} - {$row['teacher_name']}";
            $has_conflict = true;
        }
        
        // 3. ตรวจสอบวันหยุด
        $holiday_conflict = "
            SELECT holiday_name
            FROM public_holidays 
            WHERE holiday_date = ? AND is_active = 1
        ";
        
        $stmt = $mysqli->prepare($holiday_conflict);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $conflicts[] = "วันหยุดราชการ: " . $row['holiday_name'];
            $has_conflict = true;
        }
        
    } catch (Exception $e) {
        error_log("Error in checkScheduleConflicts: " . $e->getMessage());
        $has_conflict = true;
        $conflicts[] = "เกิดข้อผิดพลาดในการตรวจสอบ: " . $e->getMessage();
    }
    
    return [
        'has_conflict' => $has_conflict,
        'conflicts' => $conflicts
    ];
}

/**
 * หาช่วงเวลาที่ว่างในห้องและอาจารย์สำหรับจำนวน slots ที่กำหนด
 */
function getAvailableTimeSlotsForRoom($mysqli, $date, $classroom_id, $teacher_id, $required_slots) {
    $available_ranges = [];
    
    // ดึง time slots ทั้งหมด
    $slots_query = "SELECT time_slot_id FROM time_slots ORDER BY time_slot_id";
    $result = $mysqli->query($slots_query);
    $all_slots = [];
    while ($row = $result->fetch_assoc()) {
        $all_slots[] = $row['time_slot_id'];
    }
    
    // หาช่วงที่ติดต่อกันและว่าง
    for ($start_slot = $all_slots[0]; $start_slot <= end($all_slots) - $required_slots + 1; $start_slot++) {
        $end_slot = $start_slot + $required_slots - 1;
        
        $conflicts = checkScheduleConflictsForUser($mysqli, $date, $classroom_id, 
                                                   $start_slot, $end_slot, $teacher_id);
        
        if (!$conflicts['has_conflict']) {
            $available_ranges[] = [
                'start' => $start_slot,
                'end' => $end_slot
            ];
        }
    }
    
    return $available_ranges;
}
/**
 * สร้าง Class Session สำหรับการชดเชย
 */
function createCompensationClassSession($mysqli, $compensation, $suitable_schedule, $user_id) {
    try {
        // ตรวจสอบว่ามี session อยู่แล้วหรือไม่
        $check_sql = "
            SELECT session_id 
            FROM class_sessions 
            WHERE schedule_id = ? AND session_date = ?
        ";
        
        $stmt = $mysqli->prepare($check_sql);
        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'ไม่สามารถเตรียม SQL statement สำหรับตรวจสอบ session: ' . $mysqli->error
            ];
        }
        
        $stmt->bind_param("is", $compensation['schedule_id'], $suitable_schedule['date']);
        $stmt->execute();
        $existing_session = $stmt->get_result()->fetch_assoc();
        
        if ($existing_session) {
            // อัปเดต session ที่มีอยู่
            $update_sql = "
                UPDATE class_sessions 
                SET actual_classroom_id = ?,
                    actual_start_time_slot_id = ?,
                    actual_end_time_slot_id = ?,
                    notes = 'การสอนชดเชย (อัปเดต)',
                    updated_at = NOW(),
                    user_id = ?
                WHERE session_id = ?
            ";
            
            $stmt = $mysqli->prepare($update_sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'message' => 'ไม่สามารถเตรียม SQL statement สำหรับอัปเดต session: ' . $mysqli->error
                ];
            }
            
            $stmt->bind_param("iiiii", 
                $suitable_schedule['classroom_id'],
                $suitable_schedule['start_slot_id'],
                $suitable_schedule['end_slot_id'],
                $user_id,
                $existing_session['session_id']
            );
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'session_id' => $existing_session['session_id'],
                    'action_type' => 'อัปเดต'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'ไม่สามารถอัปเดต Class Session ได้: ' . $stmt->error
                ];
            }
        } else {
            // สร้าง session ใหม่
            $insert_sql = "
                INSERT INTO class_sessions 
                (schedule_id, session_date, actual_classroom_id, actual_start_time_slot_id, 
                 actual_end_time_slot_id, notes, user_id, created_at)
                VALUES (?, ?, ?, ?, ?, 'การสอนชดเชย', ?, NOW())
            ";
            
            $stmt = $mysqli->prepare($insert_sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'message' => 'ไม่สามารถเตรียม SQL statement สำหรับสร้าง session: ' . $mysqli->error
                ];
            }
            
            $stmt->bind_param("isiiii", 
                $compensation['schedule_id'],        
                $suitable_schedule['date'],          
                $suitable_schedule['classroom_id'],  
                $suitable_schedule['start_slot_id'], 
                $suitable_schedule['end_slot_id'],   
                $user_id                            
            );
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'session_id' => $mysqli->insert_id,
                    'action_type' => 'สร้าง'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'ไม่สามารถสร้าง Class Session ได้: ' . $stmt->error
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Error in createCompensationClassSession: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
        ];
    }
}
/**
 * ดึงข้อมูลความพร้อมของ ห้องเรียน ชั้นปี อาจารย์ แบบละเอียด
 */
function getDetailedRoomAvailability()
{
    global $conn, $user_id;

    $date = $_POST['date'] ?? '';
    $cancellation_id = $_POST['cancellation_id'] ?? 0;

    if (!$date) {
        jsonError('ไม่ได้ระบุวันที่');
    }

    try {
        $mysqli = connectMySQLi();
        $result = getDetailedRoomAvailabilityArray($mysqli, $date, $cancellation_id);
        jsonSuccess('ดึงข้อมูลความพร้อมของห้องเรียนสำเร็จ', $result);
    } catch (Exception $e) {
        error_log("Error in getDetailedRoomAvailability: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาดในการดึงข้อมูลความพร้อม: ' . $e->getMessage());
    }
}
?>
