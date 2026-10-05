<?php
header('Content-Type: application/json');
require_once 'db.php';

$stylist_id = $_GET['stylist_id'] ?? null;
$service_id = $_GET['service_id'] ?? null;
$date       = $_GET['date'] ?? null;

if (!$stylist_id || !$service_id || !$date) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$day_of_week = date('l', strtotime($date));

// Fetch Stylist Working Hours
$stmt = $pdo->prepare("SELECT start_time, end_time FROM stylist_schedules WHERE stylist_id = ? AND day_of_week = ?");
$stmt->execute([$stylist_id, $day_of_week]);
$schedule = $stmt->fetch();

if (!$schedule) {
    echo json_encode(['slots' => []]);
    exit;
}

// Fetch Service Duration
$stmt = $pdo->prepare("SELECT duration_minutes FROM services WHERE service_id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();
$duration = $service['duration_minutes'];

// Fetch Existing Appointments
$stmt = $pdo->prepare("SELECT start_time, end_time FROM appointments WHERE stylist_id = ? AND appointment_date = ? AND status != 'cancelled'");
$stmt->execute([$stylist_id, $date]);
$existing_bookings = $stmt->fetchAll();

// Calculate Available Time Slots
$slots = [];
$current = strtotime($schedule['start_time']);
$end     = strtotime($schedule['end_time']);

while ($current + ($duration * 60) <= $end) {
    $slot_start = date('H:i:s', $current);
    $slot_end   = date('H:i:s', $current + ($duration * 60));
    
    $is_conflict = false;
    foreach ($existing_bookings as $booking) {
        if ($slot_start < $booking['end_time'] && $slot_end > $booking['start_time']) {
            $is_conflict = true;
            break;
        }
    }
    
    if (!$is_conflict) {
        $slots[] = date('H:i', $current);
    }
    
    $current += $duration * 60;
}

echo json_encode(['slots' => $slots]);
?>