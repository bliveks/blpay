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

// Get user data
$stmt = $conn->prepare("SELECT * FROM istifadeciler WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Təhlükəsizlik xətası! Səhifəni yeniləyin və yenidən cəhd edin.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_profile') {
            $ad = cleanInput($_POST['ad'] ?? '');
            $soyad = cleanInput($_POST['soyad'] ?? '');
            $email = cleanInput($_POST['email'] ?? '');

            if (empty($ad) || empty($soyad) || empty($email)) {
                $error = 'Bütün məcburi xanaları doldurun!';
            } elseif (!validateEmail($email)) {
                $error = 'Düzgün e-poçt ünvanı daxil edin!';
            } else {
                // Check if email exists (excluding current user)
                $stmt = $conn->prepare("SELECT id FROM istifadeciler WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetch()) {
                    $error = 'Bu e-poçt artıq istifadə olunur!';
                } else {
                    try {
                        $stmt = $conn->prepare("UPDATE istifadeciler SET ad = ?, soyad = ?, email = ? WHERE id = ?");
                        $stmt->execute([$ad, $soyad, $email, $user_id]);
                        $_SESSION['user_name'] = $ad;
                        $success = 'Profil məlumatları uğurla yeniləndi!';
                        logActivity($user_id, 'update_profile', 'Updated profile information');
                        
                        // Refresh user data
                        $stmt = $conn->prepare("SELECT * FROM istifadeciler WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $user = $stmt->fetch();
                    } catch (PDOException $e) {
                        $error = 'Xəta baş verdi. Zəhmət olmasa bir az sonra yenidən cəhd edin.';
                        logActivity($user_id, 'profile_error', $e->getMessage());
                    }
                }
            }
        } elseif ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'Bütün məcburi xanaları doldurun!';
            } elseif (!password_verify($current_password, $user['sifre'])) {
                $error = 'Mövcud şifrə səhvdir!';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Yeni şifrələr uyğun gəlmir!';
            } elseif (!validatePassword($new_password)) {
                $error = 'Şifrə ən azı 8 simvol uzunluğunda olmalı və böyük hərf, kiçik hərf, rəqəm və xüsusi simvol tərkibində olmalıdır!';
            } else {
                try {
                    $stmt = $conn->prepare("UPDATE istifadeciler SET sifre = ? WHERE id = ?");
                    $stmt->execute([password_hash($new_password, PASSWORD_DEFAULT), $user_id]);
                    $success = 'Şifrə uğurla yeniləndi!';
                    logActivity($user_id, 'change_password', 'Changed password');
                } catch (PDOException $e) {
                    $error = 'Xəta baş verdi. Zəhmət olmasa bir az sonra yenidən cəhd edin.';
                    logActivity($user_id, 'password_error', $e->getMessage());
                }
            }
        }
    }
}

// Get user's login IPs
$stmt = $conn->prepare("
    SELECT ip_address, user_agent, created_at 
    FROM remember_tokens 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute([$user_id]);
$loginIps = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ayarlar | Maliyyə İdarəetmə Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container py-4">
        <div class="row">
            <!-- Profile Settings -->
            <div class="col-lg-8">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Profile Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Profil Məlumatları</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="ad" class="form-label">Ad</label>
                                    <input type="text" class="form-control" id="ad" name="ad" value="<?php echo htmlspecialchars($user['ad']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="soyad" class="form-label">Soyad</label>
                                    <input type="text" class="form-control" id="soyad" name="soyad" value="<?php echo htmlspecialchars($user['soyad']); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">E-poçt</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Yadda saxla
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Şifrəni Dəyiş</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="change_password">

                            <div class="mb-3">
                                <label for="current_password" class="form-label">Mövcud şifrə</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">Yeni şifrə</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <div class="form-text">
                                    Şifrə ən azı 8 simvol uzunluğunda olmalı və böyük hərf, kiçik hərf, rəqəm və xüsusi simvol tərkibində olmalıdır.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Yeni şifrəni təsdiqlə</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-key"></i> Şifrəni dəyiş
                            </button>
                        </form>
                    </div>
                </div>
            </div>
<!-- Login IPs -->
<div class="col-lg-4">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Daxil olan IP-lər</h5>
        </div>
<div class="list-group list-group-flush">
    <?php if (!empty($loginIps)): ?>
        <?php foreach ($loginIps as $ip): ?>
            <?php
                $info = analyzeUserAgent($ip['user_agent']);
                $formattedDate = formatDate($ip['created_at']);
                $ipAddress = htmlspecialchars($ip['ip_address']);
                $deviceInfo = "{$info['device']}, {$info['os']}, {$info['browser']}";
            ?>
            <div class="list-group-item">
                <div class="d-flex flex-column">
                    <div class="d-flex align-items-center mb-1">
                        <span class="badge bg-secondary me-2">IP Girişi</span>
                        <small class="text-muted"><?= $formattedDate ?></small>
                    </div>
                    <div class="small">
                        <i class="bi bi-globe"></i> IP: <?= $ipAddress ?> <br>
                        <i class="bi bi-laptop"></i> Cihaz: <?= htmlspecialchars($deviceInfo) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="list-group-item text-center text-muted">
            Giriş məlumatı tapılmadı
        </div>
    <?php endif; ?>
</div>
<!-- Bütün cihazlardan çıx linki -->
<div class="card mt-4">
    <div class="card-body">
        <a href="cixis.php?all=true" class="btn btn-danger">
            <i class="bi bi-box-arrow-right"></i> Bütün cihazlardan çıx
        </a>
    </div>
</div>

    </div>
</div>


        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
