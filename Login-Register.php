

<?php



session_start();
require 'db.php';



// Remembered email (safe default)
$remembered_email = $_COOKIE['remember_email'] ?? '';

$message = '';




    

/* ---------- Registration Logic ---------- */
if (isset($_POST['register'])) {
    $name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password_plain = trim($_POST['password']);

    // --- Validate email format ---
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "❌ Please enter a valid email address.";
    }
    // --- Validate password complexity ---
    elseif (
        strlen($password_plain) !== 8 ||                // Must be exactly 8 characters
        !preg_match('/[A-Z]/', $password_plain) ||      // Must contain at least one uppercase letter
        !preg_match('/[\W_]/', $password_plain)         // Must contain at least one special character
    ) {
        $message = "❌ Password must be exactly 8 characters long, include at least one uppercase letter, and one special character.";
    }
    else {
        // --- Hash password only if valid ---
        $password = password_hash($password_plain, PASSWORD_BCRYPT);

        // --- Attempt to insert user ---
        try {
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $email, $password);
            $stmt->execute();
            $message = "✅ Registration successful! You can now log in.";
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                $message = "⚠️ That email is already registered. Please log in instead.";
            } else {
                $message = "⚠️ Something went wrong. Please try again.";
            }
        }
    }
}

/* ---------- Login Logic ---------- */
if (isset($_POST['login'])) {

    $email    = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

    $remember = isset($_POST['remember_me']);

    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "❌ Please enter a valid email address.";
    }
    // Password validation (KEEPING YOUR RULES)
    elseif (
        strlen($password) !== 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[\W_]/', $password)
    ) {
        $message = "❌ Password must be exactly 8 characters long, include at least one uppercase letter, and one special character.";
    }
    else {

        // Fetch user
        $stmt = $conn->prepare(
            "SELECT id, full_name, email, password FROM users WHERE email = ?"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        // ✅ ONLY NOW we check password
        if ($user && password_verify($password, $user['password'])) {

            session_regenerate_id(true);

            // 🔐 SESSION THAT INDEXPAGE USES
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['LAST_ACTIVITY'] = time();

            // Remember EMAIL only (safe approach)
            if ($remember) {
                setcookie(
                    "remember_email",
                    $user['email'],
                    time() + (86400 * 30),
                    "/",
                    "",
                    false,
                    true
                );
            } else {
                setcookie("remember_email", "", time() - 3600, "/");
            }

            header("Location: Indexpage.php");
            exit;

        } else {
            $message = "❌ Invalid email or password.";
        }
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Auth | SpendIt</title>
  <style>
  @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap');

  * {
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
  }

  body {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
    background: linear-gradient(-45deg, #3b82f6, #9333ea, #14b8a6, #f43f5e);
    background-size: 400% 400%;
    animation: gradientShift 10s ease infinite;
  }

  @keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
  }

  .container {
    position: relative;
    width: 380px;
    min-height: 460px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.3);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    overflow: hidden;
    transition: transform 0.6s ease, box-shadow 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.3);
  }

  .container:hover {
    transform: scale(1.02);
    box-shadow: 0 0 40px rgba(147, 51, 234, 0.3);
  }

  .form-container {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: 100%;
    padding: 40px;
    transition: all 0.6s ease;
    opacity: 0;
    transform: translateX(20px);
    pointer-events: none;
  }

  /* Show login form by default */
  .container .login-form {
    opacity: 1;
    transform: translateX(0);
    pointer-events: auto;
  }

  /* When toggled, show register form */
  .container.active .login-form {
    left: -100%;
    opacity: 0;
    pointer-events: none;
  }

  .container.active .register-form {
    left: 0;
    opacity: 1;
    transform: translateX(0);
    pointer-events: auto;
  }

  h2 {
    text-align: center;
    margin-bottom: 20px;
    color: #fff;
    font-weight: 600;
  }

  input {
    width: 100%;
    padding: 12px 15px;
    margin: 10px 0;
    border: none;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
    outline: none;
    transition: all 0.3s ease;
  }

  input::placeholder {
    color: rgba(255, 255, 255, 0.7);
  }

  input:focus {
    background: rgba(255, 255, 255, 0.3);
    box-shadow: 0 0 10px rgba(59, 130, 246, 0.8);
  }

  button {
    width: 100%;
    padding: 12px;
    border: none;
    background: linear-gradient(90deg, #3b82f6, #9333ea);
    color: white;
    font-weight: 600;
    border-radius: 10px;
    cursor: pointer;
    transition: 0.3s ease;
    margin-top: 10px;
  }

  button:hover {
    background: linear-gradient(90deg, #9333ea, #3b82f6);
    box-shadow: 0 0 12px rgba(147, 51, 234, 0.6);
    transform: translateY(-2px);
  }

  .switch {
    text-align: center;
    margin-top: 20px;
    font-size: 0.9em;
    cursor: pointer;
    color: #fff;
    text-shadow: 0 0 10px rgba(0,0,0,0.3);
    transition: 0.3s ease;
  }

  .switch:hover {
    text-decoration: underline;
    color: #d1c4e9;
  }

  .message {
    text-align: center;
    font-size: 0.9em;
    color: #fef2f2;
    background: rgba(239, 68, 68, 0.3);
    border-radius: 8px;
    padding: 8px;
    margin-bottom: 10px;
  }

  /* Checkbox style */
  input[type="checkbox"] {
    width: auto;
    margin-right: 8px;
    accent-color: #9333ea;
  }
</style>

</head>
<body>

<div class="container <?php if (isset($_POST['register'])) echo 'active'; ?>" id="authContainer">

  <?php if (!empty($message)): ?>
    <p class="message"><?= $message ?></p>
  <?php endif; ?>

  <!-- Login Form -->
  <div class="form-container login-form">
    <form method="POST">
      <h2>Welcome Back 👋</h2>
      <input
  type="email"
  name="email"
  placeholder="Email"
  value="<?= htmlspecialchars($remembered_email) ?>"
  required
>

    
      <input type="password" name="password" placeholder="Password" required>
      <button type="submit" name="login">Login</button>
      
      

     <label>
  <input type="checkbox" name="remember_me" value="1">
  Remember me
</label>

      <p class="switch" onclick="toggleForm()">Don’t have an account? Register</p>
      <p class="switch"><a href="Forget-Password.php" style="color: #fff; text-decoration: none;">Forgot Password?</a></p>
    </form>
  </div>

  <!-- Register Form -->
  <div class="form-container register-form">
    <form method="POST">
      <h2>Create Account 📝</h2>
      <input type="text" name="full_name" placeholder="Full Name" required>
      <input type="email" name="email" placeholder="Email" required>
      <input type="password" name="password" placeholder="Password" required>
      <button type="submit" name="register">Register</button>
      <p class="switch" onclick="toggleForm()">Already have an account? Login</p>
    </form>
  </div>
</div>

<script>
  const container = document.getElementById('authContainer');
  function toggleForm() {
    container.classList.toggle('active');
  }
</script>

</body>
</html>
