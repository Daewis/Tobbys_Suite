<?php
// includes/notifications.php
// Helper functions for the notification system.
// Always require auth.php before this file.

/**
 * Create a notification for a single user.
 *
 * @param PDO    $db
 * @param int    $user_id      Recipient's users.id
 * @param string $type         'payment' | 'complaint' | 'bill' | 'notice' | 'maintenance' | 'reminder'
 * @param string $title        Short heading shown in the bell dropdown
 * @param string $message      Full description
 * @param string|null $link    Page to go to when clicked (e.g. 'tenant_payments.php')
 * @param int|null $from_user_id  Who triggered it (optional)
 */
function createNotification(
    PDO $db,
    int $user_id,
    string $type,
    string $title,
    string $message,
    ?string $link = null,
    ?int $from_user_id = null
): bool {
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, from_user_id, type, title, message, link)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$user_id, $from_user_id, $type, $title, $message, $link]);
    } catch (Exception $e) {
        error_log('Notification Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Notify ALL staff with one or more roles at once.
 * Useful for: tenant pays rent → notify all admins + managers + accountants.
 *
 * @param PDO      $db
 * @param array    $roles         e.g. ['admin', 'manager', 'accountant']
 * @param string   $type
 * @param string   $title
 * @param string   $message
 * @param string|null $link
 * @param int|null $from_user_id
 * @param int|null $exclude_user_id  Don't notify this user (e.g. the one who triggered it)
 */
function notifyStaff(
    PDO $db,
    array $roles,
    string $type,
    string $title,
    string $message,
    ?string $link = null,
    ?int $from_user_id = null,
    ?int $exclude_user_id = null
): void {
    try {
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $sql = "SELECT id FROM users WHERE role IN ($placeholders)";
        $params = $roles;

        if ($exclude_user_id) {
            $sql    .= ' AND id != ?';
            $params[] = $exclude_user_id;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $staff = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($staff as $uid) {
            createNotification($db, (int)$uid, $type, $title, $message, $link, $from_user_id);
        }
    } catch (Exception $e) {
        error_log('notifyStaff Error: ' . $e->getMessage());
    }
}

/**
 * Notify ALL active tenants.
 * Useful for: admin posts a notice → every tenant gets pinged.
 *
 * @param PDO      $db
 * @param string   $type
 * @param string   $title
 * @param string   $message
 * @param string|null $link
 * @param int|null $from_user_id
 */
function notifyAllTenants(
    PDO $db,
    string $type,
    string $title,
    string $message,
    ?string $link = null,
    ?int $from_user_id = null
): void {
    try {
        $stmt = $db->query("SELECT user_id FROM tenants WHERE status = 'Active'");
        $tenants = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tenants as $uid) {
            createNotification($db, (int)$uid, $type, $title, $message, $link, $from_user_id);
        }
    } catch (Exception $e) {
        error_log('notifyAllTenants Error: ' . $e->getMessage());
    }
}

/**
 * Notify a single tenant by their tenants.id (not users.id).
 *
 * @param PDO      $db
 * @param int      $tenant_id   tenants.id
 * @param string   $type
 * @param string   $title
 * @param string   $message
 * @param string|null $link
 * @param int|null $from_user_id
 */
function notifyTenant(
    PDO $db,
    int $tenant_id,
    string $type,
    string $title,
    string $message,
    ?string $link = null,
    ?int $from_user_id = null
): bool {
    try {
        $stmt = $db->prepare("SELECT user_id FROM tenants WHERE id = ?");
        $stmt->execute([$tenant_id]);
        $uid = $stmt->fetchColumn();
        if (!$uid) return false;
        return createNotification($db, (int)$uid, $type, $title, $message, $link, $from_user_id);
    } catch (Exception $e) {
        error_log('notifyTenant Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Fetch notifications for the logged-in user.
 *
 * @param PDO  $db
 * @param int  $user_id
 * @param int  $limit     Max rows to return (default 20)
 * @param bool $unread_only
 * @return array
 */
function getNotifications(PDO $db, int $user_id, int $limit = 20, bool $unread_only = false): array {
    try {
        $where = $unread_only ? 'AND is_read = 0' : '';
        $stmt  = $db->prepare("
            SELECT * FROM notifications
            WHERE user_id = ? $where
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('getNotifications Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Count unread notifications for a user.
 */
function countUnread(PDO $db, int $user_id): int {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Mark one notification as read.
 */
function markAsRead(PDO $db, int $notification_id, int $user_id): bool {
    try {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notification_id, $user_id]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Mark ALL notifications as read for a user.
 */
function markAllRead(PDO $db, int $user_id): bool {
    try {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        return $stmt->execute([$user_id]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Returns a Tailwind color class + icon for each notification type.
 * Used when rendering the bell dropdown.
 */
function notificationMeta(string $type): array {
    return match($type) {
        'payment'     => ['icon' => 'payments',              'bg' => 'bg-green-50',  'text' => 'text-green-600'],
        'complaint'   => ['icon' => 'engineering',           'bg' => 'bg-orange-50', 'text' => 'text-orange-500'],
        'maintenance' => ['icon' => 'check_circle',          'bg' => 'bg-blue-50',   'text' => 'text-blue-600'],
        'bill'        => ['icon' => 'receipt_long',          'bg' => 'bg-yellow-50', 'text' => 'text-yellow-600'],
        'notice'      => ['icon' => 'campaign',              'bg' => 'bg-purple-50', 'text' => 'text-purple-600'],
        'reminder'    => ['icon' => 'notification_important','bg' => 'bg-red-50',    'text' => 'text-red-500'],
        default       => ['icon' => 'notifications',         'bg' => 'bg-slate-50',  'text' => 'text-slate-500'],
    };
}

/**
 * Human-readable time-ago string.
 * e.g. "2 minutes ago", "3 hours ago", "Yesterday"
 */
function timeAgo(string $datetime): string {
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);

    if ($diff->i < 1 && $diff->h === 0 && $diff->d === 0)  return 'Just now';
    if ($diff->h === 0 && $diff->d === 0)                   return $diff->i . 'm ago';
    if ($diff->d === 0)                                      return $diff->h . 'h ago';
    if ($diff->d === 1)                                      return 'Yesterday';
    if ($diff->d < 7)                                        return $diff->d . 'd ago';
    return $past->format('d M');
}