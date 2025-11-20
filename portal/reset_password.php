<?php
session_start();

$message = '';
$token = $_GET['token'] ?? '';
$token_is_valid = false;

if (empty($token)) {
    header("Location: login.php");
    exit;
}

// 1. Database Connection
$conn = new mysqli("localhost", "root", "", "portal_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. Hash the token from the URL to match the one in the DB
$token_hash = hash('sha256', $token);

// 3. Check if the token is valid and not expired
$stmt = $conn->prepare("SELECT email, expires FROM password_resets WHERE token = ?");
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $email = $row['email'];
    
    if (time() > $row['expires']) {
        // Token has expired
        $message = "<div class='msg-error'>This password reset link has expired. Please request a new one.</div>";
        // Clean up expired token
        $conn->execute_query("DELETE FROM password_resets WHERE token = ?", [$token_hash]);
    } else {
        // Token is valid
        $token_is_valid = true;
    }
} else {
    // Token is invalid
    $message = "<div class='msg-error'>Invalid password reset link.</div>";
}

// 4. Handle the form submission (when the user enters a new password)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $posted_token_hash = $_POST['token_hash']; // Get the hash from the hidden field
    
    if (empty($password) || empty($confirm_password)) {
        $message = "<div class='msg-error'>Please fill in both password fields.</div>";
        $token_is_valid = true; // Keep the form visible
    } elseif ($password !== $confirm_password) {
        $message = "<div class='msg-error'>Passwords do not match.</div>";
        $token_is_valid = true; // Keep the form visible
    } elseif ($posted_token_hash !== $token_hash) {
        // Security check
        $message = "<div class='msg-error'>Invalid token. Please try again.</div>";
        $token_is_valid = false;
    } else {
        // All checks passed. Update the password.
        
        // We need the email again, let's re-fetch it just to be safe
        $stmt_check = $conn->prepare("SELECT email FROM password_resets WHERE token = ?");
        $stmt_check->bind_param("s", $token_hash);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows === 1) {
            $email = $result_check->fetch_assoc()['email'];
            
            // Hash the new password
            $new_password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Update the user's password in the `users` table
            $stmt_update = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
            $stmt_update->bind_param("ss", $new_password_hash, $email);
            $stmt_update->execute();
            
            // Delete the token from the `password_resets` table
            $conn->execute_query("DELETE FROM password_resets WHERE email = ?", [$email]);
            
            // Redirect to login with a success message
            header("Location: login.php?status=reset_success");
            exit;
        } else {
            $message = "<div class='msg-error'>Your reset token was not found. It may have expired.</div>";
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { background-color: #ffffff; padding: 40px 50px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); width: 100%; max-width: 420px; box-sizing: border-box; text-align: center; }
        h1 { margin-bottom: 10px; color: #2c3e50; font-size: 28px; font-weight: 600; }
        p { margin-bottom: 30px; color: #7f8c8d; font-size: 14px; }
        .input-group { margin-bottom: 25px; text-align: left; }
        .input-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #34495e; font-size: 14px; }
        .input-group input { width: 100%; padding: 12px 15px; border: 1px solid #bdc3c7; border-radius: 8px; box-sizing: border-box; font-size: 16px; }
        .submit-btn { width: 100%; padding: 15px; background-color: #2ecc71; border: none; border-radius: 8px; color: white; font-size: 18px; font-weight: 600; cursor: pointer; }
        .back-link { margin-top: 30px; font-size: 14px; }
        .back-link a { color: #3498db; font-weight: 600; text-decoration: none; }
        .msg-success { background: #e0f8e9; border: 1px solid #2ecc71; color: #27ae60; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
        .msg-error { background: #ffebee; border: 1px solid #e74c3c; color: #c0392b; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
    </style>
</head>
<body>

    <?php echo $message; ?>

    <div class="container">
        <?php if ($token_is_valid): ?>
            <h1>Set New Password</h1>
            <p>Please enter your new password below.</p>
            
            <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST">
                <input type="hidden" name="token_hash" value="<?php echo htmlspecialchars($token_hash); ?>">
                
                <div class="input-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="input-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" name="reset_password" class="submit-btn">Reset Password</button>
            </form>
            
        <?php else: ?>
            <div class="back-link">
                <a href="login.php">Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>