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
    // Patient count is no longer taken from the form

    // 2. Validation
    if (empty($app_date) || empty($start_time_str) || empty($end_time_str)) {
        $message = "<div class='msg-error'>Please fill all fields correctly.</div>";
    } else {
        $start_time = new DateTime($start_time_str);
        $end_time = new DateTime($end_time_str);
        $total_duration_minutes = ($end_time->getTimestamp() - $start_time->getTimestamp()) / 60;
        
        // Define the fixed duration for each slot
        $slot_duration_minutes = 15; 

        if ($total_duration_minutes < $slot_duration_minutes) {
            $message = "<div class='msg-error'>End time must be at least 15 minutes after start time to create one slot.</div>";
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
                $message = "<div class='msg-success'>Successfully created $slots_created appointment slots for $app_date.</div>";
            } else {
                $message = "<div class='msg-error'>Could not create any 15-minute slots in the given time range.</div>";
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
    <title>Doctor Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- Global & Body --- */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f8fb; /* Lighter, cleaner background */
            margin: 0;
            padding: 20px;
        }

        /* --- Main Container --- */
        .main-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
        }

        /* --- Header --- */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap; /* For responsiveness */
        }
        .dashboard-header h1 {
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
            font-size: 2em;
        }
        .logout-btn {
            background-color: #e74c3c;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        .logout-btn:hover {
            background-color: #c0392b;
        }
        
        /* --- Message Styling --- */
        .msg-success, .msg-error {
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
            width: 100%;
            box-sizing: border-box; /* Ensures padding doesn't break layout */
        }
        .msg-success { background: #e0f8e9; border: 1px solid #2ecc71; color: #27ae60; }
        .msg-error { background: #ffebee; border: 1px solid #e74c3c; color: #c0392b; }

        /* --- Profile Card --- */
        .profile-box {
            background: #ffffff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }
        .profile-box h2 {
            margin-top: 0;
            color: #34495e;
            font-weight: 600;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            display: inline-block;
        }
        .profile-details {
            text-align: left;
        }
        .profile-details p {
            font-size: 1.1em;
            color: #333;
            margin: 10px 0;
            line-height: 1.6;
        }
        .profile-details p strong {
            color: #2c3e50;
            font-weight: 600;
            min-width: 100px;
            display: inline-block;
        }
        .profile-details p em {
            color: #555;
            font-style: normal;
        }
        .profile-details .designation {
            font-size: 1.3em;
            font-weight: 500;
            color: #34495e;
            margin-bottom: 5px;
        }
        .profile-details .workplace {
            font-size: 1.1em;
            font-style: italic;
            color: #7f8c8d;
            margin-bottom: 15px;
        }

        /* --- Action Grid --- */
        .action-grid {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Two columns */
            gap: 30px;
        }

        /* --- Action Card (for form and links) --- */
        .action-card {
            background: #ffffff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }
        .action-card h2 {
            margin-top: 0;
            color: #2c3e50;
            font-weight: 600;
            text-align: center;
            border-bottom: 2px solid #2ecc71;
            padding-bottom: 10px;
        }
        
        /* --- "View Requests" Card Specifics --- */
        #view-requests-panel {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        #view-requests-panel h2 {
            color: white;
            border-bottom-color: #fff;
        }
        #view-requests-panel p {
            font-size: 1.1em;
            margin-bottom: 25px;
        }
        .action-btn {
            display: inline-block;
            background: #ffffff;
            color: #3498db;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1em;
            transition: all 0.3s;
        }
        .action-btn:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0,0,0,0.1);
        }
        
        /* --- NEW: Style for the danger button --- */
        .action-btn.danger-btn {
            background-color: #e74c3c;
            color: white;
        }
        .action-btn.danger-btn:hover {
            background-color: #c0392b;
            color: white; /* Ensure text stays white */
        }


        /* --- Form Styles --- */
        .input-group {
            margin-bottom: 20px;
        }
        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #34495e;
            font-size: 14px;
        }
        .input-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #bdc3c7;
            border-radius: 8px;
            box-sizing: border-box; /* Important for width */
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
        }
        .submit-btn {
            width: 100%;
            padding: 15px;
            background-color: #2ecc71;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 10px;
        }
        .submit-btn:hover {
            background-color: #27ae60;
        }

        /* --- Responsive Design --- */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            .main-container {
                padding: 10px;
            }
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .dashboard-header h1 {
                font-size: 1.8em;
            }
            /* Stack the two action cards vertically */
            .action-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <div class="main-container">
        
        <header class="dashboard-header">
            <h1>Welcome, Dr. <?php echo htmlspecialchars($fullname); ?>!</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </header>

        <?php echo $message; ?>

        <div class="profile-box">
            <h2>Your Profile</h2>
            <div class="profile-details">
                <p class="designation"><?php echo htmlspecialchars($designation); ?></p>
                <p class="workplace"><?php echo htmlspecialchars($workplace); ?></p>
                <p><strong>Specialty:</strong> <em><?php echo htmlspecialchars($specialty); ?></em></p>
                <p><strong>Degrees:</strong> <em><?php echo htmlspecialchars($degrees); ?></em></p>
            </div>
        </div>

        <div class="action-grid">
            
            <div class="action-card" id="create-slots-panel">
                <h2>Create Appointment Slots</h2>
                <form action="doctor_dashboard.php" method="POST">
                    <div class="input-group">
                        <label for="appointment_date">Date for Appointments</label>
                        <input type="date" id="appointment_date" name="appointment_date" required>
                    </div>
                    <div class="input-group">
                        <label for="start_time">Starting Time</label>
                        <input type="time" id="start_time" name="start_time" required>
                    </div>
                    <div class="input-group">
                        <label for="end_time">Ending Time</label>
                        <input type="time" id="end_time" name="end_time" required>
                    </div>
                    
                    <button type="submit" name="create_slots" class="submit-btn">Create Schedule</button>
                </form>
            </div>
            
            <div class="action-card" id="view-requests-panel">
                <h2>Manage Appointments</h2>
                <p>View, confirm, or manage all your pending and confirmed appointments.</p>
                <a href="view_requests.php" class="action-btn">View Requests</a>
                
                <p style="margin-top: 25px; border-top: 1px solid #ffffff66; padding-top: 20px; font-size: 0.9em;">
                    Have an emergency? View your schedule to delete your availability for a specific day.
                </p>
                <a href="manage_slots.php" class="action-btn danger-btn">Manage My Schedule</a>
            </div>

        </div>
    </div>

    <script>
        // Set min date to today for the date input
        document.getElementById('appointment_date').min = new Date().toISOString().split("T")[0];
    </script>

</body>
</html>