<?php
require_once 'db.php';

$appointment_id = $_GET['appointment_id'] ?? null;

if (!$appointment_id) {
    die("Invalid appointment reference.");
}

// Fetch appointment and payment details
$stmt = $pdo->prepare("
    SELECT a.appointment_id, a.appointment_date, a.start_time, s.service_name, p.amount, p.payment_status 
    FROM appointments a
    JOIN services s ON a.service_id = s.service_id
    JOIN payments p ON a.appointment_id = p.appointment_id
    WHERE a.appointment_id = ?
");
$stmt->execute([$appointment_id]);
$booking = $stmt->fetch();

if (!$booking) {
    die("Appointment not found.");
}

// Handle payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaction_id'])) {
    $txn_id = trim($_POST['transaction_id']);

    if (!empty($txn_id)) {
        // Update payment status
        $update_pay = $pdo->prepare("UPDATE payments SET payment_status = 'paid', payment_method = 'gpay', transaction_id = ? WHERE appointment_id = ?");
        $update_pay->execute([$txn_id, $appointment_id]);

        // Update appointment status to confirmed
        $update_app = $pdo->prepare("UPDATE appointments SET status = 'confirmed' WHERE appointment_id = ?");
        $update_app->execute([$appointment_id]);

        echo "<script>alert('Payment Received! Your appointment is confirmed.'); window.location.href='my_bookings.php';</script>";
        exit;
    }
}

// Your UPI Details for GPay
$upi_id   = "yourupiid@okaxis"; // Replace with your actual GPay UPI ID
$upi_name = "Salon Management";
$amount   = $booking['amount'];
$upi_url  = "upi://pay?pa={$upi_id}&pn=" . urlencode($upi_name) . "&am={$amount}&cu=INR";
$qr_url   = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($upi_url);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pay via Google Pay - Salon Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 text-center">
                <div class="card-header bg-dark text-white p-3">
                    <h5 class="mb-0">Complete Payment via Google Pay</h5>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted mb-1">Service: <strong><?= htmlspecialchars($booking['service_name']) ?></strong></p>
                    <h3 class="text-primary mb-3">$<?= number_format($booking['amount'], 2) ?></h3>

                    <div class="my-3 p-3 bg-white border rounded d-inline-block">
                        <img src="<?= $qr_url ?>" alt="Google Pay QR Code" class="img-fluid mb-2">
                        <div class="small text-muted">Scan using <strong>Google Pay</strong> app</div>
                    </div>

                    <div class="my-2">
                        <a href="<?= $upi_url ?>" class="btn btn-outline-success btn-sm w-100 mb-3">
                            📱 Tap to Pay directly via GPay App
                        </a>
                    </div>

                    <form method="POST" class="text-start border-top pt-3">
                        <div class="mb-3">
                            <label for="transaction_id" class="form-label fw-bold">Enter GPay UTR / Transaction ID</label>
                            <input type="text" class="form-control" id="transaction_id" name="transaction_id" placeholder="e.g., 324156789012" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Confirm Payment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>