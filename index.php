<?php
/**
 * ระบบจัดการตารางสอน - เวอร์ชันสมบูรณ์
 * รองรับการค้นหาและเพิ่มวิชาใหม่ + วิชานอกสาขา
 */

// ตรวจสอบการเข้าสู่ระบบก่อนแสดงหน้า
require_once 'api/auth_check.php';
requireLogin('login.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userData = getUserData();

// เพิ่มฟังก์ชัน format_thai_date
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

// เพิ่มการเชื่อมต่อฐานข้อมูลและดึงข้อมูลปีการศึกษาปัจจุบัน
try {
    require_once 'config/database.php';
    
    if (!isset($conn) || !$conn) {
        throw new Exception("Database connection failed");
    }

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
        // ตั้งค่าเริ่มต้นหากไม่พบข้อมูล
        $academic_year_id = 0;
        $academic_year = '-';
        $semester = '-';
        $start_date = null;
        $end_date = null;
    }

} catch (Exception $e) {
    // จัดการข้อผิดพลาดแบบ graceful
    error_log("Database error in index.php: " . $e->getMessage());
    $academic_year_id = 0;
    $academic_year = 'ไม่สามารถโหลดข้อมูลได้';
    $semester = '-';
    $start_date = null;
    $end_date = null;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>ตารางสอนประจำภาคเรียน - Kaiadmin Dashboard</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <link rel="icon" href="../img/coe/CoE-LOGO.png" type="image/x-icon" />

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
        body {
            font-family: 'Public Sans', 'Sarabun', sans-serif;
        }
        .schedule-grid {
            display: grid;
            grid-template-columns: 100px repeat(13, 1fr);
            gap: 2px;
        }
        /* Subject Search Styles */
        .subject-search-container {
            position: relative;
        }
        .subject-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 5px 5px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        .subject-suggestion-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        .subject-suggestion-item:hover {
            background-color: #f5f5f5;
        }
        .subject-suggestion-item:last-child {
            border-bottom: none;
        }
        .suggestion-name {
            color: #666;
            font-size: 0.9em;
        }
        .suggestion-type {
            font-size: 0.8em;
            background: #007bff;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            margin-left: 5px;
        }

        /* Add New Subject Form */
        .add-subject-form {
            display: none;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
        }
        .add-subject-form.show {
            display: block;
        }
        .btn-toggle-add-subject {
            font-size: 0.85em;
            padding: 5px 10px;
        }

        /* External Subject Styles */
        .external-badge {
            background: #ff8c00;
            color: white;
            font-size: 0.7em;
            padding: 2px 6px;
            border-radius: 3px;
            margin-left: 5px;
        }
        .external-option {
            background-color: #fff3cd !important;
            font-style: italic;
        }
        .internal-option {
            background-color: #f8f9fa;
        }

        /* Enhanced Modal Styling */
        .modal-lg {
            max-width: 900px;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .required-field {
            color: #dc3545;
        }
        .external-required {
            color: #dc3545;
        }
        .help-text {
            font-size: 0.875em;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        /* Tooltip for external subjects in schedule grid */
        .external-subject-item {
            position: relative;
        }
        .external-subject-item::before {
            content: "นอกสาขา";
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff8c00;
            color: white;
            font-size: 0.65em;
            padding: 2px 4px;
            border-radius: 3px;
            z-index: 10;
        }
        .schedule-grid {
            display: grid;
            grid-template-columns: 100px repeat(13, 1fr);
            gap: 0;
            border: 1px solid #d1d5db;
            background: #f8fafc;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(30,75,156,0.06);
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .grid-cell {
            padding: 8px 4px;
            text-align: center;
            min-height: 48px;
            border-right: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            background: #fff;
            font-size: 1rem;
            transition: background 0.2s;
        }
        .grid-header, .time-slot-header {
            background: linear-gradient(135deg, #667eea 20%, #764ba2 100%);
            color: white;
            font-weight: bold;
            border-bottom: 2px solid #764ba2;
        }
        .day-cell {
            background: #f3f4f6;
            font-weight: bold;
            color: #1e4b9c;
            border-right: 2px solid #3d80dc;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .schedule-item {
            background: #f0f7ff;
            border-radius: 6px;
            border: 1px solid #c7e0fa;
            padding: 8px 4px;
            margin: 0;
            font-size: 0.97rem;
            box-shadow: 0 1px 2px rgba(61,128,220,0.04);
        }
        .theory-class {
            background: #e6f7e9 !important;
            border: 1px solid #b7e4c7 !important;
        }
        .lab-class {
            background: #e3f6fb !important;
            border: 1px solid #b8dfec !important;
        }
        .external-class {
            background: #fff7e6 !important;
            border: 1px solid #ffe4b3 !important;
        }
        @media (max-width: 991px) {
            .schedule-grid {
                grid-template-columns: 80px repeat(13, 1fr);
                font-size: 0.95em;
            }
            .grid-cell {
                min-height: 36px;
                padding: 6px 2px;
            }
        }
        .badge.bg-info {
    background-color: #17a2b8 !important;
    font-size: 0.7em;
}

#coTeachersSection {
    border: 1px solid #e3f2fd;
    border-radius: 8px;
    padding: 15px;
    background-color: #f8f9fa;
}

#coTeachersSection h6 {
    margin-bottom: 15px;
    color: #1976d2;
}

.current-user-option {
    background-color: #e3f2fd !important;
    font-weight: bold;
}

.multi-teacher-indicator {
    position: absolute;
    top: 2px;
    right: 2px;
    background: #17a2b8;
    color: white;
    font-size: 0.6em;
    padding: 1px 4px;
    border-radius: 2px;
    z-index: 5;
}
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

      <div class="main-panel">
        <div class="main-header">
          <div class="main-header-logo">
          </div>
            <?php include 'includes/header.php'; ?>
        </div>
    
        <div class="container">
          <div class="page-inner">
            <div class="row">
                <div class="col-12">
                    <div class="stats-card">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2><i class="fas fa-calendar-alt"></i> ตารางสอนประจำภาคเรียน</h2>
                                <p class="mb-1">ปีการศึกษา <?php echo $academic_year; ?> เทอม <?php echo $semester; ?></p>
                                 <small>ระหว่างวันที่ <?php echo format_thai_date($start_date); ?> - <?php echo format_thai_date($end_date); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Filter Bar -->
            <div class="row">
              <div class="col-md-12">
                <div class="card">
                  <div class="card-body">
                    <div class="row">
                      <div class="col-md-2">
                        <div class="form-group">
                          <label>ปีการศึกษา</label>
                          <select id="academicYearFilter" class="form-select">
                            <option value="">ทั้งหมด</option>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-2">
                        <div class="form-group">
                          <label>อาจารย์</label>
                          <select id="teacherFilter" class="form-select">
                            <option value="">ทั้งหมด</option>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-2">
                        <div class="form-group">
                          <label>ชั้นปี</label>
                          <select id="yearLevelFilter" class="form-select">
                            <option value="">ทั้งหมด</option>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-2">
                        <div class="form-group">
                          <label>ห้องเรียน</label>
                          <select id="classroomFilter" class="form-select">
                            <option value="">ทั้งหมด</option>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="form-group">
                          <label>&nbsp;</label>
                          <div class="d-flex gap-2 justify-content-end">
                            <button id="btnReset" class="btn btn-outline-secondary">
                              <i class="fas fa-redo"></i> รีเซ็ต
                            </button>
                            <button id="btnAddSchedule" class="btn btn-success">
                              <i class="fas fa-plus"></i> เพิ่ม
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-12">
                <div class="card">
                  <div class="card-header">
                    <h4 class="card-title">ตารางสอน</h4>
                  </div>
                  <div class="card-body">
                    <div class="table-responsive">
                      <div class="schedule-grid" id="scheduleGrid">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal เพิ่ม/แก้ไขตารางสอน -->
        <div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="scheduleModalLabel">เพิ่มตารางสอน</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="scheduleForm">
                            <input type="hidden" id="schedule_id" name="schedule_id" value="0">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="academic_year_id" class="form-label">ปีการศึกษา <span class="required-field">*</span></label>
                                    <select id="academic_year_id" name="academic_year_id" class="form-select" required>
                                        <option value="">-- เลือกปีการศึกษา --</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="user_id" class="form-label">อาจารย์ <span class="required-field">*</span></label>
                                    <select id="user_id" name="user_id" class="form-select" required>
                                        <option value="">-- เลือกอาจารย์ --</option>
                                    </select>
                                </div>
                            </div>
                            <!-- เพิ่มในส่วน modal form -->
                            <div class="row mb-3" id="coTeachersSection" style="display: none;">
                                <div class="col-md-12">
                                    <h6 class="text-primary"><i class="fas fa-users"></i> อาจารย์ร่วมสอน (สำหรับวิชาปฏิบัติ)</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="co_user_id" class="form-label">อาจารย์ร่วมคนที่ 1</label>
                                            <select id="co_user_id" name="co_user_id" class="form-select">
                                                <option value="">-- เลือกอาจารย์ร่วม --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="co_user_id_2" class="form-label">อาจารย์ร่วมคนที่ 2</label>
                                            <select id="co_user_id_2" name="co_user_id_2" class="form-select">
                                                <option value="">-- เลือกอาจารย์ร่วม --</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="help-text mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle"></i> 
                                            วิชาปฏิบัติสามารถมีอาจารย์ร่วมสอนได้สูงสุด 3 คน (อาจารย์หลัก + อาจารย์ร่วม 2 คน)
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <!-- Enhanced Subject Selection -->
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="subject_search" class="form-label">วิชา <span class="required-field">*</span></label>
                                    <div class="subject-search-container">
                                        <input type="text" id="subject_search" class="form-control" 
                                               placeholder="พิมพ์รหัสวิชาหรือชื่อวิชาเพื่อค้นหา..." autocomplete="off">
                                        <div id="subject_suggestions" class="subject-suggestions"></div>
                                    </div>
                                    <input type="hidden" id="subject_id" name="subject_id">
                                    <div class="help-text">
                                        พิมพ์รหัสวิชาหรือชื่อวิชาเพื่อค้นหา หรือ 
                                        <button type="button" class="btn btn-link p-0 btn-toggle-add-subject">
                                            <i class="fas fa-plus"></i> เพิ่มวิชาใหม่
                                        </button>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" value="1" id="is_module_subject" name="is_module_subject">
                                        <label class="form-check-label" for="is_module_subject">
                                            เป็นวิชาโมดูล
                                        </label>
                                    </div>
                                    <!-- Add New Subject Form -->
                                    <div id="addSubjectForm" class="add-subject-form">
                                        <h6 class="mb-3"><i class="fas fa-plus-circle"></i> เพิ่มวิชาใหม่</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label for="new_subject_code" class="form-label">รหัสวิชา <span class="required-field">*</span></label>
                                                <input type="text" id="new_subject_code" class="form-control" placeholder="เช่น 0451430364">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="new_subject_type" class="form-label">ประเภทวิชา</label>
                                                <select id="new_subject_type" class="form-select">
                                                    <option value="ทฤษฎี">ทฤษฎี</option>
                                                    <option value="ปฏิบัติ">ปฏิบัติ</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-md-12">
                                                <label for="new_subject_name" class="form-label">ชื่อวิชา <span class="required-field">*</span></label>
                                                <input type="text" id="new_subject_name" class="form-control" placeholder="เช่น การเขียนโปรแกรมเบื้องต้น">
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <button type="button" class="btn btn-primary btn-sm" id="btnSaveNewSubject">
                                                <i class="fas fa-save"></i> บันทึกวิชาใหม่
                                            </button>
                                            <button type="button" class="btn btn-secondary btn-sm btn-toggle-add-subject">
                                                <i class="fas fa-times"></i> ยกเลิก
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="day_of_week" class="form-label">วัน <span class="required-field">*</span></label>
                                    <select id="day_of_week" name="day_of_week" class="form-select" required>
                                        <option value="">-- เลือกวัน --</option>
                                        <option value="จ.">วันจันทร์</option>
                                        <option value="อ.">วันอังคาร</option>
                                        <option value="พ.">วันพุธ</option>
                                        <option value="พฤ.">วันพฤหัสบดี</option>
                                        <option value="ศ.">วันศุกร์</option>
                                        <option value="ส.">วันเสาร์</option>
                                        <option value="อา.">วันอาทิตย์</option>
                                    </select>
                                </div>
                                <div class="col-md-4" id="yearLevelSection">
                                    <label for="year_level_id" class="form-label">ชั้นปี <span class="required-field">*</span></label>
                                    <select id="year_level_id" name="year_level_id" class="form-select" required>
                                        <option value="">-- เลือกชั้นปี --</option>
                                    </select>
                                </div>
                                <div class="col-md-4" id="moduleGroupSection" style="display:none;">
                                    <label for="group_id" class="form-label">กลุ่มโมดูล <span class="required-field">*</span></label>
                                    <select id="group_id" name="group_id" class="form-select">
                                        <option value="">-- เลือกกลุ่มโมดูล --</option>
                                    </select>
                                    <div id="moduleGroupYearLevels" class="help-text mt-2 text-primary"></div>
                                </div>
                                <div class="col-md-4">
                                    <label for="classroom_id" class="form-label">ห้องเรียน <span class="required-field">*</span></label>
                                    <select id="classroom_id" name="classroom_id" class="form-select" required>
                                        <option value="">-- เลือกห้องเรียน --</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="start_time_slot_id" class="form-label">คาบเริ่มต้น <span class="required-field">*</span></label>
                                    <select id="start_time_slot_id" name="start_time_slot_id" class="form-select" required>
                                        <option value="">-- เลือกคาบเริ่มต้น --</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="end_time_slot_id" class="form-label">คาบสิ้นสุด <span class="required-field">*</span></label>
                                    <select id="end_time_slot_id" name="end_time_slot_id" class="form-select" required>
                                        <option value="">-- เลือกคาบสิ้นสุด --</option>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="button" class="btn btn-primary" id="btnSaveSchedule">บันทึก</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal ยืนยันการลบ -->
        <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteConfirmModalLabel">ยืนยันการลบ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>คุณต้องการลบรายการนี้ใช่หรือไม่?</p>
                        <p class="text-danger">หมายเหตุ: การลบนี้ไม่สามารถเรียกคืนได้</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="button" class="btn btn-danger" id="btnConfirmDelete">ลบ</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <?php include 'includes/footer.php'; ?>
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
const userType = "<?php echo $userData['user_type']; ?>";
const currentUserId = <?php echo $userData['user_id']; ?>;
const userName = "<?php echo htmlspecialchars($userData['title'] . $userData['name']); ?>";
let selectedModuleYearLevels = [];
let currentScheduleId = 0;
let scheduleData = [];
let teachersData = [];
let subjectsData = [];
let classroomsData = [];
let yearLevelsData = [];
let timeSlotsData = [];
let academicYearsData = [];
let deleteScheduleId = 0;
let daysOfWeek = [
    { code: 'จ.', name: 'จันทร์' },
    { code: 'อ.', name: 'อังคาร' },
    { code: 'พ.', name: 'พุธ' },
    { code: 'พฤ.', name: 'พฤหัสบดี' },
    { code: 'ศ.', name: 'ศุกร์' },
    { code: 'ส.', name: 'เสาร์' },
    { code: 'อา.', name: 'อาทิตย์' }
];

// Error handling
function handleAjaxError(xhr, status, error, errorContext = '') {
    console.error(`AJAX Error in ${errorContext}:`, {
        status: xhr.status,
        statusText: xhr.statusText,
        responseText: xhr.responseText,
        error: error
    });
    
    let errorMessage = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
    
    if (xhr.status === 500) {
        errorMessage = 'เกิดข้อผิดพลาดภายในเซิร์ฟเวอร์ กรุณาตรวจสอบ log';
        
        // Try to parse error response
        try {
            const response = JSON.parse(xhr.responseText);
            if (response.message) {
                errorMessage = response.message;
            }
        } catch (e) {
            console.error('Cannot parse error response:', xhr.responseText);
        }
    } else if (xhr.status === 404) {
        errorMessage = 'ไม่พบ API endpoint';
    } else if (xhr.status === 403) {
        errorMessage = 'ไม่มีสิทธิ์เข้าถึง';
    } else if (xhr.status === 0) {
        errorMessage = 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้';
    }
    
    alert(`${errorContext}: ${errorMessage}`);
}

function loadClassroomsForType(isExternal = false) {
    // Always load internal classrooms only
    $.ajax({
        url: `api/schedule_api.php?action=get_classrooms&is_external=0`,
        type: "GET",
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                const classrooms = response.data;
                
                // Update filter dropdown
                let filterOptions = '<option value="">ทั้งหมด</option>';
                classrooms.forEach(function(classroom) {
                    filterOptions += `<option value="${classroom.classroom_id}">${classroom.room_number} ${classroom.building ? '(' + classroom.building + ')' : ''}</option>`;
                });
                
                // Update modal dropdown
                let modalOptions = '<option value="">-- เลือกห้องเรียน --</option>';
                classrooms.forEach(function(classroom) {
                    modalOptions += `<option value="${classroom.classroom_id}">${classroom.room_number} ${classroom.building ? '(' + classroom.building + ')' : ''}</option>`;
                });
                $("#classroom_id").html(modalOptions);
            }
        },
        error: function(xhr, status, error) {
            handleAjaxError(xhr, status, error, 'loadClassroomsForType');
        }
    });
}

// Load Year Levels for specific type (internal/external) - SIMPLIFY
// Load Year Levels for Internal/External
function loadYearLevelsForType(isExternal = false, selectedYearLevelId = null) {
    $.ajax({
        url: `api/schedule_api.php?action=get_year_levels&is_external=0`,
        type: "GET",
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                const yearLevels = response.data;
                let modalOptions = '<option value="">-- เลือกชั้นปี --</option>';
                yearLevels.forEach(function(yearLevel) {
                    const description = `${yearLevel.department} ${yearLevel.class_year} ${yearLevel.curriculum}`;
                    modalOptions += `<option value="${yearLevel.year_level_id}">${description}</option>`;
                });
                $("#year_level_id").html(modalOptions);
                if (selectedYearLevelId) {
                    $("#year_level_id").val(String(selectedYearLevelId));
                }
            }
        }
    });
}

