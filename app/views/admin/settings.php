<?php
use App\Csrf;
use App\OpenAi;
?>
<div class="page-head">
  <div>
    <h1>Settings</h1>
    <p>Platform-wide options. Per-website options live under each website.</p>
  </div>
</div>

<?php if ($testResult !== null): ?>
  <div class="alert <?= $testResult['ok'] ? 'success' : 'error' ?>"><?= e($testResult['message']) ?></div>
<?php endif; ?>

<div class="grid cols-2">
  <div class="card">
    <h2>OpenAI</h2>

    <?php if ($fromEnv): ?>
      <div class="alert info">A key is being supplied by the <span class="mono">OPENAI_API_KEY</span> environment variable, which overrides the one stored here.</div>
    <?php endif; ?>

    <form method="post" action="<?= e(admin_url('settings')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="save">
      <div class="field">
        <label for="openai_api_key">API key</label>
        <input type="password" id="openai_api_key" name="openai_api_key" autocomplete="off"
               placeholder="<?= $hasKey ? e($maskedKey) : 'sk-…' ?>">
        <div class="help"><?= $hasKey ? 'A key is stored. Leave this empty to keep it.' : 'Create one at platform.openai.com → API keys.' ?></div>
      </div>
      <div class="field">
        <label for="openai_base_url">API base URL</label>
        <input type="text" id="openai_base_url" name="openai_base_url" value="<?= e((string)$baseUrl) ?>">
        <div class="help">For an OpenAI-compatible proxy.</div>
      </div>
      <div class="field">
        <label for="default_chat_model">Default model for new websites</label>
        <select id="default_chat_model" name="default_chat_model">
          <?php foreach (OpenAi::CHAT_MODELS as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $defaultModel === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="platform_name">Platform name</label>
        <input type="text" id="platform_name" name="platform_name" value="<?= e((string)$platformName) ?>" maxlength="60">
      </div>
      <button class="btn" type="submit">Save settings</button>
    </form>
  </div>

  <div>
    <div class="card">
      <h2>Connection test</h2>
      <form method="post" action="<?= e(admin_url('settings')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="test">
        <button class="btn secondary" type="submit">Test OpenAI connection</button>
      </form>
    </div>

    <div class="card">
      <h2>Maintenance</h2>
      <div class="actions">
        <form method="post" action="<?= e(admin_url('settings')) ?>">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="prune">
          <button class="btn secondary" type="submit">Clear old rate-limit rows</button>
        </form>
        <?php if ($hasKey && !$fromEnv): ?>
          <form method="post" action="<?= e(admin_url('settings')) ?>" onsubmit="return confirm('Remove the stored API key? All bots will fall back to their fallback message.');">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="clear_key">
            <button class="btn danger" type="submit">Remove stored API key</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <h2>Server</h2>
      <div class="table-wrap"><table>
        <tbody>
          <tr><th>PHP</th><td><?= e(PHP_VERSION) ?></td></tr>
          <tr><th>cURL</th><td><?= function_exists('curl_init') ? 'available' : 'missing (using stream fallback)' ?></td></tr>
          <tr><th>Install path</th><td class="mono"><?= e(APP_ROOT) ?></td></tr>
          <tr><th>Base URL</th><td class="mono"><?= e(base_url()) ?></td></tr>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
