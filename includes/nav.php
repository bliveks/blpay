<?php
// Bu kodu məsələn header.php-də və ya bütün auth-lı səhifələrə include olunan bir yerdə yerləşdir
if (isset($_SESSION['user_id']) && isset($_COOKIE['remember_user'])) {
    list($user_id, $token) = explode(':', $_COOKIE['remember_user']);

    $stmt = $conn->prepare("SELECT id FROM remember_tokens WHERE user_id = ? AND token = ?");
    $stmt->execute([$user_id, $token]);

    if (!$stmt->fetch()) {
        // Token artıq mövcud deyil → sessiyanı bağla
        session_unset();
        session_destroy();
        setcookie('remember_user', '', time() - 3600, '/');

        header("Location: login.php");
        exit();
    }
}

$current_page = basename($_SERVER['PHP_SELF']);

// Get available months for dropdown
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT DISTINCT DATE_FORMAT(tarix, '%Y-%m') as ay
    FROM (
        SELECT tarix FROM gelirler WHERE istifadeci_id = ?
        UNION
        SELECT tarix FROM xercler WHERE istifadeci_id = ?
        UNION
        SELECT DATE_FORMAT(NOW(), '%Y-%m-01') as tarix
    ) t
    ORDER BY ay DESC
");
$stmt->execute([$user_id, $user_id]);
$available_months = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique months from transactions
$stmt = $conn->prepare("
    SELECT DISTINCT DATE_FORMAT(tarix, '%Y-%m-01') as tarix
    FROM (
        SELECT tarix FROM gelirler WHERE istifadeci_id = ?
        UNION
        SELECT tarix FROM xercler WHERE istifadeci_id = ?
        UNION
        SELECT DATE_FORMAT(NOW(), '%Y-%m-01') as tarix
    ) as transactions
    ORDER BY tarix DESC
");
$stmt->execute([$user_id, $user_id]);
$months = $stmt->fetchAll();

// Get selected month from GET parameter or session
$selected_month = $_GET['month'] ?? $_SESSION['selected_month'] ?? getCurrentMonth();
$_SESSION['selected_month'] = $selected_month;

// If month is selected from dropdown, update session and redirect to current page
if (isset($_GET['month'])) {
    $_SESSION['selected_month'] = $_GET['month'];
    // Redirect to current page without the month parameter to avoid duplicate parameters
    $redirect_url = $current_page;
    header("Location: $redirect_url");
    exit();
}
?>
<!-- Inter font -->
<link rel="stylesheet" href="https://rsms.me/inter/inter.css">

<nav class="navbar navbar-expand-lg navbar-light">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="bi bi-wallet2"></i>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="index.php">İdarə Paneli</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'gelirler.php' ? 'active' : ''; ?>" href="gelirler.php">Gəlirlər</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'xercler.php' ? 'active' : ''; ?>" href="xercler.php">Xərclər</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'hesabat.php' ? 'active' : ''; ?>" href="hesabat.php">Hesabat</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'kateqoriyalar.php' ? 'active' : ''; ?>" href="kateqoriyalar.php">Kateqoriyalar</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'hedefler.php' ? 'active' : ''; ?>" href="hedefler.php">Hədəflər</a>
                </li>
                <?php if (function_exists('isAdmin') && isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($current_page, 'admin') !== false ? 'active' : ''; ?>" href="admin/index.php">
                        <i class="bi bi-shield-lock"></i> Admin Panel
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="monthDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-calendar3"></i> <?php echo formatMonthName($selected_month); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <?php foreach ($available_months as $month): ?>
                            <li>
                                <a class="dropdown-item <?php echo $month === $selected_month ? 'active' : ''; ?>" 
                                   href="<?php echo $current_page; ?>?month=<?php echo $month; ?>">
                                    <?php echo formatMonthName($month); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo $current_page === 'ayarlar.php' ? 'active' : ''; ?>" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="ayarlar.php">Ayarlar</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="cixis.php">Çıxış</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav> 