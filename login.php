<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

// Check for remember me cookie
if (!isLoggedIn() && isset($_COOKIE['remember_user'])) {
    list($user_id, $token) = explode(':', $_COOKIE['remember_user']);

    if (!empty($user_id) && !empty($token)) {
        $stmt = $conn->prepare("SELECT istifadeciler.id, istifadeciler.ad FROM istifadeciler
                                 JOIN remember_tokens ON remember_tokens.user_id = istifadeciler.id
                                 WHERE remember_tokens.token = ? AND istifadeciler.status = 'aktiv'");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['ad'];

            $stmt = $conn->prepare("UPDATE istifadeciler SET son_giris = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$user['id']]);

            logActivity($user['id'], 'auto_login', 'Automatic login via remember me cookie');

            header('Location: index.php');
            exit();
        }
    }
}

if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Təhlükəsizlik xətası! Səhifəni yeniləyin və yenidən cəhd edin.';
    } else {
        $email = cleanInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) ? true : false;

        if (empty($email) || empty($password)) {
            $error = 'Bütün xanaları doldurun!';
        } else {
            $stmt = $conn->prepare("SELECT id, email, sifre, ad FROM istifadeciler WHERE email = ? AND status = 'aktiv'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['sifre'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['ad'];

                $stmt = $conn->prepare("UPDATE istifadeciler SET son_giris = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$user['id']]);

                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

                    $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, token, user_agent, ip_address) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$user['id'], $token, $user_agent, $ip_address]);

                    setcookie(
                        'remember_user',
                        $user['id'] . ':' . $token,
                        time() + (86400 * 30),
                        '/',
                        '',
                        false,
                        true
                    );

                    logActivity($user['id'], 'login', 'Successful login with remember me enabled');
                } else {
                    logActivity($user['id'], 'login', 'Successful login');
                }

                header('Location: index.php');
                exit();
            } else {
                $error = 'E-poçt və ya şifrə səhvdir!';
                logActivity(null, 'failed_login', "Failed login attempt for email: $email");
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
    <title>Giriş | Maliyyə İdarəetmə Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow">
                    <div class="card-body">
                        <h2 class="text-center mb-4">Giriş</h2>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <?php
                            // Fetch all active users' logins for the dropdown
                            $stmt = $conn->prepare("SELECT id, email FROM istifadeciler WHERE status = 'aktiv' ORDER BY email ASC");
                            $stmt->execute();
                            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <div class="mb-3">
                                <label for="login" class="form-label">Login</label>
                                <select class="form-select" id="login" name="email" required>
                                    <option value="">Login seçin</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo htmlspecialchars($user['email']); ?>">
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Şifrə</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Məni yadda saxla</label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Daxil ol</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="qeydiyyat.php" class="text-decoration-none">Hesabınız yoxdur? Qeydiyyatdan keçin</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 