<?php
// verify_otp.php
session_start();

// Security: If they haven't sent an OTP, kick them back
if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit;
}

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_otp'])) {
    
    $conn = new mysqli("localhost", "root", "", "portal_db");
    
    $email = $_SESSION['reset_email'];
    $entered_otp = $_POST['otp'];

    // Check DB for this email and OTP
    $stmt = $conn->prepare("SELECT expires FROM password_resets WHERE email = ? AND token = ?");
    $stmt->bind_param("ss", $email, $entered_otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (time() < $row['expires']) {
            // OTP is valid and not expired
            $_SESSION['otp_verified'] = true; // Mark session as verified
            header("Location: reset_password.php");
            exit;
        } else {
            $message = "<div class='msg-error'>OTP has expired. Please try again.</div>";
        }
    } else {
        $message = "<div class='msg-error'>Invalid OTP. Please check your email.</div>";
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { background-color: #ffffff; padding: 40px 50px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); width: 100%; max-width: 420px; box-sizing: border-box; text-align: center; }
        h1 { margin-bottom: 10px; color: #2c3e50; font-size: 28px; font-weight: 600; }
        p { margin-bottom: 30px; color: #7f8c8d; font-size: 14px; }
        .input-group { margin-bottom: 25px; text-align: left; }
        .input-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #34495e; font-size: 14px; }
        .input-group input { width: 100%; padding: 12px 15px; border: 1px solid #bdc3c7; border-radius: 8px; box-sizing: border-box; font-size: 24px; text-align: center; letter-spacing: 5px; font-weight: bold; }
        .submit-btn { width: 100%; padding: 15px; background-color: #28a745; border: none; border-radius: 8px; color: white; font-size: 18px; font-weight: 600; cursor: pointer; transition: 0.3s; }
        .submit-btn:hover { background-color: #218838; }
        .msg-error { background: #ffebee; border: 1px solid #e74c3c; color: #c0392b; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
    </style>
</head>
<body>
    <?php echo $message; ?>
    <div class="container">
        <h1>Verify OTP</h1>
        <p>An OTP has been sent to <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong></p>
        <form action="verify_otp.php" method="POST">
            <div class="input-group">
                <label>Enter 6-Digit Code</label>
                <input type="text" name="otp" maxlength="6" required placeholder="XXXXXX" autocomplete="off">
            </div>
            <button type="submit" name="verify_otp" class="submit-btn">Verify Code</button>
        </form>
    </div>
</body>
</html>