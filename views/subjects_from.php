<?php
require_once '../api/auth_check.php';
requireAdmin(); // เปลี่ยนจาก requireLogin เป็น requireAdmin

$userData = getUserData();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>จัดการข้อมูลวิชา - Kaiadmin Bootstrap 5 Admin Dashboard</title>
    <meta
      content="width=device-width, initial-scale=1.0, shrink-to-fit=no"
      name="viewport"
    />
    <link
      rel="icon"
      href="../img/kaiadmin/favicon.ico"
      type="image/x-icon"
    />

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
              <h3 class="fw-bold mb-3">จัดการรายวิชา</h3>
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
                      data-bs-target="#subjectModal"
                      id="btnAddSubject"
                    >
                      <i class="fa fa-plus"></i>
                      เพิ่มวิชาใหม่
                    </button>
                  </div>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table
                      id="subjectTable"
                      class="display table table-striped table-hover"
                    >
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>รหัสวิชา</th>
                          <th>ชื่อวิชา</th>
                          <th>หน่วยกิต</th>
                          <th>ประเภทวิชา</th>
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
    

    <!-- Modal เพิ่ม/แก้ไขวิชา -->
    <div class="modal fade" id="subjectModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h5 class="modal-title" id="subjectModalLabel">
              <span class="fw-mediumbold">เพิ่ม</span>
              <span class="fw-light">วิชาใหม่</span>
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
              กรอกข้อมูลวิชาใหม่ โปรดกรอกข้อมูลให้ครบถ้วน
            </p>
            <form id="subjectForm">
              <input type="hidden" id="subject_id" name="subject_id" value="0">
              
              <div class="row">
                <div class="col-sm-12">
                  <div class="form-group form-group-default">
                    <label>รหัสวิชา <span class="required">*</span></label>
                    <input
                      id="subject_code"
                      name="subject_code"
                      type="text"
                      class="form-control"
                      placeholder="เช่น CS101, MATH201"
                      maxlength="20"
                      required
                    />
                  </div>
                </div>
                <div class="col-sm-12">
                  <div class="form-group form-group-default">
                    <label>ชื่อวิชา <span class="required">*</span></label>
                    <input
                      id="subject_name"
                      name="subject_name"
                      type="text"
                      class="form-control"
                      placeholder="กรอกชื่อวิชา"
                      maxlength="255"
                      required
                    />
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group form-group-default">
                    <label>หน่วยกิต <span class="required">*</span></label>
                    <input
                      id="credits"
                      name="credits"
                      type="number"
                      class="form-control"
                      placeholder="จำนวนหน่วยกิต"
                      min="1"
                      max="9"
                      required
                    />
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group form-group-default">
                    <label>ประเภทวิชา <span class="required">*</span></label>
                    <select class="form-control" id="subject_type" name="subject_type" required>
                      <option value="">เลือกประเภทวิชา</option>
                      <option value="ทฤษฎี">ทฤษฎี</option>
                      <option value="ปฏิบัติ">ปฏิบัติ</option>
                    </select>
                  </div>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer border-0">
            <button
              type="button"
              id="btnSaveSubject"
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
            <button
              type="button"
              class="btn-close"
              data-bs-dismiss="modal"
              aria-label="Close"
            ></button>
          </div>
          <div class="modal-body">
            <p>คุณต้องการลบวิชานี้ใช่หรือไม่?</p>
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
// ตัวแปรสำหรับเก็บข้อมูลวิชา
let subjectsData = [];
let currentSubjectId = 0;
let dataTable;

$(document).ready(function() {
    // โหลดข้อมูลวิชา
    loadSubjects();
    
    // กำหนดเหตุการณ์ปุ่มเพิ่มวิชา
    $("#btnAddSubject").click(function() {
        resetSubjectForm();
        $("#subjectModalLabel").text("เพิ่มวิชาใหม่");
        $("#subjectModal").modal("show");
    });
    
    // กำหนดเหตุการณ์ปุ่มบันทึก
    $("#btnSaveSubject").click(function() {
        $(this).prop('disabled', true).text('กำลังบันทึก...');
        saveSubject();
    });
    
    // กำหนดเหตุการณ์ปุ่มยืนยันการลบ
    $("#btnConfirmDelete").click(function() {
        deleteSubject(currentSubjectId);
    });
});

