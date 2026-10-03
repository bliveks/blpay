<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

if (!isAdmin()) {
    header('Location: ../index.php');
    exit();
}

// Get session messages
$error = $_SESSION['admin_error'] ?? '';
$success = $_SESSION['admin_success'] ?? '';
unset($_SESSION['admin_error'], $_SESSION['admin_success']);

// Default filter values
$status_filter = $_GET['status'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Prepare base query
$query = "SELECT id, ad, soyad, email, yaradilma_tarixi, son_giris, status, qeydiyyat_tamamlanib, rol FROM istifadeciler";
$params = [];
$conditions = [];

// Add filters
if ($status_filter !== 'all') {
    $conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($search_query)) {
    $conditions[] = "(ad LIKE ? OR soyad LIKE ? OR email LIKE ?)";
    $search_term = "%$search_query%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Construct final query
if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}
$query .= " ORDER BY yaradilma_tarixi DESC";

// Execute query
$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get user statistics
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN status = 'aktiv' THEN 1 ELSE 0 END) as active_users,
    SUM(CASE WHEN status = 'deaktiv' THEN 1 ELSE 0 END) as inactive_users,
    SUM(CASE WHEN DATE(yaradilma_tarixi) = CURDATE() THEN 1 ELSE 0 END) as new_today,
    SUM(CASE WHEN DATE(son_giris) = CURDATE() THEN 1 ELSE 0 END) as active_today
FROM istifadeciler");
$stmt->execute();
$stats = $stmt->fetch();

