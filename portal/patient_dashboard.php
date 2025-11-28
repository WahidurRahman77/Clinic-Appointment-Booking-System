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
        $stmt_check_taken = $conn->prepare("SELECT id FROM appointments WHERE slot_id = ?");
        $stmt_check_taken->bind_param("i", $slot_id);
        $stmt_check_taken->execute();
        $stmt_check_taken->store_result();

        if ($stmt_check_taken->num_rows > 0) {
            $message = "<div class='msg-alert msg-error'><i class='fas fa-exclamation-circle'></i> Sorry, this slot was just taken. Please refresh.</div>";
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO appointments (patient_id, slot_id) VALUES (?, ?)");
            $stmt_insert->bind_param("ii", $patient_id, $slot_id);
            
            if ($stmt_insert->execute()) { 
                $message = "<div class='msg-alert msg-success'><i class='fas fa-check-circle'></i> Appointment requested! The doctor will confirm it shortly.</div>"; 
            } else {
                $message = "<div class='msg-alert msg-error'><i class='fas fa-times-circle'></i> Error requesting appointment.</div>";
            }
            $stmt_insert->close();
        }
        $stmt_check_taken->close();
    }
    
    // B. Handle "Cancel Appointment"
    if (isset($_POST['cancel_appointment'])) {
        $appointment_id = $_POST['appointment_id']; 
        $slot_id = $_POST['slot_id']; 
        
        $conn->begin_transaction();
        try {
            $stmt_delete = $conn->prepare("DELETE FROM appointments WHERE id = ? AND patient_id = ?");
            $stmt_delete->bind_param("ii", $appointment_id, $patient_id);
            $stmt_delete->execute();

            if ($slot_id) {
                $stmt_free_slot = $conn->prepare("UPDATE appointment_slots SET status = 'available' WHERE id = ?");
                $stmt_free_slot->bind_param("i", $slot_id);
                $stmt_free_slot->execute();
            }
            
            $conn->commit();
            $message = "<div class='msg-alert msg-success'><i class='fas fa-trash-alt'></i> Appointment successfully canceled.</div>";
        } catch (mysqli_sql_exception $exception) { 
            $conn->rollback(); 
            $message = "<div class='msg-alert msg-error'>Error canceling appointment.</div>"; 
        }
    }
}


// --- VIEW LOGIC ---

$selected_category = $_GET['category'] ?? '';
$selected_doctor_id = $_GET['doctor_id'] ?? '';

// 1. Fetch Categories
$categories_result = $conn->query("SELECT DISTINCT specialty FROM users WHERE role = 'doctor' AND specialty IS NOT NULL ORDER BY specialty");

// 2. Fetch Doctors
$doctors_result = null;
if ($selected_category) {
    $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE role = 'doctor' AND specialty = ? ORDER BY fullname");
    $stmt->bind_param("s", $selected_category);
    $stmt->execute();
    $doctors_result = $stmt->get_result();
}

// 3. Fetch Available Slots
$available_slots = null;
$doctor_details = null;

