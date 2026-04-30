<?php
// includes/permissions.php
// Central permission matrix for all staff roles.
// Usage: hasPermission('manage_tenants') — returns true/false based on session role.

define('ROLE_PERMISSIONS', [
    'admin' => [
        'manage_staff', 'manage_tenants', 'manage_apartments',
        'view_payments', 'record_payments', 'view_reports',
        'manage_bills', 'manage_notices', 'manage_maintenance',
        'view_visitors', 'log_visitors', 'view_activity_logs',
        'send_reminders', 'manage_documents',
    ],
    'manager' => [
        'manage_tenants', 'manage_apartments',
        'view_payments', 'view_reports',
        'manage_notices', 'manage_maintenance',
        'view_visitors', 'send_reminders', 'manage_documents',
    ],
    'accountant' => [
        'view_payments', 'record_payments', 'view_reports',
        'manage_bills', 'send_reminders',
    ],
    'receptionist' => [
        'view_tenants',       // read-only tenant list
        'manage_notices',
        'submit_maintenance', // can submit, not resolve
        'view_visitors', 'log_visitors',
    ],
    'maintenance' => [
        'view_own_tickets',   // only tickets assigned to them
        'update_ticket_status',
    ],
]);

/**
 * Check if the currently logged-in user has a given permission.
 */
function hasPermission(string $permission): bool {
    $role = $_SESSION['role'] ?? '';
    $map  = ROLE_PERMISSIONS;
    return isset($map[$role]) && in_array($permission, $map[$role]);
}

/**
 * Abort with 403 if the user lacks a permission.
 */
function requirePermission(string $permission): void {
    if (!hasPermission($permission)) {
        http_response_code(403);
        die('<p style="font-family:monospace;padding:2rem;">403 — You do not have permission to access this page.</p>');
    }
}

/**
 * Require that the logged-in user is one of the given roles.
 * Redirects to login if not authenticated, 403 if wrong role.
 */
function requireRole(array $roles): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    if (!in_array($_SESSION['role'], $roles)) {
        http_response_code(403);
        die('<p style="font-family:monospace;padding:2rem;">403 — Access denied for your role.</p>');
    }
}

/**
 * Returns human-readable label + style for a given role.
 */
function roleInfo(string $role): array {
    return match($role) {
        'admin'       => ['label' => 'Admin',       'bg' => 'bg-red-50',    'text' => 'text-red-600'],
        'manager'     => ['label' => 'Manager',     'bg' => 'bg-blue-50',   'text' => 'text-blue-600'],
        'accountant'  => ['label' => 'Accountant',  'bg' => 'bg-green-50',  'text' => 'text-green-700'],
        'receptionist'=> ['label' => 'Receptionist','bg' => 'bg-purple-50', 'text' => 'text-purple-600'],
        'maintenance' => ['label' => 'Maintenance', 'bg' => 'bg-orange-50', 'text' => 'text-orange-600'],
        'tenant'      => ['label' => 'Tenant',      'bg' => 'bg-slate-100', 'text' => 'text-slate-600'],
        default       => ['label' => ucfirst($role),'bg' => 'bg-slate-100', 'text' => 'text-slate-500'],
    };
}