<?php
require_once 'db.php';

$page = $_GET['page'] ?? 'home';
$client_id = 4; // Test Client: John Doe

// ==========================================
// 1. POST ACTION HANDLERS
// ==========================================

// Handle New Booking Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_booking') {
    $service_id       = $_POST['service_id'] ?? null;
    $stylist_id       = $_POST['stylist_id'] ?? null;
    $appointment_date = $_POST['appointment_date'] ?? null;
    $start_time       = $_POST['start_time'] ?? null;

    if ($service_id && $stylist_id && $appointment_date && $start_time) {
        $stmt = $pdo->prepare("SELECT duration_minutes, price FROM services WHERE service_id = ?");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch();

        $end_timestamp = strtotime("$appointment_date $start_time") + ($service['duration_minutes'] * 60);
        $end_time = date('H:i:s', $end_timestamp);

        $sql = "INSERT INTO appointments (client_id, stylist_id, service_id, appointment_date, start_time, end_time, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$client_id, $stylist_id, $service_id, $appointment_date, $start_time, $end_time])) {
            $appointment_id = $pdo->lastInsertId();
            $pay_stmt = $pdo->prepare("INSERT INTO payments (appointment_id, amount, payment_status) VALUES (?, ?, 'unpaid')");
            $pay_stmt->execute([$appointment_id, $service['price']]);

            header("Location: index.php?page=pay&appointment_id=" . $appointment_id);
            exit;
        }
    }
}

// Handle Payment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_pay') {
    $appointment_id = $_POST['appointment_id'] ?? null;
    $txn_id = trim($_POST['transaction_id'] ?? '');

    if ($appointment_id && !empty($txn_id)) {
        $update_pay = $pdo->prepare("UPDATE payments SET payment_status = 'paid', payment_method = 'gpay', transaction_id = ? WHERE appointment_id = ?");
        $update_pay->execute([$txn_id, $appointment_id]);

        $update_app = $pdo->prepare("UPDATE appointments SET status = 'confirmed' WHERE appointment_id = ?");
        $update_app->execute([$appointment_id]);

        echo "<script>alert('Payment Received! Appointment confirmed.'); window.location.href='index.php?page=my_bookings';</script>";
        exit;
    }
}

// Handle Appointment Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
    $cancel_id = $_POST['cancel_id'];
    $cancel_stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ? AND client_id = ?");
    $cancel_stmt->execute([$cancel_id, $client_id]);

    header("Location: index.php?page=my_bookings");
    exit;
}

// Handle Admin Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_update') {
    $appointment_id = $_POST['appointment_id'];
    $new_status     = $_POST['status'];

    $update_stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
    $update_stmt->execute([$new_status, $appointment_id]);

    header("Location: index.php?page=admin");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salon Appointment & Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- Global Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php?page=home">Salon Management</a>
        <div class="navbar-nav">
            <a class="nav-link <?php echo $page === 'home' ? 'active' : ''; ?>" href="index.php?page=home">Book Service</a>
            <a class="nav-link <?php echo $page === 'my_bookings' ? 'active' : ''; ?>" href="index.php?page=my_bookings">My Bookings</a>
            <a class="nav-link <?php echo $page === 'admin' ? 'active' : ''; ?>" href="index.php?page=admin">Admin Dashboard</a>
        </div>
    </div>
</nav>

<div class="container">

<?php
// ==========================================
// 2. PAGE ROUTER
// ==========================================

