<?php
// ตรวจสอบการเข้าสู่ระบบก่อนแสดงหน้า
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

try {

    require_once '../api/config.php';
    
    $conn = connectMySQLi();
    if (!$conn || $conn->connect_error) {
        throw new Exception("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . ($conn->connect_error ?? 'Unknown error'));
    }

    $user_id = $_SESSION['user_id'];
    $user_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $userData = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();

    // ดึงข้อมูลปีการศึกษาปัจจุบัน
    $sql = "SELECT academic_year_id, academic_year, semester, start_date, end_date
            FROM academic_years
            WHERE is_current = 1
            LIMIT 1";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $academic_year_id = $row['academic_year_id'];
        $academic_year = $row['academic_year'];
        $semester = $row['semester'];
        $start_date = $row['start_date'];
        $end_date = $row['end_date'];
    } else {
        // สร้างปีการศึกษาเริ่มต้นถ้าไม่มี
        $current_thai_year = date('Y') + 543;
        $current_month = date('n');
        $default_semester = ($current_month >= 6 && $current_month <= 10) ? 1 : 2;
        
        $insert_sql = "INSERT INTO academic_years (academic_year, semester, start_date, end_date, is_current, is_active) 
                       VALUES (?, ?, ?, ?, 1, 1)";
        $start_date_default = date('Y-06-15');
        $end_date_default = date('Y-10-15');
        
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iiss", $current_thai_year, $default_semester, $start_date_default, $end_date_default);
        
        if ($insert_stmt->execute()) {
            $academic_year_id = $conn->insert_id;
            $academic_year = $current_thai_year;
            $semester = $default_semester;
            $start_date = $start_date_default;
            $end_date = $end_date_default;
        } else {
            $academic_year_id = 0;
            $academic_year = '-';
            $semester = '-';
            $start_date = null;
            $end_date = null;
        }
        $insert_stmt->close();
    }

    function format_thai_date($date_str) {
        if (!$date_str) return '';
        
        $months_th = [
            "", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.",
            "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."
        ];

        $timestamp = strtotime($date_str);
        if (!$timestamp) return $date_str;
        
        $day = date("j", $timestamp);
        $month = (int)date("n", $timestamp);
        $year = (int)date("Y", $timestamp) + 543;

        return "{$day} {$months_th[$month]} {$year}";
    }

    $conn->close();

} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $academic_year_id = 0;
    $academic_year = '-';
    $semester = '-';
    $start_date = null;
    $end_date = null;
}
$userData = $_SESSION;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>ระบบจัดการตารางสอนตามปฏิทินวันหยุด - Dashboard</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <link rel="icon" href="../img/kaiadmin/favicon.ico" type="image/x-icon" />

    <!-- Fonts and icons -->
    <script src="../js/plugin/webfont/webfont.min.js"></script>
    <script>
        WebFont.load({
            google: { families: ["Public Sans:300,400,500,600,700"] },
            custom: {
                families: [
                    "Font Awesome 5 Solid",
                    "Font Awesome 5 Regular", 
                    "Font Awesome 5 Brands",
                    "simple-line-icons",
                ],
                urls: ["../css/fonts.min.css"],
            },
            active: function () {
                sessionStorage.fonts = true;
            },
        });
    </script>

    <!-- CSS Files -->
    <link rel="stylesheet" href="../css/bootstrap.min.css" />
    <link rel="stylesheet" href="../css/plugins.min.css" />
    <link rel="stylesheet" href="../css/kaiadmin.min.css" />
    <link rel="stylesheet" href="../css/demo.css" />

    <!-- Custom CSS -->
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .holiday-card {
            border-left: 4px solid #dc3545;
            transition: all 0.3s ease;
        }
        
        .holiday-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .session-card {
            border-left: 4px solid #28a745;
            margin-bottom: 10px;
        }
        
        .compensation-card {
            border-left: 4px solid #ffc107;
            margin-bottom: 10px;
        }
        
        .status-pending { color: #ffc107; }
        .status-completed { color: #28a745; }
        .status-cancelled { color: #dc3545; }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .quick-action-btn {
            margin: 5px;
            transition: all 0.3s ease;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .holiday-badge {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
        }
        
        .schedule-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 8px;
            border-left: 4px solid #007bff;
        }

        .holiday-row:hover {
            background-color: #f8f9fa;
            transform: translateX(2px);
            transition: all 0.2s ease;
        }

        .date-display {
            text-align: center;
            font-size: 0.9em;
        }

        .holiday-title {
            font-weight: 500;
            color: #2c3e50;
        }

        .holiday-name small {
            font-style: italic;
            opacity: 0.7;
        }

        .table thead th {
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
        }

        .sticky-top {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        @media (max-width: 768px) {
            .holiday-card .table-responsive {
                font-size: 0.85em;
            }
            
            .date-display {
                font-size: 0.8em;
            }
            
            .holiday-title {
                font-size: 0.9em;
                line-height: 1.2;
            }
        }

        .holiday-row.new-item {
            animation: highlightNew 2s ease-in-out;
        }

        @keyframes highlightNew {
            0% { background-color: #d4edda; }
            100% { background-color: transparent; }
        }

        .holiday-tooltip {
            cursor: help;
            border-bottom: 1px dotted #6c757d;
        }

        .api-status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .api-status-indicator.online {
            background-color: #28a745;
            box-shadow: 0 0 3px rgba(40, 167, 69, 0.5);
        }

        .api-status-indicator.offline {
            background-color: #dc3545;
            box-shadow: 0 0 3px rgba(220, 53, 69, 0.5);
        }

        .api-status-indicator.loading {
            background-color: #ffc107;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .error-alert {
            border-left: 4px solid #dc3545;
        }

        .warning-alert {
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center text-white">
            <div class="spinner mx-auto mb-3"></div>
            <p>กำลังประมวลผล...</p>
        </div>
    </div>

    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-panel">
            <?php include '../includes/header.php'; ?>

            <div class="container">
                <div class="page-inner">

                    <div class="row">
                        <div class="col-12">
                            <div class="stats-card">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h2><i class="fas fa-calendar-alt"></i> ระบบจัดการตารางสอนตามปฏิทินวันหยุด</h2>
                                        <p class="mb-1">ปีการศึกษา <?php echo $academic_year; ?> เทอม <?php echo $semester; ?></p>
                                        <small>
                                            ระหว่างวันที่ <?php echo format_thai_date($start_date); ?> - <?php echo format_thai_date($end_date); ?>
                                            <br>
                                            <span class="api-status-indicator" id="apiStatusIndicator"></span>
                                            API Status: <span id="apiStatusText">กำลังตรวจสอบ...</span>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="col-md-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="fas fa-calendar-alt text-info me-2"></i>
                                    Google Calendar Integration
                                    <span class="api-status-indicator loading" id="googleStatusIndicator"></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div>
                                    <h6 class="text-muted mb-3">
                                        <i class="fab fa-google me-2"></i>
                                        สถานะการเชื่อมต่อ
                                    </h6>
                                    <div id="googleCalendarStatus">
                                        <div class="text-center text-muted">
                                            <div class="spinner-border spinner-border-sm me-2" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            กำลังตรวจสอบสถานะ Google Calendar...
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <h6 class="text-muted mb-3">
                                        การจัดการข้อมูล
                                    </h6>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button class="btn btn-outline-success" onclick="generateClassSessions()">
                                            <i class="fas fa-calendar-plus"></i> สร้างปฏิทินการศึกษา
                                        </button>
                                        <button class="btn btn-outline-info" onclick="refreshData()">
                                            <i class="fas fa-sync-alt"></i> รีเฟรชข้อมูล
                                        </button>                                    
                                        <button class="btn btn-outline-primary" onclick="sendPendingProgressively()">
                                            <i class="fas fa-cloud-upload-alt"></i> ส่งข้อมูลเข้า Google Calendar
                                        </button>
                                    </div>
                                </div>
                                <?php if (($userData['user_type'] ?? '')): ?>
                                <div class="mb-3">
                                    <label for="teacherFilter" class="form-label"></i>อาจารย์  </label>
                                    <select id="teacherFilter" class="form-select" style="max-width:350px;display:inline-block;">
                                        <option value="">-- อาจารย์ --</option>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label for="sessionDateFilter" class="form-label"><i class="fas fa-calendar-day"></i> วันที่เรียน</label>
                                    <input type="date" id="sessionDateFilter" class="form-control" style="max-width:350px;display:inline-block;" onchange="filterClassSessionsByDate()" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- <div class="row">
                         วันหยุดราชการ 
                        <div class="col-md-6">
                            <div class="card holiday-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5>
                                    <i class="fas fa-flag"></i> 
                                    วันหยุดราชการ ปีการศึกษา <?php echo $academic_year; ?> เทอม <?php echo $semester; ?>
                                </h5>
                                <span class="holiday-badge" id="holidayBadge">- วัน</span>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-alt"></i>
                                        ช่วงวันที่: <?php echo format_thai_date($start_date); ?> - <?php echo format_thai_date($end_date); ?>
                                    </small>
                                </div>
                                <div class="mb-3">
                                    <input type="text" id="holidaySearch" class="form-control form-control-sm" 
                                           placeholder="ค้นหาวันหยุด..." onkeyup="filterHolidays(this.value)">
                                </div>
                                    <div class="table-responsive" style="max-height: 450px;">
                                        <table class="table table-sm table-hover">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th width="25%">วันที่</th>
                                                    <th width="50%">ชื่อวันหยุด</th>
                                                    <th width="25%">ประเภท</th>
                                                </tr>
                                            </thead>
                                            <tbody id="holidaysTableBody">
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted py-3">
                                                        <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div><br>
                                                        กำลังโหลดข้อมูล...
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div> -->

                        <!-- ตารางสอน -->
                        <!-- <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5><i class="fas fa-calendar-week"></i> ตารางสอน</h5>
                                </div>
                                <div class="card-body">
                                    <div style="max-height: 400px; overflow-y: auto;" id="teachingSchedulesContainer">
                                        <div class="text-center text-muted py-3">
                                            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div><br>
                                            กำลังโหลดข้อมูล...
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div> 
                    </div>-->

                    <!-- บันทึกการเรียนการสอน -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5><i class="fas fa-list"></i> บันทึกการเรียนการสอน</h5>
                                    <span class="badge bg-success" id="classSessionsBadge">- รายการ</span>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>วันที่เรียน</th>
                                                    <th>รายวิชา</th>
                                                    <th>ชั้นปี</th>
                                                    <th>ห้องเรียน</th>
                                                    <th>หมายเหตุ</th>
                                                    <th>จัดการ</th>
                                                </tr>
                                            </thead>
                                            <tbody id="classSessionsTableBody">
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted py-3">
                                                        <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div><br>
                                                        กำลังโหลดข้อมูล...
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle"></i> สำเร็จ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="successMessage">ดำเนินการเสร็จสิ้น</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">ตกลง</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> เกิดข้อผิดพลาด</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="errorMessage">เกิดข้อผิดพลาดในการดำเนินการ</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">ตกลง</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Session Modal -->
    <div class="modal fade" id="editSessionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i> แก้ไขการเรียนการสอน
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editSessionForm">
                        <input type="hidden" id="edit_session_id" name="session_id">
                        <input type="hidden" id="edit_schedule_id" name="schedule_id">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">รายวิชา</label>
                                <input type="text" class="form-control" id="edit_subject_info" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ชั้นปี</label>
                                <input type="text" class="form-control" id="edit_class_year" readonly>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">วันที่เรียน <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="edit_session_date" name="session_date" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ห้องเรียน <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_classroom" name="actual_classroom_id" required>
                                    <option value="">เลือกห้องเรียน</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">เวลาเริ่ม <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_start_time" name="actual_start_time_slot_id" required>
                                    <option value="">เลือกเวลาเริ่ม</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">เวลาสิ้นสุด <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_end_time" name="actual_end_time_slot_id" required>
                                    <option value="">เลือกเวลาสิ้นสุด</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">หมายเหตุ</label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger me-auto" onclick="showCancelSessionModal()">
                        <i class="fas fa-times"></i> ยกเลิกการเรียน
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    <button type="button" class="btn btn-primary" onclick="updateSession()">
                        <i class="fas fa-save"></i> บันทึก
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Session Modal -->
    <div class="modal fade" id="cancelSessionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-times-circle"></i> ยกเลิกการเรียนการสอน
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>คำเตือน:</strong> การยกเลิกการเรียนจะไม่สามารถยกเลิกได้ กรุณาตรวจสอบข้อมูลให้ถูกต้อง
                    </div>
                    
                    <form id="cancelSessionForm">
                        <input type="hidden" id="cancel_session_id" name="session_id">
                        <input type="hidden" id="cancel_schedule_id" name="schedule_id">
                        <input type="hidden" id="cancel_session_date" name="cancellation_date">
                        
                        <div class="mb-3">
                            <label class="form-label">รายละเอียดการเรียนที่จะยกเลิก</label>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div id="cancel_session_info"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">ประเภทการยกเลิก <span class="text-danger">*</span></label>
                                <select class="form-select" id="cancel_type" name="cancellation_type" required>
                                    <option value="">เลือกประเภทการยกเลิก</option>
                                    <option value="วันหยุดราชการ">วันหยุดราชการ</option>
                                    <option value="เหตุส่วนตัว">เหตุส่วนตัว</option>
                                    <option value="เหตุฉุกเฉิน">เหตุฉุกเฉิน</option>
                                    <option value="ยกเลิกรายวิชา">ยกเลิกรายวิชา</option>
                                    <option value="อื่นๆ">อื่นๆ</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="is_makeup_required" name="is_makeup_required" checked>
                                    <label class="form-check-label" for="is_makeup_required">
                                        <strong>ต้องการสอนชดเชย</strong>
                                    </label>
                                </div>
                                <small class="text-muted">เลือกถ้าต้องการกำหนดวันสอนชดเชยภายหลัง</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">เหตุผลการยกเลิก <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="cancel_reason" name="reason" rows="3" 
                                    placeholder="กรุณาระบุเหตุผลการยกเลิกการเรียน..." required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">หมายเหตุเพิ่มเติม</label>
                            <textarea class="form-control" id="cancel_additional_notes" name="additional_notes" rows="2" 
                                    placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)"></textarea>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i>
                            <strong>หมายเหตุ:</strong> 
                            <ul class="mb-0 mt-2">
                                <li>การยกเลิกจะลบ Class Session ออกจากระบบ</li>
                                <li>หากเลือก "ต้องการสอนชดเชย" ระบบจะสร้างรายการในหน้า "จัดการการชดเชย"</li>
                                <li>สามารถกำหนดวันที่และเวลาสอนชดเชยได้ภายหลัง</li>
                            </ul>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-arrow-left"></i> ย้อนกลับ
                    </button>
                    <button type="button" class="btn btn-danger" onclick="confirmCancelSession()">
                        <i class="fas fa-times"></i> ยืนยันการยกเลิก
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancellation Request Modal -->
    <div class="modal fade" id="cancellationRequestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle"></i> ขอยกเลิกการเรียนการสอน
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="cancellationRequestForm">
                        <input type="hidden" id="request_session_id" name="session_id">
                        <input type="hidden" id="request_schedule_id" name="schedule_id">
                        <input type="hidden" id="request_session_date" name="cancellation_date">
                        
                        <div class="mb-3">
                            <label class="form-label">รายละเอียดการเรียนที่จะยกเลิก</label>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div id="request_session_info"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">ประเภทการยกเลิก <span class="text-danger">*</span></label>
                                <select class="form-select" id="request_cancel_type" name="cancellation_type" required>
                                    <option value="">เลือกประเภทการยกเลิก</option>
                                    <option value="ยกเลิกรายวิชา">ยกเลิกรายวิชา</option>
                                    <option value="วันหยุดราชการ">วันหยุดราชการ</option>
                                    <option value="เหตุส่วนตัว">เหตุส่วนตัว</option>
                                    <option value="เหตุฉุกเฉิน">เหตุฉุกเฉิน</option>
                                    <option value="อื่นๆ">อื่นๆ</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">เหตุผลการยกเลิก <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="request_cancel_reason" name="reason" rows="3" 
                                    placeholder="กรุณาระบุเหตุผลการยกเลิกการเรียน..." required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> ปิด
                    </button>
                    <button type="button" class="btn btn-danger" onclick="submitCancellationRequest()">
                        <i class="fas fa-times-circle"></i> ยืนยันยกเลิกทันที
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!--   Core JS Files   -->
    <script src="../js/core/jquery-3.7.1.min.js"></script>
    <script src="../js/core/popper.min.js"></script>
    <script src="../js/core/bootstrap.min.js"></script>
    <!-- jQuery Scrollbar -->
    <script src="../js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>
    <!-- Kaiadmin JS -->
    <script src="../js/kaiadmin.min.js"></script>

<script>
// กำหนด API paths ที่แก้ไขแล้ว
const API_CONFIG = {
    processor: '../api/api_holiday_processor.php',
    data: '../api/api_holiday_data.php',
    management: '../api/api_holiday_management.php',
    google_check: '../api/calendar/google_calendar_check.php',
    google_sender: '../api/calendar/google_calendar_sender.php',
    token_refresh: '../api/calendar/token_refresh.php'
};

const ACADEMIC_YEAR_ID = <?php echo $academic_year_id; ?>;
const ACADEMIC_YEAR = <?php echo $academic_year; ?>;

// ตัวแปรสำหรับเก็บสถานะ
let apiWorking = false;
let statsUpdateInterval = null;

// ========================================
// ฟังก์ชันช่วยเหลือพื้นฐาน
// ========================================

function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.style.display = 'flex';
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.style.display = 'none';
}

function showSuccess(message) {
    const msgElement = document.getElementById('successMessage');
    if (msgElement) {
        msgElement.textContent = message;
        const modal = new bootstrap.Modal(document.getElementById('successModal'));
        modal.show();
    }
}
function filterClassSessionsByDate() {
    const filterDate = document.getElementById('sessionDateFilter').value;
    const rows = document.querySelectorAll('#classSessionsTableBody tr');
    let visibleCount = 0;

    rows.forEach(row => {
        // ข้ามแถวที่เป็นข้อความ loading หรือ error
        if (!row.querySelector('td')) return;
        const dateCell = row.querySelector('td');
        if (!dateCell) return;
        const dateText = dateCell.textContent.trim();

        if (!filterDate || dateText.includes(formatThaiDate(filterDate))) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    // แสดงข้อความถ้าไม่พบผลการค้นหา
    let noResultsRow = document.getElementById('noSessionDateResults');
    const tbody = document.getElementById('classSessionsTableBody');
    if (visibleCount === 0 && filterDate) {
        if (!noResultsRow) {
            noResultsRow = document.createElement('tr');
            noResultsRow.id = 'noSessionDateResults';
            noResultsRow.innerHTML = `
                <td colspan="7" class="text-center text-muted py-3">
                    <i class="fas fa-search fa-lg mb-2"></i><br>
                    ไม่พบรายการที่ตรงกับวันที่ "${formatThaiDate(filterDate)}"
                </td>
            `;
            tbody.appendChild(noResultsRow);
        }
    } else if (noResultsRow) {
        noResultsRow.remove();
    }
}
function showError(message) {
    const msgElement = document.getElementById('errorMessage');
    if (msgElement) {
        msgElement.textContent = message;
        const modal = new bootstrap.Modal(document.getElementById('errorModal'));
        modal.show();
    }
}

function updateAPIStatus(status, message) {
    const indicator = document.getElementById('apiStatusIndicator');
    const text = document.getElementById('apiStatusText');
    
    if (indicator && text) {
        indicator.className = `api-status-indicator ${status}`;
        text.textContent = message;
    }
}

// ========================================
// ฟังก์ชันเรียก API ที่ปรับปรุงแล้ว
// ========================================

async function callAPI(apiPath, action, params = {}) {
    if (!ACADEMIC_YEAR_ID || ACADEMIC_YEAR_ID === 0) {
        throw new Error('Academic Year ID ไม่ถูกต้อง: ' + ACADEMIC_YEAR_ID);
    }

    // ตรวจสอบและเพิ่มพารามิเตอร์ที่จำเป็น
    const requestParams = {
        action: action,
        academic_year_id: ACADEMIC_YEAR_ID,
        ...params
    };

    const formData = new URLSearchParams(requestParams);

    try {

        const response = await fetch(apiPath, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache'
            },
            body: formData,
            credentials: 'same-origin'
        });

        if (!response.ok) {
            // ดึงข้อมูล error จาก response body
            let errorText = '';
            try {
                errorText = await response.text();
                console.log(`HTTP ${response.status} Response:`, errorText);
            } catch (textError) {
                console.log(`HTTP ${response.status} - Cannot read response text`);
            }
            
            throw new Error(`HTTP ${response.status}: ${response.statusText}${errorText ? ` - ${errorText.substring(0, 200)}` : ''}`);
        }

        const text = await response.text();

        // ตรวจสอบว่า response เป็น JSON หรือไม่
        if (!text.trim()) {
            throw new Error('การตอบกลับจากเซิร์ฟเวอร์ว่างเปล่า');
        }

        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.error('Response text:', text);
            
            // ตรวจหา PHP error ใน response
            if (text.includes('Fatal error') || text.includes('Parse error') || text.includes('Warning')) {
                throw new Error('PHP Error: ' + text.substring(0, 200));
            }
            
            throw new Error('การตอบกลับจากเซิร์ฟเวอร์ไม่ใช่ JSON ที่ถูกต้อง');
        }

        if (!data.success) {
            throw new Error(data.message || 'การเรียก API ไม่สำเร็จ');
        }
        return data;

    } catch (error) {
        console.error(`API Error: ${apiPath} - ${action}`, error);
        
        // อัปเดตสถานะ API
        apiWorking = false;
        updateAPIStatus('offline', 'API มีปัญหา');
        
        throw error;
    }
}

async function generateClassSessions() {
    const startDate = '<?php echo $start_date; ?>';
    const endDate = '<?php echo $end_date; ?>';
    
    if (!startDate || !endDate) {
        showError('ไม่พบข้อมูลช่วงวันที่ของปีการศึกษา');
        return;
    }
    
    const start = new Date(startDate);
    const end = new Date(endDate);
    const totalDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
    
    const userType = '<?php echo $userData['user_type'] ?? 'teacher'; ?>';
    const isAdmin = (userType === 'admin');
    
    const useBatchProcessing = totalDays > 30;
    let confirmMessage = `คุณต้องการสร้าง สร้างปฏิทินการสอน สำหรับช่วง:\n${formatThaiDate(startDate)} - ${formatThaiDate(endDate)}\n`;
    confirmMessage += `จำนวน: ${totalDays} วัน\n\n`;
    
    if (isAdmin) {
        confirmMessage += "คุณมีสิทธิ์ผู้ดูแลระบบ\n";
        confirmMessage += "ระบบจะสร้างปฏิทินการสอนสำหรับอาจารย์ทุกคนในระบบ\n\n";
    } else {
        confirmMessage += "ระบบจะสร้างปฏิทินการสอนสำหรับตารางสอนของคุณเท่านั้น\n\n";
    }
    if (!confirm(confirmMessage)) {
        return;
    }
    
    if (useBatchProcessing) {
        await processBatchGeneration(startDate, endDate);
    } else {
        await processSingleGeneration(startDate, endDate);
    }
}

// ฟังก์ชันประมวลผลแบบ Batch
async function processBatchGeneration(startDate, endDate) {
    try {
        let currentBatch = 0;
        let isComplete = false;
        let totalResults = {
            generated_count: 0,
            skipped_holidays: 0,
            compensation_created: 0,
            compensation_details: [],
            total_batches: 0,
            processed_batches: 0
        };
        
        showLoading();
        
        while (!isComplete) {
            try {
                // อัปเดตข้อความ loading
                updateLoadingMessage(`กำลังประมวลผล Batch ${currentBatch + 1}...`);
                
                const data = await callAPI(API_CONFIG.processor, 'generate_class_sessions', {
                    date_from: startDate,
                    date_to: endDate,
                    batch_mode: '1',
                    batch_size_days: '30',
                    current_batch: currentBatch.toString()
                });
                
                if (data.success && data.data) {
                    const batchData = data.data;
                    
                    // รวมผลลัพธ์
                    totalResults.generated_count += batchData.generated_count || 0;
                    totalResults.skipped_holidays += batchData.skipped_holidays || 0;
                    totalResults.compensation_created += batchData.compensation_created || 0;
                    
                    if (batchData.compensation_details) {
                        totalResults.compensation_details = totalResults.compensation_details.concat(batchData.compensation_details);
                    }
                    
                    // ตรวจสอบ batch info
                    if (batchData.batch_info) {
                        const batchInfo = batchData.batch_info;
                        totalResults.total_batches = batchInfo.total_batches;
                        totalResults.processed_batches = batchInfo.current_batch;
                        isComplete = batchInfo.is_complete;
                        currentBatch = batchInfo.next_batch || currentBatch + 1;
                        
                        // อัปเดต progress
                        const progress = batchInfo.progress_percentage || 0;
                        updateLoadingMessage(`กำลังประมวลผล Batch ${batchInfo.current_batch}/${batchInfo.total_batches} (${progress}%)`);
                    } else {
                        // Fallback ถ้าไม่มี batch_info
                        isComplete = true;
                    }

                    if (!isComplete) {
                        await new Promise(resolve => setTimeout(resolve, 1000));
                    }
                    
                } else {
                    throw new Error(data.message || 'การประมวลผล batch ล้มเหลว');
                }
                
            } catch (batchError) {
                console.error(`Error in batch ${currentBatch}:`, batchError);
                
                // ถามว่าต้องการลองต่อหรือไม่
                const retry = confirm(
                    `เกิดข้อผิดพลาดใน Batch ${currentBatch + 1}:\n${batchError.message}\n\n` +
                    'คุณต้องการข้าม batch นี้และดำเนินการต่อหรือไม่?\n\n' +
                    'ใช่ = ข้าม batch นี้และดำเนินการต่อ\n' +
                    'ไม่ = หยุดการประมวลผล'
                );
                
                if (retry) {
                    currentBatch++;
                    continue; // ข้าม batch นี้
                } else {
                    throw batchError; // หยุดการประมวลผล
                }
            }
        }
        
        hideLoading();
        
        // สร้างข้อความสรุปรวม
        let finalMessage = `การสร้างปฏิทินการสอนเสร็จสิ้น!\n\n`;
        finalMessage += `• สร้างปฏิทินการสอน: ${totalResults.generated_count} รายการ\n`;
        finalMessage += `• ข้ามวันหยุด: ${totalResults.skipped_holidays} วัน\n`;
        
        if (totalResults.compensation_details.length > 0) {
            finalMessage += `\n🔄 รายการที่ต้องชดเชย: ${totalResults.compensation_details.length} รายการ\n`;
            finalMessage += `💡 กรุณาไปที่หน้า 'จัดการการชดเชย' เพื่อกำหนดวันที่ชดเชย`;
        }
        
        showSuccess(finalMessage);
        
        // รีเฟรชข้อมูลหลังจาก 2 วินาที
        setTimeout(() => {
            updateStats();
        }, 2000);
        
    } catch (error) {
        hideLoading();
        
        let errorMessage = 'เกิดข้อผิดพลาดใน Batch Processing:\n' + error.message;
        
        if (error.message.includes('timeout') || error.message.includes('execution time')) {
            errorMessage += '\n\n💡 ข้อแนะนำ:';
            errorMessage += '\n• ลองแบ่งช่วงวันที่ให้สั้นลง';
            errorMessage += '\n• ตรวจสอบการเชื่อมต่ออินเทอร์เน็ต';
            errorMessage += '\n• ลองใหม่ในเวลาที่เครือข่ายไม่ติดขัด';
        }
        
        showError(errorMessage);
    }
}

// ฟังก์ชันประมวลผลแบบเดิม (สำหรับช่วงวันที่สั้น)
async function processSingleGeneration(startDate, endDate) {
    try {
        showLoading();
        
        const data = await callAPI(API_CONFIG.processor, 'generate_class_sessions', {
            date_from: startDate,
            date_to: endDate,
            batch_mode: '0' // ปิด batch mode
        });
        
        hideLoading();
        
        if (data.success) {
            let message = data.message;
            
            showSuccess(message);
            
            // รีเฟรชข้อมูลหลังจาก 2 วินาที
            setTimeout(() => {
                updateStats();
            }, 2000);
        }
        
    } catch (error) {
        hideLoading();
        showError('เกิดข้อผิดพลาด: ' + error.message);
    }
}

// ฟังก์ชันอัปเดตข้อความ loading
function updateLoadingMessage(message) {
    const loadingText = document.querySelector('#loadingOverlay p');
    if (loadingText) {
        loadingText.textContent = message;
    }
}
// ========================================
// ฟังก์ชันโหลดข้อมูล
// ========================================

async function loadHolidaysData() {
    try {
        // ตรวจสอบค่าที่จำเป็น
        if (!ACADEMIC_YEAR_ID || ACADEMIC_YEAR_ID === 0) {
            throw new Error('Academic Year ID ไม่ถูกต้อง');
        }
        
        // เพิ่มช่วงวันที่ของปีการศึกษา
        const startDate = '<?php echo $start_date; ?>';
        const endDate = '<?php echo $end_date; ?>';
        
        const data = await callAPI(API_CONFIG.data, 'get_all_holidays', {
            academic_year_id: ACADEMIC_YEAR_ID,
            date_from: startDate,  // เพิ่มพารามิเตอร์นี้
            date_to: endDate       // เพิ่มพารามิเตอร์นี้
        });
        
        if (data.success && data.data) {
            updateHolidaysTable(data.data.holidays || []);
            
            // อัปเดต badge
            const badge = document.getElementById('holidayBadge');
            if (badge) {
                badge.textContent = `${data.data.total_count || 0} วัน`;
            }
        } else {
            throw new Error(data.message || 'ไม่สามารถโหลดข้อมูลวันหยุดได้');
        }
        
    } catch (error) {
        console.error('Error loading holidays:', error);
        
        const tbody = document.getElementById('holidaysTableBody');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center text-warning py-3">
                        <i class="fas fa-exclamation-triangle fa-lg mb-2"></i><br>
                        ไม่สามารถโหลดข้อมูลวันหยุดได้<br>
                        <small class="text-muted">${error.message}</small>
                        <br><br>
                        <button class="btn btn-sm btn-outline-primary" onclick="loadHolidaysData()">
                            <i class="fas fa-retry"></i> ลองใหม่
                        </button>
                    </td>
                </tr>
            `;
        }
    }
}
async function loadTeacherList() {
    try {
        const response = await fetch('../api/api_holiday_data.php?action=get_teacher_list', {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin'
        });
        const data = await response.json();
        if (data.success && data.data && Array.isArray(data.data.teachers)) {
            const select = document.getElementById('teacherFilter');
            if (!select) return;
            const currentUserId = <?php echo (int)$userData['user_id']; ?>;
            select.innerHTML = `<option value="">-- อาจารย์ --</option>`;
            data.data.teachers.forEach(teacher => {
                select.innerHTML += `<option value="${teacher.user_id}">${teacher.title}${teacher.name} ${teacher.lastname}</option>`;
            });
            // ตั้งค่า default เป็น user ที่ login
            select.value = currentUserId.toString();

            // Trigger event เพื่อโหลดข้อมูลของตัวเองทันที
            setTimeout(() => {
                select.dispatchEvent(new Event('change'));
            }, 100); // ให้ DOM render option ก่อน
        }
    } catch (e) {
        console.warn('โหลดรายชื่ออาจารย์ล้มเหลว', e);
    }
}
async function loadTeachingSchedules() {
    try {
        if (!ACADEMIC_YEAR_ID || ACADEMIC_YEAR_ID === 0) throw new Error('Academic Year ID ไม่ถูกต้อง');
        // ใช้ user_id ที่ login เป็น teacher_id
        const teacherId = <?php echo (int)$_SESSION['user_id']; ?>;
        const params = {
            academic_year_id: ACADEMIC_YEAR_ID,
            teacher_id: teacherId
        };
        const data = await callAPI(API_CONFIG.data, 'get_teaching_schedules', params);
        if (data.success && data.data) {
            updateTeachingSchedulesTable(data.data.schedules || []);
        } else {
            throw new Error(data.message || 'ไม่สามารถโหลดตารางสอนได้');
        }
    } catch (error) {
        console.error('Error loading teaching schedules:', error);
        
        const container = document.getElementById('teachingSchedulesContainer');
        if (container) {
            container.innerHTML = `
                <div class="text-center text-warning py-3">
                    <i class="fas fa-exclamation-triangle fa-lg mb-2"></i><br>
                    ไม่สามารถโหลดตารางสอนได้<br>
                    <small class="text-muted">${error.message}</small>
                    <br><br>
                    <button class="btn btn-sm btn-outline-primary" onclick="loadTeachingSchedules()">
                        <i class="fas fa-retry"></i> ลองใหม่
                    </button>
                </div>
            `;
        }
    }
}

async function loadClassSessions() {
    try {
        if (!ACADEMIC_YEAR_ID || ACADEMIC_YEAR_ID === 0) throw new Error('Academic Year ID ไม่ถูกต้อง');
        // ใช้ user_id ที่ login เป็น teacher_id
        const teacherId = <?php echo (int)$_SESSION['user_id']; ?>;
        const startDate = '<?php echo $start_date; ?>';
        const endDate = '<?php echo $end_date; ?>';
        const params = {
            academic_year_id: ACADEMIC_YEAR_ID,
            date_from: startDate,
            date_to: endDate,
            teacher_id: teacherId
        };
        const data = await callAPI(API_CONFIG.data, 'get_class_sessions', params);
        if (data.success && data.data) {
            updateClassSessionsTable(data.data.sessions || []);
            const badge = document.getElementById('classSessionsBadge');
            if (badge) badge.textContent = `${data.data.total_count || 0} รายการ`;
        } else {
            throw new Error(data.message || 'ไม่สามารถโหลด สร้างปฏิทินการสอน ได้');
        }
    } catch (error) {
        console.error('Error loading สร้างปฏิทินการสอน:', error);
        
        const tbody = document.getElementById('classSessionsTableBody');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-warning py-3">
                        <i class="fas fa-exclamation-triangle fa-lg mb-2"></i><br>
                        ไม่สามารถโหลด สร้างปฏิทินการสอน ได้<br>
                        <small class="text-muted">${error.message}</small>
                        <br><br>
                        <button class="btn btn-sm btn-outline-primary" onclick="loadClassSessions()">
                            <i class="fas fa-retry"></i> ลองใหม่
                        </button>
                    </td>
                </tr>
            `;
        }
    }
}

// เพิ่มฟังก์ชันตรวจสอบสถานะ Google Calendar
async function checkGoogleCalendarStatus() {
    try {
        const response = await fetch(API_CONFIG.google_check, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache'
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        
        if (data.requires_login) {
            updateGoogleCalendarStatus('not_logged_in', 'กรุณาเข้าสู่ระบบ');
            return;
        }

        updateGoogleCalendarStatus(data.has_google_auth ? 'connected' : 'not_connected', data);
        
    } catch (error) {
        console.error('Error checking Google Calendar status:', error);
        updateGoogleCalendarStatus('error', { error: error.message });
    }
}

// อัปเดตสถานะ Google Calendar ใน UI
function updateGoogleCalendarStatus(status, data) {
    const statusElement = document.getElementById('googleCalendarStatus');
    const indicator = document.getElementById('googleStatusIndicator');
    
    if (!statusElement || !indicator) return;
    
    let html = '';
    let indicatorClass = 'loading';
    
    switch (status) {
        case 'connected':
            indicatorClass = 'online';
            html = `
                <div class="row">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                            <div>
                                <h6 class="mb-1 text-success">เชื่อมต่อ Google Calendar สำเร็จ</h6>
                                <small class="text-muted">
                                    Google Account: ${data.google_email || 'N/A'}<br>
                                    สถานะ Token: ${data.token_status === 'valid' ? 'ใช้งานได้' : 'ต้องรีเฟรช'}
                                </small>
                            </div>
                        </div>
                        
                        ${data.sync_stats ? `
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="card border-success">
                                    <div class="card-body p-2">
                                        <h5 class="text-success mb-1">${data.sync_stats.synced_count || 0}</h5>
                                        <small>ส่งแล้ว</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="card border-warning">
                                    <div class="card-body p-2">
                                        <h5 class="text-warning mb-1">${data.sync_stats.pending_count || 0}</h5>
                                        <small>รอส่ง</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="card border-danger">
                                    <div class="card-body p-2">
                                        <h5 class="text-danger mb-1">${data.sync_stats.failed_count || 0}</h5>
                                        <small>ล้มเหลว</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
            break;
            
        case 'not_connected':
            indicatorClass = 'offline';
            html = `
                <div class="text-center">
                    <i class="fas fa-calendar-times text-warning fa-3x mb-3"></i>
                    <h6 class="text-warning">ยังไม่ได้เชื่อมต่อ Google Calendar</h6>
                    <p class="text-muted mb-3">
                        เชื่อมต่อ Google Calendar เพื่อส่งตารางเรียนไปยัง Google Calendar ของคุณโดยอัตโนมัติ
                    </p>
                    <button class="btn btn-primary" onclick="connectGoogleCalendar()">
                        <i class="fab fa-google"></i> เชื่อมต่อ Google Calendar
                    </button>
                </div>
            `;
            break;
            
        case 'error':
            indicatorClass = 'offline';
            html = `
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle text-danger fa-3x mb-3"></i>
                    <h6 class="text-danger">เกิดข้อผิดพลาด</h6>
                    <p class="text-muted mb-3">
                        ${data.error || 'ไม่สามารถตรวจสอบสถานะ Google Calendar ได้'}
                    </p>
                    <button class="btn btn-outline-primary" onclick="checkGoogleCalendarStatus()">
                        <i class="fas fa-retry"></i> ลองใหม่
                    </button>
                </div>
            `;
            break;
            
        case 'not_logged_in':
            indicatorClass = 'offline';
            html = `
                <div class="text-center">
                    <i class="fas fa-sign-in-alt text-info fa-3x mb-3"></i>
                    <h6 class="text-info">กรุณาเข้าสู่ระบบ</h6>
                    <p class="text-muted">เข้าสู่ระบบเพื่อใช้งาน Google Calendar Integration</p>
                </div>
            `;
            break;
            
        default:
            html = `
                <div class="text-center text-muted">
                    <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                    <p>กำลังตรวจสอบสถานะ Google Calendar...</p>
                </div>
            `;
    }
    
    statusElement.innerHTML = html;
    indicator.className = `api-status-indicator ${indicatorClass}`;
}

// ฟังก์ชันเชื่อมต่อ Google Calendar
function connectGoogleCalendar() {
    const width = 600;
    const height = 600;
    const left = (window.innerWidth - width) / 2;
    const top = (window.innerHeight - height) / 2;
    
    const popup = window.open(
        '../api/calendar/google_calendar_oauth.php?action=start',
        'google_auth',
        `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`
    );
    
    // ตรวจสอบเมื่อ popup ปิด
    const checkClosed = setInterval(() => {
        if (popup.closed) {
            clearInterval(checkClosed);
            // รอ 2 วินาทีแล้วตรวจสอบสถานะใหม่
            setTimeout(() => {
                checkGoogleCalendarStatus();
            }, 2000);
        }
    }, 1000);
}

// ฟังก์ชันรีเฟรช Google Token (แก้ไขแล้ว)
async function refreshGoogleToken() {
    try {
        showLoading();
        
        // ส่ง POST request พร้อม action
        const response = await fetch(API_CONFIG.token_refresh, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'refresh',
                force: true
            }),
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        hideLoading();
        
        if (data.status === 'success') {
            showSuccess('รีเฟรช Google Token สำเร็จ!\n\n' + 
                       (data.message || 'Token ได้รับการอัปเดตแล้ว'));
            
            // อัปเดตสถานะ Google Calendar หลังจาก refresh
            setTimeout(() => {
                checkGoogleCalendarStatus();
            }, 1000);
        } else {
            let errorMessage = 'การรีเฟรช Token ล้มเหลว: ' + (data.message || 'ไม่ทราบสาเหตุ');
            
            if (data.requires_reauth || data.action_required === 'connect') {
                errorMessage += '\n\nกรุณาเชื่อมต่อ Google Calendar ใหม่';
            }
            
            showError(errorMessage);
        }
        
    } catch (error) {
        hideLoading();
        console.error('Refresh token error:', error);
        showError('เกิดข้อผิดพลาดในการรีเฟรช Token: ' + error.message);
    }
}

// ฟังก์ชันทดสอบการเชื่อมต่อ Google
async function testGoogleConnection() {
    try {
        showLoading();
        
        const response = await fetch(API_CONFIG.google_sender, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=test_connection',
            credentials: 'same-origin'
        });
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            throw new Error('การตอบกลับจากเซิร์ฟเวอร์ไม่ใช่ JSON: ' + text.substring(0, 200));
        }
        
        hideLoading();
        
        if (data.success) {
            let message = 'การเชื่อมต่อ Google Calendar ทำงานปกติ';
            if (data.data) {
                message += `\n\nรายละเอียด:`;
                message += `\n• Google Account: ${data.data.google_email || 'N/A'}`;
                message += `\n• Token Status: ${data.data.token_status || 'Unknown'}`;
                if (data.data.minutes_to_expiry !== null) {
                    message += `\n• Token หมดอายุใน: ${Math.round(data.data.minutes_to_expiry)} นาที`;
                }
                if (data.data.api_test) {
                    message += `\n• API Test: ${data.data.api_test.success ? 'ผ่าน' : 'ล้มเหลว'}`;
                }
            }
            showSuccess(message);
        } else {
            throw new Error(data.message || 'การทดสอบการเชื่อมต่อล้มเหลว');
        }
        
    } catch (error) {
        hideLoading();
        console.error('Test connection error:', error);
        
        let errorMessage = 'เกิดข้อผิดพลาดในการทดสอบ: ' + error.message;
        
        if (error.message.includes('400')) {
            errorMessage += '\n\n💡 แนะนำการแก้ไข:';
            errorMessage += '\n• ตรวจสอบการเชื่อมต่อ Google Calendar';
            errorMessage += '\n• ลองรีเฟรช Token';
            errorMessage += '\n• ตรวจสอบ Console สำหรับข้อมูลเพิ่มเติม';
        }
        
        showError(errorMessage);
    }
}

