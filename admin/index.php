<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

// Check if user is admin
if (!isAdmin()) {
    header('Location: ../index.php');
    exit();
}

// Get total statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM istifadeciler");
$stmt->execute();
$total_users = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) as total_transactions FROM (
    SELECT id FROM gelirler UNION ALL SELECT id FROM xercler
) as all_transactions");
$stmt->execute();
$total_transactions = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) as total_categories FROM kateqoriyalar WHERE standart = 0");
$stmt->execute();
$total_categories = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) as total_goals FROM hedefler");
$stmt->execute();
$total_goals = $stmt->fetchColumn();

// Get recent users
$stmt = $conn->prepare("SELECT id, ad, soyad, email, yaradilma_tarixi, status FROM istifadeciler ORDER BY yaradilma_tarixi DESC LIMIT 5");
$stmt->execute();
$recent_users = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | Maliyyə İdarəetmə Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <!-- Main Content -->
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Admin Panel</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="../index.php">Ana Səhifə</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Admin Panel</li>
                </ol>
            </nav>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">İstifadəçilər</h5>
                        <p class="card-text display-6"><?php echo $total_users; ?></p>
                        <a href="users.php" class="btn btn-sm btn-light mt-2">İdarə et</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Əməliyyatlar</h5>
                        <p class="card-text display-6"><?php echo $total_transactions; ?></p>
                        <a href="transactions.php" class="btn btn-sm btn-light mt-2">İdarə et</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-bg-info">
                    <div class="card-body">
                        <h5 class="card-title">Kateqoriyalar</h5>
                        <p class="card-text display-6"><?php echo $total_categories; ?></p>
                        <a href="categories.php" class="btn btn-sm btn-light mt-2">İdarə et</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Hədəflər</h5>
                        <p class="card-text display-6"><?php echo $total_goals; ?></p>
                        <a href="goals.php" class="btn btn-sm btn-light mt-2">İdarə et</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Son qeydiyyat olan istifadəçilər</h5>
                <a href="users.php" class="btn btn-sm btn-primary">Bütün istifadəçilər</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Ad Soyad</th>
                                <th>Email</th>
                                <th>Qeydiyyat tarixi</th>
                                <th>Status</th>
                                <th>Əməliyyatlar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_users)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">İstifadəçi tapılmadı</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($user['yaradilma_tarixi'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['status'] === 'aktiv' ? 'success' : 'danger'; ?>">
                                                <?php echo $user['status'] === 'aktiv' ? 'Aktiv' : 'Deaktiv'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary rounded-circle" title="Redaktə et">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button class="btn btn-sm btn-outline-<?php echo $user['status'] === 'aktiv' ? 'danger' : 'success'; ?> rounded-circle"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#statusModal" 
                                                        data-user-id="<?php echo $user['id']; ?>"
                                                        data-user-status="<?php echo $user['status']; ?>"
                                                        title="<?php echo $user['status'] === 'aktiv' ? 'Deaktiv et' : 'Aktiv et'; ?>">
                                                    <i class="bi bi-<?php echo $user['status'] === 'aktiv' ? 'toggle-off' : 'toggle-on'; ?>"></i>
                                                </button>
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

        <!-- System Information -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Sistem Məlumatı</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p><strong>PHP Versiyası:</strong> <?php echo phpversion(); ?></p>
                        <p><strong>MySQL Versiyası:</strong> <?php echo $conn->getAttribute(PDO::ATTR_SERVER_VERSION); ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
                        <p><strong>Protokol:</strong> <?php echo $_SERVER['SERVER_PROTOCOL']; ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Cari Tarix:</strong> <?php echo date('d.m.Y H:i:s'); ?></p>
                        <p><strong>Server Saatı:</strong> <?php echo date_default_timezone_get(); ?></p>
                    </div>
                </div>
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
                    <button type="button" class="btn btn-light rounded-circle" data-bs-dismiss="modal" title="Ləğv et">
                        <i class="bi bi-x"></i>
                    </button>
                    <form action="status_change.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="user_id" id="modal_user_id">
                        <input type="hidden" name="current_status" id="modal_current_status">
                        <button type="submit" class="btn btn-primary rounded-circle" title="Statusu dəyiş">
                            <i class="bi bi-check-lg"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Modal üçün verilənləri ötürmək
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>
    
    <style>
        .status-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
    </style>
</body>
</html> 