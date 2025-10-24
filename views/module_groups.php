<?php
require_once '../api/auth_check.php';
requireAdmin();

$userData = getUserData();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>จัดการกลุ่มโมดูล - Kaiadmin Bootstrap 5 Admin Dashboard</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport"/>
    <link rel="icon" href="../img/kaiadmin/favicon.ico" type="image/x-icon"/>
    <!-- Fonts and icons -->
    <script src="../js/plugin/webfont/webfont.min.js"></script>
    <!-- CSS Files -->
    <link rel="stylesheet" href="../css/bootstrap.min.css" />
    <link rel="stylesheet" href="../css/plugins.min.css" />
    <link rel="stylesheet" href="../css/kaiadmin.min.css" />
    <link rel="stylesheet" href="../css/demo.css" />
    <style>
        body { font-family: 'Sarabun', sans-serif; }
        .required { color: red; }
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
        #moduleGroupsTable td:last-child {
            min-width: 120px;
            width: 120px;
        }
        #selectYearLevels + .select2-container--default .select2-selection--multiple {
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            min-height: 38px;
            padding-top: 4px;
        }
        #selectYearLevels + .select2-container--default .select2-selection--multiple .select2-search__field {
            background: transparent !important;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-panel">
        <div class="main-header">
            <?php include '../includes/header.php'; ?>
        </div>
        <div class="container">
            <div class="page-inner">
                <div class="page-header">
                    <h3 class="fw-bold mb-3">จัดการกลุ่มโมดูล</h3>
                </div>
                <div class="col-md-12">
                    <div class="card mb-4">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#moduleGroupModal" id="btnAddModuleGroup">
                                    <i class="fa fa-plus"></i> เพิ่มกลุ่มโมดูล
                                </button>
                                <button class="btn btn-primary btn-round ms-2" data-bs-toggle="modal" data-bs-target="#moduleModal" id="btnAddModule">
                                    <i class="fa fa-plus"></i> เพิ่มโมดูลใหม่
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="moduleGroupsTable" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>ชื่อกลุ่มโมดูล</th>
                                            <th>โมดูล</th>
                                            <th>ชั้นปี</th>
                                            <th>ตัวอย่างการแสดงผล</th>
                                            <th style="width: 120px">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- ข้อมูลกลุ่มโมดูลจะแสดงที่นี่ -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                                        <!-- ตารางแสดงโมดูลที่มีอยู่ -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">รายการโมดูลที่มีอยู่</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="modulesTable" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>ชื่อโมดูล</th>
                                            <th>รายละเอียด</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- ข้อมูลโมดูลจะแสดงที่นี่ -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal เพิ่ม/แก้ไขกลุ่มโมดูล -->
        <div class="modal fade" id="moduleGroupModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header border-0">
                        <h5 class="modal-title" id="moduleGroupModalLabel">
                            <span class="fw-mediumbold">เพิ่มกลุ่มโมดูล</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="moduleGroupForm">
                            <input type="hidden" id="group_id" name="group_id" value="0">
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="form-group form-group-default">
                                        <label>เลือกโมดูล <span class="required">*</span></label>
                                        <select id="selectModule" class="form-control" required></select>
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="form-group form-group-default">
                                        <label>ชื่อกลุ่มโมดูล <span class="required">*</span></label>
                                        <input type="text" id="inputGroupName" class="form-control" placeholder="เช่น ปี3, ปี, ..." required>
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="form-group form-group-default">
                                        <label>เลือกชั้นปี <span class="required">*</span></label>
                                        <select id="selectYearLevels" class="form-control border-0" multiple="multiple" style="width:100%;background:transparent;box-shadow:none;" required></select>
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="form-group form-group-default">
                                        <label>ตัวอย่างการแสดงผล</label>
                                        <div class="form-control bg-light" id="modalDisplayExample" style="min-height: 38px; display: flex; align-items: center;">
                                            <span class="text-primary">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" id="btnSaveModuleGroup" class="btn btn-primary">บันทึก</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">ยกเลิก</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal เพิ่ม/แก้ไขโมดูล -->
        <div class="modal fade" id="moduleModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header border-0">
                        <h5 class="modal-title" id="moduleModalLabel">
                            <span class="fw-mediumbold">เพิ่ม</span>
                            <span class="fw-light">โมดูลใหม่</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="moduleForm">
                            <input type="hidden" id="module_id" name="module_id" value="0">
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="form-group form-group-default">
                                        <label>ชื่อโมดูล <span class="required">*</span></label>
                                        <input type="text" id="module_name" name="module_name" class="form-control" required maxlength="255" placeholder="ชื่อโมดูล">
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="form-group form-group-default">
                                        <label>รายละเอียด</label>
                                        <textarea id="description" name="description" class="form-control" rows="2" maxlength="1000" placeholder="รายละเอียดเพิ่มเติม"></textarea>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" id="btnSaveModule" class="btn btn-primary">บันทึก</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">ยกเลิก</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal ยืนยันการลบ -->
        <div class="modal fade" id="deleteModuleGroupConfirmModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header border-0">
                        <h5 class="modal-title" id="deleteModuleGroupConfirmModalLabel">
                            <span class="fw-mediumbold">ยืนยัน</span>
                            <span class="fw-light">การลบ</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>คุณต้องการลบกลุ่มโมดูลนี้ใช่หรือไม่?</p>
                        <p class="text-danger">หมายเหตุ: การลบนี้ไม่สามารถเรียกคืนได้</p>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" id="btnConfirmDeleteModuleGroup" class="btn btn-danger"> ลบ
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../includes/footer.php'; ?>
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
let allModules = [];
let allYearLevels = [];
let moduleGroupsData = [];
let currentGroupId = 0;
let moduleGroupsTable;
let modulesTable;

