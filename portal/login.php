<?php
// START A SESSION
// This must be the very first thing on the page, before any HTML.
session_start();

// If user is already logged in (e.g., they hit 'back' button), send them to the correct dashboard
if (isset($_SESSION["user_id"])) {
    if ($_SESSION["role"] === 'doctor') {
        header("Location: doctor_dashboard.php");
    } else {
        header("Location: patient_dashboard.php");
    }
    exit;
}

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

$message = ''; // To store login error messages
$login_success = false; // Flag to trigger JavaScript redirect
$redirect_url = ''; // To store the correct dashboard URL

// NEW: Check for password reset success message
if (isset($_GET['status']) && $_GET['status'] === 'reset_success') {
    $message = "<div class='msg-success'>Password has been reset successfully. You can now log in.</div>";
}

// PHP Backend Logic: This block runs when the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $role = $_POST['role'] ?? 'patient';
    $email = $_POST['email'] ?? '';
    $password_plain = $_POST['password'] ?? '';

    if (empty($email) || empty($password_plain)) {
        $message = "<div class='msg-error'>Email and password are required.</div>";
    } else {
        // Find the user in the database
        $stmt = $conn->prepare("SELECT id, fullname, password_hash, role, specialty, degrees, designation, workplace FROM users WHERE email = ? AND role = ?");
        $stmt->bind_param("ss", $email, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // User found. Now, VERIFY the password.
            if (password_verify($password_plain, $user['password_hash'])) {
                // Password is correct!
                // Store user data in the session to "log them in"
                $_SESSION["user_id"] = $user['id'];
                $_SESSION["fullname"] = $user['fullname'];
                $_SESSION["role"] = $user['role'];

                // Store doctor-specific info in session
                if ($user['role'] === 'doctor') {
                    $_SESSION["specialty"] = $user['specialty'];
                    $_SESSION["degrees"] = $user['degrees'];
                    $_SESSION["designation"] = $user['designation'];
                    $_SESSION["workplace"] = $user['workplace'];
                    $redirect_url = 'doctor_dashboard.php';
                } else {
                    $redirect_url = 'patient_dashboard.php';
                }
                
                // SET THE SUCCESS FLAG
                $login_success = true;
                
            } else {
                // Password was incorrect
                $message = "<div class='msg-error'>Invalid email or password.</div>";
            }
        } else {
            // No user found with that email and role
            $message = "<div class='msg-error'>Invalid email or password.</div>";
        }
        $stmt->close();
    }
}
$conn->close();

// Get the role from URL to set the checked button and signup link
$role = $_GET['role'] ?? 'patient';
$signup_link = 'signup.php?role=' . urlencode($role);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor & Patient Portal - Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 100vh; margin: 0; color: #333; padding: 20px 0; box-sizing: border-box; }
        .container { background-color: #ffffff; padding: 40px 50px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); width: 100%; max-width: 420px; box-sizing: border-box; text-align: center; }
        .form-header h1 { margin-bottom: 10px; color: #2c3e50; font-size: 28px; font-weight: 600; }
        .form-header p { margin-bottom: 30px; color: #7f8c8d; font-size: 14px; }
        .role-selector { display: flex; margin-bottom: 30px; border: 1px solid #e0e0e0; border-radius: 50px; overflow: hidden; }
        .role-selector input[type="radio"] { display: none; }
        .role-selector label { flex: 1; padding: 12px 20px; cursor: pointer; transition: all 0.3s ease; font-weight: 500; color: #7f8c8d; }
        .role-selector input[type="radio"]:checked + label { background-color: #3498db; color: #ffffff; box-shadow: 0 2px 10px rgba(52, 152, 219, 0.4); }
        .input-group { position: relative; margin-bottom: 25px; text-align: left; }
        .input-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #34495e; font-size: 14px; }
        .input-group input { width: 100%; padding: 12px 15px; border: 1px solid #bdc3c7; border-radius: 8px; box-sizing: border-box; font-size: 16px; }
        
        /* NEW: Style for forgot password link */
        .input-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .forgot-password a {
            font-size: 13px;
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }
        .forgot-password a:hover {
            text-decoration: underline;
        }
        /* Adjust password input-group margin */
        #password-group {
            margin-bottom: 10px;
        }

        .submit-btn { width: 100%; padding: 15px; background-color: #2ecc71; border: none; border-radius: 8px; color: white; font-size: 18px; font-weight: 600; cursor: pointer; transition: background-color 0.3s; margin-top: 10px; }
        .submit-btn:hover { background-color: #27ae60; }
        .form-toggle { margin-top: 30px; font-size: 14px; color: #7f8c8d; }
        .form-toggle .toggle-link { color: #3498db; font-weight: 600; text-decoration: none; }
        
        /* Message styles */
        .msg-error { background: #ffebee; border: 1px solid #e74c3c; color: #c0392b; padding: 15px; text-align: center; margin-bottom: 20px; border-radius: 8px; }
        .msg-success { background: #e0f8e9; border: 1px solid #2ecc71; color: #27ae60; padding: 15px; text-align: center; margin-bottom: 20px; border-radius: 8px; }
    </style>
</head>
<body>

    <?php echo $message; ?>

    <div class="container" id="form-container">
        <div class="form-header">
            <h1 id="form-title">Login</h1>
            <p id="form-subtitle">Welcome back! Please enter your details.</p>
        </div>

        <form id="auth-form" action="login.php?role=<?php echo htmlspecialchars($role); ?>" method="POST">
            <div class="role-selector">
                <input type="radio" id="patient" name="role" value="patient" <?php echo ($role === 'patient') ? 'checked' : ''; ?>>
                <label for="patient">Patient</label>
                
                <input type="radio" id="doctor" name="role" value="doctor" <?php echo ($role === 'doctor') ? 'checked' : ''; ?>>
                <label for="doctor">Doctor</label>
            </div>

            <div class="input-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="e.g., name@example.com" required>
            </div>

            <div class="input-group" id="password-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            
            <div class="input-options">
                <div></div>
                <div class="forgot-password">
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>
            </div>
            <button type="submit" class="submit-btn" id="submit-button">Login</button>
        </form>

        <div class="form-toggle">
            <span id="toggle-text">Don't have an account?</span>
            <a id="toggle-link" class="toggle-link" href="<?php echo htmlspecialchars($signup_link); ?>">Sign Up</a>
        </div>
    </div>

    <script>
    // JS to reload page with new role param
    document.getElementById("patient").addEventListener("change", function () {
        if (this.checked) { window.location.href = "login.php?role=patient"; }
    });

    document.getElementById("doctor").addEventListener("change", function () {
        if (this.checked) { window.location.href = "login.php?role=doctor"; }
    });
    
    
    // --- REDIRECT LOGIC ---
    <?php if ($login_success === true): ?>
        // If PHP login was successful, run this JavaScript
        
        // 1. Open the correct dashboard in a brand new tab
        window.open('<?php echo $redirect_url; ?>', '_blank');
        
        // 2. Redirect the original login tab (this one) back to a clean login page
        window.location.href = 'login.php'; 
    <?php endif; ?>
    
    </script>
</body>
</html>