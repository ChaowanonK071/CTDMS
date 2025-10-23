<?php
// ตรวจสอบการเข้าสู่ระบบก่อนแสดงหน้า
// แก้ไข path ให้ถูกต้อง
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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดตารางสอนชดเชย</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .header-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .header-card h2 {
            margin: 0;
            font-weight: 600;
        }

        .header-card p {
            margin: 0;
            opacity: 0.9;
        }

       .info-card {
            border-left: 4px solid #17a2b8;
            margin-bottom: 20px;
        }

        .info-card .card-header {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            border: none;
        }

        .info-card .card-header h5 {
            margin: 0;
            font-weight: 500;
        }

        .session-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .conflict-card .card-header {
            background: #dc3545;
            border-radius: 10px 10px 0 0;
        }

        .schedule-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            background: white;
        }

        .schedule-card .card-header {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            border: none;
        }

        .schedule-card .card-header h5 {
            margin: 0;
            font-weight: 500;
        }

        /* New Form Styles */
        .compensation-form {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            border: 1px solid #e9ecef;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        /* Button Styles */
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            background: #6c757d;
            transform: none;
            box-shadow: none;
            cursor: not-allowed;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 500;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            border: none;
            color: #212529;
            font-weight: 500;
        }

        /* Summary and Conflict Card Styles */
        .summary-card .card-header {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }

        .conflict-card .card-header {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: #212529;
        }

        .summary-info, .session-info {
            background: white;
            border-radius: 10px;
            padding: 20px;
        }

        .info-item {
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f1f3f4;
        }

        .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .info-row {
            display: flex;
            margin-bottom: 10px;
            align-items: flex-start;
        }

        .info-row strong {
            min-width: 120px;
            color: #495057;
        }

        /* Calendar Styles */
        .calendar-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #e9ecef;
        }

        .calendar-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            margin-bottom: 10px;
        }

        .day-header {
            padding: 10px;
            text-align: center;
            font-weight: 600;
            color: #495057;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }

        .schedule-card .card-body {
            background: white;
            border-radius: 0 0 10px 10px;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #ced4da;
            padding: 10px 15px;
        }

        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }

        .date-info {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 10px;
            border: 1px solid #e9ecef;
        }

        .room-availability-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            background: white;
        }

        .room-availability-card .card-header {
            background: linear-gradient(45deg, #fd7e14, #fd7e14);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            border: none;
        }

        .loading-spinner {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .availability-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
            color: #495057;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 2px solid #dee2e6;
        }

        .legend-color.available {
            background: #d4edda;
            border-color: #28a745;
        }

        .legend-color.occupied {
            background: #f8d7da;
            border-color: #dc3545;
        }

        .legend-color.selected {
            background: #cce5ff;
            border-color: #007bff;
        }

        .legend-color.holiday {
            background: #fff3cd;
            border-color: #ffc107;
        }

        .room-card.selected-room {
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }

        .room-header {
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 500;
            color: #495057;
            position: relative;
        }

        .room-header.available {
            background: linear-gradient(45deg, #d4edda, #c3e6cb);
        }

        .room-header.partial {
            background: linear-gradient(45deg, #fff3cd, #ffeaa7);
        }

        .room-header.occupied {
            background: linear-gradient(45deg, #f8d7da, #f5c6cb);
        }

        .room-header.holiday {
            background: linear-gradient(45deg, #e2e3e5, #d6d8db);
        }

        .room-selection-indicator {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .room-card.selected-room .room-selection-indicator {
            opacity: 1;
            color: #007bff;
        }

        .time-slots-container {
            padding: 15px;
            background: white;
        }

        .time-slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 8px;
        }

        .time-slot {
            border: 2px solid #e9ecef;
            border-radius: 6px;
            padding: 8px;
            text-align: center;
            font-size: 0.85em;
            transition: all 0.3s ease;
            background: white;
        }

        .time-slot.available {
            background: #d4edda;
            border-color: #28a745;
            cursor: pointer;
            color: #155724;
        }

        .time-slot.available:hover {
            background: #c3e6cb;
            transform: scale(1.05);
        }

        .time-slot.occupied {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
            cursor: not-allowed;
        }

        .time-slot.selected {
            background: #cce5ff;
            border-color: #007bff;
            color: #004085;
            font-weight: 600;
        }

        .room-stats {
            padding: 10px 15px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            font-size: 0.85em;
            color: #6c757d;
        }

        .room-stats strong {
            color: #495057;
        }

        .selected-slots-summary {
            background: #e7f3ff;
            border-radius: 6px;
            padding: 10px;
            margin-top: 10px;
        }

        .slot-badge {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            margin: 2px;
        }

        .btn-schedule {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-schedule:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            color: white;
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

        .error-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .error-state h5 {
            color: #495057;
            margin-bottom: 15px;
        }

        .btn-light {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            padding: 8px 16px;
            transition: all 0.3s ease;
        }

        .btn-light:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            border-color: rgba(255, 255, 255, 0.5);
        }

        .has-changes {
            color: #856404;
            background: #fff3cd;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ffeaa7;
        }

        .no-changes {
            color: #155724;
            background: #d4edda;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #c3e6cb;
        }

        .badge {
            font-size: 0.75em;
            padding: 0.35em 0.65em;
        }

        @media (max-width: 768px) {
            .time-slots-grid {
                grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
                gap: 6px;
            }
            
            .time-slot {
                padding: 6px;
                font-size: 0.8em;
            }
            
            .room-header {
                padding: 12px;
                font-size: 0.9em;
            }
            
            .availability-legend {
                gap: 10px;
                padding: 10px;
            }
            
            .legend-item {
                font-size: 0.8em;
            }
        }

        /* Modal สไตล์ */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .modal-header {
            border-radius: 15px 15px 0 0;
            border-bottom: none;
        }

        .modal-body {
            padding: 25px;
            line-height: 1.6;
        }

        .modal-footer {
            border-top: none;
            border-radius: 0 0 15px 15px;
        }

        /* แก้ไขปัญหาสีขาว */
        .card {
            background-color: white !important;
        }

        .card-body {
            background-color: white !important;
            color: #495057 !important;
        }

        .time-slots-container {
            background-color: white !important;
        }
        
        /* สไตล์สำหรับแสดงความพร้อมของห้อง */
        .room-availability-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .room-availability-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .room-card-header {
            padding: 12px 15px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            font-weight: 500;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .room-card-header.available {
            background: linear-gradient(45deg, #28a745, #20c997);
        }
        
        .room-card-header.partial {
            background: linear-gradient(45deg, #ffc107, #fd7e14);
        }
        
        .room-card-header.occupied {
            background: linear-gradient(45deg, #dc3545, #c82333);
        }
        
        .time-slots-availability {
            padding: 15px;
        }
        
        .time-slots-grid-availability {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 8px;
        }
        
        .time-slot-availability {
            padding: 8px 4px;
            text-align: center;
            border-radius: 6px;
            font-size: 0.8em;
            border: 2px solid;
            transition: all 0.3s ease;
        }
        
        .time-slot-availability.available {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
            cursor: pointer;
        }
        
        .time-slot-availability.available:hover {
            background: #c3e6cb;
            transform: scale(1.05);
        }
        
        .time-slot-availability.occupied {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
            cursor: not-allowed;
        }
        
        .room-availability-summary {
            padding: 10px 15px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            font-size: 0.85em;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .availability-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 500;
        }
        
        .availability-badge.available {
            background: #d4edda;
            color: #155724;
        }
        
        .availability-badge.partial {
            background: #fff3cd;
            color: #856404;
        }
        
        .availability-badge.occupied {
            background: #f8d7da;
            color: #721c24;
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

    <div class="container mt-4">
        <!-- Header -->
        <div class="header-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-calendar-plus"></i> จัดตารางสอนชดเชย</h2>
                    <p class="mb-0">เลือกวันเวลาสำหรับการสอนชดเชยด้วยตนเอง</p>
                </div>
                <div>
                    <button type="button" class="btn btn-light" onclick="goBack()">
                        <i class="fas fa-arrow-left"></i> กลับ
                    </button>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- ข้อมูลการชดเชย -->
            <div class="col-md-4">
                <div class="card info-card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> ข้อมูลการชดเชย</h5>
                    </div>
                    <div class="card-body">
                        <div class="session-info" id="compensationInfo">
                            <div class="text-center">
                                <i class="fas fa-spinner fa-spin fa-2x"></i>
                                <p class="mt-2">กำลังโหลดข้อมูล...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- แสดงความขัดแย้ง -->
                <div class="card conflict-card" id="conflictCard" style="display: none;">
                    <div class="card-header bg-danger text-white">
                        <h5><i class="fas fa-exclamation-triangle"></i> ความขัดแย้งของตาราง</h5>
                    </div>
                    <div class="card-body">
                        <div id="conflictInfo">
                            <!-- ข้อมูลความขัดแย้งจะแสดงที่นี่ -->
                        </div>
                    </div>
                </div>

                <!-- แสดงตารางที่เลือก -->
                <div class="card schedule-card" id="selectionSummary" style="display: none;">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-check-circle"></i> การเลือกของคุณ</h5>
                    </div>
                    <div class="card-body">
                        <div id="summaryContent">
                            <!-- จะแสดงสรุปการเลือกที่นี่ -->
                        </div>
                        <div class="d-grid gap-2 mt-3">
                            <button type="button" class="btn btn-schedule" onclick="confirmSchedule()">
                                <i class="fas fa-save"></i> บันทึกตารางสอนชดเชย
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="resetSelection()">
                                <i class="fas fa-undo"></i> เริ่มใหม่
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ฟอร์มเลือกวันเวลา -->
            <div class="col-lg-8">
                <!-- เลือกวันที่ -->
                <div class="card schedule-card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-alt"></i> เลือกวันที่สอนชดเชย</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="makeupDate" class="form-label">
                                    <i class="fas fa-calendar"></i> วันที่สอนชดเชย
                                </label>
                                <input type="date" class="form-control" id="makeupDate" onchange="loadRoomAvailability()" min="2025-06-11">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- แสดงความพร้อมของห้อง -->
                <div class="card room-availability-card" id="roomAvailabilityCard" style="display: none;">
                    <div class="card-header">
                        <h5><i class="fas fa-door-open"></i> ความพร้อมของห้องเรียน</h5>
                    </div>
                    <div class="card-body">
                        <!-- Loading -->
                        <div class="loading-spinner" id="loadingSpinner">
                            <i class="fas fa-spinner fa-spin fa-2x"></i>
                            <p class="mt-2">กำลังตรวจสอบความพร้อมของห้อง...</p>
                        </div>

                        <!-- ตัวอย่างสี -->
                        <div class="availability-legend" id="availabilityLegend" style="display: none;">
                            <div class="legend-item">
                                <div class="legend-color available"></div>
                                <span>ว่าง (คลิกได้)</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color occupied"></div>
                                <span>ไม่ว่าง</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color selected"></div>
                                <span>เลือกแล้ว</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color holiday"></div>
                                <span>วันหยุด</span>
                            </div>
                        </div>

                        <!-- รายการห้อง -->
                        <div id="roomsList">
                            <!-- จะแสดงรายการห้องที่นี่ -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ฟอร์มซ่อนสำหรับส่งข้อมูล -->
        <form id="scheduleForm" style="display: none;">
            <input type="hidden" id="cancellation_id" name="cancellation_id">
            <input type="hidden" id="academic_year_id" name="academic_year_id">
            <input type="hidden" id="makeup_date" name="makeup_date">
            <input type="hidden" id="makeup_classroom_id" name="makeup_classroom_id">
            <input type="hidden" id="makeup_start_time_slot_id" name="makeup_start_time_slot_id">
            <input type="hidden" id="makeup_end_time_slot_id" name="makeup_end_time_slot_id">
        </form>
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
                    <p id="successMessage">จัดตารางสอนชดเชยเรียบร้อยแล้ว</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="goBackAfterSuccess()">ตกลง</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-circle"></i> เกิดข้อผิดพลาด</h5>
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
    <script src="../js/core/jquery-3.7.1.min.js"></script>
    <script src="../js/core/popper.min.js"></script>
    <script src="../js/core/bootstrap.min.js"></script>
    <script src="../js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>
    <script src="../js/kaiadmin.min.js"></script>
    <script>
        // ตัวแปรสำหรับเก็บข้อมูล
        let compensationData = null;
        let classroomsData = [];
        let timeSlotsData = [];
        let availabilityData = null;
        let selectedRoom = null;
        let selectedTimeSlots = [];
        let selectedDate = '';
        
        const API_ENDPOINTS = '../api/api_compensation_management.php';
        
        // ฟังก์ชันแสดง/ซ่อน Loading
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        // ฟังก์ชันแสดง Modal
        function showSuccess(message) {
            document.getElementById('successMessage').innerHTML = message;
            new bootstrap.Modal(document.getElementById('successModal')).show();
        }
        
        function showError(message) {
            document.getElementById('errorMessage').innerHTML = message;
            new bootstrap.Modal(document.getElementById('errorModal')).show();
        }
        
        // ฟังก์ชันเรียก API - อัปเดตใหม่
        async function callAPI(action, data = {}) {
            console.log('🔄 กำลังเรียก API:', action, data);
            
            const formData = new FormData();
            formData.append('action', action);
            
            for (const [key, value] of Object.entries(data)) {
                if (value !== null && value !== undefined) {
                    formData.append(key, value);
                }
            }
            
            try {
                const response = await fetch(API_ENDPOINTS, {
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP ${response.status}: ${errorText}`);
                }

                const result = await response.json();
                console.log('✅ ได้รับข้อมูล:', result);

                if (!result.success) {
                    throw new Error(result.message || 'การเรียก API ไม่สำเร็จ');
                }

                return result;
            } catch (error) {
                console.error('🔥 Exception:', error);
                throw new Error('เกิดข้อผิดพลาด: ' + error.message);
            }
        }
        
        // ฟังก์ชันดึงข้อมูลการชดเชย - ปรับปรุงใหม่
        async function loadCompensationData() {
            const urlParams = new URLSearchParams(window.location.search);
            const cancellationId = urlParams.get('id') || urlParams.get('cancellation_id');
            const academicYearId = urlParams.get('academic_year_id') || urlParams.get('academic_year');
            
            console.log('URL Parameters:', {
                cancellationId,
                academicYearId,
                fullUrl: window.location.href
            });
            
            if (!cancellationId) {
                showError('ไม่พบรหัสการยกเลิกเรียน กรุณาตรวจสอบ URL');
                return;
            }
            
            try {
                showLoading();
                
                // ดึงข้อมูลการชดเชย
                const compensationResult = await callAPI('get_compensation_details', {
                    cancellation_id: cancellationId
                });
                
                if (compensationResult.success && compensationResult.data) {
                    compensationData = compensationResult.data;
                    displayCompensationInfo(compensationData);
                    populateForm(academicYearId);
                }
                
                // ดึงข้อมูลห้องเรียน
                const classroomsResult = await callAPI('get_classrooms');
                if (classroomsResult.success && classroomsResult.data) {
                    classroomsData = classroomsResult.data;
                }
                
                // ดึงข้อมูล time slots
                const timeSlotsResult = await callAPI('get_time_slots');
                if (timeSlotsResult.success && timeSlotsResult.data) {
                    timeSlotsData = timeSlotsResult.data;
                }
                
                hideLoading();
                
            } catch (error) {
                hideLoading();
                console.error('Error loading compensation data:', error);
                showError('เกิดข้อผิดพลาดในการโหลดข้อมูล: ' + error.message);
            }
        }
        
        // ฟังก์ชันจัดรูปแบบวันที่ - เพิ่มฟังก์ชันที่หายไป
        function formatThaiDate(dateString) {
            if (!dateString) return '';
            
            const date = new Date(dateString);
            const thaiMonths = [
                'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
                'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
            ];
            
            const thaiDays = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
            
            const day = date.getDate();
            const month = thaiMonths[date.getMonth()];
            const year = date.getFullYear() + 543;
            const dayName = thaiDays[date.getDay()];
            
            return `${dayName}ที่ ${day} ${month} ${year}`;
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
        
        function getThaiDayOfWeek(dateString) {
            const date = new Date(dateString);
            const thaiDays = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];
            return thaiDays[date.getDay()];
        }
        
        function getTimeSlotText(startSlot, endSlot) {
            if (!timeSlotsData || timeSlotsData.length === 0) {
                return `(คาบ ${startSlot}-${endSlot})`;
            }
            
            const startTimeData = timeSlotsData.find(t => t.slot_number == startSlot);
            const endTimeData = timeSlotsData.find(t => t.slot_number == endSlot);
            
            if (!startTimeData || !endTimeData) {
                return `(คาบ ${startSlot}-${endSlot})`;
            }
            
            const startTime = startTimeData.start_time.substring(0, 5);
            const endTime = endTimeData.end_time.substring(0, 5);
            
            return `(${startTime}-${endTime})`;
        }
        
        // ฟังก์ชันแสดงข้อมูลการชดเชย - ปรับปรุงให้ใช้ข้อมูลที่ถูกต้อง
        function displayCompensationInfo(data) {
            const compensationInfo = document.getElementById('compensationInfo');

            // สร้างข้อมูลชั้นปี
            let classYearDisplay = '';
            if (data.is_module_subject == 1 && data.group_name) {
                // กรณีวิชาโมดูล: แสดงชื่อกลุ่ม, โมดูล, และชั้นปีในกลุ่ม
                classYearDisplay = `<span>${data.group_name || '-'} ${data.module_name || '-'}</span>`;
                if (Array.isArray(data.year_levels_in_group) && data.year_levels_in_group.length > 0) {
                    classYearDisplay += `<br><span class="text-muted">ชั้นปีในกลุ่ม:</span><br>`;
                    classYearDisplay += data.year_levels_in_group.map(yl =>
                        `<span>${(yl.department || '-') + ' ' + (yl.class_year || '-') + ' ' + (yl.curriculum || '-')}</span>`
                    ).join('<br>');
                }
            } else {
                // กรณีวิชาปกติ: แสดง department, class_year, curriculum
                classYearDisplay = `
                    <span>${data.department || '-'}</span>
                    <span>${data.class_year || '-'}</span>
                    <span>${data.curriculum || '-'}</span>
                `;
            }

            compensationInfo.innerHTML = `
                <div class="mb-3">
                    <h6 class="text-primary mb-2"><i class="fas fa-book"></i> ข้อมูลรายวิชา</h6>
                    <p class="mb-1"><strong>รหัสวิชา:</strong> ${data.subject_code || 'ไม่ระบุ'}</p>
                    <p class="mb-1"><strong>ชื่อวิชา:</strong> ${data.subject_name || 'ไม่ระบุ'}</p>
                    <p class="mb-1"><strong>อาจารย์:</strong> ${data.teacher_name || 'ไม่ระบุ'}</p>
                    <p class="mb-1"><strong>ชั้นปี:</strong> ${classYearDisplay}</p>
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
        }
        
        // ฟังก์ชันเติมข้อมูลในฟอร์ม
        function populateForm(academicYearId = null) {
            const urlParams = new URLSearchParams(window.location.search);
            
            document.getElementById('cancellation_id').value = urlParams.get('id') || urlParams.get('cancellation_id') || '';
            
            // ลองหา academic_year_id จากหลายแหล่ง
            const academicYear = academicYearId || 
                                urlParams.get('academic_year_id') || 
                                urlParams.get('academic_year') ||
                                (compensationData && compensationData.academic_year_id) ||
                                '1'; // ค่าเริ่มต้น
            
            document.getElementById('academic_year_id').value = academicYear;
            
            console.log('Form populated with:', {
                cancellation_id: document.getElementById('cancellation_id').value,
                academic_year_id: document.getElementById('academic_year_id').value
            });
            
            if (compensationData && compensationData.cancellation_date) {
                // ถ้าต้องการสอนหลังจากวันที่ยกเลิก (เดิม)
                const defaultDate = new Date(compensationData.cancellation_date);
                defaultDate.setDate(defaultDate.getDate() + 7);
                
                // แต่ถ้าวันที่เดิมผ่านไปแล้ว ให้ใช้วันพรุ่งนี้แทน
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                
                const finalDate = defaultDate > tomorrow ? defaultDate : tomorrow;
                document.getElementById('makeupDate').value = finalDate.toISOString().split('T')[0];
            }
        }

        // ฟังก์ชัน resetSelection - เพิ่มฟังก์ชันที่หายไป
        function resetSelection() {
            clearSelection();
            
            // รีเซ็ต date input
            document.getElementById('makeupDate').value = '';
            
            // ซ่อนการ์ดต่างๆ
            document.getElementById('roomAvailabilityCard').style.display = 'none';
            document.getElementById('selectionSummary').style.display = 'none';
            document.getElementById('conflictCard').style.display = 'none';
            
            // รีเซ็ต date info
            document.getElementById('dateInfo').innerHTML = '<small class="text-muted">กรุณาเลือกวันที่สอนชดเชย</small>';
        }
        
        // ฟังก์ชันล้างการเลือก
        function clearSelection() {
            selectedRoom = null;
            selectedTimeSlots = [];
            
            // ลบการเลือกในหน้าจอ
            document.querySelectorAll('.room-card.selected-room').forEach(card => {
                card.classList.remove('selected-room');
            });
            
            document.querySelectorAll('.time-slot.selected, .time-slot-availability.selected').forEach(slot => {
                slot.classList.remove('selected');
            });
            
            // ล้างค่าในฟอร์ม
            document.getElementById('makeup_classroom_id').value = '';
            document.getElementById('makeup_start_time_slot_id').value = '';
            document.getElementById('makeup_end_time_slot_id').value = '';
            
            // ซ่อนสรุปการเลือก
            document.getElementById('selectionSummary').style.display = 'none';
        }

        // ฟังก์ชันล้างการเลือกห้องเรียน - เพิ่มฟังก์ชันที่หายไป
        function clearRoomSelection() {
            // ลบการเลือกทุก slot ในห้องที่เลือกอยู่
            document.querySelectorAll('.time-slot-availability.selected').forEach(slot => {
                slot.classList.remove('selected');
            });
            
            // ลบการเลือกการ์ดห้อง
            document.querySelectorAll('.room-card.selected-room').forEach(card => {
                card.classList.remove('selected-room');
            });
            
            // รีเซ็ตตัวแปร
            selectedRoom = null;
            selectedTimeSlots = [];
            
            // อัปเดตฟอร์ม
            updateFormValues();
        }

        // ฟังก์ชันโหลดความพร้อมของห้องเรียน
        async function loadRoomAvailability() {
            const selectedDate = document.getElementById('makeupDate').value;
            const cancellationId = document.getElementById('cancellation_id').value;
            console.log('loadRoomAvailability', {selectedDate, cancellationId});
            if (!selectedDate) {
                document.getElementById('roomAvailabilityCard').style.display = 'none';
                return;
            }
            
            try {
                // แสดงการ์ดและ loading
                document.getElementById('roomAvailabilityCard').style.display = 'block';
                document.getElementById('loadingSpinner').style.display = 'block';
                document.getElementById('availabilityLegend').style.display = 'none';
                document.getElementById('roomsList').innerHTML = '';
                
                // เรียก API ดึงข้อมูลความพร้อมของห้อง
                const result = await callAPI('get_detailed_room_availability', {
                    date: selectedDate,
                    cancellation_id: document.getElementById('cancellation_id').value
                });
                console.log('API result:', result);
                if (result.success && result.data) {
                    displayRoomAvailability(result.data);
                } else {
                    throw new Error(result.message || 'ไม่สามารถโหลดข้อมูลความพร้อมของห้องได้');
                }
                
            } catch (error) {
                console.error('Error loading room availability:', error);
                document.getElementById('loadingSpinner').style.display = 'none';
                document.getElementById('roomsList').innerHTML = `
                    <div class="error-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h5>เกิดข้อผิดพลาด</h5>
                        <p>${error.message}</p>
                        <button class="btn btn-outline-primary btn-sm" onclick="loadRoomAvailability()">
                            <i class="fas fa-redo"></i> ลองใหม่
                        </button>
                    </div>
                `;
            }
        }
        
        // ฟังก์ชันแสดงความพร้อมของห้องเรียน
        function displayRoomAvailability(roomsData) {
            console.log('displayRoomAvailability', roomsData);
            document.getElementById('loadingSpinner').style.display = 'none';
            document.getElementById('availabilityLegend').style.display = 'flex';
            const roomsList = document.getElementById('roomsList');
            
            if (!roomsData || roomsData.length === 0) {
                roomsList.innerHTML = `
                    <div class="error-state">
                        <i class="fas fa-building"></i>
                        <h5>ไม่พบข้อมูลห้องเรียน</h5>
                        <p>ไม่มีข้อมูลห้องเรียนสำหรับวันที่นี้</p>
                    </div>
                `;
                return;
            }
            
            roomsList.innerHTML = roomsData.map(roomData => {
                const classroom = roomData.classroom;
                const availabilityStatus = roomData.availability_status;
                const availableSlots = roomData.available_slots || [];
                const occupiedSlots = roomData.occupied_slots || [];
                
                // กำหนดสีและไอคอนตามสถานะ
                let statusClass = 'available';
                let statusIcon = 'fas fa-check-circle';
                let statusText = 'ว่าง';
                
                if (roomData.holiday) {
                    statusClass = 'holiday';
                    statusIcon = 'fas fa-calendar-times';
                    statusText = `วันหยุด: ${roomData.holiday.holiday_name}`;
                } else {
                    switch (availabilityStatus) {
                        case 'occupied':
                            statusClass = 'occupied';
                            statusIcon = 'fas fa-times-circle';
                            statusText = 'ไม่ว่าง';
                            break;
                        case 'partially_available':
                            statusClass = 'partial';
                            statusIcon = 'fas fa-exclamation-circle';
                            statusText = 'ว่างบางช่วง';
                            break;
                        default:
                            statusClass = 'available';
                            statusIcon = 'fas fa-check-circle';
                            statusText = 'ว่าง';
                    }
                }
                
                return `
                    <div class="room-availability-card">
                        <div class="room-card-header ${statusClass}">
                            <div>
                                <strong><i class="fas fa-door-open"></i> ${classroom.room_number}</strong>
                                <span class="badge bg-light text-dark ms-2">${classroom.building}</span>
                            </div>
                            <div>
                                <i class="${statusIcon}"></i>
                                <span>${statusText}</span>
                            </div>
                        </div>
                        
                        ${!roomData.holiday ? `
                            <div class="time-slots-availability">
                                <div class="time-slots-grid-availability">
                                    ${generateTimeSlots(availableSlots, occupiedSlots, classroom.classroom_id)}
                                </div>
                            </div>
                            
                            <div class="room-availability-summary">
                                <span>
                                    <strong>ช่วงเวลาว่าง:</strong> ${availableSlots.length} / ${availableSlots.length + occupiedSlots.length}
                                </span>
                                <div>
                                    ${availableSlots.length > 0 ? 
                                        `<span class="availability-badge available">มีช่วงว่าง</span>` : 
                                        `<span class="availability-badge occupied">เต็ม</span>`
                                    }
                                </div>
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('');
        }
        
        // ฟังก์ชันสร้าง time slots สำหรับห้องเรียน
        function generateTimeSlots(availableSlots, occupiedSlots, classroomId) {
            const allSlots = [...availableSlots, ...occupiedSlots].sort((a, b) => a.slot_number - b.slot_number);
            
            return allSlots.map(slot => {
                const isAvailable = availableSlots.some(s => s.time_slot_id === slot.time_slot_id);
                const slotClass = isAvailable ? 'available' : 'occupied';
                const conflictInfo = slot.conflicts && slot.conflicts.length > 0 ? 
                    `title="${slot.conflicts.join(', ')}"` : '';
                
                return `
                    <div class="time-slot-availability ${slotClass}" 
                         ${conflictInfo}
                         ${isAvailable ? `onclick="selectTimeSlot(${slot.time_slot_id}, ${classroomId}, '${slot.start_time}', '${slot.end_time}')" ` : ''}>
                        ${slot.slot_number}<br>
                        <small>${slot.start_time.substring(0, 5)}</small>
                    </div>
                `;
            }).join('');
        }
        
        // ฟังก์ชันเลือก time slot - ปรับปรุงให้รองรับการเลือกช่วง
        function selectTimeSlot(timeSlotId, classroomId, startTime, endTime) {
            const slotElement = event.target;
            const slotNumber = parseInt(slotElement.textContent.split('\n')[0]);
            
            // ตรวจสอบว่า slot นี้เลือกอยู่แล้วหรือไม่
            const isCurrentlySelected = slotElement.classList.contains('selected');
            
            if (isCurrentlySelected) {
                // ถ้าเลือกอยู่แล้ว ให้ยกเลิกการเลือก
                removeSlotFromSelection(timeSlotId, slotElement);
            } else {
                // ถ้ายังไม่เลือก ให้เพิ่มเข้าการเลือก
                addSlotToSelection(timeSlotId, classroomId, slotNumber, slotElement);
            }
            
            // อัปเดตการแสดงผลและสรุป
            updateSlotRangeDisplay();
            updateSelectionSummary();
        }
        
        // ฟังก์ชันเพิ่ม slot เข้าการเลือก
        function addSlotToSelection(timeSlotId, classroomId, slotNumber, slotElement) {
            // ตรวจสอบว่าเป็นห้องเดียวกันหรือไม่
            if (selectedRoom && selectedRoom !== classroomId) {
                if (!confirm('คุณต้องการเปลี่ยนห้องเรียนหรือไม่? การเลือกก่อนหน้านี้จะถูกล้าง')) {
                    return;
                }
                clearRoomSelection();
            }
            
            // ตั้งค่าห้องที่เลือก
            selectedRoom = classroomId;
            
            // เพิ่ม slot เข้าอาเรย์
            if (!selectedTimeSlots.includes(timeSlotId)) {
                selectedTimeSlots.push(timeSlotId);
                selectedTimeSlots.sort((a, b) => a - b); // เรียงลำดับ
            }
            
            // เพิ่มคลาส selected
            slotElement.classList.add('selected');
            
            // อัปเดตฟอร์ม
            updateFormValues();
        }
        
        // ฟังก์ชันล้างการเลือกห้องเรียน - เพิ่มฟังก์ชันที่หายไป
        function clearRoomSelection() {
            // ลบการเลือกทุก slot ในห้องที่เลือกอยู่
            document.querySelectorAll('.time-slot-availability.selected').forEach(slot => {
                slot.classList.remove('selected');
            });
            
            // ลบการเลือกการ์ดห้อง
            document.querySelectorAll('.room-card.selected-room').forEach(card => {
                card.classList.remove('selected-room');
            });
            
            // รีเซ็ตตัวแปร
            selectedRoom = null;
            selectedTimeSlots = [];
            
            // อัปเดตฟอร์ม
            updateFormValues();
        }

        // ฟังก์ชันลบ slot จากการเลือก
        function removeSlotFromSelection(timeSlotId, slotElement) {
            // ลบจากอาเรย์
            const index = selectedTimeSlots.indexOf(timeSlotId);
            if (index > -1) {
                selectedTimeSlots.splice(index, 1);
            }
            
            // ลบคลาส selected
            slotElement.classList.remove('selected');
            
            // ถ้าไม่มี slot ใดเลือกแล้ว ให้ล้างห้อง
            if (selectedTimeSlots.length === 0) {
                selectedRoom = null;
            }
            
            // อัปเดตฟอร์ม
            updateFormValues();
        }
        
        // ฟังก์ชันอัปเดตการแสดงผลช่วงเวลา
        function updateSlotRangeDisplay() {
            if (selectedTimeSlots.length === 0) return;
            
            // หา slots ที่เป็นช่วงติดต่อกัน
            const ranges = findConsecutiveRanges(selectedTimeSlots);
            
            // อัปเดตการแสดงผลแต่ละ slot
            document.querySelectorAll('.time-slot-availability').forEach(slot => {
                if (slot.classList.contains('selected')) {
                    const slotNumber = parseInt(slot.textContent.split('\n')[0]);
                    const rangeInfo = getRangeInfo(slotNumber, ranges);
                    
                    // เพิ่มข้อมูลช่วงเวลา
                    if (rangeInfo) {
                        slot.setAttribute('data-range', rangeInfo);
                        slot.title = `ช่วงที่เลือก: ${rangeInfo}`;
                    }
                }
            });
        }
        
        // ฟังก์ชันหาช่วงติดต่อกัน
        function findConsecutiveRanges(slots) {
            if (slots.length === 0) return [];
            
            const sortedSlots = [...slots].sort((a, b) => a - b);
            const ranges = [];
            let currentRange = { start: sortedSlots[0], end: sortedSlots[0] };
            
            for (let i = 1; i < sortedSlots.length; i++) {
                if (sortedSlots[i] === currentRange.end + 1) {
                    // ติดต่อกัน
                    currentRange.end = sortedSlots[i];
                } else {
                    // ไม่ติดต่อกัน เริ่มช่วงใหม่
                    ranges.push({ ...currentRange });
                    currentRange = { start: sortedSlots[i], end: sortedSlots[i] };
                }
            }
            ranges.push(currentRange);
            
            return ranges;
        }
        
        // ฟังก์ชันหาข้อมูลช่วงของ slot
        function getRangeInfo(slotNumber, ranges) {
            for (const range of ranges) {
                if (slotNumber >= range.start && slotNumber <= range.end) {
                    if (range.start === range.end) {
                        return `คาบ ${range.start}`;
                    } else {
                        return `คาบ ${range.start}-${range.end}`;
                    }
                }
            }
            return null;
        }
        
        // ฟังก์ชันอัปเดตค่าในฟอร์ม
        function updateFormValues() {
            if (selectedTimeSlots.length === 0) {
                document.getElementById('makeup_classroom_id').value = '';
                document.getElementById('makeup_start_time_slot_id').value = '';
                document.getElementById('makeup_end_time_slot_id').value = '';
                return;
            }
            
            const sortedSlots = [...selectedTimeSlots].sort((a, b) => a - b);
            
            document.getElementById('makeup_classroom_id').value = selectedRoom || '';
            document.getElementById('makeup_start_time_slot_id').value = sortedSlots[0];
            document.getElementById('makeup_end_time_slot_id').value = sortedSlots[sortedSlots.length - 1];
        }
        
        // ฟังก์ชันอัปเดตสรุปการเลือก - ปรับปรุงให้แสดงช่วงเวลา
        function updateSelectionSummary() {
            const summaryCard = document.getElementById('selectionSummary');
            const summaryContent = document.getElementById('summaryContent');
            
            if (!selectedRoom || selectedTimeSlots.length === 0) {
                summaryCard.style.display = 'none';
                return;
            }
            
            // หาข้อมูลห้องที่เลือกจากข้อมูลที่โหลดมา
            let roomNumber = `ห้อง ${selectedRoom}`;
            
            // ค้นหาข้อมูลห้องจาก classroomsData
            if (classroomsData && classroomsData.length > 0) {
                const roomData = classroomsData.find(room => room.classroom_id == selectedRoom);
                if (roomData) {
                    roomNumber = `${roomData.room_number}`;
                }
            }
            
            // สร้างข้อความแสดงช่วงเวลา
            const ranges = findConsecutiveRanges(selectedTimeSlots);
            const timeRangesText = ranges.map(range => {
                if (range.start === range.end) {
                    return `คาบ ${range.start}`;
                } else {
                    return `คาบ ${range.start}-${range.end}`;
                }
            }).join(', ');
            
            // หาเวลาจริงจาก time slots ที่เลือก
            const sortedSlots = [...selectedTimeSlots].sort((a, b) => a - b);
            let actualTimeText = timeRangesText;
            
            // ถ้ามีข้อมูล timeSlotsData ให้แสดงเวลาจริง
            if (timeSlotsData && timeSlotsData.length > 0) {
                const startTimeData = timeSlotsData.find(t => t.time_slot_id == sortedSlots[0]);
                const endTimeData = timeSlotsData.find(t => t.time_slot_id == sortedSlots[sortedSlots.length - 1]);
                
                if (startTimeData && endTimeData) {
                    const startTime = startTimeData.start_time.substring(0, 5);
                    const endTime = endTimeData.end_time.substring(0, 5);
                    actualTimeText = `${timeRangesText} (${startTime}-${endTime})`;
                }
            }
            
            summaryContent.innerHTML = `
                <div class="selected-slots-summary">
                    <h6><i class="fas fa-check-circle text-success"></i> การเลือกของคุณ</h6>
                    <div class="mb-2">
                        <strong>ห้องเรียน:</strong> ${roomNumber}
                    </div>
                    <div class="mb-2">
                        <strong>เวลา:</strong> ${actualTimeText}
                    </div>
                    <div class="mb-2">
                        <strong>วันที่:</strong> ${formatThaiDate(document.getElementById('makeupDate').value)}
                    </div>
                </div>
            `;
            
            summaryCard.style.display = 'block';
        }

        // ฟังก์ชันยืนยันการจัดตาราง
        async function confirmSchedule() {
            if (!selectedRoom || selectedTimeSlots.length === 0) {
                showError('กรุณาเลือกห้องเรียนและเวลาที่ต้องการ');
                return;
            }
            
            if (!confirm('ยืนยันการจัดตารางสอนชดเชยนี้หรือไม่?')) {
                return;
            }
            
            try {
                showLoading();
                
                const result = await callAPI('confirm_manual_schedule', {
                    cancellation_id: document.getElementById('cancellation_id').value,
                    academic_year_id: document.getElementById('academic_year_id').value,
                    makeup_date: document.getElementById('makeupDate').value,
                    makeup_classroom_id: selectedRoom,
                    makeup_start_time_slot_id: selectedTimeSlots[0],
                    makeup_end_time_slot_id: selectedTimeSlots[selectedTimeSlots.length - 1],
                    require_approval: false
                });
                
                hideLoading();
                
                if (result.success) {
                    showSuccess('จัดตารางสอนชดเชยเสร็จสิ้น\n\n' + (result.message || ''));
                    
                    // กลับไปหน้าเดิมหลังจาก 3 วินาที
                    setTimeout(() => {
                        goBackAfterSuccess();
                    }, 3000);
                } else {
                    throw new Error(result.message || 'ไม่สามารถจัดตารางได้');
                }
                
            } catch (error) {
                hideLoading();
                console.error('Error confirming schedule:', error);
                showError('เกิดข้อผิดพลาดในการจัดตาราง:\n\n' + error.message);
            }
        }

        // ฟังก์ชันกลับไปหน้าเดิม
        function goBack() {
            if (selectedTimeSlots.length > 0) {
                if (confirm('คุณมีการเลือกเวลาที่ยังไม่ได้บันทึก ต้องการออกหรือไม่?')) {
                    window.history.back();
                }
            } else {
                window.history.back();
            }
        }
        
        // ฟังก์ชันกลับไปหน้าเดิมหลังสำเร็จ
        function goBackAfterSuccess() {
            window.location.href = 'compensation.php';
        }
        
        // เริ่มต้นเมื่อโหลดหน้า
        document.addEventListener('DOMContentLoaded', function() {
            // ตั้งค่าวันที่ขั้นต่ำเป็นวันพรุ่งนี้
            const today = new Date();
            today.setDate(today.getDate() + 1);
            document.getElementById('makeupDate').min = today.toISOString().split('T')[0];
            
            // โหลดข้อมูลเริ่มต้น
            loadCompensationData(); 
            setMakeupDateRange();
        });
        async function setMakeupDateRange() {
    const academicYearId = document.getElementById('academic_year_id').value || '1';
    try {
        // เรียก API ดึงช่วงวันที่ของปีการศึกษา
        const result = await callAPI('get_academic_year_range', { academic_year_id: academicYearId });
        if (result.success && result.data) {
            const { start_date, end_date } = result.data;
            const makeupDateInput = document.getElementById('makeupDate');
            makeupDateInput.min = start_date;
            makeupDateInput.max = end_date;
        }
    } catch (error) {
        console.warn('ไม่สามารถตั้งช่วงวันที่:', error);
    }
}
    </script>
</body>
</html>