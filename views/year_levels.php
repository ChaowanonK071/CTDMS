<?php
require_once '../api/auth_check.php';
requireAdmin(); // เปลี่ยนจาก requireLogin เป็น requireAdmin

$userData = getUserData();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>จัดการข้อมูลชั้นปี - Kaiadmin Bootstrap 5 Admin Dashboard</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport"/>
    <link rel="icon" href="../img/coe/CoE-LOGO.png" type="image/x-icon" />

    <!-- Fonts and icons -->
    <script src="../js/plugin/webfont/webfont.min.js"></script>

    <!-- CSS Files -->
    <link rel="stylesheet" href="../css/bootstrap.min.css" />
    <link rel="stylesheet" href="../css/plugins.min.css" />
    <link rel="stylesheet" href="../css/kaiadmin.min.css" />

    <!-- CSS Just for demo purpose, don't include it in your project -->
    <link rel="stylesheet" href="../css/demo.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />

    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
        }
        .required {
            color: red;
        }
        .btn-action {
            margin-right: 5px;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: nowrap;
            align-items: center;
        }
        .action-buttons .btn {
            white-space: nowrap;
            flex-shrink: 0;
        }
        /* กำหนดความกว้างของคอลัมน์จัดการ */
        #yearLevelTable td:last-child {
            min-width: 120px;
            width: 120px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
      <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
      <!-- End Sidebar -->

      <div class="main-panel">
        <div class="main-header">
          <!-- Navbar Header -->
          <?php include '../includes/header.php'; ?>
          <!-- End Navbar -->
        </div>

        <div class="container">
          <div class="page-inner">
            <div class="page-header">
              <h3 class="fw-bold mb-3">จัดการข้อมูลชั้นปี</h3>
              <ul class="breadcrumbs mb-3">
              </ul>
            </div>

            <div class="col-md-12">
              <div class="card">
                <div class="card-header">
                  <div class="d-flex align-items-center">
                    <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#yearLevelModal" id="btnAddYearLevel">
                      <i class="fa fa-plus"></i> เพิ่มชั้นปีใหม่
                    </button>
                  </div>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table
                      id="yearLevelTable"
                      class="display table table-striped table-hover"
                    >
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>สาขา</th>
                          <th>ชั้นปี</th>
                          <th>หลักสูตร</th>
                          <th>การแสดงผล</th>
                          <th style="width: 120px">จัดการ</th>
                        </tr>
                      </thead>
                      <tbody>
                        
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php include '../includes/footer.php'; ?>
      </div>
    </div>
    

    <!-- Modal เพิ่ม/แก้ไขชั้นปี -->
    <div class="modal fade" id="yearLevelModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h5 class="modal-title" id="yearLevelModalLabel">
              <span class="fw-mediumbold">เพิ่ม</span>
              <span class="fw-light">ชั้นปีใหม่</span>
            </h5>
            <button
              type="button"
              class="btn-close"
              data-bs-dismiss="modal"
              aria-label="Close"
            ></button>
          </div>
          <div class="modal-body">
            <p class="small">
              กรอกข้อมูลชั้นปีใหม่ โปรดกรอกข้อมูลให้ครบถ้วน
            </p>
            <form id="yearLevelForm">
              <input type="hidden" id="year_level_id" name="year_level_id" value="0">
              
              <div class="row">
                <div class="col-sm-12">
                  <div class="form-group form-group-default">
                    <label>สาขา <span class="required">*</span></label>
                    <select class="form-control" id="department" name="department" required>
                      <option value="">เลือกสาขา</option>
                      <option value="วต.">วต.</option>
                      <option value="วป.">วป.</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group form-group-default">
                    <label>ชั้นปี <span class="required">*</span></label>
                    <input
                      id="class_year"
                      name="class_year"
                      type="text"
                      class="form-control"
                      placeholder="เช่น 1/1, 2/2, 3/1"
                      maxlength="10"
                      required
                    />
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group form-group-default">
                    <label>หลักสูตร <span class="required">*</span></label>
                    <select class="form-control" id="curriculum" name="curriculum" required>
                      <option value="">เลือกหลักสูตร</option>
                      <option value="4ปี">4ปี</option>
                      <option value="เทียบโอน">เทียบโอน</option>
                    </select>
                  </div>
                </div>
                <div class="col-sm-12">
                  <div class="form-group form-group-default">
                    <label>ตัวอย่างการแสดงผล</label>
                    <div class="form-control bg-light" id="displayPreview" style="min-height: 38px; display: flex; align-items: center;">
                      <span class="text-muted">เลือกข้อมูลเพื่อดูตัวอย่าง</span>
                    </div>
                  </div>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer border-0">
            <button
              type="button"
              id="btnSaveYearLevel"
              class="btn btn-primary"
            >
              บันทึก
            </button>
            <button
              type="button"
              class="btn btn-danger"
              data-bs-dismiss="modal"
            >
              ยกเลิก
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal ยืนยันการลบ -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h5 class="modal-title" id="deleteConfirmModalLabel">
              <span class="fw-mediumbold">ยืนยัน</span>
              <span class="fw-light">การลบ</span>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>คุณต้องการลบชั้นปีนี้ใช่หรือไม่?</p>
            <p class="text-danger">หมายเหตุ: การลบนี้ไม่สามารถเรียกคืนได้</p>
          </div>
          <div class="modal-footer border-0">
            <button type="button" id="btnConfirmDelete" class="btn btn-danger"> ลบ
            </button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก
            </button>
          </div>
        </div>
      </div>
    </div>
    <script src="../js/core/jquery-3.7.1.min.js"></script>
    <script src="../js/core/popper.min.js"></script>
    <script src="../js/core/bootstrap.min.js"></script>
    <script src="../js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>
    <script src="../js/plugin/datatables/datatables.min.js"></script>
    <script src="../js/kaiadmin.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