// Reset form - SIMPLIFY
function resetScheduleForm() {
    $("#schedule_id").val("");
    $("#is_module_subject").prop("checked", false); 
    $("#moduleGroupYearLevels").html("");
    $("#group_id").val("");
    $("#year_level_id").val("");
    loadYearLevelsForType(false);  
    $("#yearLevelSection").show();
    $("#moduleGroupSection").hide(); 
    const currentAcademicYear = academicYearsData.find(year => year.is_current == 1);
    if (currentAcademicYear) {
        $("#academic_year_id").val(currentAcademicYear.academic_year_id);
    } else {
        $("#academic_year_id").val("");
    }
    
    if (userType === 'teacher') {
        $("#user_id").val(currentUserId).prop('disabled', true);
    } else if (userType === 'admin') {
        $("#user_id").val(currentUserId).prop('disabled', false);
    }
    
    // Reset subject fields
    $("#subject_search").val("");
    $("#subject_id").val("");
    hideSubjectSuggestions();
    $("#addSubjectForm").removeClass("show");
    
    // Reset co-teachers
    $("#co_user_id").val("");
    $("#co_user_id_2").val("");
    $("#coTeachersSection").hide();
    
    $("#day_of_week").val("");
    $("#classroom_id").val("");
    $("#start_time_slot_id").val("");
    $("#end_time_slot_id").val("");
    currentScheduleId = 0;
}