// โหลดโมดูลทั้งหมด
function loadAllModulesForMap(callback) {
    $.getJSON("../api/year_level_api.php?action=get_all_modules", function(res) {
        if (res.status === "success") {
            allModules = res.data;
            let html = "";
            allModules.forEach(m => {
                html += `<option value="${m.module_id}">${m.module_name}</option>`;
            });
            $("#selectModule").html(html);
            if (callback) callback();
        }
    });
}

// โหลดชั้นปีทั้งหมด (select2)
function loadAllYearLevelsForMap(callback) {
    $.getJSON("../api/year_level_api.php?action=get_all_year_levels", function(res) {
        if (res.status === "success") {
            allYearLevels = res.data;
            let options = allYearLevels.map(y => ({
                id: y.year_level_id,
                text: `${y.department} ${y.class_year} (${y.curriculum})`
            }));
            $("#selectYearLevels").empty().select2({
                data: options,
                dropdownParent: $('#moduleGroupModal'),
                width: '100%',
                placeholder: "เลือกชั้นปี",
                allowClear: true
            });
            if (callback) callback();
        }
    });
}

// Helper: แปลง module_id เป็นชื่อโมดูล
function getModuleName(module_id) {
    let m = allModules.find(m => m.module_id == module_id);
    return m ? m.module_name : module_id;
}

// Helper: แปลง year_levels array เป็น string
function getYearLevelsText(year_levels) {
    return year_levels.map(y => `${y.department} ${y.class_year} (${y.curriculum})`).join(", ");
}

// อัปเดตตัวอย่างการแสดงผลใน Modal
function updateModalDisplayExample() {
    let groupName = $("#inputGroupName").val().trim();
    let moduleId = $("#selectModule").val();
    let moduleName = getModuleName(moduleId);
    let displayExample = `${groupName ? groupName : "-"} ${moduleName ? moduleName : ""}`;
    $("#modalDisplayExample").html(`<span class="text-primary">${displayExample}</span>`);
}

// โหลดข้อมูลกลุ่มโมดูล
function loadModuleGroups() {
    $.getJSON("../api/year_level_api.php?action=get_module_groups", function(res) {
        if (res.status === "success") {
            moduleGroupsData = res.data;
            renderModuleGroupsTable();
        } else {
            alert("เกิดข้อผิดพลาดในการโหลดข้อมูลกลุ่มโมดูล: " + res.message);
        }
    }).fail(function(xhr) {
        alert("เกิดข้อผิดพลาดในการโหลดข้อมูลกลุ่มโมดูล");
    });
}

