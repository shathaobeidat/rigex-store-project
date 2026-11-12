<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../backend/classes/UsersClass.php';
require_once __DIR__ . '/../layout.php';

$loginUrl = '../../backend/auth/login.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . $loginUrl);
    exit;
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../user/userprofile.php');
    exit;
}
$userId   = (int)$_SESSION['user_id'];
$usersObj = new UsersClass();

$flash  = ['type' => '', 'msg' => ''];
$errors = [];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_profile'])) {
            $name    = trim((string)($_POST['name'] ?? ''));
            $email   = trim((string)($_POST['email'] ?? ''));
            $current = $usersObj->getProfile($userId);
            $address = (string)($current['address_line1'] ?? '');   

            if ($name === '' || mb_strlen($name) > 100) {
                $errors['name'] = 'Enter a valid name (max 100 characters).';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
                $errors['email'] = 'Enter a valid email address.';
            } elseif ($usersObj->emailExists($email, $userId)) {
                $errors['email'] = 'This email is already used by another account.';
            }

            if (!$errors) {
                if ($usersObj->updateProfile($userId, $name, $email, $address)) {
                    $_SESSION['user_name']  = $name;
                    $_SESSION['admin_name'] = $name;
                    $_SESSION['email']      = $email;
                    $flash = ['type' => 'success', 'msg' => 'Profile updated successfully.'];
                } else {
                    $flash = ['type' => 'error', 'msg' => 'Could not update your profile.'];
                }
            } else {
                $flash = ['type' => 'error', 'msg' => 'Please fix the errors below.'];
            }
        }

        if (isset($_POST['change_password'])) {
            $currentPw = (string)($_POST['current_password'] ?? '');
            $new       = (string)($_POST['new_password'] ?? '');
            $confirm   = (string)($_POST['confirm_password'] ?? '');

            if ($currentPw === '') {
                $errors['current_password'] = 'Enter your current password.';
            }
            if (strlen($new) < 8) {
                $errors['new_password'] = 'New password must be at least 8 characters.';
            }
            if ($new !== $confirm) {
                $errors['confirm_password'] = 'Passwords do not match.';
            }

            if (!$errors) {
                if ($usersObj->changePassword($userId, $currentPw, $new)) {
                    session_regenerate_id(true);
                    $flash = ['type' => 'success', 'msg' => 'Password changed successfully.'];
                } else {
                    $errors['current_password'] = 'Current password is incorrect.';
                    $flash = ['type' => 'error', 'msg' => 'Could not change your password.'];
                }
            } else {
                $flash = ['type' => 'error', 'msg' => 'Please fix the errors below.'];
            }
        }
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $flash = ['type' => 'error', 'msg' => 'Server error, please try again.'];
    }
}


$profile = $usersObj->getProfile($userId);
if (!$profile) {
    header('Location: ' . $loginUrl);
    exit;
}


$val = static function (string $key, string $fallback) use ($errors): string {
    return isset($_POST[$key]) && !empty($errors) ? (string)$_POST[$key] : $fallback;
};
$fName  = $val('name', (string)$profile['name']);
$fEmail = $val('email', (string)$profile['email']);

$extraHead = '<link rel="stylesheet" href="../css/admin-profile.css?v=' . filemtime(__DIR__ . '/../css/admin-profile.css') . '">';

page_start('My profile', 'profile.php', $extraHead);

heading('Account', 'My Profile', 'Manage your admin account details and password.');
?>

<?php if ($flash['msg'] !== ''): ?>
  <div class="pf-alert <?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div>
<?php endif; ?>

<div class="admin-profile-page">
<section class="pf-top">
  <div class="pf-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($profile['name'], 0, 1))) ?></div>
  <div>
    <h2><?= e($profile['name']) ?></h2>
    <p><?= e($profile['email']) ?> · Member since <?= e(substr((string)$profile['created_at'], 0, 10)) ?></p>
  </div>
  <div class="pf-tags">
    <span class="badge badge-role-admin"><?= e(ucfirst((string)$profile['role'])) ?></span>
  </div>
</section>

<div class="pf-grid">


  <form class="pf-panel" method="post" novalidate>
    <h3>Personal information</h3>

    <div class="form-group">
      <label for="pfName">Full name</label>
      <input id="pfName" name="name" type="text" class="form-control" maxlength="100" required value="<?= e($fName) ?>">
      <?php if (isset($errors['name'])): ?><span class="pf-err"><?= e($errors['name']) ?></span><?php endif; ?>
    </div>

    <div class="form-group">
      <label for="pfEmail">Email address</label>
      <input id="pfEmail" name="email" type="email" class="form-control" maxlength="150" required value="<?= e($fEmail) ?>">
      <?php if (isset($errors['email'])): ?><span class="pf-err"><?= e($errors['email']) ?></span><?php endif; ?>
    </div>

    <button class="btn-primary" type="submit" name="update_profile" value="1">Save changes</button>
  </form>

  <form class="pf-panel" method="post" autocomplete="off" novalidate>
    <h3>Change password</h3>

    <div class="form-group">
      <label for="pfCurrent">Current password</label>
      <input id="pfCurrent" name="current_password" type="password" class="form-control" autocomplete="current-password" required>
      <?php if (isset($errors['current_password'])): ?><span class="pf-err"><?= e($errors['current_password']) ?></span><?php endif; ?>
    </div>

    <div class="form-group">
      <label for="pfNew">New password</label>
      <input id="pfNew" name="new_password" type="password" class="form-control" minlength="8" autocomplete="new-password" required>
      <?php if (isset($errors['new_password'])): ?><span class="pf-err"><?= e($errors['new_password']) ?></span><?php endif; ?>
    </div>

    <div class="form-group">
      <label for="pfConfirm">Confirm new password</label>
      <input id="pfConfirm" name="confirm_password" type="password" class="form-control" minlength="8" autocomplete="new-password" required>
      <?php if (isset($errors['confirm_password'])): ?><span class="pf-err"><?= e($errors['confirm_password']) ?></span><?php endif; ?>
    </div>

    <button class="btn-primary" type="submit" name="change_password" value="1">Update password</button>
  </form>

</div>

</div>
<?php page_end(); ?>
