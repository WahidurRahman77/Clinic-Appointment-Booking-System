<?php
// START SESSION
session_start();

// Redirect if logged in
if (isset($_SESSION["user_id"])) {
    if ($_SESSION["role"] === 'doctor') { header("Location: doctor_dashboard.php"); } 
    else { header("Location: patient_dashboard.php"); }
    exit;
}

// DB Connection
$servername = "localhost"; $username = "root"; $password = ""; $dbname = "portal_db";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role = $_POST['role'] ?? 'patient';
    $email = $_POST['email'] ?? '';
    $password_plain = $_POST['password'] ?? '';

    if (empty($email) || empty($password_plain)) {
        $message = "<div class='msg-error'>Please enter email and password.</div>";
    } else {
        $stmt = $conn->prepare("SELECT id, fullname, password_hash, role, specialty, degrees, designation, workplace FROM users WHERE email = ? AND role = ?");
        $stmt->bind_param("ss", $email, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password_plain, $user['password_hash'])) {
                $_SESSION["user_id"] = $user['id'];
                $_SESSION["fullname"] = $user['fullname'];
                $_SESSION["role"] = $user['role'];

                if ($user['role'] === 'doctor') {
                    $_SESSION["specialty"] = $user['specialty'];
                    $_SESSION["degrees"] = $user['degrees'];
                    $_SESSION["designation"] = $user['designation'];
                    $_SESSION["workplace"] = $user['workplace'];
                    header("Location: doctor_dashboard.php");
                } else {
                    header("Location: patient_dashboard.php");
                }
                exit;
            } else {
                $message = "<div class='msg-error'>Invalid password.</div>";
            }
        } else {
            $message = "<div class='msg-error'>No account found with this email and role.</div>";
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | Medicare Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --primary: #007bff; --bg-light: #f4f7f6; --text-dark: #333; }
        
        * { box-sizing: border-box; }
        
        body { margin: 0; padding: 0; font-family: 'Poppins', sans-serif; background-color: var(--bg-light); height: 100vh; overflow: hidden; }
        
        /* Split Layout */
        .split-container { display: flex; width: 100%; height: 100%; }
        
        /* Left Side: Image */
        .image-side { 
            flex: 1.2; 
            background: url('https://images.unsplash.com/photo-1505751172876-fa1923c5c528?q=80&w=1920&auto=format&fit=crop') no-repeat center center/cover; 
            position: relative; 
        }
        .image-overlay {
            position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,123,255,0.9), rgba(0,0,0,0.1));
            display: flex; flex-direction: column; justify-content: flex-end; padding: 60px; color: white;
        }
        .image-overlay h2 { font-size: 2.5rem; margin: 0 0 10px 0; font-weight: 700; }
        .image-overlay p { font-size: 1.1rem; opacity: 0.9; max-width: 500px; }

        /* Right Side: Form */
        .form-side { 
            flex: 1; 
            background: white; 
            padding: 40px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; /* Vertically center login form */
            overflow-y: auto; /* Allow scroll on small screens */
        }
        
        .form-wrapper { width: 100%; max-width: 400px; }

        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { font-size: 2rem; color: var(--text-dark); margin: 0; }
        .header p { color: #666; margin-top: 5px; }

        /* Role Switcher */
        .role-switch { display: flex; background: #f1f3f5; padding: 5px; border-radius: 50px; margin-bottom: 25px; }
        .role-switch input { display: none; }
        .role-switch label { 
            flex: 1; text-align: center; padding: 10px; cursor: pointer; border-radius: 50px; 
            font-weight: 500; color: #666; transition: 0.3s; 
        }
        .role-switch input:checked + label { background: var(--primary); color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

        /* Inputs */
        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; font-weight: 500; margin-bottom: 8px; color: #444; font-size: 0.9rem; }
        .input-box { position: relative; }
        .input-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; }
        .input-box input { 
            width: 100%; padding: 12px 15px 12px 40px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; 
            font-family: inherit; transition: 0.3s;
        }
        .input-box input:focus { border-color: var(--primary); outline: none; }

        .btn-submit { 
            width: 100%; padding: 14px; background: var(--primary); color: white; border: none; border-radius: 8px; 
            font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: 0.3s; margin-top: 10px; 
        }
        .btn-submit:hover { background: #0056b3; }

        .extra-links { display: flex; justify-content: space-between; margin-top: 15px; font-size: 0.9rem; }
        .extra-links a { text-decoration: none; color: var(--primary); font-weight: 500; }

        .msg-error { background: #fee2e2; color: #b91c1c; padding: 12px; border-radius: 6px; text-align: center; margin-bottom: 20px; border: 1px solid #fecaca; }
        .msg-success { background: #d1e7dd; color: #0f5132; padding: 12px; border-radius: 6px; text-align: center; margin-bottom: 20px; border: 1px solid #badbcc; }

        /* Mobile */
        @media (max-width: 900px) { 
            .image-side { display: none; } 
            .form-side { width: 100%; padding: 20px; }
        }
    </style>
</head>
<body>

<div class="split-container">
    <div class="image-side">
        <div class="image-overlay">
            <h2>Welcome Back.</h2>
            <p>Access your dashboard to view appointments and manage healthcare records securely.</p>
        </div>
    </div>
    
    <div class="form-side">
        <div class="form-wrapper">
            <div class="header">
                <h1>Medicare Login</h1>
                <p>Please enter your credentials</p>
            </div>

            <?php echo $message; ?>

            <form action="login.php" method="POST">
                
                <div class="role-switch">
                    <input type="radio" id="login_patient" name="role" value="patient" checked>
                    <label for="login_patient">Patient</label>
                    
                    <input type="radio" id="login_doctor" name="role" value="doctor">
                    <label for="login_doctor">Doctor</label>
                </div>

                <div class="input-group">
                    <label>Email Address</label>
                    <div class="input-box">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="name@example.com" required>
                    </div>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <div class="input-box">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="Enter password" required>
                    </div>
                </div>

                <div class="extra-links">
                    <a href="signup.php">Create Account</a>
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-submit">Login</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