$(document).ready(function() {
    loadAcademicYears();
    loadTeachers();
    loadSubjects();
    loadClassrooms();
    loadYearLevels();
    loadTimeSlots();
    
    // ซ่อนตัวกรองอาจารย์สำหรับ teacher
    if (userType === 'teacher') {
        $("#teacherFilter").closest('.col-md-2').hide();
        $("#academicYearFilter").closest('.col-md-2').removeClass('col-md-2').addClass('col-md-3');
        $("#yearLevelFilter").closest('.col-md-2').removeClass('col-md-2').addClass('col-md-3');
        $("#classroomFilter").closest('.col-md-2').removeClass('col-md-2').addClass('col-md-3');
        $("#classroomFilter").closest('.col-md-3').siblings('.col-md-4').removeClass('col-md-4').addClass('col-md-3');
    }
    
    // Event Listeners
    $("#btnReset").click(function() {
        $("#academicYearFilter").val("");
        if (userType === 'admin') {
            $("#teacherFilter").val("");
        }
        $("#yearLevelFilter").val("");
        $("#classroomFilter").val("");
        loadSchedule();
    });
    $("#teacherFilter").on('change', function() {
        $("#yearLevelFilter").val("");
        $("#classroomFilter").val("");
        loadSchedule();
    });
    $("#yearLevelFilter").on('change', function() {
        $("#teacherFilter").val("");
        $("#classroomFilter").val("");
        loadSchedule();
    });
    $("#classroomFilter").on('change', function() {
        $("#teacherFilter").val("");
        $("#yearLevelFilter").val("");
        loadSchedule();
    });
    // Auto filter
    if (userType === 'admin') {
        $("#academicYearFilter, #teacherFilter, #yearLevelFilter, #classroomFilter").on('change', function() {
            loadSchedule();
        });
    } else {
        $("#academicYearFilter, #yearLevelFilter, #classroomFilter").on('change', function() {
            loadSchedule();
        });
    }
    
    $("#btnAddSchedule").click(function() {
        resetScheduleForm();
        $("#scheduleModalLabel").text("เพิ่มตารางสอน");
        $("#scheduleModal").modal("show");
    });
    
    $("#start_time_slot_id").change(function() {
        updateEndTimeSlots();
    });
    
    $("#btnSaveSchedule").click(function() {
        saveSchedule();
    });
    
    $("#btnConfirmDelete").click(function() {
        performDeleteSchedule(deleteScheduleId);
    });

$("#is_module_subject").change(function() {
    if ($(this).is(":checked")) {
        $("#yearLevelSection").hide();
        $("#moduleGroupSection").show();
        loadModuleGroups();
    } else {
        $("#yearLevelSection").show();
        $("#moduleGroupSection").hide();
    }
});

$("#group_id").on("change", function() {
    const groupId = $(this).val();
    selectedModuleYearLevels = [];
    $("#moduleGroupYearLevels").html("");
    if (!groupId) {
        $("#year_level_id").html('<option value="">-- เลือกชั้นปี --</option>');
        return;
    }
    $.ajax({
        url: "api/schedule_api.php?action=get_year_levels_by_module_group&group_id=" + groupId,
        type: "GET",
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                let options = '<option value="">-- เลือกชั้นปี --</option>';
                let yearLevelNames = [];
                response.data.forEach(function(yearLevel) {
                    const name = `${yearLevel.department} ${yearLevel.class_year} ${yearLevel.curriculum}`;
                    options += `<option value="${yearLevel.year_level_id}">${name}</option>`;
                    yearLevelNames.push(name);
                });
                $("#year_level_id").html(options);
                if (yearLevelNames.length > 0) {
                    $("#moduleGroupYearLevels").html(
                        `<strong>ชั้นปีในกลุ่มนี้:</strong> <br> ${yearLevelNames.join("<br>")}`
                    );
                } else {
                    $("#moduleGroupYearLevels").html(`<span class="text-danger">กลุ่มนี้ยังไม่มีชั้นปี</span>`);
                }
                if (response.data.length > 0) {
                    $("#year_level_id").val(response.data[0].year_level_id);
                }
                selectedModuleYearLevels = response.data.map(item => item.year_level_id);
            }
        }
    });
});
    // === Subject Search Event Listeners ===
    $("#subject_search").on('input', function() {
        const query = $(this).val().trim();
        if (query.length >= 1) {
            searchSubjects(query);
        } else {
            hideSubjectSuggestions();
        }
    });

    $("#subject_search").on('focus', function() {
        const query = $(this).val().trim();
        if (query.length >= 1) {
            searchSubjects(query);
        }
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.subject-search-container').length) {
            hideSubjectSuggestions();
        }
    });

    // Toggle Add Subject Form
    $(".btn-toggle-add-subject").click(function() {
        $("#addSubjectForm").toggleClass("show");
        if ($("#addSubjectForm").hasClass("show")) {
            $("#new_subject_code").focus();
        }
    });
    $("#co_user_id, #co_user_id_2").on('change', function() {
        const coTeacherValidation = validateCoTeachers();
        if (!coTeacherValidation.valid) {
            $(this).val("");
            alert(coTeacherValidation.message);
        }
    });
    // Save New Subject
    $("#btnSaveNewSubject").click(function() {
        saveNewSubject();
    });
});