if ($selected_doctor_id) {
    $stmt_doc = $conn->prepare("SELECT fullname, specialty, degrees, designation, workplace FROM users WHERE id = ? AND role = 'doctor'");
    $stmt_doc->bind_param("i", $selected_doctor_id);
    $stmt_doc->execute();
    $doctor_details = $stmt_doc->get_result()->fetch_assoc();
    $stmt_doc->close();

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


// 4. Fetch History
$sql_my_appointments = "
    SELECT 
        a.id AS appointment_id, a.slot_id, a.request_status, 
        s.appointment_date, s.start_time, d.fullname AS doctor_name 
    FROM appointments a
    LEFT JOIN appointment_slots s ON a.slot_id = s.id
    LEFT JOIN users d ON s.doctor_id = d.id
    WHERE a.patient_id = ?
    ORDER BY CASE WHEN s.appointment_date IS NULL THEN 1 ELSE 0 END, s.appointment_date, s.start_time
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
    <title>Patient Dashboard | Medicare Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- Shared Variables & Global --- */
        :root {
            --primary-color: #007bff; --primary-dark: #0056b3; --success-color: #28a745;
            --warning-color: #f39c12; --danger-color: #dc3545; --light-bg: #f4f7f6;
            --white: #ffffff; --text-dark: #2c3e50; --text-muted: #6c757d;
            --shadow: 0 10px 30px rgba(0,0,0,0.08); --radius: 12px;
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--light-bg); margin: 0; min-height: 100vh; display: flex; flex-direction: column; }
        
        /* --- Navbar (Matches Doctor) --- */
        .navbar { background: var(--white); padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 1000; }
        .navbar-brand { font-size: 1.5rem; font-weight: 800; color: var(--primary-color); }
        .navbar-brand span { color: var(--success-color); }
        .logout-btn { background: #fff; color: var(--danger-color); border: 2px solid #fadbd8; padding: 8px 25px; border-radius: 50px; text-decoration: none; font-weight: 600; transition: 0.3s; display: flex; align-items: center; gap: 8px; }
        .logout-btn:hover { background: var(--danger-color); color: white; border-color: var(--danger-color); }

        .main-container { max-width: 1100px; margin: 40px auto; padding: 0 20px; flex: 1; width: 100%; box-sizing: border-box; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 2rem; color: var(--text-dark); margin: 0; font-weight: 700; }

        /* --- Cards --- */
        .card { background: var(--white); border-radius: var(--radius); padding: 30px; box-shadow: var(--shadow); margin-bottom: 30px; }
        .card-header { border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 25px; }
        .card-header h2 { margin: 0; font-size: 1.4rem; color: var(--text-dark); display: flex; align-items: center; gap: 10px; }

        /* --- Booking Wizard --- */
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .filter-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: var(--text-dark); }
        .filter-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; font-family: 'Poppins', sans-serif; cursor: pointer; transition: 0.3s; }
        .filter-group select:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 3px rgba(0,123,255,0.1); }

        /* --- Doctor Profile Preview --- */
        .doc-profile { display: flex; align-items: center; gap: 20px; background: #f8f9fa; padding: 20px; border-radius: var(--radius); border-left: 5px solid var(--primary-color); margin-bottom: 25px; }
        .doc-icon { font-size: 2.5rem; color: var(--primary-color); background: #e6f2ff; width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .doc-info h3 { margin: 0; font-size: 1.3rem; }
        .doc-info p { margin: 5px 0 0; color: var(--text-muted); font-size: 0.9rem; }
        .doc-info strong { color: var(--text-dark); }
        .condition-alert { background: #fff3cd; color: #856404; padding: 12px 15px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 20px; border: 1px solid #ffeeba; display: flex; align-items: center; gap: 10px; }

        /* --- Tables --- */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th { background: #f8f9fa; color: var(--text-muted); font-weight: 600; text-align: left; padding: 15px; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 15px; border-bottom: 1px solid #eee; vertical-align: middle; color: var(--text-dark); }
        tr:last-child td { border-bottom: none; }

        /* --- Badges & Buttons --- */
        .badge { padding: 6px 12px; border-radius: 30px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; display: inline-block; }
        .bg-pending { background: #fff3cd; color: #856404; }
        .bg-confirmed { background: #d1e7dd; color: #0f5132; }
        .bg-canceled { background: #f8d7da; color: #842029; }
        
        .btn { border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 0.9rem; transition: 0.2s; }
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-danger { background: var(--white); color: var(--danger-color); border: 1px solid var(--danger-color); }
        .btn-danger:hover { background: var(--danger-color); color: white; }
        .btn-disabled { background: #e9ecef; color: #adb5bd; cursor: not-allowed; }

        /* --- Alerts & Footer --- */
        .msg-alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; display: flex; align-items: center; gap: 10px; animation: slideIn 0.5s ease; }
        .msg-success { background: #d1e7dd; color: #0f5132; border-left: 5px solid var(--success-color); }
        .msg-error { background: #f8d7da; color: #842029; border-left: 5px solid var(--danger-color); }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        footer { background: var(--white); color: var(--text-muted); text-align: center; padding: 25px 0; margin-top: auto; border-top: 1px solid #e0e0e0; font-size: 0.9rem; }
        .placeholder-text { text-align: center; padding: 30px; color: var(--text-muted); font-style: italic; }

        @media (max-width: 768px) {
            .doc-profile { flex-direction: column; text-align: center; border-left: none; border-top: 5px solid var(--primary-color); }
            .filter-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="navbar-brand"><i class="fas fa-heartbeat"></i> Medicare<span>Portal</span></div>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <div class="main-container">
        <div class="page-header">
            <h1>Welcome, <?php echo htmlspecialchars($fullname); ?></h1>
            <p style="color: var(--text-muted); margin-top: 5px;">Manage your health and appointments.</p>
        </div>

        <?php echo $message; ?>

        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-calendar-plus" style="color: var(--primary-color);"></i> Book New Appointment</h2>
            </div>
            
            <form action="patient_dashboard.php" method="GET" id="filterForm">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>1. Select Specialty</label>
                        <select name="category" onchange="this.form.submit()">
                            <option value="">-- Choose Category --</option>
                            <?php while($cat = $categories_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($cat['specialty']); ?>" <?php if($selected_category == $cat['specialty']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($cat['specialty']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <?php if ($selected_category && $doctors_result): ?>
                    <div class="filter-group">
                        <label>2. Select Doctor</label>
                         <select name="doctor_id" onchange="this.form.submit()">
                            <option value="">-- Choose Doctor --</option>
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

            <div style="margin-top: 30px;">
            <?php if ($selected_doctor_id && $doctor_details): ?>
                <div class="doc-profile">
                    <div class="doc-icon"><i class="fas fa-user-md"></i></div>
                    <div class="doc-info">
                        <h3>Dr. <?php echo htmlspecialchars($doctor_details['fullname']); ?></h3>
                        <p><?php echo htmlspecialchars($doctor_details['designation']); ?> | <strong><?php echo htmlspecialchars($doctor_details['specialty']); ?></strong></p>
                        <p><i class="fas fa-hospital-alt"></i> <?php echo htmlspecialchars($doctor_details['workplace']); ?></p>
                        <p style="font-size: 0.85rem; color: #888;"><?php echo htmlspecialchars($doctor_details['degrees']); ?></p>
                    </div>
                </div>
                
                <div class="condition-alert">
                    <i class="fas fa-info-circle"></i> 
                    <b>Note:</b> Please arrive 10 minutes before your scheduled slot. Late arrivals may be canceled.
                </div>
                
                <div class="table-responsive">
                    <?php if ($available_slots && $available_slots->num_rows > 0): ?>
                    <table>
                        <thead> <tr> <th>Date</th> <th>Time</th> <th style="text-align: right;">Action</th> </tr> </thead>
                        <tbody>
                            <?php while($row = $available_slots->fetch_assoc()): ?>
                            <tr>
                                <td><i class="far fa-calendar-alt" style="color:#888; margin-right:5px;"></i> <?php echo date("D, M j, Y", strtotime($row['appointment_date'])); ?></td>
                                <td><i class="far fa-clock" style="color:#888; margin-right:5px;"></i> <?php echo date("g:i A", strtotime($row['start_time'])); ?></td>
                                <td style="text-align: right;">
                                    <form action="patient_dashboard.php?category=<?php echo urlencode($selected_category); ?>&doctor_id=<?php echo $selected_doctor_id; ?>" method="POST">
                                        <input type="hidden" name="slot_id" value="<?php echo $row['slot_id']; ?>">
                                        <button type="submit" name="request_appointment" class="btn btn-primary">Book Now</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p class="placeholder-text">No slots available for this doctor currently.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($selected_category): ?>
                <p class="placeholder-text"><i class="fas fa-arrow-up"></i> Select a doctor to view their profile and schedule.</p>
            <?php else: ?>
                <p class="placeholder-text"><i class="fas fa-arrow-up"></i> Start by selecting a medical specialty above.</p>
            <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-history" style="color: var(--success-color);"></i> My Appointments</h2>
            </div>
            <div class="table-responsive">
                <?php if ($my_appointments->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $my_appointments->fetch_assoc()): 
                            $status = $row['request_status'];
                            $status_class = ($status == 'confirmed') ? 'bg-confirmed' : (($status == 'pending') ? 'bg-pending' : 'bg-canceled');
                            $status_text = ($status == 'canceled_by_doctor') ? 'Canceled by Dr.' : $status;
                        ?>
                        <tr>
                            <td><strong>Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></strong></td>
                            <td>
                                <?php if($row['appointment_date']): ?>
                                    <?php echo date("M j, Y", strtotime($row['appointment_date'])); ?> <br>
                                    <small style="color:#888"><?php echo date("g:i A", strtotime($row['start_time'])); ?></small>
                                <?php else: ?>
                                    <span style="color: #999;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($status_text); ?></span></td>
                            <td style="text-align: right;">
                                <form action="patient_dashboard.php" method="POST" onsubmit="return confirm('Cancel this appointment?');">
                                    <input type="hidden" name="appointment_id" value="<?php echo $row['appointment_id']; ?>">
                                    <input type="hidden" name="slot_id" value="<?php echo $row['slot_id']; ?>">
                                    <button type="submit" name="cancel_appointment" class="btn <?php echo ($status == 'canceled_by_doctor') ? 'btn-disabled' : 'btn-danger'; ?>" 
                                        <?php if ($status == 'canceled_by_doctor') echo 'disabled'; ?>>
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p class="placeholder-text">You have no appointment history.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <footer>
        <div class="container">
            &copy; <?php echo date("Y"); ?> <strong>Medicare Portal</strong>. All Rights Reserved.
        </div>
    </footer>

</body>
</html>
