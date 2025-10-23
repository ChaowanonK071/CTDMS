<?php
$user_type = $_SESSION['user_type'] ?? '';
?>
<script>
  WebFont.load({
    google: {
      families: ["Public Sans:300,400,500,600,700"]
    },
    custom: {
      families: [
        "Font Awesome 5 Solid",
        "Font Awesome 5 Regular",
        "Font Awesome 5 Brands",
        "simple-line-icons",
      ],
      urls: ["../css/fonts.min.css"],
    },
    active: function() {
      sessionStorage.fonts = true;
    },
  });
</script>
<div class="sidebar" data-background-color="dark">
  <div class="sidebar-logo">
    <div class="logo-header" data-background-color="dark">
      <a href="../index.php" class="logo">
        <img
          src="../img/kaiadmin/logo_light.svg"
          alt="navbar brand"
          class="navbar-brand"
          height="20" />
      </a>
      <div class="nav-toggle">
        <button class="btn btn-toggle toggle-sidebar">
          <i class="gg-menu-right"></i>
        </button>
        <button class="btn btn-toggle sidenav-toggler">
          <i class="gg-menu-left"></i>
        </button>
      </div>
      <button class="topbar-toggler more">
        <i class="gg-more-vertical-alt"></i>
      </button>
    </div>
  </div>
  <div class="sidebar-wrapper scrollbar scrollbar-inner">
    <div class="sidebar-content">
      <ul class="nav nav-secondary">
        <li class="nav-item">
          <a href="../index.php">
            <i class="fas fa-home"></i>
            <p>หน้าหลัก</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="../views/class_sessions_calendar.php">
            <i class="fas fa-calendar-alt"></i>
            <p>ปฏิทินการศึกษา</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="../views/compensation.php">
            <i class="fas fa-calendar-plus"></i>
            <p>การสอนชดเชย</p>
          </a>
        </li>
        <li class="nav-section">
          <span class="sidebar-mini-icon">
            <i class="fa fa-ellipsis-h"></i>
          </span>
          <?php if ($user_type === 'admin'): ?>
          <h4 class="text-section">จัดการข้อมูล</h4>
        </li>
        <li class="nav-item">
          <a href="../views/holiday_management.php">
            <i class="fas fa-graduation-cap"></i>
            <p>ปีการศึกษา</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="../views/subjects_from.php">
            <i class="fas fa-th-list"></i>
            <p>รายวิชา</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="../views/year_levels.php">
            <i class="fas fa-layer-group"></i>
            <p>ชั้นปี</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="../views/module_groups.php">
            <i class="fas fa-layer-group"></i>
            <p>กลุ่มโมดูล</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="../views/classroom.php">
            <i class="fas fa-table"></i>
            <p>ห้องเรียน</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="../views/manageuser.php">
            <i class="fas fa-pen-square"></i>
            <p>ผู้ใช้</p>
          </a>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</div>