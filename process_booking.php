<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id        = 4; // Default client ID (John Doe)
    $service_id       = $_POST['service_id'] ?? null;
    $stylist_id       = $_POST['stylist_id'] ?? null;
    $appointment_date = $_POST['appointment_date'] ?? null;
    $start_time       = $_POST['start_time'] ?? null;

    if (!$service_id || !$stylist_id || !$appointment_date || !$start_time) {
        die("Please fill in all required fields.");
    }

    // Fetch service info
    $stmt = $pdo->prepare("SELECT duration_minutes, price FROM services WHERE service_id = ?");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch();

    if (!$service) {
        die("Invalid service selected.");
    }

    // Calculate end time
    $end_timestamp = strtotime("$appointment_date $start_time") + ($service['duration_minutes'] * 60);
    $end_time = date('H:i:s', $end_timestamp);

    // Save appointment to DB
    $sql = "INSERT INTO appointments (client_id, stylist_id, service_id, appointment_date, start_time, end_time, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending')";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute([$client_id, $stylist_id, $service_id, $appointment_date, $start_time, $end_time])) {
        $appointment_id = $pdo->lastInsertId();

        // Save unpaid payment row
        $pay_stmt = $pdo->prepare("INSERT INTO payments (appointment_id, amount, payment_status) VALUES (?, ?, 'unpaid')");
        $pay_stmt->execute([$appointment_id, $service['price']]);

        echo "<script>alert('Booking successful!'); window.location.href='index.php';</script>";
    } else {
        echo "Failed to process booking.";
    }
}
?>