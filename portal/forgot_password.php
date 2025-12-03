<?php
// forgot_password.php
session_start();

$message = '';

// YOUR BREVO API KEY HERE
$api_key = 'Your API KEY';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_otp'])) {
    
    $conn = new mysqli("localhost", "root", "", "portal_db");
    $email = $_POST['email'];

    // 1. Check if email exists
    $stmt_check = $conn->prepare("SELECT id, fullname FROM users WHERE email = ?");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // 2. Generate OTP
        $otp = rand(100000, 999999);
        $expires = time() + 300; 

        // 3. Save to DB
        $conn->execute_query("DELETE FROM password_resets WHERE email = ?", [$email]);
        $stmt_insert = $conn->prepare("INSERT INTO password_resets (email, token, expires) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("ssi", $email, $otp, $expires);
        $stmt_insert->execute();

        // 4. SEND EMAIL VIA API (cURL)
        $url = 'https://api.brevo.com/v3/smtp/email';
        
        $data = [
            'sender' => ['name' => 'Medicare Portal', 'email' => 'Your Mail'],
            'to' => [['email' => $email, 'name' => $user['fullname']]],
            'subject' => 'Password Reset OTP',
            'htmlContent' => "
                <div style='font-family: Arial, sans-serif; padding: 20px; background: #f4f4f4;'>
                    <div style='background: white; padding: 20px; border-radius: 8px; text-align: center;'>
                        <h2 style='color: #007bff;'>Medicare Portal</h2>
                        <p>You requested a password reset. Your OTP is:</p>
                        <h1 style='letter-spacing: 5px; background: #eee; padding: 10px; display: inline-block;'>$otp</h1>
                        <p>This code expires in 5 minutes.</p>
                    </div>
                </div>"
        ];

        $ch = curl_init(); curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Add this line!
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'api-key: ' . $api_key,
            'content-type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 201) {
            $_SESSION['reset_email'] = $email;
            header("Location: verify_otp.php");
            exit;
        } else {
            $message = "<div class='msg-error'>Error sending email. API Error.</div>";
        }

    } else {
        $message = "<div class='msg-error'>If that email exists, we have sent an OTP.</div>";
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
        .msg-error { background: #ffebee; border: 1px solid #e74c3c; color: #c0392b; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
        .back-link { margin-top: 20px; } .back-link a { color: #3498db; text-decoration: none; }
    </style>
</head>
<body>
    <?php echo $message; ?>
    <div class="container">
        <h1>Forgot Password</h1>
        <p>Enter your email to receive a 6-digit OTP.</p>
        <form action="forgot_password.php" method="POST">
            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" required>
            </div>
            <button type="submit" name="send_otp" class="submit-btn">Send OTP</button>
        </form>
        <div class="back-link"><a href="login.php">Back to Login</a></div>
    </div>
</body>
</html>
