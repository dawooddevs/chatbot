<?php
use App\Csrf;
use App\OpenAi;
use App\Site;

$design = $site['design'];
$ai = $site['ai'];
$tabs = ['general' => 'General', 'design' => 'Design', 'ai' => 'AI & answers', 'embed' => 'Embed code'];
?>
<div class="page-head">
  <div>
    <h1><?= e($site['name']) ?></h1>
    <p><span class="mono"><?= e($site['site_key']) ?></span> · <?= (int)$kbStats['documents'] ?> documents · <?= (int)$kbStats['chunks'] ?> chunks</p>
  </div>
  <div class="actions">
    <a class="btn secondary" href="<?= e(admin_url('knowledge', ['id' => $site['id']])) ?>">Knowledge base</a>
    <a class="btn secondary" target="_blank" rel="noopener" href="<?= e(admin_url('preview', ['id' => $site['id']])) ?>">Live preview</a>
  </div>
</div>

<div class="tabs">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="<?= $tab === $key ? 'active' : '' ?>" href="<?= e(admin_url('site', ['id' => $site['id'], 'tab' => $key])) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'general'): ?>
  <div class="card" style="max-width:640px">
    <h2>General</h2>
    <p class="hint">Basic details and where the widget is allowed to run.</p>
    <form method="post" action="<?= e(admin_url('site')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="save_general">
      <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
      <div class="field">
        <label for="name">Website name</label>
        <input type="text" id="name" name="name" value="<?= e($site['name']) ?>" required>
      </div>
      <div class="field">
        <label for="allowed_domains">Allowed domains</label>
        <textarea id="allowed_domains" name="allowed_domains"><?= e(implode("\n", $site['domains'])) ?></textarea>
        <div class="help">One per line, e.g. <span class="mono">acme.com</span>. Subdomains are included. Empty means any domain may embed this bot.</div>
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

