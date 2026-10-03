<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Təhlükəsizlik xətası! Səhifəni yeniləyin və yenidən cəhd edin.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $ad = cleanInput($_POST['ad'] ?? '');
            $tip = cleanInput($_POST['tip'] ?? '');
            $limit_mebleq = null;
            
            if ($tip === 'xerc' && isset($_POST['limit_mebleq']) && trim($_POST['limit_mebleq']) !== '') {
                $limit_mebleq = (float)cleanInput($_POST['limit_mebleq']);
                if ($limit_mebleq <= 0) {
                    $error = 'Limit məbləği müsbət ədəd olmalıdır!';
                }
            }

            if (empty($ad) || empty($tip)) {
                $error = 'Bütün məcburi xanaları doldurun!';
            } else {
                try {
                    // Check if category with same name and type already exists
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM kateqoriyalar WHERE istifadeci_id = ? AND ad = ? AND tip = ?");
                    $stmt->execute([$user_id, $ad, $tip]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'Bu adda və tipdə kateqoriya artıq mövcuddur!';
                    } else {
                        $stmt = $conn->prepare("INSERT INTO kateqoriyalar (ad, tip, limit_mebleq, istifadeci_id) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$ad, $tip, $limit_mebleq, $user_id]);
                        $success = 'Kateqoriya uğurla əlavə edildi!';
                        logActivity($user_id, 'add_category', "Added category: $ad");
                    }
                } catch (PDOException $e) {
                    $error = 'Xəta baş verdi. Zəhmət olmasa bir az sonra yenidən cəhd edin.';
                    logActivity($user_id, 'category_error', $e->getMessage());
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $ad = cleanInput($_POST['ad'] ?? '');
            $tip = cleanInput($_POST['tip'] ?? '');
            $limit_mebleq = null;
            
            if ($tip === 'xerc' && isset($_POST['limit_mebleq']) && trim($_POST['limit_mebleq']) !== '') {
                $limit_mebleq = (float)cleanInput($_POST['limit_mebleq']);
                if ($limit_mebleq <= 0) {
                    $error = 'Limit məbləği müsbət ədəd olmalıdır!';
                }
            }

            if (empty($ad) || empty($tip)) {
                $error = 'Bütün məcburi xanaları doldurun!';
            } else {
                try {
                    // Check if category with same name and type already exists (excluding current category)
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM kateqoriyalar WHERE istifadeci_id = ? AND ad = ? AND tip = ? AND id != ?");
                    $stmt->execute([$user_id, $ad, $tip, $id]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'Bu adda və tipdə kateqoriya artıq mövcuddur!';
                    } else {
                        $stmt = $conn->prepare("UPDATE kateqoriyalar SET ad = ?, tip = ?, limit_mebleq = ? WHERE id = ? AND istifadeci_id = ?");
                        $stmt->execute([$ad, $tip, $limit_mebleq, $id, $user_id]);
                        $success = 'Kateqoriya uğurla yeniləndi!';
                        logActivity($user_id, 'edit_category', "Updated category ID: $id");
                    }
                } catch (PDOException $e) {
                    $error = 'Xəta baş verdi. Zəhmət olmasa bir az sonra yenidən cəhd edin.';
                    logActivity($user_id, 'category_error', $e->getMessage());
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            try {
                // Check if category is in use
                $stmt = $conn->prepare("SELECT COUNT(*) FROM gelirler WHERE kateqoriya_id = ?");
                $stmt->execute([$id]);
                $income_count = $stmt->fetchColumn();

                $stmt = $conn->prepare("SELECT COUNT(*) FROM xercler WHERE kateqoriya_id = ?");
                $stmt->execute([$id]);
                $expense_count = $stmt->fetchColumn();

                if ($income_count > 0 || $expense_count > 0) {
                    $error = 'Bu kateqoriya istifadə olunur və silinə bilməz!';
                } else {
                    $stmt = $conn->prepare("DELETE FROM kateqoriyalar WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'Kateqoriya uğurla silindi!';
                    logActivity($user_id, 'delete_category', "Deleted category ID: $id");
                }
            } catch (PDOException $e) {
                $error = 'Xəta baş verdi. Zəhmət olmasa bir az sonra yenidən cəhd edin.';
                logActivity($user_id, 'category_error', $e->getMessage());
            }
        }
    }
}

// Get all categories
$stmt = $conn->prepare("SELECT * FROM kateqoriyalar WHERE istifadeci_id = ? ORDER BY tip, ad");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll();

// Get selected month from GET parameter or session
$selected_month = $_GET['month'] ?? $_SESSION['selected_month'] ?? getCurrentMonth();
$_SESSION['selected_month'] = $selected_month;

// Calculate expenses by category for selected month
$start_date = $selected_month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

$stmt = $conn->prepare("
    SELECT 
        k.id,
        SUM(x.mebleq) as total_expenses
    FROM 
        kateqoriyalar k
    LEFT JOIN 
        xercler x ON k.id = x.kateqoriya_id AND x.tarix BETWEEN ? AND ?
    WHERE 
        k.istifadeci_id = ? AND
        k.tip = 'xerc'
    GROUP BY 
        k.id
");
$stmt->execute([$start_date, $end_date, $user_id]);
$expenses_by_category = [];
while ($row = $stmt->fetch()) {
    $expenses_by_category[$row['id']] = $row['total_expenses'] ?: 0;
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kateqoriyalar | Maliyyə İdarəetmə Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container py-4">
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Yeni Kateqoriya</h5>
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
                                <label for="ad" class="form-label">Kateqoriya Adı</label>
                                <input type="text" class="form-control" id="ad" name="ad" required>
                            </div>

                            <div class="mb-3">
                                <label for="tip" class="form-label">Tip</label>
                                <select class="form-select" id="tip" name="tip" required onchange="toggleLimitField()">
                                    <option value="">Seçin</option>
                                    <option value="gelir">Gəlir</option>
                                    <option value="xerc">Xərc</option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="limitField" style="display:none;">
                                <label for="limit_mebleq" class="form-label">Xərc Limiti (AZN)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-control" id="limit_mebleq" name="limit_mebleq" placeholder="Xərc limiti (ixtiyari)">
                                    <span class="input-group-text">AZN</span>
                                </div>
                                <div class="form-text">Xərc kateqoriyası üçün aylıq limit təyin edin. Bos saxlasanız, limit olmaz.</div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-circle"></i> Əlavə et
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Kateqoriyalar Siyahısı</h5>
                        <ul class="nav nav-tabs card-header-tabs mt-2" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#gelirler" type="button" role="tab">
                                    <i class="bi bi-arrow-up-circle text-success"></i> Gəlirlər
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#xercler" type="button" role="tab">
                                    <i class="bi bi-arrow-down-circle text-danger"></i> Xərclər
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content">
                        <!-- Gəlirlər Tab -->
                        <div class="tab-pane fade show active" id="gelirler" role="tabpanel">
                            <div class="list-group list-group-flush">
                                <?php 
                                $gelir_categories = array_filter($categories, function($category) {
                                    return $category['tip'] === 'gelir';
                                });
                                if (empty($gelir_categories)): 
                                ?>
                                    <div class="list-group-item text-center text-muted py-4">
                                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                        <p class="mb-0">Gəlir kateqoriyası tapılmadı</p>
                                        <small>Yeni kateqoriya əlavə etmək üçün sol tərəfdəki formu istifadə edin</small>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($gelir_categories as $category): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($category['ad']); ?></strong>
                                                </div>
                                                <div class="action-buttons">
                                                    <button type="button" class="btn btn-sm btn-outline-primary edit-category" 
                                                            data-id="<?php echo $category['id']; ?>"
                                                            data-ad="<?php echo htmlspecialchars($category['ad']); ?>"
                                                            data-tip="<?php echo $category['tip']; ?>"
                                                            data-limit="<?php echo $category['limit_mebleq'] ?? ''; ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <form method="POST" action="" class="d-inline" onsubmit="return confirmDelete(event, 'kateqoriya')">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
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
                        
                        <!-- Xərclər Tab -->
                        <div class="tab-pane fade" id="xercler" role="tabpanel">
                            <div class="list-group list-group-flush">
                                <?php 
                                $xerc_categories = array_filter($categories, function($category) {
                                    return $category['tip'] === 'xerc';
                                });
                                if (empty($xerc_categories)): 
                                ?>
                                    <div class="list-group-item text-center text-muted py-4">
                                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                        <p class="mb-0">Xərc kateqoriyası tapılmadı</p>
                                        <small>Yeni kateqoriya əlavə etmək üçün sol tərəfdəki formu istifadə edin</small>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($xerc_categories as $category): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($category['ad']); ?></strong>
                                                    <?php if (!empty($category['limit_mebleq'])): ?>
                                                        <span class="badge bg-info ms-2">
                                                            Limit: <?php echo number_format($category['limit_mebleq'], 2); ?> AZN
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="action-buttons">
                                                    <button type="button" class="btn btn-sm btn-outline-primary edit-category" 
                                                            data-id="<?php echo $category['id']; ?>"
                                                            data-ad="<?php echo htmlspecialchars($category['ad']); ?>"
                                                            data-tip="<?php echo $category['tip']; ?>"
                                                            data-limit="<?php echo $category['limit_mebleq'] ?? ''; ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <form method="POST" action="" class="d-inline" onsubmit="return confirmDelete(event, 'kateqoriya')">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($category['limit_mebleq'])): 
                                                $current_expense = $expenses_by_category[$category['id']] ?? 0;
                                                $percentage = min(100, round(($current_expense / $category['limit_mebleq']) * 100));
                                                $progress_class = 'bg-success';
                                                
                                                if ($percentage >= 80 && $percentage < 100) {
                                                    $progress_class = 'bg-warning';
                                                } else if ($percentage >= 100) {
                                                    $progress_class = 'bg-danger';
                                                }

                                                // Əgər seçilmiş il cari ildirsə, yalnız ayın adını göstər
                                                $selected_year = date('Y', strtotime($selected_month));
                                                $current_year = date('Y');
                                                $month_display = $selected_year == $current_year ? 
                                                    formatMonthName($selected_month, false) : 
                                                    formatMonthName($selected_month);
                                            ?>
                                            <div class="mb-1 small">
                                                <?php echo $month_display; ?>: <?php echo formatMoney($current_expense); ?> AZN / <?php echo formatMoney($category['limit_mebleq']); ?> AZN
                                                (<?php echo $percentage; ?>%)
                                            </div>
                                            <div class="progress" role="progressbar" style="height: 8px;">
                                                <div class="progress-bar <?php echo $progress_class; ?>" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
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
                    <h5 class="modal-title">Kateqoriyanı Redaktə Et</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="editForm" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="mb-3">
                            <label for="edit_ad" class="form-label">Kateqoriya Adı</label>
                            <input type="text" class="form-control" id="edit_ad" name="ad" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_tip" class="form-label">Tip</label>
                            <select class="form-select" id="edit_tip" name="tip" required onchange="toggleEditLimitField()">
                                <option value="">Seçin</option>
                                <option value="gelir">Gəlir</option>
                                <option value="xerc">Xərc</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="editLimitField" style="display:none;">
                            <label for="edit_limit_mebleq" class="form-label">Xərc Limiti (AZN)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" class="form-control" id="edit_limit_mebleq" name="limit_mebleq" placeholder="Xərc limiti (ixtiyari)">
                                <span class="input-group-text">AZN</span>
                            </div>
                            <div class="form-text">Xərc kateqoriyası üçün aylıq limit təyin edin. Bos saxlasanız, limit olmaz.</div>
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
        // Toggle limit field based on category type
        function toggleLimitField() {
            const tipField = document.getElementById('tip');
            const limitField = document.getElementById('limitField');
            
            if (tipField.value === 'xerc') {
                limitField.style.display = 'block';
            } else {
                limitField.style.display = 'none';
            }
        }
        
        function toggleEditLimitField() {
            const tipField = document.getElementById('edit_tip');
            const limitField = document.getElementById('editLimitField');
            
            if (tipField.value === 'xerc') {
                limitField.style.display = 'block';
            } else {
                limitField.style.display = 'none';
            }
        }
        
        // Initialize edit modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const editModal = new bootstrap.Modal(document.getElementById('editModal'));

            document.querySelectorAll('.edit-category').forEach(button => {
                button.addEventListener('click', function() {
                    const data = this.dataset;

                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_ad').value = data.ad;
                    document.getElementById('edit_tip').value = data.tip;
                    
                    // Handle limit mebleq
                    const editLimitField = document.getElementById('editLimitField');
                    const editLimitInput = document.getElementById('edit_limit_mebleq');
                    
                    if (data.tip === 'xerc') {
                        editLimitField.style.display = 'block';
                        editLimitInput.value = data.limit;
                    } else {
                        editLimitField.style.display = 'none';
                        editLimitInput.value = '';
                    }

                    editModal.show();
                });
            });
            
            // Initialize limit field on page load
            toggleLimitField();
        });
    </script>
</body>
</html> 