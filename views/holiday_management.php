<?php
require_once '../api/auth_check.php';
requireAdmin(); // เปลี่ยนจาก requireLogin เป็น requireAdmin

$userData = getUserData();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>จัดการปีการศึกษาและวันหยุด - Teaching Schedule Management System</title>
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
        
        .academic-year-card {
            border-left: 4px solid #28a745;
            transition: all 0.3s ease;
        }
        
        .holiday-management-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        
        .api-holidays-card {
            border-left: 4px solid #6f42c1;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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

        .academic-year-item {
            transition: all 0.3s ease;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .academic-year-item:hover {
            background-color: #f8f9fa;
            transform: translateX(2px);
        }

        .academic-year-item.current {
            background-color: #e3f2fd;
            border: 1px solid #2196f3;
        }

        .holiday-row {
            transition: all 0.2s ease;
        }

        .holiday-row:hover {
            background-color: #f8f9fa;
            transform: translateX(2px);
        }

        .date-display {
            text-align: center;
            font-size: 0.9em;
        }

        .holiday-title {
            font-weight: 500;
            color: #2c3e50;
        }

        .holiday-source-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 0.7em;
        }

        .custom-holiday {
            border-left: 3px solid #28a745;
            position: relative;
        }

        .api-holiday {
            border-left: 3px solid #6f42c1;
            position: relative;
        }

        .new-item {
            animation: highlightNew 2s ease-in-out;
        }

        @keyframes highlightNew {
            0% { background-color: #d4edda; }
            100% { background-color: transparent; }
        }

        .api-status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .api-status-indicator.online {
            background-color: #28a745;
            box-shadow: 0 0 5px rgba(40, 167, 69, 0.5);
        }

        .api-status-indicator.offline {
            background-color: #dc3545;
            box-shadow: 0 0 5px rgba(220, 53, 69, 0.5);
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

        .holiday-filters {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .filter-btn {
            margin: 2px;
            font-size: 0.8em;
            padding: 4px 8px;
        }

        .filter-btn.active {
            background-color: #007bff;
            color: white;
        }

        .holiday-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .tab-content-area {
            min-height: 400px;
        }

        .holiday-type-badge {
            font-size: 0.7em;
            padding: 2px 6px;
            border-radius: 10px;
        }

        .holiday-type-national { background-color: #dc3545; }
        .holiday-type-religious { background-color: #fd7e14; }
        .holiday-type-royal { background-color: #6f42c1; }
        .holiday-type-substitute { background-color: #6c757d; }
        .holiday-type-custom { background-color: #28a745; }

        @media (max-width: 768px) {
            .stats-card {
                padding: 15px;
            }
            
            .stats-card h2 {
                font-size: 1.5rem;
            }
            
            .date-display {
                font-size: 0.8em;
            }
            
            .holiday-title {
                font-size: 0.9em;
                line-height: 1.2;
            }
        }
        
        .bg-purple {
            background-color: #6f42c1 !important;
        }
        
        .btn-purple {
            background-color: #6f42c1;
            border-color: #6f42c1;
            color: white;
        }
        
        .btn-purple:hover {
            background-color: #5a2d91;
            border-color: #5a2d91;
            color: white;
        }
        
        .btn-outline-purple {
            color: #6f42c1;
            border-color: #6f42c1;
        }
        
        .btn-outline-purple:hover {
            background-color: #6f42c1;
            border-color: #6f42c1;
            color: white;
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
        <!-- Include Sidebar and Header -->
            <?php include '../includes/sidebar.php'; ?>

        <div class="main-panel">
            <!-- Include Header -->
            <?php include '../includes/header.php'; ?>

            <div class="container">
                <div class="page-inner">

                    <!-- Header Statistics -->
                    <div class="row">
                        <div class="col-12">
                            <div class="stats-card">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h2><i class="fas fa-graduation-cap"></i> จัดการปีการศึกษาและวันหยุด</h2>
                                        <p class="mb-1">จัดการข้อมูลปีการศึกษา การตั้งค่าเทอม และการจัดการวันหยุดจาก Calendarific API</p>
                                        <small>
                                            <span class="api-status-indicator" id="apiStatus"></span>
                                            สถานะ API: <span id="apiStatusText">กำลังตรวจสอบ...</span>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- จัดการปีการศึกษา -->
                        <div class="col-md-4">
                            <div class="card academic-year-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5><i class="fas fa-graduation-cap"></i> จัดการปีการศึกษา</h5>
                                    <span class="badge bg-success" id="academicYearsBadge">0</span>
                                </div>
                                <div class="card-body">
                                    <!-- ฟอร์มเพิ่มปีการศึกษา -->
                                <div class="academic-year-form">
                                    <h6><i class="fas fa-plus-circle"></i> เพิ่มปีการศึกษาใหม่</h6>
                                    <form id="academicYearForm">
                                        <div class="row">
                                            <div class="col-6">
                                                <label class="form-label">ปีการศึกษา</label>
                                                <input type="number" class="form-control form-control-sm" id="academicYear" required min="2560" max="2580">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label">เทอม</label>
                                                <select class="form-control form-control-sm" id="semester" required>
                                                    <option value="">เลือกเทอม</option>
                                                    <option value="1">เทอม 1</option>
                                                    <option value="2">เทอม 2</option>
                                                    <option value="3">เทอม 3</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-6">
                                                <label class="form-label">วันที่เริ่มต้น</label>
                                                <input type="date" class="form-control form-control-sm" id="startDate" required>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label">วันที่สิ้นสุด</label>
                                                <input type="date" class="form-control form-control-sm" id="endDate" required>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="setAsCurrent">
                                                <label class="form-check-label" for="setAsCurrent">ตั้งเป็นปีการศึกษาปัจจุบัน</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="setAsActive" checked>
                                                <label class="form-check-label" for="setAsActive">เปิดใช้งาน</label>
                                            </div>
                                        </div>
                                        <div class="mt-3 d-grid">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="fas fa-plus"></i> เพิ่มปีการศึกษา
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                    <!-- รายการปีการศึกษา -->
                                    <div class="mt-3">
                                        <h6><i class="fas fa-list"></i> ปีการศึกษาที่มีอยู่</h6>
                                        <div id="academicYearsContainer" style="max-height: 300px; overflow-y: auto;">
                                            <div class="text-center text-muted py-3">
                                                <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div><br>
                                                <small>กำลังโหลด...</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- จัดการวันหยุดเพิ่มเติม -->
                        <div class="col-md-4">
                            <div class="card holiday-management-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5><i class="fas fa-calendar-plus"></i> วันหยุดเพิ่มเติม</h5>
                                    <span class="badge btn-primary" id="manualHolidays">0</span>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">เลือกปีการศึกษา</label>
                                        <select class="form-control form-control-sm" id="holidayAcademicYearSelect">
                                            <option value="">เลือกปีการศึกษา...</option>
                                        </select>
                                    </div>

                                    <!-- ฟอร์มเพิ่มวันหยุด -->
                                    <div class="form-section">
                                        <h6><i class="fas fa-plus-circle"></i> เพิ่มวันหยุดใหม่</h6>
                                        <form id="addHolidayForm">
                                            <div class="mb-3">
                                                <label class="form-label">วันที่หยุด</label>
                                                <input type="date" class="form-control form-control-sm" id="holidayDate" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">ชื่อวันหยุด (ภาษาไทย)</label>
                                                <input type="text" class="form-control form-control-sm" id="holidayName" required placeholder="เช่น วันแรงงานแห่งชาติ">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">ชื่อภาษาอังกฤษ</label>
                                                <input type="text" class="form-control form-control-sm" id="holidayNameEn" placeholder="เช่น Labor Day">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">ประเภทวันหยุด</label>
                                                <select class="form-control form-control-sm" id="holidayType" required>
                                                    <option value="">เลือกประเภท</option>
                                                    <option value="national">วันหยุดชาติ</option>
                                                    <option value="religious">วันหยุดศาสนา</option>
                                                    <option value="royal">วันหยุดราชวงศ์</option>
                                                    <option value="substitute">วันหยุดชดเชย</option>
                                                    <option value="custom">กำหนดเอง</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">หมายเหตุ</label>
                                                <textarea class="form-control form-control-sm" id="holidayNotes" rows="2" placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)"></textarea>
                                            </div>
                                            <div class="d-grid">
                                                <button type="submit" class="btn btn-primary btn-sm" id="addHolidayBtn" disabled>
                                                    <i class="fas fa-plus"></i> เพิ่มวันหยุด
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- วันหยุดจาก API -->
                        <div class="col-md-4">
                            <div class="card api-holidays-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5><i class="fas fa-cloud-download-alt"></i> วันหยุดจาก API</h5>
                                    <span class="badge bg-purple" id="apiHolidaysCount">0</span>
                                </div>
                                <div class="holiday-summary" id="holidaySummary">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <h6 class="mb-1" id="nationalHolidaysCount">0</h6>
                                            <small>วันหยุดชาติ</small>
                                        </div>
                                        <div class="col-4">
                                            <h6 class="mb-1" id="religiousHolidaysCount">0</h6>
                                            <small>วันหยุดศาสนา</small>
                                        </div>
                                        <div class="col-4">
                                            <h6 class="mb-1" id="royalHolidaysCount">0</h6>
                                            <small>วันหยุดราชวงศ์</small>
                                        </div>
                                    </div>
                                </div>

                                    <div class="text-center mb-3">
                                        <button class="btn btn-primary btn-sm" onclick="fetchHolidaysFromAPI()" id="fetchApiMainBtn">
                                            <i class="fas fa-download"></i> ดึงวันหยุดจาก API
                                        </button>
                                        <button class="btn btn-outline-info btn-sm ms-1" onclick="viewAllApiHolidays()">
                                            <i class="fas fa-eye"></i> ดูทั้งหมด
                                        </button>
                                    </div>

                                    <div class="api-holidays-preview" style="max-height: 200px; overflow-y: auto;">
                                        <small class="text-muted d-block mb-2">วันหยุดที่กำลังมาถึง:</small>
                                        <div id="upcomingHolidays">
                                            <div class="text-center text-muted py-3">
                                                <i class="fas fa-calendar-times fa-lg mb-2"></i><br>
                                                <small>เลือกปีการศึกษาเพื่อดูวันหยุด</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ตารางวันหยุดแบบรวม -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5><i class="fas fa-calendar-alt"></i> รายการวันหยุดทั้งหมด</h5>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Holiday Filters -->
                                    <div class="holiday-filters">
                                        <small class="text-muted d-block mb-2">กรองตามประเภท:</small>
                                        <button class="btn btn-outline-primary filter-btn active" data-filter="all">
                                            ทั้งหมด (<span id="allHolidaysCount">0</span>)
                                        </button>
                                        <button class="btn btn-outline-success filter-btn" data-filter="custom">
                                            เพิ่มเอง (<span id="customHolidaysFilterCount">0</span>)
                                        </button>
                                        <button class="btn btn-outline-purple filter-btn" data-filter="api">
                                            จาก API (<span id="apiHolidaysFilterCount">0</span>)
                                        </button>
                                        <button class="btn btn-outline-danger filter-btn" data-filter="national">
                                            วันหยุดชาติ (<span id="nationalFilterCount">0</span>)
                                        </button>
                                        <button class="btn btn-outline-warning filter-btn" data-filter="religious">
                                            วันหยุดศาสนา (<span id="religiousFilterCount">0</span>)
                                        </button>
                                        <button class="btn btn-outline-info filter-btn" data-filter="royal">
                                            วันหยุดราชวงศ์ (<span id="royalFilterCount">0</span>)
                                        </button>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="10%">วันที่</th>
                                                    <th width="30%">ชื่อวันหยุด</th>
                                                    <th width="15%">ประเภท</th>
                                                    <th width="15%">แหล่งที่มา</th>
                                                    <th width="10%">ปีการศึกษา</th>
                                                    <th width="20%">การจัดการ</th>
                                                </tr>
                                            </thead>
                                            <tbody id="allHolidaysTableBody">
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-3">
                                                        <i class="fas fa-spinner fa-spin fa-lg mb-2"></i><br>
                                                        กำลังโหลดข้อมูลวันหยุด...
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

            <!-- Include Footer -->
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <!-- View All API Holidays Modal -->
    <div class="modal fade" id="viewApiHolidaysModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-purple text-white">
                    <h5 class="modal-title"><i class="fas fa-cloud-download-alt"></i> วันหยุดจาก Calendarific API</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" id="apiHolidaySearch" class="form-control" placeholder="ค้นหาวันหยุดจาก API...">
                    </div>
                    
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-hover">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th width="20%">วันที่</th>
                                    <th width="40%">ชื่อวันหยุด</th>
                                    <th width="20%">ประเภท</th>
                                    <th width="20%">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody id="apiHolidaysModalBody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">
                                        <i class="fas fa-spinner fa-spin fa-lg mb-2"></i><br>
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

    <!-- Edit Holiday Modal -->
    <div class="modal fade" id="editHolidayModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> แก้ไขวันหยุด</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editHolidayForm">
                        <input type="hidden" id="editHolidayId">
                        <div class="mb-3">
                            <label class="form-label">วันที่หยุด</label>
                            <input type="date" class="form-control" id="editHolidayDate" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ชื่อวันหยุด (ภาษาไทย)</label>
                            <input type="text" class="form-control" id="editHolidayName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ชื่อภาษาอังกฤษ</label>
                            <input type="text" class="form-control" id="editHolidayNameEn">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ประเภทวันหยุด</label>
                            <select class="form-control" id="editHolidayType" required>
                                <option value="national">วันหยุดชาติ</option>
                                <option value="religious">วันหยุดศาสนา</option>
                                <option value="royal">วันหยุดราชวงศ์</option>
                                <option value="substitute">วันหยุดชดเชย</option>
                                <option value="custom">กำหนดเอง</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">หมายเหตุ</label>
                            <textarea class="form-control" id="editHolidayNotes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-warning" onclick="updateHoliday()">บันทึกการแก้ไข</button>
                </div>
            </div>
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
        
    <!-- JavaScript Libraries -->
    <script src="../js/core/jquery-3.7.1.min.js"></script>
    <script src="../js/core/popper.min.js"></script>
    <script src="../js/core/bootstrap.min.js"></script>
    <script src="../js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>
    <script src="../js/kaiadmin.min.js"></script>

    <script>
// ========================================
// ตัวแปรและการตั้งค่าหลัก
// ========================================

const API_CONFIG = {
    academicYear: '../api/api_academic_year_direct.php',
    holidayManagement: '../api/api_holiday_management.php',
    holidayData: '../api/api_holiday_data.php',
    holidayProcessor: '../api/api_holiday_processor.php'
};

// ตัวแปรสำหรับเก็บสถานะ
let academicYears = [];
let allHolidays = [];
let customHolidays = [];
let apiHolidays = [];
let currentSelectedAcademicYearId = null;
let holidayFilter = 'all';

console.log('🎓 Academic Year & Holiday Management System v2.1 - Improved API Integration');

// ========================================
// ฟังก์ชันช่วยเหลือพื้นฐาน
// ========================================

function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.display = 'flex';
    }
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

function showSuccess(message) {
    const msgElement = document.getElementById('successMessage');
    if (msgElement) {
        msgElement.textContent = message || 'ดำเนินการเสร็จสิ้น';
        const modal = new bootstrap.Modal(document.getElementById('successModal'));
        modal.show();
    }
}

function showError(message) {
    const msgElement = document.getElementById('errorMessage');
    if (msgElement) {
        msgElement.textContent = cleanErrorMessage(message);
        const modal = new bootstrap.Modal(document.getElementById('errorModal'));
        modal.show();
    }
}

function cleanErrorMessage(message) {
    if (!message) return 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ';
    
    let cleaned = message.trim();
    // ลบ HTTP status codes
    cleaned = cleaned.replace(/HTTP \d+:/gi, '');
    cleaned = cleaned.replace(/Bad Request/gi, '');
    cleaned = cleaned.replace(/Conflict/gi, '');
    cleaned = cleaned.replace(/Internal Server Error/gi, '');
    cleaned = cleaned.replace(/^(เกิดข้อผิดพลาด:\s*)+/gi, '');
    cleaned = cleaned.replace(/\s+/g, ' ').trim();
    
    if (!cleaned) {
        return 'เกิดข้อผิดพลาดในการดำเนินการ';
    }
    
    return cleaned;
}

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
        console.error('Date formatting error:', error);
        return dateString;
    }
}

function getHolidayTypeBadge(type) {
    const typeMap = {
        'national': { class: 'holiday-type-national', text: 'วันหยุดชาติ' },
        'religious': { class: 'holiday-type-religious', text: 'วันหยุดศาสนา' },
        'royal': { class: 'holiday-type-royal', text: 'วันหยุดราชวงศ์' },
        'substitute': { class: 'holiday-type-substitute', text: 'วันหยุดชดเชย' },
        'custom': { class: 'holiday-type-custom', text: 'กำหนดเอง' }
    };
    
    const typeInfo = typeMap[type] || typeMap['custom'];
    return `<span class="badge ${typeInfo.class} holiday-type-badge">${typeInfo.text}</span>`;
}

// ========================================
// ฟังก์ชันเรียก API
// ========================================

async function callAPI(url, params = {}, retryCount = 0) {
    const maxRetries = 2;
    
    try {
        console.log(`🔄 API Call: ${url}`, params);
        
        const formData = new FormData();
        for (const [key, value] of Object.entries(params)) {
            if (value !== null && value !== undefined) {
                formData.append(key, String(value));
            }
        }

        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        console.log(`📡 Response status: ${response.status} ${response.statusText}`);

        if (!response.ok) {
            // อ่าน response body เพื่อดู error message
            const errorText = await response.text();
            console.error('❌ Response error text:', errorText);
            
            let errorMessage = `HTTP ${response.status}: ${response.statusText}`;
            
            // พยายามแปลง error text เป็น JSON
            try {
                const errorJson = JSON.parse(errorText);
                if (errorJson.message) {
                    errorMessage = errorJson.message;
                }
            } catch (parseError) {
                // ถ้าแปลงไม่ได้ ใช้ status text
                console.warn('Could not parse error JSON:', parseError);
            }
            
            throw new Error(errorMessage);
        }

        const text = await response.text();
        console.log(`📥 API Response: ${url}`, text.substring(0, 200) + '...');
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.error('Response text:', text);
            throw new Error('การตอบกลับจากเซิร์ฟเวอร์ไม่ถูกต้อง');
        }

        if (!data.success) {
            if (data.message) {
                throw new Error(data.message);
            } else {
                throw new Error('การเรียก API ไม่สำเร็จ');
            }
        }

        console.log(`✅ API Success: ${url}`);
        return data;

    } catch (error) {
        console.error(`❌ API Error: ${url}`, error);
        
        // Retry logic for network errors
        if (retryCount < maxRetries && (
            error.message.includes('fetch') || 
            error.message.includes('network') ||
            error.message.includes('timeout')
        )) {
            console.log(`🔄 Retrying API call (${retryCount + 1}/${maxRetries})`);
            await new Promise(resolve => setTimeout(resolve, 1000 * (retryCount + 1)));
            return callAPI(url, params, retryCount + 1);
        }
        
        throw error;
    }
}

// ========================================
// ฟังก์ชันตรวจสอบสถานะ API
// ========================================

async function checkApiStatus() {
    const statusIndicator = document.getElementById('apiStatus');
    const statusText = document.getElementById('apiStatusText');
    
    if (statusIndicator && statusText) {
        statusIndicator.className = 'api-status-indicator loading';
        statusText.textContent = 'กำลังตรวจสอบ...';
    }
    
    try {
        const data = await callAPI(API_CONFIG.holidayProcessor, { action: 'test_api' });
        
        if (data.success) {
            if (statusIndicator && statusText) {
                statusIndicator.className = 'api-status-indicator online';
                statusText.textContent = 'เชื่อมต่อ API สำเร็จ';
            }
            console.log('✅ API Status: Online');
        } else {
            throw new Error(data.message || 'API ไม่พร้อมใช้งาน');
        }
        
    } catch (error) {
        if (statusIndicator && statusText) {
            statusIndicator.className = 'api-status-indicator offline';
            statusText.textContent = 'API ไม่พร้อมใช้งาน';
        }
        console.error('❌ API Status Check Error:', error);
    }
}

// ========================================
// ฟังก์ชันจัดการปีการศึกษา
// ========================================

async function loadAcademicYears() {
    try {
        console.log('📚 Loading academic years...');
        
        const data = await callAPI(API_CONFIG.academicYear, { action: 'get_academic_years' });
        
        if (data.success && data.data) {
            academicYears = data.data;
            updateAcademicYearsContainer(academicYears);
            updateAcademicYearDropdowns(academicYears);
            updateStats();
            
            console.log('✅ Academic years loaded:', academicYears.length, 'items');
        }
        
    } catch (error) {
        console.error('❌ Error loading academic years:', error);
        const container = document.getElementById('academicYearsContainer');
        if (container) {
            container.innerHTML = `
                <div class="alert alert-warning p-3">
                    <i class="fas fa-exclamation-triangle"></i> ไม่สามารถโหลดข้อมูลปีการศึกษาได้<br>
                    <small>${error.message}</small>
                </div>
            `;
        }
        showError('ไม่สามารถโหลดข้อมูลปีการศึกษาได้: ' + error.message);
    }
}

function updateAcademicYearsContainer(years) {
    const container = document.getElementById('academicYearsContainer');
    if (!container) return;
    
    if (!years || years.length === 0) {
        container.innerHTML = `
            <div class="alert alert-info p-3">
                <i class="fas fa-info-circle"></i> ยังไม่มีข้อมูลปีการศึกษา<br>
                <small>กรุณาเพิ่มปีการศึกษาใหม่จากฟอร์มด้านบน</small>
            </div>
        `;
        return;
    }
    
    container.innerHTML = years.map(year => {
        const isCurrent = year.is_current == 1;
        const isActive = year.is_active == 1;
        
        return `
            <div class="academic-year-item ${isCurrent ? 'current' : ''} p-2 border rounded mb-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>ปีการศึกษา ${year.academic_year}/${year.semester}</strong>
                        ${isCurrent ? '<span class="badge bg-primary ms-1">ปัจจุบัน</span>' : ''}
                        <br>
                        <small class="text-muted">
                            ${formatThaiDate(year.start_date)} - ${formatThaiDate(year.end_date)}
                        </small>
                    </div>
                    <div class="text-end">
                        ${isActive ? '<span class="badge bg-success">ใช้งาน</span>' : '<span class="badge bg-secondary">ไม่ใช้งาน</span>'}
                        <br>
                        <div class="btn-group mt-1" role="group">
                            ${!isCurrent ? `
                                <button class="btn btn-outline-success btn-sm" onclick="setCurrentAcademicYear(${year.academic_year_id})" title="ตั้งเป็นปีปัจจุบัน">
                                    <i class="fas fa-check"></i>
                                </button>
                            ` : ''}
                            ${!isCurrent ? `
                                <button class="btn btn-outline-danger btn-sm" onclick="deleteAcademicYear(${year.academic_year_id})" title="ลบปีการศึกษา">
                                    <i class="fas fa-trash"></i>
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function updateAcademicYearDropdowns(years) {
    const dropdowns = ['holidayAcademicYearSelect', 'holidayYearFilter'];
    
    dropdowns.forEach(dropdownId => {
        const dropdown = document.getElementById(dropdownId);
        if (!dropdown) return;
        
        const isFilter = dropdownId === 'holidayYearFilter';
        dropdown.innerHTML = isFilter ? '<option value="">ทุกปีการศึกษา</option>' : '<option value="">เลือกปีการศึกษา...</option>';
        
        years.forEach(year => {
            const option = document.createElement('option');
            option.value = year.academic_year_id;
            option.textContent = `ปีการศึกษา ${year.academic_year} เทอม ${year.semester}${year.is_current == 1 ? ' (ปัจจุบัน)' : ''}`;
            dropdown.appendChild(option);
        });
    });
}

async function addAcademicYear(event) {
    event.preventDefault();
    
    const formData = {
        action: 'add_academic_year',
        academic_year: document.getElementById('academicYear').value,
        semester: document.getElementById('semester').value,
        start_date: document.getElementById('startDate').value,
        end_date: document.getElementById('endDate').value,
        is_current: document.getElementById('setAsCurrent').checked ? 1 : 0,
        is_active: document.getElementById('setAsActive').checked ? 1 : 0
    };

    if (!formData.academic_year || !formData.semester || !formData.start_date || !formData.end_date) {
        showError('กรุณากรอกข้อมูลให้ครบถ้วน');
        return;
    }

    if (new Date(formData.start_date) >= new Date(formData.end_date)) {
        showError('วันที่เริ่มต้นต้องน้อยกว่าวันที่สิ้นสุด');
        return;
    }

    showLoading();
    
    try {
        const data = await callAPI(API_CONFIG.academicYear, formData);
        
        hideLoading();
        
        if (data.success) {
            showSuccess('เพิ่มปีการศึกษาสำเร็จ!');
            document.getElementById('academicYearForm').reset();
            
            setTimeout(() => {
                loadAcademicYears();
                if (formData.is_current) {
                    setTimeout(() => location.reload(), 1000);
                }
            }, 1000);
        }
        
    } catch (error) {
        hideLoading();
        showError(error.message);
    }
}

async function setCurrentAcademicYear(academicYearId) {
    const year = academicYears.find(y => y.academic_year_id == academicYearId);
    if (!year) {
        showError('ไม่พบข้อมูลปีการศึกษา');
        return;
    }
    
    if (!confirm(`คุณต้องการตั้งปีการศึกษา ${year.academic_year}/${year.semester} เป็นปีปัจจุบันหรือไม่?`)) {
        return;
    }
    
    showLoading();
    
    try {
        const data = await callAPI(API_CONFIG.academicYear, {
            action: 'set_current_academic_year',
            academic_year_id: academicYearId
        });
        
        hideLoading();
        
        if (data.success) {
            showSuccess('ตั้งค่าปีการศึกษาปัจจุบันสำเร็จ');
            setTimeout(() => location.reload(), 2000);
        }
        
    } catch (error) {
        hideLoading();
        showError(error.message);
    }
}

async function deleteAcademicYear(academicYearId) {
    const year = academicYears.find(y => y.academic_year_id == academicYearId);
    if (!year) {
        showError('ไม่พบข้อมูลปีการศึกษา');
        return;
    }

    if (!confirm(`คุณต้องการลบปีการศึกษา ${year.academic_year}/${year.semester} หรือไม่?\n\nการลบจะไม่สามารถกู้คืนได้`)) {
        return;
    }
    
    showLoading();
    
    try {
        const data = await callAPI(API_CONFIG.academicYear, {
            action: 'delete_academic_year',
            academic_year_id: academicYearId
        });
        
        hideLoading();
        
        if (data.success) {
            showSuccess('ลบปีการศึกษาสำเร็จ!');
            setTimeout(() => loadAcademicYears(), 1000);
        }
        
    } catch (error) {
        hideLoading();
        showError(error.message);
    }
}

// ========================================
// ฟังก์ชันจัดการวันหยุด
// ========================================

async function loadAllHolidays(academicYearId = null) {
    try {
        console.log('📅 Loading all holidays...');
        
        const params = { action: 'get_all_holidays' };
        if (academicYearId) {
            params.academic_year_id = academicYearId;
        }
        
        const data = await callAPI(API_CONFIG.holidayData, params);
        
        if (data.success && data.data) {
            allHolidays = data.data.holidays || [];
            customHolidays = allHolidays.filter(h => h.is_custom);
            apiHolidays = allHolidays.filter(h => !h.is_custom);
            
            updateAllHolidaysTable();
            updateHolidayStats();
            updateUpcomingHolidays();
            
            console.log('✅ Holidays loaded:', allHolidays.length, 'total holidays');
        }
        
    } catch (error) {
        console.error('❌ Error loading holidays:', error);
        const tbody = document.getElementById('allHolidaysTableBody');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-muted py-3">
                        <i class="fas fa-exclamation-triangle fa-lg mb-2 text-warning"></i><br>
                        ไม่สามารถโหลดข้อมูลวันหยุดได้<br>
                        <small class="text-muted">${error.message}</small>
                    </td>
                </tr>
            `;
        }
    }
}

// ฟังก์ชันลบวันหยุดจากตาราง
async function deleteHolidayFromTable(holidayId) {
    const holiday = allHolidays.find(h => h.holiday_id == holidayId);
    if (!holiday) {
        showError('ไม่พบข้อมูลวันหยุด');
        return;
    }
    
    const confirmMessage = holiday.is_custom ? 
        `คุณต้องการลบวันหยุด "${holiday.holiday_name}" หรือไม่?\n\nการลบจะไม่สามารถกู้คืนได้` :
        `คุณต้องการลบวันหยุด "${holiday.holiday_name}" จาก API หรือไม่?\n\nวันหยุดนี้จะถูกลบออกจากระบบ`;

    if (!confirm(confirmMessage)) {
        return;
    }
    
    showLoading();
    
    try {
        // แก้ไข: ตรวจสอบให้แน่ใจว่า holidayId เป็นตัวเลข
        const numericHolidayId = parseInt(holidayId);
        if (isNaN(numericHolidayId) || numericHolidayId <= 0) {
            throw new Error('รหัสวันหยุดไม่ถูกต้อง');
        }
        
        console.log('🗑️ Deleting holiday:', {
            holidayId: numericHolidayId,
            holidayName: holiday.holiday_name,
            isCustom: holiday.is_custom
        });
        
        const data = await callAPI(API_CONFIG.holidayManagement, {
            action: 'delete_holiday',
            holiday_id: numericHolidayId
        });
        
        hideLoading();
        
        if (data.success) {
            showSuccess('ลบวันหยุดสำเร็จ!');
            
            // ลบจาก array ท้องถิ่น
            const index = allHolidays.findIndex(h => h.holiday_id == numericHolidayId);
            if (index > -1) {
                allHolidays.splice(index, 1);
            }
            
            // อัปเดต arrays ย่อย
            customHolidays = allHolidays.filter(h => h.is_custom);
            apiHolidays = allHolidays.filter(h => !h.is_custom);
            
            // อัปเดต UI ทันที
            updateAllHolidaysTable();
            updateHolidayStats();
            updateUpcomingHolidays();
            updateStats();
            
            console.log('✅ Holiday deleted successfully');
        }
        
    } catch (error) {
        hideLoading();
        console.error('❌ Delete holiday error:', error);
        
        // แสดง error message ที่ละเอียดขึ้น
        let errorMessage = 'เกิดข้อผิดพลาดในการลบวันหยุด';
        
        if (error.message) {
            if (error.message.includes('HTTP 400')) {
                errorMessage = 'ข้อมูลที่ส่งไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
            } else if (error.message.includes('HTTP 403')) {
                errorMessage = 'คุณไม่มีสิทธิ์ลบวันหยุดนี้';
            } else if (error.message.includes('HTTP 404')) {
                errorMessage = 'ไม่พบวันหยุดที่ต้องการลบ';
            } else if (error.message.includes('HTTP 409')) {
                errorMessage = 'ไม่สามารถลบวันหยุดนี้ได้ เนื่องจากมีการใช้งานในระบบแล้ว';
            } else {
                errorMessage = cleanErrorMessage(error.message);
            }
        }
        
        showError(errorMessage);
    }
}

// ฟังก์ชันลบปีการศึกษาจากรายการ
async function deleteAcademicYearFromList(academicYearId) {
    const year = academicYears.find(y => y.academic_year_id == academicYearId);
    if (!year) {
        showError('ไม่พบข้อมูลปีการศึกษา');
        return;
    }

    if (year.is_current == 1) {
        showError('ไม่สามารถลบปีการศึกษาปัจจุบันได้');
        return;
    }

    if (!confirm(`คุณต้องการลบปีการศึกษา ${year.academic_year}/${year.semester} หรือไม่?\n\nการลบจะไม่สามารถกู้คืนได้`)) {
        return;
    }
    
    showLoading();
    
    try {
        const data = await callAPI(API_CONFIG.academicYear, {
            action: 'delete_academic_year',
            academic_year_id: academicYearId
        });
        
        hideLoading();
        
        if (data.success) {
            showSuccess('ลบปีการศึกษาสำเร็จ!');
            
            // ลบจาก array ท้องถิ่น
            const index = academicYears.findIndex(y => y.academic_year_id == academicYearId);
            if (index > -1) {
                academicYears.splice(index, 1);
            }
            
            // อัปเดต UI ทันที
            updateAcademicYearsContainer(academicYears);
            updateAcademicYearDropdowns(academicYears);
            updateStats();
        }
        
    } catch (error) {
        hideLoading();
        showError(error.message);
    }
}

// แก้ไขฟังก์ชัน updateAllHolidaysTable เพื่อให้มีปุ่มลบทุกรายการ
function updateAllHolidaysTable() {
    const tbody = document.getElementById('allHolidaysTableBody');
    if (!tbody) return;
    
    if (!allHolidays || allHolidays.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-muted py-3">
                    <i class="fas fa-calendar-times fa-lg mb-2"></i><br>
                    ยังไม่มีข้อมูลวันหยุด
                </td>
            </tr>
        `;
        return;
    }
    
    // กรองข้อมูลตาม filter ที่เลือก
    let filteredHolidays = allHolidays;
    
    switch (holidayFilter) {
        case 'custom':
            filteredHolidays = allHolidays.filter(h => h.is_custom);
            break;
        case 'api':
            filteredHolidays = allHolidays.filter(h => !h.is_custom);
            break;
        case 'national':
            filteredHolidays = allHolidays.filter(h => h.holiday_type === 'national');
            break;
        case 'religious':
            filteredHolidays = allHolidays.filter(h => h.holiday_type === 'religious');
            break;
        case 'royal':
            filteredHolidays = allHolidays.filter(h => h.holiday_type === 'royal');
            break;
        default:
            filteredHolidays = allHolidays;
    }
    
    // เรียงตามวันที่
    filteredHolidays.sort((a, b) => new Date(a.holiday_date) - new Date(b.holiday_date));
    
    tbody.innerHTML = filteredHolidays.map(holiday => {
        const date = new Date(holiday.holiday_date);
        const day = date.getDate();
        const monthNames = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 
                          'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        const month = monthNames[date.getMonth()];
        const weekdays = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
        const weekday = weekdays[date.getDay()];
        
        const sourceClass = holiday.is_custom ? 'custom-holiday' : 'api-holiday';
        const sourceBadge = holiday.is_custom ? 
            '<span class="badge bg-success">เพิ่มเอง</span>' : 
            '<span class="badge bg-purple">Calendarific API</span>';
        
        return `
            <tr class="holiday-row ${sourceClass}" data-date="${holiday.holiday_date}" data-type="${holiday.holiday_type}">
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
                        <span class="holiday-title">${holiday.holiday_name}</span>
                        ${holiday.english_name && holiday.english_name !== holiday.holiday_name ? 
                            `<br><small class="text-muted">${holiday.english_name}</small>` : ''}
                    </div>
                </td>
                <td>
                    ${getHolidayTypeBadge(holiday.holiday_type)}
                </td>
                <td>
                    ${sourceBadge}
                </td>
                <td>
                    <span class="badge bg-info">${holiday.academic_year}</span>
                </td>
                <td>
                    <div class="btn-group" role="group">
                        <button class="btn btn-outline-info btn-sm" onclick="viewHolidayDetails(${holiday.holiday_id})" title="ดูรายละเอียด">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${holiday.is_custom ? `
                            <button class="btn btn-outline-primary btn-sm" onclick="editHoliday(${holiday.holiday_id})" title="แก้ไข">
                                <i class="fas fa-edit"></i>
                            </button>
                        ` : ''}
                        <button class="btn btn-outline-danger btn-sm" onclick="deleteHolidayFromTable(${holiday.holiday_id})" title="ลบ">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function updateHolidayStats() {
    // นับจำนวนวันหยุดแต่ละประเภท
    const customCount = allHolidays.filter(h => h.is_custom).length;
    const apiCount = allHolidays.filter(h => !h.is_custom).length;
    const nationalCount = allHolidays.filter(h => h.holiday_type === 'national').length;
    const religiousCount = allHolidays.filter(h => h.holiday_type === 'religious').length;
    const royalCount = allHolidays.filter(h => h.holiday_type === 'royal').length;
    const totalCount = allHolidays.length;
    
    // อัปเดต UI elements
    const elements = {
        'customHolidays': customCount,
        'manualHolidays': customCount,
        'apiHolidays': apiCount,
        'apiHolidaysCount': apiCount,
        'nationalHolidaysCount': nationalCount,
        'religiousHolidaysCount': religiousCount,
        'royalHolidaysCount': royalCount,
        
        // Filter counts
        'allHolidaysCount': totalCount,
        'customHolidaysFilterCount': customCount,
        'apiHolidaysFilterCount': apiCount,
        'nationalFilterCount': nationalCount,
        'religiousFilterCount': religiousCount,
        'royalFilterCount': royalCount
    };
    
    Object.entries(elements).forEach(([id, count]) => {
        const element = document.getElementById(id);
        if (element) element.textContent = count;
    });
}

function updateUpcomingHolidays() {
    const container = document.getElementById('upcomingHolidays');
    if (!container) return;
    
    if (!allHolidays || allHolidays.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-3">
                <i class="fas fa-calendar-times fa-lg mb-2"></i><br>
                <small>ไม่มีข้อมูลวันหยุด</small>
            </div>
        `;
        return;
    }
    
    // หาวันหยุดที่กำลังจะมาถึง (30 วันข้างหน้า)
    const today = new Date();
    const nextMonth = new Date(today.getTime() + (30 * 24 * 60 * 60 * 1000));
    
    const upcoming = allHolidays
        .filter(h => {
            const holidayDate = new Date(h.holiday_date);
            return holidayDate >= today && holidayDate <= nextMonth;
        })
        .sort((a, b) => new Date(a.holiday_date) - new Date(b.holiday_date))
        .slice(0, 5); // แสดงแค่ 5 วันหยุดที่ใกล้ที่สุด
    
    if (upcoming.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-3">
                <i class="fas fa-calendar-check fa-lg mb-2"></i><br>
                <small>ไม่มีวันหยุดใน 30 วันข้างหน้า</small>
            </div>
        `;
        return;
    }
    
    container.innerHTML = upcoming.map(holiday => {
        const date = new Date(holiday.holiday_date);
        const day = date.getDate();
        const month = date.toLocaleDateString('th-TH', { month: 'short' });
        const daysUntil = Math.ceil((date - today) / (1000 * 60 * 60 * 24));
        
        return `
            <div class="d-flex align-items-center mb-2 p-2 border rounded">
                <div class="text-center me-3" style="min-width: 50px;">
                    <strong>${day}</strong><br>
                    <small class="text-muted">${month}</small>
                </div>
                <div class="flex-grow-1">
                    <div class="fw-bold" style="font-size: 0.9em;">${holiday.holiday_name}</div>
                    <small class="text-muted">
                        ${daysUntil === 0 ? 'วันนี้' : daysUntil === 1 ? 'พรุ่งนี้' : `อีก ${daysUntil} วัน`}
                        ${!holiday.is_custom ? ' (API)' : ' (เพิ่มเอง)'}
                    </small>
                </div>
            </div>
        `;
    }).join('');
}

async function fetchHolidaysFromAPI() {
    if (!currentSelectedAcademicYearId) {
        // หาปีการศึกษาปัจจุบัน
        const currentYear = academicYears.find(y => y.is_current == 1);
        if (!currentYear) {
            showError('กรุณาเลือกปีการศึกษาก่อน หรือตั้งค่าปีการศึกษาปัจจุบัน');
            return;
        }
        currentSelectedAcademicYearId = currentYear.academic_year_id;
    }
    
    if (!confirm('คุณต้องการดึงข้อมูลวันหยุดจาก Calendarific API หรือไม่?\n\nข้อมูลเก่าจะถูกแทนที่')) {
        return;
    }
    
    showLoading();
    
    // ปิดการใช้งานปุ่ม
    const buttons = ['fetchApiBtn', 'fetchApiMainBtn'];
    buttons.forEach(id => {
        const btn = document.getElementById(id);
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }
    });
    
    try {
        const data = await callAPI(API_CONFIG.holidayProcessor, {
            action: 'fetch_and_process',
            academic_year_id: currentSelectedAcademicYearId
        });
        
        hideLoading();
        
        if (data.success) {
            const stats = data.data || {};
            showSuccess(`ดึงข้อมูลวันหยุดจาก API สำเร็จ!\n\nนำเข้า: ${stats.total_imported || 0} วัน`);
            
            setTimeout(() => {
                loadAllHolidays();
                updateStats();
            }, 2000);
        }
        
    } catch (error) {
        hideLoading();
        showError('เกิดข้อผิดพลาดในการดึงข้อมูลจาก API: ' + error.message);
    } finally {
        // เปิดการใช้งานปุ่มใหม่
        buttons.forEach(id => {
            const btn = document.getElementById(id);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = btn.id === 'fetchApiBtn' ? 
                    '<i class="fas fa-sync-alt"></i>' : 
                    '<i class="fas fa-download"></i> ดึงวันหยุดจาก API';
            }
        });
    }
}

async function addHoliday(event) {
    event.preventDefault();
    
    if (!currentSelectedAcademicYearId) {
        showError('กรุณาเลือกปีการศึกษาก่อน');
        return;
    }
    
    const formData = {
        action: 'add_holiday',
        academic_year_id: currentSelectedAcademicYearId,
        holiday_date: document.getElementById('holidayDate').value,
        holiday_name: document.getElementById('holidayName').value.trim(),
        holiday_name_en: document.getElementById('holidayNameEn').value.trim(),
        holiday_type: document.getElementById('holidayType').value,
        notes: document.getElementById('holidayNotes').value.trim()
    };

    if (!formData.holiday_date || !formData.holiday_name || !formData.holiday_type) {
        showError('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
        return;
    }

    showLoading();
    
    try {
        const data = await callAPI(API_CONFIG.holidayManagement, formData);
        
        hideLoading();
        
        if (data.success) {
            showSuccess('เพิ่มวันหยุดสำเร็จ!');
            document.getElementById('addHolidayForm').reset();
            
            setTimeout(() => {
                loadAllHolidays();
                updateStats();
            }, 1000);
        }
        
    } catch (error) {
        hideLoading();
        showError(error.message);
    }
}

function editHoliday(holidayId) {
    const holiday = allHolidays.find(h => h.holiday_id == holidayId);
    if (!holiday) {
        showError('ไม่พบข้อมูลวันหยุด');
        return;
    }
    
    if (!holiday.is_custom) {
        showError('ไม่สามารถแก้ไขวันหยุดจาก API ได้');
        return;
    }

    document.getElementById('editHolidayId').value = holiday.holiday_id;
    document.getElementById('editHolidayDate').value = holiday.holiday_date;
    document.getElementById('editHolidayName').value = holiday.holiday_name;
    document.getElementById('editHolidayNameEn').value = holiday.english_name || '';
    document.getElementById('editHolidayType').value = holiday.holiday_type;
    document.getElementById('editHolidayNotes').value = holiday.notes || '';

    new bootstrap.Modal(document.getElementById('editHolidayModal')).show();
}

async function updateHoliday() {
    const formData = {
        action: 'update_holiday',
        holiday_id: document.getElementById('editHolidayId').value,
        holiday_date: document.getElementById('editHolidayDate').value,
        holiday_name: document.getElementById('editHolidayName').value.trim(),
        holiday_name_en: document.getElementById('editHolidayNameEn').value.trim(),
        holiday_type: document.getElementById('editHolidayType').value,
        notes: document.getElementById('editHolidayNotes').value.trim()
    };

    if (!formData.holiday_date || !formData.holiday_name || !formData.holiday_type) {
        showError('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
        return;
    }

    showLoading();
    
    try {
        const data = await callAPI(API_CONFIG.holidayManagement, formData);
        
        hideLoading();
        
        if (data.success) {
            showSuccess('แก้ไขวันหยุดสำเร็จ!');
            
            bootstrap.Modal.getInstance(document.getElementById('editHolidayModal')).hide();
            
            setTimeout(() => {
                loadAllHolidays();
                updateStats();
            }, 1000);
        }
        
    } catch (error) {
        hideLoading();
        showError(error.message);
    }
}

async function deleteHoliday(holidayId) {
    const holiday = allHolidays.find(h => h.holiday_id == holidayId);
    if (!holiday) {
        showError('ไม่พบข้อมูลวันหยุด');
        return;
    }
    
    if (!holiday.is_custom) {
        showError('ไม่สามารถลบวันหยุดจาก API ได้');
        return;
    }

    if (!confirm(`คุณต้องการลบวันหยุด "${holiday.holiday_name}" หรือไม่?\n\nการลบจะไม่สามารถกู้คืนได้`)) {
        return;
    }
    
    showLoading();
    
    try {
        const data = await callAPI(API_CONFIG.holidayManagement, {
            action: 'delete_holiday',
            holiday_id: holidayId
        });
        
        hideLoading();
        
        if (data.success) {
            showSuccess('ลบวันหยุดสำเร็จ!');
            
            setTimeout(() => {
                loadAllHolidays();
                updateStats();
            }, 1000);
        }
        
    } catch (error) {
        hideLoading();
        showError(error.message);
    }
}

function viewHolidayDetails(holidayId) {
    const holiday = allHolidays.find(h => h.holiday_id == holidayId);
    if (!holiday) {
        showError('ไม่พบข้อมูลวันหยุด');
        return;
    }
    
    let details = `ชื่อวันหยุด: ${holiday.holiday_name}\n`;
    if (holiday.english_name && holiday.english_name !== holiday.holiday_name) {
        details += `ชื่อภาษาอังกฤษ: ${holiday.english_name}\n`;
    }
    details += `วันที่: ${formatThaiDate(holiday.holiday_date)}\n`;
    details += `ประเภท: ${getHolidayTypeBadge(holiday.holiday_type).replace(/<[^>]*>/g, '')}\n`;
    details += `แหล่งที่มา: ${holiday.is_custom ? 'เพิ่มเอง' : 'Calendarific API'}\n`;
    details += `ปีการศึกษา: ${holiday.academic_year}`;
    
    if (holiday.notes) {
        details += `\n\nหมายเหตุ: ${holiday.notes}`;
    }
    
    alert(details);
}

function viewAllApiHolidays() {
    const modal = new bootstrap.Modal(document.getElementById('viewApiHolidaysModal'));
    modal.show();
    
    const tbody = document.getElementById('apiHolidaysModalBody');
    
    if (apiHolidays.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center text-muted py-3">
                    <i class="fas fa-calendar-times fa-lg mb-2"></i><br>
                    ไม่มีข้อมูลวันหยุดจาก API
                </td>
            </tr>
        `;
        return;
    }
    
    const sortedApiHolidays = [...apiHolidays].sort((a, b) => new Date(a.holiday_date) - new Date(b.holiday_date));
    
    tbody.innerHTML = sortedApiHolidays.map(holiday => {
        const today = new Date();
        const holidayDate = new Date(holiday.holiday_date);
        const isPast = holidayDate < today;
        const isToday = holidayDate.toDateString() === today.toDateString();
        
        let statusBadge;
        if (isToday) {
            statusBadge = '<span class="badge bg-warning">วันนี้</span>';
        } else if (isPast) {
            statusBadge = '<span class="badge bg-secondary">ผ่านแล้ว</span>';
        } else {
            const daysUntil = Math.ceil((holidayDate - today) / (1000 * 60 * 60 * 24));
            statusBadge = `<span class="badge bg-info">อีก ${daysUntil} วัน</span>`;
        }
        
        return `
            <tr class="${isPast ? 'text-muted' : ''}">
                <td>${formatThaiDate(holiday.holiday_date)}</td>
                <td>
                    <div class="fw-bold">${holiday.holiday_name}</div>
                    ${holiday.english_name && holiday.english_name !== holiday.holiday_name ? 
                        `<small class="text-muted">${holiday.english_name}</small>` : ''}
                </td>
                <td>${getHolidayTypeBadge(holiday.holiday_type)}</td>
                <td>${statusBadge}</td>
            </tr>
        `;
    }).join('');
}

// ========================================
// ฟังก์ชันกรองและค้นหา
// ========================================

function setupFilters() {
    // Holiday type filters
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            holidayFilter = this.dataset.filter;
            updateAllHolidaysTable();
        });
    });
    
    // Year filter
    const yearFilter = document.getElementById('holidayYearFilter');
    if (yearFilter) {
        yearFilter.addEventListener('change', function() {
            const selectedYear = this.value;
            if (selectedYear) {
                loadAllHolidays(selectedYear);
            } else {
                loadAllHolidays();
            }
        });
    }
    
    // Search functionality
    const searchInput = document.getElementById('holidaySearchAll');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterHolidaysBySearch(this.value);
        });
    }
    
    // API Holiday search
    const apiSearchInput = document.getElementById('apiHolidaySearch');
    if (apiSearchInput) {
        apiSearchInput.addEventListener('input', function() {
            filterApiHolidaysBySearch(this.value);
        });
    }
}

function filterHolidaysBySearch(searchTerm) {
    const rows = document.querySelectorAll('#allHolidaysTableBody .holiday-row');
    const term = searchTerm.toLowerCase();
    
    rows.forEach(row => {
        const holidayName = row.querySelector('.holiday-title')?.textContent?.toLowerCase() || '';
        const englishName = row.querySelector('.text-muted')?.textContent?.toLowerCase() || '';
        
        if (holidayName.includes(term) || englishName.includes(term)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function filterApiHolidaysBySearch(searchTerm) {
    const rows = document.querySelectorAll('#apiHolidaysModalBody tr');
    const term = searchTerm.toLowerCase();
    
    rows.forEach(row => {
        if (row.cells.length < 4) return; // Skip empty rows
        
        const holidayName = row.cells[1]?.textContent?.toLowerCase() || '';
        
        if (holidayName.includes(term)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// ========================================
// ฟังก์ชันการดำเนินการด่วน
// ========================================

async function refreshAllData() {
    showLoading();
    
    try {
        await Promise.all([
            loadAcademicYears(),
            loadAllHolidays(),
            checkApiStatus()
        ]);
        
        hideLoading();
        showSuccess('รีเฟรชข้อมูลเสร็จสิ้น');
    } catch (error) {
        hideLoading();
        showError('เกิดข้อผิดพลาดในการรีเฟรชข้อมูล: ' + error.message);
    }
}

// ========================================
// ฟังก์ชันสถิติและสรุปข้อมูล
// ========================================

function updateStats() {
    // นับปีการศึกษา
    const totalAcademicYears = academicYears.length;
    
    // อัปเดตสถิติปีการศึกษา
    const elements = {
        'academicYearsBadge': `${totalAcademicYears}`
    };
    
    Object.entries(elements).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element) element.textContent = value;
    });
    
    // อัปเดตสถิติวันหยุด
    updateHolidayStats();
}

// ========================================
// การเริ่มต้นระบบ
// ========================================

document.addEventListener('DOMContentLoaded', async function() {
    console.log('🎓 Academic Year & Holiday Management System v2.1 - Improved API Integration Starting...');
    
    try {
        // ขั้นตอนที่ 1: ผูก event listener กับฟอร์ม
        console.log('🔄 Step 1: Bind event listeners');
        
        const academicYearForm = document.getElementById('academicYearForm');
        if (academicYearForm) {
            academicYearForm.addEventListener('submit', addAcademicYear);
        }

        const addHolidayForm = document.getElementById('addHolidayForm');
        if (addHolidayForm) {
            addHolidayForm.addEventListener('submit', addHoliday);
        }
        
        // ผูก event สำหรับการเลือกปีการศึกษาสำหรับวันหยุด
        const holidayAcademicYearSelect = document.getElementById('holidayAcademicYearSelect');
        if (holidayAcademicYearSelect) {
            holidayAcademicYearSelect.addEventListener('change', function() {
                const selectedYear = parseInt(this.value);
                currentSelectedAcademicYearId = selectedYear || null;
                
                // เปิด/ปิดการใช้งานฟอร์มเพิ่มวันหยุด
                const addHolidayBtn = document.getElementById('addHolidayBtn');
                const holidayInputs = document.querySelectorAll('#addHolidayForm input, #addHolidayForm select, #addHolidayForm textarea');
                
                if (selectedYear) {
                    if (addHolidayBtn) addHolidayBtn.disabled = false;
                    holidayInputs.forEach(input => input.disabled = false);
                    
                    // โหลดวันหยุดสำหรับปีที่เลือก
                    loadAllHolidays(selectedYear);
                } else {
                    if (addHolidayBtn) addHolidayBtn.disabled = true;
                    holidayInputs.forEach(input => input.disabled = true);
                    
                    // โหลดวันหยุดทั้งหมด
                    loadAllHolidays();
                }
                
                updateStats();
            });
        }
        
        // ขั้นตอนที่ 2: ตั้งค่า UI components
        console.log('🔄 Step 2: Setup UI components');
        
        setupFilters();
        
        // ตั้งค่าวันที่เริ่มต้น
        const today = new Date();
        const todayString = today.toISOString().split('T')[0];
        
        const holidayDateInput = document.getElementById('holidayDate');
        if (holidayDateInput) {
            holidayDateInput.value = todayString;
        }
        
        // ตั้งค่าปีการศึกษาเริ่มต้น
        const currentThaiYear = today.getFullYear() + 543;
        const academicYearInput = document.getElementById('academicYear');
        if (academicYearInput) {
            academicYearInput.value = currentThaiYear;
        }
        
        // ขั้นตอนที่ 3: โหลดข้อมูลเริ่มต้น
        console.log('🔄 Step 3: Load initial data');
        
        await loadAcademicYears();
        await loadAllHolidays();
        await checkApiStatus();
        
        // ขั้นตอนที่ 4: ตั้งค่า keyboard shortcuts
        console.log('🔄 Step 4: Setup keyboard shortcuts');
        
        document.addEventListener('keydown', function(e) {
            // Ctrl+R: Refresh data
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshAllData();
            }
            
            // Ctrl+F: Focus search
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                const searchInput = document.getElementById('holidaySearchAll');
                if (searchInput) searchInput.focus();
            }
            
            // Ctrl+A: Fetch API holidays
            if (e.ctrlKey && e.key === 'a') {
                e.preventDefault();
                fetchHolidaysFromAPI();
            }
            
            // Escape: Close modals
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal.show');
                modals.forEach(modal => {
                    const bsModal = bootstrap.Modal.getInstance(modal);
                    if (bsModal) bsModal.hide();
                });
            }
        });
        
        // ขั้นตอนที่ 5: ตั้งค่า auto-refresh
        console.log('🔄 Step 5: Setup auto-refresh');
        
        // Auto-refresh API status every 5 minutes
        setInterval(checkApiStatus, 5 * 60 * 1000);
        
        // Auto-refresh holiday data every 30 minutes
        setInterval(() => {
            if (currentSelectedAcademicYearId) {
                loadAllHolidays(currentSelectedAcademicYearId);
            } else {
                loadAllHolidays();
            }
        }, 30 * 60 * 1000);
        
        console.log('✅ Academic Year & Holiday Management System v2.1 Ready!');
        console.log('💡 Available commands:');
        console.log('- Ctrl+R: Refresh all data');
        console.log('- Ctrl+F: Focus on search');
        console.log('- Ctrl+A: Fetch holidays from API');
        console.log('- Escape: Close modals');
        
        // แสดงข้อความต้อนรับ
        setTimeout(() => {
            const apiStatusText = document.getElementById('apiStatusText');
            if (apiStatusText && apiStatusText.textContent.includes('เชื่อมต่อ API สำเร็จ')) {
                console.log('🎉 System fully loaded with improved API integration!');
            }
        }, 2000);
        
    } catch (error) {
        console.error('❌ Error initializing system:', error);
        showError('เกิดข้อผิดพลาดในการเริ่มต้นระบบ: ' + error.message);
    }
});

// ========================================
// Global functions for debugging and external access
// ========================================

window.academicYearManagement = {
    // Data access
    getAcademicYears: () => academicYears,
    getAllHolidays: () => allHolidays,
    getCustomHolidays: () => customHolidays,
    getApiHolidays: () => apiHolidays,
    getCurrentSelectedAcademicYear: () => currentSelectedAcademicYearId,
    
    // API calls
    callAPI,
    
    // Main functions
    loadAcademicYears,
    loadAllHolidays,
    fetchHolidaysFromAPI,
    updateStats,
    refreshAllData,
    checkApiStatus,
    
    // Holiday management
    addHoliday,
    editHoliday,
    updateHoliday,
    deleteHoliday,
    viewHolidayDetails,
    viewAllApiHolidays,
    
    // Academic year management
    addAcademicYear,
    setCurrentAcademicYear,
    deleteAcademicYear,
    
    // Filter and search
    setHolidayFilter: (filter) => {
        holidayFilter = filter;
        updateAllHolidaysTable();
    },
    filterHolidaysBySearch,
    
    // UI utilities
    showSuccess,
    showError,
    showLoading,
    hideLoading,
    formatThaiDate,
    getHolidayTypeBadge,
    
    // System info
    version: '2.1',
    lastUpdate: new Date().toISOString(),
    features: [
        'Improved API Integration',
        'Enhanced Error Handling',
        'Holiday Management',
        'Academic Year Management',
        'Real-time Statistics',
        'Responsive Design',
        'Auto-refresh',
        'Keyboard Shortcuts'
    ]
};

// ========================================
// Error Handling
// ========================================

// Set up global error handler
window.addEventListener('error', function(event) {
    console.error('Global error caught:', event.error);
});

// Set up unhandled promise rejection handler
window.addEventListener('unhandledrejection', function(event) {
    console.error('Unhandled promise rejection:', event.reason);
    event.preventDefault(); // Prevent default browser behavior
});

// Expose version info
window.systemInfo = {
    name: 'Academic Year & Holiday Management System',
    version: '2.1',
    build: new Date().toISOString(),
    features: window.academicYearManagement?.features || [],
    apis: API_CONFIG
};

console.log('🎉 Academic Year & Holiday Management System v2.1 - Complete with Improved API Integration!');
console.log('🔧 Debug interface available at: window.academicYearManagement');
    </script>
</body>
</html>