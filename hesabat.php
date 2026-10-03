<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get selected month from GET parameter or session
$selected_month = $_GET['month'] ?? $_SESSION['selected_month'] ?? getCurrentMonth();
$_SESSION['selected_month'] = $selected_month;

// Get monthly report
$monthly_report = getMonthlyReport($user_id, $selected_month);
$total_income = $monthly_report['total_income'] ?? 0;
$total_expense = $monthly_report['total_expense'] ?? 0;
$total_balance = $total_income - $total_expense;

// Get income details by account type
$stmt = $conn->prepare("
    SELECT hesab_novu, SUM(mebleq) as total_amount
    FROM gelirler 
    WHERE istifadeci_id = ? AND DATE_FORMAT(tarix, '%Y-%m') = ?
    GROUP BY hesab_novu
");
$stmt->execute([$user_id, $selected_month]);
$gelir_details = $stmt->fetchAll();

// Get expense details by account type
$stmt = $conn->prepare("
    SELECT hesab_novu, SUM(mebleq) as total_amount
    FROM xercler 
    WHERE istifadeci_id = ? AND DATE_FORMAT(tarix, '%Y-%m') = ?
    GROUP BY hesab_novu
");
$stmt->execute([$user_id, $selected_month]);
$xerc_details = $stmt->fetchAll();

// Get available months for dropdown
$stmt = $conn->prepare("
    SELECT DISTINCT DATE_FORMAT(tarix, '%Y-m') as ay
    FROM (
        SELECT tarix FROM gelirler WHERE istifadeci_id = ?
        UNION
        SELECT tarix FROM xercler WHERE istifadeci_id = ?
    ) t
    ORDER BY ay DESC
");
$stmt->execute([$user_id, $user_id]);
$available_months = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get transactions for selected month
$start_date = $selected_month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

// Get unique values for filters
$stmt = $conn->prepare("
    SELECT DISTINCT tarix FROM (
        SELECT tarix FROM gelirler WHERE istifadeci_id = ? AND DATE_FORMAT(tarix, '%Y-%m') = ?
        UNION
        SELECT tarix FROM xercler WHERE istifadeci_id = ? AND DATE_FORMAT(tarix, '%Y-%m') = ?
    ) t ORDER BY tarix DESC
");
$stmt->execute([$user_id, $selected_month, $user_id, $selected_month]);
$unique_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $conn->prepare("
    SELECT DISTINCT hesab_novu FROM (
        SELECT hesab_novu FROM gelirler WHERE istifadeci_id = ? AND DATE_FORMAT(tarix, '%Y-%m') = ?
        UNION
        SELECT hesab_novu FROM xercler WHERE istifadeci_id = ? AND DATE_FORMAT(tarix, '%Y-%m') = ?
    ) t ORDER BY hesab_novu
");
$stmt->execute([$user_id, $selected_month, $user_id, $selected_month]);
$unique_accounts = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $conn->prepare("
    SELECT DISTINCT k.ad FROM (
        SELECT kateqoriya_id FROM gelirler WHERE istifadeci_id = ? AND DATE_FORMAT(tarix, '%Y-%m') = ?
        UNION
        SELECT kateqoriya_id FROM xercler WHERE istifadeci_id = ? AND DATE_FORMAT(tarix, '%Y-%m') = ?
    ) t
    JOIN kateqoriyalar k ON t.kateqoriya_id = k.id
    ORDER BY k.ad
");
$stmt->execute([$user_id, $selected_month, $user_id, $selected_month]);
$unique_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $conn->prepare("
    SELECT DISTINCT mebleq FROM (
        SELECT mebleq FROM gelirler WHERE istifadeci_id = ? AND DATE_FORMAT(tarix, '%Y-%m') = ?
        UNION
        SELECT mebleq FROM xercler WHERE istifadeci_id = ? AND DATE_FORMAT(tarix, '%Y-%m') = ?
    ) t ORDER BY mebleq
");
$stmt->execute([$user_id, $selected_month, $user_id, $selected_month]);
$unique_amounts = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $conn->prepare("
    SELECT DISTINCT qeyd FROM (
        SELECT qeyd FROM gelirler WHERE istifadeci_id = ? AND DATE_FORMAT(tarix, '%Y-%m') = ? AND qeyd IS NOT NULL
        UNION
        SELECT qeyd FROM xercler WHERE istifadeci_id = ? AND DATE_FORMAT(tarix, '%Y-%m') = ? AND qeyd IS NOT NULL
    ) t ORDER BY qeyd
");
$stmt->execute([$user_id, $selected_month, $user_id, $selected_month]);
$unique_notes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get filter values
$date_filter = $_GET['date_filter'] ?? '';
$type_filter = $_GET['type_filter'] ?? '';
$account_filter = $_GET['account_filter'] ?? '';
$category_filter = $_GET['category_filter'] ?? '';
$amount_filter = $_GET['amount_filter'] ?? '';
$note_filter = $_GET['note_filter'] ?? '';

// Modify the transactions query to include filters
$where_conditions = ["DATE_FORMAT(t.tarix, '%Y-%m') = ?"];
$params = [$selected_month];

if ($date_filter) {
    $where_conditions[] = "t.tarix = ?";
    $params[] = $date_filter;
}
if ($type_filter) {
    $where_conditions[] = "t.tip = ?";
    $params[] = $type_filter;
}
if ($account_filter) {
    $where_conditions[] = "t.hesab_novu = ?";
    $params[] = $account_filter;
}
if ($category_filter) {
    $where_conditions[] = "k.ad = ?";
    $params[] = $category_filter;
}
if ($amount_filter) {
    $where_conditions[] = "t.mebleq = ?";
    $params[] = $amount_filter;
}
if ($note_filter) {
    $where_conditions[] = "t.qeyd = ?";
    $params[] = $note_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get transactions with filters
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
        WHERE istifadeci_id = ? AND DATE_FORMAT(tarix, '%Y-%m') = ?
        UNION ALL
        SELECT 'xerc' as tip, tarix, hesab_novu, kateqoriya_id, mebleq, qeyd, id
        FROM xercler 
        WHERE istifadeci_id = ? AND DATE_FORMAT(tarix, '%Y-%m') = ?
    ) t
    JOIN kateqoriyalar k ON t.kateqoriya_id = k.id
    WHERE " . $where_clause . "
    ORDER BY sort_date DESC, id DESC
");

$params = array_merge([$user_id, $selected_month, $user_id, $selected_month], $params);
$stmt->execute($params);
$emeliyyatlar = $stmt->fetchAll();

// Get account balances
$stmt = $conn->prepare("
    SELECT hesab_novu, 
        SUM(CASE WHEN type = 'gelir' THEN mebleq ELSE -mebleq END) as balance
    FROM (
        SELECT 'gelir' as type, hesab_novu, mebleq 
        FROM gelirler 
        WHERE istifadeci_id = ? AND tarix BETWEEN ? AND ?
        UNION ALL
        SELECT 'xerc' as type, hesab_novu, mebleq 
        FROM xercler 
        WHERE istifadeci_id = ? AND tarix BETWEEN ? AND ?
    ) as all_transactions
    GROUP BY hesab_novu
    HAVING balance != 0
");
$stmt->execute([$user_id, $start_date, $end_date, $user_id, $start_date, $end_date]);
$balances_by_account = $stmt->fetchAll();

// Check if this is an AJAX request
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    // Calculate total amount for filtered transactions
    $total_filtered_amount = 0;
    foreach ($emeliyyatlar as $emeliyyat) {
        if ($emeliyyat['tip'] == 'gelir') {
            $total_filtered_amount += $emeliyyat['mebleq'];
        } else {
            $total_filtered_amount -= $emeliyyat['mebleq'];
        }
    }
    
    $html = '';
    if (empty($emeliyyatlar)) {
        $html = '<tr>
            <td colspan="6" class="text-center text-muted py-4">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                <p class="mb-0">Əməliyyat tapılmadı</p>
                <small>Seçilmiş ayda heç bir əməliyyat yoxdur</small>
            </td>
        </tr>';
    } else {
        foreach ($emeliyyatlar as $emeliyyat) {
            $html .= '<tr>
                <td>' . formatDate($emeliyyat['tarix']) . '</td>
                <td>';
            if ($emeliyyat['tip'] == 'gelir') {
                $html .= '<span class="badge bg-success">Gəlir</span>';
            } else {
                $html .= '<span class="badge bg-danger">Xərc</span>';
            }
            $html .= '</td>
                <td>
                    <span class="badge bg-light text-dark">
                        ' . htmlspecialchars(formatHesabNovu($emeliyyat['hesab_novu'])) . '
                    </span>
                </td>
                <td>' . htmlspecialchars($emeliyyat['kateqoriya']) . '</td>
                <td class="' . ($emeliyyat['tip'] == 'gelir' ? 'text-success' : 'text-danger') . ' fw-bold">
                    ' . formatMoney($emeliyyat['mebleq']) . '
                </td>
                <td>';
            if ($emeliyyat['qeyd']) {
                $html .= '<small class="text-muted">' . htmlspecialchars($emeliyyat['qeyd']) . '</small>';
            }
            $html .= '</td>
            </tr>';
        }
    }

    echo json_encode([
        'html' => $html,
        'total_filtered_amount' => $total_filtered_amount
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hesabat | Maliyyə İdarəetmə Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
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
                                    <small class="text-muted"><?php echo formatMonthName($selected_month); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card-body p-3">
                        <div class="stat-amount text-success mb-3">
                            <?php echo formatMoney($total_income); ?>
                        </div>
                        <div class="small text-muted">
                            <?php foreach ($gelir_details as $detail): ?>
                                <div><?php echo formatHesabNovu($detail['hesab_novu']); ?>: 
                                    <span class="text-success">
                                        <?php echo formatMoney($detail['total_amount']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
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
                                    <small class="text-muted"><?php echo formatMonthName($selected_month); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card-body p-3">
                        <div class="stat-amount text-danger mb-3">
                            <?php echo formatMoney($total_expense); ?>
                        </div>
                        <div class="small text-muted">
                            <?php foreach ($xerc_details as $detail): ?>
                                <div><?php echo formatHesabNovu($detail['hesab_novu']); ?>: 
                                    <span class="text-danger">
                                        <?php echo formatMoney($detail['total_amount']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
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
                                    <small class="text-muted"><?php echo formatMonthName($selected_month); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card-body p-3">
                        <div class="stat-amount <?php echo $total_balance >= 0 ? 'text-primary' : 'text-danger'; ?> mb-3">
                            <?php echo formatMoney($total_balance); ?>
                        </div>
                        <div class="small text-muted">
                            <?php foreach ($balances_by_account as $balance): ?>
                                <div><?php echo formatHesabNovu($balance['hesab_novu']); ?>: 
                                    <span class="<?php echo $balance['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo formatMoney($balance['balance']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-list"></i> Bütün əməliyyatlar
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" id="filterForm">
                            <input type="hidden" name="month" value="<?php echo $selected_month; ?>">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>
                                                <div class="d-flex flex-column">
                                                    <span>Tarix</span>
                                                    <select class="form-select form-select-sm mt-1" name="date_filter">
                                                        <option value="">Hamısı</option>
                                                        <?php foreach ($unique_dates as $date): ?>
                                                            <option value="<?php echo $date; ?>" <?php echo $date_filter == $date ? 'selected' : ''; ?>>
                                                                <?php echo formatDate($date); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="d-flex flex-column">
                                                    <span>Növ</span>
                                                    <select class="form-select form-select-sm mt-1" name="type_filter">
                                                        <option value="">Hamısı</option>
                                                        <option value="gelir" <?php echo $type_filter == 'gelir' ? 'selected' : ''; ?>>Gəlir</option>
                                                        <option value="xerc" <?php echo $type_filter == 'xerc' ? 'selected' : ''; ?>>Xərc</option>
                                                    </select>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="d-flex flex-column">
                                                    <span>Hesab</span>
                                                    <select class="form-select form-select-sm mt-1" name="account_filter">
                                                        <option value="">Hamısı</option>
                                                        <?php foreach ($unique_accounts as $account): ?>
                                                            <option value="<?php echo $account; ?>" <?php echo $account_filter == $account ? 'selected' : ''; ?>>
                                                                <?php echo formatHesabNovu($account); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="d-flex flex-column">
                                                    <span>Kateqoriya</span>
                                                    <select class="form-select form-select-sm mt-1" name="category_filter">
                                                        <option value="">Hamısı</option>
                                                        <?php foreach ($unique_categories as $category): ?>
                                                            <option value="<?php echo $category; ?>" <?php echo $category_filter == $category ? 'selected' : ''; ?>>
                                                                <?php echo $category; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="d-flex flex-column">
                                                    <span>Məbləğ
                                                    <small class="text-muted mt-1" id="totalFilteredAmount">
                                                        <?php
                                                        $total_filtered_amount = 0;
                                                        foreach ($emeliyyatlar as $emeliyyat) {
                                                            if ($emeliyyat['tip'] == 'gelir') {
                                                                $total_filtered_amount += $emeliyyat['mebleq'];
                                                            } else {
                                                                $total_filtered_amount -= $emeliyyat['mebleq'];
                                                            }
                                                        }
                                                        echo "(" . formatMoney($total_filtered_amount) . ")";
                                                        ?>
                                                    </small></span>
                                                    <select class="form-select form-select-sm mt-1" name="amount_filter">
                                                        <option value="">Hamısı</option>
                                                        <?php foreach ($unique_amounts as $amount): ?>
                                                            <option value="<?php echo $amount; ?>" <?php echo $amount_filter == (string)$amount ? 'selected' : ''; ?>>
                                                                <?php echo formatMoney($amount); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="d-flex flex-column">
                                                    <span>Qeyd</span>
                                                    <select class="form-select form-select-sm mt-1" name="note_filter">
                                                        <option value="">Hamısı</option>
                                                        <?php foreach ($unique_notes as $note): ?>
                                                            <option value="<?php echo htmlspecialchars($note); ?>" <?php echo $note_filter == $note ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($note); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($emeliyyatlar)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">
                                                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                                    <p class="mb-0">Əməliyyat tapılmadı</p>
                                                    <small>Seçilmiş ayda heç bir əməliyyat yoxdur</small>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($emeliyyatlar as $emeliyyat): ?>
                                                <tr>
                                                    <td><?php echo formatDate($emeliyyat['tarix']); ?></td>
                                                    <td>
                                                        <?php if ($emeliyyat['tip'] == 'gelir'): ?>
                                                            <span class="badge bg-success">Gəlir</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Xərc</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-light text-dark">
                                                            <?php echo htmlspecialchars(formatHesabNovu($emeliyyat['hesab_novu'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($emeliyyat['kateqoriya']); ?></td>
                                                    <td class="<?php echo $emeliyyat['tip'] == 'gelir' ? 'text-success' : 'text-danger'; ?> fw-bold">
                                                        <?php echo formatMoney($emeliyyat['mebleq']); ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($emeliyyat['qeyd']): ?>
                                                            <small class="text-muted"><?php echo htmlspecialchars($emeliyyat['qeyd']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.getElementById('filterForm');
            const filterSelects = filterForm.querySelectorAll('select');
            const tbody = document.querySelector('tbody');
            const totalFilteredAmount = document.getElementById('totalFilteredAmount');

            // Form submit hadisəsini dayandır
            filterForm.addEventListener('submit', function(e) {
                e.preventDefault();
            });

            // Bütün select elementlərinə change hadisəsi əlavə et
            filterSelects.forEach(select => {
                select.addEventListener('change', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const formData = new FormData(filterForm);
                    formData.append('ajax', '1');
                    
                    // URL parametrlərini yarat
                    const params = new URLSearchParams(formData);
                    
                    // AJAX sorğusu göndər
                    fetch('hesabat.php?' + params.toString())
                        .then(response => response.json())
                        .then(data => {
                            tbody.innerHTML = data.html;
                            totalFilteredAmount.textContent = "(" + data.total_filtered_amount + ")";
                        })
                        .catch(error => {
                            console.error('Error:', error);
                        });
                });
            });
        });
    </script>

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

        .text-muted {
            color: #6c757d !important;
        }
        
        #totalFilteredAmount {
            font-size: 0.875rem;
            color: #6c757d;
        }
    </style>
</body>
</html> 