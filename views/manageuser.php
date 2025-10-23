<?php
require_once '../api/auth_check.php';
requireAdmin(); // เปลี่ยนจาก requireLogin เป็น requireAdmin

$userData = getUserData();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>ระบบจัดการผู้ใช้ - Kaiadmin Bootstrap 5 Admin Dashboard</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <link rel="icon" href="../img/kaiadmin/favicon.ico" type="image/x-icon" />

    <!-- Fonts and icons -->
    <script src="../js/plugin/webfont/webfont.min.js"></script>
    <!-- CSS Files -->
    <link rel="stylesheet" href="../css/bootstrap.min.css" />
    <link rel="stylesheet" href="../css/plugins.min.css" />
    <link rel="stylesheet" href="../css/kaiadmin.min.css" />
    <link rel="stylesheet" href="../css/demo.css" />
    
    <!-- Bootstrap Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.7.5/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        .table-responsive {
            min-height: 400px;
        }
        .actions-column {
            width: 130px;
        }
        .btn-icon {
            padding: 0.25rem 0.5rem;
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            visibility: hidden;
            opacity: 0;
            transition: visibility 0s, opacity 0.3s linear;
        }
        .loading-overlay.active {
            visibility: visible;
            opacity: 1;
        }
        .spinner-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
        }
        .badge-active {
            background-color: #28a745;
        }
        .badge-inactive {
            background-color: #dc3545;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner-container">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">กำลังโหลด...</span>
            </div>
            <div class="mt-2">กำลังดำเนินการ กรุณารอสักครู่...</div>
        </div>
    </div>

    <div class="wrapper">

        <?php include '../includes/sidebar.php'; ?>

        <div class="main-panel">
            <div class="main-header">
                <?php include '../includes/header.php'; ?>
            </div>

            <div class="container">
                <div class="page-inner">
                    <div class="page-header">
                        <h3 class="fw-bold mb-3">จัดการข้อมูลผู้ใช้</h3>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="d-flex align-items-center">
                                    <button
                                        class="btn btn-primary btn-round ms-auto"
                                        data-bs-toggle="modal"
                                        data-bs-target="#subjectModal"
                                        id="addUserBtn"
                                        >
                                        <i class="bi bi-plus-circle"></i> เพิ่มผู้ใช้
                                    </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Search & Filter Controls -->
                                    <div class="row mb-3">
                                        <!-- <div class="col-md-4 mb-2">
                                            <select id="limitSelect" class="form-select form-select-sm d-inline-block w-auto me-2">
                                                <option value="5">5</option>
                                                <option value="10" selected>10</option>
                                                <option value="20">20</option>
                                                <option value="50">50</option>
                                            </select>
                                        </div> -->
                                        <div class="col-md-4 mb-2">
                                            <div class="input-group">
                                                <input type="text" id="searchInput" class="form-control" placeholder="ค้นหา...">
                                                <button class="btn btn-outline-primary" type="button" id="searchButton">
                                                    <i class="bi bi-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <select id="typeFilter" class="form-select">
                                                <option value="">-- ประเภทผู้ใช้ทั้งหมด --</option>
                                                <option value="admin">ผู้ดูแลระบบ</option>
                                                <option value="teacher">อาจารย์</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <select id="statusFilter" class="form-select">
                                                <option value="">-- สถานะทั้งหมด --</option>
                                                <option value="1">เปิดใช้งาน</option>
                                                <option value="0">ปิดใช้งาน</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>ID</th>
                                                    <th>ชื่อผู้ใช้</th>
                                                    <th>ชื่อ-นามสกุล</th>
                                                    <th>อีเมล</th>
                                                    <th>ประเภท</th>
                                                    <th>สถานะ</th>
                                                    <th class="actions-column">จัดการ</th>
                                                </tr>
                                            </thead>
                                            <tbody id="userTableBody">

                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Pagination -->
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div>
                                            <span id="paginationInfo">แสดง 1 - 2 จาก 2 รายการ</span>
                                        </div>
                                        <div>
                                            <nav aria-label="Page navigation">
                                                <ul class="pagination pagination-sm mb-0" id="pagination">
                                                    <li class="page-item active">
                                                        <a class="page-link" href="#">1</a>
                                                    </li>
                                                </ul>
                                            </nav>
                                        </div>
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

    <!-- Add/Edit User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel">เพิ่มผู้ใช้ใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="userForm">
                        <input type="hidden" id="userId">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">ชื่อผู้ใช้ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" required>
                        </div>
                        
                        <div class="mb-3 password-field">
                            <label for="password" class="form-label">รหัสผ่าน <span class="text-danger password-required">*</span></label>
                            <input type="password" class="form-control" id="password">
                            <small class="text-muted password-hint d-none">เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">คำนำหน้า</label>
                            <select class="form-select" id="title">
                                <option value="">-- เลือกคำนำหน้า --</option>
                                <option value="นาย">นาย</option>
                                <option value="นาง">นาง</option>
                                <option value="นางสาว">นางสาว</option>
                                <option value="ดร.">ดร.</option>
                                <option value="ผศ.">ผศ.</option>
                                <option value="ผศ.ดร.">ผศ.ดร.</option>
                                <option value="รศ.">รศ.</option>
                                <option value="ศ.">ศ.</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">ชื่อ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="lastname" class="form-label">นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="lastname" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">อีเมล <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="userType" class="form-label">ประเภทผู้ใช้ <span class="text-danger">*</span></label>
                            <select class="form-select" id="userType" required>
                                <option value="">-- เลือกประเภทผู้ใช้ --</option>
                                <option value="admin">ผู้ดูแลระบบ</option>
                                <option value="teacher">อาจารย์</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="isActive" class="form-label">สถานะ</label>
                            <select class="form-select" id="isActive">
                                <option value="1">เปิดใช้งาน</option>
                                <option value="0">ปิดใช้งาน</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" id="saveUserBtn">บันทึก</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Core JS Files -->
    <script src="../js/core/jquery-3.7.1.min.js"></script>
    <script src="../js/core/popper.min.js"></script>
    <script src="../js/core/bootstrap.min.js"></script>
    <!-- jQuery Scrollbar -->
    <script src="../js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>
    <!-- Kaiadmin JS -->
    <script src="../js/kaiadmin.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.7.5/sweetalert2.min.js"></script>
    
    <script>

        // Global variables
let currentPage = 1;
let limit = 10;
let totalPages = 0;
let totalUsers = 0;
let userModal;

// DOM elements
const userTableBody = document.getElementById('userTableBody');
const pagination = document.getElementById('pagination');
const paginationInfo = document.getElementById('paginationInfo');
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const typeFilter = document.getElementById('typeFilter');
const statusFilter = document.getElementById('statusFilter');
const limitSelect = document.getElementById('limitSelect');
const addUserBtn = document.getElementById('addUserBtn');
const saveUserBtn = document.getElementById('saveUserBtn');
const loadingOverlay = document.getElementById('loadingOverlay');

// Show loading overlay
function showLoading() {
    loadingOverlay.classList.add('active');
}

// Hide loading overlay
function hideLoading() {
    loadingOverlay.classList.remove('active');
}

// Initialize 
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap modal
    userModal = new bootstrap.Modal(document.getElementById('userModal'));
    
    // Load initial data
    loadUsers();
    
    // Event listeners
    searchButton.addEventListener('click', () => {
        currentPage = 1;
        loadUsers();
    });
    
    searchInput.addEventListener('keyup', (e) => {
        if (e.key === 'Enter') {
            currentPage = 1;
            loadUsers();
        }
    });
    
    typeFilter.addEventListener('change', () => {
        currentPage = 1;
        loadUsers();
    });
    
    statusFilter.addEventListener('change', () => {
        currentPage = 1;
        loadUsers();
    });
    
    // Only add event listener if limitSelect exists
    if (limitSelect) {
        limitSelect.addEventListener('change', () => {
            limit = parseInt(limitSelect.value);
            currentPage = 1;
            loadUsers();
        });
    }
    
    addUserBtn.addEventListener('click', () => {
        openAddUserModal();
    });
    
    saveUserBtn.addEventListener('click', saveUser);
});

