<?php
// Always start the session
session_start();

// 1. Check if the user is a logged-in doctor
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'doctor') {
    header("Location: login.php");
    exit;
}

$doctor_id = $_SESSION["user_id"];
$fullname = $_SESSION["fullname"];
$message = '';

// 2. Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "portal_db";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3. Handle Appointment Confirmation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    $slot_id = $_POST['slot_id'];

    // Use a transaction
    $conn->begin_transaction();
    try {
        // Update the appointment status to 'confirmed'
        $stmt_confirm = $conn->prepare("UPDATE appointments SET request_status = 'confirmed' WHERE id = ?");
        $stmt_confirm->bind_param("i", $appointment_id);
        $stmt_confirm->execute();

        // Update the slot status to 'booked'
        $stmt_book_slot = $conn->prepare("UPDATE appointment_slots SET status = 'booked' WHERE id = ?");
        $stmt_book_slot->bind_param("i", $slot_id);
        $stmt_book_slot->execute();
        
        $conn->commit();
        $message = "<div class='msg-success'>Appointment confirmed and slot is now booked.</div>";
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $message = "<div class='msg-error'>Error confirming appointment.</div>";
    }
}

// 3.5. Handle Appointment Cancellation by Doctor (UPDATED LOGIC)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    $slot_id = $_POST['slot_id'];

    // Use a transaction
    $conn->begin_transaction();
    try {
        // 1. Update the appointment status to 'canceled_by_doctor' INSTEAD of deleting
        $stmt_update = $conn->prepare("UPDATE appointments SET request_status = 'canceled_by_doctor' WHERE id = ? AND request_status = 'confirmed'");
        $stmt_update->bind_param("i", $appointment_id);
        $stmt_update->execute();

        // 2. Set the slot back to 'available'
        $stmt_free_slot = $conn->prepare("UPDATE appointment_slots SET status = 'available' WHERE id = ?");
        $stmt_free_slot->bind_param("i", $slot_id);
        $stmt_free_slot->execute();
        
        $conn->commit();
        $message = "<div class='msg-success'>Appointment canceled successfully. The slot is now available again.</div>";
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $message = "<div class='msg-error'>Error canceling appointment.</div>";
    }
}


// 4. Fetch Pending Requests
$sql_pending = "
    SELECT 
        a.id AS appointment_id,
        a.slot_id,
        p.fullname AS patient_name,
        s.appointment_date,
        s.start_time
    FROM appointments a
    JOIN users p ON a.patient_id = p.id
    JOIN appointment_slots s ON a.slot_id = s.id
    WHERE s.doctor_id = ? AND a.request_status = 'pending' AND s.status = 'available'
    ORDER BY s.appointment_date, s.start_time";
$stmt_pending = $conn->prepare($sql_pending);
$stmt_pending->bind_param("i", $doctor_id);
$stmt_pending->execute();
$pending_requests = $stmt_pending->get_result();

// 5. Fetch Confirmed Appointments
$sql_confirmed = "
    SELECT 
        a.id AS appointment_id,
        s.id AS slot_id,
        p.fullname AS patient_name,
        s.appointment_date,
        s.start_time
    FROM appointments a
    JOIN users p ON a.patient_id = p.id
    JOIN appointment_slots s ON a.slot_id = s.id
    WHERE s.doctor_id = ? AND a.request_status = 'confirmed'
    ORDER BY s.appointment_date, s.start_time";
$stmt_confirmed = $conn->prepare($sql_confirmed);
$stmt_confirmed->bind_param("i", $doctor_id);
$stmt_confirmed->execute();
$confirmed_appointments = $stmt_confirmed->get_result();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Appointments</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f7f6; margin: 0; padding: 40px; }
        .container { max-width: 1100px; margin: auto; background: #fff; padding: 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        h1, h2 { color: #2c3e50; font-weight: 600; text-align: center; border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 30px; }
        .nav-links { text-align: center; margin-bottom: 30px; }
        .nav-links a { text-decoration: none; background: #3498db; color: white; padding: 10px 20px; border-radius: 5px; margin: 0 10px; font-weight: 500; }
        .nav-links .logout { background-color: #e74c3c; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #ecf0f1; color: #34495e; }
        
        .confirm-btn { background-color: #2ecc71; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: 500; }
        .confirm-btn:hover { background-color: #27ae60; }
        
        .cancel-btn { background-color: #e74c3c; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: 500; }
        .cancel-btn:hover { background-color: #c0392b; }

        p.no-data { text-align: center; color: #7f8c8d; font-size: 1.1em; padding: 20px; }
        .msg-success { background: #e0ffe0; border: 1px solid green; padding: 15px; text-align: center; margin-bottom: 20px; border-radius: 8px; }
        .msg-error { background: #ffe0e0; border: 1px solid red; padding: 15px; text-align: center; margin-bottom: 20px; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Appointment Management</h1>
        <div class="nav-links">
            <a href="doctor_dashboard.php">Back to Dashboard</a>
            <a href="logout.php" class="logout">Logout</a>
        </div>
        
        <?php echo $message; ?>

        <h2>Pending Requests</h2>
        <?php if ($pending_requests->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Patient Name</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $pending_requests->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                        <td><?php echo date("D, M j, Y", strtotime($row['appointment_date'])); ?></td>
                        <td><?php echo date("g:i A", strtotime($row['start_time'])); ?></td>
                        <td>
                            <form action="view_requests.php" method="POST" style="display:inline;">
                                <input type="hidden" name="appointment_id" value="<?php echo $row['appointment_id']; ?>">
                                <input type="hidden" name="slot_id" value="<?php echo $row['slot_id']; ?>">
                                <button type="submit" name="confirm_appointment" class="confirm-btn">Confirm</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data">You have no pending appointment requests.</p>
        <?php endif; ?>

        <h2 style="margin-top: 50px;">Confirmed Appointments</h2>
        <?php if ($confirmed_appointments->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Patient Name</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $confirmed_appointments->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                        <td><?php echo date("D, M j, Y", strtotime($row['appointment_date'])); ?></td>
                        <td><?php echo date("g:i A", strtotime($row['start_time'])); ?></td>
                        <td>
                            <form action="view_requests.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this appointment? The slot will become available again.');" style="display:inline;">
                                <input type="hidden" name="appointment_id" value="<?php echo $row['appointment_id']; ?>">
                                <input type="hidden" name="slot_id" value="<?php echo $row['slot_id']; ?>">
                                <button type="submit" name="cancel_appointment" class="cancel-btn">Cancel</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data">You have no confirmed appointments.</p>
        <?php endif; ?>

    </div>
</body>
</html>