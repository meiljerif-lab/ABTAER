<?php
session_start();
include 'API/db_connect.php';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"] ?? '');
    $password = $_POST["password"] ?? '';

    if (empty($username)) {
        $error = "Username is required";
    } elseif (empty($password)) {
        $error = "Password is required";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    }
    if (empty($error)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $error = "Username already taken.";
        }
    }
    if (empty($error)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt2 = $conn->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt2->bind_param('ss', $username, $hash);
        if ($stmt2->execute()) {

            $newUserId = $conn->insert_id;

            $savingsStmt = $conn->prepare("INSERT INTO savings (user_id, balance) VALUES (?, 0)");
            $savingsStmt->bind_param('i', $newUserId);
            $savingsStmt->execute();

            $_SESSION['user_id'] = $newUserId;
            header("Location: index.php");
            exit;
        } else {
            $error = "Could not create account. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
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
                <h2>Register</h2>
                <form method="POST">
                    <input type="text" id="username" name="username" required placeholder="Username: ">
                    <div class="password-wrap">
                        <input type="password" id="password" name="password" required placeholder="Password: ">
                        <button type="button" class="toggle-pw" data-target="password" aria-label="Show password">👁</button>
                    </div>
                    <?php if ($error): ?>
                        <span style="color:red"><?= htmlspecialchars($error) ?></span>
                    <?php endif; ?>
                    <button type="submit">Register</button>
                    <p>Already registered? <a href="login.php" class="hover_a">Login here</a></p>
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