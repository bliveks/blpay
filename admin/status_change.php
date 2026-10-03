<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../index.php');
    exit();
}

// Check if POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['admin_error'] = 'Təhlükəsizlik xətası! Səhifəni yeniləyin və yenidən cəhd edin.';
        header('Location: users.php');
        exit();
    }
    
    // Get user ID and current status
    $user_id = (int)($_POST['user_id'] ?? 0);
    $current_status = $_POST['current_status'] ?? '';
    
    // Update user status
    if ($user_id > 0 && in_array($current_status, ['aktiv', 'deaktiv'])) {
        $new_status = ($current_status === 'aktiv') ? 'deaktiv' : 'aktiv';
        
        $stmt = $conn->prepare("UPDATE istifadeciler SET status = ? WHERE id = ?");
        $result = $stmt->execute([$new_status, $user_id]);
        
        if ($result) {
            // Log activity
            $user_info = $conn->prepare("SELECT email FROM istifadeciler WHERE id = ?");
            $user_info->execute([$user_id]);
            $email = $user_info->fetchColumn();
            
            logActivity(
                $_SESSION['user_id'], 
                'user_status_change', 
                "İstifadəçi statusu dəyişdirildi: $email, $current_status -> $new_status"
            );
            
            $_SESSION['admin_success'] = 'İstifadəçi statusu uğurla dəyişdirildi.';
        } else {
            $_SESSION['admin_error'] = 'Status dəyişdirilərkən xəta baş verdi.';
        }
    } else {
        $_SESSION['admin_error'] = 'Yanlış istifadəçi ID və ya status.';
    }
    
    // Redirect back to users page
    header('Location: users.php');
    exit();
} else {
    // Not a POST request, redirect
    header('Location: index.php');
    exit();
} 