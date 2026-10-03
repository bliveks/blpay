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
        
        // Add new goal
        if ($action === 'add_goal') {
            $ad = cleanInput($_POST['ad'] ?? '');
            $hedef_mebleq = cleanInput($_POST['hedef_mebleq'] ?? '');
            $cari_mebleq = cleanInput($_POST['cari_mebleq'] ?? '0.00');
            $baslangic_tarixi = cleanInput($_POST['baslangic_tarixi'] ?? date('Y-m-d'));
            $muddet = cleanInput($_POST['muddet'] ?? 'free');
            
            // Calculate target date based on period
            $hedef_tarixi = date('Y-m-d'); // Default to today
            if ($muddet === 'free') {
                // For free period, set target date to a far future date (10 years from now)
                $hedef_tarixi = date('Y-m-d', strtotime('+10 years'));
            } else {
                // Use exact days instead of months for more accuracy
                $today = time(); // Current timestamp
                
                switch ($muddet) {
                    case '1month':
                        // Exactly 30 days
                        $hedef_tarixi = date('Y-m-d', strtotime('+30 days'));
                        break;
                    case '3months':
                        // Exactly 90 days
                        $hedef_tarixi = date('Y-m-d', strtotime('+90 days'));
                        break;
                    case '6months':
                        // Exactly 180 days
                        $hedef_tarixi = date('Y-m-d', strtotime('+180 days'));
                        break;
                    case '12months':
                        // Exactly 365 days
                        $hedef_tarixi = date('Y-m-d', strtotime('+365 days'));
                        break;
                }
            }
            
            $prioritet = cleanInput($_POST['prioritet'] ?? 'orta');
            $qeyd = cleanInput($_POST['qeyd'] ?? '');
            
            if (empty($ad) || empty($hedef_mebleq) || (($muddet === 'free') && empty($hedef_tarixi))) {
                $error = 'Bütün məcburi xanaları doldurun!';
            } elseif (!is_numeric($hedef_mebleq) || $hedef_mebleq <= 0) {
                $error = 'Hədəf məbləği müsbət ədəd olmalıdır!';
            } elseif (!is_numeric($cari_mebleq) || $cari_mebleq < 0) {
                $error = 'Cari məbləğ mənfi ola bilməz!';
            } elseif (strtotime($hedef_tarixi) < strtotime($baslangic_tarixi)) {
                $error = 'Hədəf tarixi başlanğıc tarixindən əvvəl ola bilməz!';
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO hedefler (istifadeci_id, ad, hedef_mebleq, cari_mebleq, baslangic_tarixi, hedef_tarixi, prioritet, qeyd, yaradilma_tarixi)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$user_id, $ad, $hedef_mebleq, $cari_mebleq, $baslangic_tarixi, $hedef_tarixi, $prioritet, $qeyd]);
                
                $goal_id = $conn->lastInsertId();
                
                // If there's an initial amount, add it as a transaction
                if (floatval($cari_mebleq) > 0) {
                    $stmt = $conn->prepare("
                        INSERT INTO hedef_emeliyyatlar (hedef_id, istifadeci_id, mebleq, emeliyyat_novu, tarix, qeyd)
                        VALUES (?, ?, ?, 'artirma', ?, 'İlkin məbləğ')
                    ");
                    $stmt->execute([$goal_id, $user_id, $cari_mebleq, $baslangic_tarixi]);
                }
                
                $success = 'Hədəf uğurla əlavə edildi!';
                header('Location: hedefler.php');
                exit();
            }
        }
        
        // Edit goal
        elseif ($action === 'edit_goal') {
            $id = (int)($_POST['id'] ?? 0);
            $ad = cleanInput($_POST['ad'] ?? '');
            $hedef_mebleq = cleanInput($_POST['hedef_mebleq'] ?? '');
            $muddet = cleanInput($_POST['muddet'] ?? 'free');
            
            // Calculate target date based on period
            $hedef_tarixi = date('Y-m-d'); // Default to today
            if ($muddet === 'free') {
                // For free period, set target date to a far future date (10 years from now)
                $hedef_tarixi = date('Y-m-d', strtotime('+10 years'));
            } else {
                // Use exact days instead of months for more accuracy
                $today = time(); // Current timestamp
                
                switch ($muddet) {
                    case '1month':
                        // Exactly 30 days
                        $hedef_tarixi = date('Y-m-d', strtotime('+30 days'));
                        break;
                    case '3months':
                        // Exactly 90 days
                        $hedef_tarixi = date('Y-m-d', strtotime('+90 days'));
                        break;
                    case '6months':
                        // Exactly 180 days
                        $hedef_tarixi = date('Y-m-d', strtotime('+180 days'));
                        break;
                    case '12months':
                        // Exactly 365 days
                        $hedef_tarixi = date('Y-m-d', strtotime('+365 days'));
                        break;
                }
            }
            
            $prioritet = cleanInput($_POST['prioritet'] ?? 'orta');
            $qeyd = cleanInput($_POST['qeyd'] ?? '');
            
            if (empty($ad) || empty($hedef_mebleq) || (($muddet === 'free') && empty($hedef_tarixi))) {
                $error = 'Bütün məcburi xanaları doldurun!';
            } elseif (!is_numeric($hedef_mebleq) || $hedef_mebleq <= 0) {
                $error = 'Hədəf məbləği müsbət ədəd olmalıdır!';
            } else {
                $stmt = $conn->prepare("
                    UPDATE hedefler 
                    SET ad = ?, hedef_mebleq = ?, hedef_tarixi = ?, prioritet = ?, qeyd = ?
                    WHERE id = ? AND istifadeci_id = ?
                ");
                $stmt->execute([$ad, $hedef_mebleq, $hedef_tarixi, $prioritet, $qeyd, $id, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    $success = 'Hədəf uğurla yeniləndi!';
                } else {
                    $error = 'Hədəf tapılmadı və ya dəyişiklik edilmədi.';
                }
                
                header('Location: hedefler.php');
                exit();
            }
        }
        
        // Delete goal
        elseif ($action === 'delete_goal') {
            $id = (int)($_POST['id'] ?? 0);
            
            $stmt = $conn->prepare("DELETE FROM hedefler WHERE id = ? AND istifadeci_id = ?");
            $stmt->execute([$id, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                $success = 'Hədəf uğurla silindi!';
            } else {
                $error = 'Hədəf tapılmadı və ya silinmədi.';
            }
            
            header('Location: hedefler.php');
            exit();
        }
        
        // Add transaction to goal
        elseif ($action === 'add_transaction') {
            $hedef_id = (int)($_POST['hedef_id'] ?? 0);
            $mebleq = cleanInput($_POST['mebleq'] ?? '');
            $emeliyyat_novu = cleanInput($_POST['emeliyyat_novu'] ?? 'artirma');
            $qeyd = cleanInput($_POST['qeyd'] ?? '');
            
            if (empty($mebleq) || !is_numeric($mebleq) || $mebleq <= 0) {
                $error = 'Məbləğ müsbət ədəd olmalıdır!';
            } else {
                $result = updateGoalAmount($hedef_id, $user_id, $mebleq, $emeliyyat_novu, $qeyd);
                
                if ($result) {
                    $success = 'Əməliyyat uğurla əlavə edildi!';
                } else {
                    $error = 'Əməliyyat əlavə edilərkən xəta baş verdi.';
                }
                
                header('Location: hedefler.php');
                exit();
            }
        }
    }
}

// Get all active goals
$goals = getActiveGoals($user_id);

// Get average monthly savings
$avg_monthly_savings = getAverageUserMonthlySavings($user_id);

// Removed include header.php as it's not needed
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hədəflər | Maliyyə İdarəetmə Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <!-- Main Content -->
    <div class="container py-4" id="goals-container">
        <div class="row">
            <!-- Left Column - Add New Goal Form -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Yeni Hədəf</h5>
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
                            <input type="hidden" name="action" value="add_goal">
                            <input type="hidden" name="cari_mebleq" value="0">
                            <input type="hidden" name="baslangic_tarixi" value="<?php echo date('Y-m-d'); ?>">
                            <input type="hidden" name="prioritet" value="orta">
                            <input type="hidden" name="qeyd" value="">
                            
                            <div class="mb-3">
                                <label for="ad" class="form-label">Hədəf adı</label>
                                <input type="text" class="form-control" id="ad" name="ad" placeholder="Məs: Avtomobil almaq" required>
                                <div class="invalid-feedback">
                                    Hədəf adını daxil edin.
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="hedef_mebleq" class="form-label">Hədəf məbləği</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0.01" class="form-control" id="hedef_mebleq" name="hedef_mebleq" required>
                                    <span class="input-group-text">AZN</span>
                                </div>
                                <div class="invalid-feedback">
                                    Müsbət məbləğ daxil edin.
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="muddet" class="form-label">Hədəf müddəti</label>
                                <select class="form-select" id="muddet" name="muddet" required>
                                    <option value="free">Sərbəst müddət</option>
                                    <option value="1month">1 ay</option>
                                    <option value="3months">3 ay</option>
                                    <option value="6months">6 ay</option>
                                    <option value="12months">12 ay</option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="custom_date_container" style="display:none;">
                                <label for="hedef_tarixi" class="form-label">Hədəf tarixi</label>
                                <input type="date" class="form-control datepicker" id="hedef_tarixi" name="hedef_tarixi">
                                <div class="invalid-feedback">
                                    Hədəf tarixini daxil edin.
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-circle"></i> Əlavə et
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column - Goals List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Hədəflər Siyahısı</h5>
                        <span class="badge bg-primary rounded-pill"><?php echo count($goals); ?> hədəf</span>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php if (empty($goals)): ?>
                            <div class="list-group-item text-center text-muted py-4">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                <p class="mb-0">Hədəf tapılmadı</p>
                                <small>Yeni hədəf əlavə etmək üçün sol tərəfdəki formu istifadə edin</small>
                            </div>
                        <?php else: ?>
                            <?php foreach ($goals as $goal): ?>
                                <?php 
                                    $progress = calculateGoalProgress($goal);
                                    $monthly_target = calculateMonthlyTargetAmount($goal);
                                    $predicted_date = predictGoalCompletionDate($goal, $avg_monthly_savings);
                                ?>
                                <div class="list-group-item p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($goal['ad']); ?></h6>
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="showAddTransactionModal(<?php echo $goal['id']; ?>, '<?php echo htmlspecialchars($goal['ad']); ?>')">
                                                <i class="bi bi-plus-circle"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary edit-goal" 
                                                    data-bs-id="<?php echo $goal['id']; ?>"
                                                    data-bs-ad="<?php echo htmlspecialchars($goal['ad']); ?>"
                                                    data-bs-mebleq="<?php echo $goal['hedef_mebleq']; ?>"
                                                    data-bs-tarix="<?php echo $goal['hedef_tarixi']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirmDeleteGoal(event)">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="delete_goal">
                                                <input type="hidden" name="id" value="<?php echo $goal['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="flex-grow-1 me-3">
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar bg-success" 
                                                     role="progressbar" 
                                                     style="width: <?php echo $progress; ?>%;" 
                                                     aria-valuenow="<?php echo $progress; ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-end" style="min-width: 100px;">
                                            <strong><?php echo formatMoney($goal['cari_mebleq']); ?></strong> / 
                                            <?php echo formatMoney($goal['hedef_mebleq']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex flex-wrap text-muted small">
                                        <div class="me-3">
                                            <i class="bi bi-graph-up"></i> 
                                            <?php echo round($progress); ?>% tamamlanıb
                                        </div>
                                        
                                        <div class="me-3">
                                            <?php if (strtotime($goal['hedef_tarixi']) > strtotime('+9 years')): ?>
                                                <i class="bi bi-infinity"></i> Sərbəst müddət
                                            <?php else: ?>
                                                <i class="bi bi-calendar-event"></i> 
                                                Hədəf: <?php echo formatDate($goal['hedef_tarixi']); ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($predicted_date && strtotime($predicted_date) !== strtotime($goal['hedef_tarixi'])): ?>
                                            <div class="me-3">
                                                <i class="bi bi-clock-history"></i> 
                                                Təxmini: <?php echo formatDate($predicted_date); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div>
                                            <i class="bi bi-currency-exchange"></i> 
                                            Aylıq: <?php echo formatMoney($monthly_target); ?>
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
    
    <!-- Edit Goal Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Hədəfi Redaktə Et</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="editForm" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="edit_goal">
                        <input type="hidden" name="id" id="edit_id">
                        <input type="hidden" name="prioritet" value="orta">
                        <input type="hidden" name="qeyd" value="">
                        
                        <div class="mb-3">
                            <label for="edit_ad" class="form-label">Hədəf adı</label>
                            <input type="text" class="form-control" id="edit_ad" name="ad" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_hedef_mebleq" class="form-label">Hədəf məbləği</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0.01" class="form-control" id="edit_hedef_mebleq" name="hedef_mebleq" required>
                                <span class="input-group-text">AZN</span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_muddet" class="form-label">Hədəf müddəti</label>
                            <select class="form-select" id="edit_muddet" name="muddet" required>
                                <option value="free">Sərbəst müddət</option>
                                <option value="1month">1 ay</option>
                                <option value="3months">3 ay</option>
                                <option value="6months">6 ay</option>
                                <option value="12months">12 ay</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="edit_custom_date_container" style="display:none;">
                            <label for="edit_hedef_tarixi" class="form-label">Hədəf tarixi</label>
                            <input type="date" class="form-control datepicker" id="edit_hedef_tarixi" name="hedef_tarixi">
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
    
    <!-- Add Transaction Modal -->
    <div class="modal fade transaction-modal" id="transactionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Əməliyyat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" class="needs-validation" novalidate>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="add_transaction">
                        <input type="hidden" name="hedef_id" id="transaction_goal_id">
                        
                        <div class="mb-3">
                            <label for="mebleq" class="form-label">Məbləğ</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0.01" class="form-control" id="mebleq" name="mebleq" required>
                                <span class="input-group-text">AZN</span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="emeliyyat_novu" class="form-label">Əməliyyat növü</label>
                            <select class="form-select" id="emeliyyat_novu" name="emeliyyat_novu" required>
                                <option value="artirma">Artırma (Əlavə et)</option>
                                <option value="azaltma">Azaltma (Çıxar)</option>
                            </select>
                        </div>
                        
                        <input type="hidden" name="qeyd" value="">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Bağla</button>
                        <button type="submit" class="btn btn-primary">Əməliyyatı Əlavə Et</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Create a reference to the edit modal
            const editModalElement = document.getElementById('editModal');
            const editModal = new bootstrap.Modal(editModalElement);
            
            const editButtons = document.querySelectorAll('.edit-goal');
            
            editButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const id = this.getAttribute('data-bs-id');
                    const ad = this.getAttribute('data-bs-ad');
                    const mebleq = this.getAttribute('data-bs-mebleq');
                    const tarix = this.getAttribute('data-bs-tarix');
                    
                    document.getElementById('edit_id').value = id;
                    document.getElementById('edit_ad').value = ad;
                    document.getElementById('edit_hedef_mebleq').value = mebleq;
                    
                    // Determine muddet value
                    const targetDate = new Date(tarix);
                    const today = new Date();
                    
                    // Calculate the difference in days
                    const diffTime = targetDate.getTime() - today.getTime();
                    const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));
                    
                    if (diffDays > 3650) { // More than 10 years (approx)
                        document.getElementById('edit_muddet').value = 'free';
                    } else if (diffDays <= 45) { // 1 month +/- 15 days
                        document.getElementById('edit_muddet').value = '1month';
                    } else if (diffDays <= 105) { // 3 months +/- 15 days
                        document.getElementById('edit_muddet').value = '3months';
                    } else if (diffDays <= 195) { // 6 months +/- 15 days
                        document.getElementById('edit_muddet').value = '6months';
                    } else if (diffDays <= 380) { // 12 months +/- 15 days
                        document.getElementById('edit_muddet').value = '12months';
                    } else {
                        document.getElementById('edit_muddet').value = 'free';
                    }
                    
                    // Make sure to hide the custom date field for all options
                    toggleCustomDateField('edit_muddet', 'edit_custom_date_container');
                    
                    // Show the modal
                    editModal.show();
                });
            });
            
            // Toggle custom date field based on selected period
            document.getElementById('muddet').addEventListener('change', function() {
                toggleCustomDateField('muddet', 'custom_date_container');
            });
            
            document.getElementById('edit_muddet').addEventListener('change', function() {
                toggleCustomDateField('edit_muddet', 'edit_custom_date_container');
            });
            
            // Initial toggle for both forms
            toggleCustomDateField('muddet', 'custom_date_container');
            toggleCustomDateField('edit_muddet', 'edit_custom_date_container');
        });
        
        // Function to toggle custom date field visibility
        function toggleCustomDateField(selectId, containerId) {
            const selectElement = document.getElementById(selectId);
            const containerElement = document.getElementById(containerId);
            const dateField = containerElement.querySelector('input[type="date"]');
            
            if (selectElement.value === 'free') {
                containerElement.style.display = 'none';
                if (dateField) dateField.removeAttribute('required');
            } else {
                containerElement.style.display = 'none';
                if (dateField) dateField.removeAttribute('required');
            }
        }
        
        // Function to confirm delete
        function confirmDeleteGoal(event) {
            return confirm('Bu hədəfi silmək istədiyinizə əminsiniz?');
        }
        
        // Function to show transaction modal
        function showAddTransactionModal(goalId, goalName) {
            document.getElementById('transaction_goal_id').value = goalId;
            const transactionModal = new bootstrap.Modal(document.getElementById('transactionModal'));
            document.querySelector('#transactionModal .modal-title').textContent = goalName + ' - Yeni Əməliyyat';
            transactionModal.show();
        }
    </script>
</body>
</html> 