// Get suspicious activity (multiple failed login attempts)
$stmt = $conn->prepare("
    SELECT ip, COUNT(*) as attempt_count, MAX(tarix) as last_attempt 
    FROM giris_cehdleri 
    GROUP BY ip 
    HAVING COUNT(*) > 5
    ORDER BY last_attempt DESC
    LIMIT 5
");
$stmt->execute();
$suspicious_ips = $stmt->fetchAll();

// Handle user edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['admin_error'] = 'Təhlükəsizlik xətası! Səhifəni yeniləyin və yenidən cəhd edin.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $user_id = (int)($_POST['user_id'] ?? 0);
    $ad = cleanInput($_POST['ad'] ?? '');
    $soyad = cleanInput($_POST['soyad'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $status = $_POST['status'] ?? 'aktiv';
    $rol = $_POST['rol'] ?? 'user';
    
    // Validate inputs
    if (empty($ad) || empty($soyad) || empty($email)) {
        $_SESSION['admin_error'] = 'Bütün məcburi xanaları doldurun!';
    } elseif (!validateEmail($email)) {
        $_SESSION['admin_error'] = 'Düzgün e-poçt ünvanı daxil edin!';
    } else {
        // Check if email exists (excluding current user)
        $stmt = $conn->prepare("SELECT id FROM istifadeciler WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $_SESSION['admin_error'] = 'Bu e-poçt artıq istifadə olunur!';
        } else {
            try {
                $stmt = $conn->prepare("
                    UPDATE istifadeciler 
                    SET ad = ?, soyad = ?, email = ?, status = ?, rol = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$ad, $soyad, $email, $status, $rol, $user_id]);
                
                $_SESSION['admin_success'] = 'İstifadəçi məlumatları uğurla yeniləndi!';
                logActivity($_SESSION['user_id'], 'edit_user', "İstifadəçi məlumatları redaktə edildi: $email (ID: $user_id)");
            } catch (PDOException $e) {
                $_SESSION['admin_error'] = 'Xəta baş verdi. Zəhmət olmasa bir az sonra yenidən cəhd edin.';
                logActivity($_SESSION['user_id'], 'edit_user_error', $e->getMessage());
            }
        }
    }
    
    // Redirect to avoid form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İstifadəçi İdarəetməsi | Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Admin Panel</a></li>
            <li class="breadcrumb-item active">İstifadəçilər</li>
        </ol>
    </nav>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon me-3 rounded-circle bg-primary bg-opacity-10 p-3">
                        <i class="bi bi-people-fill text-primary fs-4"></i>
                    </div>
                    <div>
                        <h6 class="stat-card-title mb-0">Ümumi</h6>
                        <p class="stat-card-number"><?php echo $stats['total_users'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon me-3 rounded-circle bg-success bg-opacity-10 p-3">
                        <i class="bi bi-person-check-fill text-success fs-4"></i>
                    </div>
                    <div>
                        <h6 class="stat-card-title mb-0">Aktiv</h6>
                        <p class="stat-card-number"><?php echo $stats['active_users'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon me-3 rounded-circle bg-info bg-opacity-10 p-3">
                        <i class="bi bi-person-plus-fill text-info fs-4"></i>
                    </div>
                    <div>
                        <h6 class="stat-card-title mb-0">Bu gün yeni</h6>
                        <p class="stat-card-number"><?php echo $stats['new_today'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon me-3 rounded-circle bg-warning bg-opacity-10 p-3">
                        <i class="bi bi-clock-history text-warning fs-4"></i>
                    </div>
                    <div>
                        <h6 class="stat-card-title mb-0">Bu gün aktiv</h6>
                        <p class="stat-card-number"><?php echo $stats['active_today'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Main Content -->
        <div class="col-xl-8 col-lg-7">
            <div class="card h-100">
                <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <h5 class="mb-0"><i class="bi bi-people me-2"></i>İstifadəçilər</h5>
                    <form class="d-flex flex-wrap gap-2" method="GET">
                        <select name="status" class="form-select form-select-sm" style="width: auto;">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Bütün statuslar</option>
                            <option value="aktiv" <?php echo $status_filter === 'aktiv' ? 'selected' : ''; ?>>Aktiv</option>
                            <option value="deaktiv" <?php echo $status_filter === 'deaktiv' ? 'selected' : ''; ?>>Deaktiv</option>
                        </select>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Axtar..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="d-none d-md-table-cell">İD</th>
                                    <th>İstifadəçi</th>
                                    <th class="d-none d-lg-table-cell">Status</th>
                                    <th class="d-none d-xl-table-cell">Rol</th>
                                    <th class="d-none d-lg-table-cell">Qeydiyyat</th>
                                    <th class="text-end">Əməliyyat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="bi bi-emoji-frown fs-2 d-block mb-2 text-muted"></i>
                                            İstifadəçi tapılmadı
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td class="d-none d-md-table-cell"><?php echo $user['id']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-initials bg-<?php echo $user['status'] === 'aktiv' ? 'primary' : 'secondary'; ?> bg-opacity-10 text-<?php echo $user['status'] === 'aktiv' ? 'primary' : 'secondary'; ?> me-2"><?php echo strtoupper(substr($user['ad'], 0, 1) . substr($user['soyad'], 0, 1)); ?></div>
                                                    <div>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?></div>
                                                        <div class="small text-muted"><?php echo htmlspecialchars($user['email']); ?></div>
                                                        <!-- Mobile only badges -->
                                                        <div class="d-flex d-lg-none gap-1 mt-1">
                                                            <span class="badge bg-<?php echo $user['status'] === 'aktiv' ? 'success' : 'danger'; ?> text-white">
                                                                <?php echo $user['status'] === 'aktiv' ? 'Aktiv' : 'Deaktiv'; ?>
                                                            </span>
                                                            <span class="badge bg-<?php echo $user['rol'] === 'admin' ? 'primary' : 'secondary'; ?> text-white">
                                                                <?php echo $user['rol'] === 'admin' ? 'Admin' : 'İstifadəçi'; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="d-none d-lg-table-cell">
                                                <span class="badge bg-<?php echo $user['status'] === 'aktiv' ? 'success' : 'danger'; ?>">
                                                    <i class="bi bi-<?php echo $user['status'] === 'aktiv' ? 'check-circle-fill' : 'x-circle-fill'; ?> me-1"></i>
                                                    <?php echo $user['status'] === 'aktiv' ? 'Aktiv' : 'Deaktiv'; ?>
                                                </span>
                                            </td>
                                            <td class="d-none d-xl-table-cell">
                                                <span class="badge bg-<?php echo $user['rol'] === 'admin' ? 'primary' : 'secondary'; ?>">
                                                    <i class="bi bi-<?php echo $user['rol'] === 'admin' ? 'shield-fill' : 'person-fill'; ?> me-1"></i>
                                                    <?php echo $user['rol'] === 'admin' ? 'Admin' : 'İstifadəçi'; ?>
                                                </span>
                                            </td>
                                            <td class="d-none d-lg-table-cell">
                                                <div class="small">
                                                    <i class="bi bi-calendar3 me-1"></i> <?php echo date('d.m.Y', strtotime($user['yaradilma_tarixi'])); ?>
                                                    <div class="text-muted mt-1 d-none d-xl-block">
                                                        <i class="bi bi-clock-history me-1"></i>
                                                        <?php echo $user['son_giris'] ? date('d.m.Y H:i', strtotime($user['son_giris'])) : 'Heç vaxt'; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <button type="button" class="btn btn-icon btn-sm btn-outline-primary rounded-circle" data-bs-toggle="modal" data-bs-target="#editUserModal" 
                                                        data-id="<?php echo $user['id']; ?>"
                                                        data-ad="<?php echo htmlspecialchars($user['ad']); ?>"
                                                        data-soyad="<?php echo htmlspecialchars($user['soyad']); ?>"
                                                        data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                        data-status="<?php echo $user['status']; ?>"
                                                        data-rol="<?php echo $user['rol']; ?>"
                                                        data-bs-toggle="tooltip" 
                                                        title="Redaktə et">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <form method="POST" action="status_change.php" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="current_status" value="<?php echo $user['status']; ?>">
                                                        <button type="button" class="btn btn-icon btn-sm btn-outline-<?php echo $user['status'] === 'aktiv' ? 'danger' : 'success'; ?> rounded-circle" 
                                                               data-bs-toggle="modal" 
                                                               data-bs-target="#statusModal"
                                                               data-user-id="<?php echo $user['id']; ?>"
                                                               data-user-status="<?php echo $user['status']; ?>"
                                                               data-bs-toggle="tooltip"
                                                               title="<?php echo $user['status'] === 'aktiv' ? 'Deaktiv et' : 'Aktiv et'; ?>">
                                                            <i class="bi bi-<?php echo $user['status'] === 'aktiv' ? 'toggle-off' : 'toggle-on'; ?>"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-xl-4 col-lg-5">
            <!-- Tabbed Content -->
            <div class="card mb-4">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="suspicious-tab" data-bs-toggle="tab" data-bs-target="#suspicious-content" type="button" role="tab" aria-controls="suspicious-content" aria-selected="true">
                                <i class="bi bi-shield-exclamation me-1"></i> Şübhəli aktivliklər
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats-content" type="button" role="tab" aria-controls="stats-content" aria-selected="false">
                                <i class="bi bi-bar-chart-line me-1"></i> Statistika
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <!-- Suspicious Activity Tab -->
                        <div class="tab-pane fade show active" id="suspicious-content" role="tabpanel" aria-labelledby="suspicious-tab">
                            <?php if (empty($suspicious_ips)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-shield-check fs-1 text-success mb-2"></i>
                                    <p class="text-muted">Şübhəli aktivlik qeydə alınmayıb.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-borderless">
                                        <thead>
                                            <tr>
                                                <th>IP ünvanı</th>
                                                <th>Cəhd sayı</th>
                                                <th>Son cəhd</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($suspicious_ips as $ip): ?>
                                                <tr>
                                                    <td>
                                                        <span class="d-flex align-items-center">
                                                            <i class="bi bi-pc-display me-2 text-danger"></i>
                                                            <?php echo htmlspecialchars($ip['ip']); ?>
                                                        </span>
                                                    </td>
                                                    <td><span class="badge bg-danger"><?php echo $ip['attempt_count']; ?></span></td>
                                                    <td>
                                                        <span class="small text-muted">
                                                            <i class="bi bi-clock me-1"></i>
                                                            <?php echo date('d.m.Y H:i', strtotime($ip['last_attempt'])); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Statistics Tab -->
                        <div class="tab-pane fade" id="stats-content" role="tabpanel" aria-labelledby="stats-tab">
                            <div class="chart-container" style="position: relative; height:240px;">
                                <canvas id="userStatsChart"></canvas>
                            </div>
                            
                            <ul class="list-group list-group-flush mt-3">
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                    <span><i class="bi bi-check-circle-fill text-success me-2"></i> Aktiv istifadəçilər</span>
                                    <span class="badge bg-success rounded-pill"><?php echo $stats['active_users'] ?? 0; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                    <span><i class="bi bi-x-circle-fill text-danger me-2"></i> Deaktiv istifadəçilər</span>
                                    <span class="badge bg-danger rounded-pill"><?php echo $stats['inactive_users'] ?? 0; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                    <span><i class="bi bi-shield-fill text-primary me-2"></i> Adminlər</span>
                                    <span class="badge bg-primary rounded-pill">
                                        <?php 
                                        $adminCount = 0;
                                        foreach ($users as $user) {
                                            if ($user['rol'] === 'admin') $adminCount++;
                                        }
                                        echo $adminCount;
                                        ?>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="editUserModalLabel"><i class="bi bi-pencil-square me-2"></i>İstifadəçi məlumatlarını redaktə et</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_ad" class="form-label">Ad</label>
                            <input type="text" class="form-control" id="edit_ad" name="ad" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_soyad" class="form-label">Soyad</label>
                            <input type="text" class="form-control" id="edit_soyad" name="soyad" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_status" class="form-label">Status</label>
                                    <select class="form-select" id="edit_status" name="status">
                                        <option value="aktiv">Aktiv</option>
                                        <option value="deaktiv">Deaktiv</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_rol" class="form-label">Rol</label>
                                    <select class="form-select" id="edit_rol" name="rol">
                                        <option value="user">İstifadəçi</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">İmtina</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Yadda saxla
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Status Change Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-toggles me-2"></i>İstifadəçi Statusunu Dəyiş</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="status-icon mb-3">
                            <i class="bi bi-exclamation-circle text-warning"></i>
                        </div>
                        <p class="mb-0">Bu istifadəçinin statusunu dəyişmək istədiyinizə əminsiniz?</p>
                        <p class="text-muted small">İstifadəçinin statusu dəyişdikdən sonra sistəmdəki bütün funksionallığı yenilənəcək.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-1"></i> Ləğv et
                    </button>
                    <form action="status_change.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="user_id" id="modal_user_id">
                        <input type="hidden" name="current_status" id="modal_current_status">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Statusu dəyiş
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    </div> <!-- End of admin-main -->
</div> <!-- End of admin-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="js/admin.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit User Modal
    const editUserModal = document.getElementById('editUserModal');
    if (editUserModal) {
        editUserModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const ad = button.getAttribute('data-ad');
            const soyad = button.getAttribute('data-soyad');
            const email = button.getAttribute('data-email');
            const status = button.getAttribute('data-status');
            const rol = button.getAttribute('data-rol');
            
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_ad').value = ad;
            document.getElementById('edit_soyad').value = soyad;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_status').value = status;
            document.getElementById('edit_rol').value = rol;
        });
    }
    
    // Status Modal
    const statusModal = document.getElementById('statusModal');
    if (statusModal) {
        statusModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const userStatus = button.getAttribute('data-user-status');
            
            document.getElementById('modal_user_id').value = userId;
            document.getElementById('modal_current_status').value = userStatus;
            
            // Update modal message based on current status
            const statusText = userStatus === 'aktiv' ? 'deaktiv' : 'aktiv';
            const statusIcon = document.querySelector('.status-icon i');
            
            statusIcon.className = userStatus === 'aktiv' 
                ? 'bi bi-toggle-off text-danger fs-1' 
                : 'bi bi-toggle-on text-success fs-1';
        });
    }
    
    // User Stats Chart
    const ctx = document.getElementById('userStatsChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Aktiv istifadəçilər', 'Deaktiv istifadəçilər'],
                datasets: [{
                    data: [<?php echo $stats['active_users'] ?? 0; ?>, <?php echo $stats['inactive_users'] ?? 0; ?>],
                    backgroundColor: ['#10b981', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 15,
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
</body>
</html> 