// ตัวแปรสำหรับเก็บข้อมูลชั้นปี
let yearLevelsData = [];
let currentYearLevelId = 0;
let dataTable;

// โหลดข้อมูลชั้นปี
function loadYearLevels() {
    const apiUrl = "../api/year_level_api.php";
    
    $.ajax({
        url: apiUrl,
        type: "GET",
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                yearLevelsData = response.data;
                renderYearLevelsTable();
            } else {
                console.error("Error loading year levels:", response.message);
                alert("เกิดข้อผิดพลาดในการโหลดข้อมูลชั้นปี: " + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error("AJAX Error Details:");
            console.error("Status:", status);
            console.error("Error:", error);
            console.error("Response Text:", xhr.responseText);
            console.error("Status Code:", xhr.status);
            console.error("Ready State:", xhr.readyState);
            
            // แสดงข้อผิดพลาดที่เข้าใจง่าย
            let errorMessage = "เกิดข้อผิดพลาดในการโหลดข้อมูลชั้นปี\n";
            if (xhr.status === 404) {
                errorMessage += "ไม่พบไฟล์ API ที่ " + apiUrl + "\n";
                errorMessage += "กรุณาตรวจสอบว่าไฟล์ year_level_api.php อยู่ในโฟลเดอร์ api/";
            } else if (xhr.status === 0) {
                errorMessage += "ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้";
            } else {
                errorMessage += "รหัสข้อผิดพลาด: " + xhr.status;
            }
            alert(errorMessage);
        }
    });
}

// ฟังก์ชันแสดงข้อมูลในตาราง
function renderYearLevelsTable() {
    if (dataTable) {
        dataTable.destroy();
    }
    
    const tableBody = $("#yearLevelTable tbody");
    tableBody.empty();
    
    yearLevelsData.forEach(function(yearLevel) {
        const displayText = `${yearLevel.department} ${yearLevel.class_year} ${yearLevel.curriculum}`;
        
        tableBody.append(`
            <tr>
                <td>${yearLevel.year_level_id}</td>
                <td>${yearLevel.department}</td>
                <td>${yearLevel.class_year}</td>
                <td>${yearLevel.curriculum}</td>
                <td><strong>${displayText}</strong></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline-primary btn-edit" data-id="${yearLevel.year_level_id}" title="แก้ไข">
                            </i> แก้ไข
                        </button>
                        <button class="btn btn-sm btn-outline-danger btn-delete" data-id="${yearLevel.year_level_id}" title="ลบ">
                            </i> ลบ
                        </button>
                    </div>
                </td>
            </tr>
        `);
    });
    
    $(".btn-edit").click(function() {
        const yearLevelId = $(this).data("id");
        editYearLevel(yearLevelId);
    });
    
    $(".btn-delete").click(function() {
        currentYearLevelId = $(this).data("id");
        $("#deleteConfirmModal").modal("show");
    });
    
    dataTable = $("#yearLevelTable").DataTable({
        language: {
            url: "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json"
        },
        order: [[0, "asc"]],
        columnDefs: [
            { orderable: false, targets: [5] } 
        ]
    });
}

// ฟังก์ชันรีเซ็ตฟอร์ม
function resetYearLevelForm() {
    $("#year_level_id").val("0");
    $("#department").val("");
    $("#class_year").val("");
    $("#curriculum").val("");
    currentYearLevelId = 0;
    updateDisplayPreview();
}

