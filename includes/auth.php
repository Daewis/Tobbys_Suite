<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php'; // role + permission helpers

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function getRoleDashboard(string $role): string {
    return match($role) {
        'tenant'      => 'tenant_dashboard.php',
        'admin',
        'manager',
        'accountant',
        'receptionist',
        'maintenance' => 'admin_dashboard.php',
        default       => 'login.php'
    };
}

function logActivity(string $action, string $details = ''): void {
    if (!isLoggedIn()) return;
    try {
        $db  = getDB();
        $ip  = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $action, $details, $ip]);
    } catch (Exception $e) {
        error_log("Activity Log Error: " . $e->getMessage());
    }
}

function login(string $email, string $password): bool {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if ($user['role'] === 'tenant' && !$user['is_verified']) {
            return false;
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email']   = $user['email'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['name']    = $user['name'];

        logActivity('Login', 'User logged into the system');
        return true;
    }
    return false;
}

function logout(): void {
    logActivity('Logout', 'User logged out');
    session_destroy();
    header('Location: login.php');
    exit;
}

/**
 * Seed initial Admin — runs once if no admin exists.
 */
function ensureAdminExists(): void {
    $db   = getDB();
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    if ($stmt->fetchColumn() == 0) {
        $pass = password_hash('B!vckbox123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (email, password, name, role, is_verified) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute(['davidabokunwa@gmail.com', $pass, 'System Administrator', 'admin']);
    }
}

ensureAdminExists();