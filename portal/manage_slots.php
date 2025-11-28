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
            $stmt_update_apps = $conn->prepare("UPDATE appointments SET request_status = 'canceled_by_doctor' WHERE slot_id = ?");
            $stmt_update_apps->bind_param("i", $slot_id);
            $stmt_update_apps->execute();
            
            // 2. Delete the slot itself
            $stmt_del_slot = $conn->prepare("DELETE FROM appointment_slots WHERE id = ? AND doctor_id = ?");
            $stmt_del_slot->bind_param("ii", $slot_id, $doctor_id);
            $stmt_del_slot->execute();
            
            $conn->commit();
            $message = "<div class='msg-alert msg-success'><i class='fas fa-trash-alt'></i> Slot successfully deleted. Patients notified.</div>";
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $message = "<div class='msg-alert msg-error'><i class='fas fa-exclamation-triangle'></i> Error deleting slot.</div>";
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
                
                // Create placeholders for the IN clause
                $placeholders = implode(',', array_fill(0, count($slot_ids), '?'));
                $types = str_repeat('i', count($slot_ids)); 
                
                // 2. Update all appointments in these slots
                $stmt_update_apps = $conn->prepare("UPDATE appointments SET request_status = 'canceled_by_doctor' WHERE slot_id IN ($placeholders)");
                $stmt_update_apps->bind_param($types, ...$slot_ids);
                $stmt_update_apps->execute();

                // 3. Delete all slots for this date
                $stmt_del_slots = $conn->prepare("DELETE FROM appointment_slots WHERE doctor_id = ? AND appointment_date = ?");
                $stmt_del_slots->bind_param("is", $doctor_id, $date_to_delete);
                $stmt_del_slots->execute();
                
                $conn->commit();
                $message = "<div class='msg-alert msg-success'><i class='fas fa-calendar-times'></i> Deleted all slots for $date_to_delete.</div>";
            } else {
                $conn->rollback(); 
                $message = "<div class='msg-alert msg-error'>No slots found for $date_to_delete.</div>";
            }
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $message = "<div class='msg-alert msg-error'>Error deleting slots: " . $exception->getMessage() . "</div>";
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
    <title>Manage Schedule | Medicare Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #007bff; --light-bg: #f4f7f6; --white: #fff; --text: #2c3e50; 
            --success: #28a745; --danger: #dc3545; --warning: #f39c12; --shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        * { box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: var(--light-bg); margin: 0; min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Navbar */
        .navbar { background: var(--white); padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .brand { font-size: 1.5rem; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 8px; }
        .back-link { color: var(--text); text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: 0.3s; }
        .back-link:hover { color: var(--primary); }

        /* Container */
        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; flex: 1; width: 100%; }

        .header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e0e0e0; padding-bottom: 15px; }
        .header h1 { margin: 0; color: var(--text); font-size: 1.8rem; }
        
        /* Messages */
        .msg-alert { padding: 15px; border-radius: 8px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; animation: slideIn 0.5s ease; }
        .msg-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* Date Group Cards */
        .date-card { background: var(--white); border-radius: 12px; box-shadow: var(--shadow); margin-bottom: 30px; overflow: hidden; }
        
        .date-header { 
            background: #f8f9fa; padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; 
            border-bottom: 1px solid #eee; 
        }
        .date-header h2 { margin: 0; color: var(--text); font-size: 1.1rem; display: flex; align-items: center; gap: 10px; }
        
        .btn-delete-all { 
            background: #fff; color: var(--danger); border: 1px solid #fadbd8; padding: 8px 15px; 
            border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 500; transition: 0.2s;
            display: flex; align-items: center; gap: 6px;
        }
        .btn-delete-all:hover { background: var(--danger); color: white; border-color: var(--danger); }

        /* Table */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 500px; }
        th { background: var(--white); color: #888; font-weight: 500; text-align: left; padding: 15px 25px; font-size: 0.9rem; }
        td { padding: 15px 25px; border-top: 1px solid #f0f0f0; color: #333; vertical-align: middle; }
        tr:hover { background-color: #fafafa; }

        /* Status Badges */
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-available { background: #e6f9ed; color: var(--success); }
        .status-booked { background: #fff8e1; color: var(--warning); }

        /* Delete Slot Button */
        .btn-delete { 
            background: none; border: none; color: #ccc; cursor: pointer; font-size: 1rem; transition: 0.2s; padding: 5px; 
        }
        .btn-delete:hover { color: var(--danger); transform: scale(1.1); }

        /* Empty State */
        .no-data { text-align: center; padding: 50px 20px; color: #888; }
        .no-data i { font-size: 3rem; margin-bottom: 15px; color: #e0e0e0; display: block; }
        .no-data a { color: var(--primary); font-weight: 600; text-decoration: none; }

        footer { text-align: center; padding: 25px; color: #888; border-top: 1px solid #e0e0e0; margin-top: auto; font-size: 0.9rem; background: var(--white); }

        @media (max-width: 600px) {
            .date-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .btn-delete-all { width: 100%; justify-content: center; }
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
            <h1>Manage Schedule</h1>
        </div>

        <?php echo $message; ?>

        <?php if (empty($grouped_slots)): ?>
            <div class="no-data">
                <i class="far fa-calendar-times"></i>
                <p>You have no scheduled slots.</p>
                <a href="doctor_dashboard.php">Go back to create some?</a>
            </div>
        <?php else: ?>
            
            <?php foreach ($grouped_slots as $date => $slots): ?>
                <div class="date-card">
                    <div class="date-header">
                        <h2><i class="far fa-calendar-alt" style="color: var(--primary);"></i> <?php echo date("l, F j, Y", strtotime($date)); ?></h2>
                        
                        <form action="manage_slots.php" method="POST" onsubmit="return confirm('WARNING:\nThis will delete ALL slots for <?php echo $date; ?>.\nAny patient with a confirmed appointment on this day will be canceled.\n\nAre you sure?');">
                            <input type="hidden" name="date_to_delete" value="<?php echo $date; ?>">
                            <button type="submit" name="delete_date" class="btn-delete-all">
                                <i class="fas fa-trash-alt"></i> Delete Day
                            </button>
                        </form>
                    </div>
                    
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($slots as $slot): ?>
                                    <tr>
                                        <td><strong><?php echo date("g:i A", strtotime($slot['start_time'])); ?></strong></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $slot['status']; ?>">
                                                <?php echo htmlspecialchars($slot['status']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <form action="manage_slots.php" method="POST" onsubmit="return confirm('Delete this specific slot?');" style="display: inline;">
                                                <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                                                <button type="submit" name="delete_slot" class="btn-delete" title="Delete Slot">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>

    </div>

    <footer>&copy; <?php echo date("Y"); ?> Medicare Portal. All Rights Reserved.</footer>

</body>
</html>
