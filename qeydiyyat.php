<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

// If already logged in, redirect to index
if (isLoggedIn() && isRegistrationCompleted()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';
$step = $_SESSION['registration_step'] ?? 1;

// Get standard categories for step 2
if ($step == 2) {
    $stmt = $conn->prepare("
        SELECT * FROM kateqoriyalar 
        WHERE standart = TRUE 
        ORDER BY tip, ad
    ");
    $stmt->execute();
    $standard_categories = $stmt->fetchAll();

    // Group categories by type
    $gelir_categories = array_filter($standard_categories, function($cat) {
        return $cat['tip'] === 'gelir';
    });
    $xerc_categories = array_filter($standard_categories, function($cat) {
        return $cat['tip'] === 'xerc';
    });
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Təhlükəsizlik xətası! Səhifəni yeniləyin və yenidən cəhd edin.';
    } else {
        if ($step == 1) {
        $ad = cleanInput($_POST['ad'] ?? '');
        $soyad = cleanInput($_POST['soyad'] ?? '');
        $email = cleanInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        // Validation
        if (empty($ad) || empty($soyad) || empty($email) || empty($password) || empty($password_confirm)) {
            $error = 'Bütün xanaları doldurun!';
        } elseif (!validateEmail($email)) {
            $error = 'Düzgün e-poçt ünvanı daxil edin!';
        } elseif (!validatePassword($password)) {
            $error = 'Şifrə ən azı 8 simvol uzunluğunda olmalı və böyük hərf, kiçik hərf, rəqəm və xüsusi simvol tərkibində olmalıdır!';
        } elseif ($password !== $password_confirm) {
            $error = 'Şifrələr uyğun gəlmir!';
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM istifadeciler WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Bu e-poçt artıq istifadə olunur!';
            } else {
                // Create user
                try {
                    $stmt = $conn->prepare("INSERT INTO istifadeciler (ad, soyad, email, sifre) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$ad, $soyad, $email, password_hash($password, PASSWORD_DEFAULT)]);
                    
                    $user_id = $conn->lastInsertId();
                        logActivity($user_id, 'registration', 'New user registration - Step 1 completed');
                        
                        // Set session variables
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['user_name'] = $ad . ' ' . $soyad;
                        $_SESSION['registration_step'] = 2;
                        
                        // Redirect to clear POST data
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit();
                    } catch (PDOException $e) {
                        $error = 'Qeydiyyat zamanı xəta baş verdi. Zəhmət olmasa bir az sonra yenidən cəhd edin.';
                        logActivity(null, 'registration_error', $e->getMessage());
                    }
                }
            }
        } else if ($step == 2) {
            $selected_categories = $_POST['categories'] ?? [];
            $category_limits = $_POST['limits'] ?? [];
            
            // Get selected categories by type
            $stmt = $conn->prepare("
                SELECT tip 
                FROM kateqoriyalar 
                WHERE id IN (" . implode(',', array_map('intval', $selected_categories)) . ")
                GROUP BY tip
            ");
            $stmt->execute();
            $selected_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($selected_categories)) {
                $error = 'Ən azı bir kateqoriya seçin!';
            } elseif (!in_array('gelir', $selected_types)) {
                $error = 'Ən azı bir gəlir kateqoriyası seçməlisiniz!';
            } elseif (!in_array('xerc', $selected_types)) {
                $error = 'Ən azı bir xərc kateqoriyası seçməlisiniz!';
            } else {
                try {
                    // Begin transaction
                    $conn->beginTransaction();
                    
                    // Insert selected categories for user
                    foreach ($selected_categories as $category_id) {
                        $stmt = $conn->prepare("
                            SELECT ad, tip FROM kateqoriyalar 
                            WHERE id = ?
                        ");
                        $stmt->execute([$category_id]);
                        $category = $stmt->fetch();
                        
                        if ($category) {
                            $limit_mebleq = null;
                            if ($category['tip'] === 'xerc' && isset($category_limits[$category_id]) && trim($category_limits[$category_id]) !== '') {
                                $limit_mebleq = (float)cleanInput($category_limits[$category_id]);
                                if ($limit_mebleq <= 0) {
                                    $limit_mebleq = null;
                                }
                            }
                            
                            $stmt = $conn->prepare("
                                INSERT INTO kateqoriyalar (istifadeci_id, ad, tip, limit_mebleq) 
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([$_SESSION['user_id'], $category['ad'], $category['tip'], $limit_mebleq]);
                        }
                    }
                    
                    // Mark registration as completed
                    $stmt = $conn->prepare("
                        UPDATE istifadeciler 
                        SET qeydiyyat_tamamlanib = TRUE 
                        WHERE id = ?
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    
                    // Log activity
                    logActivity($_SESSION['user_id'], 'registration_completed', 'Registration completed with category selection');
                    
                    // Commit transaction
                    $conn->commit();
                    
                    // Clear registration step
                    unset($_SESSION['registration_step']);
                    
                    // Redirect to index
                    header('Location: index.php');
                    exit();
                } catch (PDOException $e) {
                    // Rollback transaction
                    $conn->rollBack();
                    $error = 'Xəta baş verdi. Zəhmət olmasa bir az sonra yenidən cəhd edin.';
                    logActivity($_SESSION['user_id'], 'category_selection_error', $e->getMessage());
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qeydiyyat | Maliyyə İdarəetmə Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-<?php echo $step == 1 ? '6' : '8'; ?>">
                <div class="card shadow">
                    <div class="card-body">
                        <h2 class="text-center mb-4">Qeydiyyat - Mərhələ <?php echo $step; ?></h2>
                        <div class="progress mb-4">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $step == 1 ? '50%' : '100%'; ?>;" 
                                 aria-valuenow="<?php echo $step == 1 ? '50' : '100'; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                                <?php echo $step; ?>/2
                            </div>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <?php if ($step == 1): ?>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="ad" class="form-label">Ad</label>
                                    <input type="text" class="form-control" id="ad" name="ad" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="soyad" class="form-label">Soyad</label>
                                    <input type="text" class="form-control" id="soyad" name="soyad" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">E-poçt</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Şifrə</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="form-text">
                                    Şifrə ən azı 8 simvol uzunluğunda olmalı və böyük hərf, kiçik hərf, rəqəm və xüsusi simvol tərkibində olmalıdır.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">Şifrəni təsdiqləyin</label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                            </div>

                            <?php else: ?>
                                <div class="row">
                                    <!-- Gəlir Kateqoriyaları -->
                                    <div class="col-md-6 mb-4">
                                        <h5 class="text-success mb-3">
                                            <i class="bi bi-arrow-up-circle-fill"></i> Gəlir Kateqoriyaları
                                        </h5>
                                        <div class="card">
                                            <div class="card-body">
                                                <?php foreach ($gelir_categories as $category): ?>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="checkbox" name="categories[]" 
                                                               value="<?php echo $category['id']; ?>" 
                                                               id="cat_<?php echo $category['id']; ?>" checked>
                                                        <label class="form-check-label" for="cat_<?php echo $category['id']; ?>">
                                                            <?php echo htmlspecialchars($category['ad']); ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Xərc Kateqoriyaları -->
                                    <div class="col-md-6 mb-4">
                                        <h5 class="text-danger mb-3">
                                            <i class="bi bi-arrow-down-circle-fill"></i> Xərc Kateqoriyaları
                                        </h5>
                                        <div class="card">
                                            <div class="card-body">
                                                <?php foreach ($xerc_categories as $category): ?>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="checkbox" name="categories[]" 
                                                               value="<?php echo $category['id']; ?>" 
                                                               id="cat_<?php echo $category['id']; ?>" checked>
                                                        <label class="form-check-label" for="cat_<?php echo $category['id']; ?>">
                                                            <?php echo htmlspecialchars($category['ad']); ?>
                                                        </label>
                                                        <?php if ($category['tip'] === 'xerc'): ?>
                                                        <div class="mt-1 mb-2 ms-4">
                                                            <div class="input-group input-group-sm">
                                                                <input type="number" step="0.01" min="0" 
                                                                       class="form-control form-control-sm" 
                                                                       id="limit_<?php echo $category['id']; ?>" 
                                                                       name="limits[<?php echo $category['id']; ?>]" 
                                                                       placeholder="Aylıq limit (ixtiyari)">
                                                                <span class="input-group-text">AZN</span>
                                                            </div>
                                                            <small class="form-text text-muted">Xərc limiti təyin edin (boş saxlasanız limit olmaz)</small>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> Qeyd: Sonradan yeni kateqoriyalar əlavə edə və ya mövcud kateqoriyaları silə bilərsiniz.
                                </div>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-primary w-100">
                                <?php echo $step == 1 ? 'Növbəti' : 'Qeydiyyatı Tamamla'; ?>
                            </button>
                        </form>
                        
                        <?php if ($step == 1): ?>
                        <div class="text-center mt-3">
                            <a href="login.php" class="text-decoration-none">Artıq hesabınız var? Daxil olun</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($step == 2): ?>
    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <span class="toast-message"></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script>
        // Toast initialization
        const toastElement = document.querySelector('.toast');
        const toast = new bootstrap.Toast(toastElement, {
            animation: true,
            autohide: true,
            delay: 3000
        });

        // Show toast message function
        function showToast(message) {
            document.querySelector('.toast-message').textContent = message;
            toast.show();
        }

        document.querySelector('form').addEventListener('submit', function(e) {
            const gelirChecked = Array.from(document.querySelectorAll('input[type="checkbox"]'))
                .filter(cb => cb.closest('.col-md-6').querySelector('h5').textContent.includes('Gəlir'))
                .some(cb => cb.checked);
                
            const xercChecked = Array.from(document.querySelectorAll('input[type="checkbox"]'))
                .filter(cb => cb.closest('.col-md-6').querySelector('h5').textContent.includes('Xərc'))
                .some(cb => cb.checked);

            if (!gelirChecked || !xercChecked) {
                e.preventDefault();
                if (!gelirChecked) {
                    showToast('Ən azı bir gəlir kateqoriyası seçməlisiniz!');
                } else {
                    showToast('Ən azı bir xərc kateqoriyası seçməlisiniz!');
                }
            }
        });

        // Add select/deselect all buttons
        document.querySelectorAll('.col-md-6').forEach(col => {
            const cardBody = col.querySelector('.card-body');
            const checkboxes = cardBody.querySelectorAll('input[type="checkbox"]');
            const isGelir = col.querySelector('h5').textContent.includes('Gəlir');
            
            const btnGroup = document.createElement('div');
            btnGroup.className = 'btn-group w-100 mb-3';
            
            const selectAllBtn = document.createElement('button');
            selectAllBtn.type = 'button';
            selectAllBtn.className = `btn btn-sm btn-outline-${isGelir ? 'success' : 'danger'}`;
            selectAllBtn.textContent = 'Hamısını seç';
            selectAllBtn.onclick = () => {
                checkboxes.forEach(cb => cb.checked = true);
                showToast(`Bütün ${isGelir ? 'gəlir' : 'xərc'} kateqoriyaları seçildi`);
            };
            
            const deselectAllBtn = document.createElement('button');
            deselectAllBtn.type = 'button';
            deselectAllBtn.className = `btn btn-sm btn-outline-${isGelir ? 'success' : 'danger'}`;
            deselectAllBtn.textContent = 'Heç birini seçmə';
            deselectAllBtn.onclick = () => {
                checkboxes.forEach(cb => cb.checked = false);
                showToast(`Bütün ${isGelir ? 'gəlir' : 'xərc'} kateqoriyaları ləğv edildi`);
            };
            
            btnGroup.appendChild(selectAllBtn);
            btnGroup.appendChild(deselectAllBtn);
            cardBody.insertBefore(btnGroup, cardBody.firstChild);
        });
    </script>

    <style>
    .toast {
        opacity: 1 !important;
    }
    .toast-container {
        z-index: 9999;
    }
    </style>
    <?php endif; ?>
</body>
</html> 