// เพิ่มฟังก์ชันส่งแบบ Progressive (ส่งทีละน้อย)
async function sendPendingProgressively() {
    if (!confirm('คุณต้องการส่งข้อมูลแบบค่อยเป็นค่อยไป (Progressive) หรือไม่?\n\nระบบจะส่งทีละ 20 รายการ และแสดงความคืบหน้า')) {
        return;
    }
    
    try {
        let offset = 0;
        const batchSize = 20;
        let totalSent = 0;
        let totalFailed = 0;
        let isComplete = false;
        
        showLoading();
        
        while (!isComplete) {
            const formData = new URLSearchParams({
                action: 'send_class_sessions',
                academic_year_id: ACADEMIC_YEAR_ID,
                batch_size: batchSize,
                offset: offset
            });
            
            const response = await fetch(API_CONFIG.google_sender, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData,
                credentials: 'same-origin'
            });
            
            const text = await response.text();
            const data = JSON.parse(text);
            
            if (!data.success) {
                throw new Error(data.message || 'การส่งข้อมูล batch ล้มเหลว');
            }
            
            totalSent += data.data.sent_count || 0;
            totalFailed += data.data.failed_count || 0;
            isComplete = data.data.is_complete;
            offset = data.data.next_offset || 0;
            
            const progress = data.data.total_sessions > 0 ? 
                Math.round((data.data.processed_total / data.data.total_sessions) * 100) : 100;
            const loadingText = document.querySelector('#loadingOverlay p');
            if (loadingText) {
                loadingText.textContent = `กำลังส่งข้อมูล... ${progress}% (${totalSent} ส่งแล้ว)`;
            }
            
            await new Promise(resolve => setTimeout(resolve, 1000));
        }
        
        hideLoading();
        
        let message = `ส่งข้อมูลเสร็จสิ้น (Progressive)`;
        message += `\n\n• ส่งสำเร็จ: ${totalSent} รายการ`;
        message += `\n• ล้มเหลว: ${totalFailed} รายการ`;
        
        showSuccess(message);
        
        setTimeout(() => {
            checkGoogleCalendarStatus();
            updateStats();
        }, 2000);
        
    } catch (error) {
        hideLoading();
        console.error('Progressive send error:', error);
        showError('เกิดข้อผิดพลาดในการส่งข้อมูลแบบ Progressive: ' + error.message);
    }
}