// === Subject Search Functions ===
function searchSubjects(query) {
    const suggestions = subjectsData.filter(subject => {
        return subject.subject_code.toLowerCase().includes(query.toLowerCase()) ||
               subject.subject_name.toLowerCase().includes(query.toLowerCase());
    });

    displaySubjectSuggestions(suggestions);
}

function displaySubjectSuggestions(suggestions) {
    const container = $("#subject_suggestions");
    container.empty();

    if (suggestions.length === 0) {
        container.html('<div class="subject-suggestion-item text-muted">ไม่พบวิชาที่ค้นหา</div>');
    } else {
        suggestions.forEach(subject => {
            const item = $(`
                <div class="subject-suggestion-item" data-id="${subject.subject_id}" 
                     data-code="${subject.subject_code}" data-name="${subject.subject_name}" 
                     data-type="${subject.subject_type}">
                    <div class="suggestion-code">${subject.subject_code}</div>
                    <div class="suggestion-name">${subject.subject_name}</div>
                    <span class="suggestion-type">${subject.subject_type}</span>
                </div>
            `);
            
            item.click(function() {
                selectSubject(subject);
            });
            
            container.append(item);
        });
    }

    container.show();
}

// เพิ่มฟังก์ชันใหม่ในส่วน JavaScript
function handleSubjectTypeChange() {
    const selectedSubjectId = $("#subject_id").val();
    if (!selectedSubjectId) {
        $("#coTeachersSection").hide();
        return;
    }
    
    const selectedSubject = subjectsData.find(s => s.subject_id == selectedSubjectId);
    if (selectedSubject && selectedSubject.subject_type === 'ปฏิบัติ') {
        $("#coTeachersSection").show();
        loadCoTeachers();
    } else {
        $("#coTeachersSection").hide();
        $("#co_user_id").val("");
        $("#co_user_id_2").val("");
    }
}

function loadCoTeachers() {
    // ใช้ข้อมูล teachers ที่โหลดไว้แล้ว
    let options = '<option value="">-- เลือกอาจารย์ร่วม --</option>';
    teachersData.forEach(function(teacher) {
        const optionClass = teacher.is_current_user ? 'current-user-option' : '';
        options += `<option value="${teacher.user_id}" class="${optionClass}">${teacher.fullname}</option>`;
    });
    
    $("#co_user_id").html(options);
    $("#co_user_id_2").html(options);
}

function validateCoTeachers() {
    const mainTeacherId = $("#user_id").val();
    const coTeacher1Id = $("#co_user_id").val();
    const coTeacher2Id = $("#co_user_id_2").val();
    
    // ตรวจสอบไม่ให้เลือกอาจารย์คนเดียวกัน
    const teacherIds = [mainTeacherId, coTeacher1Id, coTeacher2Id].filter(id => id && id !== "");
    const uniqueTeacherIds = [...new Set(teacherIds)];
    
    if (teacherIds.length !== uniqueTeacherIds.length) {
        return {
            valid: false,
            message: "ไม่สามารถเลือกอาจารย์คนเดียวกันได้"
        };
    }
    
    return { valid: true };
}

//selectSubject
function selectSubject(subject) {
    $("#subject_search").val(`${subject.subject_code} - ${subject.subject_name} (${subject.subject_type})`);
    $("#subject_id").val(subject.subject_id);
    hideSubjectSuggestions();
    
    // เรียกฟังก์ชันจัดการประเภทวิชา
    handleSubjectTypeChange();
}

function hideSubjectSuggestions() {
    $("#subject_suggestions").hide();
}

// === Save New Subject Function ===
function saveNewSubject() {
    const subjectCode = $("#new_subject_code").val().trim();
    const subjectName = $("#new_subject_name").val().trim();
    const subjectType = $("#new_subject_type").val();

    if (!subjectCode) {
        alert("กรุณากรอกรหัสวิชา");
        $("#new_subject_code").focus();
        return;
    }

    if (!subjectName) {
        alert("กรุณากรอกชื่อวิชา");
        $("#new_subject_name").focus();
        return;
    }

    // Check for duplicate subject code
    const existingSubject = subjectsData.find(s => 
        s.subject_code.toLowerCase() === subjectCode.toLowerCase() && 
        s.subject_type === subjectType
    );

    if (existingSubject) {
        alert(`รหัสวิชา ${subjectCode} (${subjectType}) มีอยู่ในระบบแล้ว`);
        return;
    }

    const newSubjectData = {
        subject_code: subjectCode,
        subject_name: subjectName,
        subject_type: subjectType,
        credits: 3 // Default credits
    };
    $.ajax({
        url: "api/schedule_api.php?action=add_subject",
        type: "POST",
        dataType: "json",
        contentType: "application/json",
        data: JSON.stringify(newSubjectData),
        beforeSend: function() {
            $("#btnSaveNewSubject").prop("disabled", true).html(
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> กำลังบันทึก...'
            );
        },
        success: function(response) {
            $("#btnSaveNewSubject").prop("disabled", false).html('<i class="fas fa-save"></i> บันทึกวิชาใหม่');
            if (response.status === "success") {
                // Add to local subjects data
                const newSubject = {
                    subject_id: response.subject_id,
                    subject_code: subjectCode,
                    subject_name: subjectName,
                    subject_type: subjectType,
                    credits: 3
                };
                
                subjectsData.push(newSubject);
                
                // Select the new subject
                selectSubject(newSubject);
                
                // Hide the add form
                $("#addSubjectForm").removeClass("show");
                
                // Clear form
                $("#new_subject_code").val("");
                $("#new_subject_name").val("");
                $("#new_subject_type").val("ทฤษฎี");
                
                alert("เพิ่มวิชาใหม่สำเร็จ");
            } else {
                alert("เกิดข้อผิดพลาด: " + response.message);
            }
        },
        error: function(xhr, status, error) {
            $("#btnSaveNewSubject").prop("disabled", false).html('<i class="fas fa-save"></i> บันทึกวิชาใหม่');
            handleAjaxError(xhr, status, error, 'saveNewSubject');
        }
    });
}