// แสดงข้อมูลในตาราง
function renderModuleGroupsTable() {
    if (moduleGroupsTable) {
        moduleGroupsTable.destroy();
    }
    const tableBody = $("#moduleGroupsTable tbody");
    tableBody.empty();
    moduleGroupsData.forEach(function(group) {
        let displayExample = `${group.group_name ? group.group_name : "-"} ${getModuleName(group.module_id)}`;
        tableBody.append(`
            <tr>
                <td>${group.group_id}</td>
                <td>${group.group_name ? group.group_name : "-"}</td>
                <td>${getModuleName(group.module_id)}</td>
                <td>${getYearLevelsText(group.year_levels)}</td>
                <td><span class="text-primary">${displayExample}</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline-primary btn-edit-group" data-id="${group.group_id}" title="แก้ไข">แก้ไข</button>
                        <button class="btn btn-sm btn-outline-danger btn-delete-group" data-id="${group.group_id}" title="ลบ">ลบ</button>
                    </div>
                </td>
            </tr>
        `);
    });
    $(".btn-edit-group").click(function() {
        const groupId = $(this).data("id");
        editModuleGroup(groupId);
    });
    $(".btn-delete-group").click(function() {
        currentGroupId = $(this).data("id");
        $("#deleteModuleGroupConfirmModal").modal("show");
    });
    moduleGroupsTable = $("#moduleGroupsTable").DataTable({
        language: {
            url: "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json"
        },
        order: [[0, "asc"]],
        columnDefs: [
            { orderable: false, targets: [5] }
        ]
    });
}

// รีเซ็ตฟอร์ม
function resetModuleGroupForm() {
    $("#group_id").val("0");
    $("#selectModule").val("");
    $("#inputGroupName").val("");
    $("#selectYearLevels").val(null).trigger('change');
    updateModalDisplayExample();
}

// แก้ไขกลุ่มโมดูล
function editModuleGroup(groupId) {
    const group = moduleGroupsData.find(item => parseInt(item.group_id) === parseInt(groupId));
    if (!group) {
        alert("ไม่พบข้อมูลกลุ่มโมดูล");
        return;
    }
    $("#group_id").val(group.group_id);
    $("#selectModule").val(group.module_id);
    $("#inputGroupName").val(group.group_name);
    // set year levels
    let yearLevelIds = group.year_levels.map(y => y.year_level_id);
    $("#selectYearLevels").val(yearLevelIds).trigger('change');
    $("#moduleGroupModalLabel").html('<span class="fw-mediumbold">แก้ไข</span> <span class="fw-light">กลุ่มโมดูล</span>');
    updateModalDisplayExample();
    $("#moduleGroupModal").modal("show");
}

// ตรวจสอบฟอร์ม
function validateModuleGroupForm() {
    let isValid = true;
    let errorMessage = "";
    if (!$("#selectModule").val()) {
        isValid = false;
        errorMessage += "- กรุณาเลือกโมดูล\n";
        $("#selectModule").addClass("is-invalid");
    } else {
        $("#selectModule").removeClass("is-invalid");
    }
    if (!$("#inputGroupName").val().trim()) {
        isValid = false;
        errorMessage += "- กรุณากรอกชื่อกลุ่มโมดูล\n";
        $("#inputGroupName").addClass("is-invalid");
    } else {
        $("#inputGroupName").removeClass("is-invalid");
    }
    if (!$("#selectYearLevels").val() || $("#selectYearLevels").val().length === 0) {
        isValid = false;
        errorMessage += "- กรุณาเลือกชั้นปี\n";
        $("#selectYearLevels").addClass("is-invalid");
    } else {
        $("#selectYearLevels").removeClass("is-invalid");
    }
    if (!isValid) {
        alert("กรุณาแก้ไขข้อมูลต่อไปนี้:\n" + errorMessage);
    }
    return isValid;
}

// บันทึกกลุ่มโมดูล (เพิ่ม/แก้ไข)
function saveModuleGroup() {
    if (!validateModuleGroupForm()) {
        $("#btnSaveModuleGroup").prop('disabled', false).text('บันทึก');
        return;
    }
    const data = {
        module_id: $("#selectModule").val(),
        year_level_ids: $("#selectYearLevels").val(),
        group_name: $("#inputGroupName").val().trim()
    };
    const group_id = $("#group_id").val();
    let url = "../api/year_level_api.php?action=create_module_group";
    let method = "POST";
    if (group_id && group_id !== "0") {
        // สำหรับการแก้ไข สามารถเพิ่ม endpoint update ได้ในอนาคต
        alert("ยังไม่รองรับการแก้ไขกลุ่มโมดูล (เฉพาะเพิ่มใหม่และลบ)");
        $("#btnSaveModuleGroup").prop('disabled', false).text('บันทึก');
        return;
    }
    $.ajax({
        url: url,
        type: method,
        dataType: "json",
        contentType: "application/json",
        data: JSON.stringify(data),
        success: function(response) {
            $("#btnSaveModuleGroup").prop('disabled', false).text('บันทึก');
            if (response.status === "success") {
                alert(response.message);
                $("#moduleGroupModal").modal("hide");
                loadModuleGroups();
            } else {
                alert(response.message);
            }
        },
        error: function(xhr) {
            $("#btnSaveModuleGroup").prop('disabled', false).text('บันทึก');
            alert("เกิดข้อผิดพลาดในการบันทึกข้อมูลกลุ่มโมดูล");
        }
    });
}

