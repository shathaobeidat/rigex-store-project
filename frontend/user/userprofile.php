<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../../backend/classes/UsersClass.php';
$loginUrl = '../../backend/auth/login.php';

$usersObj = new UsersClass();
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . $loginUrl);
    exit;
}


$flash = ['type' => '', 'msg' => ''];
$errors = [];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_profile'])) {
            $name    = trim((string)($_POST['name'] ?? ''));
            $email   = trim((string)($_POST['email'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));

            if ($name === '' || mb_strlen($name) > 100) {
                $errors['name'] = 'Enter a valid name (max 100 characters).';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
                $errors['email'] = 'Enter a valid email address.';
            } elseif ($usersObj->emailExists($email, $userId)) {
                $errors['email'] = 'This email is already used by another account.';
            }
            if (mb_strlen($address) > 255) {
                $errors['address'] = 'Address is too long (max 255 characters).';
            }

            if (!$errors) {
                if ($usersObj->updateProfile($userId, $name, $email, $address)) {
                    $_SESSION['user_name'] = $name;
                    $_SESSION['email']     = $email;
                    $flash = ['type' => 'success', 'msg' => 'Profile updated successfully.'];
                } else {
                    $flash = ['type' => 'error', 'msg' => 'Could not update your profile.'];
                }
            } else {
                $flash = ['type' => 'error', 'msg' => 'Please fix the errors below.'];
            }
        }

        if (isset($_POST['change_password'])) {
            $current = (string)($_POST['current_password'] ?? '');
            $new     = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');

            if ($current === '') {
                $errors['current_password'] = 'Enter your current password.';
            }
            if (strlen($new) < 8) {
                $errors['new_password'] = 'New password must be at least 8 characters.';
            }
            if ($new !== $confirm) {
                $errors['confirm_password'] = 'Passwords do not match.';
            }

            if (!$errors) {
                if ($usersObj->changePassword($userId, $current, $new)) {
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
$orders = $usersObj->getUserOrders($userId, 10);


$val = static function (string $key, string $fallback) use ($errors): string {
    return isset($_POST[$key]) && !empty($errors) ? (string)$_POST[$key] : $fallback;
};
$fName    = $val('name', (string)$profile['name']);
$fEmail   = $val('email', (string)$profile['email']);
$fAddress = $val('address', (string)($profile['address_line1'] ?? ''));

$initials = strtoupper(mb_substr($profile['name'], 0, 1));

store_page_start('My profile');
?>
<link rel="stylesheet" href="../css/userprofile.css?v=<?= filemtime(__DIR__ . '/../css/userprofile.css') ?>">
<div class="regix-profile">
<a class="profile-back" href="index.php">← Back to store</a>

<section class="profile-head">
    <div class="avatar" aria-hidden="true"><?= e($initials) ?></div>
    <div>
        <p class="eyebrow">MY ACCOUNT</p>
        <h1><?= e($profile['name']) ?></h1>
        <p class="muted"><?= e($profile['email']) ?> · Member since <?= e(substr((string)$profile['created_at'], 0, 10)) ?></p>
    </div>
    <a class="button ghost" href="../../backend/auth/logout.php">Sign out <?= store_icon('arrow') ?></a>
</section>

<?php if ($flash['msg'] !== ''): ?>
    <div class="alert <?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div>
<?php endif; ?>

<section class="profile-stats">
    <div class="stat"><span>ORDERS</span><strong><?= number_format($profile['orders_count']) ?></strong></div>
    <div class="stat"><span>TOTAL SPENT</span><strong><?= number_format($profile['total_spent'], 2) ?> JOD</strong></div>
</section>

<div class="profile-grid">


    <form class="panel" method="post" novalidate>
        <h2>Personal information</h2>
        <p class="panel-description">Your details, ready for your next upgrade.</p>

        <label for="name">Full name</label>
        <input id="name" name="name" type="text" maxlength="100" required value="<?= e($fName) ?>">
        <?php if (isset($errors['name'])): ?><small class="err"><?= e($errors['name']) ?></small><?php endif; ?>

        <label for="email">Email</label>
        <input id="email" name="email" type="email" maxlength="150" required value="<?= e($fEmail) ?>">
        <?php if (isset($errors['email'])): ?><small class="err"><?= e($errors['email']) ?></small><?php endif; ?>

        <label for="address">Delivery address</label>
        <input id="address" name="address" type="text" maxlength="255" value="<?= e($fAddress) ?>">
        <?php if (isset($errors['address'])): ?><small class="err"><?= e($errors['address']) ?></small><?php endif; ?>

        <button class="button" type="submit" name="update_profile" value="1">Save changes</button>
    </form>

    
    <form class="panel" method="post" autocomplete="off" novalidate>
        <h2>Account security</h2>
        <p class="panel-description">Use a unique password with at least 8 characters.</p>

        <label for="current_password">Current password</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
        <?php if (isset($errors['current_password'])): ?><small class="err"><?= e($errors['current_password']) ?></small><?php endif; ?>

        <label for="new_password">New password</label>
        <input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password" required>
        <?php if (isset($errors['new_password'])): ?><small class="err"><?= e($errors['new_password']) ?></small><?php endif; ?>

        <label for="confirm_password">Confirm new password</label>
        <input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required>
        <?php if (isset($errors['confirm_password'])): ?><small class="err"><?= e($errors['confirm_password']) ?></small><?php endif; ?>

        <button class="button" type="submit" name="change_password" value="1">Update password</button>
    </form>
</div>


<section class="panel orders-panel">
    <h2>Recent orders</h2>
    <p class="panel-description">Your latest orders and their current status.</p>
    <?php if (!$orders): ?>
        <div class="profile-empty"><?= store_icon('box') ?><h3>Your next build starts here</h3><p class="muted">You haven't placed any orders yet.</p><a class="button" href="index.php#rigs">Explore builds <?= store_icon('arrow') ?></a></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Order</th><th>Date</th><th>Status</th><th>Total</th></tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>#<?= (int)$o['id'] ?></td>
                        <td><?= e($o['created_at']) ?></td>
                        <td><span class="pill <?= e(strtolower($o['status'])) ?>"><?= e($o['status']) ?></span></td>
                        <td><?= number_format($o['total_jod'], 2) ?> JOD</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

</div>
<?php store_page_end(); ?>
