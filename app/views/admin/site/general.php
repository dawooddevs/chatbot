<?php
use App\Csrf;
use App\OpenAi;
use App\Site;

$design = $site['design'];
$ai = $site['ai'];
?>
  <div class="card" style="max-width:640px">
    <h2>General</h2>
    <form method="post" action="<?= e(admin_url('site')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="save_general">
      <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
      <div class="field">
        <label for="name">Business Name</label>
        <input type="text" id="name" name="name" value="<?= e($site['name']) ?>" required>
      </div>
      <div class="field">
        <label for="allowed_domains">Allowed domains</label>
        <textarea id="allowed_domains" name="allowed_domains"><?= e(implode("\n", $site['domains'])) ?></textarea>
        <div class="help">One per line, subdomains included. Empty allows any domain.</div>
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="active" <?= $site['status'] === 'active' ? 'selected' : '' ?>>Active — the widget answers visitors</option>
          <option value="paused" <?= $site['status'] === 'paused' ? 'selected' : '' ?>>Paused — the widget stays hidden</option>
        </select>
      </div>
      <button class="btn" type="submit">Save changes</button>
    </form>
  </div>
