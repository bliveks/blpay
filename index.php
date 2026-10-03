<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

// Check if it's a new month and reset balances if needed
if (isNewMonth()) {
    resetMonthlyBalances();
}

// Get current month's report
$monthly_report = getMonthlyReport($user_id);
$total_income = $monthly_report['total_income'] ?? 0;
$total_expense = $monthly_report['total_expense'] ?? 0;
$total_balance = $total_income - $total_expense;

// Get recent transactions
$stmt = $conn->prepare("
    SELECT 
        t.tarix,
        t.tip,
        t.hesab_novu,
        k.ad as kateqoriya,
        t.mebleq,
        t.qeyd,
        t.id,
        t.tarix as sort_date
    FROM (
        SELECT 'gelir' as tip, tarix, hesab_novu, kateqoriya_id, mebleq, qeyd, id
        FROM gelirler 
        WHERE istifadeci_id = ?
        UNION ALL
        SELECT 'xerc' as tip, tarix, hesab_novu, kateqoriya_id, mebleq, qeyd, id
        FROM xercler 
        WHERE istifadeci_id = ?
    ) t
    JOIN kateqoriyalar k ON t.kateqoriya_id = k.id
    ORDER BY sort_date DESC, id DESC
    LIMIT 10
");
$stmt->execute([$user_id, $user_id]);
$son_emeliyyatlar = $stmt->fetchAll();

// Get active goals
$active_goals = getActiveGoals($user_id, 5);

// Get account balances
$stmt = $conn->prepare("
    SELECT hesab_novu, 
        SUM(CASE WHEN type = 'gelir' THEN mebleq ELSE -mebleq END) as balance
    FROM (
        SELECT 'gelir' as type, hesab_novu, mebleq 
        FROM gelirler 
        WHERE istifadeci_id = ?
        UNION ALL
        SELECT 'xerc' as type, hesab_novu, mebleq 
        FROM xercler 
        WHERE istifadeci_id = ?
    ) as all_transactions
    GROUP BY hesab_novu
    HAVING balance != 0
");
$stmt->execute([$user_id, $user_id]);
$balances_by_account = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ana Səhifə | Maliyyə İdarəetmə Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <!-- Main Content -->
    <div class="container py-4">
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="bg-white shadow-sm rounded-3 border-0">
                    <div class="stat-card-header bg-success bg-opacity-10 p-3 rounded-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-success bg-opacity-25 rounded-circle p-3 me-3">
                                    <i class="bi bi-graph-up-arrow text-success fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 text-success">Ümumi Gəlir</h6>
                                    <small class="text-muted">Bu ay</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card-body p-3">
                        <div class="stat-amount text-success mb-3">
                            <?php echo formatMoney($total_income); ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bg-white shadow-sm rounded-3 border-0">
                    <div class="stat-card-header bg-danger bg-opacity-10 p-3 rounded-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-danger bg-opacity-25 rounded-circle p-3 me-3">
                                    <i class="bi bi-graph-down-arrow text-danger fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 text-danger">Ümumi Xərc</h6>
                                    <small class="text-muted">Bu ay</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card-body p-3">
                        <div class="stat-amount text-danger mb-3">
                            <?php echo formatMoney($total_expense); ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bg-white shadow-sm rounded-3 border-0">
                    <div class="stat-card-header <?php echo $total_balance >= 0 ? 'bg-primary bg-opacity-10' : 'bg-danger bg-opacity-10'; ?> p-3 rounded-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon <?php echo $total_balance >= 0 ? 'bg-primary' : 'bg-danger'; ?> bg-opacity-25 rounded-circle p-3 me-3">
                                    <i class="bi bi-bank2 <?php echo $total_balance >= 0 ? 'text-primary' : 'text-danger'; ?> fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 <?php echo $total_balance >= 0 ? 'text-primary' : 'text-danger'; ?>">Balans</h6>
                                    <small class="text-muted">Bu ay</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card-body p-3">
                        <div class="stat-amount <?php echo $total_balance >= 0 ? 'text-primary' : 'text-danger'; ?> mb-3">
                            <?php echo formatMoney($total_balance); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions and Goals -->
        <div class="row">
            <!-- Recent Transactions -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clock-history"></i> Son əməliyyatlar
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php if (empty($son_emeliyyatlar)): ?>
                                <div class="list-group-item text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                    <p class="mb-0">Əməliyyat tapılmadı</p>
                                    <small>Hələ heç bir əməliyyat yoxdur</small>
                                </div>
                            <?php else: ?>
                                <?php foreach ($son_emeliyyatlar as $emeliyyat): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge <?php echo $emeliyyat['tip'] == 'gelir' ? 'bg-success' : 'bg-danger'; ?> rounded-pill">
                                                <?php echo formatMoney($emeliyyat['mebleq']); ?>
                                            </span>
                                            <div class="content-wrapper">
                                                <strong><?php echo htmlspecialchars($emeliyyat['kateqoriya']); ?></strong>
                                                <div class="small text-muted">
                                                    <?php echo formatDate($emeliyyat['tarix']); ?> - 
                                                    <?php echo formatHesabNovu($emeliyyat['hesab_novu']); ?>
                                                </div>
                                                <?php if ($emeliyyat['qeyd']): ?>
                                                    <div class="small text-muted">
                                                        <i class="bi bi-chat-left-text"></i> <?php echo htmlspecialchars($emeliyyat['qeyd']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Goals -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-bullseye"></i> Aktiv hədəflər
                        </h5>
                        <span class="badge bg-primary rounded-pill"><?php echo count($active_goals); ?> hədəf</span>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php if (empty($active_goals)): ?>
                            <div class="list-group-item text-center text-muted py-4">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                <p class="mb-0">Hədəf tapılmadı</p>
                                <small>Hələ heç bir hədəf yoxdur</small>
                            </div>
                        <?php else: ?>
                            <?php foreach ($active_goals as $goal): ?>
                                <div class="list-group-item p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($goal['ad']); ?></h6>
                                        <div class="action-buttons">
                                            <a href="hedefler.php" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="flex-grow-1 me-3">
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-success" 
                                                     role="progressbar" 
                                                     style="width: <?php echo $goal['progress']; ?>%;" 
                                                     aria-valuenow="<?php echo $goal['progress']; ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-end" style="min-width: 80px;">
                                            <small><?php echo round($goal['progress']); ?>%</small>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between text-muted small">
                                        <div>
                                            <?php echo formatMoney($goal['cari_mebleq']); ?> / <?php echo formatMoney($goal['hedef_mebleq']); ?>
                                        </div>
                                        <div>
                                            <?php echo formatDate($goal['hedef_tarixi']); ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>

<style>
.stat-card {
    transition: transform 0.2s ease-in-out;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-amount {
    font-size: 2rem;
    font-weight: bold;
    line-height: 1;
}

.badge {
    font-weight: 500;
}

.stat-card-header {
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.stat-card-body {
    background: linear-gradient(to bottom, rgba(255,255,255,0.8), rgba(255,255,255,1));
}

.content-wrapper {
    flex: 1;
    margin-left: 1rem;
}

.progress {
    background-color: rgba(0,0,0,0.05);
}

.progress-bar {
    background-color: var(--bs-primary);
}
</style> 