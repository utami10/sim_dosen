<?php
// logout.php - keluar dari sistem (hanya lewat POST + token CSRF), lalu kembali ke halaman utama.
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

// Dibuka langsung lewat alamat (GET): jangan keluar, arahkan saja ke tempat yang sesuai.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(isset($_SESSION['user_id']) ? 'dashboard.php' : 'index.php');
}

$token = $_POST['csrf_token'] ?? null;
if (!csrf_validate(is_string($token) ? $token : null)) {
    http_response_code(400);
    exit('Permintaan keluar tidak valid. Kembali ke dashboard lalu coba lagi.');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

redirect('index.php');