// ฟังก์ชันเรียกดูข้อมูลชั้นปีเพื่อแก้ไข
function editYearLevel(yearLevelId) {
    const yearLevel = yearLevelsData.find(item => parseInt(item.year_level_id) === parseInt(yearLevelId));
    
    if (!yearLevel) {
        alert("ไม่พบข้อมูลชั้นปี");
        return;
    }
    
    currentYearLevelId = yearLevelId;
    
    $("#year_level_id").val(yearLevel.year_level_id);
    $("#department").val(yearLevel.department);
    $("#class_year").val(yearLevel.class_year);
    $("#curriculum").val(yearLevel.curriculum);
    
    // อัปเดตตัวอย่างการแสดงผล
    updateDisplayPreview();
    
    $("#yearLevelModalLabel").text("แก้ไขชั้นปี");
    $("#yearLevelModal").modal("show");
}

// ฟังก์ชันตรวจสอบความถูกต้องของข้อมูล
function validateForm() {
    let isValid = true;
    let errorMessage = "";
    
    // ตรวจสอบสาขา
    if (!$("#department").val()) {
        isValid = false;
        errorMessage += "- กรุณาเลือกสาขา\n";
        $("#department").addClass("is-invalid");
    } else {
        $("#department").removeClass("is-invalid");
    }
    
    // ตรวจสอบชั้นปี
    const classYear = $("#class_year").val().trim();
    if (!classYear) {
        isValid = false;
        errorMessage += "- กรุณากรอกชั้นปี\n";
        $("#class_year").addClass("is-invalid");
    } else if (classYear.length > 10) {
        isValid = false;
        errorMessage += "- ชั้นปีต้องไม่เกิน 10 ตัวอักษร\n";
        $("#class_year").addClass("is-invalid");
    } else {
        $("#class_year").removeClass("is-invalid");
    }
    
    // ตรวจสอบหลักสูตร
    if (!$("#curriculum").val()) {
        isValid = false;
        errorMessage += "- กรุณาเลือกหลักสูตร\n";
        $("#curriculum").addClass("is-invalid");
    } else {
        $("#curriculum").removeClass("is-invalid");
    }
    
    if (!isValid) {
        alert("กรุณาแก้ไขข้อมูลต่อไปนี้:\n" + errorMessage);
    }
    
    return isValid;
}

// ฟังก์ชันบันทึกข้อมูลชั้นปีพร้อม Debug เต็มรูปแบบ
function saveYearLevel() {
    console.log("=== Start saving year level ===");
    
    // ตรวจสอบข้อมูล
    if (!validateForm()) {
        $("#btnSaveYearLevel").prop('disabled', false).text('บันทึก');
        return;
    }
    
    // เตรียมข้อมูล
    const data = {
        department: $("#department").val(),
        class_year: $("#class_year").val(),
        curriculum: $("#curriculum").val()
    };
    
    // ถ้าเป็นการแก้ไข
    if (parseInt($("#year_level_id").val()) > 0) {
        data.year_level_id = parseInt($("#year_level_id").val());
    }
    
    // กำหนด method ตามการกระทำ (เพิ่มหรือแก้ไข)
    const method = data.year_level_id ? "PUT" : "POST";
    const apiUrl = "../api/year_level_api.php";
    
    console.log("=== Save Details ===");
    console.log("Data to send:", data);
    console.log("Method:", method);
    console.log("URL:", apiUrl);
    console.log("JSON String:", JSON.stringify(data));
    
    // ส่งข้อมูลไปยัง API
    $.ajax({
        url: apiUrl,
        type: method,
        dataType: "json",
        contentType: "application/json",
        data: JSON.stringify(data),
        timeout: 10000,
        beforeSend: function(xhr) {
            console.log("=== Before Send ===");
            console.log("XHR object:", xhr);
        },
        success: function(response, textStatus, xhr) {
            console.log("=== Success Response ===");
            console.log("Response:", response);
            console.log("Text Status:", textStatus);
            console.log("XHR Status:", xhr.status);
            
            $("#btnSaveYearLevel").prop('disabled', false).text('บันทึก');
            
            if (response.status === "success") {
                alert(response.message);
                $("#yearLevelModal").modal("hide");
                loadYearLevels();
            } else {
                alert(response.message);
            }
        },
        error: function(xhr, status, error) {
            console.log("=== Save Error Details ===");
            console.log("XHR object:", xhr);
            console.log("Status:", status);
            console.log("Error:", error);
            console.log("Response Text:", xhr.responseText);
            console.log("Status Code:", xhr.status);
            console.log("Ready State:", xhr.readyState);
            console.log("Response Headers:", xhr.getAllResponseHeaders());
            
            $("#btnSaveYearLevel").prop('disabled', false).text('บันทึก');
            
            let message = "เกิดข้อผิดพลาด: ";
            
            // วิเคราะห์ข้อผิดพลาดแบบละเอียด
            if (xhr.status === 0) {
                message += "ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้ (Network Error)";
                console.log("Possible causes: CORS issue, server down, or wrong URL");
            } else if (xhr.status === 404) {
                message += "ไม่พบไฟล์ API (" + apiUrl + ")";
                console.log("File not found. Check if year_level_api.php exists in /api/ folder");
            } else if (xhr.status === 405) {
                message += "HTTP Method ไม่ได้รับอนุญาต (Method: " + method + ")";
                console.log("Server doesn't allow " + method + " method");
            } else if (xhr.status === 500) {
                message += "เกิดข้อผิดพลาดในเซิร์ฟเวอร์";
                console.log("Server error. Check PHP error logs");
                if (xhr.responseText) {
                    console.log("Server response:", xhr.responseText);
                }
            } else if (xhr.status === 400) {
                message += "ข้อมูลที่ส่งไปไม่ถูกต้อง";
                console.log("Bad request. Check data format");
            } else {
                message += "รหัสข้อผิดพลาด " + xhr.status;
            }
            
            // ลองแปลง response เป็น JSON
            try {
                const jsonResponse = JSON.parse(xhr.responseText);
                console.log("Parsed JSON response:", jsonResponse);
                if (jsonResponse.message) {
                    message += "\nรายละเอียด: " + jsonResponse.message;
                }
            } catch (e) {
                console.log("Response is not valid JSON");
                if (xhr.responseText.length > 0) {
                    console.log("Raw response (first 500 chars):", 
                               xhr.responseText.substring(0, 500));
                }
            }
            
            alert(message);
        },
        complete: function(xhr, status) {
            console.log("=== Request Complete ===");
            console.log("Final status:", status);
            console.log("Final XHR status:", xhr.status);
        }
    });
}

