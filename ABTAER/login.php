<?php
session_start();
include 'API/db_connect.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (empty($user)) {
        $error = "Invalid username or password.";
    } else {
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="Assets/favicon.png">
    <link rel="stylesheet" href="login_register.css">
</head>

<body>
    <div class="container">
        <div class="welcome_login-card">
            <div class="welcome">
                <img src="Assets/Abtaer_logo.jpg" alt="ABTAER Logo">
            </div>
            <div class="login">
                <h2>Login</h2>
                <form action="login.php" method="POST">
                    <input type="text" id="username" name="username" required placeholder="Username: ">
                    <div class="password-wrap">
                        <input type="password" id="password" name="password" required placeholder="Password: ">
                        <button type="button" class="toggle-pw" data-target="password" aria-label="Show password">👁</button>
                    </div>
                    <!-- <button type="button" class="toggle-pw" data-target="password" aria-label="Show password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="20" height="20">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>-->
                    <?php echo '<span style="color:red">' . $error . '</span>'; ?>
                    <button type="submit">Login</button>
                    <p>Not registered? <a href="register.php" class="hover_a">Register here</a></p>
                </form>
            </div>
        </div>
    </div>
    <script>
        document.querySelectorAll('.toggle-pw').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const input = document.getElementById(btn.dataset.target);
                if (!input) return;
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            });
        });
    </script>
</body>

</html>