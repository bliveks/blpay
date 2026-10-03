<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
function analyzeUserAgent($userAgent) {
    $device = 'Kompüter';

    if (preg_match('/mobile/i', $userAgent)) {
        $device = 'Telefon';
    } elseif (preg_match('/tablet|ipad/i', $userAgent)) {
        $device = 'Planşet';
    }

    // Sadə browser və OS axtarışı
    $browser = 'Naməlum';
    if (stripos($userAgent, 'Chrome') !== false) {
        $browser = 'Chrome';
    } elseif (stripos($userAgent, 'Firefox') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($userAgent, 'Safari') !== false && stripos($userAgent, 'Chrome') === false) {
        $browser = 'Safari';
    } elseif (stripos($userAgent, 'Edge') !== false) {
        $browser = 'Edge';
    }

    $os = 'Naməlum';
    if (stripos($userAgent, 'Windows') !== false) {
        $os = 'Windows';
    } elseif (stripos($userAgent, 'Android') !== false) {
        $os = 'Android';
    } elseif (stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false) {
        $os = 'iOS';
    } elseif (stripos($userAgent, 'Macintosh') !== false) {
        $os = 'macOS';
    }

    return [
        'device' => $device,
        'browser' => $browser,
        'os' => $os
    ];
}

// Security functions
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function logout() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

// Database helper functions
function executeQuery($sql, $params = []) {
    global $conn;
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}

// Format functions
function formatMoney($amount) {
    $amount = $amount ?? 0;
    return number_format($amount, 2, '.', ',');
}

function formatDate($date) {
    if (empty($date)) {
        return '';
    }
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '';
    }
    return date('d.m.Y', $timestamp);
}

function formatHesabNovu($hesab_novu) {
    $hesablar = [
        'nagd' => 'Nağd',
        'kredit_kart' => 'Kredit kartı',
        'debet_kart' => 'Debet kartı'
    ];
    return $hesablar[$hesab_novu] ?? $hesab_novu;
}

function formatMonthName($date, $show_year = true) {
    $months = [
        'January' => 'Yanvar',
        'February' => 'Fevral',
        'March' => 'Mart',
        'April' => 'Aprel',
        'May' => 'May',
        'June' => 'İyun',
        'July' => 'İyul',
        'August' => 'Avqust',
        'September' => 'Sentyabr',
        'October' => 'Oktyabr',
        'November' => 'Noyabr',
        'December' => 'Dekabr'
    ];
    
    $timestamp = strtotime($date);
    $month_name = date('F', $timestamp);
    $year = date('Y', $timestamp);
    $current_year = date('Y');
    
    $formatted_month = $months[$month_name];
    
    if ($show_year && $year != $current_year) {
        return $formatted_month . ' ' . $year;
    }
    
    return $formatted_month;
}

