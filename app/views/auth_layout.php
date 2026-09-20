<?php
use App\Session;
use App\Settings;

$flashes = Session::takeFlashes();
$platform = Settings::get('platform_name', 'Chatbot Platform');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Sign in · <?= e($platform) ?></title>
<link rel="stylesheet" href="<?= e(base_url('assets/admin.css')) ?>">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <?php foreach ($flashes as $flash): ?>
      <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
  </div>
</div>
</body>
</html>
