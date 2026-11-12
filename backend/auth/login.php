<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/UsersClass.php'; 
$error = '';
if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['login_btn'])) {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        $userObj = new UsersClass();
        $user = $userObj->login($email, $password);
        if ($user) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            if ($user['role'] === 'customer') {
                header("Location: ../../frontend/User/index.php");
                exit();
            } elseif ($user['role'] === 'admin') {
                header("Location: ../../frontend/admin/dashboard.php");
                exit();
            }

            header("Location: ../../frontend/User/index.php");
            exit();
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="utf-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title> regix – Sign In</title>

<link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<link rel="icon" type="image/svg+xml" href="../../frontend/assets/favicon.svg">
<link rel="stylesheet" href="../../frontend/css/auth.css">

</head>

<body>

<div class="ring" aria-hidden="true"></div>

<main class="wrap">

  <section class="card">

<a class="logo" href="login.php" aria-label="REGIX home">
      <img src="../../frontend/assets/regix-logo.svg" alt="REGIX — Next level shopping" width="200" height="48">
    </a>
   <nav class="tabs" aria-label="Account">
  <a href="login.php" class="active" aria-current="page">Sign In</a>
  <a href="signup.php">Register</a>
</nav>

    <form id="login" novalidate action="login.php" method="post">

      <h1>Welcome <em>back</em></h1>



      <?php if (!empty($error)): ?>

        <div class="alert alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>

      <?php endif; ?>

      <div class="field">

        <label for="email">Email</label>

        <div class="inp">

          <svg class="ic" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>

          <input id="email" name="email" type="email" autocomplete="email" placeholder="name@gmail.com" required>

        </div>

      </div>



      <div class="field">

        <label for="password">Password</label>

        <div class="inp">

          <svg class="ic" viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>

          <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>

          <button type="button" class="eye" aria-label="Show password"><svg viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button>

        </div>

 

      </div>



      <div class="row">


      </div>



      <button class="btn" type="submit" name="login_btn">Sign In <svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>

 

      <p class="foot">New to Rigx? <a href="signup.php">Create an account</a></p>

    </form>

  </section>

  <div class="trust"><span>Premium quality</span><span>Fast delivery</span><span>24/7 support</span></div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const eyeButtons = document.querySelectorAll('.eye');
    eyeButtons.forEach(button => {
        button.addEventListener('click', function() {
    
            const passwordInput = this.previousElementSibling;
  
            if (passwordInput && passwordInput.tagName === 'INPUT') {
        
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
            }
        });
    });
});
</script>
</body>
</html>
