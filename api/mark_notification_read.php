<?php
// api/mark_notification_read.php
// AJAX endpoint — called by the bell dropdown JS.
// Accepts POST with JSON body: { "id": 5 } or { "all": true }
// Always returns JSON.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';

header('Content-Type: application/json');

// Must be logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$db      = getDB();
$user_id = (int) $_SESSION['user_id'];

// Parse JSON body
$body = json_decode(file_get_contents('php://input'), true);

// ── Mark ALL read ─────────────────────────────────────────────────────────────
if (!empty($body['all'])) {
    $ok = markAllRead($db, $user_id);
    echo json_encode([
        'status'  => $ok ? 'ok' : 'error',
        'message' => $ok ? 'All notifications marked as read' : 'Failed to update',
    ]);
    exit;
}

// ── Mark ONE read ─────────────────────────────────────────────────────────────
$notification_id = isset($body['id']) ? (int) $body['id'] : 0;

if ($notification_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid notification id']);
    exit;
}

// Security: make sure this notification belongs to the logged-in user
$stmt = $db->prepare("SELECT id FROM notifications WHERE id = ? AND user_id = ?");
$stmt->execute([$notification_id, $user_id]);

if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Notification not found']);
    exit;
}

$ok = markAsRead($db, $notification_id, $user_id);
echo json_encode([
    'status'  => $ok ? 'ok' : 'error',
    'message' => $ok ? 'Marked as read' : 'Failed to update',
    'unread'  => countUnread($db, $user_id), // return updated count so badge can refresh
]);