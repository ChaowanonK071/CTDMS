<div class="main-header">
    <nav class="navbar navbar-header navbar-header-transparent navbar-expand-lg border-bottom">
        <div class="container-fluid">
            <ul class="navbar-nav topbar-nav ms-md-auto align-items-center">
                 <li class="nav-item topbar-user dropdown hidden-caret">
                    <a class="dropdown-toggle profile-pic" data-bs-toggle="dropdown" href="#" aria-expanded="false">
                        <span class="profile-username">
                            <span class="op-7">สวัสดี,</span>
                            <span class="fw-bold"><?php echo htmlspecialchars($userData['name'] ?? 'ผู้ใช้งาน'); ?></span>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-user animated fadeIn">
                        <div class="dropdown-user-scroll scrollbar-outer">
                            <li>
                                <div class="user-box">
                                    <div class="u-text">
                                        <h4><?php echo htmlspecialchars($userData['title'] . $userData['name'] . ' ' ?? 'ผู้ใช้งาน'); ?></h4>
                                        <p class="text-muted"><?php echo htmlspecialchars($userData['email'] ?? ''); ?></p>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../login.php">ออกจากระบบ</a>
                            </li>
                        </div>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
</div>