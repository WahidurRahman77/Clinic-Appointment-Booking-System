<?php
// Always start the session on any page that needs session data
session_start();

// Check if the user is logged in and is a doctor.
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'doctor') {
    header("Location: login.php?role=doctor");
    exit;
}

// Get user data from the session
$fullname = $_SESSION["fullname"];
$doctor_id = $_SESSION["user_id"];
$message = '';

// Get new details from session
$specialty = $_SESSION["specialty"] ?? 'N/A';
$degrees = $_SESSION["degrees"] ?? 'N/A';
$designation = $_SESSION["designation"] ?? 'N/A';
$workplace = $_SESSION["workplace"] ?? 'N/A';

// --- DYNAMIC GREETING LOGIC ---
date_default_timezone_set('Asia/Dhaka'); // Set your timezone
$hour = date('H');
$greeting = "Welcome";
if ($hour >= 5 && $hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour >= 12 && $hour < 18) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}

// --- PHP LOGIC TO HANDLE FORM SUBMISSION (No Changes) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_slots'])) {
    
    // START PHP DATABASE CONNECTION
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "portal_db";
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    // END PHP DATABASE CONNECTION

    // 1. Get and sanitize form data
    $app_date = $_POST['appointment_date'];
    $start_time_str = $_POST['start_time'];
    $end_time_str = $_POST['end_time'];

    // 2. Validation
    if (empty($app_date) || empty($start_time_str) || empty($end_time_str)) {
        $message = "<div class='msg-alert msg-error'><i class='fas fa-exclamation-circle'></i> Please fill all fields correctly.</div>";
    } else {
        $start_time = new DateTime($start_time_str);
        $end_time = new DateTime($end_time_str);
        $total_duration_minutes = ($end_time->getTimestamp() - $start_time->getTimestamp()) / 60;
        
        // Define the fixed duration for each slot
        $slot_duration_minutes = 15; 

        if ($total_duration_minutes < $slot_duration_minutes) {
            $message = "<div class='msg-alert msg-error'><i class='fas fa-exclamation-triangle'></i> End time must be at least 15 minutes after start time.</div>";
        } else {
            
            $current_slot_start = clone $start_time;
            
            // Use a Prepared Statement to prevent SQL Injection
            $stmt = $conn->prepare("INSERT INTO appointment_slots (doctor_id, appointment_date, start_time, end_time) VALUES (?, ?, ?, ?)");
            
            $slots_created = 0;
            
            // Loop while the start of the *next* slot is still before or at the end time
            while ($current_slot_start < $end_time) {
                
                $current_slot_end = clone $current_slot_start;
                $current_slot_end->modify("+" . $slot_duration_minutes . " minutes");

                // Stop if this new slot would go PAST the doctor's end time
                if ($current_slot_end > $end_time) {
                    break; 
                }

                $start_time_db = $current_slot_start->format('H:i:s');
                $end_time_db = $current_slot_end->format('H:i:s');

                $stmt->bind_param("isss", $doctor_id, $app_date, $start_time_db, $end_time_db);
                if ($stmt->execute()) {
                    $slots_created++;
                }

                // Set the start of the next slot
                $current_slot_start = $current_slot_end;
            }

            if ($slots_created > 0) {
                $message = "<div class='msg-alert msg-success'><i class='fas fa-check-circle'></i> Successfully created $slots_created appointment slots for $app_date.</div>";
            } else {
                $message = "<div class='msg-alert msg-error'><i class='fas fa-times-circle'></i> Could not create any 15-minute slots in the given time range.</div>";
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard | Medicare Portal</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* --- Global Variables & Reset --- */
        :root {
            --primary-color: #007bff;
            --primary-dark: #0056b3;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --light-bg: #f4f7f6;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
            --shadow-sm: 0 4px 6px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 30px rgba(0, 0, 0, 0.08);
            --border-radius: 15px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            margin: 0;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* --- Navbar --- */
        .navbar {
            background-color: var(--white);
            padding: 15px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .navbar-brand span { color: var(--success-color); }
        
        .logout-btn {
            background-color: #fff;
            color: var(--danger-color);
            border: 2px solid #fadbd8;
            padding: 8px 25px;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .logout-btn:hover {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        /* --- Main Container --- */
        .main-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
            flex: 1; /* Pushes footer down */
            width: 100%;
        }

        /* --- Greeting Header --- */
        .greeting-header {
            margin-bottom: 30px;
        }
        .greeting-header h1 {
            font-size: 2rem;
            color: var(--text-dark);
            margin: 0;
            font-weight: 700;
        }
        .greeting-header p {
            color: var(--text-muted);
            margin: 5px 0 0;
            font-size: 1rem;
        }

        /* --- Alerts --- */
        .msg-alert {
            padding: 15px 20px;
            margin-bottom: 30px;
            border-radius: 10px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.5s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .msg-success { background: #d1e7dd; color: #0f5132; border-left: 5px solid var(--success-color); }
        .msg-error { background: #f8d7da; color: #842029; border-left: 5px solid var(--danger-color); }

        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* --- Profile Card --- */
        .profile-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow-md);
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            gap: 30px;
            border-left: 5px solid var(--primary-color);
            position: relative;
            overflow: hidden;
        }
        .profile-card::after {
            content: '';
            position: absolute;
            right: -20px;
            top: -20px;
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, rgba(0,123,255,0.1), rgba(0,123,255,0));
            border-radius: 50%;
        }
        .profile-icon {
            background: linear-gradient(135deg, #e6f2ff, #cce5ff);
            width: 90px;
            height: 90px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--primary-color);
            box-shadow: 0 5px 15px rgba(0,123,255,0.15);
        }
        .profile-info h2 { margin: 0; font-size: 1.6rem; color: var(--text-dark); }
        .profile-info .designation { color: var(--primary-color); font-weight: 600; margin-bottom: 10px; display: block; }
        .profile-meta { display: flex; flex-wrap: wrap; gap: 20px; color: var(--text-muted); font-size: 0.95rem; }
        .profile-meta span { display: flex; align-items: center; gap: 8px; }

        /* --- Dashboard Grid --- */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 30px;
        }

        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 35px;
            box-shadow: var(--shadow-md);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12); }
        
        .card-header { border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 25px; }
        .card-header h3 { margin: 0; font-size: 1.3rem; color: var(--text-dark); display: flex; align-items: center; gap: 10px; }

        /* --- Form Styling --- */
        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark); font-size: 0.9rem; }
        .input-wrapper { position: relative; }
        .input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #999; }
        .input-wrapper input {
            width: 100%; padding: 14px 15px 14px 45px; border: 1px solid #e0e0e0; border-radius: 10px;
            font-size: 1rem; font-family: 'Poppins', sans-serif; transition: all 0.3s;
        }
        .input-wrapper input:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.1); }

        .submit-btn {
            width: 100%; padding: 15px; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none; border-radius: 10px; color: white; font-size: 1.1rem; font-weight: 600;
            cursor: pointer; transition: all 0.3s ease; display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 10px;
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.2);
        }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0, 123, 255, 0.3); }

        /* --- Action Buttons --- */
        .action-content { text-align: center; }
        .action-content p { color: var(--text-muted); margin-bottom: 30px; line-height: 1.6; }

        .btn-view-req {
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            background-color: #f0f7ff; color: var(--primary-color); padding: 14px 30px; border-radius: 50px;
            text-decoration: none; font-weight: 600; transition: all 0.3s ease; width: 100%; border: 1px solid transparent;
        }
        .btn-view-req:hover { background-color: var(--primary-color); color: white; box-shadow: 0 5px 15px rgba(0, 123, 255, 0.25); }

        .danger-zone { margin-top: 35px; padding-top: 20px; border-top: 1px dashed #e0e0e0; }
        .danger-zone p { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 15px; }
        .btn-manage-schedule {
            color: var(--danger-color); text-decoration: none; font-weight: 600; font-size: 0.9rem;
            border: 1px solid #fadbd8; padding: 10px 20px; border-radius: 8px; transition: all 0.3s;
            display: inline-flex; align-items: center; gap: 8px; background: #fff;
        }
        .btn-manage-schedule:hover { background-color: var(--danger-color); color: white; border-color: var(--danger-color); }

        /* --- Footer --- */
        footer {
            background-color: var(--white);
            color: var(--text-muted);
            text-align: center;
            padding: 25px 0;
            margin-top: 40px;
            border-top: 1px solid #e0e0e0;
            font-size: 0.9rem;
        }
        footer strong { color: var(--primary-color); }

        @media (max-width: 768px) {
            .navbar { padding: 15px 20px; }
            .profile-card { flex-direction: column; text-align: center; border-left: none; border-top: 5px solid var(--primary-color); }
            .profile-meta { justify-content: center; }
            .dashboard-grid { grid-template-columns: 1fr; }
            .input-group-row { flex-direction: column; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-heartbeat fa-lg"></i>
            Medicare<span>Portal</span>
        </div>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <div class="main-container">
        
        <div class="greeting-header">
            <h1><?php echo $greeting; ?>, Dr. <?php echo htmlspecialchars($fullname); ?>!</h1>
            <p>Here is an overview of your schedule and tasks for today.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div id="alert-box">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="profile-card">
            <div class="profile-icon">
                <i class="fas fa-user-md"></i>
            </div>
            <div class="profile-info">
                <span class="designation"><?php echo htmlspecialchars($designation); ?></span>
                <h2>Dr. <?php echo htmlspecialchars($fullname); ?></h2>
                <div class="profile-meta">
                    <span><i class="fas fa-hospital-alt" style="color: var(--success-color);"></i> <?php echo htmlspecialchars($workplace); ?></span>
                    <span><i class="fas fa-stethoscope" style="color: var(--primary-color);"></i> <?php echo htmlspecialchars($specialty); ?></span>
                    <span><i class="fas fa-graduation-cap" style="color: #6610f2;"></i> <?php echo htmlspecialchars($degrees); ?></span>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="far fa-calendar-plus" style="color: var(--primary-color);"></i> Create Schedule</h3>
                </div>
                <form action="doctor_dashboard.php" method="POST">
                    <div class="input-group">
                        <label for="appointment_date">Select Date</label>
                        <div class="input-wrapper">
                            <i class="far fa-calendar-alt"></i>
                            <input type="date" id="appointment_date" name="appointment_date" required>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 20px;" class="input-group-row">
                        <div class="input-group" style="flex: 1;">
                            <label for="start_time">Start Time</label>
                            <div class="input-wrapper">
                                <i class="far fa-clock"></i>
                                <input type="time" id="start_time" name="start_time" required>
                            </div>
                        </div>
                        <div class="input-group" style="flex: 1;">
                            <label for="end_time">End Time</label>
                            <div class="input-wrapper">
                                <i class="far fa-clock"></i>
                                <input type="time" id="end_time" name="end_time" required>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="create_slots" class="submit-btn">
                        <i class="fas fa-magic"></i> Generate Slots
                    </button>
                </form>
            </div>
            
            <div class="card action-card">
                <div class="card-header">
                    <h3><i class="fas fa-tasks" style="color: var(--success-color);"></i> Appointment Actions</h3>
                </div>
                <div class="action-content">
                    <p>You can view all pending requests from patients, confirm their bookings, or review your daily schedule.</p>
                    
                    <a href="view_requests.php" class="btn-view-req">
                        View Pending Requests <i class="fas fa-arrow-right"></i>
                    </a>
                    
                    <div class="danger-zone">
                        <p><i class="fas fa-exclamation-circle"></i> Need to cancel availability?</p>
                        <a href="manage_slots.php" class="btn-manage-schedule">
                            <i class="fas fa-calendar-times"></i> Manage / Delete Slots
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <footer>
        <div class="container">
            &copy; <?php echo date("Y"); ?> <strong>Medicare Portal</strong>. All Rights Reserved. <br>
            Designed for Excellence in Healthcare Management.
        </div>
    </footer>

    <script>
        // Set min date to today for the date input
        document.getElementById('appointment_date').min = new Date().toISOString().split("T")[0];

        // Auto-dismiss alerts after 4 seconds
        setTimeout(function() {
            var alertBox = document.getElementById('alert-box');
            if (alertBox) {
                alertBox.style.transition = 'opacity 0.5s ease';
                alertBox.style.opacity = '0';
                setTimeout(function() {
                    alertBox.remove();
                }, 500);
            }
        }, 4000);
    </script>

</body>
</html>