// ฟังก์ชันอัปเดตสถิติ Google Calendar
async function updateGoogleCalendarStats() {
    try {
        // ดึงข้อมูลสถิติ Google Calendar
        const response = await fetch(API_CONFIG.data, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=get_stats&academic_year_id=${ACADEMIC_YEAR_ID}`,
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success && data.data && data.data.google_calendar_stats) {
            const stats = data.data.google_calendar_stats;
            
            // อัปเดต progress bar
            const progressBar = document.querySelector('#googleCalendarStats .progress-bar');
            if (progressBar && stats.total_sessions > 0) {
                const percentage = (stats.synced_count / stats.total_sessions) * 100;
                progressBar.style.width = `${percentage}%`;
            }
        }
        
    } catch (error) {
        console.warn('⚠️ Failed to update Google Calendar stats:', error.message);
    }
}

// ปรับปรุงฟังก์ชัน updateStats เพื่อรวม Google Calendar
async function updateStats() {
    try {
        // ตรวจสอบค่าที่จำเป็น
        if (!ACADEMIC_YEAR_ID || ACADEMIC_YEAR_ID === 0) {
            throw new Error('Academic Year ID ไม่ถูกต้อง');
        }
        
        const data = await callAPI(API_CONFIG.data, 'get_stats', {
            academic_year_id: ACADEMIC_YEAR_ID
        });
        
        if (data.success && data.data) {
            // อัปเดตสถิติในส่วน header
            const stats = data.data;
            
            // อัปเดต Google Calendar stats
            const gcStats = stats.google_calendar_stats;
            if (!apiWorking) {
                apiWorking = true;
                updateAPIStatus('online', 'API ทำงานปกติ');
            }
        } else {
            throw new Error(data.message || 'ไม่สามารถอัปเดตสถิติได้');
        }
        
    } catch (error) {
        console.warn('⚠️ Stats update failed:', error.message);
        
        if (apiWorking) {
            apiWorking = false;
            updateAPIStatus('offline', 'API มีปัญหา');
        }
    }
}

// ========================================
// ฟังก์ชันอัปเดตตาราง
// ========================================

function updateHolidaysTable(holidays) {
    const tbody = document.getElementById('holidaysTableBody');
    
    if (!tbody) return;
    
    if (!holidays || holidays.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="3" class="text-center text-muted py-3">
                    <i class="fas fa-calendar-times fa-lg mb-2"></i><br>
                    ไม่มีวันหยุดในช่วงปีการศึกษานี้<br>
                    <small>ช่วงวันที่: <?php echo format_thai_date($start_date); ?> - <?php echo format_thai_date($end_date); ?></small>
                </td>
            </tr>
        `;
        return;
    }
    
    // กรองวันหยุดที่อยู่ในช่วงปีการศึกษา (ป้องกันเผื่อ API ส่งข้อมูลนอกช่วง)
    const startDate = new Date('<?php echo $start_date; ?>');
    const endDate = new Date('<?php echo $end_date; ?>');
    
    const filteredHolidays = holidays.filter(holiday => {
        const holidayDate = new Date(holiday.holiday_date);
        return holidayDate >= startDate && holidayDate <= endDate;
    });
    
    if (filteredHolidays.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="3" class="text-center text-muted py-3">
                    <i class="fas fa-info-circle fa-lg mb-2"></i><br>
                    ไม่มีวันหยุดในช่วงปีการศึกษานี้<br>
                    <small>ช่วงวันที่: <?php echo format_thai_date($start_date); ?> - <?php echo format_thai_date($end_date); ?></small>
                </td>
            </tr>
        `;
        return;
    }
    
    // สร้างแถวตาราง (ใช้ filteredHolidays แทน holidays)
    tbody.innerHTML = filteredHolidays.map(holiday => {
        const date = new Date(holiday.holiday_date);
        const day = date.getDate();
        const monthNames = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 
                          'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

        const month = monthNames[date.getMonth()];
        const weekdays = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
        const weekday = weekdays[date.getDay()];
        
        const badgeClass = getHolidayTypeBadge(holiday.holiday_type);
        const typeName = getHolidayTypeName(holiday.holiday_type);
        
        // เพิ่มการแสดงว่าอยู่ในช่วงปีการศึกษา
        const isInAcademicYear = date >= startDate && date <= endDate;
        const academicYearIndicator = isInAcademicYear ? 
            '<i class="fas fa-graduation-cap text-success ms-1" title="อยู่ในช่วงปีการศึกษา"></i>' : '';
        
        return `
            <tr class="holiday-row" data-date="${holiday.holiday_date}" data-name="${holiday.holiday_name}">
                <td>
                    <div class="date-display">
                        <strong>${day}</strong><br>
                        <small class="text-muted">
                            ${month}<br>
                            ${weekday}
                        </small>
                    </div>
                </td>
                <td>
                    <div class="holiday-name">
                        <span class="holiday-title">${holiday.holiday_name}${academicYearIndicator}</span>
                        ${holiday.english_name && holiday.english_name !== holiday.holiday_name ? 
                            `<br><small class="text-muted">${holiday.english_name}</small>` : ''}
                    </div>
                </td>
                <td>
                    <span class="badge ${badgeClass}">
                        ${typeName}
                    </span>
                    ${holiday.is_custom ? '<br><small class="text-success">เพิ่มเอง</small>' : ''}
                </td>
            </tr>
        `;
    }).join('');
}

function updateTeachingSchedulesTable(schedules) {
    const container = document.getElementById('teachingSchedulesContainer');
    
    if (!container) return;
    
    if (!schedules || schedules.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-3">
                <i class="fas fa-calendar-times fa-lg mb-2"></i><br>
                ยังไม่มีตารางสอน<br>
                <small>ไม่พบข้อมูลตารางสอนในปีการศึกษานี้</small>
            </div>
        `;
        return;
    }
    
    // จัดกลุ่มตามวัน
    const groupedByDay = {};
    const dayOrder = ['จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.'];
    
    schedules.forEach(schedule => {
        const day = schedule.day_of_week;
        if (!groupedByDay[day]) {
            groupedByDay[day] = [];
        }
        groupedByDay[day].push(schedule);
    });
    
    // สร้าง HTML
    let html = '';
    dayOrder.forEach(day => {
        if (groupedByDay[day]) {
            html += `<h6 class="text-primary mb-2">${day}</h6>`;
            
            groupedByDay[day].forEach(schedule => {
                html += `
                    <div class="schedule-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>${schedule.subject_code}</strong><br>
                                <small>${schedule.subject_name}</small><br>
                                <small class="text-muted">
                                    ${schedule.start_time} - ${schedule.end_time}<br>
                                    ห้อง ${schedule.room_number} | ${schedule.class_year}
                                </small>
                            </div>
                            <span class="badge bg-info">${schedule.credits} หน่วยกิต</span>
                        </div>
                    </div>
                `;
            });
            
            html += '<hr class="my-3">';
        }
    });
    
    container.innerHTML = html || `
        <div class="text-center text-muted py-3">
            <i class="fas fa-calendar-times fa-lg mb-2"></i><br>
            ยังไม่มีตารางสอน
        </div>
    `;
}

function updateClassSessionsTable(sessions) {
    const tbody = document.getElementById('classSessionsTableBody');
    if (!tbody) return;

    if (!sessions || sessions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted py-3">
                    <i class="fas fa-calendar-times fa-lg mb-2"></i><br>
                    ยังไม่มีบันทึกการเรียนการสอน<br>
                    <small>กดปุ่ม "สร้าง สร้างปฏิทินการสอน" เพื่อสร้างการบันทึก</small>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = sessions.map(session => {
        // ตรวจสอบว่าเป็นวิชาโมดูลหรือไม่
        let classYearDisplay = '';
        if (session.is_module_subject == 1 && session.group_id) {
            // แสดงชื่อกลุ่มและชื่อโมดูล
            classYearDisplay = `<span>${session.group_name || '-'}</span> <br>
                                <span>${session.module_name || '-'}</span>`;
        } else {
            // แสดง department, class_year, curriculum
            classYearDisplay = `
                <span>${session.department || '-'}</span>
                <span>${session.class_year || '-'}</span>
                <span>${session.curriculum || '-'}</span>
            `;
        }

        const isPendingCancellation = session.notes && session.notes.includes('[รอการยกเลิก]');
        const statusBadge = isPendingCancellation ? 
            '<span class="badge bg-warning text-dark ms-2">รอการยกเลิก</span>' : '';

        // ส่งข้อมูลครบไปยัง requestCancellation
        return `
            <tr class="${isPendingCancellation ? 'table-warning' : ''}">
                <td>${formatThaiDate(session.session_date)}</td>
                <td>
                    ${session.subject_code}<br>
                    <small>${session.subject_name}</small>
                    ${statusBadge}
                </td>
                <td>${classYearDisplay}</td>
                <td>${session.original_room || session.actual_room || '-'}</td>
                <td>${session.notes || '-'}</td>
                <td>
                    ${isPendingCancellation ? 
                        '<span class="text-muted">กำลังดำเนินการ...</span>' :
                        `<button class="btn btn-sm btn-outline-warning" onclick="requestCancellation(
                            ${session.session_id}, 
                            '${session.subject_code}', 
                            '${session.subject_name}', 
                            '${session.session_date}', 
                            '${session.class_year}', 
                            '${session.department}', 
                            '${session.curriculum}', 
                            '${session.group_name}', 
                            '${session.module_name}', 
                            ${session.is_module_subject}
                        )">
                            <i class="fas fa-times-circle"></i> ขอยกเลิก
                        </button>`
                    }
                </td>
            </tr>
        `;
    }).join('');
}

// ========================================
// ฟังก์ชันช่วยเหลือ
// ========================================

function formatThaiDate(dateString) {
    if (!dateString) return '';
    
    try {
        const date = new Date(dateString);
        const thaiMonths = [
            'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
            'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
        ];
        
        const day = date.getDate();
        const month = thaiMonths[date.getMonth()];
        const year = date.getFullYear() + 543;
        
        return `${day} ${month} ${year}`;
    } catch (error) {
        return dateString;
    }
}

function getHolidayTypeBadge(type) {
    const badges = {
        'religious': 'bg-warning text-dark',
        'royal': 'bg-info text-white', 
        'national': 'bg-danger text-white',
        'substitute': 'bg-secondary text-white'
    };
    
    return badges[type] || 'bg-secondary text-white';
}

function getHolidayTypeName(type) {
    const names = {
        'religious': 'ศาสนา',
        'royal': 'ราชวงศ์',
        'national': 'ชาติ',
        'substitute': 'ชดเชย'
    };
    
    return names[type] || 'อื่นๆ';
}

function filterHolidays(searchTerm) {
    const rows = document.querySelectorAll('#holidaysTableBody .holiday-row');
    const term = searchTerm.toLowerCase();
    
    let visibleCount = 0;
    
    rows.forEach(row => {
        const holidayName = row.getAttribute('data-name')?.toLowerCase() || '';
        const dateText = row.querySelector('.holiday-title')?.textContent?.toLowerCase() || '';
        
        if (holidayName.includes(term) || dateText.includes(term)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // แสดงข้อความถ้าไม่พบผลการค้นหา
    const tbody = document.getElementById('holidaysTableBody');
    let noResultsRow = document.getElementById('noSearchResults');
    
    if (visibleCount === 0 && term.length > 0) {
        if (!noResultsRow) {
            noResultsRow = document.createElement('tr');
            noResultsRow.id = 'noSearchResults';
            noResultsRow.innerHTML = `
                <td colspan="3" class="text-center text-muted py-3">
                    <i class="fas fa-search fa-lg mb-2"></i><br>
                    ไม่พบวันหยุดที่ตรงกับ "${searchTerm}"
                </td>
            `;
            tbody.appendChild(noResultsRow);
        }
    } else if (noResultsRow) {
        noResultsRow.remove();
    }
}

function refreshData() {
    showLoading();
    setTimeout(() => {
        location.reload();
    }, 500);
}

function startStatsUpdate() {
    if (statsUpdateInterval) {
        clearInterval(statsUpdateInterval);
    }

    statsUpdateInterval = setInterval(updateStats, 30000);
}

function stopStatsUpdate() {
    if (statsUpdateInterval) {
        clearInterval(statsUpdateInterval);
        statsUpdateInterval = null;
    }
}

// อัปเดต loadAllData เพื่อรวม Google Calendar
async function loadAllData() {
    try {
        // ตรวจสอบค่าที่จำเป็น
        if (!ACADEMIC_YEAR_ID || ACADEMIC_YEAR_ID === 0) {
            throw new Error('Academic Year ID ไม่ถูกต้อง: ' + ACADEMIC_YEAR_ID);
        }

        await Promise.allSettled([
            loadHolidaysData(),
            loadTeachingSchedules(),
            loadClassSessions(),
            checkGoogleCalendarStatus()
        ]).then(results => {
            results.forEach((result, index) => {
                const functions = ['loadHolidaysData', 'loadTeachingSchedules', 'loadClassSessions', 'checkGoogleCalendarStatus'];
                if (result.status === 'rejected') {
                    console.warn(`${functions[index]} failed:`, result.reason);
                } else {
                    console.log(`${functions[index]} completed successfully`);
                }
            });
        });
    } catch (error) {
        console.error('Error in loadAllData:', error);
    }
}

function showAPIAlert(type, message) {
    const alertDiv = document.getElementById('apiStatusAlert');
    if (alertDiv) {
        alertDiv.style.display = 'block';
        alertDiv.className = `alert alert-${type} ${type === 'danger' ? 'error-alert' : 'warning-alert'} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            <h5><i class="fas fa-${type === 'danger' ? 'exclamation-triangle' : 'info-circle'}"></i> API Status</h5>
            <p>${message}</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
    }
}

// ========================================
// ฟังก์ชันตรวจสอบสถานะระบบ
// ========================================

function systemHealthCheck() {
    const checks = {
        academicYearId: ACADEMIC_YEAR_ID > 0,
        apiConfig: Object.keys(API_CONFIG).length > 0,
        requiredElements: {
            loadingOverlay: !!document.getElementById('loadingOverlay'),
            holidaysTableBody: !!document.getElementById('holidaysTableBody'),
            apiStatusIndicator: !!document.getElementById('apiStatusIndicator'),
            apiStatusText: !!document.getElementById('apiStatusText'),
            successModal: !!document.getElementById('successModal'),
            errorModal: !!document.getElementById('errorModal'),
            debugModal: !!document.getElementById('debugModal')
        },
        bootstrapLoaded: typeof bootstrap !== 'undefined',
        jqueryLoaded: typeof $ !== 'undefined'
    };
    return checks;
}

// ========================================
// การจัดการ Error และ Event Listeners
// ========================================

// จัดการ error ทั่วไป
window.addEventListener('error', function(e) {
    console.error('JavaScript Error:', e.error);
    hideLoading();
});

// จัดการ unhandled promise rejection
window.addEventListener('unhandledrejection', function(e) {
    console.error('Unhandled Promise Rejection:', e.reason);
    hideLoading();
});

// ========================================
// การเริ่มต้นระบบ
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    // ตรวจสอบ academic_year_id
    if (!ACADEMIC_YEAR_ID || ACADEMIC_YEAR_ID === 0) {
        console.error('Academic Year ID ไม่ถูกต้อง');
        showAPIAlert('danger', 'Academic Year ID ไม่ถูกต้อง กรุณาตั้งค่าปีการศึกษาปัจจุบัน');
        updateAPIStatus('offline', 'ปีการศึกษาไม่ถูกต้อง');
        return;
    }

    // โหลดรายชื่ออาจารย์และตั้งค่า default filter
    loadTeacherList();
    const select = document.getElementById('teacherFilter');
    if (select) {
        select.addEventListener('change', function() {
            loadTeachingSchedules();
            loadClassSessions();
        });
    }

    // ตรวจสอบสถานะระบบ
    const healthCheck = systemHealthCheck();
    if (!healthCheck.academicYearId) {
        showAPIAlert('danger', 'ข้อมูลปีการศึกษาไม่ถูกต้อง');
        return;
    }
    
    loadAllData();
    updateStats();
    setInterval(checkGoogleCalendarStatus, 5 * 60 * 1000);
});

// ========================================
// การเริ่มต้นทันทีเมื่อโหลดหน้า
// ========================================

// ตรวจสอบการเชื่อมต่อเบื้องต้น
setTimeout(() => {
    if (ACADEMIC_YEAR_ID && ACADEMIC_YEAR_ID > 0) {
        updateAPIStatus('loading', 'กำลังเตรียมระบบ...');
        
        // ทดสอบการเชื่อมต่อแบบเบื้องต้น
        fetch(API_CONFIG.data, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get_stats&academic_year_id=' + ACADEMIC_YEAR_ID
        })
        .then(response => {
            if (response.ok) {
                updateAPIStatus('online', 'ระบบพร้อมใช้งาน');
                apiWorking = true;
            } else {
                updateAPIStatus('offline', 'API ไม่ตอบสนอง');
            }
        })
        .catch(() => {
            updateAPIStatus('offline', 'ไม่สามารถเชื่อมต่อ API ได้');
        });
    }
}, 500);

// ========================================
// Export functions for debugging
// ========================================

window.dashboardAPI = {
    // Main functions
    generateClassSessions,
    loadAllData,
    updateStats,
    refreshData,
    
    // Data loading functions
    loadHolidaysData,
    loadTeachingSchedules,
    loadClassSessions,
    
    // Google Calendar functions
    checkGoogleCalendarStatus,
    connectGoogleCalendar,
    refreshGoogleToken,
    testGoogleConnection,
    updateGoogleCalendarStats,
    
    // Utility functions
    callAPI,
    formatThaiDate,
    filterHolidays,
    
    // System functions
    systemHealthCheck,
    showAPIAlert,
    
    // State
    getAPIWorking: () => apiWorking,
    getAcademicYearId: () => ACADEMIC_YEAR_ID,
    getAPIConfig: () => API_CONFIG,
    
    // Controls
    startStatsUpdate,
    stopStatsUpdate,
    
    // Testing functions
    testAllAPIs: async function() {
        const results = {};
        for (const [name, path] of Object.entries(API_CONFIG)) {
            try {
                const response = await fetch(path, { method: 'HEAD' });
                results[name] = {
                    status: response.status,
                    ok: response.ok,
                    statusText: response.statusText
                };
            } catch (error) {
                results[name] = {
                    error: error.message,
                    ok: false
                };
            }
        }
        return results;
    },
    
    clearAllData: function() {
        // ล้างข้อมูลในตาราง
        const elements = [
            'holidaysTableBody',
            'teachingSchedulesContainer', 
            'classSessionsTableBody'
        ];
        
        elements.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.innerHTML = '<tr><td colspan="7" class="text-center">Loading...</td></tr>';
            }
        });
    }
};

// ========================================
// ฟังก์ชันจัดการ สร้างปฏิทินการสอน
// ========================================

// ฟังก์ชันแก้ไข Class Session
async function editSession(sessionId) {
    try {
        showLoading();
        
        // ดึงข้อมูล session
        const sessionResponse = await fetch('../api/api_edit_session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_session&session_id=${sessionId}`
        });
        
        const sessionData = await sessionResponse.json();
        
        if (!sessionData.success) {
            throw new Error(sessionData.message);
        }
        
        // ดึงข้อมูลห้องเรียน
        const classroomResponse = await fetch('../api/api_edit_session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_classrooms'
        });
        
        const classroomData = await classroomResponse.json();
        
        // ดึงข้อมูลช่วงเวลา
        const timeSlotsResponse = await fetch('../api/api_edit_session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_time_slots'
        });
        
        const timeSlotsData = await timeSlotsResponse.json();
        
        hideLoading();
        
        if (!classroomData.success || !timeSlotsData.success) {
            throw new Error('ไม่สามารถดึงข้อมูลพื้นฐานได้');
        }
        
        // เติมข้อมูลใน form
        populateEditForm(sessionData.data, classroomData.data, timeSlotsData.data);
        
        // แสดง modal
        const modal = new bootstrap.Modal(document.getElementById('editSessionModal'));
        modal.show();
        
    } catch (error) {
        hideLoading();
        showError('เกิดข้อผิดพลาด: ' + error.message);
    }
}

