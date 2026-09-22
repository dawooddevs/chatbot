<?php use App\Csrf; ?>
<div class="page-head">
  <div>
    <h1>My account</h1>
    <p>Sign-in details for <strong><?= e($user['username']) ?></strong>.</p>
  </div>
</div>

<?php if ((int)$user['must_change_password'] === 1): ?>
  <div class="alert warning">You are still using the password that was set up for you. Choose a new one to unlock the rest of the panel.</div>
<?php endif; ?>

<?php foreach ($errors as $error): ?>
  <div class="alert error"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="grid cols-2">
  <div class="card">
    <h2>Change password</h2>
    <form method="post" action="<?= e(admin_url('profile')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="password">
      <div class="field">
        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
      </div>
      <div class="field">
        <label for="new_password">New password</label>
        <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="10" required>
      </div>
      <div class="field">
        <label for="confirm_password">Repeat new password</label>
        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="10" required>
      </div>
      <button class="btn" type="submit">Update password</button>
    </form>
  </div>

  <div class="card">
    <h2>Account details</h2>
    <form method="post" action="<?= e(admin_url('profile')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="account">
      <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" value="<?= e($user['username']) ?>" disabled>
      </div>
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e((string)$user['email']) ?>">
      </div>
      <div class="field">
        <label>Last sign-in</label>
        <p class="muted" style="margin:0"><?= e($user['last_login_at'] ? time_ago($user['last_login_at']) : 'this is your first session') ?></p>
      </div>
      <button class="btn secondary" type="submit">Save details</button>
    </form>
  </div>
</div>
