<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$db = getDB();
$reference = $_GET['reference'] ?? '';

if (empty($reference)) {
    header('Location: ../tenant_dashboard.php?error=no_reference');
    exit;
}

// Secret key from env (or set here if you haven't configured .env yet)
// We'll proceed if successful
try {
    // 1. Verify payment with Paystack
    // In a production environment, you would use CURL to fetch:
    // https://api.paystack.co/transaction/verify/:reference
    // with your Secret Key in the Authorization header.
    
    /* MOCK VERIFICATION LOGIC START */
    // For demo purposes, we'll "verify" any valid-looking reference
    $is_valid = true; 
    $amount_kobo = $_GET['amount'] ?? 0;
    $amount_naira = $amount_kobo / 100;
    /* MOCK VERIFICATION LOGIC END */

    if ($is_valid) {
        $tenant_id = $_SESSION['user_id']; // For tenants, user_id is linked
        // Note: For actual payment tracking, we need the tenant_id from the 'tenants' table, not 'users' table
        $stmt = $db->prepare("SELECT id FROM tenants WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $tenant = $stmt->fetch();
        
        if ($tenant) {
            $month_paid_for = date('m-Y');
            $receipt_no = 'PAY-' . strtoupper($reference);
            
            // Record the payment
            $stmt = $db->prepare("INSERT INTO payments (tenant_id, amount, payment_date, method, receipt_no, month_paid_for, status) 
                                   VALUES (?, ?, CURRENT_TIMESTAMP, 'Paystack', ?, ?, 'Paid')");
            $stmt->execute([$tenant['id'], $amount_naira, $receipt_no, $month_paid_for]);
            
            header('Location: ../tenant_dashboard.php?success=payment_received');
            exit;
        }
    }

    header('Location: ../tenant_dashboard.php?error=verification_failed');
    exit;

} catch (Exception $e) {
    header('Location: ../tenant_dashboard.php?error=system_error');
    exit;
}