// ฟังก์ชันลบข้อมูลชั้นปี
function deleteYearLevel(yearLevelId) {
    const apiUrl = "../api/year_level_api.php?id=" + yearLevelId;
    console.log("Deleting year level ID:", yearLevelId);
    console.log("Delete URL:", apiUrl);
    
    $.ajax({
        url: apiUrl,
        type: "DELETE",
        dataType: "json",
        success: function(response) {
            console.log("Delete response:", response);
            if (response.status === "success") {
                alert(response.message);
                $("#deleteConfirmModal").modal("hide");
                loadYearLevels();
            } else {
                alert(response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error("Delete Error Details:");
            console.error("Status:", status);
            console.error("Error:", error);
            console.error("Response Text:", xhr.responseText);
            console.error("Status Code:", xhr.status);
            
            let message = "เกิดข้อผิดพลาดในการลบข้อมูล";
            if (xhr.status === 404) {
                message = "ไม่พบไฟล์ API กรุณาตรวจสอบ path";
            } else if (xhr.status === 0) {
                message = "ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้";
            }
            alert(message);
        }
    });
}

// โหลดกลุ่มเมื่อหน้าเพจโหลด
$(document).ready(function() {
    // โหลดข้อมูลชั้นปี
    loadYearLevels();
    
    // กำหนดเหตุการณ์ปุ่มเพิ่มชั้นปี
    $("#btnAddYearLevel").click(function() {
        resetYearLevelForm();
        $("#yearLevelModalLabel").text("เพิ่มชั้นปีใหม่");
        $("#yearLevelModal").modal("show");
    });
    
    // กำหนดเหตุการณ์ปุ่มบันทึก
    $("#btnSaveYearLevel").click(function() {
        $(this).prop('disabled', true).text('กำลังบันทึก...');
        saveYearLevel();
    });
    
    // กำหนดเหตุการณ์ปุ่มยืนยันการลบ
    $("#btnConfirmDelete").click(function() {
        deleteYearLevel(currentYearLevelId);
    });
    
    // กำหนดเหตุการณ์เมื่อเปลี่ยนค่าในฟอร์มเพื่อแสดงตัวอย่าง
    $("#department, #class_year, #curriculum").on('input change', function() {
        updateDisplayPreview();
    });
});

// ฟังก์ชันอัปเดตตัวอย่างการแสดงผล
function updateDisplayPreview() {
    const department = $("#department").val();
    const classYear = $("#class_year").val();
    const curriculum = $("#curriculum").val();
    
    if (department && classYear && curriculum) {
        const preview = `${department} ${classYear} ${curriculum}`;
        $("#displayPreview").html(`<strong>${preview}</strong>`);
    } else {
        $("#displayPreview").html('<span class="text-muted">เลือกข้อมูลเพื่อดูตัวอย่าง</span>');
    }
}
    </script>
</body>
</html>