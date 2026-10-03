<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get selected month from session or default to current month
$selected_month = $_SESSION['selected_month'] ?? date('Y-m');
$start_date = $selected_month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Təhlükəsizlik xətası! Səhifəni yeniləyin və yenidən cəhd edin.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add' || $action === 'edit') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $mebleq = cleanInput($_POST['mebleq'] ?? '');
            $kateqoriya_id = (int)($_POST['kateqoriya_id'] ?? 0);
            $tarix = cleanInput($_POST['tarix'] ?? '');
            $hesab_novu = cleanInput($_POST['hesab_novu'] ?? '');
            $qeyd = cleanInput($_POST['qeyd'] ?? '');

            if (empty($mebleq) || empty($kateqoriya_id) || empty($tarix) || empty($hesab_novu)) {
                $_SESSION['error'] = 'Bütün məcburi xanaları doldurun!';
            } elseif (!is_numeric($mebleq) || $mebleq <= 0) {
                $_SESSION['error'] = 'Məbləğ müsbət ədəd olmalıdır!';
            } else {
                try {
                    if ($action === 'add') {
                        $stmt = $conn->prepare("INSERT INTO xercler (istifadeci_id, mebleq, kateqoriya_id, tarix, hesab_novu, qeyd) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$user_id, $mebleq, $kateqoriya_id, $tarix, $hesab_novu, $qeyd]);
                        $_SESSION['success'] = 'Xərc uğurla əlavə edildi!';
                        logActivity($user_id, 'add_expense', "Added expense: $mebleq AZN");
                    } else {
                        $stmt = $conn->prepare("UPDATE xercler SET mebleq = ?, kateqoriya_id = ?, tarix = ?, hesab_novu = ?, qeyd = ? WHERE id = ? AND istifadeci_id = ?");
                        $stmt->execute([$mebleq, $kateqoriya_id, $tarix, $hesab_novu, $qeyd, $id, $user_id]);
                        $_SESSION['success'] = 'Xərc uğurla yeniləndi!';
                        logActivity($user_id, 'edit_expense', "Updated expense ID: $id");
                    }
                    // Redirect to prevent form resubmission
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                } catch (PDOException $e) {
                    $_SESSION['error'] = 'Xəta baş verdi. Zəhmət olmasa bir az sonra yenidən cəhd edin.';
                    logActivity($user_id, 'expense_error', $e->getMessage());
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $stmt = $conn->prepare("DELETE FROM xercler WHERE id = ? AND istifadeci_id = ?");
                $stmt->execute([$id, $user_id]);
                $_SESSION['success'] = 'Xərc uğurla silindi!';
                logActivity($user_id, 'delete_expense', "Deleted expense ID: $id");
                // Redirect to prevent form resubmission
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            } catch (PDOException $e) {
                $_SESSION['error'] = 'Xəta baş verdi. Zəhmət olmasa bir az sonra yenidən cəhd edin.';
                logActivity($user_id, 'expense_error', $e->getMessage());
            }
        }
    }
}

// Get messages from session
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// Get expense categories
$stmt = $conn->prepare("SELECT id, ad FROM kateqoriyalar WHERE tip = 'xerc' AND istifadeci_id = ? ORDER BY ad");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll();