<?php elseif ($tab === 'design'): ?>
  <form method="post" action="<?= e(admin_url('site')) ?>" enctype="multipart/form-data">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save_design">
    <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
    <div class="grid cols-2">
      <div class="card">
        <h2>Wording</h2>
        <p class="hint">What visitors read inside the chat window.</p>
        <div class="field">
          <label for="title">Header title</label>
          <input type="text" id="title" name="title" value="<?= e($design['title']) ?>" maxlength="60">
        </div>
        <div class="field">
          <label for="subtitle">Status line</label>
          <input type="text" id="subtitle" name="subtitle" value="<?= e($design['subtitle']) ?>" maxlength="80"
                 placeholder="Typically replies in a few seconds">
          <div class="help">Sits under the title in the header, next to the green dot.</div>
        </div>
        <div class="field">
          <label for="welcome_message">Welcome message</label>
          <textarea id="welcome_message" name="welcome_message" maxlength="400"><?= e($design['welcome_message']) ?></textarea>
        </div>
        <div class="field">
          <label for="placeholder">Input placeholder</label>
          <input type="text" id="placeholder" name="placeholder" value="<?= e($design['placeholder']) ?>" maxlength="60">
        </div>
        <div class="field">
          <label for="suggestions">Suggested questions</label>
          <textarea id="suggestions" name="suggestions" placeholder="One question per line"><?= e(implode("\n", (array)$design['suggestions'])) ?></textarea>
          <div class="help">Shown as tappable chips before the first reply. One per line, leave empty to hide.</div>
        </div>
        <div class="field">
          <label for="launcher_label">Launcher label</label>
          <input type="text" id="launcher_label" name="launcher_label" value="<?= e($design['launcher_label']) ?>" maxlength="30" placeholder="Leave empty for an icon-only bubble">
        </div>
        <div class="field">
          <label for="launcher_icon">Launcher icon</label>
          <select id="launcher_icon" name="launcher_icon">
            <?php foreach (['chat' => 'Speech bubble', 'question' => 'Question mark', 'sparkle' => 'Sparkle', 'support' => 'Headset'] as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= $design['launcher_icon'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="card">
        <h2>Look &amp; feel</h2>
        <p class="hint">Colours, placement and behaviour on the client site.</p>
        <?php
        $colors = [
          'primary_color' => 'Header / primary colour',
          'text_on_primary' => 'Text on primary colour',
          'bubble_color' => 'Launcher bubble colour',
          'background' => 'Chat background',
          'agent_bubble' => 'Bot message bubble',
          'user_bubble' => 'Visitor message bubble',
        ];
        foreach ($colors as $field => $label): ?>
          <div class="field">
            <label for="<?= e($field) ?>"><?= e($label) ?></label>
            <div class="color-row">
              <input type="color" value="<?= e($design[$field]) ?>"
                     oninput="this.nextElementSibling.value = this.value">
              <input type="text" id="<?= e($field) ?>" name="<?= e($field) ?>" class="mono" value="<?= e($design[$field]) ?>" maxlength="7">
            </div>
          </div>
        <?php endforeach; ?>

        <div class="field">
          <label for="theme">Theme</label>
          <select id="theme" name="theme">
            <option value="light" <?= $design['theme'] === 'light' ? 'selected' : '' ?>>Light</option>
            <option value="dark" <?= $design['theme'] === 'dark' ? 'selected' : '' ?>>Dark</option>
          </select>
        </div>
        <div class="field">
          <label for="font">Font</label>
          <select id="font" name="font">
            <?php foreach (['system' => 'System UI', 'inter' => 'Inter', 'georgia' => 'Georgia (serif)', 'mono' => 'Monospace'] as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= $design['font'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="position">Screen position</label>
          <select id="position" name="position">
            <option value="right" <?= $design['position'] === 'right' ? 'selected' : '' ?>>Bottom right</option>
            <option value="left" <?= $design['position'] === 'left' ? 'selected' : '' ?>>Bottom left</option>
          </select>
        </div>
        <div class="grid" style="grid-template-columns:repeat(3,1fr)">
          <div class="field">
            <label for="offset_x">Side offset (px)</label>
            <input type="number" id="offset_x" name="offset_x" min="0" max="200" value="<?= (int)$design['offset_x'] ?>">
          </div>
          <div class="field">
            <label for="offset_y">Bottom offset (px)</label>
            <input type="number" id="offset_y" name="offset_y" min="0" max="200" value="<?= (int)$design['offset_y'] ?>">
          </div>
          <div class="field">
            <label for="radius">Corner radius</label>
            <input type="number" id="radius" name="radius" min="0" max="30" value="<?= (int)$design['radius'] ?>">
          </div>
        </div>
        <div class="field">
          <label>Header avatar</label>
          <?php if ($design['avatar_url']): ?>
            <div class="actions" style="margin-bottom:10px">
              <img src="<?= e($design['avatar_url']) ?>" alt="" width="44" height="44"
                   style="border-radius:50%;object-fit:cover;border:1px solid var(--border)">
              <label class="checkbox" style="margin:0">
                <input type="checkbox" name="remove_avatar" value="1"> Remove this image
              </label>
            </div>
          <?php endif; ?>
          <input type="file" name="avatar_file" accept=".png,.jpg,.jpeg,.gif,.webp,.svg">
          <div class="help">PNG, JPG, GIF, WEBP or SVG, up to 2 MB. Square images look best.</div>
        </div>
        <div class="field">
          <label for="avatar_url">…or an image URL</label>
          <input type="url" id="avatar_url" name="avatar_url" value="<?= e($design['avatar_url']) ?>" placeholder="https://acme.com/logo.png">
          <div class="help">An uploaded image replaces whatever is here.</div>
        </div>
        <div class="field checkbox">
          <input type="checkbox" id="auto_open" name="auto_open" value="1" <?= $design['auto_open'] ? 'checked' : '' ?>>
          <label for="auto_open" style="margin:0">Open automatically for first-time visitors</label>
        </div>
        <div class="field">
          <label for="auto_open_delay">Auto-open delay (seconds)</label>
          <input type="number" id="auto_open_delay" name="auto_open_delay" min="0" max="120" value="<?= (int)$design['auto_open_delay'] ?>">
        </div>
        <div class="field checkbox">
          <input type="checkbox" id="show_status_dot" name="show_status_dot" value="1" <?= $design['show_status_dot'] ? 'checked' : '' ?>>
          <label for="show_status_dot" style="margin:0">Show the green "online" dot</label>
        </div>
        <div class="field checkbox">
          <input type="checkbox" id="show_reset" name="show_reset" value="1" <?= $design['show_reset'] ? 'checked' : '' ?>>
          <label for="show_reset" style="margin:0">Show the reset button in the header</label>
        </div>
        <div class="field checkbox">
          <input type="checkbox" id="show_attachments" name="show_attachments" value="1" <?= $design['show_attachments'] ? 'checked' : '' ?>>
          <label for="show_attachments" style="margin:0">Let visitors attach files (images, PDF, text — 5 MB)</label>
        </div>
        <div class="field checkbox">
          <input type="checkbox" id="show_voice" name="show_voice" value="1" <?= $design['show_voice'] ? 'checked' : '' ?>>
          <label for="show_voice" style="margin:0">Let visitors record voice messages (transcribed by OpenAI)</label>
        </div>
        <div class="field checkbox">
          <input type="checkbox" id="show_branding" name="show_branding" value="1" <?= $design['show_branding'] ? 'checked' : '' ?>>
          <label for="show_branding" style="margin:0">Show the small "Powered by AI" line</label>
        </div>
      </div>
    </div>
    <div class="actions">
      <button class="btn" type="submit">Save design</button>
      <a class="btn secondary" target="_blank" rel="noopener" href="<?= e(admin_url('preview', ['id' => $site['id']])) ?>">Open preview</a>
    </div>
  </form>

<?php elseif ($tab === 'ai'): ?>
  <form method="post" action="<?= e(admin_url('site')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save_ai">
    <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
    <div class="grid cols-2">
      <div class="card">
        <h2>Model</h2>
        <p class="hint">These calls are billed to the OpenAI credits on your account.</p>
        <div class="field">
          <label for="model">Chat model</label>
          <select id="model" name="model">
            <?php foreach (OpenAi::CHAT_MODELS as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= $ai['model'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="embedding_model">Embedding model</label>
          <select id="embedding_model" name="embedding_model">
            <?php foreach (OpenAi::EMBEDDING_MODELS as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= $ai['embedding_model'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="help">Changing this means re-indexing the knowledge base.</div>
        </div>
        <div class="field">
          <label for="temperature">Creativity (0 = strict, 1.5 = loose)</label>
          <input type="number" step="0.1" min="0" max="1.5" id="temperature" name="temperature" value="<?= e((string)$ai['temperature']) ?>">
        </div>
        <div class="field">
          <label for="max_tokens">Maximum reply length (tokens)</label>
          <input type="number" min="100" max="2000" id="max_tokens" name="max_tokens" value="<?= (int)$ai['max_tokens'] ?>">
        </div>
        <div class="field">
          <label for="rate_limit_per_hour">Messages per visitor per hour</label>
          <input type="number" min="5" max="1000" id="rate_limit_per_hour" name="rate_limit_per_hour" value="<?= (int)$ai['rate_limit_per_hour'] ?>">
          <div class="help">Protects your credits from abuse.</div>
        </div>
      </div>

      <div class="card">
        <h2>Behaviour</h2>
        <p class="hint">How the assistant talks and how much of the knowledge base it sees.</p>
        <div class="field">
          <label for="persona">Persona / system prompt</label>
          <textarea id="persona" name="persona" maxlength="1500"><?= e($ai['persona']) ?></textarea>
        </div>
        <div class="field">
          <label for="fallback">Fallback message</label>
          <textarea id="fallback" name="fallback" maxlength="500"><?= e($ai['fallback']) ?></textarea>
          <div class="help">Used when the answer is not in the knowledge base or OpenAI is unreachable.</div>
        </div>
        <div class="field checkbox">
          <input type="checkbox" id="strict_knowledge" name="strict_knowledge" value="1" <?= $ai['strict_knowledge'] ? 'checked' : '' ?>>
          <label for="strict_knowledge" style="margin:0">Answer only from the knowledge base (recommended)</label>
        </div>
        <div class="field">
          <label for="top_k">Knowledge passages per answer</label>
          <input type="number" min="1" max="12" id="top_k" name="top_k" value="<?= (int)$ai['top_k'] ?>">
        </div>
        <div class="field">
          <label for="min_score">Minimum match score (0–0.9)</label>
          <input type="number" step="0.05" min="0" max="0.9" id="min_score" name="min_score" value="<?= e((string)$ai['min_score']) ?>">
          <div class="help">Higher values keep weak matches out of the prompt.</div>
        </div>
        <div class="field">
          <label for="history_turns">Conversation turns remembered</label>
          <input type="number" min="0" max="20" id="history_turns" name="history_turns" value="<?= (int)$ai['history_turns'] ?>">
        </div>
      </div>
    </div>
    <button class="btn" type="submit">Save AI settings</button>
  </form>

<?php else: ?>
  <div class="card" style="max-width:760px">
    <h2>Embed code</h2>
    <p class="hint">Paste this once before <span class="mono">&lt;/head&gt;</span> (or before <span class="mono">&lt;/body&gt;</span>) on every page of the client website.</p>
    <pre class="code" id="embed-code"><?= e(Site::embedCode($site)) ?></pre>
    <div class="actions" style="margin-top:14px">
      <button class="btn" type="button" onclick="copyEmbed(this)">Copy code</button>
      <a class="btn secondary" target="_blank" rel="noopener" href="<?= e(admin_url('preview', ['id' => $site['id']])) ?>">Test it here</a>
    </div>

    <script>
      function copyEmbed(button) {
        var code = document.getElementById('embed-code').textContent;
        var done = function () {
          button.textContent = 'Copied!';
          setTimeout(function () { button.textContent = 'Copy code'; }, 1600);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(code).then(done, function () { window.prompt('Copy the code:', code); });
        } else {
          window.prompt('Copy the code:', code);
        }
      }
    </script>

    <h2 style="margin-top:26px">WordPress</h2>
    <p class="hint">Appearance → Theme File Editor → <span class="mono">header.php</span>, or any "header scripts" plugin. Paste the same snippet.</p>

    <h2 style="margin-top:26px">Control it from your own page</h2>
    <pre class="code">Chatbot.open();   // open the window
Chatbot.close();  // close it
Chatbot.send('Do you ship to Germany?');
Chatbot.reset();  // clear this visitor's history</pre>

    <h2 style="margin-top:26px">Site key</h2>
    <p class="hint">The key identifies this website. Regenerating it immediately disables the old embed code.</p>
    <p class="mono"><?= e($site['site_key']) ?></p>
    <form method="post" action="<?= e(admin_url('site')) ?>" onsubmit="return confirm('Generate a new key? The current embed code will stop working until you replace it.');">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="regenerate_key">
      <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
      <button class="btn secondary" type="submit">Regenerate key</button>
    </form>
  </div>
<?php endif; ?>