// Load users data
function loadUsers() {
    showLoading();
    
    // Get filter values
    const search = searchInput.value.trim();
    const type = typeFilter.value;
    const status = statusFilter.value;
    
    // Construct API URL
    let apiUrl = '../api/manageuser_api.php?';
    apiUrl += `page=${currentPage}&limit=${limit}`;
    
    if (search) {
        apiUrl += `&search=${encodeURIComponent(search)}`;
    }
    
    if (type) {
        apiUrl += `&type=${encodeURIComponent(type)}`;
    }
    
    if (status !== '') {
        apiUrl += `&status=${encodeURIComponent(status)}`;
    }
    
    console.log('API URL:', apiUrl); // Debug
    
    // Fetch data from API
    fetch(apiUrl)
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response ok:', response.ok);
            
            // ถ้า response ไม่ ok ให้อ่าน text เพื่อดู error
            if (!response.ok) {
                return response.text().then(text => {
                    console.log('Error response:', text);
                    throw new Error(`HTTP ${response.status}: ${text}`);
                });
            }
            
            return response.text().then(text => {
                console.log('Raw response:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response: ' + text);
                }
            });
        })
        .then(data => {
            console.log('Parsed data:', data);
            
            if (data.status === 'success') {
                totalPages = data.total_pages;
                totalUsers = data.total;
                
                renderUserTable(data.users);
                renderPagination();
                updatePaginationInfo(data.page, data.limit, data.total);
            } else {
                throw new Error(data.message || 'เกิดข้อผิดพลาดในการโหลดข้อมูลผู้ใช้');
            }
        })
        .catch(error => {
            console.error('Error loading users:', error);
            Swal.fire({
                icon: 'error',
                title: 'ข้อผิดพลาด',
                text: 'ไม่สามารถโหลดข้อมูลผู้ใช้ได้: ' + error.message
            });
        })
        .finally(() => {
            hideLoading();
        });
}
// Render user table
function renderUserTable(users) {
    userTableBody.innerHTML = '';
    
    if (users.length === 0) {
        const row = document.createElement('tr');
        row.innerHTML = `<td colspan="7" class="text-center">ไม่พบข้อมูลผู้ใช้</td>`;
        userTableBody.appendChild(row);
        return;
    }
    
    users.forEach(user => {
        const row = document.createElement('tr');
        
        const userTypeDisplay = user.user_type === 'admin' ? 'ผู้ดูแลระบบ' : 'อาจารย์';
        
        const statusBadge = parseInt(user.is_active) === 1 
            ? '<span class="badge bg-success">เปิดใช้งาน</span>' 
            : '<span class="badge bg-danger">ปิดใช้งาน</span>';
                
        row.innerHTML = `
            <td>${user.user_id}</td>
            <td>${user.username}</td>
            <td>${user.fullname}</td>
            <td>${user.email}</td>
            <td>${userTypeDisplay}</td>
            <td>${statusBadge}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-primary btn-icon me-1" onclick="editUser(${user.user_id})">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-danger btn-icon" onclick="confirmDeleteUser(${user.user_id})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        
        userTableBody.appendChild(row);
    });
}

// Update pagination info text
function updatePaginationInfo(page, limit, total) {
    const start = total === 0 ? 0 : (page - 1) * limit + 1;
    const end = Math.min(page * limit, total);
    paginationInfo.textContent = `แสดง ${start} - ${end} จาก ${total} รายการ`;
}

// Render pagination controls
function renderPagination() {
    pagination.innerHTML = '';
    
    if (totalPages <= 1) {
        return;
    }
    
    const prevLi = document.createElement('li');
    prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
    prevLi.innerHTML = `
        <a class="page-link" href="#" aria-label="Previous" ${currentPage > 1 ? `onclick="changePage(${currentPage - 1}); return false;"` : ''}>
            <span aria-hidden="true">&laquo;</span>
        </a>
    `;
    pagination.appendChild(prevLi);
    
    const maxPages = 5; 
    let startPage = Math.max(1, currentPage - Math.floor(maxPages / 2));
    let endPage = Math.min(totalPages, startPage + maxPages - 1);
    
    if (endPage - startPage + 1 < maxPages) {
        startPage = Math.max(1, endPage - maxPages + 1);
    }
    
    if (startPage > 1) {
        const firstPageLi = document.createElement('li');
        firstPageLi.className = 'page-item';
        firstPageLi.innerHTML = `
            <a class="page-link" href="#" onclick="changePage(1); return false;">1</a>
        `;
        pagination.appendChild(firstPageLi);
        
        if (startPage > 2) {
            const ellipsisLi = document.createElement('li');
            ellipsisLi.className = 'page-item disabled';
            ellipsisLi.innerHTML = '<a class="page-link" href="#">...</a>';
            pagination.appendChild(ellipsisLi);
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        const pageLi = document.createElement('li');
        pageLi.className = `page-item ${i === currentPage ? 'active' : ''}`;
        pageLi.innerHTML = `
            <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
        `;
        pagination.appendChild(pageLi);
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            const ellipsisLi = document.createElement('li');
            ellipsisLi.className = 'page-item disabled';
            ellipsisLi.innerHTML = '<a class="page-link" href="#">...</a>';
            pagination.appendChild(ellipsisLi);
        }
        
        const lastPageLi = document.createElement('li');
        lastPageLi.className = 'page-item';
        lastPageLi.innerHTML = `
            <a class="page-link" href="#" onclick="changePage(${totalPages}); return false;">${totalPages}</a>
        `;
        pagination.appendChild(lastPageLi);
    }
    
    const nextLi = document.createElement('li');
    nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
    nextLi.innerHTML = `
        <a class="page-link" href="#" aria-label="Next" ${currentPage < totalPages ? `onclick="changePage(${currentPage + 1}); return false;"` : ''}>
            <span aria-hidden="true">&raquo;</span>
        </a>
    `;
    pagination.appendChild(nextLi);
}

function changePage(page) {
    if (page < 1 || page > totalPages || page === currentPage) {
        return;
    }
    
    currentPage = page;
    loadUsers();
}

// ฟังก์ชัน openAddUserModal ที่แก้ไขแล้ว
function openAddUserModal() {
    document.getElementById('userModalLabel').textContent = 'เพิ่มผู้ใช้ใหม่';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';

    // ไม่ต้อง required password ตอนเพิ่มผู้ใช้
    const passwordElement = document.getElementById('password');
    if (passwordElement) {
        passwordElement.removeAttribute('required');
    }
    const passwordRequired = document.querySelector('.password-required');
    const passwordHint = document.querySelector('.password-hint');
    if (passwordRequired) passwordRequired.classList.add('d-none');
    if (passwordHint) passwordHint.classList.add('d-none');

    userModal.show();
}

// ฟังก์ชัน editUser ที่แก้ไขแล้ว
function editUser(userId) {
    showLoading();
    
    fetch(`../api/manageuser_api.php?id=${userId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                const user = data.data;
                
                // ตรวจสอบและกำหนดค่าให้ form elements อย่างปลอดภัย
                const elements = {
                    userId: document.getElementById('userId'),
                    username: document.getElementById('username'),
                    title: document.getElementById('title'),
                    name: document.getElementById('name'),
                    lastname: document.getElementById('lastname'),
                    email: document.getElementById('email'),
                    userType: document.getElementById('userType'),
                    isActive: document.getElementById('isActive'),
                    password: document.getElementById('password')
                };
                
                // ตรวจสอบว่า elements ทั้งหมดมีอยู่จริง
                const missingElements = Object.keys(elements).filter(key => !elements[key]);
                if (missingElements.length > 0) {
                    console.error('Missing elements:', missingElements);
                    Swal.fire({
                        icon: 'error',
                        title: 'ข้อผิดพลาด',
                        text: 'เกิดข้อผิดพลาดในการโหลดฟอร์ม'
                    });
                    return;
                }
                
                // Set form values
                elements.userId.value = user.user_id || '';
                elements.username.value = user.username || '';
                elements.title.value = user.title || '';
                elements.name.value = user.name || '';
                elements.lastname.value = user.lastname || '';
                elements.email.value = user.email || '';
                elements.userType.value = user.user_type || '';
                elements.isActive.value = user.is_active || '1';
                
                // Reset password field
                elements.password.value = '';
                elements.password.removeAttribute('required');
                
                const passwordRequired = document.querySelector('.password-required');
                const passwordHint = document.querySelector('.password-hint');
                
                if (passwordRequired) passwordRequired.classList.add('d-none');
                if (passwordHint) passwordHint.classList.remove('d-none');
                
                // Update modal title
                const modalTitle = document.getElementById('userModalLabel');
                if (modalTitle) modalTitle.textContent = 'แก้ไขข้อมูลผู้ใช้';
                
                // Open modal
                userModal.show();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: data.message || 'ไม่สามารถดึงข้อมูลผู้ใช้ได้'
                });
            }
        })
        .catch(error => {
            console.error('Error fetching user:', error);
            Swal.fire({
                icon: 'error',
                title: 'ข้อผิดพลาด',
                text: 'ไม่สามารถดึงข้อมูลผู้ใช้ได้ กรุณาลองใหม่อีกครั้ง'
            });
        })
        .finally(() => {
            hideLoading();
        });
}

