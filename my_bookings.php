<?php
require_once 'db.php';

$client_id = 4; // Test Client: John Doe

// Handle Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $cancel_id = $_POST['cancel_id'];

    $cancel_stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ? AND client_id = ?");
    $cancel_stmt->execute([$cancel_id, $client_id]);

    echo "<script>alert('Appointment cancelled successfully.'); window.location.href='my_bookings.php';</script>";
    exit;
}

// Fetch Client Appointments
$stmt = $pdo->prepare("
    SELECT 
        a.appointment_id,
        a.appointment_date,
        a.start_time,
        a.end_time,
        a.status AS booking_status,
        s.service_name,
        st.full_name AS stylist_name,
        p.amount,
        p.payment_status
    FROM appointments a
    JOIN services s ON a.service_id = s.service_id
    JOIN users st ON a.stylist_id = st.user_id
    LEFT JOIN payments p ON a.appointment_id = p.appointment_id
    WHERE a.client_id = ?
    ORDER BY a.appointment_date DESC, a.start_time DESC
");
$stmt->execute([$client_id]);
$bookings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Bookings - Salon Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1">My Salon Appointments</span>
        <a href="index.php" class="btn btn-outline-light btn-sm">Book New Appointment</a>
    </div>
</nav>

<div class="container">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white p-3">
            <h5 class="mb-0">Your Upcoming & Past Appointments</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Service</th>
                            <th>Stylist</th>
                            <th>Date & Time</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No bookings found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $b): ?>
                                <tr>
                                    <td>#<?= $b['appointment_id'] ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($b['service_name']) ?></td>
                                    <td><?= htmlspecialchars($b['stylist_name']) ?></td>
                                    <td>
                                        <?= date('M d, Y', strtotime($b['appointment_date'])) ?><br>
                                        <small class="text-muted"><?= date('h:i A', strtotime($b['start_time'])) ?></small>
                                    </td>
                                    <td>$<?= number_format($b['amount'], 2) ?></td>
                                    <td>
                                        <?php if ($b['payment_status'] === 'paid'): ?>
                                            <span class="badge bg-success">Paid (GPay)</span>
                                        <?php else: ?>
                                            <a href="pay.php?appointment_id=<?= $b['appointment_id'] ?>" class="btn btn-warning btn-sm">Pay via GPay</a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($b['booking_status'] === 'confirmed'): ?>
                                            <span class="badge bg-success">Confirmed</span>
                                        <?php elseif ($b['booking_status'] === 'pending'): ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php elseif ($b['booking_status'] === 'completed'): ?>
                                            <span class="badge bg-primary">Completed</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Cancelled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($b['booking_status'] !== 'cancelled' && $b['booking_status'] !== 'completed'): ?>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                                <input type="hidden" name="cancel_id" value="<?= $b['appointment_id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Cancel</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>