switch ($page):

    case 'home':
        $services = $pdo->query("SELECT * FROM services WHERE status = 'active'")->fetchAll();
        $stylists = $pdo->query("SELECT user_id, full_name FROM users WHERE role = 'stylist'")->fetchAll();
        ?>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-dark text-white p-3"><h4 class="mb-0">Book an Appointment</h4></div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <input type="hidden" name="action" value="create_booking">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Service</label>
                                <select class="form-select" id="service_id" name="service_id" required>
                                    <option value="" selected disabled>Choose a service...</option>
                                    <?php foreach ($services as $s): ?>
                                        <option value="<?php echo $s['service_id']; ?>"><?php echo htmlspecialchars($s['service_name']); ?> (<?php echo $s['duration_minutes']; ?> min) - $<?php echo $s['price']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Stylist</label>
                                <select class="form-select" id="stylist_id" name="stylist_id" required disabled>
                                    <option value="" selected disabled>Choose a stylist...</option>
                                    <?php foreach ($stylists as $st): ?>
                                        <option value="<?php echo $st['user_id']; ?>"><?php echo htmlspecialchars($st['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Date</label>
                                <input type="date" class="form-control" id="appointment_date" name="appointment_date" required min="<?php echo date('Y-m-d'); ?>" disabled>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold d-block">Available Time Slots</label>
                                <div id="slotContainer" class="d-flex flex-wrap gap-2">
                                    <span class="text-muted small">Select options above to load slots.</span>
                                </div>
                                <input type="hidden" id="selected_time" name="start_time" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100" id="submitBtn" disabled>Confirm Booking</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', () => {
            const serviceSelect = document.getElementById('service_id');
            const stylistSelect = document.getElementById('stylist_id');
            const dateInput     = document.getElementById('appointment_date');
            const slotContainer = document.getElementById('slotContainer');
            const selectedTime  = document.getElementById('selected_time');
            const submitBtn     = document.getElementById('submitBtn');

            serviceSelect.addEventListener('change', () => { stylistSelect.disabled = false; fetchSlots(); });
            stylistSelect.addEventListener('change', () => { dateInput.disabled = false; fetchSlots(); });
            dateInput.addEventListener('change', fetchSlots);

            function fetchSlots() {
                const s = serviceSelect.value, st = stylistSelect.value, d = dateInput.value;
                if (!s || !st || !d) return;
                slotContainer.innerHTML = '<span class="text-info">Loading slots...</span>';

                fetch(`get_slots.php?service_id=${s}&stylist_id=${st}&date=${d}`)
                    .then(res => res.json())
                    .then(data => {
                        slotContainer.innerHTML = '';
                        if (!data.slots || data.slots.length === 0) {
                            slotContainer.innerHTML = '<span class="text-danger small">No slots available.</span>';
                            submitBtn.disabled = true;
                            return;
                        }
                        data.slots.forEach(time => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'btn btn-outline-dark btn-sm slot-btn';
                            btn.innerText = time;
                            btn.onclick = () => {
                                document.querySelectorAll('.slot-btn').forEach(b => b.classList.replace('btn-dark', 'btn-outline-dark'));
                                btn.classList.replace('btn-outline-dark', 'btn-dark');
                                selectedTime.value = time;
                                submitBtn.disabled = false;
                            };
                            slotContainer.appendChild(btn);
                        });
                    });
            }
        });
        </script>
        <?php
        break;

    case 'pay':
        $appointment_id =$_GET['appointment_id'] ?? null;
        $stmt =$pdo->prepare("SELECT a.appointment_id, s.service_name, p.amount FROM appointments a JOIN services s ON a.service_id = s.service_id JOIN payments p ON a.appointment_id = p.appointment_id WHERE a.appointment_id = ?");
        $stmt->execute([$appointment_id]);
        $booking =$stmt->fetch();

        if (!$booking) { echo "<div class='alert alert-danger'>Invalid Request</div>"; break; }

        $upi_id   = "yourupiid@okaxis";
        $upi_url  = "upi://pay?pa={$upi_id}&pn=SalonManagement&am={$booking['amount']}&cu=INR";
        $qr_url   = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($upi_url);
        ?>
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm border-0 text-center">
                    <div class="card-header bg-dark text-white p-3"><h5 class="mb-0">Pay via Google Pay</h5></div>
                    <div class="card-body p-4">
                        <p class="text-muted mb-1">Service: <strong><?php echo htmlspecialchars($booking['service_name']); ?></strong></p>
                        <h3 class="text-primary mb-3">$<?php echo number_format($booking['amount'], 2); ?></h3>

                        <div class="my-3 p-3 bg-white border rounded d-inline-block">
                            <img src="<?php echo $qr_url; ?>" alt="GPay QR" class="img-fluid mb-2">
                            <div class="small text-muted">Scan using <strong>Google Pay</strong> app</div>
                        </div>

                        <form method="POST" class="text-start border-top pt-3">
                            <input type="hidden" name="action" value="process_pay">
                            <input type="hidden" name="appointment_id" value="<?php echo $booking['appointment_id']; ?>">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Enter GPay UTR / Transaction ID</label>
                                <input type="text" class="form-control" name="transaction_id" placeholder="e.g., 324156789012" required>
                            </div>
                            <button type="submit" class="btn btn-success w-100">Confirm Payment</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
        break;

    case 'my_bookings':
        $stmt =$pdo->prepare("SELECT a.appointment_id, a.appointment_date, a.start_time, a.status AS booking_status, s.service_name, st.full_name AS stylist_name, p.amount, p.payment_status FROM appointments a JOIN services s ON a.service_id = s.service_id JOIN users st ON a.stylist_id = st.user_id LEFT JOIN payments p ON a.appointment_id = p.appointment_id WHERE a.client_id = ? ORDER BY a.appointment_date DESC");
        $stmt->execute([$client_id]);
        $bookings =$stmt->fetchAll();
        ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white p-3"><h5 class="mb-0">Your Appointments</h5></div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr><th>ID</th><th>Service</th><th>Stylist</th><th>Date</th><th>Amount</th><th>Payment</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as$b): ?>
                            <tr>
                                <td>#<?php echo $b['appointment_id']; ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($b['service_name']); ?></td>
                                <td><?php echo htmlspecialchars($b['stylist_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($b['appointment_date'])); ?></td>
                                <td>$<?php echo number_format($b['amount'], 2); ?></td>
                                <td>
                                    <?php if ($b['payment_status'] === 'paid'): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php else: ?>
                                        <a href="index.php?page=pay&appointment_id=<?php echo $b['appointment_id']; ?>" class="btn btn-warning btn-sm">Pay via GPay</a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $statusClass = 'warning';
                                        if ($b['booking_status'] === 'confirmed') {$statusClass = 'success';
                                        } else if ($b['booking_status'] === 'cancelled') {$statusClass = 'danger';
                                        }
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>"><?php echo ucfirst($b['booking_status']); ?></span>
                                </td>
                                <td>
                                    <?php if ($b['booking_status'] !== 'cancelled'): ?>
                                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('Cancel appointment?');">
                                            <input type="hidden" name="action" value="cancel_booking">
                                            <input type="hidden" name="cancel_id" value="<?php echo $b['appointment_id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Cancel</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        break;

    case 'admin':
        $query = "SELECT a.appointment_id, a.appointment_date, a.start_time, a.status AS booking_status, c.full_name AS client_name, s.full_name AS stylist_name, srv.service_name, p.amount FROM appointments a JOIN users c ON a.client_id = c.user_id JOIN users s ON a.stylist_id = s.user_id JOIN services srv ON a.service_id = srv.service_id LEFT JOIN payments p ON a.appointment_id = p.appointment_id ORDER BY a.appointment_date DESC";
        $appointments = $pdo->query($query)->fetchAll();
        ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white p-3"><h5 class="mb-0">All Salon Appointments (Admin)</h5></div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr><th>ID</th><th>Client</th><th>Service</th><th>Stylist</th><th>Date</th><th>Amount</th><th>Status</th><th>Update Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as$app): ?>
                            <tr>
                                <td>#<?php echo $app['appointment_id']; ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($app['client_name']); ?></td>
                                <td><?php echo htmlspecialchars($app['service_name']); ?></td>
                                <td><?php echo htmlspecialchars($app['stylist_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($app['appointment_date'])); ?></td>
                                <td>$<?php echo number_format($app['amount'], 2); ?></td>
                                <td>
                                    <?php 
                                        $adminStatusClass = 'warning';
                                        if ($app['booking_status'] === 'confirmed') {$adminStatusClass = 'success';
                                        } else if ($app['booking_status'] === 'cancelled') {$adminStatusClass = 'danger';
                                        }
                                    ?>
                                    <span class="badge bg-<?php echo $adminStatusClass; ?>"><?php echo ucfirst($app['booking_status']); ?></span>
                                </td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="admin_update">
                                        <input type="hidden" name="appointment_id" value="<?php echo $app['appointment_id']; ?>">
                                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="pending" <?php echo $app['booking_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="confirmed" <?php echo $app['booking_status'] === 'confirmed' ? 'selected' : ''; ?>>Confirm</option>
                                            <option value="completed" <?php echo $app['booking_status'] === 'completed' ? 'selected' : ''; ?>>Complete</option>
                                            <option value="cancelled" <?php echo $app['booking_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancel</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        break;

endswitch;
?>

</div>
</body>
</html>