// Load Academic Years
function loadAcademicYears() {
    $.ajax({
        url: "api/schedule_api.php?action=get_academic_years",
        type: "GET",
        dataType: "json",
        timeout: 15000,
        success: function(response) {
            if (response.status === "success") {
                academicYearsData = response.data;
                
                let options = '<option value="">ทั้งหมด</option>';
                academicYearsData.forEach(function(academicYear) {
                    const isCurrent = academicYear.is_current == 1 ? ' (ปัจจุบัน)' : '';
                    options += `<option value="${academicYear.academic_year_id}">
                        ${academicYear.academic_year} ภาคเรียนที่ ${academicYear.semester}${isCurrent}
                    </option>`;
                });
                $("#academicYearFilter").html(options);
                
                options = '<option value="">-- เลือกปีการศึกษา --</option>';
                academicYearsData.forEach(function(academicYear) {
                    const isCurrent = academicYear.is_current == 1 ? ' (ปัจจุบัน)' : '';
                    options += `<option value="${academicYear.academic_year_id}">
                        ${academicYear.academic_year} ภาคเรียนที่ ${academicYear.semester}${isCurrent}
                    </option>`;
                });
                $("#academic_year_id").html(options);
                
                const currentAcademicYear = academicYearsData.find(year => year.is_current == 1);
                if (currentAcademicYear) {
                    $("#academicYearFilter").val(currentAcademicYear.academic_year_id);
                }
                // เรียก loadSchedule เฉพาะสำหรับ teacher (admin จะเรียกใน loadTeachers)
                if (userType === 'teacher') {
                    loadSchedule();
                }
            } else {
                console.error("API Error:", response.message);
                if (response.message && response.message.includes('กรุณาเข้าสู่ระบบ')) {
                    window.location.href = '../login.php';
                } else {
                    alert("เกิดข้อผิดพลาดในการโหลดข้อมูลปีการศึกษา: " + response.message);
                }
            }
        },
        error: function(xhr, status, error) {
            handleAjaxError(xhr, status, error, 'loadAcademicYears');
        }
    });
}

// Load Teachers
function loadTeachers() {
    // เพิ่ม auth parameters เพื่อให้ API รู้ว่าใครเป็น current user
    let url = `api/schedule_api.php?action=get_teachers&auth_user_id=${currentUserId}&auth_user_type=${userType}`;
    
    $.ajax({
        url: url,
        type: "GET",
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                teachersData = response.data || [];
                
                // เรียงอาจารย์ตาม fullname (รองรับภาษาไทย)
                teachersData.sort((a, b) => {
                    const nameA = (a.fullname || '').trim();
                    const nameB = (b.fullname || '').trim();
                    return nameA.localeCompare(nameB, 'th', { sensitivity: 'base' });
                });
                
                if (userType === 'admin') {
                    let options = '<option value="">ทั้งหมด</option>';
                    teachersData.forEach(function(teacher) {
                        // เพิ่ม class พิเศษสำหรับ current user
                        const optionClass = teacher.is_current_user ? 'current-user-option' : '';
                        options += `<option value="${teacher.user_id}" class="${optionClass}">${teacher.fullname}</option>`;
                    });
                    $("#teacherFilter").html(options);
                    
                    // ตั้งค่าเริ่มต้นให้เป็น current user สำหรับ admin
                    $("#teacherFilter").val(currentUserId);
                }
                
                let modalOptions = '<option value="">-- เลือกอาจารย์ --</option>';
                teachersData.forEach(function(teacher) {
                    const optionClass = teacher.is_current_user ? 'current-user-option' : '';
                    modalOptions += `<option value="${teacher.user_id}" class="${optionClass}">${teacher.fullname}</option>`;
                });
                $("#user_id").html(modalOptions);

                if (userType === 'admin') {
                    loadSchedule();
                }
            } else if (response.message && response.message.includes('กรุณาเข้าสู่ระบบ')) {
                window.location.href = '../login.php';
            }
        },
        error: function(xhr, status, error) {
            handleAjaxError(xhr, status, error, 'loadTeachers');
        }
    });
}

// Load Subjects
function loadSubjects() {
    $.ajax({
        url: "api/schedule_api.php?action=get_subjects",
        type: "GET",
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                subjectsData = response.data;
            }
        },
        error: function(xhr, status, error) {
            handleAjaxError(xhr, status, error, 'loadSubjects');
        }
    });
}

// Load Classrooms
function loadClassrooms() {
    $.ajax({
        url: "api/schedule_api.php?action=get_classrooms",
        type: "GET",
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                classroomsData = response.data;
                
                let options = '<option value="">ทั้งหมด</option>';
                classroomsData.forEach(function(classroom) {
                    const optionClass = classroom.is_external ? 'external-option' : 'internal-option';
                    options += `<option value="${classroom.classroom_id}" class="${optionClass}">${classroom.room_number} ${classroom.building ? '(' + classroom.building + ')' : ''}</option>`;
                });
                $("#classroomFilter").html(options);
                
                loadClassroomsForType(false);
            }
        },
        error: function(xhr, status, error) {
            handleAjaxError(xhr, status, error, 'loadClassrooms');
        }
    });
}

// Load Year Levels
function loadYearLevels() {
    $.ajax({
        url: "api/schedule_api.php?action=get_year_levels",
        type: "GET",
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                yearLevelsData = response.data;
                
                let options = '<option value="">ทั้งหมด</option>';
                yearLevelsData.forEach(function(yearLevel) {
                    const description = `${yearLevel.department} ${yearLevel.class_year} ${yearLevel.curriculum}`;
                    const optionClass = yearLevel.is_external ? 'external-option' : 'internal-option';
                    options += `<option value="${yearLevel.year_level_id}" class="${optionClass}">${description}</option>`;
                });
                $("#yearLevelFilter").html(options);

                loadYearLevelsForType(false);
            }
        },
        error: function(xhr, status, error) {
            handleAjaxError(xhr, status, error, 'loadYearLevels');
        }
    });
}

// Load Time Slots
function loadTimeSlots() {
    $.ajax({
        url: "api/schedule_api.php?action=get_time_slots",
        type: "GET",
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                timeSlotsData = response.data;

                createScheduleGrid();

                let optionsStart = '<option value="">-- เลือกคาบเริ่มต้น --</option>';
                let optionsEnd = '<option value="">-- เลือกคาบสิ้นสุด --</option>';
                timeSlotsData.forEach(function(timeSlot) {
                    optionsStart += `<option value="${timeSlot.time_slot_id}">คาบ ${timeSlot.slot_number} (${timeSlot.start_time.substring(0,5)})</option>`;
                    optionsEnd += `<option value="${timeSlot.time_slot_id}">คาบ ${timeSlot.slot_number} (${timeSlot.start_time.substring(0,5)})</option>`;
                });
                $("#start_time_slot_id").html(optionsStart);
                $("#end_time_slot_id").html(optionsEnd);
            }
        },
        error: function(xhr, status, error) {
            handleAjaxError(xhr, status, error, 'loadTimeSlots');
        }
    });
}

function createScheduleGrid() {
    const scheduleGrid = document.getElementById('scheduleGrid');
    if (!scheduleGrid || !timeSlotsData.length) return;
    
    scheduleGrid.innerHTML = '';
    
    const headerCell = document.createElement('div');
    headerCell.className = 'grid-cell grid-header';
    scheduleGrid.appendChild(headerCell);
    
    for (let i = 1; i <= timeSlotsData.length; i++) {
        const slotHeader = document.createElement('div');
        slotHeader.className = 'grid-cell grid-header';
        slotHeader.textContent = i;
        scheduleGrid.appendChild(slotHeader);
    }
    
    const timeHeader = document.createElement('div');
    timeHeader.className = 'grid-cell time-slot-header';
    timeHeader.textContent = 'ช่วงเวลา';
    scheduleGrid.appendChild(timeHeader);
    
    timeSlotsData.forEach(timeSlot => {
        const timeCell = document.createElement('div');
        timeCell.className = 'grid-cell time-slot-header';
        timeCell.textContent = `${timeSlot.start_time.substring(0, 5)}-${timeSlot.end_time.substring(0, 5)}`;
        scheduleGrid.appendChild(timeCell);
    });
    
    daysOfWeek.forEach(day => {
        const dayCell = document.createElement('div');
        dayCell.className = 'grid-cell day-cell';
        dayCell.textContent = day.name;
        scheduleGrid.appendChild(dayCell);
        
        for (let i = 1; i <= timeSlotsData.length; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.className = 'grid-cell empty-cell';
            emptyCell.dataset.day = day.code;
            emptyCell.dataset.slot = i;
            scheduleGrid.appendChild(emptyCell);
        }
    });
    
}

