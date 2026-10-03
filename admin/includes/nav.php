<?php
$current_page = basename($_SERVER['PHP_SELF']);
$base_url = '../';
?>
<!-- Inter font -->
<link rel="stylesheet" href="https://rsms.me/inter/inter.css">

<!-- Admin Sidebar -->
<div class="admin-sidebar" id="adminSidebar">
    <div class="admin-logo">
        <a href="index.php" class="text-white text-decoration-none d-flex align-items-center">
            <i class="bi bi-shield-lock fs-4 me-2"></i>
            <span class="fw-bold fs-5 sidebar-text">Admin Panel</span>
        </a>
        <button type="button" class="btn-close-sidebar d-lg-none" id="closeSidebar">
            <i class="bi bi-x text-white fs-5"></i>
        </button>
    </div>
    
    <ul class="nav flex-column mt-3">
        <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="index.php">
                <i class="bi bi-speedometer2"></i>
                <span class="sidebar-text">Kontrol Paneli</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'users.php' ? 'active' : ''; ?>" href="users.php">
                <i class="bi bi-people"></i>
                <span class="sidebar-text">İstifadəçilər</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'categories.php' ? 'active' : ''; ?>" href="categories.php">
                <i class="bi bi-tags"></i>
                <span class="sidebar-text">Kateqoriyalar</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'transactions.php' ? 'active' : ''; ?>" href="transactions.php">
                <i class="bi bi-currency-exchange"></i>
                <span class="sidebar-text">Əməliyyatlar</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'goals.php' ? 'active' : ''; ?>" href="goals.php">
                <i class="bi bi-trophy"></i>
                <span class="sidebar-text">Hədəflər</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'logs.php' ? 'active' : ''; ?>" href="logs.php">
                <i class="bi bi-journal-text"></i>
                <span class="sidebar-text">Log</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                <i class="bi bi-gear"></i>
                <span class="sidebar-text">Parametrlər</span>
            </a>
        </li>
        <li class="nav-item mt-3">
            <a class="nav-link" href="<?php echo $base_url; ?>index.php">
                <i class="bi bi-house"></i>
                <span class="sidebar-text">Əsas sayta qayıt</span>
            </a>
        </li>
    </ul>
</div>

<!-- Admin Navbar -->
<nav class="admin-navbar">
    <div class="d-flex align-items-center">
        <button type="button" class="toggle-sidebar me-3" id="toggleSidebar">
            <i class="bi bi-list"></i>
        </button>
        <h5 class="mb-0 d-none d-md-block"><?php echo getPageTitle($current_page); ?></h5>
    </div>
    
    <div class="user-menu dropdown">
        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="avatar-initials bg-primary bg-opacity-10 text-primary">
                <?php echo getInitials($_SESSION['user_name']); ?>
            </div>
            <div class="ms-2 d-none d-sm-block">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                <div class="user-role">Admin</div>
            </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
            <li><a class="dropdown-item" href="<?php echo $base_url; ?>ayarlar.php"><i class="bi bi-person me-2"></i>Profilim</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?php echo $base_url; ?>cixis.php"><i class="bi bi-box-arrow-right me-2"></i>Çıxış</a></li>
        </ul>
    </div>
</nav>

<!-- Admin Content Wrapper -->
<div class="admin-content" id="adminContent">
    <div class="admin-main">

<?php
// Helper functions
function getPageTitle($page) {
    $titles = [
        'index.php' => 'Kontrol Paneli',
        'users.php' => 'İstifadəçilər',
        'categories.php' => 'Kateqoriyalar',
        'transactions.php' => 'Əməliyyatlar',
        'goals.php' => 'Hədəflər',
        'logs.php' => 'Log',
        'settings.php' => 'Parametrlər'
    ];
    
    return $titles[$page] ?? 'Admin Panel';
}

function getInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
        if (strlen($initials) >= 2) break;
    }
    
    return $initials;
}
?> 