// Validation functions
function validatePassword($password) {
    // "123" parolunu xüsusi olaraq qəbul et
    if ($password === '123') {
        return true;
    }
    // Əks halda əvvəlki qaydalara bax
    return strlen($password) >= 8 
        && preg_match('/[A-Z]/', $password) 
        && preg_match('/[a-z]/', $password) 
        && preg_match('/[0-9]/', $password) 
        && preg_match('/[^A-Za-z0-9]/', $password);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Audit logging
function logActivity($user_id, $action, $details) {
    $sql = "INSERT INTO audit_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
    $params = [$user_id, $action, $details, $_SERVER['REMOTE_ADDR']];
    executeQuery($sql, $params);
}

function isRegistrationCompleted() {
    global $conn;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $stmt = $conn->prepare("SELECT qeydiyyat_tamamlanib FROM istifadeciler WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return (bool)$stmt->fetchColumn();
}

// Savings Goals Functions
function getActiveGoals($user_id, $limit = null) {
    global $conn;
    
    // Bütün aktiv hədəfləri əldə edək
    $sql = "
        SELECT 
            h.id,
            h.ad,
            h.cari_mebleq,
            h.hedef_mebleq,
            h.hedef_tarixi,
            h.prioritet,
            h.status,
            h.istifadeci_id,
            h.yaradilma_tarixi
        FROM hedefler h
        WHERE h.istifadeci_id = ? AND h.status = 'aktiv'
        ORDER BY h.yaradilma_tarixi DESC, h.prioritet ASC, h.hedef_tarixi ASC
    ";
    
    if ($limit !== null) {
        $sql .= " LIMIT " . (int)$limit;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    $goals = $stmt->fetchAll();
    
    // Debug məlumatı
    error_log("Aktiv hədəflər: " . count($goals));
    error_log("SQL: " . $sql);
    error_log("User ID: " . $user_id);
    
    // Hər bir hədəf üçün progress və aylıq hədəf hesabla
    foreach ($goals as &$goal) {
        $goal['progress'] = calculateGoalProgress($goal);
        $goal['monthly_target'] = calculateMonthlyTargetAmount($goal);
    }
    
    return $goals;
}

function getGoalById($goal_id, $user_id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT * FROM hedefler 
        WHERE id = ? AND istifadeci_id = ?
    ");
    $stmt->execute([$goal_id, $user_id]);
    return $stmt->fetch();
}

function calculateGoalProgress($goal) {
    $progress = 0;
    if ($goal['hedef_mebleq'] > 0) {
        $progress = ($goal['cari_mebleq'] / $goal['hedef_mebleq']) * 100;
    }
    return min(100, $progress); // Ensure progress doesn't exceed 100%
}

function calculateMonthlyTargetAmount($goal) {
    // For free period, consider 12 months as standard period
    if (strtotime($goal['hedef_tarixi']) > strtotime('+9 years')) {
        $amount_needed = $goal['hedef_mebleq'] - $goal['cari_mebleq'];
        return $amount_needed / 12; // Standard 12-month payment plan
    }
    
    $today = time();
    $target_date = strtotime($goal['hedef_tarixi']);
    
    // If target date is in the past, return full amount needed
    if ($target_date < $today) {
        return $goal['hedef_mebleq'] - $goal['cari_mebleq'];
    }
    
    // Calculate days difference
    $diff_seconds = $target_date - $today;
    $diff_days = round($diff_seconds / (60 * 60 * 24));
    
    // Convert days to months for calculation (using 30 days as average month)
    $months = max(1, round($diff_days / 30));
    
    // Calculate required monthly amount
    $amount_needed = $goal['hedef_mebleq'] - $goal['cari_mebleq'];
    
    return $amount_needed / $months;
}

function predictGoalCompletionDate($goal, $avg_monthly_savings) {
    if ($avg_monthly_savings <= 0) {
        return null; // Can't predict with no savings
    }
    
    // If the goal has free period (very far date), don't show prediction
    if (strtotime($goal['hedef_tarixi']) > strtotime('+9 years')) {
        return null;
    }
    
    $amount_needed = $goal['hedef_mebleq'] - $goal['cari_mebleq'];
    
    // If already completed, return current date
    if ($amount_needed <= 0) {
        return date('Y-m-d');
    }
    
    // Calculate days needed (assuming 30 days per month)
    $months_needed = ceil($amount_needed / $avg_monthly_savings);
    $days_needed = $months_needed * 30;
    
    // Calculate predicted completion date
    $predicted_date = strtotime("+{$days_needed} days");
    $predicted_date_str = date('Y-m-d', $predicted_date);
    
    // Get target date for comparison
    $target_date = strtotime($goal['hedef_tarixi']);
    
    // Return predicted date only if it's significantly different from target date
    // (difference more than 14 days to make it more noticeable)
    $diff_seconds = abs($predicted_date - $target_date);
    $diff_days = round($diff_seconds / (60 * 60 * 24));
    
    if ($diff_days <= 14) {
        return null; // Don't show prediction if it's close to target date
    }
    
    return $predicted_date_str;
}

function getAverageUserMonthlySavings($user_id) {
    global $conn;
    
    // Get last 6 months of savings transactions
    $sixMonthsAgo = date('Y-m-d', strtotime('-6 months'));
    
    $stmt = $conn->prepare("
        SELECT SUM(CASE WHEN emeliyyat_novu = 'artirma' THEN mebleq ELSE -mebleq END) as total_savings,
               COUNT(DISTINCT EXTRACT(YEAR_MONTH FROM tarix)) as month_count
        FROM hedef_emeliyyatlar
        WHERE istifadeci_id = ? AND tarix >= ?
    ");
    $stmt->execute([$user_id, $sixMonthsAgo]);
    $result = $stmt->fetch();
    
    if ($result['month_count'] > 0) {
        return $result['total_savings'] / $result['month_count'];
    }
    
    return 0; // No savings history
}

function updateGoalAmount($goal_id, $user_id, $amount, $operation, $note = '') {
    global $conn;
    
    // Start transaction
    $conn->beginTransaction();
    
    try {
        // First get the goal
        $stmt = $conn->prepare("SELECT * FROM hedefler WHERE id = ? AND istifadeci_id = ? FOR UPDATE");
        $stmt->execute([$goal_id, $user_id]);
        $goal = $stmt->fetch();
        
        if (!$goal) {
            $conn->rollBack();
            return false;
        }
        
        // Calculate new amount
        $new_amount = $goal['cari_mebleq'];
        if ($operation === 'artirma') {
            $new_amount += $amount;
        } else {
            $new_amount -= $amount;
            if ($new_amount < 0) $new_amount = 0; // Don't allow negative amounts
        }
        
        // Update goal amount
        $stmt = $conn->prepare("UPDATE hedefler SET cari_mebleq = ? WHERE id = ?");
        $stmt->execute([$new_amount, $goal_id]);
        
        // Record transaction
        $stmt = $conn->prepare("
            INSERT INTO hedef_emeliyyatlar (hedef_id, istifadeci_id, mebleq, emeliyyat_novu, tarix, qeyd)
            VALUES (?, ?, ?, ?, CURDATE(), ?)
        ");
        $stmt->execute([$goal_id, $user_id, $amount, $operation, $note]);
        
        // Check if goal is completed
        if ($new_amount >= $goal['hedef_mebleq']) {
            $stmt = $conn->prepare("UPDATE hedefler SET status = 'tamamlanib' WHERE id = ?");
            $stmt->execute([$goal_id]);
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollBack();
        return false;
    }
}

// Check if user is admin
function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    
    global $conn;
    $stmt = $conn->prepare("SELECT rol FROM istifadeciler WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_role = $stmt->fetchColumn();
    
    return $user_role === 'admin';
}

// Monthly report functions
function getCurrentMonth() {
    return date('Y-m');
}

function getPreviousMonth() {
    return date('Y-m', strtotime('-1 month'));
}

function isNewMonth() {
    $today = date('d');
    return $today === '01';
}

function resetMonthlyBalances() {
    global $conn;
    $currentMonth = getCurrentMonth();
    $stmt = $conn->prepare("UPDATE istifadeciler SET aylik_gelir = 0, aylik_xerc = 0 WHERE DATE_FORMAT(son_giris, '%Y-%m') = ?");
    $stmt->execute([$currentMonth]);
}

function getMonthlyReport($user_id, $month = null) {
    global $conn;
    $month = $month ?? getCurrentMonth();
    $startDate = $month . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    
    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN type = 'gelir' THEN mebleq ELSE 0 END) as total_income,
            SUM(CASE WHEN type = 'xerc' THEN mebleq ELSE 0 END) as total_expense
        FROM (
            SELECT 'gelir' as type, mebleq FROM gelirler WHERE istifadeci_id = ? AND tarix BETWEEN ? AND ?
            UNION ALL
            SELECT 'xerc' as type, mebleq FROM xercler WHERE istifadeci_id = ? AND tarix BETWEEN ? AND ?
        ) as transactions
    ");
    $stmt->execute([$user_id, $startDate, $endDate, $user_id, $startDate, $endDate]);
    return $stmt->fetch();
} 