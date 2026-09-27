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
$titles = $nav + ['site' => 'Website', 'conversation' => 'Conversation'];
$route = $currentRoute ?? 'dashboard';
$pageTitle = isset($site['name']) ? $site['name'] : ($titles[$route] ?? 'Dashboard');
// Pages for a single website or conversation light up their parent section.
$section = ['site' => 'sites', 'conversation' => 'conversations'][$route] ?? $route;
$meta = sprintf(
    '<template id="app-meta" data-title="%s" data-section="%s"></template>',
    e($pageTitle . ' · ' . $platform),
    e($section)
);

// The app shell only needs the page itself; it already has the chrome.
if (is_app_request()) {
    app_flash_header($flashes);
    echo $meta, '<div class="page">', $content, '</div>';
    return;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle) ?> · <?= e($platform) ?></title>
<link rel="stylesheet" href="<?= e(base_url('assets/admin.css')) ?>">
</head>
<body>
<div class="app-progress" id="app-progress"></div>
<div class="shell">
  <nav class="sidebar">
    <a class="brand" href="<?= e(admin_url('dashboard')) ?>"><?= e($platform) ?></a>
    <?php foreach ($nav as $key => $label): ?>
      <a class="nav <?= $section === $key ? 'active' : '' ?>" data-section="<?= e($key) ?>" href="<?= e(admin_url($key)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <div class="spacer"></div>
    <div class="who">
      Signed in as <strong><?= e($currentUser['username'] ?? '') ?></strong><br>
      <a href="<?= e(admin_url('logout')) ?>" style="color:#93c5fd">Sign out</a>
    </div>
  </nav>
  <main class="main" id="app-main">
    <?= $meta ?>
    <div class="page"><?= $content ?></div>
  </main>
</div>
<div class="toasts" id="toasts" aria-live="polite"></div>
<script type="application/json" id="app-flashes"><?= json_encode($flashes, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(base_url('assets/admin.js')) ?>" defer></script>
</body>
</html>
