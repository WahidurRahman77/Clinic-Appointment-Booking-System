<?php
// Always start the session
session_start();

// 1. Check if the user is a logged-in doctor
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'doctor') {
    header("Location: login.php");
    exit;
}

$doctor_id = $_SESSION["user_id"];
$message = '';

// 2. Database Connection
$conn = new mysqli("localhost", "root", "", "portal_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3. Handle POST Requests for Deleting
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 3.1. Handle Deleting a SINGLE slot
    if (isset($_POST['delete_slot'])) {
        $slot_id = $_POST['slot_id'];
        
        $conn->begin_transaction();
        try {
            // 1. Mark any appointments in this slot as 'canceled_by_doctor'
            // This will notify the patient.
            $stmt_update_apps = $conn->prepare("UPDATE appointments SET request_status = 'canceled_by_doctor' WHERE slot_id = ?");
            $stmt_update_apps->bind_param("i", $slot_id);
            $stmt_update_apps->execute();
            
            // 2. Delete the slot itself (only if it belongs to this doctor)
            $stmt_del_slot = $conn->prepare("DELETE FROM appointment_slots WHERE id = ? AND doctor_id = ?");
            $stmt_del_slot->bind_param("ii", $slot_id, $doctor_id);
            $stmt_del_slot->execute();
            
            $conn->commit();
            $message = "<div class='msg-success'>Slot successfully deleted. Any patient in that slot has been notified of the cancellation.</div>";
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $message = "<div class='msg-error'>Error deleting slot.</div>";
        }
    }

    // 3.2. Handle Deleting ALL slots for a specific date
    if (isset($_POST['delete_date'])) {
        $date_to_delete = $_POST['date_to_delete'];

        $conn->begin_transaction();
        try {
            // 1. Find all slots for this doctor on this date
            $sql_find = "SELECT id FROM appointment_slots WHERE doctor_id = ? AND appointment_date = ?";
            $stmt_find = $conn->prepare($sql_find);
            $stmt_find->bind_param("is", $doctor_id, $date_to_delete);
            $stmt_find->execute();
            $slots_result = $stmt_find->get_result();

            if ($slots_result->num_rows > 0) {
                
                $slot_ids = [];
                while ($row = $slots_result->fetch_assoc()) {
                    $slot_ids[] = $row['id'];
                }
                
                // Create placeholders for the IN clause (e.g., ?, ?, ?)
                $placeholders = implode(',', array_fill(0, count($slot_ids), '?'));
                $types = str_repeat('i', count($slot_ids)); // 'iii...'
                
                // 2. Update all appointments in these slots
                $stmt_update_apps = $conn->prepare("UPDATE appointments SET request_status = 'canceled_by_doctor' WHERE slot_id IN ($placeholders)");
                $stmt_update_apps->bind_param($types, ...$slot_ids);
                $stmt_update_apps->execute();

                // 3. Delete all slots for this date
                $stmt_del_slots = $conn->prepare("DELETE FROM appointment_slots WHERE doctor_id = ? AND appointment_date = ?");
                $stmt_del_slots->bind_param("is", $doctor_id, $date_to_delete);
                $stmt_del_slots->execute();
                
                $conn->commit();
                $message = "<div class='msg-success'>Successfully deleted all slots for $date_to_delete. All affected patients have been notified.</div>";
            } else {
                $conn->rollback(); // No slots found
                $message = "<div class='msg-error'>No slots found for $date_to_delete.</div>";
            }
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $message = "<div class='msg-error'>Error deleting slots: " . $exception->getMessage() . "</div>";
        }
    }
}

// 4. Fetch all FUTURE slots for this doctor
$sql = "
    SELECT id, appointment_date, start_time, status 
    FROM appointment_slots 
    WHERE doctor_id = ? AND appointment_date >= CURDATE()
    ORDER BY appointment_date, start_time
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$slots_result = $stmt->get_result();

// Group slots by date
$grouped_slots = [];
while ($row = $slots_result->fetch_assoc()) {
    $date = $row['appointment_date'];
    if (!isset($grouped_slots[$date])) {
        $grouped_slots[$date] = [];
    }
    $grouped_slots[$date][] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage My Schedule</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f7f6; margin: 0; padding: 40px; }
        .container { max-width: 900px; margin: auto; background: #fff; padding: 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; font-weight: 600; text-align: center; border-bottom: 2px solid #e74c3c; padding-bottom: 10px; margin-bottom: 30px; }
        .nav-links { text-align: center; margin-bottom: 30px; }
        .nav-links a { text-decoration: none; background: #3498db; color: white; padding: 10px 20px; border-radius: 5px; margin: 0 10px; font-weight: 500; }
        .nav-links .logout { background-color: #e74c3c; }
        
        .date-group { margin-bottom: 40px; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
        .date-header { background-color: #ecf0f1; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .date-header h2 { margin: 0; color: #34495e; font-size: 1.4em; border: none; padding: 0; }
        .delete-btn { background-color: #e74c3c; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: 500; text-decoration: none; font-size: 0.9em; }
        .delete-btn:hover { background-color: #c0392b; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px 20px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f9f9f9; color: #555; }
        tr:last-child td { border-bottom: none; }

        .status { padding: 4px 10px; border-radius: 20px; font-size: 0.85em; font-weight: 500; color: white; }
        .status-available { background-color: #2ecc71; }
        .status-booked { background-color: #f39c12; }
        
        p.no-data { text-align: center; color: #7f8c8d; font-size: 1.1em; padding: 20px; }
        .msg-success { background: #e0ffe0; border: 1px solid green; padding: 15px; text-align: center; margin-bottom: 20px; border-radius: 8px; }
        .msg-error { background: #ffe0e0; border: 1px solid red; padding: 15px; text-align: center; margin-bottom: 20px; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manage My Schedule</h1>
        <div class="nav-links">
            <a href="doctor_dashboard.php">Back to Dashboard</a>
            <a href="logout.php" class="logout">Logout</a>
        </div>
        
        <?php echo $message; ?>

        <?php if (empty($grouped_slots)): ?>
            <p class="no-data">You have no future appointment slots created.</p>
        <?php else: ?>
            <?php foreach ($grouped_slots as $date => $slots): ?>
                <div class="date-group">
                    <div class="date-header">
                        <h2><?php echo date("l, F j, Y", strtotime($date)); ?></h2>
                        <form action="manage_slots.php" method="POST" onsubmit="return confirm('WARNING:\nAre you sure you want to delete ALL slots for <?php echo $date; ?>? This will cancel any booked appointments.');" style="display:inline;">
                            <input type="hidden" name="date_to_delete" value="<?php echo $date; ?>">
                            <button type="submit" name="delete_date" class="delete-btn">Delete All for this Date</button>
                        </form>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($slots as $slot): ?>
                                <tr>
                                    <td><?php echo date("g:i A", strtotime($slot['start_time'])); ?></td>
                                    <td>
                                        <span class="status status-<?php echo $slot['status']; ?>">
                                            <?php echo htmlspecialchars($slot['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form action="manage_slots.php" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this slot? This will cancel any booked appointment.');" style="display:inline;">
                                            <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                                            <button type="submit" name="delete_slot" class="delete-btn">Delete Slot</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</body>
</html>