// ฟังก์ชันเติมข้อมูลใน edit form
function populateEditForm(sessionData, classrooms, timeSlots) {
    // ข้อมูลพื้นฐาน
    document.getElementById('edit_session_id').value = sessionData.session_id;
    document.getElementById('edit_schedule_id').value = sessionData.schedule_id;
    document.getElementById('edit_subject_info').value = `${sessionData.subject_code} - ${sessionData.subject_name}`;
    document.getElementById('edit_class_year').value = sessionData.class_year || '-';
    
    // ข้อมูลที่แก้ไขได้
    document.getElementById('edit_session_date').value = sessionData.session_date;
    document.getElementById('edit_notes').value = sessionData.notes || '';
    
    // เติมข้อมูลห้องเรียน
    const classroomSelect = document.getElementById('edit_classroom');
    classroomSelect.innerHTML = '<option value="">เลือกห้องเรียน</option>';
    classrooms.forEach(classroom => {
        const option = document.createElement('option');
        option.value = classroom.classroom_id;
        option.textContent = `${classroom.room_number} (${classroom.building})`;
        if (classroom.classroom_id == sessionData.actual_classroom_id) {
            option.selected = true;
        }
        classroomSelect.appendChild(option);
    });
    
    // เติมข้อมูลช่วงเวลา
    const startTimeSelect = document.getElementById('edit_start_time');
    const endTimeSelect = document.getElementById('edit_end_time');
    
    startTimeSelect.innerHTML = '<option value="">เลือกเวลาเริ่ม</option>';
    endTimeSelect.innerHTML = '<option value="">เลือกเวลาสิ้นสุด</option>';
    
    timeSlots.forEach(slot => {
        // เวลาเริ่ม
        const startOption = document.createElement('option');
        startOption.value = slot.time_slot_id;
        startOption.textContent = `คาบ ${slot.slot_number} (${slot.start_time.substring(0,5)} - ${slot.end_time.substring(0,5)})`;
        if (slot.time_slot_id == sessionData.actual_start_time_slot_id) {
            startOption.selected = true;
        }
        startTimeSelect.appendChild(startOption);
        
        // เวลาสิ้นสุด
        const endOption = document.createElement('option');
        endOption.value = slot.time_slot_id;
        endOption.textContent = `คาบ ${slot.slot_number} (${slot.start_time.substring(0,5)} - ${slot.end_time.substring(0,5)})`;
        if (slot.time_slot_id == sessionData.actual_end_time_slot_id) {
            endOption.selected = true;
        }
        endTimeSelect.appendChild(endOption);
    });
}

