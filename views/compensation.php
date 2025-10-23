<?php
// ตรวจสอบการเข้าสู่ระบบก่อนแสดงหน้า
require_once '../api/auth_check.php';
requireLogin('../login.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userData = getUserData();
$current_user_id = $_SESSION['user_id'];

// เพิ่มฟังก์ชัน format_thai_date ไว้ที่ต้นไฟล์
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

try {
    require_once '../config/database.php';
    
    if (!isset($conn) || !$conn) {
        throw new Exception("Database connection failed");
    }

    $userData = getUserData();

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
        $academic_year_id = 0;
        $academic_year = '-';
        $semester = '-';
        $start_date = null;
        $end_date = null;
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>จัดการการชดเชยการเรียนการสอน - Teaching Schedule System</title>
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
        /* Export Button Styling */
        .btn-export-pdf {
            background: linear-gradient(135deg, #af0000ff 0%, #ff0000ff 100%);
            border: none;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .compensation-card {
            border-left: 4px solid #ffc107;
            transition: all 0.3s ease;
        }
        
        .compensation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .status-pending { 
            color: #ffc107; 
            background: rgba(255, 193, 7, 0.1);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .status-completed { 
            color: #28a745; 
            background: rgba(40, 167, 69, 0.1);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .status-cancelled { 
            color: #dc3545; 
            background: rgba(220, 53, 69, 0.1);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .status-waiting-approval {
            color: #17a2b8;
            background: rgba(23, 162, 184, 0.1);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
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

        @keyframes pendingPulse {
            0% { background-color: rgba(23, 162, 184, 0.1); }
            50% { background-color: rgba(23, 162, 184, 0.3); }
            100% { background-color: rgba(23, 162, 184, 0.1); }
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .filter-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0,123,255,0.05);
            transform: scale(1.002);
            transition: all 0.2s ease;
        }
        
        .badge-large {
            font-size: 0.9em;
            padding: 0.5em 1em;
        }
        
        .action-buttons .btn {
            margin: 2px;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .confirmation-modal .modal-content {
            border: none;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .confirmation-modal .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px 25px;
        }

        .schedule-changes {
            background: #fff3cd;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid #ffc107;
        }

        .date-change-section {
            background: #e7f3ff;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            border: 2px solid #007bff;
        }

        .date-picker-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            margin-top: 15px;
        }

        .date-cell {
            aspect-ratio: 1;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fff;
            min-height: 35px;
        }

        .date-cell:hover:not(.disabled) {
            background: #e3f2fd;
            border-color: #2196f3;
        }

        .date-cell.selected {
            background: #2196f3;
            color: white;
            border-color: #1976d2;
        }

        .date-cell.disabled {
            background: #f5f5f5;
            color: #9e9e9e;
            cursor: not-allowed;
        }

        .date-cell.holiday {
            background: #ffebee;
            color: #d32f2f;
        }

        .date-cell.weekend {
            background: #fff8e1;
            color: #f57c00;
        }

        .approval-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
        }

        .change-reasons {
            margin-top: 15px;
        }

        .change-reasons textarea {
            min-height: 80px;
        }

        .time-conflict-warning {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
        }

        .room-conflict-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
        }

        .approval-pending-indicator {
            position: relative;
            overflow: hidden;
            animation: pendingPulse 2s infinite;
        }

        .approval-pending-indicator::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shimmer 2s infinite;
        }

        .quick-date-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .quick-date-btn {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9em;
        }

        .quick-date-btn:hover {
            background: #e3f2fd;
            border-color: #2196f3;
        }

        .quick-date-btn.active {
            background: #2196f3;
            color: white;
            border-color: #1976d2;
        }

        .detail-row {
            display: flex;
            margin-bottom: 8px;
            padding: 4px 0;
        }
        
        .detail-label {
            font-weight: 600;
            min-width: 140px;
            color: #495057;
        }
        
        .detail-value {
            flex: 1;
            color: #212529;
        }
        
        .compensation-details {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background-color: #f8f9fa;
        }
        
        .compensation-details h6 {
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .stats-card {
                margin-bottom: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                margin-bottom: 5px;
                width: 100%;
            }
            
            .table-responsive {
                border: none;
            }
            
            .mobile-responsive table {
                font-size: 0.875rem;
            }
            
            .mobile-responsive th,
            .mobile-responsive td {
                padding: 0.5rem 0.25rem;
            }
            
            .detail-row {
                flex-direction: column;
            }
            
            .detail-label {
                min-width: auto;
                margin-bottom: 2px;
                font-size: 0.9em;
            }
            
            .filter-card .row {
                margin: 0;
            }
            
            .filter-card .col-md-3 {
                margin-bottom: 15px;
            }
        }

        /* Enhanced Loading States */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }

        .skeleton-row {
            height: 20px;
            margin-bottom: 10px;
            border-radius: 4px;
        }

        /* Enhanced Error States */
        .error-state {
            text-align: center;
            padding: 40px 20px;
            color: #dc3545;
        }

        .error-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Network Status Indicator */
        .network-status {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .network-status.online {
            background: #28a745;
            color: white;
        }

        .network-status.offline {
            background: #dc3545;
            color: white;
        }

        /* Enhanced Tooltips */
        .tooltip-inner {
            max-width: 300px;
            padding: 8px 12px;
            font-size: 0.875rem;
        }

        .change-indicator {
            position: relative;
            padding: 8px 12px;
            border-radius: 6px;
            background: #fff3cd;
            color: #856404;
            font-size: 0.8em;
            border: 1px solid #ffeaa7;
        }

        .change-indicator::before {
            content: "⚠️";
            margin-right: 5px;
        }

        .priority-high {
            border-left: 4px solid #dc3545;
        }

        .priority-medium {
            border-left: 4px solid #ffc107;
        }

        .priority-low {
            border-left: 4px solid #28a745;
        }

        .workflow-timeline {
            position: relative;
            padding: 20px 0;
        }

        .workflow-step {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 8px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            position: relative;
        }

        .workflow-step.active {
            background: #e3f2fd;
            border-color: #2196f3;
        }

        .workflow-step.completed {
            background: #e8f5e8;
            border-color: #28a745;
        }

        .workflow-step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            background: #fff;
            border: 2px solid #dee2e6;
        }

        .workflow-step.active .workflow-step-icon {
            background: #2196f3;
            color: white;
            border-color: #1976d2;
        }

        .workflow-step.completed .workflow-step-icon {
            background: #28a745;
            color: white;
            border-color: #1e7e34;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7em;
            font-weight: bold;
        }

        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .auto-schedule-all-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .auto-schedule-all-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }

        .table-actions {
            white-space: nowrap;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .approval-needed {
            background: linear-gradient(45deg, #17a2b8, #20c997);
            color: white;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .edit-schedule-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 15px 0;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }

        .suggested-alternative {
            background: #e8f5e8;
            border: 1px solid #28a745;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }

        .suggested-alternative h6 {
            color: #28a745;
            margin-bottom: 10px;
        }

        .conflict-warning {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }

        .conflict-warning h6 {
            color: #721c24;
            margin-bottom: 10px;
        }

        .schedule-comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }

        .schedule-comparison .schedule-item {
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .schedule-comparison .original {
            background: #fff3cd;
            border-color: #ffeaa7;
        }

        .schedule-comparison .proposed {
            background: #e8f5e8;
            border-color: #c3e6cb;
        }

        @media (max-width: 768px) {
            .schedule-comparison {
                grid-template-columns: 1fr;
            }
            
            .stats-card {
                margin-bottom: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
            
            .action-buttons .btn {
                width: 100%;
                margin: 2px 0;
            }
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

                    <!-- Header Card -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card filter-card">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h4><i class="fas fa-calendar-plus"></i> จัดการการสอนชดเชยการเรียนการสอน</h4>
                                            <p class="mb-1">ปีการศึกษา <?php echo $academic_year; ?> เทอม <?php echo $semester; ?></p>
                                            <small>ระหว่างวันที่ <?php echo format_thai_date($start_date); ?> - <?php echo format_thai_date($end_date); ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number text-secondary" id="pendingCount">0</div>
                                <div class="stats-label">รอดำเนินการ</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number text-info" id="waitingApprovalCount">0</div>
                                <div class="stats-label">รอยืนยัน</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number text-success" id="completedCount">0</div>
                                <div class="stats-label">เสร็จสิ้น</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                            <button class="btn auto-schedule-all-btn" onclick="autoScheduleAll()" title="จัดตารางชดเชยอัตโนมัติทั้งหมด">
                                <i class="fas fa-magic"></i> จัดตารางทั้งหมด
                            </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter Controls -->
                    <div class="row mb-1">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label">อาจารย์</label>
                                            <select class="form-select" id="teacherFilter" onchange="filterData()" data-current-user="<?php echo $current_user_id; ?>">
                                                <option value="">ทั้งหมด</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">สถานะ</label>
                                            <select class="form-select" id="statusFilter" onchange="filterData()">
                                                <option value="">ทั้งหมด</option>
                                                <option value="รอดำเนินการ">รอดำเนินการ</option>
                                                <option value="รอยืนยัน">รอยืนยัน</option>
                                                <option value="ดำเนินการแล้ว">ดำเนินการแล้ว</option>
                                                <option value="ยกเลิก">ยกเลิก</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">ประเภทการยกเลิก</label>
                                            <select class="form-select" id="typeFilter" onchange="filterData()">
                                                <option value="">ทั้งหมด</option>
                                                <option value="ยกเลิกรายวิชา">ยกเลิกรายวิชา</option>
                                                <option value="วันหยุดราชการ">วันหยุดราชการ</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">ค้นหารายวิชา</label>
                                            <input type="text" class="form-control" id="subjectSearch" 
                                                   placeholder="รหัสวิชา หรือชื่อวิชา" onkeyup="filterData()">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">การดำเนินการ</label>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-outline-primary btn-sm" onclick="refreshData()">
                                                    <i class="fas fa-sync-alt"></i> รีเฟรช
                                                </button>
                                                <button class="btn btn-outline-info btn-sm" onclick="showApprovalQueue()">
                                                    <i class="fas fa-clock"></i> คิวยืนยัน
                                                </button>
                                                <button class="btn btn-export-pdf btn-sm"
                                                        data-bs-toggle="tooltip" 
                                                        title="ส่งออกข้อมูล PDF">
                                                    <i class="fas fa-file-export"></i> PDF
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- เพิ่ม Auto Schedule All Options Modal -->
                    <div class="modal fade confirmation-modal" id="autoScheduleAllModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title">
                                        <i class="fas fa-magic"></i> จัดตารางชดเชยอัตโนมัติทั้งหมด
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div id="autoScheduleOptionsContent">
                                        <div class="text-center py-4">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">กำลังโหลด...</span>
                                            </div>
                                            <p class="mt-2">กำลังโหลดข้อมูลอาจารย์...</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times"></i> ยกเลิก
                                    </button>
                                    <button type="button" class="btn btn-success" id="confirmAutoScheduleAllBtn" onclick="executeAutoScheduleAll()" disabled>
                                        <i class="fas fa-magic"></i> เริ่มจัดตารางอัตโนมัติ
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Compensation List -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4>รายการการชดเชย</h4>
                                </div>
                                <div class="card-body">
                                    <div class="mobile-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>วันที่ยกเลิก</th>
                                                    <th>รายวิชา</th>
                                                    <th>อาจารย์</th>
                                                    <th>เหตุผล</th>
                                                    <th>วันที่ชดเชย</th>
                                                    <th>สถานะ</th>
                                                    <th>จัดการ</th>
                                                </tr>
                                            </thead>
                                            <tbody id="compensationTableBody">
                                                <tr>
                                                    <td colspan="7" class="text-center py-4">
                                                        <div class="spinner-border text-primary" role="status">
                                                            <span class="visually-hidden">กำลังโหลด...</span>
                                                        </div>
                                                        <p class="mt-2 text-muted">กำลังโหลดข้อมูล...</p>
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

    <!-- Compensation Detail Modal -->
    <div class="modal fade confirmation-modal" id="compensationDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-info-circle"></i> รายละเอียดการชดเชย</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="compensationDetailContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Auto Schedule Confirmation Modal -->
    <div class="modal fade confirmation-modal" id="autoScheduleConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-magic"></i> ยืนยันการจัดตารางอัตโนมัติ
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="autoScheduleConfirmContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Date Change Request Modal -->
    <div class="modal fade" id="dateChangeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-calendar-alt"></i> ขอเปลี่ยนวันที่ชดเชย
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="dateChangeContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Approval Queue Modal -->
    <div class="modal fade" id="approvalQueueModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="fas fa-clock"></i> คิวรอการยืนยัน
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="approvalQueueContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle"></i> สำเร็จ
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                        <h5 class="mt-3">ดำเนินการสำเร็จ</h5>
                        <p id="successMessage" class="text-muted"></p>
                    </div>
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
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle"></i> เกิดข้อผิดพลาด
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle text-danger" style="font-size: 4rem;"></i>
                        <h5 class="mt-3">เกิดข้อผิดพลาด</h5>
                        <p id="errorMessage" class="text-muted"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Modals -->
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
    <!--   Core JS Files   -->
    <script src="../js/core/jquery-3.7.1.min.js"></script>
    <script src="../js/core/popper.min.js"></script>
    <script src="../js/core/bootstrap.min.js"></script>

    <script src="../js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>
    <script src="../js/kaiadmin.min.js"></script>

    <script>
const COMPENSATION_API_PATH = '../api/api_compensation_management.php';
const ACADEMIC_YEAR_ID = <?php echo $academic_year_id; ?>;

let allCompensations = [];
let filteredCompensations = [];
let refreshInterval = null;
let currentPreviewData = null;
let teachersList = [];

 // ===== ตัวแปรสำหรับ Auto Schedule All =====
let autoScheduleTeachers = [];
let selectedTeacherId = null;
let userRole = null;

// ===== ฟังก์ชันแสดง/ซ่อน Loading =====
function showLoading() {
    document.getElementById('loadingOverlay').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
}

function showSuccess(message) {
    document.getElementById('successMessage').textContent = message;
    new bootstrap.Modal(document.getElementById('successModal')).show();
}

function showError(message) {
    document.getElementById('errorMessage').textContent = message;
    new bootstrap.Modal(document.getElementById('errorModal')).show();
}

// ===== ฟังก์ชันเรียก API =====
async function callCompensationAPI(action, params = {}) {
    console.log('🔄 กำลังเรียก API:', action, params);
    
    const formData = new URLSearchParams({
        action: action,
        academic_year_id: ACADEMIC_YEAR_ID,
        ...params
    });

    try {
        const response = await fetch(COMPENSATION_API_PATH, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json'
            },
            body: formData,
            credentials: 'include'
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP ${response.status}: ${errorText}`);
        }

        const data = await response.json();
        console.log('✅ ได้รับข้อมูล:', data);

        if (!data.success) {
            throw new Error(data.message || 'การเรียก API ไม่สำเร็จ');
        }

        return data;

    } catch (error) {
        console.error('🔥 Exception:', error);
        throw new Error('เกิดข้อผิดพลาด: ' + error.message);
    }
}

// ===== ฟังก์ชันโหลดข้อมูล =====
async function loadCompensations() {
    try {
        const data = await callCompensationAPI('get_all_compensations');
        
        if (data.success && data.data) {
            allCompensations = data.data.compensations || [];
            
            // อัปเดตสถิติ
            if (data.data.statistics) {
                updateStatistics(data.data.statistics);
            } else {
                updateStatistics();
            }
            
            applyFilters();
        }
        
    } catch (error) {
        console.error('Error loading compensations:', error);
        showError(error.message);
        updateStatistics();
    }
}

// ===== ฟังก์ชันโหลดรายชื่ออาจารย์ =====
async function loadTeachers() {
    try {
        const data = await callCompensationAPI('get_teachers_with_compensations', {
            academic_year_id: ACADEMIC_YEAR_ID
        });
        
        if (data.success && data.data) {
            teachersList = data.data;
            updateTeacherFilter();
        }
    } catch (error) {
        console.error('Error loading teachers:', error);
    }
}

function updateTeacherFilter() {
    const teacherSelect = document.getElementById('teacherFilter');
    teacherSelect.innerHTML = '<option value="">ทั้งหมด</option>';

    const currentUserId = <?php echo $current_user_id; ?>;
    const teacherMap = {};
    allCompensations.forEach(comp => {
        if (comp.user_id && comp.teacher_name) {
            if (!teacherMap[comp.user_id]) {
                teacherMap[comp.user_id] = {
                    user_id: comp.user_id,
                    teacher_name: comp.teacher_name,
                    count: 0
                };
            }
        }
        if (comp.co_user_id && comp.co_teacher_name) {
            if (!teacherMap[comp.co_user_id]) {
                teacherMap[comp.co_user_id] = {
                    user_id: comp.co_user_id,
                    teacher_name: comp.co_teacher_name,
                    count: 0
                };
            }
        }
        if (comp.co_user_id_2 && comp.co_teacher_name_2) {
            if (!teacherMap[comp.co_user_id_2]) {
                teacherMap[comp.co_user_id_2] = {
                    user_id: comp.co_user_id_2,
                    teacher_name: comp.co_teacher_name_2,
                    count: 0
                };
            }
        }
    });

    Object.values(teacherMap).forEach(teacher => {
        teacher.count = allCompensations.filter(comp =>
            comp.user_id == teacher.user_id ||
            comp.co_user_id == teacher.user_id ||
            comp.co_user_id_2 == teacher.user_id
        ).length;
    });

    // เรียง user_id ตัวเองขึ้นก่อน
    const teachersArr = Object.values(teacherMap).sort((a, b) => {
        if (a.user_id == currentUserId) return -1;
        if (b.user_id == currentUserId) return 1;
        return a.teacher_name.localeCompare(b.teacher_name, 'th');
    });

    teachersArr.forEach(teacher => {
        const option = document.createElement('option');
        option.value = teacher.user_id;
        option.textContent = `${teacher.teacher_name} (รายการ ${teacher.count})`;
        if (teacher.user_id == currentUserId) {
            option.selected = true;
        }
        teacherSelect.appendChild(option);
    });
    filterData();
}

// ===== ฟังก์ชันกรองข้อมูล =====
function applyFilters() {
    const statusFilter = document.getElementById('statusFilter').value;
    const typeFilter = document.getElementById('typeFilter').value;
    const teacherFilter = document.getElementById('teacherFilter').value;
    const subjectSearch = document.getElementById('subjectSearch').value.toLowerCase();

    filteredCompensations = allCompensations.filter(comp => {
        const statusMatch = !statusFilter || comp.status === statusFilter;
        const typeMatch = !typeFilter || comp.cancellation_type === typeFilter;
        // ปรับตรงนี้: ให้แสดงทั้งอาจารย์หลักและอาจารย์ร่วม
        const teacherMatch = !teacherFilter ||
            comp.user_id == teacherFilter ||
            comp.co_user_id == teacherFilter ||
            comp.co_user_id_2 == teacherFilter;
        const subjectMatch = !subjectSearch || 
            (comp.subject_code && comp.subject_code.toLowerCase().includes(subjectSearch)) ||
            (comp.subject_name && comp.subject_name.toLowerCase().includes(subjectSearch));

        return statusMatch && typeMatch && teacherMatch && subjectMatch;
    });

    updateCompensationTable();
    updateFilteredStatistics();
}
function filterData() {
    applyFilters();
}

// ===== ฟังก์ชันอัปเดตตาราง =====
function updateCompensationTable() {
    const tbody = document.getElementById('compensationTableBody');
    
    if (filteredCompensations.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center">
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h5>ไม่พบข้อมูลการชดเชย</h5>
                        <p>ไม่มีรายการที่ตรงกับเงื่อนไขที่เลือก</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = filteredCompensations.map(comp => {
        const statusClass = comp.status === 'รอดำเนินการ' ? 'status-pending' : 
                          comp.status === 'รอยืนยัน' ? 'status-waiting-approval' :
                          comp.status === 'ดำเนินการแล้ว' ? 'status-completed' : 'status-cancelled';
        
        const rowClass = comp.status === 'รอยืนยัน' ? 'approval-pending-indicator' : '';
        
        let makeupDateDisplay = '-';
        if (comp.status === 'รอยืนยัน' && comp.proposed_makeup_date) {
            makeupDateDisplay = `<span class="text-info fw-bold">เสนอ: ${formatThaiDate(comp.proposed_makeup_date)}</span>`;
        } else if (comp.makeup_date) {
            makeupDateDisplay = `<span class="text-success fw-bold">${formatThaiDate(comp.makeup_date)}</span>`;
        }
        
        let teachersDisplay = '';
        if (comp.teacher_name) {
            teachersDisplay += `<span>${comp.teacher_name}</span>`;
        }
        if (comp.co_teacher_name) {
            teachersDisplay += `<br><small class="text-muted">อาจารย์ร่วม: ${comp.co_teacher_name}</small>`;
        }
        if (comp.co_teacher_name_2) {
            teachersDisplay += `<br><small class="text-muted">อาจารย์ร่วม: ${comp.co_teacher_name_2}</small>`;
        }
        if (!teachersDisplay) {
            teachersDisplay = 'ไม่ระบุ';
        }

        let teachersTooltip = '';
        let coTeachers = [];
        if (comp.co_teacher_name) coTeachers.push(comp.co_teacher_name);
        if (comp.co_teacher_name_2) coTeachers.push(comp.co_teacher_name_2);
        if (coTeachers.length > 0) {
            teachersTooltip = `title="อาจารย์ร่วม: ${coTeachers.join(', ')}" data-bs-toggle="tooltip"`;
        }

        let actionButtons = `
            <button class="btn btn-sm btn-outline-info" onclick="viewDetails(${comp.cancellation_id})" title="ดูรายละเอียด">
                <i class="fas fa-eye"></i>
            </button>
        `;
        
        switch (comp.status) {
            case 'รอดำเนินการ':
                actionButtons += `
                    <button class="btn btn-sm btn-outline-primary" onclick="showAutoScheduleConfirm(${comp.cancellation_id})" title="จัดตารางอัตโนมัติ">
                        <i class="fas fa-magic"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="manualSchedule(${comp.cancellation_id})" title="จัดตารางเอง">
                        <i class="fas fa-calendar-plus"></i>
                    </button>
                `;
                break;
                
            case 'รอยืนยัน':
                actionButtons += `
                    <button class="btn btn-sm btn-outline-success" onclick="approveSchedule(${comp.cancellation_id})" title="อนุมัติ">
                        <i class="fas fa-check"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-warning" onclick="rejectSchedule(${comp.cancellation_id})" title="ยกเลิก">
                        <i class="fas fa-times"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="exportSingleCompensation(${comp.cancellation_id})" title="Export PDF">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                `;
                break;
                
            case 'ดำเนินการแล้ว':
                actionButtons += `
                    <button class="btn btn-sm btn-outline-warning" onclick="requestCancelCompensation(${comp.cancellation_id})" title="ขอยกเลิกรายการ">
                        <i class="fas fa-ban"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="exportSingleCompensation(${comp.cancellation_id})" title="Export PDF">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                `;
                break;
        }
        
        return `
            <tr class="${rowClass}">
                <td>${formatThaiDate(comp.cancellation_date)}</td>
                <td>
                    <strong>${comp.subject_code || 'ไม่ระบุ'}</strong><br>
                    <small class="text-muted">${comp.subject_name || 'ไม่ระบุ'}</small>
                </td>
                <td ${teachersTooltip}>
                    <div class="teacher-info">
                        ${teachersDisplay}
                    </div>
                </td>
                <td>${comp.reason || comp.cancellation_type || 'ไม่ระบุ'}</td>
                <td>${makeupDateDisplay}</td>
                <td><span class="badge ${statusClass}">${comp.status}</span></td>
                <td class="action-buttons">${actionButtons}</td>
            </tr>
        `;
    }).join('');
}

async function exportSingleCompensation(cancellationId) {
    try {
        showLoading();
        
        // สร้าง URL สำหรับ export PDF รายการเดียว
        const params = new URLSearchParams({
            academic_year_id: ACADEMIC_YEAR_ID,
            status_filter: 'confirmed_only',
            cancellation_id: cancellationId, // เพิ่มพารามิเตอร์สำหรับรายการเดียว
            export_format: 'pdf',
            export_scope: 'single_compensation'
        });
        
        const exportUrl = `../api/export_compensation_tcpdf.php?${params.toString()}`;
        const exportWindow = window.open(exportUrl, '_blank');
        
        hideLoading();
        
        if (!exportWindow) {
            throw new Error('ไม่สามารถเปิดหน้าต่างสำหรับดาวน์โหลดได้ กรุณาตรวจสอบการตั้งค่า popup blocker');
        }
        
        showSuccess('เริ่มต้น Export รายการการชดเชยเป็น PDF');
        
    } catch (error) {
        hideLoading();
        console.error('Export single compensation error:', error);
        showError('เกิดข้อผิดพลาดในการ Export: ' + error.message);
    }
}

// ===== ฟังก์ชันการจัดตารางอัตโนมัติ =====
async function showAutoScheduleConfirm(cancellationId) {
    try {
        showLoading();
        
        const data = await callCompensationAPI('preview_auto_schedule_single', {
            cancellation_id: cancellationId
        });
        
        hideLoading();
        
        if (data.success && data.data) {
            currentPreviewData = data.data;
            showAutoScheduleConfirmModal(data.data);
        }
        
    } catch (error) {
        hideLoading();
        showError(error.message);
    }
}
function getThaiDayOfWeek(dateString) {
    if (!dateString) return '';
    const days = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
    const date = new Date(dateString);
    return days[date.getDay()];
}
function showAutoScheduleConfirmModal(scheduleData) {
    let conflictWarning = '';
    if (scheduleData.conflicts && scheduleData.conflicts.length > 0) {
        conflictWarning = `
            <div class="conflict-warning">
                <h6><i class="fas fa-exclamation-triangle"></i> คำเตือน</h6>
                <ul class="mb-0">
                    ${scheduleData.conflicts.map(conflict => `<li>${conflict}</li>`).join('')}
                </ul>
            </div>
        `;
    }

    const content = `
    <div class="compensation-details">
        <h6><i class="fas fa-book text-primary"></i> รายวิชาที่จะจัดตาราง</h6>
        <div class="detail-row">
            <span class="detail-label">รหัสวิชา:</span>
            <span class="detail-value">${scheduleData.subject_code}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">ชื่อวิชา:</span>
            <span class="detail-value">${scheduleData.subject_name}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">อาจารย์ผู้สอน:</span>
            <span class="detail-value">${scheduleData.teacher_name}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">ชั้นปี:</span>
            <span class="detail-value">
                ${scheduleData.is_module_subject == 1 && scheduleData.year_levels_in_group && scheduleData.year_levels_in_group.length > 0
                    ? scheduleData.year_levels_in_group.map(yl => `${yl.department || '-'} ${yl.class_year || '-'} ${yl.curriculum || '-'}`).join('<br>')
                    : `${scheduleData.department || '-'} ${scheduleData.class_year || '-'} ${scheduleData.curriculum || '-'}`
                }
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">เหตุผล:</span>
            <span class="detail-value">${scheduleData.reason || scheduleData.cancellation_reason || scheduleData.cancellation_type || 'ไม่ระบุ'}</span>
        </div>
    </div>

        ${scheduleData.suggested_schedule ? `
            <div class="compensation-details">
                <h6><i class="fas fa-calendar-check text-success"></i> ตารางที่แนะนำ</h6>
                <div class="schedule-comparison">
                    <div class="schedule-item original">
                        <h6>ตารางเดิม</h6>
                        <p><strong>วันที่:</strong> ${formatThaiDate(scheduleData.cancellation_date)} (${getThaiDayOfWeek(scheduleData.cancellation_date)})</p>
                        <p><strong>เวลา:</strong> คาบ ${scheduleData.original_schedule.start_slot}-${scheduleData.original_schedule.end_slot}</p>
                        <p><strong>ห้อง:</strong> ${scheduleData.original_schedule.room}</p>
                    </div>
                    <div class="schedule-item proposed">
                        <h6>ตารางที่เสนอ</h6>
                        <p><strong>วันที่:</strong> ${formatThaiDate(scheduleData.suggested_schedule.date)} (${scheduleData.suggested_schedule.day_of_week})</p>
                        <p><strong>เวลา:</strong> คาบ ${scheduleData.suggested_schedule.start_slot}-${scheduleData.suggested_schedule.end_slot}</p>
                        <p><strong>ห้อง:</strong> ${scheduleData.suggested_schedule.room_number}</p>
                    </div>
                </div>
            </div>

            ${scheduleData.changes && scheduleData.changes.length > 0 ? `
                <div class="schedule-changes">
                    <h6><i class="fas fa-exclamation-triangle text-warning"></i> การเปลี่ยนแปลงจากตารางเดิม</h6>
                    <ul class="mb-0">
                        ${scheduleData.changes.map(change => `<li>${change}</li>`).join('')}
                    </ul>
                </div>
            ` : ''}

            ${conflictWarning}

            <div class="approval-actions">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> ยกเลิก
                </button>
                <button type="button" class="btn btn-info" onclick="requestDateAndRoomChangeFromPreview(${scheduleData.cancellation_id})">
                    <i class="fas fa-external-link-alt"></i> จัดตารางเอง
                </button>
                <button type="button" class="btn btn-success" onclick="confirmAutoScheduleSingleDirect(${scheduleData.cancellation_id})">
                    <i class="fas fa-check"></i> ยืนยัน
                </button>
            </div>
        ` : `
            <div class="alert alert-warning">
                <h6><i class="fas fa-exclamation-triangle"></i> ไม่สามารถจัดตารางอัตโนมัติได้</h6>
                <p>ระบบไม่พบช่วงเวลาที่เหมาะสมสำหรับการชดเชย</p>
                <p class="mb-0">แนะนำให้เลือกวันเวลาเอง หรือตรวจสอบความพร้อมของห้องเรียน</p>
            </div>

            <div class="approval-actions">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> ปิด
                </button>
                <button type="button" class="btn btn-info" onclick="requestDateAndRoomChangeFromPreview(${scheduleData.cancellation_id})">
                    <i class="fas fa-external-link-alt"></i> จัดการการเปลี่ยนแปลง
                </button>
                <button type="button" class="btn btn-primary" onclick="manualSchedule(${scheduleData.cancellation_id})">
                    <i class="fas fa-calendar-plus"></i> กำหนดเอง
                </button>
            </div>
        `}
    `;
    
    document.getElementById('autoScheduleConfirmContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('autoScheduleConfirmModal')).show();
}

// ยืนยันการจัดตารางอัตโนมัติแบบส่งรอยืนยัน
async function confirmAutoScheduleSingleWithApproval(cancellationId) {
    try {
        showLoading();
        
        const data = await callCompensationAPI('confirm_auto_schedule_single', {
            cancellation_id: cancellationId,
            require_approval: true
        });
        
        hideLoading();
        
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('autoScheduleConfirmModal')).hide();
            showSuccess('จัดตารางชดเชยสำเร็จ ส่งรอการยืนยันแล้ว (ยังไม่สร้างการสอนชดเชย)');
            setTimeout(() => loadCompensations(), 2000);
        }
        
    } catch (error) {
        hideLoading();
        showError(error.message);
    }
}

// ยืนยันการจัดตารางอัตโนมัติแบบอนุมัติทันที
async function confirmAutoScheduleSingleDirect(cancellationId) {
    try {
        showLoading();
        
        const data = await callCompensationAPI('confirm_auto_schedule_single', {
            cancellation_id: cancellationId,
            require_approval: false
        });
        
        hideLoading();
        
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('autoScheduleConfirmModal')).hide();
            showSuccess('จัดตารางชดเชยและสร้างการสอนชดเชยเสร็จสิ้น');
            setTimeout(() => loadCompensations(), 2000);
        }
        
    } catch (error) {
        hideLoading();
        showError(error.message);
    }
}

// ===== ฟังก์ชันการอนุมัติ/ปฏิเสธ =====
async function approveSchedule(cancellationId) {
    if (!confirm('ยืนยันการอนุมัติตารางชดเชยนี้หรือไม่?')) {
        return;
    }

    try {
        showLoading();
        
        const data = await callCompensationAPI('approve_compensation_schedule', {
            cancellation_id: cancellationId
        });
        
        hideLoading();
        
        if (data.success) {
            showSuccess(data.message);
            setTimeout(() => loadCompensations(), 2000);
        }
        
    } catch (error) {
        hideLoading();
        showError(error.message);
    }
}

async function rejectSchedule(cancellationId) {
    const reason = prompt('กรุณาระบุเหตุผลในการปฏิเสธ:');
    if (!reason) return;

    try {
        showLoading();
        
        const data = await callCompensationAPI('reject_compensation_schedule', {
            cancellation_id: cancellationId,
            rejection_reason: reason
        });
        
        hideLoading();
        
        if (data.success) {
            showSuccess(data.message);
            setTimeout(() => loadCompensations(), 2000);
        }
        
    } catch (error) {
        hideLoading();
        showError(error.message);
    }
}

// ===== ฟังก์ชันขอเปลี่ยนวันและห้อง =====
async function requestDateAndRoomChange(cancellationId) {
    try {
        // นำทางไปยังหน้า compensation_management.php พร้อมพารามิเตอร์
        window.location.href = `compensation_management.php?action=change_date&cancellation_id=${cancellationId}`;
        
    } catch (error) {
        showError('เกิดข้อผิดพลาดในการนำทาง: ' + error.message);
    }
}

async function requestDateAndRoomChangeFromDetail(cancellationId) {
    try {
        // นำทางไปยังหน้า compensation_management.php พร้อมพารามิเตอร์
        window.location.href = `compensation_management.php?action=change_date&cancellation_id=${cancellationId}&from=detail`;
        
    } catch (error) {
        showError('เกิดข้อผิดพลาดในการนำทาง: ' + error.message);
    }
}

async function requestDateAndRoomChangeFromPreview(cancellationId) {
    try {
        // นำทางไปยังหน้า compensation_management.php พร้อมพารามิเตอร์
        window.location.href = `compensation_management.php?action=change_date&cancellation_id=${cancellationId}&from=preview`;
        
    } catch (error) {
        showError('เกิดข้อผิดพลาดในการนำทาง: ' + error.message);
    }
}

// ===== ฟังก์ชันจัดตารางเอง =====
async function manualSchedule(cancellationId) {
    try {
        // นำทางไปยังหน้า compensation_management.php พร้อมพารามิเตอร์
        window.location.href = `compensation_management.php?action=manual_schedule&cancellation_id=${cancellationId}`;
        
    } catch (error) {
        showError('เกิดข้อผิดพลาดในการนำทาง: ' + error.message);
    }
}


// ===== ฟังก์ชันแสดง Auto Schedule All Modal =====
function autoScheduleAll() {
    // แสดง Modal และโหลดข้อมูลอาจารย์
    loadAutoScheduleOptions();
    new bootstrap.Modal(document.getElementById('autoScheduleAllModal')).show();
}

// ===== โหลดข้อมูลอาจารย์สำหรับ Auto Schedule =====
async function loadAutoScheduleOptions() {
    try {
        const data = await callCompensationAPI('get_teachers_for_auto_schedule', {
            academic_year_id: ACADEMIC_YEAR_ID
        });
        
        if (data.success && data.data) {
            autoScheduleTeachers = data.data.teachers || [];
            userRole = data.data.user_role;
            
            showAutoScheduleOptions(
                autoScheduleTeachers, 
                userRole, 
                data.data.can_select_all || false
            );
        } else {
            showAutoScheduleError('ไม่พบข้อมูลอาจารย์ที่มีการชดเชย');
        }
        
    } catch (error) {
        console.error('Error loading auto schedule options:', error);
        showAutoScheduleError(error.message);
    }
}

// ===== แสดงตัวเลือกอาจารย์ =====
function showAutoScheduleOptions(teachers, role, canSelectAll) {
    let content = '';
    
    if (teachers.length === 0) {
        content = `
            <div class="alert alert-warning text-center">
                <h6><i class="fas fa-exclamation-triangle"></i> ไม่มีรายการที่ต้องจัดตาราง</h6>
                <p class="mb-0">ไม่พบรายการชดเชยที่รอดำเนินการ</p>
            </div>
        `;
        document.getElementById('confirmAutoScheduleAllBtn').disabled = true;
    } else {
        content = `
            <div class="mb-4">
                <h6><i class="fas fa-users"></i> เลือกขอบเขตการจัดตาราง</h6>
                <div class="form-check-container">
        `;
        
        // ถ้าเป็น admin สามารถเลือก "ทั้งหมด" ได้
        if (canSelectAll) {
            const totalPending = teachers.reduce((sum, teacher) => sum + teacher.pending_compensation_count, 0);
            content += `
                <div class="form-check mb-3 p-3 border rounded bg-light">
                    <input class="form-check-input" type="radio" name="teacherSelection" id="allTeachers" value="all" checked>
                    <label class="form-check-label fw-bold" for="allTeachers">
                        <i class="fas fa-users text-primary"></i> 
                        จัดตารางทั้งหมด
                        <span class="badge bg-primary ms-2">${totalPending} รายการ</span>
                    </label>
                    <div class="text-muted mt-1">
                        จัดตารางชดเชยอัตโนมัติสำหรับอาจารย์ทุกคนที่มีรายการรอดำเนินการ
                    </div>
                </div>
            `;
        }
        
        // แสดงรายชื่ออาจารย์
        teachers.forEach((teacher, index) => {
            const isChecked = (!canSelectAll && teachers.length === 1) || 
                             (canSelectAll && teacher.user_id == <?php echo $current_user_id; ?>) ? 'checked' : '';
            const selectionValue = teacher.user_id == <?php echo $current_user_id; ?> ? 'self' : 'other';
            
            content += `
                <div class="form-check mb-3 p-3 border rounded">
                    <input class="form-check-input" type="radio" name="teacherSelection" 
                           id="teacher_${teacher.user_id}" 
                           value="${selectionValue}" 
                           data-teacher-id="${teacher.user_id}" 
                           ${isChecked}>
                    <label class="form-check-label" for="teacher_${teacher.user_id}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${teacher.teacher_name}</strong>
                                ${teacher.user_id == <?php echo $current_user_id; ?> ? 
                                    '<span class="badge bg-info ms-2">ของคุณ</span>' : ''}
                                <div class="text-muted small">วิชาที่สอน: ${teacher.subjects}</div>
                            </div>
                            <span class="badge bg-warning text-dark">${teacher.pending_compensation_count} รายการ</span>
                        </div>
                    </label>
                </div>
            `;
        });
        

        
        document.getElementById('confirmAutoScheduleAllBtn').disabled = false;
    }
    
    document.getElementById('autoScheduleOptionsContent').innerHTML = content;
    
    // เพิ่ม event listener สำหรับการเลือก
    document.querySelectorAll('input[name="teacherSelection"]').forEach(radio => {
        radio.addEventListener('change', function() {
            selectedTeacherId = this.getAttribute('data-teacher-id') || null;
            const selectionType = this.value;
            updateConfirmButtonText(selectionType);
        });
    });
    
    // ตั้งค่าเริ่มต้น
    if (canSelectAll) {
        selectedTeacherId = null; // เลือกทั้งหมด
        updateConfirmButtonText('all');
    } else if (teachers.length === 1) {
        selectedTeacherId = teachers[0].user_id;
        updateConfirmButtonText('self');
    }
}

// ===== อัปเดตข้อความบนปุ่มยืนยัน =====
function updateConfirmButtonText(selectionType = null) {
    const confirmBtn = document.getElementById('confirmAutoScheduleAllBtn');
    
    if (!selectionType) {
        const selectedRadio = document.querySelector('input[name="teacherSelection"]:checked');
        selectionType = selectedRadio ? selectedRadio.value : 'all';
    }
    
    switch (selectionType) {
        case 'self':
            const currentTeacher = autoScheduleTeachers.find(t => t.user_id == <?php echo $current_user_id; ?>);
            if (currentTeacher) {
                confirmBtn.innerHTML = `<i class="fas fa-magic"></i> จัดตารางของตัวเอง (${currentTeacher.pending_compensation_count} รายการ)`;
            }
            break;
        case 'other':
            if (selectedTeacherId) {

                const selectedTeacher = autoScheduleTeachers.find(t => t.user_id == selectedTeacherId);
                if (selectedTeacher) {
                    confirmBtn.innerHTML = `<i class="fas fa-magic"></i> จัดตารางของ ${selectedTeacher.teacher_name} (${selectedTeacher.pending_compensation_count} รายการ)`;
                }
            }
            break;
        case 'all':
        default:
            const totalPending = autoScheduleTeachers.reduce((sum, teacher) => sum + teacher.pending_compensation_count, 0);
            confirmBtn.innerHTML = `<i class="fas fa-magic"></i> จัดตารางทั้งหมด (${totalPending} รายการ)`;
            break;
    }
}

// ===== ดำเนินการจัดตารางอัตโนมัติทั้งหมด =====
async function executeAutoScheduleAll() {
    const selectedRadio = document.querySelector('input[name="teacherSelection"]:checked');
    
    if (!selectedRadio) {
        showError('กรุณาเลือกขอบเขตการจัดตารางก่อน');
        return;
    }
    
    const selectionType = selectedRadio.value;
    const teacherId = selectedRadio.getAttribute('data-teacher-id');
    
    // สร้างข้อความยืนยัน
    let confirmMessage = 'คุณต้องการจัดตารางชดเชยอัตโนมัติหรือไม่?\n\n';
    
    switch (selectionType) {
        case 'self':
            const currentTeacher = autoScheduleTeachers.find(t => t.user_id == <?php echo $current_user_id; ?>);
            if (currentTeacher) {
                confirmMessage += `ขอบเขต: รายการของตัวเอง\n`;
                confirmMessage += `จำนวนรายการ: ${currentTeacher.pending_compensation_count} รายการ\n`;
            }
            break;
        case 'other':
            if (teacherId) {
                const selectedTeacher = autoScheduleTeachers.find(t => t.user_id == teacherId);
                if (selectedTeacher) {
                    confirmMessage += `ขอบเขต: รายการของ ${selectedTeacher.teacher_name}\n`;
                    confirmMessage += `จำนวนรายการ: ${selectedTeacher.pending_compensation_count} รายการ\n`;
                }
            }
            break;
        case 'all':
            const totalPending = autoScheduleTeachers.reduce((sum, teacher) => sum + teacher.pending_compensation_count, 0);
            confirmMessage += `ขอบเขต: อาจารย์ทุกคน\n`;
            confirmMessage += `จำนวนรายการ: ${totalPending} รายการ\n`;
            break;
    }
    
    confirmMessage += '\n✅ ระบบจะสร้างตารางเสนอและรอการยืนยันจากคุณก่อนดำเนินการจริง';
    confirmMessage += '\n⚠️ การดำเนินการนี้อาจใช้เวลาสักครู่';
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    try {
        showLoading();
        
        const params = {
            academic_year_id: ACADEMIC_YEAR_ID,
            selection_type: selectionType
        };
        
        // เพิ่ม teacher_id ถ้าเลือกอาจารย์เฉพาะ
        if (selectionType === 'other' && teacherId) {
            params.selected_teacher_id = teacherId;
        } else if (selectionType === 'self') {
            params.selected_teacher_id = <?php echo $current_user_id; ?>;
        }
        // สำหรับ 'all' ไม่ต้องเพิ่ม selected_teacher_id
        
        console.log('🚀 Sending auto schedule request:', params);
        
        const data = await callCompensationAPI('auto_schedule_all_compensations', params);
        
        hideLoading();
        
        if (data.success) {
            // ปิด modal
            bootstrap.Modal.getInstance(document.getElementById('autoScheduleAllModal')).hide();
            
            // แสดงผลลัพธ์
            showAutoScheduleResults(data.data, data.message);
            
            // รีโหลดข้อมูล
            setTimeout(() => loadCompensations(), 2000);
        }
        
    } catch (error) {
        hideLoading();
        console.error('❌ Auto schedule error:', error);
        showError(error.message);
    }
}

// ===== แสดงผลลัพธ์การจัดตารางอัตโนมัติ =====
function showAutoScheduleResults(results, message) {
    let detailsHtml = '';
    
    if (results && results.details && results.details.length > 0) {
        const successful = results.details.filter(d => d.status === 'success');
        const failed = results.details.filter(d => d.status === 'failed' || d.status === 'error');
        
        if (successful.length > 0) {
            detailsHtml += `
                <div class="mt-3">
                    <h6 class="text-success">✅ จัดตารางสำเร็จ (${successful.length} รายการ)</h6>
                    <ul class="list-unstyled">
            `;
            successful.forEach(item => {
                detailsHtml += `
                    <li class="mb-1">
                        <span class="text-muted">${item.subject_code}</span> 
                        - ${item.teacher_name}
                        <small class="text-success ms-2">(${getStrategyDisplayName(item.strategy_used)})</small>
                    </li>
                `;
            });
            detailsHtml += `</ul></div>`;
        }
        
        if (failed.length > 0) {
            detailsHtml += `
                <div class="mt-3">
                    <h6 class="text-warning">⚠️ ไม่สามารถจัดตารางได้ (${failed.length} รายการ)</h6>
                    <ul class="list-unstyled">
            `;
            failed.forEach(item => {
                detailsHtml += `
                    <li class="mb-1">
                        <span class="text-muted">${item.subject_code}</span> 
                        - ${item.teacher_name}
                        <small class="text-warning ms-2">(${item.reason})</small>
                    </li>
                `;
            });
            detailsHtml += `</ul></div>`;
        }
    }
    
    // แสดงใน success modal
    document.getElementById('successMessage').innerHTML = message + detailsHtml;
    new bootstrap.Modal(document.getElementById('successModal')).show();
}

// ฟังก์ชันช่วยแปลชื่อกลยุทธ์
function getStrategyDisplayName(strategy) {
    const strategies = {
        'same_room_same_time': 'ห้องเดิม เวลาเดิม',
        'same_room_different_time': 'ห้องเดิม เวลาใหม่',
        'different_room_same_time': 'ห้องใหม่ เวลาเดิม',
        'different_room_different_time': 'ห้องใหม่ เวลาใหม่',
        'auto': 'อัตโนมัติ'
    };
    
    return strategies[strategy] || strategy;
}
// ===== ฟังก์ชันคิวการอนุมัติ =====
function showApprovalQueue() {
    const waitingApproval = allCompensations.filter(comp => comp.status === 'รอยืนยัน');
    
    if (waitingApproval.length === 0) {
        showSuccess('ไม่มีรายการที่รอการยืนยัน');
        return;
    }
    
    const content = `
        <div class="alert alert-info">
            <h6><i class="fas fa-info-circle"></i> รายการที่รอการยืนยัน</h6>
            <p class="mb-0">มีรายการทั้งหมด ${waitingApproval.length} รายการที่รอการยืนยัน</p>
        </div>
        
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>รายวิชา</th>
                        <th>อาจารย์</th>
                        <th>วันที่เสนอ</th>
                        <th>การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    ${waitingApproval.map(comp => `
                        <tr>
                            <td>
                                <strong>${comp.subject_code}</strong><br>
                                <small class="text-muted">${comp.subject_name}</small>
                            </td>
                            <td>${comp.teacher_name}</td>
                            <td>${formatThaiDate(comp.proposed_makeup_date)}</td>
                            <td>
                                <button class="btn btn-sm btn-success" onclick="approveFromQueue(${comp.cancellation_id})">
                                    <i class="fas fa-check"></i> อนุมัติ
                                </button>
                                <button class="btn btn-sm btn-info" onclick="viewFromQueue(${comp.cancellation_id})">
                                    <i class="fas fa-eye"></i> ดู
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
        
        <div class="d-flex justify-content-end gap-2 mt-3">
            <button class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
            <button class="btn btn-success" onclick="approveAllInQueue()">
                <i class="fas fa-check-double"></i> อนุมัติทั้งหมด
            </button>
        </div>
    `;
    
    document.getElementById('approvalQueueContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('approvalQueueModal')).show();
}

async function approveFromQueue(cancellationId) {
    bootstrap.Modal.getInstance(document.getElementById('approvalQueueModal')).hide();
    setTimeout(() => approveSchedule(cancellationId), 300);
}

async function viewFromQueue(cancellationId) {
    bootstrap.Modal.getInstance(document.getElementById('approvalQueueModal')).hide();
    setTimeout(() => viewDetails(cancellationId), 300);
}

async function approveAllInQueue() {
    if (!confirm('คุณต้องการอนุมัติรายการทั้งหมดที่รอการยืนยันหรือไม่?')) {
        return;
    }
    
    const waitingApproval = allCompensations.filter(comp => comp.status === 'รอยืนยัน');
    
    try {
        showLoading();
        
        for (const comp of waitingApproval) {
            await callCompensationAPI('approve_compensation_schedule', {
                cancellation_id: comp.cancellation_id
            });
        }
        
        hideLoading();
        bootstrap.Modal.getInstance(document.getElementById('approvalQueueModal')).hide();
        showSuccess(`อนุมัติเสร็จสิ้น ${waitingApproval.length} รายการ`);
        setTimeout(() => loadCompensations(), 2000);
        
    } catch (error) {
        hideLoading();
        showError(error.message);
    }
}

// ===== ฟังก์ชันดูรายละเอียด =====
async function viewDetails(cancellationId) {
    try {
        showLoading();
        const data = await callCompensationAPI('get_compensation_details', {
            cancellation_id: cancellationId
        });
        
        hideLoading();
        
        if (data.success && data.data) {
            showCompensationDetails(data.data);
        }
        
    } catch (error) {
        hideLoading();
        showError(error.message);
    }
}

// ===== ฟังก์ชันแสดงข้อมูลการชดเชย - ปรับปรุงให้ใช้ข้อมูลที่ถูกต้อง =====
function displayCompensationInfo(data) {
    const compensationInfo = document.getElementById('compensationInfo');
    
    // จัดรูปแบบข้อมูลอาจารย์
    let teachersInfo = '';
    if (data.all_teachers) {
        teachersInfo = data.all_teachers;
    } else if (data.teacher_name) {
        teachersInfo = data.teacher_name;
    } else {
        teachersInfo = 'ไม่ระบุ';
    }
    
    compensationInfo.innerHTML = `
        <div class="mb-3">
            <h6 class="text-primary mb-2"><i class="fas fa-book"></i> ข้อมูลรายวิชา</h6>
            <p class="mb-1"><strong>รหัสวิชา:</strong> ${data.subject_code || 'ไม่ระบุ'}</p>
            <p class="mb-1"><strong>ชื่อวิชา:</strong> ${data.subject_name || 'ไม่ระบุ'}</p>
            <p class="mb-1"><strong>อาจารย์:</strong> ${teachersInfo}</p>
            ${data.teachers_count > 1 ? `<p class="mb-1"><small class="text-muted">จำนวนอาจารย์: ${data.teachers_count} คน</small></p>` : ''}
            <p class="mb-1"><strong>ชั้นปี:</strong> ${data.class_year || 'ไม่ระบุ'}</p>
        </div>
        
        <div class="mb-3">
            <h6 class="text-danger mb-2"><i class="fas fa-times-circle"></i> การยกเลิก</h6>
            <p class="mb-1"><strong>วันที่ยกเลิก:</strong> ${formatThaiDate(data.cancellation_date)}</p>
            <p class="mb-1"><strong>ประเภท:</strong> ${data.cancellation_type || 'ไม่ระบุ'}</p>
            <p class="mb-1"><strong>เหตุผล:</strong> ${data.reason || 'ไม่ระบุ'}</p>
        </div>
        
        <div class="mb-3">
            <h6 class="text-info mb-2"><i class="fas fa-clock"></i> เวลาเดิม</h6>
            <p class="mb-1"><strong>ห้องเรียน:</strong> ${data.room_number || 'ไม่ระบุ'}</p>
            <p class="mb-1"><strong>เวลา:</strong> ${data.start_time || ''} - ${data.end_time || ''}</p>
            <p class="mb-1"><strong>วัน:</strong> ${data.day_of_week || 'ไม่ระบุ'}</p>
        </div>
        
        <div class="alert alert-info">
            <i class="fas fa-lightbulb"></i>
            <strong>สถานะ:</strong> ${data.status || 'รอดำเนินการ'}
        </div>
    `;
    
    // โหลด Auto Schedule Preview หลังจากแสดงข้อมูลแล้ว
    loadAutoSchedulePreview();
}

function showCompensationDetails(compensation) {
    // จัดรูปแบบข้อมูลอาจารย์
    let teachersInfo = '';
    if (compensation.all_teachers) {
        teachersInfo = compensation.all_teachers;
    } else if (compensation.teacher_name) {
        teachersInfo = compensation.teacher_name;
    } else {
        teachersInfo = 'ไม่ระบุ';
    }

    // ==== ปรับตรงนี้: แสดงชั้นปีเป็น department + class_year + curriculum ====
    let classYearDisplay = '';
    if (compensation.is_module_subject == 1 && compensation.group_name) {
        classYearDisplay = `<span>${compensation.group_name || '-'} ${compensation.module_name || '-'}</span>
                            <br><span>ชั้นปีในกลุ่ม<br>${
                            (compensation.year_levels_in_group || [])
                                .map(yl => `${yl.department || '-'} ${yl.class_year || '-'} ${yl.curriculum || '-'}`)
                                .join('<br>')
                        }</span>`;
    } else {
        classYearDisplay = `
            <span>${compensation.department || '-'}</span>
            <span>${compensation.class_year || '-'}</span>
            <span>${compensation.curriculum || '-'}</span>
        `;
    }

    const content = `
        <div class="compensation-details">
            <h6><i class="fas fa-calendar-times text-danger"></i> ข้อมูลการยกเลิก</h6>
            <div class="detail-row">
                <span class="detail-label">วันที่ยกเลิก:</span>
                <span class="detail-value">${formatThaiDate(compensation.cancellation_date)}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">ประเภท:</span>
                <span class="detail-value">${compensation.cancellation_type || 'ไม่ระบุ'}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">เหตุผล:</span>
                <span class="detail-value">${compensation.reason || 'ไม่ระบุ'}</span>
            </div>
        </div>

        <div class="compensation-details">
            <h6><i class="fas fa-book text-primary"></i> ข้อมูลรายวิชา</h6>
            <div class="detail-row">
                <span class="detail-label">รหัสวิชา:</span>
                <span class="detail-value">${compensation.subject_code || 'ไม่ระบุ'}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">ชื่อวิชา:</span>
                <span class="detail-value">${compensation.subject_name || 'ไม่ระบุ'}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">อาจารย์ผู้สอน:</span>
                <span class="detail-value">${teachersInfo}</span>
            </div>
            ${compensation.teachers_count > 1 ? `
            <div class="detail-row">
                <span class="detail-label">จำนวนอาจารย์:</span>
                <span class="detail-value">${compensation.teachers_count} คน</span>
            </div>
            ` : ''}
            <div class="detail-row">
                <span class="detail-label">ชั้นปี:</span>
                <span class="detail-value">${classYearDisplay}</span>
            </div>
        </div>

        <div class="compensation-details">
            <h6><i class="fas fa-clock text-info"></i> ตารางเดิม</h6>
            <div class="detail-row">
                <span class="detail-label">ห้องเรียน:</span>
                <span class="detail-value">${compensation.room_number || 'ไม่ระบุ'}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">เวลา:</span>
                <span class="detail-value">${compensation.start_time || ''} - ${compensation.end_time || ''}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">วัน:</span>
                <span class="detail-value">${compensation.day_of_week || 'ไม่ระบุ'}</span>
            </div>
        </div>

        ${compensation.status === 'รอยืนยัน' && compensation.proposed_makeup_date ? `
            <div class="compensation-details">
                <h6><i class="fas fa-calendar-check text-warning"></i> ตารางชดเชยที่เสนอ</h6>
                <div class="detail-row">
                    <span class="detail-label">วันที่:</span>
                    <span class="detail-value">${formatThaiDate(compensation.proposed_makeup_date)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">ห้องเรียน:</span>
                    <span class="detail-value">${compensation.proposed_room_number || 'ไม่ระบุ'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">เวลา:</span>
                    <span class="detail-value">${compensation.proposed_start_time || ''} - ${compensation.proposed_end_time || ''}</span>
                </div>
                ${compensation.change_reason ? `
                <div class="detail-row">
                    <span class="detail-label">เหตุผล:</span>
                    <span class="detail-value">${compensation.change_reason}</span>
                </div>
                ` : ''}
            </div>
        ` : ''}

        ${compensation.status === 'ดำเนินการแล้ว' && compensation.makeup_date ? `
            <div class="compensation-details">
                <h6><i class="fas fa-calendar-check text-success"></i> ตารางชดเชยที่อนุมัติ</h6>
                <div class="detail-row">
                    <span class="detail-label">วันที่:</span>
                    <span class="detail-value">${formatThaiDate(compensation.makeup_date)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">ห้องเรียน:</span>
                    <span class="detail-value">${compensation.makeup_room_number || 'ไม่ระบุ'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">เวลา:</span>
                    <span class="detail-value">${compensation.makeup_start_time || ''} - ${compensation.makeup_end_time || ''}</span>
                </div>
                ${compensation.approved_by_name ? `
                <div class="detail-row">
                    <span class="detail-label">อนุมัติโดย:</span>
                    <span class="detail-value">${compensation.approved_by_name}</span>
                </div>
                ` : ''}
                ${compensation.approved_at ? `
                <div class="detail-row">
                    <span class="detail-label">วันที่อนุมัติ:</span>
                    <span class="detail-value">${formatThaiDateTime(compensation.approved_at)}</span>
                </div>
                ` : ''}
            </div>
        ` : ''}

        ${compensation.rejected_reason ? `
            <div class="compensation-details">
                <h6><i class="fas fa-times-circle text-danger"></i> เหตุผลการปฏิเสธ</h6>
                <div class="detail-row">
                    <span class="detail-label">เหตุผล:</span>
                    <span class="detail-value">${compensation.rejected_reason}</span>
                </div>
            </div>
        ` : ''}

        <div class="compensation-details">
            <h6><i class="fas fa-info-circle text-secondary"></i> สถานะและข้อมูลเพิ่มเติม</h6>
            <div class="detail-row">
                <span class="detail-label">สถานะ:</span>
                <span class="detail-value"><span class="badge ${compensation.status === 'รอดำเนินการ' ? 'status-pending' : 
                      compensation.status === 'รอยืนยัน' ? 'status-waiting-approval' :
                      compensation.status === 'ดำเนินการแล้ว' ? 'status-completed' : 'status-cancelled'}">${compensation.status}</span></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">สร้างเมื่อ:</span>
                <span class="detail-value">${formatThaiDateTime(compensation.created_at)}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">ต้องการชดเชย:</span>
                <span class="detail-value">${compensation.is_makeup_required ? 'ใช่' : 'ไม่'}</span>
            </div>
            ${compensation.updated_at ? `
            <div class="detail-row">
                <span class="detail-label">แก้ไขล่าสุด:</span>
                <span class="detail-value">${formatThaiDateTime(compensation.updated_at)}</span>
            </div>
            ` : ''}
        </div>

        ${compensation.status === 'รอยืนยัน' ? `
            <div class="approval-actions mt-4">
                <button class="btn btn-success" onclick="approveFromDetail(${compensation.cancellation_id})">
                    <i class="fas fa-check"></i> อนุมัติ
                </button>
                <button class="btn btn-danger" onclick="rejectFromDetail(${compensation.cancellation_id})">
                    <i class="fas fa-times"></i> ปฏิเสธ
                </button>
            </div>
        ` : ''}
        
        ${compensation.status === 'ดำเนินการแล้ว' ? `

        ` : ''}
    `;
    
    document.getElementById('compensationDetailContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('compensationDetailModal')).show();
}

// ===== ฟังก์ชันสำหรับเรียกใช้งานจากหน้ารายละเอียด =====
function approveFromDetail(cancellationId) {
    bootstrap.Modal.getInstance(document.getElementById('compensationDetailModal')).hide();
    setTimeout(() => approveSchedule(cancellationId), 300);
}

function requestDateAndRoomChangeFromDetail(cancellationId) {
    window.location.href = `compensation_management.php?action=change_date&cancellation_id=${cancellationId}&from=detail`;
}

function rejectFromDetail(cancellationId) {
    bootstrap.Modal.getInstance(document.getElementById('compensationDetailModal')).hide();
    setTimeout(() => rejectSchedule(cancellationId), 300);
}

// ===== ฟังก์ชันสำหรับเรียกใช้งานจากหน้า preview =====
function requestDateAndRoomChangeFromPreview(cancellationId) {
    // นำทางไปยังหน้า compensation_management.php
    window.location.href = `compensation_management.php?action=change_date&cancellation_id=${cancellationId}&from=preview`;
}

// ===== ฟังก์ชัน Backward Compatibility =====
function requestDateChange(cancellationId) {
    // นำทางไปยังหน้า compensation_management.php
    window.location.href = `compensation_management.php?action=change_date&cancellation_id=${cancellationId}`;
}

function requestDateChangeFromDetail(cancellationId) {
    // นำทางไปยังหน้า compensation_management.php
    window.location.href = `compensation_management.php?action=change_date&cancellation_id=${cancellationId}&from=detail`;
}

function requestDateChangeFromPreview(cancellationId) {
    // นำทางไปยังหน้า compensation_management.php
    window.location.href = `compensation_management.php?action=change_date&cancellation_id=${cancellationId}&from=preview`;
}

function refreshData() {
    showLoading();
    loadCompensations().finally(() => {
        hideLoading();
    });
}

// ===== ฟังก์ชันอัปเดตสถิติจากข้อมูลที่กรองแล้ว =====
function updateFilteredStatistics() {
    if (filteredCompensations && filteredCompensations.length >= 0) {
        const pending = filteredCompensations.filter(comp => comp.status === 'รอดำเนินการ').length;
        const waitingApproval = filteredCompensations.filter(comp => comp.status === 'รอยืนยัน').length;
        const completed = filteredCompensations.filter(comp => comp.status === 'ดำเนินการแล้ว').length;
        const cancelled = filteredCompensations.filter(comp => comp.status === 'ยกเลิก').length;

        // อัปเดตตัวเลขสถิติ
        document.getElementById('pendingCount').textContent = pending;
        document.getElementById('waitingApprovalCount').textContent = waitingApproval;
        document.getElementById('completedCount').textContent = completed;      
    }
}

// ===== ฟังก์ชันอัพเดตสถิติ =====
function updateStatistics(statistics = null) {
    try {
        if (statistics) {
            document.getElementById('pendingCount').textContent = statistics.pending || 0;
            document.getElementById('waitingApprovalCount').textContent = statistics.waiting_approval || 0;
            document.getElementById('completedCount').textContent = statistics.completed || 0;
            return;
        }

        if (allCompensations && allCompensations.length > 0) {
            const pending = allCompensations.filter(comp => comp.status === 'รอดำเนินการ').length;
            const waitingApproval = allCompensations.filter(comp => comp.status === 'รอยืนยัน').length;
            const completed = allCompensations.filter(comp => comp.status === 'ดำเนินการแล้ว').length;
            const cancelled = allCompensations.filter(comp => comp.status === 'ยกเลิก').length;

            document.getElementById('pendingCount').textContent = pending;
            document.getElementById('waitingApprovalCount').textContent = waitingApproval;
            document.getElementById('completedCount').textContent = completed;
        } else {
            document.getElementById('pendingCount').textContent = '0';
            document.getElementById('waitingApprovalCount').textContent = '0';
            document.getElementById('completedCount').textContent = '0';
        }
        
    } catch (error) {
        console.warn('Warning: Could not update statistics display:', error.message);
    }
}

// ===== ฟังก์ชันจัดรูปแบบวันที่ =====
function formatThaiDate(dateString) {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    const thaiMonths = [
        'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
        'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
    ];
    
    const day = date.getDate();
    const month = thaiMonths[date.getMonth()];
    const year = date.getFullYear() + 543;
    
    return `${day} ${month} ${year}`;
}

function formatThaiDateTime(dateTimeString) {
    if (!dateTimeString) return '';
    
    const date = new Date(dateTimeString);
    const thaiMonths = [
        'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
        'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
    ];
    
    const day = date.getDate();
    const month = thaiMonths[date.getMonth()];
    const year = date.getFullYear() + 543;
    const hour = date.getHours().toString().padStart(2, '0');
    const minute = date.getMinutes().toString().padStart(2, '0');
    
    return `${day} ${month} ${year} ${hour}:${minute}`;
}

// ===== ฟังก์ชันรีเฟรชอัตโนมัติ =====
function startAutoRefresh() {
    // รีเฟรชทุก 30 วินาที
    refreshInterval = setInterval(() => {
        loadCompensations();
    }, 30000);
}

function stopAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
}

// ===== ฟังก์ชันการแจ้งเตือน =====
function highlightPendingApprovals() {
    const pendingItems = document.querySelectorAll('.approval-pending-indicator');
    pendingItems.forEach(item => {
        item.style.animation = 'pendingPulse 2s infinite';
    });
}

// ===== การเริ่มต้นระบบ =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎯 Compensation Management System with Teacher Filter Initialized');
    console.log('📅 Academic Year ID:', ACADEMIC_YEAR_ID);
    console.log('🔗 API Path:', COMPENSATION_API_PATH);
    
    // เปิดใช้งาน tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // โหลดข้อมูลเริ่มต้น
    loadCompensations();
    loadTeachers(); // เพิ่มการโหลดรายชื่ออาจารย์
    
    // เริ่มรีเฟรชอัตโนมัติ
    startAutoRefresh();
    
    // เรียกใช้ highlight เมื่อโหลดข้อมูลเสร็จ
    setInterval(highlightPendingApprovals, 5000);
});

// หยุดรีเฟรชอัตโนมัติเมื่อออกจากหน้า
window.addEventListener('beforeunload', function() {
    stopAutoRefresh();
});

// จัดการ error ทั่วไป
window.addEventListener('error', function(e) {
    console.error('JavaScript Error:', e.error);
    hideLoading();
});

window.addEventListener('unhandledrejection', function(e) {
    console.error('Unhandled Promise Rejection:', e.reason);
    hideLoading();
});
    </script>
</body>
</html>