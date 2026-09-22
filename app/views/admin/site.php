<?php
use App\Site;

$tabs = [
    'general' => 'General',
    'design' => 'Design',
    'ai' => 'AI & answers',
    'embed' => 'Embed code',
    'knowledge' => 'Knowledge base',
];
?>
<div class="page-head">
  <div>
    <h1><?= e($site['name']) ?></h1>
    <p><span class="mono"><?= e($site['site_key']) ?></span> · <?= (int)$kbStats['documents'] ?> documents · <?= (int)$kbStats['chunks'] ?> chunks</p>
  </div>
  <div class="actions">
    <a class="btn secondary" target="_blank" rel="noopener" href="<?= e(admin_url('preview', ['id' => $site['id']])) ?>">Live preview</a>
  </div>
</div>

<div class="tabs" id="site-tabs">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="<?= $tab === $key ? 'active' : '' ?>" data-tab="<?= e($key) ?>"
       href="<?= e(admin_url('site', ['id' => $site['id'], 'tab' => $key])) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="tab-panel" id="tab-panel" data-tab="<?= e($tab) ?>">
  <?= $panel ?>
</div>
