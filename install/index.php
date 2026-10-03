<?php
// install/index.php - Maliyyə İdarəetmə Sistemi quraşdırıcısı
session_start();

define('APP_ROOT', dirname(__DIR__));
define('LOCK_FILE', __DIR__ . '/lock');
define('SQL_FILE', __DIR__ . '/install.sql');
define('CONFIG_FILE', APP_ROOT . '/config/db.php');

// Artıq quraşdırılıbsa icazə vermir
$alreadyInstalled = file_exists(LOCK_FILE);

// Tələblər
$requirements = [
    'PHP 7.4+' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'install.sql tapıldı' => file_exists(SQL_FILE),
    'config/ yazıla bilir' => is_writable(dirname(CONFIG_FILE)) && (!file_exists(CONFIG_FILE) || is_writable(CONFIG_FILE)),
];
$requirementsOk = !in_array(false, $requirements, true);

$error = '';
$success = false;

// CSRF token (quraşdırıcı üçün)
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['install_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyInstalled && $requirementsOk) {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        $error = 'Təhlükəsizlik xətası! Səhifəni yeniləyin və yenidən cəhd edin.';
    } else {
        $dbHost = trim($_POST['db_host'] ?? 'localhost');
        $dbPort = trim($_POST['db_port'] ?? '3306');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = $_POST['db_pass'] ?? '';
        $dbName = trim($_POST['db_name'] ?? 'maliyye_idareetme');
        $overwrite = !empty($_POST['overwrite']);

        $ad = trim($_POST['ad'] ?? '');
        $soyad = trim($_POST['soyad'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password_confirm'] ?? '';

        if ($ad === '' || $soyad === '' || $email === '' || $dbUser === '' || $dbName === '') {
            $error = 'Bütün mütləq xanaları doldurun!';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Düzgün e-poçt (login) ünvanı daxil edin!';
        } elseif (strlen($password) < 8) {
            $error = 'Şifrə ən azı 8 simvol uzunluğunda olmalıdır!';
        } elseif ($password !== $password2) {
            $error = 'Şifrələr uyğun gəlmir!';
        } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
            $error = 'Verilənlər bazası adı yalnız hərf, rəqəm və _ saxlaya bilər!';
        } else {
            try {
                // 1. Serverə qoşul (bazasız)
                $pdo = new PDO(
                    "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",
                    $dbUser,
                    $dbPass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                // 2. Bazanı yarat
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$dbName}`");

                // 3. Mövcud cədvəlləri yoxla
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                if ($tables) {
                    if (!$overwrite) {
                        throw new Exception(" '{$dbName}' bazasında artıq cədvəllər mövcuddur. Təmizləmək istəsəniz \"Mövcud datası sil\" seçimini işarələyin.");
                    }
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                    foreach ($tables as $t) {
                        $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
                    }
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                }

                // 4. install.sql icra et (şərhləri təmizləyib statement-statement)
                $sqlLines = [];
                foreach (file(SQL_FILE, FILE_IGNORE_NEW_LINES) as $line) {
                    $trimmed = trim($line);
                    if ($trimmed === '' || strpos($trimmed, '--') === 0) {
                        continue;
                    }
                    $sqlLines[] = $line;
                }
                $statements = array_filter(array_map('trim', explode(';', implode("\n", $sqlLines))));
                foreach ($statements as $statement) {
                    $pdo->exec($statement);
                }

                // 5. Admin istifadəçini yarat
                $stmt = $pdo->prepare("INSERT INTO istifadeciler (ad, soyad, email, sifre, qeydiyyat_tamamlanib, status, rol) VALUES (?, ?, ?, ?, 1, 'aktiv', 'admin')");
                $stmt->execute([$ad, $soyad, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $adminId = (int)$pdo->lastInsertId();

                // 6. Standart kateqoriyaları admin üçün kopyala (qeydiyyat addımı 2 kimi)
                $stmt = $pdo->query("SELECT ad, tip FROM kateqoriyalar WHERE standart = 1");
                $standardCategories = $stmt->fetchAll();
                $insert = $pdo->prepare("INSERT INTO kateqoriyalar (istifadeci_id, ad, tip, standart) VALUES (?, ?, ?, 0)");
                foreach ($standardCategories as $cat) {
                    $insert->execute([$adminId, $cat['ad'], $cat['tip']]);
                }

                // 7. config/db.php faylını yaz
                $configContent = "<?php\n"
                    . "// Database configuration - install/index.php tərəfindən yaradılıb (" . date('Y-m-d H:i:s') . ")\n"
                    . '$servername = ' . var_export($dbHost, true) . ";\n"
                    . '$username = ' . var_export($dbUser, true) . ";\n"
                    . '$password = ' . var_export($dbPass, true) . ";\n"
                    . '$dbname = ' . var_export($dbName, true) . ";\n\n"
                    . "try {\n"
                    . '    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);' . "\n"
                    . "    \$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n"
                    . "    \$conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);\n"
                    . "} catch(PDOException \$e) {\n"
                    . "    die(\"Connection failed: \" . \$e->getMessage());\n"
                    . "}\n";
                if (file_put_contents(CONFIG_FILE, $configContent) === false) {
                    throw new Exception('config/db.php faylı yazıla bilmədi. config/ qovluğunun yazıq hüququnu yoxlayın.');
                }

                // 8. Quraşdırma kilidi
                file_put_contents(LOCK_FILE, 'Installed: ' . date('Y-m-d H:i:s') . "\n");

                $success = true;
            } catch (Exception $e) {
                $error = 'Quraşdırma xətası: ' . $e->getMessage();
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
    <title>Quraşdırma | Maliyyə İdarəetmə Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .install-card { border-radius: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-8 col-lg-6">
                <h1 class="text-center mb-4">Maliyyə İdarəetmə Sistemi</h1>

                <?php if ($alreadyInstalled): ?>
                    <div class="card install-card shadow">
                        <div class="card-body">
                            <h2 class="text-center mb-3">⚠️ Sistem artıq quraşdırılıb</h2>
                            <p class="text-center">Quraşdırma yenidən icra oluna bilməz. Təkrar quraşdırmaq üçün <code>install/lock</code> faylını silin.</p>
                            <div class="text-center">
                                <a href="../login.php" class="btn btn-primary">Giriş səhifəsinə keçid</a>
                            </div>
                        </div>
                    </div>
                <?php elseif ($success): ?>
                    <div class="card install-card shadow">
                        <div class="card-body">
                            <h2 class="text-center mb-3 text-success">✅ Quraşdırma uğurla tamamlandı!</h2>
                            <ul class="mb-4">
                                <li>Verilənlər bazası və cədvəllər yaradıldı.</li>
                                <li>Admin hesabı yaradıldı: <strong><?php echo htmlspecialchars($email); ?></strong></li>
                                <li>Standart kateqoriyalar əlavə olundu.</li>
                                <li><code>config/db.php</code> konfiqurasiyası yazıldı.</li>
                            </ul>
                            <div class="alert alert-warning">
                                Təhlükəsizlik üçün <code>install/</code> qovluğunu silməyiniz və ya yalnız size xüsusi etməyiniz tövsiyə olunur.
                            </div>
                            <div class="text-center">
                                <a href="../login.php" class="btn btn-primary btn-lg">Sistemə daxil ol</a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card install-card shadow mb-4">
                        <div class="card-body">
                            <h2 class="mb-3">Sistem tələbləri</h2>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($requirements as $name => $ok): ?>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span><?php echo htmlspecialchars($name); ?></span>
                                        <span class="badge <?php echo $ok ? 'bg-success' : 'bg-danger'; ?>"><?php echo $ok ? 'OK' : 'XƏTA'; ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($requirementsOk): ?>
                        <div class="card install-card shadow">
                            <div class="card-body">
                                <h2 class="mb-4">Quraşdırma</h2>
                                <form method="post" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">

                                    <h5 class="mb-3">1. Verilənlər bazası</h5>
                                    <div class="row g-3 mb-4">
                                        <div class="col-8">
                                            <label class="form-label">Host <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required>
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label">Port</label>
                                            <input type="text" class="form-control" name="db_port" value="<?php echo htmlspecialchars($_POST['db_port'] ?? '3306'); ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">İstifadəçi <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">Şifrə</label>
                                            <input type="password" class="form-control" name="db_pass" value="">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Baza adı <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'maliyye_idareetme'); ?>" required>
                                            <div class="form-text">Baza mövcud deyilsə avtomatik yaradılacaq.</div>
                                        </div>
                                        <div class="col-12 form-check ms-3">
                                            <input type="checkbox" class="form-check-input" id="overwrite" name="overwrite" value="1">
                                            <label class="form-check-label" for="overwrite">Mövcud datası sil və yenidən quraşdır (diqqət: bütün data itəcək!)</label>
                                        </div>
                                    </div>

                                    <h5 class="mb-3">2. Admin hesabı</h5>
                                    <div class="row g-3 mb-4">
                                        <div class="col-6">
                                            <label class="form-label">Ad <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="ad" value="<?php echo htmlspecialchars($_POST['ad'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">Soyad <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="soyad" value="<?php echo htmlspecialchars($_POST['soyad'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">E-poçt (login) <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">Şifrə <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" name="password" minlength="8" required>
                                            <div class="form-text">Ən azı 8 simvol.</div>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">Şifrə (təkrar) <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" name="password_confirm" minlength="8" required>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-lg w-100">Quraşdırmanı icra et</button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">Tələblər ödənmir. Yuxarıdakı xətaları aradan qaldırın.</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
