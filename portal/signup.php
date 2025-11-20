<?php
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

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $role = $_POST['role'] ?? 'patient';
    $fullname = $_POST['fullname'] ?? '';
    $email = $_POST['email'] ?? '';
    $password_plain = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm-password'] ?? '';
    
    // Get doctor-specific fields (or set to null if not a doctor)
    $specialty = ($role === 'doctor') ? ($_POST['specialty'] ?? '') : null;
    $degrees = ($role === 'doctor') ? ($_POST['degrees'] ?? '') : null;
    $designation = ($role === 'doctor') ? ($_POST['designation'] ?? '') : null;
    $workplace = ($role === 'doctor') ? ($_POST['workplace'] ?? '') : null;

    if (empty($fullname) || empty($email) || empty($password_plain)) {
        $message = "<div class='msg-error'>All fields are required.</div>";
    } elseif ($role === 'doctor' && (empty($specialty) || empty($degrees) || empty($designation) || empty($workplace))) {
        $message = "<div class='msg-error'>All fields, including specialty, degrees, designation, and workplace, are required for doctors.</div>";
    } elseif ($password_plain !== $confirm_password) {
        $message = "<div class='msg-error'>Passwords do not match.</div>";
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $message = "<div class='msg-error'>An account with this email already exists.</div>";
        } else {
            $password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

            // Updated Prepared Statement to include all new doctor fields
            $stmt_insert = $conn->prepare("INSERT INTO users (fullname, email, password_hash, role, specialty, degrees, designation, workplace) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            // 'ssssssss' - 8 string parameters
            $stmt_insert->bind_param("ssssssss", $fullname, $email, $password_hash, $role, $specialty, $degrees, $designation, $workplace);

            if ($stmt_insert->execute()) {
                $message = "<div class='msg-success'>Account created successfully! You can now log in.</div>";
            } else {
                $message = "<div class='msg-error'>Error creating account: " . $stmt_insert->error . "</div>";
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}
$conn->close();

$role = $_GET['role'] ?? 'patient';
$login_link = 'login.php?role=' . urlencode($role);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor & Patient Portal - Sign Up</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Your existing styles */
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px 0; }
        .container { background-color: #ffffff; padding: 40px 50px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 100%; max-width: 420px; text-align: center; }
        h1 { margin-bottom: 10px; color: #2c3e50; font-size: 28px; font-weight: 600; }
        p { margin-bottom: 30px; color: #7f8c8d; font-size: 14px; }
        .role-selector { display: flex; margin-bottom: 20px; border: 1px solid #e0e0e0; border-radius: 50px; overflow: hidden; }
        .role-selector input[type="radio"] { display: none; }
        .role-selector label { flex: 1; padding: 12px 20px; cursor: pointer; transition: all 0.3s ease; font-weight: 500; color: #7f8c8d; }
        .role-selector input[type="radio"]:checked + label { background-color: #3498db; color: #ffffff; }
        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #34495e; font-size: 14px; }
        .input-group input, .input-group select { width: 100%; padding: 12px 15px; border: 1px solid #bdc3c7; border-radius: 8px; box-sizing: border-box; font-size: 16px; }
        .submit-btn { width: 100%; padding: 15px; background-color: #2ecc71; border: none; border-radius: 8px; color: white; font-size: 18px; font-weight: 600; cursor: pointer; }
        .form-toggle { margin-top: 30px; font-size: 14px; }
        .form-toggle .toggle-link { color: #3498db; font-weight: 600; text-decoration: none; }
        .msg-success { background: #e0ffe0; border: 1px solid green; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
        .msg-error { background: #ffe0e0; border: 1px solid red; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
    </style>
</head>
<body>
    <?php echo $message; ?>
    <div class="container">
        <h1>Create Account</h1>
        <form action="signup.php?role=<?php echo htmlspecialchars($role); ?>" method="POST">
            <div class="role-selector">
                <input type="radio" id="patient" name="role" value="patient" <?php echo ($role === 'patient') ? 'checked' : ''; ?>>
                <label for="patient">Patient</label>
                <input type="radio" id="doctor" name="role" value="doctor" <?php echo ($role === 'doctor') ? 'checked' : ''; ?>>
                <label for="doctor">Doctor</label>
            </div>
            
            <div id="doctor-fields" style="display: <?php echo ($role === 'doctor') ? 'block' : 'none'; ?>;">
                <div class="input-group">
                    <label for="specialty">Specialty</label>
                    <select id="specialty" name="specialty">
                        <option value="">-- Select Specialty --</option>
                        <option value="Cardiologist">Cardiologist</option>
                        <option value="Dermatologist">Dermatologist</option>
                        <option value="Neurologist">Neurologist</option>
                        <option value="Pediatrician">Pediatrician</option>
                        <option value="General Physician">General Physician</option>
                    </select>
                </div>
                <div class="input-group">
                    <label for="degrees">Degrees</label>
                    <input type="text" id="degrees" name="degrees" placeholder="e.g., MBBS, FCPS (Cardiology)">
                </div>
                <div class="input-group">
                    <label for="designation">Designation / Post</label>
                    <input type="text" id="designation" name="designation" placeholder="e.g., Senior Consultant">
                </div>
                <div class="input-group">
                    <label for="workplace">Current Workplace</label>
                    <input type="text" id="workplace" name="workplace" placeholder="e.g., City Medical College Hospital">
                </div>
            </div>
            <div class="input-group">
                <label for="fullname">Full Name</label>
                <input type="text" id="fullname" name="fullname" required>
            </div>
            <div class="input-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="input-group">
                <label for="confirm-password">Confirm Password</label>
                <input type="password" id="confirm-password" name="confirm-password" required>
            </div>
            <button type="submit" class="submit-btn">Sign Up</button>
        </form>
        <div class="form-toggle">
            <span>Already have an account?</span>
            <a href="<?php echo htmlspecialchars($login_link); ?>" class="toggle-link">Login</a>
        </div>
    </div>
    <script>
        // JS to reload page with new role param AND show/hide doctor fields
        document.getElementById("patient").addEventListener("change", function () {
            if (this.checked) { window.location.href = "signup.php?role=patient"; }
        });
        document.getElementById("doctor").addEventListener("change", function () {
            if (this.checked) { window.location.href = "signup.php?role=doctor"; }
        });
    </script>
</body>
</html>