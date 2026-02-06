<?php
require 'db.php'; // your database connection

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    // Password rule: at least 1 uppercase + 1 special char
    if (!preg_match('/^(?=.*[A-Z])(?=.*[!@#$%^&*]).{8,}$/', $new_password)) {
        $message = "Password must have 1 uppercase and 1 special character, minimum 8 characters.";
    } else {
        // Hash new password
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed, $email);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = "✅ Password successfully reset. You can now <a href='Login-Register.php'>login</a>.";
        } else {
            $message = "❌ No account found with that email.";
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: linear-gradient(135deg, #0073e6, #005bb5);
      display: flex; align-items: center; justify-content: center;
      height: 100vh; margin: 0; color: white;
    }
    .box {
      background: #fff; color: #333;
      padding: 2rem; border-radius: 10px;
      box-shadow: 0 0 10px rgba(0,0,0,.3);
      width: 320px; text-align: center;
    }
    input {
      width: 100%; padding: 10px; margin: 10px 0;
      border: 1px solid #ccc; border-radius: 5px;
    }
    button {
      width: 100%; padding: 10px; border: none;
      background: #0073e6; color: #fff; border-radius: 5px;
      cursor: pointer;
    }
    button:hover { background: #005bb5; }
    .msg { margin-top: 15px; font-size: 14px; }
  </style>
</head>
<body>
  <div class="box">
    <h2>Reset Password</h2>
    <form method="post">
      <input type="email" name="email" placeholder="Enter your email" required>
      <input type="password" name="new_password" placeholder="New Password" required>
      <button type="submit">Reset Password</button>
    </form>
    <p class="msg"><?= $message ?></p>
  </div>
</body>
</html>
