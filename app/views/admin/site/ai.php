<?php
use App\Csrf;
use App\OpenAi;
use App\Site;

$design = $site['design'];
$ai = $site['ai'];
?>
  <form method="post" action="<?= e(admin_url('site')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save_ai">
    <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
    <div class="grid cols-2">
      <div class="card">
        <h2>Model</h2>
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
          <div class="help">Changing this needs a re-index.</div>
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
        </div>
      </div>

      <div class="card">
        <h2>Behaviour</h2>
        <div class="field">
          <label for="persona">Persona / system prompt</label>
          <textarea id="persona" name="persona" maxlength="1500"><?= e($ai['persona']) ?></textarea>
        </div>
        <div class="field">
          <label for="fallback">Fallback message</label>
          <textarea id="fallback" name="fallback" maxlength="500"><?= e($ai['fallback']) ?></textarea>
          <div class="help">Used when nothing matches, or OpenAI is unreachable.</div>
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
        </div>
        <div class="field">
          <label for="history_turns">Conversation turns remembered</label>
          <input type="number" min="0" max="20" id="history_turns" name="history_turns" value="<?= (int)$ai['history_turns'] ?>">
        </div>
      </div>
    </div>
    <button class="btn" type="submit">Save AI settings</button>
  </form>
