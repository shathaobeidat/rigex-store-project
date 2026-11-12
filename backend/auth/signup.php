<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/UsersClass.php'; 
$error = '';
$name = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subtn'])) {
    $name = trim($_POST['fname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rpass = $_POST['rpass'] ?? '';
    $role = 'customer';

    if ($name === '' || $email === '' || $password === '' || $rpass === '') {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif ($password !== $rpass) {
        $error = 'Passwords do not match';
    } else {
        try {
            $reg = new UsersClass();
            $result = $reg->register($name, $email, $password, $role);

            if ($result) {
                $user = $reg->login($email, $password);
                if ($user) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    header('Location: ../../frontend/User/index.php');
                    exit();
                }
            }
           
        }
       catch (PDOException $e) {
    if ($e->getCode() === '23000' && ($e->errorInfo[1] ?? 0) == 1062) {
        $error = 'This email is already registered. Try signing in instead.';
    } else {
        error_log($e->getMessage());
        $error = 'Registration failed. Please try again.';
    }
}
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rigx – Create Account</title>
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
      <a href="login.php">Sign In</a>
      <a href="signup.php" class="active" aria-current="page">Register</a>
    </nav>

    <form id="signup" method="post" action="signup.php" novalidate>
      <h1>Join <em>Regix</em></h1>

      <?php if (!empty($error)): ?>
        <div class="alert alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <div class="field">
        <label for="name">Full Name</label>
        <div class="inp">
          <svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
          <input id="name" name="fname" type="text" autocomplete="name" placeholder="Your full name"
                 value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
      </div>

      <div class="field">
        <label for="email">Email</label>
        <div class="inp">
          <svg class="ic" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
          <input id="email" name="email" type="email" autocomplete="email" placeholder="name@gmail.com"
                 value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="inp">
          <svg class="ic" viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
          <input id="password" name="password" type="password" autocomplete="new-password" placeholder="At least 8 characters" required>
        </div>
      </div>

      <div class="field">
        <label for="rpassword">Confirm Password</label>
        <div class="inp">
          <svg class="ic" viewBox="0 0 24 24"><path d="M12 3 4 6v6c0 5 3.4 8 8 9 4.6-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/></svg>
          <input id="rpassword" name="rpass" type="password" autocomplete="new-password" placeholder="Re-enter your password" required>
        </div>
      </div>

      <button class="btn" type="submit" name="subtn" style="margin-top:22px">Create Account <svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
      <p class="foot">Already have an account? <a href="login.php">Sign in</a></p>
    </form>
  </section>
  <div class="trust"><span>Premium quality</span><span>Fast delivery</span><span>24/7 support</span></div>
</main>
</body>
</html>