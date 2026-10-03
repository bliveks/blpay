<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    logActivity($user_id, 'logout', 'User logged out');

    if (isset($_GET['all']) && $_GET['all'] === 'true') {
        // Əgər cookie varsa və cari cihazın tokeni məlumdursa
        $currentToken = null;
        if (isset($_COOKIE['remember_user'])) {
            list($uid, $token) = explode(':', $_COOKIE['remember_user']);
            if (!empty($uid) && !empty($token)) {
                $currentToken = $token;
            }
        }

        // Bütün tokenləri sil, cari cihaz xaric
        if ($currentToken) {
            $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ? AND token != ?");
            $stmt->execute([$user_id, $currentToken]);
        } else {
            // Əgər cari token məlum deyilsə, sadəcə hamısını sil
            $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
            $stmt->execute([$user_id]);
        }

        logActivity($user_id, 'logout_all_devices_except_current', 'Logged out from all devices except current');
        
        // Sessiyanı saxla və girişə yönləndir
        header('Location: ayarlar.php');
        exit;
    } else {
        // Yalnız cari cihazdan çıx
        if (isset($_COOKIE['remember_user'])) {
            list($uid, $token) = explode(':', $_COOKIE['remember_user']);
            if (!empty($uid) && !empty($token)) {
                $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ? AND token = ?");
                $stmt->execute([$uid, $token]);
            }
        }

        // Sessiyanı sıfırla
        session_unset();
        session_destroy();

        // Cookie-ni sil
        if (isset($_COOKIE['remember_user'])) {
            setcookie('remember_user', '', time() - 3600, '/', '', false, true);
        }

        header('Location: login.php');
        exit;
    }
}
