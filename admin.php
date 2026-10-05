<?php
require_once 'db.php';

// Update appointment status if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $appointment_id = $_POST['appointment_id'];
    $new_status     = $_POST['status'];

    $update_stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
    $update_stmt->execute([$new_status, $appointment_id]);

    header("Location: admin.php");
    exit;
}

// Fetch all appointments with client, stylist, service, and payment details
$query = "
    SELECT 
        a.appointment_id,
        a.appointment_date,
        a.start_time,
        a.end_time,
        a.status AS booking_status,
        c.full_name AS client_name,
        s.full_name AS stylist_name,
        srv.service_name,
        p.amount,
        p.payment_status
    FROM appointments a
    JOIN users c ON a.client_id = c.user_id
    JOIN users s ON a.stylist_id = s.user_id
    JOIN services srv ON a.service_id = srv.service_id
    LEFT JOIN payments p ON a.appointment_id = p.appointment_id
    ORDER BY a.appointment_date DESC, a.start_time DESC
";

$appointments = $pdo->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Salon Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Salon Admin Dashboard</span>
        <a href="index.php" class="btn btn-outline-light btn-sm">Go to Booking Page</a>
    </div>
</nav>

<div class="container">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white p-3">
            <h5 class="mb-0">All Scheduled Appointments</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Service</th>
                            <th>Stylist</th>
                            <th>Date & Time</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($appointments)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No appointments found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($appointments as $app): ?>
                                <tr>
                                    <td>#<?= $app['appointment_id'] ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($app['client_name']) ?></td>
                                    <td><?= htmlspecialchars($app['service_name']) ?></td>
                                    <td><?= htmlspecialchars($app['stylist_name']) ?></td>
                                    <td>
                                        <?= date('M d, Y', strtotime($app['appointment_date'])) ?><br>
                                        <small class="text-muted"><?= date('h:i A', strtotime($app['start_time'])) ?> - <?= date('h:i A', strtotime($app['end_time'])) ?></small>
                                    </td>
                                    <td>$<?= number_format($app['amount'], 2) ?></td>
                                    <td>
                                        <?php if ($app['booking_status'] === 'confirmed'): ?>
                                            <span class="badge bg-success">Confirmed</span>
                                        <?php elseif ($app['booking_status'] === 'pending'): ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php elseif ($app['booking_status'] === 'completed'): ?>
                                            <span class="badge bg-primary">Completed</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Cancelled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-flex gap-1">
                                            <input type="hidden" name="appointment_id" value="<?= $app['appointment_id'] ?>">
                                            <input type="hidden" name="action" value="update_status">
                                            
                                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                <option value="pending" <?= $app['booking_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="confirmed" <?= $app['booking_status'] === 'confirmed' ? 'selected' : '' ?>>Confirm</option>
                                                <option value="completed" <?= $app['booking_status'] === 'completed' ? 'selected' : '' ?>>Complete</option>
                                                <option value="cancelled" <?= $app['booking_status'] === 'cancelled' ? 'selected' : '' ?>>Cancel</option>
                                            </select>
                                        </form>
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