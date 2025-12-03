<?php
// reset_password.php
session_start();

// Security: User cannot be here unless OTP is verified in the session
// If we don't check this, anyone could just type "reset_password.php" and change a password!
if (!isset($_SESSION['otp_verified']) || !isset($_SESSION['reset_email'])) {
    header("Location: login.php");
    exit;
}

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    
    $conn = new mysqli("localhost", "root", "", "portal_db");
    
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = $_SESSION['reset_email'];

    if (strlen($password) < 6) {
        $message = "<div class='msg-error'>Password must be at least 6 characters long.</div>";
    } elseif ($password !== $confirm_password) {
        $message = "<div class='msg-error'>Passwords do not match.</div>";
    } else {
        // Update Password
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        $stmt->bind_param("ss", $new_hash, $email);
        
        if ($stmt->execute()) {
            // Cleanup: Remove the OTP from DB and clear the session
            $conn->execute_query("DELETE FROM password_resets WHERE email = ?", [$email]);
            
            // Unset specific session keys, but destroy session entirely to be safe
            session_unset();
            session_destroy();
            
            // Redirect to login with success flag
            header("Location: login.php?status=reset_success");
            exit;
        } else {
            $message = "<div class='msg-error'>Error updating password. Please try again.</div>";
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set New Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { background-color: #ffffff; padding: 40px 50px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); width: 100%; max-width: 420px; box-sizing: border-box; text-align: center; }
        h1 { margin-bottom: 10px; color: #2c3e50; font-size: 28px; font-weight: 600; }
        p { margin-bottom: 30px; color: #7f8c8d; font-size: 14px; }
        .input-group { margin-bottom: 25px; text-align: left; }
        .input-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #34495e; font-size: 14px; }
        .input-group input { width: 100%; padding: 12px 15px; border: 1px solid #bdc3c7; border-radius: 8px; box-sizing: border-box; font-size: 16px; }
        .submit-btn { width: 100%; padding: 15px; background-color: #007bff; border: none; border-radius: 8px; color: white; font-size: 18px; font-weight: 600; cursor: pointer; transition: 0.3s; }
        .submit-btn:hover { background-color: #0056b3; }
        .msg-error { background: #ffebee; border: 1px solid #e74c3c; color: #c0392b; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
    </style>
</head>
<body>
    <?php echo $message; ?>
    <div class="container">
        <h1>Set New Password</h1>
        <p>Create a strong password for your account.</p>
        <form action="reset_password.php" method="POST">
            <div class="input-group">
                <label>New Password</label>
                <input type="password" name="password" required placeholder="Enter new password">
            </div>
            <div class="input-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required placeholder="Confirm new password">
            </div>
            <button type="submit" name="reset_password" class="submit-btn">Reset Password</button>
        </form>
    </div>
</body>
</html>
