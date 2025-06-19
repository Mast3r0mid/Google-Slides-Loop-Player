<?php
// login.php

session_start(); // Start the session at the very beginning

$username = 'admin'; // <--- CHANGE THIS TO A SECURE USERNAME

$password = '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918'; // <--- CHANGE THIS TO A STRONG PASSWORD ,
//Default Password is "admin" SHA256
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_username = $_POST['username'] ?? '';
    $input_password = $_POST['password'] ?? '';
	$hashedPassword = hash("sha256", $input_password);
	
    // Basic authentication: 
    if ($input_username === $username && $hashedPassword === $password) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        header('Location: edit_presentations.php'); // Redirect to the protected page
        exit();
    } else {
        $error_message = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login to Edit Presentations</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .login-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            width: 350px;
            text-align: center;
        }
        h2 { color: #333; margin-bottom: 25px; }
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: bold;
        }
        input[type="text"],
        input[type="password"] {
            width: calc(100% - 20px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            cursor: pointer;
            transition: background-color 0.2s ease;
            margin-top: 20px;
        }
        button:hover {
            background-color: #0056b3;
        }
        .error-message {
            color: #dc3545;
            margin-top: 15px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login to Edit Presentations</h2>
        <?php if ($error_message): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>