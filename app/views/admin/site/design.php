<?php
use App\Csrf;
use App\OpenAi;
use App\Site;

$design = $site['design'];
$ai = $site['ai'];
?>
  <form method="post" action="<?= e(admin_url('site')) ?>" enctype="multipart/form-data">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save_design">
    <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
    <div class="grid cols-2">
      <div class="card">
        <h2>Wording</h2>
        <div class="field">
          <label for="title">Header title</label>
          <input type="text" id="title" name="title" value="<?= e($design['title']) ?>" maxlength="60">
        </div>
        <div class="field">
          <label for="subtitle">Status line</label>
          <input type="text" id="subtitle" name="subtitle" value="<?= e($design['subtitle']) ?>" maxlength="80"
                 placeholder="Typically replies in a few seconds">
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
          <label for="launcher_label">Launcher label</label>
          <input type="text" id="launcher_label" name="launcher_label" value="<?= e($design['launcher_label']) ?>" maxlength="30" placeholder="Leave empty for an icon-only bubble">
        </div>
        <div class="field">
          <label for="launcher_badge">Launcher badge number</label>
          <input type="number" id="launcher_badge" name="launcher_badge" min="0" max="99" value="<?= (int)$design['launcher_badge'] ?>">
          <div class="help">0 hides it. Clears when the chat is opened.</div>
        </div>
        <div class="field">
          <label for="badge_color">Badge colour</label>
          <div class="color-row">
            <input type="color" value="<?= e($design['badge_color']) ?>" oninput="this.nextElementSibling.value = this.value">
            <input type="text" id="badge_color" name="badge_color" class="mono" value="<?= e($design['badge_color']) ?>" maxlength="7">
          </div>
        </div>
        <div class="field">
          <label for="launcher_radius">Launcher corner radius</label>
          <input type="number" id="launcher_radius" name="launcher_radius" min="8" max="27" value="<?= (int)$design['launcher_radius'] ?>">
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
          <div class="help">PNG, JPG, GIF, WEBP or SVG · 2 MB max.</div>
        </div>
        <div class="field">
          <label for="avatar_url">…or an image URL</label>
          <input type="url" id="avatar_url" name="avatar_url" value="<?= e($design['avatar_url']) ?>" placeholder="https://acme.com/logo.png">
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