// ฟังก์ชันอัปเดต session
async function updateSession() {
    try {
        const form = document.getElementById('editSessionForm');
        const formData = new FormData(form);
        formData.append('action', 'update_session');
        
        showLoading();
        
        const response = await fetch('../api/api_edit_session.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        hideLoading();
        
        if (data.success) {
            showSuccess(data.message);
            
            // ปิด modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('editSessionModal'));
            modal.hide();
            
            // รีเฟรชข้อมูล
            setTimeout(() => {
                loadAllData();
            }, 1000);
        } else {
            throw new Error(data.message);
        }
        
    } catch (error) {
        hideLoading();
        showError('เกิดข้อผิดพลาด: ' + error.message);
    }
}

// ฟังก์ชันแสดง modal ยกเลิก session
function showCancelSessionModal() {
    // ดึงข้อมูลจาก edit form
    const sessionId = document.getElementById('edit_session_id').value;
    const scheduleId = document.getElementById('edit_schedule_id').value;
    const sessionDate = document.getElementById('edit_session_date').value;
    const subjectInfo = document.getElementById('edit_subject_info').value;
    const classYear = document.getElementById('edit_class_year').value;
    
    // ดึงข้อมูลห้องและเวลา
    const classroomSelect = document.getElementById('edit_classroom');
    const startTimeSelect = document.getElementById('edit_start_time');
    const endTimeSelect = document.getElementById('edit_end_time');
    
    const classroomText = classroomSelect.options[classroomSelect.selectedIndex]?.text || '-';
    const startTimeText = startTimeSelect.options[startTimeSelect.selectedIndex]?.text || '-';
    const endTimeText = endTimeSelect.options[endTimeSelect.selectedIndex]?.text || '-';
    
    // เติมข้อมูลใน cancel form
    document.getElementById('cancel_session_id').value = sessionId;
    document.getElementById('cancel_schedule_id').value = scheduleId;
    document.getElementById('cancel_session_date').value = sessionDate;
    
    // แสดงข้อมูลการเรียนที่จะยกเลิก
    const sessionInfoDiv = document.getElementById('cancel_session_info');
    sessionInfoDiv.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <strong>รายวิชา:</strong><br>
                ${subjectInfo}<br><br>
                <strong>ชั้นปี:</strong><br>
                ${classYear}
            </div>
            <div class="col-md-6">
                <strong>วันที่เรียน:</strong><br>
                ${formatThaiDate(sessionDate)}<br><br>
                <strong>ห้องเรียน:</strong><br>
                ${classroomText}<br><br>
                <strong>เวลา:</strong><br>
                ${startTimeText} ถึง ${endTimeText}
            </div>
        </div>
    `;
    
    // ปิด edit modal
    const editModal = bootstrap.Modal.getInstance(document.getElementById('editSessionModal'));
    editModal.hide();
    
    // แสดง cancel modal
    const cancelModal = new bootstrap.Modal(document.getElementById('cancelSessionModal'));
    cancelModal.show();
}

// ฟังก์ชันยืนยันการยกเลิก session
async function confirmCancelSession() {
    try {
        const form = document.getElementById('cancelSessionForm');
        const formData = new FormData(form);
        formData.append('action', 'cancel_session');
        
        // ตรวจสอบข้อมูลที่จำเป็น
        const cancellationType = document.getElementById('cancel_type').value;
        const reason = document.getElementById('cancel_reason').value.trim();
        
        if (!cancellationType) {
            showError('กรุณาเลือกประเภทการยกเลิก');
            return;
        }
        
        if (!reason) {
            showError('กรุณาระบุเหตุผลการยกเลิก');
            return;
        }
        
        if (!confirm('คุณแน่ใจหรือไม่ที่จะยกเลิกการเรียนครั้งนี้?\n\nการยกเลิกจะไม่สามารถย้อนกลับได้ กรุณาตรวจสอบข้อมูลให้ถูกต้อง')) {
            return;
        }
        
        showLoading();
        
        const response = await fetch('../api/api_edit_session.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        hideLoading();
        
        if (data.success) {
            showSuccess(data.message);
            
            // ปิด modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('cancelSessionModal'));
            modal.hide();
            
            // รีเฟรชข้อมูล
            setTimeout(() => {
                loadAllData();
            }, 1000);
            
            // แสดงข้อความแนะนำ
            if (data.data && data.data.is_makeup_required) {
                setTimeout(() => {
                    if (confirm('การยกเลิกเสร็จสิ้น!\n\nต้องการไปหน้าจัดการการชดเชยเพื่อกำหนดวันสอนชดเชยหรือไม่?')) {
                        window.location.href = 'compensation.php';
                    }
                }, 2000);
            }
        } else {
            throw new Error(data.message);
        }
        
    } catch (error) {
        hideLoading();
        showError('เกิดข้อผิดพลาด: ' + error.message);
    }
}

// ฟังก์ชันขอยกเลิกการเรียน
function requestCancellation(sessionId, subjectCode, subjectName, sessionDate, classYear, department, curriculum, groupName, moduleName, isModuleSubject) {
    try {
        document.getElementById('request_session_id').value = sessionId;
        document.getElementById('request_session_date').value = sessionDate;

        // สร้างข้อมูลชั้นปี/กลุ่มโมดูล
        let classYearDisplay = '';
        if (isModuleSubject == 1 && groupName) {
            classYearDisplay = `<span>${groupName || '-'}</span> <br><span>${moduleName || '-'}</span>`;
        } else {
            classYearDisplay = `
                <span>${department || '-'}</span>
                <span>${classYear || '-'}</span>
                <span>${curriculum || '-'}</span>
            `;
        }

        const sessionInfoDiv = document.getElementById('request_session_info');
        sessionInfoDiv.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <strong>รายวิชา:</strong><br>
                    ${subjectCode} - ${subjectName}<br><br>
                    <strong>ชั้นปี:</strong><br>
                    ${classYearDisplay}
                </div>
                <div class="col-md-6">
                    <strong>วันที่เรียน:</strong><br>
                    ${formatThaiDate(sessionDate)}<br><br>
                    <strong>สถานะ:</strong><br>
                    <span class="badge bg-primary">กำลังเรียน</span>
                </div>
            </div>
        `;

        document.getElementById('request_cancel_type').value = '';
        document.getElementById('request_cancel_reason').value = '';

        const modal = new bootstrap.Modal(document.getElementById('cancellationRequestModal'));
        modal.show();
        
    } catch (error) {
        console.error('Error in requestCancellation:', error);
        showError('เกิดข้อผิดพลาดในการเปิดฟอร์มขอยกเลิก');
    }
}

