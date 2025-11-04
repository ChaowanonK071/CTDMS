<?php
// ตรวจสอบการเข้าสู่ระบบก่อนแสดงหน้า
// แก้ไข path ให้ถูกต้อง
require_once '../api/auth_check.php';
requireAdmin(); // ฟังก์ชันนี้จะตรวจสอบว่าเป็น admin หรือไม่
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
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>จัดการข้อมูลห้องเรียน - Kaiadmin Bootstrap 5 Admin Dashboard</title>
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
            display: inline-block !important;
            margin-bottom: 0 !important;
        }
        
        /* ทำให้ปุ่มอยู่ในบรรทัดเดียวกัน */
        .action-buttons {
            display: flex;
            gap: 5px;
            align-items: center;
            justify-content: flex-start;
        }
        
        /* ป้องกันไม่ให้ cell กว้างเกินไป */
        .table td:last-child {
            white-space: nowrap;
            width: 1%;
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
            <!-- Logo Header -->
            <?php include '../includes/header.php'; ?>
            <!-- End Logo Header -->
        </div>

        <div class="container">
          <div class="page-inner">
            <div class="page-header">
              <h3 class="fw-bold mb-3">จัดการข้อมูลห้องเรียน</h3>
              <ul class="breadcrumbs mb-3">
              </ul>
            </div>

            <div class="col-md-12">
              <div class="card">
                <div class="card-header">
                  <div class="d-flex align-items-center">
                    <button
                      class="btn btn-primary btn-round ms-auto"
                      data-bs-toggle="modal"
                      data-bs-target="#classroomModal"
                      id="btnAddClassroom"
                    >
                      <i class="fa fa-plus"></i>
                      เพิ่มห้องเรียนใหม่
                    </button>
                  </div>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table
                      id="classroomTable"
                      class="display table table-striped table-hover"
                    >
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>หมายเลขห้อง</th>
                          <th>อาคาร</th>
                          <th style="width: 10%">จัดการ</th>
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

      </div>
    </div>
    

    <!-- Modal เพิ่ม/แก้ไขห้องเรียน -->
    <div class="modal fade" id="classroomModal" tabindex="-1" aria-labelledby="classroomModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="classroomModalLabel">เพิ่มห้องเรียนใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="classroomForm">
                        <input type="hidden" id="classroom_id">
                        <div class="mb-3">
                            <label for="room_number" class="form-label">หมายเลขห้อง <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="room_number" required>
                            <div class="invalid-feedback">กรุณากรอกหมายเลขห้อง</div>
                        </div>
                        <div class="mb-3">
                            <label for="building" class="form-label">อาคาร</label>
                            <input type="text" class="form-control" id="building">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" id="btnSaveClassroom">บันทึก</button>
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
            <button
              type="button"
              class="btn-close"
              data-bs-dismiss="modal"
              aria-label="Close"
            ></button>
          </div>
          <div class="modal-body">
            <p>คุณต้องการลบห้องเรียนนี้ใช่หรือไม่?</p>
            <p class="text-danger">หมายเหตุ: การลบนี้ไม่สามารถเรียกคืนได้</p>
          </div>
          <div class="modal-footer border-0">
            <button
              type="button"
              id="btnConfirmDelete"
              class="btn btn-danger"
            >
              ลบ
            </button>
            <button
              type="button"
              class="btn btn-secondary"
              data-bs-dismiss="modal"
            >
              ยกเลิก
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->

    <!--   Core JS Files   -->
    <script src="../js/core/jquery-3.7.1.min.js"></script>
    <script src="../js/core/popper.min.js"></script>
    <script src="../js/core/bootstrap.min.js"></script>

    <!-- jQuery Scrollbar -->
    <script src="../js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>
    <!-- Datatables -->
    <script src="../js/plugin/datatables/datatables.min.js"></script>
    <!-- Kaiadmin JS -->
    <script src="../js/kaiadmin.min.js"></script>
    <!-- Custom JavaScript -->
    <script>
// ตัวแปรสำหรับเก็บข้อมูลห้องเรียน
let classroomsData = [];
let currentClassroomId = 0;
let dataTable;

// ตัวแปรสำหรับเก็บชื่อคอลัมน์ที่ถูกต้อง
let columnNames = {
    id: 'classroom_id',
    room_number: 'room_number',
    building: 'building'
};

$(document).ready(function() {
    // โหลดข้อมูลห้องเรียน
    loadClassrooms();
    
    // กำหนดเหตุการณ์ปุ่มเพิ่มห้องเรียน
    $("#btnAddClassroom").click(function() {
        resetClassroomForm();
        $("#classroomModalLabel").html('<span class="fw-mediumbold">เพิ่ม</span><span class="fw-light">ห้องเรียนใหม่</span>');
        $("#classroomModal").modal("show");
    });
    
    // กำหนดเหตุการณ์ปุ่มบันทึก
    $("#btnSaveClassroom").click(function() {
        $(this).prop('disabled', true).text('กำลังบันทึก...');
        saveClassroom();
    });
    
    // กำหนดเหตุการณ์ปุ่มยืนยันการลบ
    $("#btnConfirmDelete").click(function() {
        deleteClassroom(currentClassroomId);
    });
});

// ฟังก์ชันโหลดข้อมูลห้องเรียน
function loadClassrooms() {
    // Debug: แสดง URL ที่จะเรียก
    const apiUrl = "../api/classroom_api.php?action=getAll";
    
    $.ajax({
        url: apiUrl,
        type: "GET",
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                classroomsData = response.data;
                
                // อัปเดตชื่อคอลัมน์จาก response ถ้ามี
                if (response.debug_columns) {
                    columnNames = response.debug_columns;
                }
                
                renderClassroomsTable();
            } else {
                console.error("Error loading classrooms:", response.message);
                alert("เกิดข้อผิดพลาดในการโหลดข้อมูลห้องเรียน: " + response.message);
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
            let errorMessage = "เกิดข้อผิดพลาดในการโหลดข้อมูลห้องเรียน\n";
            if (xhr.status === 404) {
                errorMessage += "ไม่พบไฟล์ API ที่ " + apiUrl + "\n";
                errorMessage += "กรุณาตรวจสอบว่าไฟล์ classroom_api.php อยู่ในโฟลเดอร์ api/";
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
function renderClassroomsTable() {
    if (dataTable) {
        dataTable.destroy();
    }
    
    const tableBody = $("#classroomTable tbody");
    tableBody.empty();
    
    classroomsData.forEach(function(classroom) {
        // ลองใช้หลายชื่อคอลัมน์ที่เป็นไปได้
        const id = classroom[columnNames.id] || classroom.classroom_id || classroom.id;
        const roomNumber = classroom[columnNames.room_number] || classroom.room_number;
        const building = classroom[columnNames.building] || classroom.building;
        
        tableBody.append(`
            <tr>
                <td>${id}</td>
                <td>${roomNumber || 'N/A'}</td>
                <td>${building || '-'}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline-primary btn-edit" data-id="${id}">
                            <i class="bi bi-pencil"></i> แก้ไข
                        </button>
                        <button class="btn btn-sm btn-outline-danger btn-delete" data-id="${id}">
                            <i class="bi bi-trash"></i> ลบ
                        </button>
                    </div>
                </td>
            </tr>
        `);
    });
    
    // กำหนดเหตุการณ์ปุ่มแก้ไข
    $(".btn-edit").click(function() {
        const classroomId = $(this).data("id");
        editClassroom(classroomId);
    });
    
    // กำหนดเหตุการณ์ปุ่มลบ
    $(".btn-delete").click(function() {
        currentClassroomId = $(this).data("id");
        $("#deleteConfirmModal").modal("show");
    });
    
    // เริ่มต้น DataTable
    dataTable = $("#classroomTable").DataTable({
        language: {
            url: "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json"
        },
        order: [[0, "asc"]],
        columnDefs: [
            { orderable: false, targets: [3] } // คอลัมน์จัดการไม่ต้องเรียงลำดับ
        ]
    });
}

// ฟังก์ชันรีเซ็ตฟอร์ม
function resetClassroomForm() {
    $("#classroom_id").val("0");
    $("#room_number").val("");
    $("#building").val("");
    currentClassroomId = 0;
    
    // ลบ class validation
    $("#room_number").removeClass("is-invalid");
    $("#building").removeClass("is-invalid");
}

// ฟังก์ชันเรียกดูข้อมูลห้องเรียนเพื่อแก้ไข
function editClassroom(classroomId) {
    
    // หาข้อมูลห้องเรียนจาก array
    const classroom = classroomsData.find(item => {
        const id = item[columnNames.id] || item.classroom_id || item.id;
        return parseInt(id) === parseInt(classroomId);
    });
    
    if (!classroom) {
        alert("ไม่พบข้อมูลห้องเรียน");
        return;
    }
    
    currentClassroomId = classroomId;
    
    // ตรวจสอบว่ามีคอลัมน์ที่ต้องการหรือไม่
    const idValue = classroom[columnNames.id] || classroom.classroom_id || classroom.id;
    const roomNumberValue = classroom[columnNames.room_number] || classroom.room_number;
    const buildingValue = classroom[columnNames.building] || classroom.building;

    // กำหนดค่าให้กับฟอร์ม
    $("#classroom_id").val(idValue);
    $("#room_number").val(roomNumberValue);
    $("#building").val(buildingValue || '');
    
    // กำหนดชื่อ Modal
    $("#classroomModalLabel").html('<span class="fw-mediumbold">แก้ไข</span><span class="fw-light">ห้องเรียน</span>');
    $("#classroomModal").modal("show");
}

// ฟังก์ชันตรวจสอบความถูกต้องของข้อมูล
function validateForm() {
    let isValid = true;
    let errorMessage = "";
    
    // ตรวจสอบหมายเลขห้อง
    const roomNumber = $("#room_number").val().trim();
    if (!roomNumber) {
        isValid = false;
        errorMessage += "- กรุณากรอกหมายเลขห้อง\n";
        $("#room_number").addClass("is-invalid");
    } else if (roomNumber.length > 20) {
        isValid = false;
        errorMessage += "- หมายเลขห้องต้องไม่เกิน 20 ตัวอักษร\n";
        $("#room_number").addClass("is-invalid");
    } else {
        $("#room_number").removeClass("is-invalid");
    }
    
    // ตรวจสอบอาคาร (ไม่บังคับ)
    const building = $("#building").val().trim();
    if (building && building.length > 100) {
        isValid = false;
        errorMessage += "- ชื่ออาคารต้องไม่เกิน 100 ตัวอักษร\n";
        $("#building").addClass("is-invalid");
    } else {
        $("#building").removeClass("is-invalid");
    }
    
    if (!isValid) {
        alert("กรุณาแก้ไขข้อมูลต่อไปนี้:\n" + errorMessage);
    }
    
    return isValid;
}

// ฟังก์ชันบันทึกข้อมูลห้องเรียนพร้อม Debug เต็มรูปแบบ
function saveClassroom() {
    // ตรวจสอบข้อมูล
    if (!validateForm()) {
        $("#btnSaveClassroom").prop('disabled', false).text('บันทึก');
        return;
    }
    
    // เตรียมข้อมูล
    const data = {
        room_number: $("#room_number").val().trim(),
        building: $("#building").val().trim() || null
    };
    
    // ถ้าเป็นการแก้ไข
    const classroomId = $("#classroom_id").val();
    const isUpdate = classroomId && parseInt(classroomId) > 0;
    
    let apiUrl, method;
    
    if (isUpdate) {
        // อัปเดตข้อมูล
        apiUrl = `../api/classroom_api.php?action=update&id=${classroomId}`;
        method = "POST";
        
        $.ajax({
            url: apiUrl,
            type: method,
            dataType: 'json',
            data: JSON.stringify(data),
            contentType: 'application/json',
            headers: {
                'X-HTTP-Method-Override': 'PUT'
            },
            success: function(response) {
                $("#btnSaveClassroom").prop('disabled', false).text('บันทึก');
                
                if (response.status === 'success') {
                    alert('อัปเดตข้อมูลห้องเรียนเรียบร้อยแล้ว');
                    $("#classroomModal").modal('hide');
                    loadClassrooms();
                } else {
                    alert(response.message);
                }
            },
            error: handleSaveError
        });
    } else {
        // เพิ่มข้อมูลใหม่
        apiUrl = "../api/classroom_api.php?action=create";
        method = "POST";
        
        $.ajax({
            url: apiUrl,
            type: method,
            dataType: 'json',
            data: JSON.stringify(data),
            contentType: 'application/json',
            success: function(response) {
                $("#btnSaveClassroom").prop('disabled', false).text('บันทึก');
                
                if (response.status === 'success') {
                    alert('เพิ่มข้อมูลห้องเรียนเรียบร้อยแล้ว');
                    $("#classroomModal").modal('hide');
                    loadClassrooms();
                } else {
                    alert(response.message);
                }
            },
            error: handleSaveError
        });
    }
}

// ฟังก์ชันจัดการข้อผิดพลาดการบันทึก
function handleSaveError(xhr, status, error) {
    console.log("=== Save Error Details ===");
    console.log("XHR object:", xhr);
    console.log("Status:", status);
    console.log("Error:", error);
    console.log("Response Text:", xhr.responseText);
    console.log("Status Code:", xhr.status);
    
    $("#btnSaveClassroom").prop('disabled', false).text('บันทึก');
    
    let message = "เกิดข้อผิดพลาด: ";
    
    if (xhr.status === 0) {
        message += "ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้";
    } else if (xhr.status === 404) {
        message += "ไม่พบไฟล์ API";
    } else if (xhr.status === 500) {
        message += "เกิดข้อผิดพลาดในเซิร์ฟเวอร์";
    } else {
        message += "รหัสข้อผิดพลาด " + xhr.status;
    }
    
    alert(message);
}

// ฟังก์ชันลบข้อมูลห้องเรียน
function deleteClassroom(classroomId) {
    const apiUrl = `../api/classroom_api.php?action=delete&id=${classroomId}`;
    console.log("Delete URL:", apiUrl);
    
    $.ajax({
        url: apiUrl,
        type: "POST",
        dataType: "json",
        headers: {
            'X-HTTP-Method-Override': 'DELETE'
        },
        success: function(response) {
            if (response.status === "success") {
                alert(response.message || 'ลบข้อมูลห้องเรียนเรียบร้อยแล้ว');
                $("#deleteConfirmModal").modal("hide");
                loadClassrooms();
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
    </script>
</body>
</html>