// Update End Time Slots
function updateEndTimeSlots() {
    const startSlotId = parseInt($("#start_time_slot_id").val());
    if (!startSlotId) {
        $("#end_time_slot_id").html('<option value="">-- เลือกคาบสิ้นสุด --</option>');
        return;
    }
    const startSlotIndex = timeSlotsData.findIndex(slot => parseInt(slot.time_slot_id) === startSlotId);
    if (startSlotIndex === -1) {
        $("#end_time_slot_id").html('<option value="">-- เลือกคาบสิ้นสุด --</option>');
        return;
    }
    let options = '<option value="">-- เลือกคาบสิ้นสุด --</option>';
    for (let i = startSlotIndex; i < timeSlotsData.length; i++) {
        const timeSlot = timeSlotsData[i];
        options += `<option value="${timeSlot.time_slot_id}">คาบ ${timeSlot.slot_number} (${timeSlot.start_time.substring(0,5)})</option>`;
    }
    $("#end_time_slot_id").html(options);
}

// Load Schedule
function loadSchedule() {
    const academicYearId = $("#academicYearFilter").val();
    const yearLevelId = $("#yearLevelFilter").val();
    const classroomId = $("#classroomFilter").val();
    let teacherId = "";
    
    const hasYearLevelFilter = yearLevelId && yearLevelId !== "";
    const hasClassroomFilter = classroomId && classroomId !== "";
    
    if (hasYearLevelFilter || hasClassroomFilter) {
        teacherId = "";
    } else {
        if (userType === 'admin') {
            teacherId = $("#teacherFilter").val();
        } else if (userType === 'teacher') {
            teacherId = currentUserId;
        }
    }
    
    let url = `api/schedule_api.php?action=get_schedule`;
    url += `&auth_user_id=${currentUserId}&auth_user_type=${userType}`;
    
    if (hasYearLevelFilter || hasClassroomFilter) {
        url += `&show_all_for_filter=1`;
    }
    
    if (academicYearId) {
        url += `&academic_year_id=${academicYearId}`;
    }
    
    if (teacherId) {
        url += `&user_id=${teacherId}`;
    }

    $.ajax({
        url: url,
        type: "GET",
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                scheduleData = response.data;
                
                let filteredData = scheduleData;
                
                if (hasYearLevelFilter) {
    // กรองรายวิชาปกติที่ year_level_id ตรงกัน
    filteredData = filteredData.filter(item => {
        // กรณีวิชาโมดูล: ตรวจสอบว่ากลุ่มโมดูลนี้มี year_level_id ที่เลือก
        if (item.is_module_subject == 1 && item.group_id) {
            // ใช้ฟังก์ชัน getYearLevelsByModuleGroup ผ่าน AJAX แบบ sync (หรือ cache ไว้ล่วงหน้า)
            // สมมติว่ามี cache: moduleGroupYearLevelsMap[group_id] = [year_level_id,...]
            if (moduleGroupYearLevelsMap[item.group_id] && moduleGroupYearLevelsMap[item.group_id].includes(yearLevelId)) {
                return true;
            }
        }
        // วิชาปกติ
        return item.year_level_id === yearLevelId;
    });
}
                
                if (hasClassroomFilter) {
                    filteredData = filteredData.filter(item => item.classroom_id === classroomId);
                }
                
                scheduleData = filteredData;
                renderSchedule();
                loadModuleGroups(null, renderSchedule);
            } else {
                console.error("Error loading schedule:", response.message);
                if (response.message && response.message.includes('กรุณาเข้าสู่ระบบ')) {
                    window.location.href = '../login.php';
                } else {
                    alert("เกิดข้อผิดพลาดในการโหลดข้อมูล: " + response.message);
                }
            }
        },
        error: function(xhr, status, error) {
            handleAjaxError(xhr, status, error, 'loadSchedule');
        }
    });
}
function renderSchedule() {
    $(".empty-cell").empty();
    $(".empty-cell").removeClass("theory-class lab-class external-class");
    $(".empty-cell").css("display", "");
    $(".empty-cell").css("grid-column", "");
    
    if (!scheduleData || scheduleData.length === 0) {
        return;
    }
    
    scheduleData.forEach(function(schedule) {
        const dayOfWeek = schedule.day_of_week;
        const startSlot = parseInt(schedule.start_time_slot_id);
        const endSlot = parseInt(schedule.end_time_slot_id);
        
        let firstCell = $(`.empty-cell[data-day="${dayOfWeek}"][data-slot="${startSlot}"]`);
        let actionButtons = '';
        
        if (firstCell.length === 0) {
            return;
        }
        
        if (startSlot !== endSlot) {
            for (let i = startSlot + 1; i <= endSlot; i++) {
                $(`.empty-cell[data-day="${dayOfWeek}"][data-slot="${i}"]`).css("display", "none");
            }
            
            const span = endSlot - startSlot + 1;
            firstCell.css("grid-column", `span ${span}`);
        }
        
        // Determine class type and styling
        let classType = '';
        let extraClass = '';
        const isExternal = schedule.is_external_subject == 1;
        
        if (isExternal) {
            classType = 'external-class';
            extraClass = 'external-subject-item';
        } else {
            classType = schedule.subject_type === 'ทฤษฎี' ? 'theory-class' : 'lab-class';
        }
        
        firstCell.addClass(classType);
        if (extraClass) {
            firstCell.addClass(extraClass);
        }
        
        const yearLevelId = $("#yearLevelFilter").val();
        const classroomId = $("#classroomFilter").val();
        const showTeacherName = (yearLevelId && yearLevelId !== "") || (classroomId && classroomId !== "");
        
        // สิทธิ์ในการแก้ไข/ลบ
        const canEdit = (userType === 'admin') || 
                       (userType === 'teacher' && (
                           schedule.user_id == currentUserId || 
                           schedule.co_user_id == currentUserId || 
                           schedule.co_user_id_2 == currentUserId
                       ));
        
        if (canEdit) {
            actionButtons = `
                <div class="mt-2">
                    <button class="btn btn-sm btn-outline-primary btn-edit" data-id="${schedule.schedule_id}" title="แก้ไข">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger btn-delete" data-id="${schedule.schedule_id}" title="ลบ">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
        }
        
        // Build content with teacher information
        let subjectInfo = `<div><strong>${schedule.subject_code}</strong>`;
        if (isExternal) {
            subjectInfo += `<span class="external-badge">นอกสาขา</span>`;
        }
        if (schedule.teacher_count > 1) {
            subjectInfo += `<span class="badge bg-info text-white ms-1">${schedule.teacher_count} อาจารย์</span>`;
        }
        subjectInfo += `</div>`;
        
        // แสดงชื่ออาจารย์
        let teacherInfo = '';
        if (showTeacherName || schedule.teacher_count > 1) {
            if (schedule.teacher_count === 1) {
                teacherInfo = `<div class="text-primary"><small><i class="fas fa-user"></i> ${schedule.teacher_name}</small></div>`;
            } else {
                teacherInfo = `<div class="text-primary"><small><i class="fas fa-users"></i> ${schedule.all_teachers_display}</small></div>`;
            }
        }
        
        let roomInfo = '';
        let yearInfo = '';

        if (!isExternal) {
            roomInfo = schedule.room_number ? `<div>${schedule.room_number}</div>` : '';
            // ถ้าเป็นวิชาโมดูล ให้แสดงชื่อกลุ่มโมดูลแทน year_description
            if (schedule.is_module_subject == 1 && schedule.group_id && moduleGroupsMap[schedule.group_id]) {
                yearInfo = `<div class="text-success">${moduleGroupsMap[schedule.group_id]}</div>`;
            } else {
                yearInfo = schedule.year_description ? `<div>${schedule.year_description}</div>` : '';
            }
        } else {
            roomInfo = '<div class="text-muted">-</div>';
            yearInfo = '<div class="text-muted">-</div>';
        }
        const content = `
            <div class="schedule-item" data-id="${schedule.schedule_id}">
                ${subjectInfo}
                <div>${schedule.subject_name}</div>
                ${teacherInfo}
                ${roomInfo}
                ${yearInfo}
                ${actionButtons}
            </div>
        `;
        
        firstCell.html(content);
    });
    
    $(".btn-edit").click(function() {
        const scheduleId = $(this).data("id");
        editSchedule(scheduleId);
    });
    
    $(".btn-delete").click(function() {
        deleteScheduleId = $(this).data("id");
        $("#deleteConfirmModal").modal("show");
    });
}


//editSchedule เพื่อรองรับอาจารย์ร่วม
function editSchedule(scheduleId) {
    const schedule = scheduleData.find(s => s.schedule_id == scheduleId);
    
    if (!schedule) {
        alert("ไม่พบข้อมูลตารางสอนที่ต้องการแก้ไข");
        return;
    }
        console.log("แก้ไขตารางสอน schedule_id:", scheduleId);
    console.log("is_module_subject:", schedule.is_module_subject);
    console.log("group_id:", schedule.group_id);

    resetScheduleForm();
    $("#scheduleModalLabel").text("แก้ไขตารางสอน");
    $("#is_module_subject").prop("checked", schedule.is_module_subject == 0 ? false : true);
    if (schedule.is_module_subject == 1) {
        $("#yearLevelSection").hide();
        $("#moduleGroupSection").show();
        loadModuleGroups(schedule.group_id ? schedule.group_id : null, function() {
            if (schedule.group_id) {
                $("#group_id").val(String(schedule.group_id)).trigger("change");
            } else {
                $("#group_id").val("").trigger("change");
            }
            if (schedule.year_level_id) {
                setTimeout(function() {
                    $("#year_level_id").val(String(schedule.year_level_id));
                }, 300);
            }
        });
    } else {
        $("#yearLevelSection").show();
        $("#moduleGroupSection").hide();
        loadYearLevelsForType(false);
        setTimeout(function() {
            $("#year_level_id").val(String(schedule.year_level_id));
        }, 300);
    }

    $("#schedule_id").val(schedule.schedule_id);
    $("#academic_year_id").val(schedule.academic_year_id);
    $("#user_id").val(schedule.user_id);
    $("#day_of_week").val(schedule.day_of_week);
    $("#start_time_slot_id").val(schedule.start_time_slot_id);
    

    $("#subject_id").val(schedule.subject_id);
    $("#subject_search").val(`${schedule.subject_code} - ${schedule.subject_name} (${schedule.subject_type})`);
    handleSubjectTypeChange();
    

    if (schedule.co_user_id) {
        $("#co_user_id").val(schedule.co_user_id);
    }
    if (schedule.co_user_id_2) {
        $("#co_user_id_2").val(schedule.co_user_id_2);
    }
    
    const isExternal = schedule.is_external_subject == 1;
    
    if (!isExternal) {
        if (schedule.classroom_id) {
            $("#classroom_id").val(schedule.classroom_id);
        }
        if (schedule.year_level_id) {
            $("#year_level_id").val(schedule.year_level_id);
        }
    } else {
        $("#classroom_id").val("");
        $("#year_level_id").val("");
    }
    
    updateEndTimeSlots();
    $("#end_time_slot_id").val(schedule.end_time_slot_id);
    
    currentScheduleId = parseInt(schedule.schedule_id);
    
    $("#scheduleModal").modal("show");
}


function deleteSchedule(scheduleId) {
    
    if (!scheduleId || scheduleId <= 0) {
        alert("รหัสตารางสอนไม่ถูกต้อง");
        return;
    }
    
    const schedule = scheduleData.find(s => s.schedule_id == scheduleId);
    let scheduleInfo = "";
    
    if (schedule) {
        scheduleInfo = `\n\nรายละเอียด: ${schedule.subject_code} - ${schedule.subject_name}\nวัน: ${schedule.day_of_week}\nเวลา: ${schedule.start_time} - ${schedule.end_time}`;
    }
    

    if (confirm(`คุณต้องการลบตารางสอนนี้ใช่หรือไม่?${scheduleInfo}\n\nหมายเหตุ: การลบนี้ไม่สามารถเรียกคืนได้`)) {
        performDeleteSchedule(scheduleId);
    }
}


function performDeleteSchedule(scheduleId) {
    let url = `api/schedule_api.php?action=delete_schedule&schedule_id=${scheduleId}`;
    url += `&auth_user_id=${currentUserId}&auth_user_type=${userType}`;

    $.ajax({
        url: url,
        type: "DELETE",
        dataType: "json",
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        },
        success: function(response) {
            if (response.status === "success") {
                alert(response.message || "ลบตารางสอนสำเร็จ");
                
                // Close any open modals
                $("#deleteConfirmModal").modal("hide");

                loadSchedule();
            } else {
                if (response.message && response.message.includes('กรุณาเข้าสู่ระบบ')) {
                    alert('Session หมดอายุ กรุณาเข้าสู่ระบบใหม่');
                    window.location.href = 'login.php';
                } else {
                    alert(response.message || "เกิดข้อผิดพลาดในการลบข้อมูล");
                }
            }
        },
        error: function(xhr, status, error) {
            console.error("❌ Delete error:", error);
            console.error("Response:", xhr.responseText);
            
            if (xhr.status === 500) {
                alert('เกิดข้อผิดพลาดในเซิร์ฟเวอร์ กรุณาลองใหม่อีกครั้ง');
            } else {
                handleAjaxError(xhr, status, error, 'deleteSchedule');
            }
        }
    });
}

// Update the Save Schedule function to handle both add and edit
function saveSchedule() {
    const dayOfWeek = $("#day_of_week").val();
    const isModuleSubject = $("#is_module_subject").is(":checked") ? 1 : 0;
    const yearLevelId = $("#year_level_id").val();
    const groupId = $("#group_id").val();

    console.log("year_level_id ที่จะส่ง:", yearLevelId);
    console.log("group_id ที่จะส่ง:", groupId);
    console.log("is_module_subject:", isModuleSubject);
    // Validation พื้นฐาน
    const requiredFields = [
        { field: "academic_year_id", message: "กรุณาเลือกปีการศึกษา" },
        { field: "user_id", message: "กรุณาเลือกอาจารย์หลัก" },
        { field: "subject_id", message: "กรุณาเลือกวิชา" },
        { field: "day_of_week", message: "กรุณาเลือกวัน", value: dayOfWeek },
        { field: "start_time_slot_id", message: "กรุณาเลือกคาบเริ่มต้น" },
        { field: "end_time_slot_id", message: "กรุณาเลือกคาบสิ้นสุด" }
    ];
    
    for (const req of requiredFields) {
        const value = req.value || $("#" + req.field).val();
        if (!value) {
            alert(req.message);
            $("#" + req.field).focus();
            return;
        }
    }
    if (isModuleSubject) {
        if (!groupId) {
            alert("กรุณาเลือกกลุ่มโมดูล");
            $("#group_id").focus();
            return;
        }
    }


    // ตรวจสอบอาจารย์ร่วม
    const coTeacherValidation = validateCoTeachers();
    if (!coTeacherValidation.valid) {
        alert(coTeacherValidation.message);
        return;
    }
    
    const classroomId = $("#classroom_id").val();
    
    if (!classroomId) {
        alert("กรุณาเลือกห้องเรียน");
        $("#classroom_id").focus();
        return;
    }
    
    const selectedUserId = $("#user_id").val();
    
    if (userType === 'teacher' && selectedUserId != currentUserId) {
        alert("คุณไม่สามารถสร้างตารางสอนให้ผู้อื่นได้");
        return;
    }
    
    const startTimeSlotId = parseInt($("#start_time_slot_id").val());
    const endTimeSlotId = parseInt($("#end_time_slot_id").val());
    
    if (endTimeSlotId < startTimeSlotId) {
        alert("คาบสิ้นสุดต้องมากกว่าหรือเท่ากับคาบเริ่มต้น");
        $("#end_time_slot_id").focus();
        return;
    }
    
    const scheduleIdValue = $("#schedule_id").val();
    const isUpdate = scheduleIdValue && scheduleIdValue !== "" && scheduleIdValue !== "0" && parseInt(scheduleIdValue) > 0;
    
    // สร้างข้อมูลที่จะส่ง
    const data = {
        academic_year_id: parseInt($("#academic_year_id").val()),
        user_id: parseInt($("#user_id").val()),
        subject_id: parseInt($("#subject_id").val()),
        classroom_id: parseInt($("#classroom_id").val()),
        day_of_week: dayOfWeek,
        start_time_slot_id: parseInt($("#start_time_slot_id").val()),
        end_time_slot_id: parseInt($("#end_time_slot_id").val()),
        is_external_subject: 0,
        is_module_subject: isModuleSubject,
        created_by: currentUserId,
        updated_by: currentUserId
    };
    if (isModuleSubject) {
        data.group_id = groupId;
    } else {
        data.year_level_id = parseInt(yearLevelId);
    }
    // เพิ่มข้อมูลอาจารย์ร่วม
    const coUserId = $("#co_user_id").val();
    const coUserId2 = $("#co_user_id_2").val();
    console.log("data ที่จะส่งไป API:", data);
    console.log("year_level_ids ที่จะส่ง:", data.year_level_ids);
    if (coUserId && coUserId !== "") {
        data.co_user_id = parseInt(coUserId);
    }
    
    if (coUserId2 && coUserId2 !== "") {
        data.co_user_id_2 = parseInt(coUserId2);
    }
    
    // คำนวณจำนวนอาจารย์
    data.current_teachers = 1; // อาจารย์หลัก
    if (coUserId && coUserId !== "") data.current_teachers++;
    if (coUserId2 && coUserId2 !== "") data.current_teachers++;
    
    // กำหนด max_teachers ตามประเภทวิชา
    const selectedSubject = subjectsData.find(s => s.subject_id == data.subject_id);
    data.max_teachers = selectedSubject && selectedSubject.subject_type === 'ปฏิบัติ' ? 3 : 1;
    
    if (isUpdate) {
        data.schedule_id = parseInt(scheduleIdValue);
    }
    
    let apiAction = isUpdate ? "update_schedule" : "add_schedule";
    let method = isUpdate ? "PUT" : "POST";
    
    let apiUrl = `api/schedule_api.php?action=${apiAction}`;
    
    if (isUpdate) {
        apiUrl += `&auth_user_id=${currentUserId}&auth_user_type=${userType}`;
    }
    const dataValidation = validateScheduleData(data);
    if (!dataValidation.valid) {
        alert("ข้อมูลไม่ถูกต้อง: " + dataValidation.message);
        return;
    }
    $.ajax({
        url: apiUrl,
        type: method,
        dataType: "json",
        contentType: "application/json; charset=utf-8",
        data: JSON.stringify(data),
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            $("#btnSaveSchedule").prop("disabled", true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> กำลังบันทึก...');

        },
        success: function(response) {
            $("#btnSaveSchedule").prop("disabled", false).html('บันทึก');
            if (response.status === "success") {
                alert(response.message || (isUpdate ? "อัปเดตตารางสอนสำเร็จ" : "เพิ่มตารางสอนสำเร็จ"));
                $("#scheduleModal").modal("hide");
                loadSchedule();
            } else {
                if (response.message && response.message.includes('กรุณาเข้าสู่ระบบ')) {
                    alert('Session หมดอายุ กรุณาเข้าสู่ระบบใหม่');
                    window.location.href = 'login.php';
                } else {
                    alert(response.message || "เกิดข้อผิดพลาดในการบันทึกข้อมูล");
                }
            }
        },
        error: function(xhr, status, error) {
            $("#btnSaveSchedule").prop("disabled", false).html('บันทึก');
            
            // เพิ่ม detailed error logging
            console.error("AJAX Error Details:", {
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText,
                error: error,
                url: apiUrl,
                data: data
            });
            
            handleAjaxError(xhr, status, error, 'saveSchedule');
        }
    });
}

// ฟังก์ชันตรวจสอบข้อมูลก่อนส่ง
function validateScheduleData(data) {
    // ตรวจสอบประเภทข้อมูล
    const integerFields = ['academic_year_id', 'user_id', 'subject_id', 'classroom_id', 'year_level_id', 'start_time_slot_id', 'end_time_slot_id'];
    
    for (const field of integerFields) {
        if (data[field] && (isNaN(data[field]) || data[field] <= 0)) {
            return {
                valid: false,
                message: `${field} ต้องเป็นตัวเลขที่มากกว่า 0`
            };
        }
    }
    
    // ตรวจสอบ day_of_week
    const validDays = ['จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.'];
    if (!validDays.includes(data.day_of_week)) {
        return {
            valid: false,
            message: `วันที่เลือกไม่ถูกต้อง: ${data.day_of_week}`
        };
    }
    
    // ตรวจสอบช่วงเวลา
    if (data.end_time_slot_id < data.start_time_slot_id) {
        return {
            valid: false,
            message: "คาบสิ้นสุดต้องมากกว่าหรือเท่ากับคาบเริ่มต้น"
        };
    }
    
    return { valid: true, message: "ข้อมูลถูกต้อง" };
}
let moduleGroupsData = [];
let moduleGroupsMap = {};
let moduleGroupYearLevelsMap = {};
function loadModuleGroups(selectedGroupId = null, callback = null) {
    $.ajax({
        url: "api/schedule_api.php?action=get_module_groups",
        type: "GET",
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                moduleGroupsData = response.data;
                moduleGroupsMap = {};
                response.data.forEach(function(group) {
                    moduleGroupsMap[group.group_id] = group.display_name;
                });
                let options = '<option value="">-- เลือกกลุ่มโมดูล --</option>';
                response.data.forEach(function(group) {
                    options += `<option value="${group.group_id}">${group.display_name}</option>`;
                });
                $("#group_id").html(options);
                if (selectedGroupId !== null && selectedGroupId !== undefined) {
                    $("#group_id").val(String(selectedGroupId)).trigger("change");
                } else {
                    $("#group_id").val(""); // reset ค่าเสมอถ้าไม่ได้แก้ไข
                }
                // === preload year levels ของแต่ละ group ===
                preloadModuleGroupYearLevels(response.data, callback);
            }
        }
    });
}
function preloadModuleGroupYearLevels(groups, callback) {
    moduleGroupYearLevelsMap = {};
    let loadedCount = 0;
    if (!groups || groups.length === 0) {
        if (typeof callback === "function") callback();
        return;
    }
    groups.forEach(function(group) {
        $.ajax({
            url: "api/schedule_api.php?action=get_year_levels_by_module_group&group_id=" + group.group_id,
            type: "GET",
            dataType: "json",
            success: function(res) {
                if (res.status === "success") {
                    moduleGroupYearLevelsMap[group.group_id] = res.data.map(yl => String(yl.year_level_id));
                }
                loadedCount++;
                if (loadedCount === groups.length && typeof callback === "function") {
                    callback();
                }
            }
        });
    });
}
    </script>
</body>
</html>