// ฟังก์ชันส่งคำขอยกเลิก
async function submitCancellationRequest() {
    try {
        const form = document.getElementById('cancellationRequestForm');
        const formData = new FormData(form);
        formData.append('action', 'request_cancellation');

        const cancellationType = document.getElementById('request_cancel_type').value;
        const reason = document.getElementById('request_cancel_reason').value.trim();
        
        if (!cancellationType) {
            showError('กรุณาเลือกประเภทการยกเลิก');
            // ไม่ปิด modal ให้ user แก้ไข
            return;
        }
        
        if (!reason) {
            showError('กรุณาระบุเหตุผลการยกเลิก');
            // ไม่ปิด modal ให้ user แก้ไข
            return;
        }
        
        if (!confirm('คุณแน่ใจหรือไม่ที่จะยกเลิกการเรียนครั้งนี้?\n\nการยกเลิกจะดำเนินการทันทีและไม่สามารถย้อนกลับได้\n\nการเรียนจะถูกลบออกจากตารางทันที')) {
            // ไม่ปิด modal หาก user ยกเลิก
            return;
        }
        
        showLoading();
        
        const response = await fetch('../api/api_edit_session.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        hideLoading();
        
        if (data.success) {
            showSuccess(data.message);
            
            // ปิด modal เฉพาะเมื่อสำเร็จ
            const modal = bootstrap.Modal.getInstance(document.getElementById('cancellationRequestModal'));
            modal.hide();
            
            setTimeout(() => {
                loadAllData();
            }, 1000);
            
            if (data.data && data.data.compensation_id && data.data.is_makeup_required) {
                setTimeout(() => {
                    if (confirm('ยกเลิกการเรียนเสร็จสิ้น!\n\nต้องการไปหน้าจัดการการชดเชยเพื่อกำหนดวันสอนชดเชยหรือไม่?')) {
                        window.location.href = 'compensation.php';
                    }
                }, 2000);
            }
        } else {
            // เกิดข้อผิดพลาด - ไม่ปิด modal ให้ user ลองใหม่
            showError('เกิดข้อผิดพลาด: ' + data.message + '\n\nกรุณาแก้ไขข้อมูลและลองใหม่อีกครั้ง');
        }
        
    } catch (error) {
        hideLoading();
        console.error('submitCancellationRequest error:', error);
        
        // เกิดข้อผิดพลาด - ไม่ปิด modal ให้ user ลองใหม่
        let errorMessage = 'เกิดข้อผิดพลาด: ' + error.message;
        errorMessage += '\n\nกรุณาตรวจสอบข้อมูลและลองใหม่อีกครั้ง หรือรีเฟรชหน้าเว็บ';
        
        showError(errorMessage);
    }
}
</script>
</body>
</html>