<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

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
    // ── 1. Verify with Paystack API (real verification) ───────────────────────
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
    $response    = curl_exec($ch);
    $curl_error  = curl_error($ch);
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

    // Optional: cross-check amount from URL param as a sanity check
    $expected_kobo = (int) ($_GET['amount'] ?? 0);
    if ($expected_kobo > 0 && abs($verified_amount_kobo - $expected_kobo) > 1) {
        // Amount mismatch — possible tampering
        logActivity('Payment Mismatch', "Ref: $reference — expected {$expected_kobo} kobo, got {$verified_amount_kobo}");
        header('Location: ../tenant_payments.php?error=amount_mismatch');
        exit;
    }

    // ── 3. Fetch tenant record ────────────────────────────────────────────────
    $stmt = $db->prepare("SELECT id FROM tenants WHERE user_id = ? AND status = 'Active'");
    $stmt->execute([$_SESSION['user_id']]);
    $tenant = $stmt->fetch();

    if (!$tenant) {
        header('Location: ../tenant_payments.php?error=tenant_not_found');
        exit;
    }

    $tenant_id   = $tenant['id'];
    $payment_type = trim($_GET['payment_type'] ?? 'Monthly');
    $allowed_types = ['Monthly', 'Quarterly', 'Yearly', 'Bill'];

    if (!in_array($payment_type, $allowed_types)) {
        $payment_type = 'Monthly';
    }

    // ── 4. Guard: prevent duplicate processing of same reference ─────────────
    $stmt = $db->prepare("SELECT COUNT(*) FROM payments WHERE receipt_no = ?");
    $stmt->execute(['PAY-' . strtoupper($reference)]);
    if ($stmt->fetchColumn() > 0) {
        // Already recorded — redirect to success silently
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

        // Confirm bill belongs to this tenant and is unpaid
        $stmt = $db->prepare("SELECT * FROM bills WHERE id = ? AND tenant_id = ? AND status = 'Unpaid'");
        $stmt->execute([$bill_id, $tenant_id]);
        $bill = $stmt->fetch();

        if (!$bill) {
            header('Location: ../tenant_payments.php?error=bill_not_found');
            exit;
        }

        // Mark bill as paid
        $stmt = $db->prepare("UPDATE bills SET status = 'Paid', paid_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$bill_id]);

        // Record in payments table for history
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
        header('Location: ../tenant_payments.php?success=payment_received');
        exit;
    }

    // ── 5b. RENT payment (Monthly / Quarterly / Yearly) ───────────────────────
    $month_label = match($payment_type) {
        'Quarterly' => 'Q' . ceil(date('n') / 3) . ' ' . date('Y'),
        'Yearly'    => 'Annual ' . date('Y'),
        default     => date('F Y'),   // e.g. "May 2026"
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
    header('Location: ../tenant_payments.php?success=payment_received');
    exit;

} catch (Exception $e) {
    error_log('Paystack Verify Error: ' . $e->getMessage());
    logActivity('Payment Error', $e->getMessage());
    header('Location: ../tenant_payments.php?error=system_error');
    exit;
}