// ฟังก์ชันโหลดข้อมูลวิชา
function loadSubjects() {
    // Debug: แสดง URL ที่จะเรียก
    const apiUrl = "../api/subjects_api.php";
    console.log("Trying to load data from:", apiUrl);
    
    $.ajax({
        url: apiUrl,
        type: "GET",
        dataType: "json",
        success: function(response) {
            console.log("Success response:", response);
            if (response.status === "success") {
                subjectsData = response.data;
                renderSubjectsTable();
            } else {
                console.error("Error loading subjects:", response.message);
                alert("เกิดข้อผิดพลาดในการโหลดข้อมูลวิชา: " + response.message);
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
            let errorMessage = "เกิดข้อผิดพลาดในการโหลดข้อมูลวิชา\n";
            if (xhr.status === 404) {
                errorMessage += "ไม่พบไฟล์ API ที่ " + apiUrl + "\n";
                errorMessage += "กรุณาตรวจสอบว่าไฟล์ subjects_api.php อยู่ในโฟลเดอร์ api/";
            } else if (xhr.status === 0) {
                errorMessage += "ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้";
            } else {
                errorMessage += "รหัสข้อผิดพลาด: " + xhr.status;
            }
            alert(errorMessage);
        }
    });
}

// ฟังก์ชันแสดงข้อมูลในตาราง (แก้ไขปุ่มให้อยู่บรรทัดเดียวกัน)
function renderSubjectsTable() {
    if (dataTable) {
        dataTable.destroy();
    }
    
    const tableBody = $("#subjectTable tbody");
    tableBody.empty();
    
    subjectsData.forEach(function(subject) {
        tableBody.append(`
            <tr>
                <td>${subject.subject_id}</td>
                <td>${subject.subject_code}</td>
                <td>${subject.subject_name}</td>
                <td>${subject.credits}</td>
                <td>${subject.subject_type}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline-primary btn-edit" data-id="${subject.subject_id}">
                            <i class="bi bi-pencil"></i> แก้ไข
                        </button>
                        <button class="btn btn-sm btn-outline-danger btn-delete" data-id="${subject.subject_id}">
                            <i class="bi bi-trash"></i> ลบ
                        </button>
                    </div>
                </td>
            </tr>
        `);
    });
    
    $(".btn-edit").click(function() {
        const subjectId = $(this).data("id");
        editSubject(subjectId);
    });
    
    $(".btn-delete").click(function() {
        currentSubjectId = $(this).data("id");
        $("#deleteConfirmModal").modal("show");
    });
    
    dataTable = $("#subjectTable").DataTable({
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
function resetSubjectForm() {
    $("#subject_id").val("0");
    $("#subject_code").val("");
    $("#subject_name").val("");
    $("#credits").val("");
    $("#subject_type").val("");
    currentSubjectId = 0;
}

// ฟังก์ชันเรียกดูข้อมูลวิชาเพื่อแก้ไข
function editSubject(subjectId) {
    const subject = subjectsData.find(item => parseInt(item.subject_id) === parseInt(subjectId));
    
    if (!subject) {
        alert("ไม่พบข้อมูลวิชา");
        return;
    }
    
    currentSubjectId = subjectId;
    
    $("#subject_id").val(subject.subject_id);
    $("#subject_code").val(subject.subject_code);
    $("#subject_name").val(subject.subject_name);
    $("#credits").val(subject.credits);
    $("#subject_type").val(subject.subject_type);
    
    $("#subjectModalLabel").text("แก้ไขวิชา");
    $("#subjectModal").modal("show");
}

// ฟังก์ชันตรวจสอบความถูกต้องของข้อมูล
function validateForm() {
    let isValid = true;
    let errorMessage = "";
    
    // ตรวจสอบรหัสวิชา
    const subjectCode = $("#subject_code").val().trim();
    if (!subjectCode) {
        isValid = false;
        errorMessage += "- กรุณากรอกรหัสวิชา\n";
        $("#subject_code").addClass("is-invalid");
    } else if (subjectCode.length > 20) {
        isValid = false;
        errorMessage += "- รหัสวิชาต้องไม่เกิน 20 ตัวอักษร\n";
        $("#subject_code").addClass("is-invalid");
    } else {
        $("#subject_code").removeClass("is-invalid");
    }
    
    // ตรวจสอบชื่อวิชา
    const subjectName = $("#subject_name").val().trim();
    if (!subjectName) {
        isValid = false;
        errorMessage += "- กรุณากรอกชื่อวิชา\n";
        $("#subject_name").addClass("is-invalid");
    } else if (subjectName.length > 255) {
        isValid = false;
        errorMessage += "- ชื่อวิชาต้องไม่เกิน 255 ตัวอักษร\n";
        $("#subject_name").addClass("is-invalid");
    } else {
        $("#subject_name").removeClass("is-invalid");
    }
    
    // ตรวจสอบหน่วยกิต
    const credits = $("#credits").val();
    if (!credits || parseInt(credits) < 1 || parseInt(credits) > 9) {
        isValid = false;
        errorMessage += "- หน่วยกิตต้องอยู่ระหว่าง 1-9\n";
        $("#credits").addClass("is-invalid");
    } else {
        $("#credits").removeClass("is-invalid");
    }
    
    // ตรวจสอบประเภทวิชา
    if (!$("#subject_type").val()) {
        isValid = false;
        errorMessage += "- กรุณาเลือกประเภทวิชา\n";
        $("#subject_type").addClass("is-invalid");
    } else {
        $("#subject_type").removeClass("is-invalid");
    }
    
    if (!isValid) {
        alert("กรุณาแก้ไขข้อมูลต่อไปนี้:\n" + errorMessage);
    }
    
    return isValid;
}

// ฟังก์ชันบันทึกข้อมูลวิชาพร้อม Debug เต็มรูปแบบ
function saveSubject() {
    console.log("=== Start saving subject ===");
    
    // ตรวจสอบข้อมูล
    if (!validateForm()) {
        $("#btnSaveSubject").prop('disabled', false).text('บันทึก');
        return;
    }
    
    // เตรียมข้อมูล
    const data = {
        subject_code: $("#subject_code").val(),
        subject_name: $("#subject_name").val(),
        credits: parseInt($("#credits").val()),
        subject_type: $("#subject_type").val()
    };
    
    // ถ้าเป็นการแก้ไข
    if (parseInt($("#subject_id").val()) > 0) {
        data.subject_id = parseInt($("#subject_id").val());
    }
    
    // กำหนด method ตามการกระทำ (เพิ่มหรือแก้ไข)
    const method = data.subject_id ? "PUT" : "POST";
    const apiUrl = "../api/subjects_api.php";
    
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
        timeout: 10000, // เพิ่ม timeout
        beforeSend: function(xhr) {
            console.log("=== Before Send ===");
            console.log("XHR object:", xhr);
        },
        success: function(response, textStatus, xhr) {
            console.log("=== Success Response ===");
            console.log("Response:", response);
            console.log("Text Status:", textStatus);
            console.log("XHR Status:", xhr.status);
            
            $("#btnSaveSubject").prop('disabled', false).text('บันทึก');
            
            if (response.status === "success") {
                alert(response.message);
                $("#subjectModal").modal("hide");
                loadSubjects();
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
            
            $("#btnSaveSubject").prop('disabled', false).text('บันทึก');
            
            let message = "เกิดข้อผิดพลาด: ";
            
            // วิเคราะห์ข้อผิดพลาดแบบละเอียด
            if (xhr.status === 0) {
                message += "ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้ (Network Error)";
                console.log("Possible causes: CORS issue, server down, or wrong URL");
            } else if (xhr.status === 404) {
                message += "ไม่พบไฟล์ API (" + apiUrl + ")";
                console.log("File not found. Check if subjects_api.php exists in /api/ folder");
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

// ฟังก์ชันลบข้อมูลวิชา
function deleteSubject(subjectId) {
    const apiUrl = "../api/subjects_api.php?id=" + subjectId;
    console.log("Deleting subject ID:", subjectId);
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
                loadSubjects();
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