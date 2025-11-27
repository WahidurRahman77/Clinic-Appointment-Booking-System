<?php
// START PHP DATABASE CONNECTION
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "portal_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Get Form Data
    $role = $_POST['role'] ?? 'patient';
    $fullname = $_POST['fullname'] ?? '';
    $email = $_POST['email'] ?? '';
    $password_plain = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm-password'] ?? '';
    
    // 2. Get Doctor Specifics (Only if role is doctor)
    $specialty = ($role === 'doctor') ? ($_POST['specialty'] ?? '') : null;
    $degrees = ($role === 'doctor') ? ($_POST['degrees'] ?? '') : null;
    $designation = ($role === 'doctor') ? ($_POST['designation'] ?? '') : null;
    $workplace = ($role === 'doctor') ? ($_POST['workplace'] ?? '') : null;

    // 3. Validations
    if (empty($fullname) || empty($email) || empty($password_plain)) {
        $message = "<div class='msg-error'>All fields are required.</div>";
    } elseif ($role === 'doctor' && (empty($specialty) || empty($degrees) || empty($designation) || empty($workplace))) {
        $message = "<div class='msg-error'>Please fill in all Doctor details.</div>";
    } elseif ($password_plain !== $confirm_password) {
        $message = "<div class='msg-error'>Passwords do not match.</div>";
    } else {
        // 4. Check Duplicate Email
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $message = "<div class='msg-error'>An account with this email already exists.</div>";
        } else {
            // 5. Create Account
            $password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

            // Insert
            $stmt_insert = $conn->prepare("INSERT INTO users (fullname, email, password_hash, role, specialty, degrees, designation, workplace) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("ssssssss", $fullname, $email, $password_hash, $role, $specialty, $degrees, $designation, $workplace);

            if ($stmt_insert->execute()) {
                $login_url = "login.php?role=" . $role;
                $message = "<div class='msg-success'>Account created! <a href='$login_url'>Login here</a>.</div>";
            } else {
                $message = "<div class='msg-error'>Error: " . $stmt_insert->error . "</div>";
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account | Medicare Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --primary: #007bff; --bg-light: #f4f7f6; --text-dark: #333; }
        
        * { box-sizing: border-box; }
        
        body { margin: 0; padding: 0; font-family: 'Poppins', sans-serif; background-color: var(--bg-light); height: 100vh; overflow: hidden; }
        
        /* Split Container */
        .split-container { display: flex; width: 100%; height: 100%; }
        
        /* Left Side: Image */
        .image-side { 
            flex: 1; 
            background: url('https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?q=80&w=2070&auto=format&fit=crop') no-repeat center center/cover; 
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
            overflow-y: auto; /* IMPORTANT: Enables scrolling within the form side */
        }
        
        .form-wrapper { width: 100%; max-width: 420px; padding: 20px 0; }

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
        .input-box input, .input-box select { 
            width: 100%; padding: 12px 15px 12px 40px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; 
            font-family: inherit; transition: 0.3s;
        }
        .input-box input:focus, .input-box select:focus { border-color: var(--primary); outline: none; }

        /* Doctor Section */
        #doctor-fields { display: none; background: #f8f9fa; padding: 20px; border-radius: 10px; border: 1px dashed #ced4da; margin-bottom: 20px; }
        .doc-title { color: var(--primary); font-weight: 600; margin-bottom: 15px; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }

        .btn-submit { 
            width: 100%; padding: 14px; background: var(--primary); color: white; border: none; border-radius: 8px; 
            font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: 0.3s; margin-top: 10px; 
        }
        .btn-submit:hover { background: #0056b3; }

        .msg-error { background: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 8px; text-align: center; margin-bottom: 20px; border: 1px solid #fecaca; }
        .msg-success { background: #d1e7dd; color: #0f5132; padding: 15px; border-radius: 8px; text-align: center; margin-bottom: 20px; border: 1px solid #badbcc; }
        .msg-success a { font-weight: 700; color: #0f5132; }

        .login-link { text-align: center; margin-top: 25px; color: #666; }
        .login-link a { color: var(--primary); text-decoration: none; font-weight: 600; }

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
            <h2>Start Your Journey.</h2>
            <p>Join the Medicare Portal to manage your health schedule or connect with patients seamlessly.</p>
        </div>
    </div>
    
    <div class="form-side">
        <div class="form-wrapper">
            <div class="header">
                <h1>Create Account</h1>
                <p>Please fill in your details</p>
            </div>

            <?php echo $message; ?>

            <form action="signup.php" method="POST">
                
                <div class="role-switch">
                    <input type="radio" id="role_patient" name="role" value="patient" checked onchange="toggleFields()">
                    <label for="role_patient">Patient</label>
                    
                    <input type="radio" id="role_doctor" name="role" value="doctor" onchange="toggleFields()">
                    <label for="role_doctor">Doctor</label>
                </div>
                
                <div class="input-group">
                    <label>Full Name</label>
                    <div class="input-box">
                        <i class="fas fa-user"></i>
                        <input type="text" name="fullname" placeholder="John Doe" required>
                    </div>
                </div>

                <div class="input-group">
                    <label>Email Address</label>
                    <div class="input-box">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="name@example.com" required>
                    </div>
                </div>

                <div id="doctor-fields">
                    <div class="doc-title"><i class="fas fa-user-md"></i> Professional Details</div>
                    
                    <div class="input-group">
                        <label>Specialty</label>
                        <div class="input-box">
                            <i class="fas fa-heartbeat"></i>
                            <select name="specialty">
                                <option value="">Select Specialty</option>
                                <option value="Cardiologist">Cardiologist</option>
                                <option value="Dermatologist">Dermatologist</option>
                                <option value="Neurologist">Neurologist</option>
                                <option value="Pediatrician">Pediatrician</option>
                                <option value="General Physician">General Physician</option>
                            </select>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Degrees</label>
                        <div class="input-box">
                            <i class="fas fa-graduation-cap"></i>
                            <input type="text" name="degrees" placeholder="MBBS, FCPS">
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Designation</label>
                        <div class="input-box">
                            <i class="fas fa-id-badge"></i>
                            <input type="text" name="designation" placeholder="Senior Consultant">
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Workplace</label>
                        <div class="input-box">
                            <i class="fas fa-hospital"></i>
                            <input type="text" name="workplace" placeholder="City Medical College">
                        </div>
                    </div>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <div class="input-box">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="Create password" required>
                    </div>
                </div>

                <div class="input-group">
                    <label>Confirm Password</label>
                    <div class="input-box">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="confirm-password" placeholder="Confirm password" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Sign Up</button>
            </form>

            <div class="login-link">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </div>
    </div>
</div>

<script>
    // Simple JavaScript to toggle doctor fields without reloading
    function toggleFields() {
        var doctorRadio = document.getElementById('role_doctor');
        var doctorFields = document.getElementById('doctor-fields');
        
        if (doctorRadio.checked) {
            doctorFields.style.display = 'block';
        } else {
            doctorFields.style.display = 'none';
        }
    }
</script>

</body>
</html>
