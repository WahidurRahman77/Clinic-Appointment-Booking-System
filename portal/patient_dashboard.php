<?php
session_start();

// 1. Check if user is logged in as a patient
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'patient') {
    header("Location: login.php");
    exit;
}

$patient_id = $_SESSION["user_id"];
$fullname = $_SESSION["fullname"];
$message = '';

// 2. Database Connection
$conn = new mysqli("localhost", "root", "", "portal_db");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// --- HANDLE FORM SUBMISSIONS (BOOKING & CANCELLATION) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // A. Handle "Request Appointment"
    if (isset($_POST['request_appointment'])) {
        $slot_id = $_POST['slot_id'];

        // FIX #1: Concurrency Check
        // Check if ANY appointment exists for this slot (not just by this patient)
        $stmt_check_taken = $conn->prepare("SELECT id FROM appointments WHERE slot_id = ?");
        $stmt_check_taken->bind_param("i", $slot_id);
        $stmt_check_taken->execute();
        $stmt_check_taken->store_result();

        if ($stmt_check_taken->num_rows > 0) {
            // If rows > 0, it means someone else grabbed it milliseconds ago or it's already pending
            $message = "<div class='msg-error'>Sorry, this slot has just been requested by another patient. Please refresh and choose another time.</div>";
        } else {
            // Slot is truly free, proceed to insert
            $stmt_insert = $conn->prepare("INSERT INTO appointments (patient_id, slot_id) VALUES (?, ?)");
            $stmt_insert->bind_param("ii", $patient_id, $slot_id);
            
            if ($stmt_insert->execute()) { 
                $message = "<div class='msg-success'>Appointment requested successfully! The doctor will confirm it shortly.</div>"; 
            } else {
                $message = "<div class='msg-error'>Error requesting appointment. Please try again.</div>";
            }
            $stmt_insert->close();
        }
        $stmt_check_taken->close();
    }
    
    // B. Handle "Cancel Appointment"
    if (isset($_POST['cancel_appointment'])) {
        $appointment_id = $_POST['appointment_id']; 
        $slot_id = $_POST['slot_id']; // Passed from hidden input
        
        $conn->begin_transaction();
        try {
            // 1. Delete the appointment record
            $stmt_delete = $conn->prepare("DELETE FROM appointments WHERE id = ? AND patient_id = ?");
            $stmt_delete->bind_param("ii", $appointment_id, $patient_id);
            $stmt_delete->execute();

            // 2. Reset the slot status to 'available' (only if slot wasn't deleted by doctor)
            if ($slot_id) {
                $stmt_free_slot = $conn->prepare("UPDATE appointment_slots SET status = 'available' WHERE id = ?");
                $stmt_free_slot->bind_param("i", $slot_id);
                $stmt_free_slot->execute();
            }
            
            $conn->commit();
            $message = "<div class='msg-success'>Your appointment has been successfully canceled.</div>";
        } catch (mysqli_sql_exception $exception) { 
            $conn->rollback(); 
            $message = "<div class='msg-error'>Error canceling appointment.</div>"; 
        }
    }
}


// --- VIEW LOGIC: FILTERING & FETCHING DATA ---

$selected_category = $_GET['category'] ?? '';
$selected_doctor_id = $_GET['doctor_id'] ?? '';

// 1. Fetch Categories (Specialties)
$categories_result = $conn->query("SELECT DISTINCT specialty FROM users WHERE role = 'doctor' AND specialty IS NOT NULL ORDER BY specialty");

// 2. Fetch Doctors (if category selected)
$doctors_result = null;
if ($selected_category) {
    $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE role = 'doctor' AND specialty = ? ORDER BY fullname");
    $stmt->bind_param("s", $selected_category);
    $stmt->execute();
    $doctors_result = $stmt->get_result();
}

// 3. Fetch Available Slots (if doctor selected)
$available_slots = null;
$doctor_details = null;

if ($selected_doctor_id) {
    // Get Doctor Profile info
    $stmt_doc = $conn->prepare("SELECT fullname, specialty, degrees, designation, workplace FROM users WHERE id = ? AND role = 'doctor'");
    $stmt_doc->bind_param("i", $selected_doctor_id);
    $stmt_doc->execute();
    $doctor_details = $stmt_doc->get_result()->fetch_assoc();
    $stmt_doc->close();

    // FIX #2: Filter Visibility
    // We select slots that are 'available', future-dated, 
    // AND currently NOT present in the appointments table.
    $sql_slots = "
        SELECT s.id AS slot_id, s.appointment_date, s.start_time, d.fullname AS doctor_name 
        FROM appointment_slots s 
        JOIN users d ON s.doctor_id = d.id 
        WHERE s.status = 'available' 
        AND s.appointment_date >= CURDATE() 
        AND s.doctor_id = ? 
        AND s.id NOT IN (SELECT slot_id FROM appointments) 
        ORDER BY s.appointment_date, s.start_time
    ";

    $stmt = $conn->prepare($sql_slots);
    $stmt->bind_param("i", $selected_doctor_id);
    $stmt->execute();
    $available_slots = $stmt->get_result();
}


