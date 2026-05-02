<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/notifications.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$db        = getDB();
$reference = trim($_GET['reference'] ?? '');

if (empty($reference)) {
    header('Location: ../tenant_payments.php?error=no_reference');
    exit;
}

try {
    // ── 1. Verify with Paystack API ───────────────────────────────────────────
    $ch = curl_init('https://api.paystack.co/transaction/verify/' . rawurlencode($reference));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response   = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new Exception('cURL error: ' . $curl_error);
    }

    $result = json_decode($response, true);

    // ── 2. Confirm status and amount ──────────────────────────────────────────
    if (!$result || !$result['status'] || $result['data']['status'] !== 'success') {
        header('Location: ../tenant_payments.php?error=verification_failed');
        exit;
    }

    $verified_amount_kobo  = (int) $result['data']['amount'];
    $verified_amount_naira = $verified_amount_kobo / 100;

    $expected_kobo = (int) ($_GET['amount'] ?? 0);
    if ($expected_kobo > 0 && abs($verified_amount_kobo - $expected_kobo) > 1) {
        logActivity('Payment Mismatch', "Ref: $reference — expected {$expected_kobo} kobo, got {$verified_amount_kobo}");
        header('Location: ../tenant_payments.php?error=amount_mismatch');
        exit;
    }

    // ── 3. Fetch tenant record (with name for notifications) ──────────────────
    $stmt = $db->prepare("SELECT t.id, t.name FROM tenants t WHERE t.user_id = ? AND t.status = 'Active'");
    $stmt->execute([$_SESSION['user_id']]);
    $tenant = $stmt->fetch();

    if (!$tenant) {
        header('Location: ../tenant_payments.php?error=tenant_not_found');
        exit;
    }

    $tenant_id    = $tenant['id'];
    $tenant_name  = $tenant['name'];
    $payment_type = trim($_GET['payment_type'] ?? 'Monthly');
    $allowed_types = ['Monthly', 'Quarterly', 'Yearly', 'Bill'];

    if (!in_array($payment_type, $allowed_types)) {
        $payment_type = 'Monthly';
    }

    // ── 4. Duplicate guard ────────────────────────────────────────────────────
    $stmt = $db->prepare("SELECT COUNT(*) FROM payments WHERE receipt_no = ?");
    $stmt->execute(['PAY-' . strtoupper($reference)]);
    if ($stmt->fetchColumn() > 0) {
        header('Location: ../tenant_payments.php?success=payment_received');
        exit;
    }

    $receipt_no = 'PAY-' . strtoupper($reference);

    // ── 5a. BILL payment ──────────────────────────────────────────────────────
    if ($payment_type === 'Bill') {
        $bill_id = (int) ($_GET['bill_id'] ?? 0);

        if ($bill_id <= 0) {
            header('Location: ../tenant_payments.php?error=invalid_bill');
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM bills WHERE id = ? AND tenant_id = ? AND status = 'Unpaid'");
        $stmt->execute([$bill_id, $tenant_id]);
        $bill = $stmt->fetch();

        if (!$bill) {
            header('Location: ../tenant_payments.php?error=bill_not_found');
            exit;
        }

        // Mark bill paid
        $stmt = $db->prepare("UPDATE bills SET status = 'Paid', paid_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$bill_id]);

        // Record payment
        $stmt = $db->prepare("INSERT INTO payments
                               (tenant_id, amount, payment_date, method, receipt_no, month_paid_for, status, payment_type)
                               VALUES (?, ?, CURRENT_TIMESTAMP, 'Paystack', ?, ?, 'Paid', 'Bill')");
        $stmt->execute([
            $tenant_id,
            $verified_amount_naira,
            $receipt_no,
            $bill['bill_type'] . ' — ' . date('M Y'),
        ]);

        logActivity('Bill Paid', "Bill #{$bill_id} ({$bill['bill_type']}) — ₦" . number_format($verified_amount_naira));

        // ── Notify staff: bill was paid by tenant ─────────────────────────────
        notifyStaff(
            $db,
            ['admin', 'manager', 'accountant'],
            'payment',
            'Bill Payment Received',
            $tenant_name . ' paid ₦' . number_format($verified_amount_naira) . ' for ' . $bill['bill_type'] . ' (' . date('M Y') . ')',
            'manage_bills.php',
            $_SESSION['user_id']
        );

        header('Location: ../tenant_payments.php?success=payment_received');
        exit;
    }

    // ── 5b. RENT payment (Monthly / Quarterly / Yearly) ───────────────────────
    $month_label = match($payment_type) {
        'Quarterly' => 'Q' . ceil(date('n') / 3) . ' ' . date('Y'),
        'Yearly'    => 'Annual ' . date('Y'),
        default     => date('F Y'),
    };

    $stmt = $db->prepare("INSERT INTO payments
                           (tenant_id, amount, payment_date, method, receipt_no, month_paid_for, status, payment_type)
                           VALUES (?, ?, CURRENT_TIMESTAMP, 'Paystack', ?, ?, 'Paid', ?)");
    $stmt->execute([
        $tenant_id,
        $verified_amount_naira,
        $receipt_no,
        $month_label,
        $payment_type,
    ]);

    logActivity('Rent Paid', "{$payment_type} rent ₦" . number_format($verified_amount_naira) . " — Ref: {$reference}");

    // ── Notify staff: rent was paid ───────────────────────────────────────────
    notifyStaff(
        $db,
        ['admin', 'manager', 'accountant'],
        'payment',
        'Rent Payment Received',
        $tenant_name . ' paid ₦' . number_format($verified_amount_naira) . ' — ' . $payment_type . ' (' . $month_label . ')',
        'manage_payments.php',
        $_SESSION['user_id']
    );

    // ── Notify tenant: payment confirmed ─────────────────────────────────────
    createNotification(
        $db,
        $_SESSION['user_id'],
        'payment',
        'Payment Confirmed',
        '₦' . number_format($verified_amount_naira) . ' ' . $payment_type . ' rent recorded. Receipt: ' . $receipt_no,
        'tenant_payments.php'
    );

    header('Location: ../tenant_payments.php?success=payment_received');
    exit;

} catch (Exception $e) {
    error_log('Paystack Verify Error: ' . $e->getMessage());
    logActivity('Payment Error', $e->getMessage());
    header('Location: ../tenant_payments.php?error=system_error');
    exit;
}