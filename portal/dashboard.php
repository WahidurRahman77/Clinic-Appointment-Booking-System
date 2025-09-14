<?php
// Always start the session on any page that needs session data
session_start();

// Check if the user is logged in. 
// If not, redirect them back to the login page.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Get user data from the session
$fullname = $_SESSION["fullname"];
$role = $_SESSION["role"];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f7f6;
            margin: 0;
            padding: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            box-sizing: border-box;
        }
        .dashboard-container {
            background: #fff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 600px;
        }
        h1 {
            color: #2c3e50;
            font-weight: 600;
        }
        p {
            font-size: 18px;
            color: #34495e;
        }
        .role-badge {
            background-color: #3498db;
            color: white;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 500;
            text-transform: capitalize;
        }
        .logout-link {
            display: inline-block;
            margin-top: 30px;
            padding: 12px 30px;
            background-color: #e74c3c;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        .logout-link:hover {
            background-color: #c0392b;
        }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        <h1>Welcome, <?php echo htmlspecialchars($fullname); ?>!</h1>
        <p>You are successfully logged in.</p>
        <p>Your role is: <span class="role-badge"><?php echo htmlspecialchars($role); ?></span></p>
        
        <?php if ($role === 'doctor'): ?>
            <p>Go to your <a href="#">Doctor Controls</a>.</p>
        <?php else: ?>
            <p>Go to your <a href="#">Patient Appointments</a>.</p>
        <?php endif; ?>

        <a href="logout.php" class="logout-link">Logout</a>
    </div>

</body>
</html>