// ฟังก์ชัน saveUser ที่แก้ไขแล้วพร้อมการตรวจสอบ
function saveUser() {
    // Basic form validation
    const form = document.getElementById('userForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // ตรวจสอบและดึงข้อมูลจากฟอร์มอย่างปลอดภัย
    const userIdElement = document.getElementById('userId');
    const usernameElement = document.getElementById('username');
    const passwordElement = document.getElementById('password');
    const titleElement = document.getElementById('title');
    const nameElement = document.getElementById('name');
    const lastnameElement = document.getElementById('lastname');
    const emailElement = document.getElementById('email');
    const userTypeElement = document.getElementById('userType');
    const isActiveElement = document.getElementById('isActive');
    
    // ตรวจสอบว่า elements ทั้งหมดมีอยู่จริง
    if (!userIdElement || !usernameElement || !passwordElement || !titleElement || 
        !nameElement || !lastnameElement || !emailElement || !userTypeElement || 
        !isActiveElement) {
        
        console.error('ไม่พบ element บางตัวในฟอร์ม');
        Swal.fire({
            icon: 'error',
            title: 'ข้อผิดพลาด',
            text: 'เกิดข้อผิดพลาดในการอ่านข้อมูลฟอร์ม กรุณาลองใหม่'
        });
        return;
    }
    
    // Get form data
    const userId = userIdElement.value;
    const username = usernameElement.value.trim();
    const password = passwordElement.value;
    const title = titleElement.value;
    const name = nameElement.value.trim();
    const lastname = lastnameElement.value.trim();
    const email = emailElement.value.trim();
    const userType = userTypeElement.value;
    const isActive = isActiveElement.value;
    
    // Validation
    if (!username) {
        Swal.fire({
            icon: 'error',
            title: 'ข้อผิดพลาด',
            text: 'กรุณากรอกชื่อผู้ใช้'
        });
        return;
    }
    
    if (!name) {
        Swal.fire({
            icon: 'error',
            title: 'ข้อผิดพลาด',
            text: 'กรุณากรอกชื่อ'
        });
        return;
    }
    
    if (!lastname) {
        Swal.fire({
            icon: 'error',
            title: 'ข้อผิดพลาด',
            text: 'กรุณากรอกนามสกุล'
        });
        return;
    }
    
    if (!email) {
        Swal.fire({
            icon: 'error',
            title: 'ข้อผิดพลาด',
            text: 'กรุณากรอกอีเมล'
        });
        return;
    }
    
    if (!userType) {
        Swal.fire({
            icon: 'error',
            title: 'ข้อผิดพลาด',
            text: 'กรุณาเลือกประเภทผู้ใช้'
        });
        return;
    }
    
    // Prepare data for API request
    const userData = {
        username: username,
        title: title,
        name: name,
        lastname: lastname,
        email: email,
        user_type: userType,
        is_active: isActive
    };
    
    // Add password if provided (required for new users)
    if (password) {
        userData.password = password;
    }
    
    // Debug - แสดงข้อมูลที่จะส่ง
    console.log("Data to be sent:", JSON.stringify(userData));
    
    // Show loading
    showLoading();
    
    // Determine if creating or updating
    const isUpdate = userId ? true : false;
    const method = isUpdate ? 'PUT' : 'POST';
    const url = isUpdate ? `../api/manageuser_api.php?id=${userId}` : '../api/manageuser_api.php';
    
    // Send request to API
    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(userData)
    })
        .then(response => {
            return response.json().then(data => {
                if (!response.ok) {
                    throw new Error(data.message || 'เกิดข้อผิดพลาดในการบันทึกข้อมูล');
                }
                return data;
            });
        })
        .then(data => {
            if (data.status === 'success') {
                // Close modal
                userModal.hide();
                
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: data.message || (isUpdate ? 'อัปเดตข้อมูลผู้ใช้เรียบร้อยแล้ว' : 'สร้างผู้ใช้ใหม่เรียบร้อยแล้ว')
                });
                
                // Reload users data
                loadUsers();
            } else {
                throw new Error(data.message || 'เกิดข้อผิดพลาดในการบันทึกข้อมูล');
            }
        })
        .catch(error => {
            console.error('Error saving user:', error);
            Swal.fire({
                icon: 'error',
                title: 'ข้อผิดพลาด',
                text: error.message || 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง'
            });
        })
        .finally(() => {
            hideLoading();
        });
}

