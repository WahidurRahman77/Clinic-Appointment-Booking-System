<?php
session_start();

$message = '';
$show_link = false;
$reset_link = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_reset'])) {
    
    // 1. Database Connection
    $conn = new mysqli("localhost", "root", "", "portal_db");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $email = $_POST['email'];

    // 2. Check if the email exists in the users table
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows === 1) {
        // 3. User exists, generate a token
        $token = bin2hex(random_bytes(32)); // Secure random token
        $token_hash = hash('sha256', $token); // Hash the token for DB storage
        $expires = time() + 3600; // Token expires in 1 hour

        // 4. Delete any old tokens for this email
        $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt_delete->bind_param("s", $email);
        $stmt_delete->execute();

        // 5. Store the new token hash in the database
        $stmt_insert = $conn->prepare("INSERT INTO password_resets (email, token, expires) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("ssi", $email, $token_hash, $expires);
        $stmt_insert->execute();

        // 6. SIMULATE SENDING EMAIL:
        // In a real app, you would email this link. For this project, we will display it.
        $reset_link = "http://localhost/portal/reset_password.php?token=" . $token;
        $show_link = true;
    }
    
    // IMPORTANT: Always show a generic message to prevent user enumeration
    // This tells the user we've processed the request, whether the email existed or not.
    $message = "<div class='msg-success'>If an account with that email exists, a password reset link has been generated.</div>";

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { background-color: #ffffff; padding: 40px 50px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); width: 100%; max-width: 420px; box-sizing: border-box; text-align: center; }
        h1 { margin-bottom: 10px; color: #2c3e50; font-size: 28px; font-weight: 600; }
        p { margin-bottom: 30px; color: #7f8c8d; font-size: 14px; }
        .input-group { margin-bottom: 25px; text-align: left; }
        .input-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #34495e; font-size: 14px; }
        .input-group input { width: 100%; padding: 12px 15px; border: 1px solid #bdc3c7; border-radius: 8px; box-sizing: border-box; font-size: 16px; }
        .submit-btn { width: 100%; padding: 15px; background-color: #3498db; border: none; border-radius: 8px; color: white; font-size: 18px; font-weight: 600; cursor: pointer; }
        .back-link { margin-top: 30px; font-size: 14px; }
        .back-link a { color: #3498db; font-weight: 600; text-decoration: none; }
        .msg-success { background: #e0f8e9; border: 1px solid #2ecc71; color: #27ae60; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
        .msg-error { background: #ffebee; border: 1px solid #e74c3c; color: #c0392b; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
        .demo-link-box { margin-top: 20px; padding: 15px; background: #fffbe6; border: 1px solid #f39c12; border-radius: 8px; text-align: left; }
        .demo-link-box strong { color: #c0392b; }
        .demo-link-box p { font-size: 12px; line-height: 1.5; color: #333; }
        .demo-link-box a { color: #3498db; word-break: break-all; }
    </style>
</head>
<body>
    
    <?php echo $message; ?>

    <div class="container">
        <h1>Forgot Password</h1>
        <p>Enter your email to receive a password reset link.</p>

        <form action="forgot_password.php" method="POST">
            <div class="input-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type_-"submit" name="request_reset" class="submit-btn">Send Reset Link</button>
        </form>

        <div class="back-link">
            <a href="login.php">Back to Login</a>
        </div>

        <?php if ($show_link): ?>
            <div class="demo-link-box">
                <strong>PROJECT DEMO ONLY:</strong>
                <p>In a real application, this link would be emailed. Please click the link below to continue:</p>
                <a href="<?php echo htmlspecialchars($reset_link); ?>"><?php echo htmlspecialchars($reset_link); ?></a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>