// 4. Fetch Patient's Own Appointment History
// We use LEFT JOINs so history is preserved even if the doctor deletes the slot/account
$sql_my_appointments = "
    SELECT 
        a.id AS appointment_id, 
        a.slot_id, 
        a.request_status, 
        s.appointment_date, 
        s.start_time, 
        d.fullname AS doctor_name 
    FROM appointments a
    LEFT JOIN appointment_slots s ON a.slot_id = s.id
    LEFT JOIN users d ON s.doctor_id = d.id
    WHERE a.patient_id = ?
    ORDER BY 
        CASE WHEN s.appointment_date IS NULL THEN 1 ELSE 0 END, 
        s.appointment_date, 
        s.start_time
";
$stmt_my_app = $conn->prepare($sql_my_appointments);
$stmt_my_app->bind_param("i", $patient_id);
$stmt_my_app->execute();
$my_appointments = $stmt_my_app->get_result();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- Global & Body --- */
        body { font-family: 'Poppins', sans-serif; background-color: #f4f8fb; margin: 0; padding: 20px; }
        .main-container { max-width: 1100px; margin: 20px auto; padding: 20px; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
        .dashboard-header h1 { color: #2c3e50; font-weight: 600; margin: 0; font-size: 2em; }
        .logout-btn { background-color: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; font-weight: 500; }
        
        /* --- Messages --- */
        .msg-success, .msg-error { padding: 15px; text-align: center; margin-bottom: 20px; border-radius: 8px; font-weight: 500; width: 100%; box-sizing: border-box; }
        .msg-success { background: #e0f8e9; border: 1px solid #2ecc71; color: #27ae60; }
        .msg-error { background: #ffebee; border: 1px solid #e74c3c; color: #c0392b; }
        
        /* --- Cards --- */
        .dashboard-card { background: #ffffff; border-radius: 15px; padding: 30px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); margin-bottom: 30px; }
        .dashboard-card h2 { margin-top: 0; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #2ecc71; padding-bottom: 10px; margin-bottom: 25px; }
        
        /* --- Booking: Filter Steps --- */
        .filter-steps { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .filter-step label { display: block; margin-bottom: 8px; font-weight: 500; color: #34495e; font-size: 14px; }
        .filter-step select { width: 100%; padding: 12px 15px; border: 1px solid #bdc3c7; border-radius: 8px; box-sizing: border-box; font-size: 16px; font-family: 'Poppins', sans-serif; cursor: pointer; }
        
        /* --- Booking: Doctor Profile --- */
        .doctor-profile { background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 25px; }
        .doctor-profile h3 { margin-top: 0; color: #34495e; font-size: 1.5em; }
        .doctor-profile p { margin: 5px 0; color: #555; font-size: 0.95em; }

        /* --- Tables --- */
        .table-container { width: 100%; overflow-x: auto; }
        .appointments-table { width: 100%; border-collapse: collapse; }
        .appointments-table th, .appointments-table td { padding: 15px; text-align: left; border-bottom: 1px solid #e0e0e0; font-size: 0.95em; vertical-align: middle; }
        .appointments-table th { background-color: #ecf0f1; color: #34495e; font-weight: 600; }
        
        /* --- Status & Buttons --- */
        .status { padding: 5px 12px; border-radius: 50px; color: white; font-size: 0.85em; font-weight: 600; text-transform: capitalize; }
        .status-pending { background-color: #f39c12; }
        .status-confirmed { background-color: #2ecc71; }
        
        .status-canceled_by_doctor { 
            background-color: #e74c3c; 
            font-size: 0.9em; 
            white-space: normal;
            text-transform: none; 
            text-align: center; 
            padding: 8px;
            border-radius: 8px;
        }

        .btn { border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: 500; font-size: 0.9em; transition: all 0.3s; }
        .btn-request { background-color: #3498db; color: white; }
        .btn-cancel { background-color: #e74c3c; color: white; }
        .btn-cancel:disabled { background-color: #bdc3c7; cursor: not-allowed; }

        .condition { background-color: #fffbe6; border-left: 4px solid #f39c12; padding: 15px; margin: 20px 0; font-size: 0.9em; color: #555; }
        .placeholder-text { text-align: center; color: #7f8c8d; padding: 20px; font-size: 1.1em; }

        @media (max-width: 768px) {
            body { padding: 10px; }
            .filter-steps { grid-template-columns: 1fr; }
            .dashboard-card { padding: 20px; }
        }
    </style>
</head>
<body>

    <div class="main-container">
        
        <header class="dashboard-header">
            <h1>Welcome, <?php echo htmlspecialchars($fullname); ?>!</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </header>

        <?php echo $message; ?>

        <div class="dashboard-card">
            <h2>Book a New Appointment</h2>
            
            <form action="patient_dashboard.php" method="GET" id="filterForm">
                <div class="filter-steps">
                    <div class="filter-step">
                        <label for="category">Step 1: Select Doctor Category</label>
                        <select name="category" id="category" onchange="this.form.submit()">
                            <option value="">-- Select Category --</option>
                            <?php while($cat = $categories_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($cat['specialty']); ?>" <?php if($selected_category == $cat['specialty']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($cat['specialty']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <?php if ($selected_category && $doctors_result): ?>
                    <div class="filter-step">
                        <label for="doctor_id">Step 2: Select Doctor</label>
                         <select name="doctor_id" id="doctor_id" onchange="this.form.submit()">
                            <option value="">-- Select Doctor --</option>
                            <?php while($doc = $doctors_result->fetch_assoc()): ?>
                                <option value="<?php echo $doc['id']; ?>" <?php if($selected_doctor_id == $doc['id']) echo 'selected'; ?>>
                                    Dr. <?php echo htmlspecialchars($doc['fullname']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($selected_doctor_id && $doctor_details): ?>
                <div class="doctor-profile">
                    <h3>Dr. <?php echo htmlspecialchars($doctor_details['fullname']); ?></h3>
                    <p><strong><?php echo htmlspecialchars($doctor_details['designation']); ?></strong></p>
                    <p><em><?php echo htmlspecialchars($doctor_details['workplace']); ?></em></p>
                    <p><strong>Specialty:</strong> <?php echo htmlspecialchars($doctor_details['specialty']); ?></p>
                    <p><strong>Degrees:</strong> <?php echo htmlspecialchars($doctor_details['degrees']); ?></p>
                </div>
                
                <div class="condition"><b>Condition Message:</b> If a patient does not attend 10 minutes before the appointment, the appointment will be canceled.</div>
                
                <div class="table-container">
                    <?php if ($available_slots && $available_slots->num_rows > 0): ?>
                    <table class="appointments-table">
                        <thead> <tr> <th>Date</th> <th>Time</th> <th>Action</th> </tr> </thead>
                        <tbody>
                            <?php while($row = $available_slots->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date("D, M j, Y", strtotime($row['appointment_date'])); ?></td>
                                <td><?php echo date("g:i A", strtotime($row['start_time'])); ?></td>
                                <td>
                                    <form action="patient_dashboard.php?category=<?php echo urlencode($selected_category); ?>&doctor_id=<?php echo $selected_doctor_id; ?>" method="POST">
                                        <input type="hidden" name="slot_id" value="<?php echo $row['slot_id']; ?>">
                                        <button type="submit" name="request_appointment" class="btn btn-request">Request</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p class="placeholder-text">Dr. <?php echo htmlspecialchars($doctor_details['fullname']); ?> has no available appointments (or all are currently pending). Please check back later.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($selected_category): ?>
                <p class="placeholder-text">Please select a doctor to see their profile and available times.</p>
            <?php else: ?>
                <p class="placeholder-text">Please select a category to begin booking.</p>
            <?php endif; ?>
        </div>
        
        <div class="dashboard-card">
            <h2>My Appointments</h2>
            <div class="table-container">
                <?php if ($my_appointments->num_rows > 0): ?>
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $my_appointments->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['doctor_name'] ? 'Dr. ' . htmlspecialchars($row['doctor_name']) : 'N/A'; ?></td>
                            <td><?php echo $row['appointment_date'] ? date("M j, Y", strtotime($row['appointment_date'])) : 'N/A'; ?></td>
                            <td><?php echo $row['start_time'] ? date("g:i A", strtotime($row['start_time'])) : 'N/A'; ?></td>
                            
                            <td>
                                <?php if ($row['request_status'] == 'canceled_by_doctor'): ?>
                                    <span class="status status-canceled_by_doctor">
                                        Due to an Emergency the appointment is Canceled by Doctor
                                    </span>
                                <?php else: ?>
                                    <span class="status status-<?php echo $row['request_status']; ?>">
                                        <?php echo htmlspecialchars($row['request_status']); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <form action="patient_dashboard.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                    <input type="hidden" name="appointment_id" value="<?php echo $row['appointment_id']; ?>">
                                    <input type="hidden" name="slot_id" value="<?php echo $row['slot_id']; ?>">
                                    <button type="submit" name="cancel_appointment" class="btn btn-cancel" 
                                        <?php if ($row['request_status'] == 'canceled_by_doctor') echo 'disabled'; ?>>
                                        Cancel
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p class="placeholder-text">You have no upcoming appointments.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</body>
</html>