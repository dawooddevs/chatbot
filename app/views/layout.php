<?php
/** @var string $content */
use App\Session;
use App\Settings;

$flashes = Session::takeFlashes();
$platform = Settings::get('platform_name', 'Chatbot Platform');
$nav = [
    'dashboard' => 'Dashboard',
    'sites' => 'Websites',
    'conversations' => 'Conversations',
    'settings' => 'Settings',
    'profile' => 'My account',
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle ?? 'Dashboard') ?> · <?= e($platform) ?></title>
<link rel="stylesheet" href="<?= e(base_url('assets/admin.css')) ?>">
</head>
<body>
<div class="shell">
  <nav class="sidebar">
    <a class="brand" href="<?= e(admin_url('dashboard')) ?>"><?= e($platform) ?></a>
    <?php foreach ($nav as $route => $label): ?>
      <a class="nav <?= ($currentRoute ?? '') === $route ? 'active' : '' ?>" href="<?= e(admin_url($route)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <div class="spacer"></div>
    <div class="who">
      Signed in as <strong><?= e($currentUser['username'] ?? '') ?></strong><br>
      <a href="<?= e(admin_url('logout')) ?>" style="color:#93c5fd">Sign out</a>
    </div>
  </nav>
  <main class="main">
    <?php foreach ($flashes as $flash): ?>
      <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
  </main>
</div>
</body>
</html>
