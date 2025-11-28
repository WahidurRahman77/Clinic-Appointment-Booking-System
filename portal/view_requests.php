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
        $message = "<div class='msg-alert msg-success'><i class='fas fa-check-circle'></i> Appointment confirmed and slot booked.</div>";
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $message = "<div class='msg-alert msg-error'><i class='fas fa-times-circle'></i> Error confirming appointment.</div>";
    }
}

// 3.5. Handle Appointment Cancellation by Doctor
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    $slot_id = $_POST['slot_id'];

    // Use a transaction
    $conn->begin_transaction();
    try {
        // 1. Update the appointment status to 'canceled_by_doctor'
        $stmt_update = $conn->prepare("UPDATE appointments SET request_status = 'canceled_by_doctor' WHERE id = ? AND request_status = 'confirmed'");
        $stmt_update->bind_param("i", $appointment_id);
        $stmt_update->execute();

        // 2. Set the slot back to 'available'
        $stmt_free_slot = $conn->prepare("UPDATE appointment_slots SET status = 'available' WHERE id = ?");
        $stmt_free_slot->bind_param("i", $slot_id);
        $stmt_free_slot->execute();
        
        $conn->commit();
        $message = "<div class='msg-alert msg-success'><i class='fas fa-trash-alt'></i> Appointment canceled. Slot is available again.</div>";
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $message = "<div class='msg-alert msg-error'>Error canceling appointment.</div>";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments | Medicare Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #007bff; --light-bg: #f4f7f6; --white: #fff; --text: #2c3e50; 
            --success: #28a745; --danger: #dc3545; --shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        * { box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: var(--light-bg); margin: 0; min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Navbar */
        .navbar { background: var(--white); padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .brand { font-size: 1.5rem; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 8px; }
        .back-link { color: var(--text); text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: 0.3s; }
        .back-link:hover { color: var(--primary); }

        /* Container */
        .container { max-width: 1100px; margin: 40px auto; padding: 0 20px; flex: 1; width: 100%; }

        .header { margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; padding-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; color: var(--text); font-size: 1.8rem; }
        
        /* Messages */
        .msg-alert { padding: 15px; border-radius: 8px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; animation: slideIn 0.5s ease; }
        .msg-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* Section Cards */
        .section-card { background: var(--white); border-radius: 12px; box-shadow: var(--shadow); padding: 0; overflow: hidden; margin-bottom: 40px; }
        .section-header { background: #f8f9fa; padding: 20px 25px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px; }
        .section-header h2 { margin: 0; font-size: 1.2rem; color: var(--text); }
        .count-badge { background: var(--primary); color: white; padding: 2px 10px; border-radius: 20px; font-size: 0.85rem; }

        /* Tables */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th { background: var(--white); color: #666; font-weight: 600; text-align: left; padding: 18px 25px; font-size: 0.9rem; text-transform: uppercase; border-bottom: 2px solid #f0f0f0; }
        td { padding: 18px 25px; border-bottom: 1px solid #f0f0f0; color: #333; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #fafafa; }

        /* Action Buttons */
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 0.9rem; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-confirm { background: #e6f9ed; color: var(--success); }
        .btn-confirm:hover { background: var(--success); color: white; }
        .btn-cancel { background: #fff5f5; color: var(--danger); }
        .btn-cancel:hover { background: var(--danger); color: white; }

        /* Empty State */
        .no-data { text-align: center; padding: 50px 20px; color: #888; }
        .no-data i { font-size: 3rem; margin-bottom: 15px; color: #ddd; display: block; }

        footer { text-align: center; padding: 25px; color: #888; border-top: 1px solid #e0e0e0; margin-top: auto; font-size: 0.9rem; background: var(--white); }

        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="brand"><i class="fas fa-heartbeat"></i> Medicare</div>
        <a href="doctor_dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </nav>

    <div class="container">
        
        <div class="header">
            <h1>Manage Appointments</h1>
        </div>

        <?php echo $message; ?>

        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-hourglass-half" style="color: #f39c12;"></i>
                <h2>Pending Requests</h2>
                <?php if ($pending_requests->num_rows > 0): ?>
                    <span class="count-badge" style="background: #f39c12;"><?php echo $pending_requests->num_rows; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="table-responsive">
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
                                <td><strong><?php echo htmlspecialchars($row['patient_name']); ?></strong></td>
                                <td><?php echo date("D, M j, Y", strtotime($row['appointment_date'])); ?></td>
                                <td><?php echo date("g:i A", strtotime($row['start_time'])); ?></td>
                                <td>
                                    <form action="view_requests.php" method="POST">
                                        <input type="hidden" name="appointment_id" value="<?php echo $row['appointment_id']; ?>">
                                        <input type="hidden" name="slot_id" value="<?php echo $row['slot_id']; ?>">
                                        <button type="submit" name="confirm_appointment" class="btn btn-confirm">
                                            <i class="fas fa-check"></i> Confirm
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <i class="far fa-folder-open"></i>
                        <p>You have no pending appointment requests.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-calendar-check" style="color: var(--success);"></i>
                <h2>Confirmed Appointments</h2>
            </div>
            
            <div class="table-responsive">
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
                                <td><strong><?php echo htmlspecialchars($row['patient_name']); ?></strong></td>
                                <td><?php echo date("D, M j, Y", strtotime($row['appointment_date'])); ?></td>
                                <td><?php echo date("g:i A", strtotime($row['start_time'])); ?></td>
                                <td>
                                    <form action="view_requests.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this appointment? The slot will become available again.');">
                                        <input type="hidden" name="appointment_id" value="<?php echo $row['appointment_id']; ?>">
                                        <input type="hidden" name="slot_id" value="<?php echo $row['slot_id']; ?>">
                                        <button type="submit" name="cancel_appointment" class="btn btn-cancel">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <i class="far fa-calendar"></i>
                        <p>You have no upcoming confirmed appointments.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <footer>&copy; <?php echo date("Y"); ?> Medicare Portal. All Rights Reserved.</footer>

</body>
</html>