// ฟังก์ชันยืนยันการลบผู้ใช้
function confirmDeleteUser(userId) {
    Swal.fire({
        title: 'คุณแน่ใจหรือไม่?',
        text: "การดำเนินการนี้ไม่สามารถยกเลิกได้!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            deleteUser(userId);
        }
    });
}

// ฟังก์ชันลบผู้ใช้
function deleteUser(userId) {
    showLoading();
    
    // ส่งคำขอไปยัง API เพื่อลบผู้ใช้
    fetch(`../api/manageuser_api.php?id=${userId}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        return response.json().then(data => {
            if (!response.ok) {
                throw new Error(data.message || 'เกิดข้อผิดพลาดในการลบผู้ใช้');
            }
            return data;
        });
    })
    .then(data => {
        if (data.status === 'success') {
            // แสดงข้อความสำเร็จ
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ',
                text: data.message || 'ลบผู้ใช้เรียบร้อยแล้ว'
            });
            
            // โหลดข้อมูลผู้ใช้ใหม่
            loadUsers();
        } else {
            throw new Error(data.message || 'เกิดข้อผิดพลาดในการลบผู้ใช้');
        }
    })
    .catch(error => {
        console.error('Error deleting user:', error);
        Swal.fire({
            icon: 'error',
            title: 'ข้อผิดพลาด',
            text: error.message || 'ไม่สามารถลบผู้ใช้ได้ กรุณาลองใหม่อีกครั้ง'
        });
    })
    .finally(() => {
        hideLoading();
    });
}
    </script>
</body>
</html>