// Get expense records for selected month
$stmt = $conn->prepare("
    SELECT x.*, k.ad as kateqoriya_ad 
    FROM xercler x 
    JOIN kateqoriyalar k ON x.kateqoriya_id = k.id 
    WHERE x.istifadeci_id = ? AND x.tarix BETWEEN ? AND ?
    ORDER BY x.id DESC, x.tarix DESC
");
$stmt->execute([$user_id, $start_date, $end_date]);
$expenses = $stmt->fetchAll();

// Get unique categories for filters
$stmt = $conn->prepare("
    SELECT DISTINCT ad FROM kateqoriyalar 
    WHERE istifadeci_id = ? 
    ORDER BY ad
");
$stmt->execute([$user_id]);
$unique_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xərclər | Maliyyə İdarəetmə Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <!-- Main Content -->
    <div class="container py-4">
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Yeni Xərc</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-3">
                                <label for="mebleq" class="form-label">Məbləğ</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-control" id="mebleq" name="mebleq" required>
                                    <span class="input-group-text">AZN</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="kateqoriya_id" class="form-label">Kateqoriya</label>
                                <select class="form-select" id="kateqoriya_id" name="kateqoriya_id" required>
                                    <option value="">Seçin</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['ad']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="tarix" class="form-label">Tarix</label>
                                <input type="date" class="form-control" id="tarix" name="tarix" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="hesab_novu" class="form-label">Hesab növü</label>
                                <select class="form-select" id="hesab_novu" name="hesab_novu" required>
                                    <option value="">Seçin</option>
                                    <option value="nagd">Nağd</option>
                                    <option value="kredit_kart">Kredit kartı</option>
                                    <option value="debet_kart">Debet kartı</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="qeyd" class="form-label">Qeyd</label>
                                <textarea class="form-control" id="qeyd" name="qeyd" rows="2"></textarea>
                            </div>

                            <button type="submit" class="btn btn-danger w-100">
                                <i class="bi bi-dash-circle"></i> Əlavə et
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Xərclər Siyahısı</h5>
                        <small class="text-muted"><?php echo formatMonthName($selected_month); ?></small>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php if (empty($expenses)): ?>
                            <div class="list-group-item text-center text-muted py-4">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                <p class="mb-0">Xərc tapılmadı</p>
                                <small>Yeni xərc əlavə etmək üçün sol tərəfdəki formu istifadə edin</small>
                            </div>
                        <?php else: ?>
                            <?php foreach ($expenses as $expense): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-danger rounded-pill">
                                            <?php echo formatMoney($expense['mebleq']); ?>
                                        </span>
                                        <div class="content-wrapper">
                                            <strong><?php echo htmlspecialchars($expense['kateqoriya_ad']); ?></strong>
                                            <div class="small text-muted">
                                                <?php echo formatDate($expense['tarix']); ?> - 
                                                <?php echo formatHesabNovu($expense['hesab_novu']); ?>
                                            </div>
                                            <?php if ($expense['qeyd']): ?>
                                                <div class="small text-muted">
                                                    <i class="bi bi-chat-left-text"></i> <?php echo htmlspecialchars($expense['qeyd']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-sm btn-outline-primary edit-expense" 
                                                    data-id="<?php echo $expense['id']; ?>"
                                                    data-mebleq="<?php echo $expense['mebleq']; ?>"
                                                    data-kateqoriya="<?php echo $expense['kateqoriya_id']; ?>"
                                                    data-tarix="<?php echo $expense['tarix']; ?>"
                                                    data-hesab="<?php echo $expense['hesab_novu']; ?>"
                                                    data-qeyd="<?php echo $expense['qeyd']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirmDelete(event, 'xərc')">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $expense['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Xərci Redaktə Et</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="editForm" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="mb-3">
                            <label for="edit_mebleq" class="form-label">Məbləğ</label>
                            <div class="input-group">
                                <input type="number" step="0.01" class="form-control" id="edit_mebleq" name="mebleq" required>
                                <span class="input-group-text">AZN</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_kateqoriya_id" class="form-label">Kateqoriya</label>
                            <select class="form-select" id="edit_kateqoriya_id" name="kateqoriya_id" required>
                                <option value="">Seçin</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['ad']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="edit_tarix" class="form-label">Tarix</label>
                            <input type="date" class="form-control" id="edit_tarix" name="tarix" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_hesab_novu" class="form-label">Hesab növü</label>
                            <select class="form-select" id="edit_hesab_novu" name="hesab_novu" required>
                                <option value="">Seçin</option>
                                <option value="nagd">Nağd</option>
                                <option value="kredit_kart">Kredit kartı</option>
                                <option value="debet_kart">Debet kartı</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="edit_qeyd" class="form-label">Qeyd</label>
                            <textarea class="form-control" id="edit_qeyd" name="qeyd" rows="2"></textarea>
                        </div>

                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ləğv et</button>
                            <button type="submit" class="btn btn-primary">Yadda saxla</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        // Initialize edit modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const editModal = new bootstrap.Modal(document.getElementById('editModal'));
            
            document.querySelectorAll('.edit-expense').forEach(button => {
                button.addEventListener('click', function() {
                    const data = this.dataset;
                    
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_mebleq').value = data.mebleq;
                    document.getElementById('edit_kateqoriya_id').value = data.kateqoriya;
                    document.getElementById('edit_tarix').value = data.tarix;
                    document.getElementById('edit_hesab_novu').value = data.hesab;
                    document.getElementById('edit_qeyd').value = data.qeyd;
                    
                    editModal.show();
                });
            });
        });
    </script>
</body>
</html> 