// ลบกลุ่มโมดูล
function deleteModuleGroup(groupId) {
    alert("ยังไม่รองรับการลบกลุ่มโมดูลผ่าน API");

}

// เพิ่มฟังก์ชันสำหรับเพิ่มโมดูลใหม่
function resetModuleForm() {
    $("#module_id").val("0");
    $("#module_name").val("");
    $("#description").val("");
}
function validateModuleForm() {
    let isValid = true;
    let errorMessage = "";
    if (!$("#module_name").val().trim()) {
        isValid = false;
        errorMessage += "- กรุณากรอกชื่อโมดูล\n";
        $("#module_name").addClass("is-invalid");
    } else {
        $("#module_name").removeClass("is-invalid");
    }
    if (!isValid) {
        alert("กรุณาแก้ไขข้อมูลต่อไปนี้:\n" + errorMessage);
    }
    return isValid;
}
function saveModule() {
    if (!validateModuleForm()) {
        $("#btnSaveModule").prop('disabled', false).text('บันทึก');
        return;
    }
    const data = {
        module_name: $("#module_name").val().trim(),
        description: $("#description").val().trim()
    };
    const method = "POST";
    $.ajax({
        url: "../api/module_api.php",
        type: method,
        dataType: "json",
        contentType: "application/json",
        data: JSON.stringify(data),
        success: function(response) {
            $("#btnSaveModule").prop('disabled', false).text('บันทึก');
            if (response.status === "success") {
                alert(response.message);
                $("#moduleModal").modal("hide");
                // reload modules for select
                loadAllModulesForMap();
            } else {
                alert(response.message);
            }
        },
        error: function(xhr) {
            $("#btnSaveModule").prop('disabled', false).text('บันทึก');
            alert("เกิดข้อผิดพลาดในการบันทึกข้อมูลโมดูล");
        }
    });
}

// โหลดและแสดงตารางโมดูลที่มีอยู่
function loadModulesTable() {
    $.getJSON("../api/year_level_api.php?action=get_all_modules", function(res) {
        if (res.status === "success") {
            let modules = res.data;
            const tableBody = $("#modulesTable tbody");
            tableBody.empty();
            modules.forEach(function(module) {
                tableBody.append(`
                    <tr>
                        <td>${module.module_id}</td>
                        <td>${module.module_name}</td>
                        <td>${module.description ? module.description : ""}</td>
                    </tr>
                `);
            });
            if (modulesTable) {
                modulesTable.destroy();
            }
            modulesTable = $("#modulesTable").DataTable({
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json"
                },
                order: [[0, "asc"]],
                columnDefs: [
                    { orderable: false, targets: [] }
                ]
            });
        }
    });
}

$(document).ready(function() {
    loadModulesTable();
    loadAllModulesForMap(function() {
        loadAllYearLevelsForMap(function() {
            updateModalDisplayExample();
        });
        loadModuleGroups();
    });

    $("#btnAddModuleGroup").click(function() {
        resetModuleGroupForm();
        $("#moduleGroupModalLabel").html('<span class="fw-mediumbold">เพิ่ม</span> <span class="fw-light">กลุ่มโมดูล</span>');
        $("#moduleGroupModal").modal("show");
    });

    $("#btnSaveModuleGroup").click(function() {
        $(this).prop('disabled', true).text('กำลังบันทึก...');
        saveModuleGroup();
    });

    $("#btnConfirmDeleteModuleGroup").click(function() {
        deleteModuleGroup(currentGroupId);
    });

    $("#inputGroupName, #selectModule").on('input change', function() {
        updateModalDisplayExample();
    });

    $("#selectModule").change(function() {
        $("#inputGroupName").val("");
        $("#selectYearLevels").val(null).trigger('change');
        updateModalDisplayExample();
    });

    // เพิ่ม event สำหรับปุ่มเพิ่มโมดูล
    $("#btnAddModule").click(function() {
        resetModuleForm();
        $("#moduleModalLabel").html('<span class="fw-mediumbold">เพิ่ม</span> <span class="fw-light">โมดูลใหม่</span>');
        $("#moduleModal").modal("show");
    });
    $("#btnSaveModule").click(function() {
        $(this).prop('disabled', true).text('กำลังบันทึก...');
        saveModule();
    });
});
</script>
</body>
</html>