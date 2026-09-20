<?php
use App\Csrf;
use App\Settings;
?>
<h1>Sign in</h1>
<p class="sub">Manage your website chatbots.</p>

<?php if (!empty($error)): ?>
  <div class="alert error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(admin_url('login')) ?>">
  <?= Csrf::field() ?>
  <div class="field">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" autocomplete="username" autofocus required>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
  </div>
  <button class="btn" type="submit" style="width:100%;justify-content:center">Sign in</button>
</form>
